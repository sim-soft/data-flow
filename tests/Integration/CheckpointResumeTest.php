<?php

namespace Simsoft\DataFlow\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * CheckpointResumeTest class.
 *
 * Regression coverage for the point of checkpoint/resume: after a crash, the
 * rows already written must not be written a second time.
 *
 * Rows were previously discarded after the final stage had already run, so a
 * resumed pipeline re-executed every load while reporting only the remaining
 * rows as processed — duplicating each side effect it was meant to avoid.
 */
#[CoversNothing]
class CheckpointResumeTest extends TestCase
{
    /** @var string Path to the checkpoint file under test. */
    private string $checkpointPath;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->checkpointPath = $dir . DIRECTORY_SEPARATOR . 'resume_test.json';
        $this->removeCheckpoint();
    }

    protected function tearDown(): void
    {
        $this->removeCheckpoint();
        parent::tearDown();
    }

    /**
     * Delete the checkpoint file if present.
     *
     * @return void
     */
    private function removeCheckpoint(): void
    {
        if (file_exists($this->checkpointPath)) {
            unlink($this->checkpointPath);
        }
    }

    #[Test]
    public function resumedRunDoesNotRepeatLoadedRows(): void
    {
        $loaded = [];

        // First run: crash at row 55, after the checkpoint at row 50 is written.
        try {
            (new DataFlow())
                ->from(range(1, 100))
                ->withCheckpoint($this->checkpointPath, interval: 10)
                ->load(function (int $row) use (&$loaded): void {
                    if ($row === 55) {
                        throw new RuntimeException('simulated crash');
                    }
                    $loaded[] = $row;
                })
                ->run();
            $this->fail('Expected the simulated crash to propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated crash', $exception->getMessage());
        }

        $this->assertFileExists($this->checkpointPath);
        $this->assertSame(range(1, 54), $loaded);

        // Second run: resume. Rows 1-50 were checkpointed, so the loader must
        // only see 51-100. (51-54 are replayed: they ran but were never
        // checkpointed, so at-least-once delivery is the documented guarantee.)
        $resumed = [];

        $result = (new DataFlow())
            ->from(range(1, 100))
            ->withCheckpoint($this->checkpointPath, interval: 10)
            ->resume()
            ->load(function (int $row) use (&$resumed): void {
                $resumed[] = $row;
            })
            ->run();

        $this->assertSame(range(51, 100), $resumed);
        $this->assertSame(50, $result->getProcessedRows());
    }

    #[Test]
    public function processedRowCountMatchesRowsActuallyLoaded(): void
    {
        // Seed a checkpoint by crashing partway through.
        try {
            (new DataFlow())
                ->from(range(1, 30))
                ->withCheckpoint($this->checkpointPath, interval: 10)
                ->load(function (int $row): void {
                    if ($row === 25) {
                        throw new RuntimeException('stop');
                    }
                })
                ->run();
        } catch (RuntimeException) {
            // expected
        }

        $loadCount = 0;

        $result = (new DataFlow())
            ->from(range(1, 30))
            ->withCheckpoint($this->checkpointPath, interval: 10)
            ->resume()
            ->load(function () use (&$loadCount): void {
                $loadCount++;
            })
            ->run();

        $this->assertSame($loadCount, $result->getProcessedRows());
    }

    #[Test]
    public function checkpointIsRemovedAfterSuccessfulCompletion(): void
    {
        (new DataFlow())
            ->from(range(1, 20))
            ->withCheckpoint($this->checkpointPath, interval: 5)
            ->load(fn(int $row) => $row)
            ->run();

        $this->assertFileDoesNotExist($this->checkpointPath);
    }

    #[Test]
    public function resumeWithoutACheckpointProcessesEveryRow(): void
    {
        $loaded = [];

        $result = (new DataFlow())
            ->from(range(1, 10))
            ->withCheckpoint($this->checkpointPath, interval: 5)
            ->resume()
            ->load(function (int $row) use (&$loaded): void {
                $loaded[] = $row;
            })
            ->run();

        $this->assertSame(range(1, 10), $loaded);
        $this->assertSame(10, $result->getProcessedRows());
    }
}
