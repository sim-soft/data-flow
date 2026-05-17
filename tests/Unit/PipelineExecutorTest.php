<?php

namespace Simsoft\DataFlow\Tests\Unit;

use ArrayIterator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use RuntimeException;
use Simsoft\DataFlow\CheckpointManager;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Extractor;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Metrics\NullMetricsExporter;
use Simsoft\DataFlow\PipelineExecutor;
use Simsoft\DataFlow\PipelineResult;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformer;

#[CoversClass(PipelineExecutor::class)]
class PipelineExecutorTest extends TestCase
{
    private NullLogger $logger;
    private DeadLetterCollection $deadLetters;

    /** @var string[] Temp files to clean up */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new NullLogger();
        $this->deadLetters = new DeadLetterCollection();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
            // Also clean up .tmp files
            if (file_exists($file . '.tmp')) {
                @unlink($file . '.tmp');
            }
        }
        parent::tearDown();
    }

    #[Test]
    public function empty_pipeline_returns_zero_processed_rows(): void
    {
        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
        );

        $result = $executor->execute([]);

        $this->assertInstanceOf(PipelineResult::class, $result);
        $this->assertSame(0, $result->getProcessedRows());
        $this->assertSame(0, $result->getFailedRows());
        $this->assertEmpty($result->getStageMetrics());
    }

    #[Test]
    public function single_extractor_stage_processes_all_rows(): void
    {
        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('test-extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                yield ['id' => 1, 'name' => 'Alice'];
                yield ['id' => 2, 'name' => 'Bob'];
                yield ['id' => 3, 'name' => 'Charlie'];
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
        );

        $result = $executor->execute([$extractor]);

        $this->assertSame(3, $result->getProcessedRows());
        $this->assertSame(0, $result->getFailedRows());
    }

    #[Test]
    public function multi_stage_pipeline_flows_rows_through_all_stages(): void
    {
        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                yield ['id' => 1, 'value' => 10];
                yield ['id' => 2, 'value' => 20];
            }
        };

        $transformer = new class extends Transformer {
            public function __construct()
            {
                $this->withName('doubler');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    $row['value'] *= 2;
                    yield $row;
                }
            }
        };

        $loaded = [];
        $loader = new class($loaded) extends Loader {
            public function __construct(private array &$loaded)
            {
                $this->withName('collector');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    $this->loaded[] = $row;
                    yield $row;
                }
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
        );

        $result = $executor->execute([$extractor, $transformer, $loader]);

        $this->assertSame(2, $result->getProcessedRows());
        $this->assertCount(2, $loaded);
        $this->assertSame(20, $loaded[0]['value']);
        $this->assertSame(40, $loaded[1]['value']);
    }

    #[Test]
    public function checkpoint_writing_at_configured_intervals(): void
    {
        $checkpointPath = $this->createTempFile();
        $checkpointManager = new CheckpointManager($checkpointPath, interval: 2);

        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                for ($i = 1; $i <= 5; $i++) {
                    yield ['id' => $i];
                }
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            checkpointManager: $checkpointManager,
        );

        $result = $executor->execute([$extractor]);

        // Checkpoint file should be deleted on successful completion
        $this->assertFileDoesNotExist($checkpointPath);
        $this->assertSame(5, $result->getProcessedRows());
    }

    #[Test]
    public function checkpoint_resume_skips_rows_up_to_last_row_index(): void
    {
        $checkpointPath = $this->createTempFile();
        $checkpointManager = new CheckpointManager($checkpointPath, interval: 2);

        // Create an extractor with a known name for pipeline ID generation
        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('resume-extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                for ($i = 1; $i <= 5; $i++) {
                    yield ['id' => $i];
                }
            }
        };

        // Generate the pipeline ID the same way PipelineExecutor does
        $pipelineId = hash('sha256', json_encode(['resume-extractor'], JSON_THROW_ON_ERROR));

        // Write a checkpoint indicating we already processed up to row 3
        $checkpointManager->write($pipelineId, 3, 'resume-extractor');

        $this->assertFileExists($checkpointPath);

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            checkpointManager: $checkpointManager,
            shouldResume: true,
        );

        $result = $executor->execute([$extractor]);

        // Should only process rows 4 and 5 (skipping rows 1-3)
        $this->assertSame(2, $result->getProcessedRows());
    }

    #[Test]
    public function checkpoint_deleted_on_successful_completion(): void
    {
        $checkpointPath = $this->createTempFile();
        $checkpointManager = new CheckpointManager($checkpointPath, interval: 1);

        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                yield ['id' => 1];
                yield ['id' => 2];
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            checkpointManager: $checkpointManager,
        );

        $executor->execute([$extractor]);

        $this->assertFileDoesNotExist($checkpointPath);
    }

    #[Test]
    public function pipeline_id_mismatch_starts_from_beginning(): void
    {
        $checkpointPath = $this->createTempFile();
        $checkpointManager = new CheckpointManager($checkpointPath, interval: 100);

        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('mismatch-extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                for ($i = 1; $i <= 4; $i++) {
                    yield ['id' => $i];
                }
            }
        };

        // Write a checkpoint with a different pipeline ID
        $checkpointManager->write('wrong-pipeline-id', 2, 'other-stage');

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            checkpointManager: $checkpointManager,
            shouldResume: true,
        );

        $result = $executor->execute([$extractor]);

        // Should process all 4 rows since pipeline ID doesn't match
        $this->assertSame(4, $result->getProcessedRows());
    }

    #[Test]
    public function metrics_exporter_records_pipeline_events(): void
    {
        $metricsExporter = $this->createMock(MetricsExporter::class);

        $metricsExporter->expects($this->atLeastOnce())
            ->method('recordRowProcessed');

        $metricsExporter->expects($this->atLeastOnce())
            ->method('recordStageDuration');

        $metricsExporter->expects($this->once())
            ->method('recordPipelineComplete');

        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('metrics-extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                yield ['id' => 1];
                yield ['id' => 2];
            }
        };

        $transformer = new class extends Transformer {
            public function __construct()
            {
                $this->withName('metrics-transformer');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    yield $row;
                }
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            metricsExporter: $metricsExporter,
        );

        $executor->execute([$extractor, $transformer]);
    }

    #[Test]
    public function progress_callback_invoked_at_configured_intervals(): void
    {
        $progressCalls = [];

        $onProgress = function (int $processedRows, float $elapsedMs) use (&$progressCalls) {
            $progressCalls[] = ['rows' => $processedRows, 'elapsed' => $elapsedMs];
        };

        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('progress-extractor');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                for ($i = 1; $i <= 5; $i++) {
                    yield ['id' => $i];
                }
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onProgress: $onProgress,
            progressInterval: 2,
        );

        $executor->execute([$extractor]);

        // Progress should be called at row 2, row 4, and a tail call at row 5
        $this->assertNotEmpty($progressCalls);

        // First call should be at 2 rows processed
        $this->assertSame(2, $progressCalls[0]['rows']);

        // Second call should be at 4 rows processed
        $this->assertSame(4, $progressCalls[1]['rows']);

        // Tail call at 5 rows
        $this->assertSame(5, $progressCalls[2]['rows']);
    }

    #[Test]
    public function pipeline_result_contains_expected_properties(): void
    {
        $extractor = new class extends Extractor {
            public function __construct()
            {
                $this->withName('result-extractor');
                $this->withErrorStrategy(ErrorStrategy::Skip);
                $this->withCircuitBreaker(failureThreshold: 5, cooldownMs: 60000);
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                yield ['id' => 1];
                yield ['id' => 2];
                yield ['id' => 3];
            }
        };

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: $this->deadLetters,
        );

        $result = $executor->execute([$extractor]);

        // Verify PipelineResult properties
        $this->assertSame(3, $result->getProcessedRows());
        $this->assertSame(0, $result->getFailedRows());
        $this->assertGreaterThan(0, $result->getDurationMs());
        $this->assertNotNull($result->getStartTime());
        $this->assertNotNull($result->getEndTime());
        $this->assertFalse($result->isDryRun());
        $this->assertNotEmpty($result->getStageMetrics());

        // Stage metrics
        $stageMetrics = $result->getStageMetrics();
        $this->assertSame('result-extractor', $stageMetrics[0]->stageName);
        $this->assertSame(3, $stageMetrics[0]->rowsExited);
        $this->assertGreaterThan(0, $stageMetrics[0]->durationMs);

        // Circuit states should include the extractor's circuit breaker
        $circuitStates = $result->getCircuitStates();
        $this->assertArrayHasKey('result-extractor', $circuitStates);
    }

    // --- Helper methods ---

    private function createTempFile(): string
    {
        $path = sys_get_temp_dir() . '/dataflow_test_' . uniqid() . '.json';
        $this->tempFiles[] = $path;
        return $path;
    }
}
