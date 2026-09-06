<?php

namespace Simsoft\DataFlow\Tests\Integration;

use ArrayIterator;
use Generator;
use Iterator;
use RuntimeException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformer;

/**
 * KeyPreservationTest class.
 *
 * Regression coverage for row keys surviving the executor.
 *
 * Stages receive the row key as their second argument, and loaders such as
 * SpoutLoader dispatch on it (the key selects the destination worksheet). When
 * the executor renumbered keys from zero, a generator yielding 'Profile' and
 * 'Address' rows collapsed into a single sheet with misaligned columns.
 */
#[CoversNothing]
class KeyPreservationTest extends TestCase
{
    /**
     * A transformer that passes rows through untouched, preserving keys.
     */
    private function passthroughTransformer(): Transformer
    {
        return new class extends Transformer {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame ?? new ArrayIterator([]) as $key => $row) {
                    yield $key => $row;
                }
            }
        };
    }

    /**
     * Source data keyed by worksheet name, as documented in 02-USEFUL_PROCESSORS.
     *
     * @return Generator<string, array<string, mixed>>
     */
    private function sheetedSource(): Generator
    {
        yield 'Profile' => ['name' => 'John Doe', 'age' => 20];
        yield 'Profile' => ['name' => 'Jane Doe', 'age' => 21];
        yield 'Address' => ['street' => '123 Main St', 'city' => 'Anytown'];
        yield 'Address' => ['street' => '456 Main St', 'city' => 'Anytown'];
    }

    #[Test]
    public function stringKeysReachTheLoader(): void
    {
        $keys = [];

        (new DataFlow())
            ->from($this->sheetedSource())
            ->load(function (array $row, int|string $key) use (&$keys): void {
                $keys[] = $key;
            })
            ->run();

        $this->assertSame(['Profile', 'Profile', 'Address', 'Address'], $keys);
    }

    #[Test]
    public function stringKeysSurviveATransformStage(): void
    {
        $keys = [];

        (new DataFlow())
            ->from($this->sheetedSource())
            ->transform($this->passthroughTransformer())
            ->load(function (array $row, int|string $key) use (&$keys): void {
                $keys[] = $key;
            })
            ->run();

        $this->assertSame(['Profile', 'Profile', 'Address', 'Address'], $keys);
    }

    #[Test]
    public function stringKeysSurviveNonThrowErrorStrategies(): void
    {
        // Non-Throw strategies route through StageRunner's per-row isolation,
        // which wraps each row in its own iterator. The key must survive that.
        $keys = [];

        (new DataFlow())
            ->from($this->sheetedSource())
            ->transform($this->passthroughTransformer()->withErrorStrategy(ErrorStrategy::Skip))
            ->load(function (array $row, int|string $key) use (&$keys): void {
                $keys[] = $key;
            })
            ->run();

        $this->assertSame(['Profile', 'Profile', 'Address', 'Address'], $keys);
    }

    #[Test]
    public function stringKeysSurviveTheRetryStrategy(): void
    {
        $attempts = 0;

        $flaky = new class($attempts) extends Transformer {
            public function __construct(private int &$attempts)
            {
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame ?? new ArrayIterator([]) as $key => $row) {
                    if ($key === 'Address' && $this->attempts++ === 0) {
                        throw new RuntimeException('transient');
                    }

                    yield $key => $row;
                }
            }
        };

        $keys = [];

        (new DataFlow())
            ->from($this->sheetedSource())
            ->transform($flaky->withErrorStrategy(ErrorStrategy::Retry))
            ->load(function (array $row, int|string $key) use (&$keys): void {
                $keys[] = $key;
            })
            ->run();

        // The retried row keeps its original key rather than being renumbered.
        $this->assertSame(['Profile', 'Profile', 'Address', 'Address'], $keys);
    }

    #[Test]
    public function integerKeysRemainSequentialForListSources(): void
    {
        $keys = [];

        (new DataFlow())
            ->from([10, 20, 30])
            ->load(function (int $row, int|string $key) use (&$keys): void {
                $keys[] = $key;
            })
            ->run();

        $this->assertSame([0, 1, 2], $keys);
    }
}
