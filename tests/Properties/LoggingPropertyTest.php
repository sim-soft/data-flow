<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;
use Stringable;

/**
 * LoggingPropertyTest
 *
 * Property-based tests for logging behavior in the StageRunner.
 * Validates Properties 10-12 from the design document using randomized inputs.
 *
 * Feature: production-readiness
 */
#[CoversClass(StageRunner::class)]
class LoggingPropertyTest extends TestCase
{
    /**
     * A test logger that captures all log entries for assertion.
     *
     * @return object{logs: array, log: callable}
     */
    private function createTestLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            /** @var array<int, array{level: string, message: string, context: array}> */
            public array $logs = [];

            public function emergency(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::EMERGENCY, $message, $context);
            }

            public function alert(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ALERT, $message, $context);
            }

            public function critical(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::CRITICAL, $message, $context);
            }

            public function error(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::ERROR, $message, $context);
            }

            public function warning(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::WARNING, $message, $context);
            }

            public function notice(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::NOTICE, $message, $context);
            }

            public function info(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::INFO, $message, $context);
            }

            public function debug(string|Stringable $message, array $context = []): void
            {
                $this->log(LogLevel::DEBUG, $message, $context);
            }

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [
                    'level' => (string)$level,
                    'message' => (string)$message,
                    'context' => $context,
                ];
            }
        };
    }

    /**
     * Create a processor that passes through all rows successfully.
     */
    private function createPassThroughProcessor(string $name): Processor
    {
        $processor = new class extends Processor {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                return $dataFrame ?? new ArrayIterator([]);
            }
        };
        $processor->withName($name);

        return $processor;
    }

    /**
     * Create a processor that throws on specific row indices.
     *
     * @param string $name The processor name.
     * @param array<int> $failIndices Zero-based indices of rows that should fail.
     */
    private function createFailingProcessor(string $name, array $failIndices): Processor
    {
        $processor = new class($failIndices) extends Processor {
            private int $callCount = 0;

            public function __construct(private readonly array $failIndices)
            {
                // No parent constructor to call
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                if ($dataFrame === null) {
                    return new ArrayIterator([]);
                }

                $results = [];
                foreach ($dataFrame as $row) {
                    $this->callCount++;
                    if (in_array($this->callCount - 1, $this->failIndices, true)) {
                        throw new RuntimeException("Simulated failure at call {$this->callCount}");
                    }
                    $results[] = $row;
                }

                return new ArrayIterator($results);
            }
        };
        $processor->withName($name);

        return $processor;
    }

    /**
     * Data provider generating 50+ random stage names and row counts for boundary logging tests.
     */
    public static function stageBoundaryLoggingProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $stageName = 'Stage_' . bin2hex(random_bytes(random_int(3, 8)));
            $rowCount = random_int(1, 30);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'value' => 'data_' . random_int(100, 9999)];
            }
            yield "name={$stageName},rows={$rowCount},i={$i}" => [$stageName, $rows];
        }
    }

    /**
     * Property 10: Stage Boundary Logging
     *
     * For any stage in the pipeline, a debug-level log message containing the stage name
     * SHALL be emitted when the stage begins processing, and an info-level log message
     * containing the stage name and the row count SHALL be emitted when the stage completes.
     *
     * **Validates: Requirements 7.1, 7.2**
     */
    #[Test]
    #[DataProvider('stageBoundaryLoggingProvider')]
    public function stageBoundaryLogging(string $stageName, array $rows): void
    {
        $logger = $this->createTestLogger();
        $runner = new StageRunner();
        $processor = $this->createPassThroughProcessor($stageName);
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $output = $runner->run(
            $processor,
            $input,
            ErrorStrategy::Skip,
            null,
            $logger,
            $deadLetters,
            null,
        );

        // Consume the generator to trigger all logging
        iterator_to_array($output);

        // Find debug-level log at start containing stage name
        $debugLogs = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::DEBUG
                && str_contains($entry['message'], $stageName),
        );

        $this->assertNotEmpty(
            $debugLogs,
            "A debug-level log containing stage name '{$stageName}' must be emitted when stage begins",
        );

        // Find info-level log at completion containing stage name and row count
        $infoLogs = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::INFO
                && str_contains($entry['message'], $stageName)
                && str_contains($entry['message'], (string)count($rows)),
        );

        $this->assertNotEmpty(
            $infoLogs,
            "An info-level log containing stage name '{$stageName}' and row count "
            . count($rows) . " must be emitted when stage completes",
        );
    }

    /**
     * Data provider generating 50+ random failure scenarios for failure logging tests.
     */
    public static function failureLoggingProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $stageName = 'FailStage_' . bin2hex(random_bytes(random_int(2, 6)));
            $rowCount = random_int(3, 20);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'name' => 'item_' . random_int(100, 9999)];
            }
            // Pick a random row index to fail (0-based for the failIndices array)
            $failIndex = random_int(0, $rowCount - 1);
            yield "stage={$stageName},rows={$rowCount},fail={$failIndex},i={$i}" => [
                $stageName,
                $rows,
                $failIndex,
            ];
        }
    }

    /**
     * Property 11: Failure Logging at Appropriate Levels
     *
     * For any row that fails processing in a stage, the pipeline SHALL emit both
     * an error-level log (containing stage name, exception message, and row index)
     * and a warning-level log (containing row index, stage name, and exception message).
     *
     * **Validates: Requirements 7.3, 8.1**
     */
    #[Test]
    #[DataProvider('failureLoggingProvider')]
    public function failureLoggingAtAppropriateLevels(string $stageName, array $rows, int $failIndex): void
    {
        $logger = $this->createTestLogger();
        $runner = new StageRunner();
        $processor = $this->createFailingProcessor($stageName, [$failIndex]);
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $output = $runner->run(
            $processor,
            $input,
            ErrorStrategy::Skip,
            null,
            $logger,
            $deadLetters,
            null,
        );

        // Consume the generator
        iterator_to_array($output);

        // The row index in the StageRunner is 1-based (incremented before processing)
        $expectedRowIndex = $failIndex + 1;

        // Find error-level log containing stage name, exception message, and row index
        $errorLogs = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::ERROR
                && str_contains($entry['message'], $stageName)
                && str_contains($entry['message'], (string)$expectedRowIndex),
        );

        $this->assertNotEmpty(
            $errorLogs,
            "An error-level log containing stage name '{$stageName}' and row index {$expectedRowIndex} "
            . "must be emitted when a row fails",
        );

        // Verify error log contains exception message
        $errorLog = array_values($errorLogs)[0];
        $this->assertStringContainsString(
            'Simulated failure',
            $errorLog['message'],
            "Error-level log must contain the exception message",
        );

        // Find warning-level log containing row index, stage name, and exception message
        $warningLogs = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::WARNING
                && str_contains($entry['message'], $stageName)
                && str_contains($entry['message'], (string)$expectedRowIndex)
                && str_contains($entry['message'], 'Simulated failure'),
        );

        $this->assertNotEmpty(
            $warningLogs,
            "A warning-level log containing row index {$expectedRowIndex}, stage name '{$stageName}', "
            . "and exception message must be emitted when a row fails",
        );
    }

    /**
     * Data provider generating 50+ random row data for debug context tests.
     */
    public static function rowDataDebugContextProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $stageName = 'DebugStage_' . bin2hex(random_bytes(random_int(2, 5)));
            // Generate random row data
            $row = [
                'id' => random_int(1, 10000),
                'name' => 'user_' . bin2hex(random_bytes(random_int(3, 8))),
                'email' => 'test_' . random_int(1, 999) . '@example.com',
                'score' => random_int(0, 100),
            ];
            yield "stage={$stageName},i={$i}" => [$stageName, $row];
        }
    }

    /**
     * Property 12: Row Data Appears in Debug Context Only
     *
     * For any row failure, the warning-level log message context SHALL NOT contain
     * the full row data in a 'row' key, but the debug-level log context array SHALL
     * include the complete row data in a 'row' key.
     *
     * **Validates: Requirements 8.2, 8.3**
     */
    #[Test]
    #[DataProvider('rowDataDebugContextProvider')]
    public function rowDataAppearsInDebugContextOnly(string $stageName, array $row): void
    {
        $logger = $this->createTestLogger();
        $runner = new StageRunner();

        // Create a processor that always fails on the first row
        $processor = $this->createFailingProcessor($stageName, [0]);
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator([$row]);

        $output = $runner->run(
            $processor,
            $input,
            ErrorStrategy::Skip,
            null,
            $logger,
            $deadLetters,
            null,
        );

        // Consume the generator
        iterator_to_array($output);

        // Find warning-level logs for this failure
        $warningLogs = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::WARNING,
        );

        $this->assertNotEmpty($warningLogs, "At least one warning-level log must be emitted on failure");

        // Warning-level log context SHALL NOT contain 'row' key with full row data
        foreach ($warningLogs as $warningLog) {
            $this->assertArrayNotHasKey(
                'row',
                $warningLog['context'],
                "Warning-level log context must NOT contain the full row data in a 'row' key",
            );
        }

        // Find debug-level logs that contain row data context
        $debugLogsWithRow = array_filter(
            $logger->logs,
            fn(array $entry) => $entry['level'] === LogLevel::DEBUG
                && array_key_exists('row', $entry['context']),
        );

        $this->assertNotEmpty(
            $debugLogsWithRow,
            "At least one debug-level log must include the row data in context",
        );

        // Debug-level log context SHALL include the complete row data
        $debugLog = array_values($debugLogsWithRow)[0];
        $this->assertSame(
            $row,
            $debugLog['context']['row'],
            "Debug-level log context 'row' key must contain the complete original row data",
        );
    }
}
