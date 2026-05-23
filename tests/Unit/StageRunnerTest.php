<?php

namespace Simsoft\DataFlow\Tests\Unit;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use RuntimeException;
use Simsoft\DataFlow\CircuitBreakerConfig;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\Enums\CircuitState;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Extractor;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Metrics\NullMetricsExporter;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformer;

#[CoversClass(StageRunner::class)]
class StageRunnerTest extends TestCase
{
    private NullLogger $logger;
    private DeadLetterCollection $deadLetters;
    private StageRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new NullLogger();
        $this->deadLetters = new DeadLetterCollection();
        $this->runner = new StageRunner();
    }

    #[Test]
    public function throw_strategy_propagates_exception_immediately(): void
    {
        $stage = $this->createFailingTransformer('Stage failed');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stage failed');

        $input = new ArrayIterator([['id' => 1]]);
        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Throw,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        // Consume the generator to trigger the exception
        iterator_to_array($output);
    }

    #[Test]
    public function skip_strategy_skips_failing_rows_and_passes_successful_ones(): void
    {
        $stage = $this->createConditionalTransformer(
            failOn: fn(mixed $row) => $row['id'] === 2,
        );

        $input = new ArrayIterator([
            ['id' => 1, 'value' => 'a'],
            ['id' => 2, 'value' => 'b'],
            ['id' => 3, 'value' => 'c'],
        ]);

        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]['id']);
        $this->assertSame(3, $results[1]['id']);

        // Failure recorded in dead letters
        $this->assertSame(1, $this->deadLetters->count());
        $entries = $this->deadLetters->toArray();
        $this->assertSame(['id' => 2, 'value' => 'b'], $entries[0]->row);
    }

    #[Test]
    public function log_and_continue_passes_original_row_on_failure(): void
    {
        $stage = $this->createConditionalTransformer(
            failOn: fn(mixed $row) => $row['id'] === 2,
        );

        $input = new ArrayIterator([
            ['id' => 1, 'value' => 'a'],
            ['id' => 2, 'value' => 'b'],
            ['id' => 3, 'value' => 'c'],
        ]);

        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::LogAndContinue,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        // All 3 rows pass through (failing row passes with original data)
        $this->assertCount(3, $results);
        $this->assertSame(['id' => 2, 'value' => 'b'], $results[1]);

        // Failure still recorded in dead letters
        $this->assertSame(1, $this->deadLetters->count());
    }

    #[Test]
    public function retry_strategy_succeeds_on_later_attempt(): void
    {
        $attempts = 0;
        $stage = new class($attempts) extends Transformer {
            private int $callCount = 0;

            public function __construct(private int &$attempts)
            {
                $this->withName('retry-stage');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    $this->callCount++;
                    $this->attempts = $this->callCount;
                    if ($this->callCount < 3) {
                        throw new RuntimeException('Transient error');
                    }
                    yield $row;
                }
            }
        };

        $retryConfig = new RetryConfig(maxAttempts: 3, delay: 0, exponential: false, maxDelay: 1);

        $input = new ArrayIterator([['id' => 1]]);
        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Retry,
            retryConfig: $retryConfig,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        $this->assertCount(1, $results);
        $this->assertSame(['id' => 1], $results[0]);
        $this->assertSame(0, $this->deadLetters->count());
    }

    #[Test]
    public function retry_exhaustion_sends_row_to_dead_letters(): void
    {
        $stage = $this->createFailingTransformer('Persistent error');
        $stage->withName('exhaustion-stage');

        $retryConfig = new RetryConfig(maxAttempts: 2, delay: 0, exponential: false, maxDelay: 1);

        $input = new ArrayIterator([['id' => 1]]);
        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Retry,
            retryConfig: $retryConfig,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        $this->assertCount(0, $results);
        $this->assertSame(1, $this->deadLetters->count());

        $entry = $this->deadLetters->toArray()[0];
        $this->assertSame(['id' => 1], $entry->row);
        $this->assertSame('Persistent error', $entry->exception->getMessage());
    }

    #[Test]
    public function circuit_breaker_skips_rows_after_failure_threshold(): void
    {
        $stage = $this->createFailingTransformer('fail');
        $stage->withName('cb-stage');
        $stage->withCircuitBreaker(failureThreshold: 2, cooldownMs: 60000);

        $input = new ArrayIterator([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
        ]);

        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        // No rows succeed (all fail or are skipped by circuit breaker)
        $this->assertCount(0, $results);

        // First 2 rows fail normally (triggering circuit open), rows 3-4 skipped by circuit
        $this->assertSame(4, $this->deadLetters->count());

        $entries = $this->deadLetters->toArray();
        // Rows 3 and 4 should have "circuit-open" reason
        $this->assertSame('circuit-open', $entries[2]->exception->getMessage());
        $this->assertSame('circuit-open', $entries[3]->exception->getMessage());
    }

    #[Test]
    public function circuit_breaker_recovery_after_cooldown(): void
    {
        // Use a 1ms cooldown so it expires quickly
        $stage = $this->createConditionalTransformer(
            failOn: fn(mixed $row) => $row['id'] <= 2,
        );
        $stage->withName('cb-recovery-stage');
        $stage->withCircuitBreaker(failureThreshold: 2, cooldownMs: 1);

        // First pass: trigger circuit open
        $input1 = new ArrayIterator([
            ['id' => 1],
            ['id' => 2],
        ]);

        $output1 = $this->runner->run(
            stage: $stage,
            input: $input1,
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );
        iterator_to_array($output1, false);

        // Circuit should be Open now
        $states = $this->runner->getCircuitStates();
        $this->assertSame(CircuitState::Open, $states['cb-recovery-stage']);

        // Wait for cooldown to elapse
        usleep(2000); // 2ms

        // Second pass: circuit should transition to HalfOpen and allow probe call
        $input2 = new ArrayIterator([
            ['id' => 3], // This should succeed (probe call)
        ]);

        $output2 = $this->runner->run(
            stage: $stage,
            input: $input2,
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );
        $results = iterator_to_array($output2, false);

        $this->assertCount(1, $results);
        $this->assertSame(['id' => 3], $results[0]);

        // Circuit should be back to Closed after successful probe
        $states = $this->runner->getCircuitStates();
        $this->assertSame(CircuitState::Closed, $states['cb-recovery-stage']);
    }

    #[Test]
    public function extractor_stage_handles_null_input(): void
    {
        $stage = new class extends Extractor {
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

        $output = $this->runner->run(
            stage: $stage,
            input: null,
            strategy: ErrorStrategy::Throw,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );

        $results = iterator_to_array($output, false);

        $this->assertCount(3, $results);
        $this->assertSame('Alice', $results[0]['name']);
        $this->assertSame('Bob', $results[1]['name']);
        $this->assertSame('Charlie', $results[2]['name']);
    }

    #[Test]
    public function get_circuit_states_returns_correct_final_states(): void
    {
        // Stage with circuit breaker that stays Closed
        $stageA = $this->createPassthroughTransformer();
        $stageA->withName('stage-a');
        $stageA->withCircuitBreaker(failureThreshold: 5, cooldownMs: 60000);

        // Stage with circuit breaker that opens
        $stageB = $this->createFailingTransformer('fail');
        $stageB->withName('stage-b');
        $stageB->withCircuitBreaker(failureThreshold: 2, cooldownMs: 60000);

        // Run stage A (all succeed → Closed)
        $outputA = $this->runner->run(
            stage: $stageA,
            input: new ArrayIterator([['id' => 1], ['id' => 2]]),
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );
        iterator_to_array($outputA, false);

        // Run stage B (all fail → Open)
        $outputB = $this->runner->run(
            stage: $stageB,
            input: new ArrayIterator([['id' => 1], ['id' => 2]]),
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
        );
        iterator_to_array($outputB, false);

        $states = $this->runner->getCircuitStates();

        $this->assertArrayHasKey('stage-a', $states);
        $this->assertArrayHasKey('stage-b', $states);
        $this->assertSame(CircuitState::Closed, $states['stage-a']);
        $this->assertSame(CircuitState::Open, $states['stage-b']);
    }

    #[Test]
    public function metrics_exporter_records_row_failed_for_skipped_rows(): void
    {
        $metricsExporter = $this->createMock(MetricsExporter::class);
        $metricsExporter->expects($this->once())
            ->method('recordRowFailed')
            ->with(
                'metrics-stage',
                $this->callback(static fn(\Throwable $error): bool => $error->getMessage() === 'Row error'),
            );

        $stage = $this->createFailingTransformer('Row error');
        $stage->withName('metrics-stage');

        $input = new ArrayIterator([['id' => 1]]);
        $output = $this->runner->run(
            stage: $stage,
            input: $input,
            strategy: ErrorStrategy::Skip,
            retryConfig: null,
            logger: $this->logger,
            deadLetters: $this->deadLetters,
            onError: null,
            metricsExporter: $metricsExporter,
        );

        iterator_to_array($output, false);
    }

    // --- Helper methods ---

    private function createFailingTransformer(string $message): Transformer
    {
        return new class($message) extends Transformer {
            public function __construct(private readonly string $message)
            {
                $this->withName('failing-stage');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    throw new RuntimeException($this->message);
                }
            }
        };
    }

    private function createPassthroughTransformer(): Transformer
    {
        return new class extends Transformer {
            public function __construct()
            {
                $this->withName('passthrough-stage');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    yield $row;
                }
            }
        };
    }

    private function createConditionalTransformer(callable $failOn): Transformer
    {
        return new class($failOn) extends Transformer {
            public function __construct(private $failOn)
            {
                $this->withName('conditional-stage');
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                foreach ($dataFrame as $row) {
                    if (($this->failOn)($row)) {
                        throw new RuntimeException('Conditional failure');
                    }
                    yield $row;
                }
            }
        };
    }
}
