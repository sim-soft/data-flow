<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Payload;
use Simsoft\DataFlow\Extractors\IterableExtractor;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\Chunk;
use Simsoft\DataFlow\Transformers\Filter;

/**
 * TransformerPropertiesTest class
 *
 * Property-based tests for transformer classes using randomized inputs.
 * Each property test uses a DataProvider generating 100+ random test cases
 * to verify universal invariants.
 */
class TransformerPropertiesTest extends TestCase
{
    /**
     * Data provider generating 100+ random inputs with varying array sizes and chunk sizes.
     *
     * @return Generator
     */
    public static function chunkCountProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $size = random_int(1, 100);
            $input = range(1, $size);
            $chunkSize = random_int(1, 50);
            yield "size={$size},chunk={$chunkSize},i={$i}" => [$input, $chunkSize];
        }
    }

    /**
     * Property 1: Chunk Count Preservation
     *
     * For any non-empty input array and for any positive integer chunk size,
     * the total number of items across all chunks produced by the Chunk transformer
     * SHALL equal the number of items in the original input.
     *
     * **Validates: Requirements 10.6**
     *
     * @param array $input The input array to chunk.
     * @param int $chunkSize The chunk size to use.
     * @return void
     */
    #[Test]
    #[DataProvider('chunkCountProvider')]
    public function chunkCountPreservation(array $input, int $chunkSize): void
    {
        $chunk = new Chunk($chunkSize);
        $dataFrame = new ArrayIterator($input);

        $result = $this->iteratorToArray($chunk($dataFrame));

        $totalItems = 0;
        foreach ($result as $batch) {
            $totalItems += count($batch);
        }

        $this->assertSame(
            count($input),
            $totalItems,
            "Total items across all chunks must equal original input count. "
            . "Input size: " . count($input) . ", Chunk size: {$chunkSize}"
        );
    }

    /**
     * Data provider generating 100+ random inputs for filter key preservation tests.
     *
     * Generates arrays with explicit string keys and a random predicate index threshold.
     *
     * @return \Generator
     */
    public static function filterKeyPreservationProvider(): Generator
    {
        for ($i = 0; $i < 110; $i++) {
            $size = random_int(1, 50);
            $input = [];
            for ($j = 0; $j < $size; $j++) {
                $key = 'key_' . $j . '_' . random_int(100, 999);
                $input[$key] = random_int(-1000, 1000);
            }
            $threshold = random_int(-1000, 1000);
            yield "size={$size},threshold={$threshold},i={$i}" => [$input, $threshold];
        }
    }

    /**
     * Data provider generating 100+ random inputs for filter metamorphic count tests.
     *
     * Generates arrays of varying sizes with a random threshold for filtering.
     *
     * @return \Generator
     */
    public static function filterMetamorphicCountProvider(): Generator
    {
        for ($i = 0; $i < 110; $i++) {
            $size = random_int(0, 100);
            $input = [];
            for ($j = 0; $j < $size; $j++) {
                $input[] = random_int(-1000, 1000);
            }
            $threshold = random_int(-1000, 1000);
            yield "size={$size},threshold={$threshold},i={$i}" => [$input, $threshold];
        }
    }

    /**
     * Property 2: Filter Key Preservation
     *
     * For any input array with explicit keys and for any predicate closure,
     * the set of keys in the Filter transformer's output SHALL be a subset
     * of the keys in the input.
     *
     * **Validates: Requirements 11.5**
     *
     * @param array $input The input array with explicit keys.
     * @param int $threshold The threshold value for the filter predicate.
     * @return void
     */
    #[Test]
    #[DataProvider('filterKeyPreservationProvider')]
    public function filterOutputKeysAreSubsetOfInputKeys(array $input, int $threshold): void
    {
        $filter = new Filter(fn($value) => $value > $threshold);
        $iterator = new ArrayIterator($input);

        $result = $this->iteratorToArray($filter($iterator));

        $inputKeys = array_keys($input);
        $outputKeys = array_keys($result);

        // Every output key must exist in the input keys (subset relationship)
        $difference = array_diff($outputKeys, $inputKeys);
        $this->assertEmpty(
            $difference,
            sprintf(
                'Output keys [%s] are not present in input keys',
                implode(', ', $difference)
            )
        );
    }

    /**
     * Property 3: Filter Metamorphic Count
     *
     * For any input array and for any predicate closure, the number of items
     * yielded by the Filter transformer SHALL be less than or equal to the
     * number of items in the input.
     *
     * **Validates: Requirements 11.6**
     *
     * @param array $input The input array.
     * @param int $threshold The threshold value for the filter predicate.
     * @return void
     */
    #[Test]
    #[DataProvider('filterMetamorphicCountProvider')]
    public function filterOutputCountIsLessThanOrEqualToInputCount(array $input, int $threshold): void
    {
        $filter = new Filter(fn($value) => $value > $threshold);
        $iterator = new ArrayIterator($input);

        $result = $this->iteratorToArray($filter($iterator));

        $this->assertLessThanOrEqual(
            count($input),
            count($result),
            sprintf(
                'Filter output count (%d) exceeds input count (%d)',
                count($result),
                count($input)
            )
        );
    }

    /**
     * Data provider generating 100+ random arrays for IterableExtractor round-trip tests.
     *
     * Generates arrays with varying sizes and mixed value types (integers, strings, floats, booleans, nulls).
     *
     * @return Generator
     */
    public static function iterableExtractorRoundTripProvider(): Generator
    {
        for ($i = 0; $i < 110; $i++) {
            $size = random_int(0, 50);
            $input = [];
            for ($j = 0; $j < $size; $j++) {
                $type = random_int(0, 4);
                $input[] = match ($type) {
                    0 => random_int(-1000, 1000),
                    1 => 'str_' . random_int(0, 9999),
                    2 => random_int(-1000, 1000) / 10.0,
                    3 => (bool)random_int(0, 1),
                    4 => null,
                };
            }
            yield "size={$size},i={$i}" => [$input];
        }
    }

    /**
     * Property 6: IterableExtractor Round-Trip
     *
     * For any array of values, constructing an IterableExtractor with that array
     * and invoking it SHALL yield an Iterator containing exactly the same items
     * in the same order.
     *
     * **Validates: Requirements 5.4**
     *
     * @param array $input The input array to extract.
     * @return void
     */
    #[Test]
    #[DataProvider('iterableExtractorRoundTripProvider')]
    public function iterableExtractorRoundTrip(array $input): void
    {
        $extractor = new IterableExtractor($input);
        $result = $this->iteratorToArray($extractor());

        $this->assertSame(
            $input,
            array_values($result),
            sprintf(
                'IterableExtractor output does not match input. Input size: %d, Output size: %d',
                count($input),
                count($result)
            )
        );
    }

    /**
     * Data provider generating 100+ random attribute sets with random modifications
     * for Payload reset round-trip tests.
     *
     * Each case provides initial attributes and a sequence of modifications (set/unset operations).
     *
     * @return Generator
     */
    public static function payloadResetRoundTripProvider(): Generator
    {
        for ($i = 0; $i < 110; $i++) {
            // Generate random initial attributes
            $attrCount = random_int(0, 10);
            $initialAttributes = [];
            for ($j = 0; $j < $attrCount; $j++) {
                $key = 'attr_' . $j;
                $initialAttributes[$key] = match (random_int(0, 3)) {
                    0 => random_int(-1000, 1000),
                    1 => bin2hex(random_bytes(random_int(1, 10))),
                    2 => (bool)random_int(0, 1),
                    3 => null,
                };
            }

            // Generate random modifications (set and unset operations)
            $modCount = random_int(1, 15);
            $modifications = [];
            for ($k = 0; $k < $modCount; $k++) {
                $opType = random_int(0, 2);
                if ($opType === 0 && $attrCount > 0) {
                    // Unset an existing key
                    $modifications[] = ['unset', 'attr_' . random_int(0, $attrCount - 1)];
                } elseif ($opType === 1) {
                    // Set a new key
                    $modifications[] = ['set', 'new_' . $k, random_int(-500, 500)];
                } else {
                    // Overwrite an existing or new key
                    $key = $attrCount > 0 ? 'attr_' . random_int(0, $attrCount - 1) : 'new_' . $k;
                    $modifications[] = ['set', $key, 'modified_' . random_int(0, 999)];
                }
            }

            yield "attrs={$attrCount},mods={$modCount},i={$i}" => [$initialAttributes, $modifications];
        }
    }

    /**
     * Property 5: Payload Reset Round-Trip
     *
     * For any set of initial attributes and for any sequence of modifications
     * (set, unset operations), calling reset() on the Payload SHALL restore
     * all attributes to the initial constructor state.
     *
     * **Validates: Requirements 3.10, 3.2**
     *
     * @param array $initialAttributes The initial attributes for the Payload.
     * @param array $modifications The sequence of modifications to apply.
     * @return void
     */
    #[Test]
    #[DataProvider('payloadResetRoundTripProvider')]
    public function payloadResetRestoresInitialState(array $initialAttributes, array $modifications): void
    {
        $payload = new Payload($initialAttributes);

        // Apply modifications
        foreach ($modifications as $modification) {
            if ($modification[0] === 'unset') {
                unset($payload->{$modification[1]});
            } else {
                $payload->{$modification[1]} = $modification[2];
            }
        }

        // Reset and verify initial state is restored
        $payload->reset();

        // Verify all initial attributes are restored
        foreach ($initialAttributes as $key => $value) {
            $this->assertSame(
                $value,
                $payload->getAttribute($key),
                "After reset(), attribute '{$key}' should be restored to its initial value"
            );
        }

        // Verify newly added keys during modifications are removed
        foreach ($modifications as $modification) {
            if ($modification[0] === 'set' && !array_key_exists($modification[1], $initialAttributes)) {
                $this->assertNull(
                    $payload->getAttribute($modification[1]),
                    "After reset(), attribute '{$modification[1]}' added during modifications should not exist"
                );
            }
        }
    }

    /**
     * Data provider generating 100+ random non-empty arrays for identity pipeline round-trip tests.
     *
     * Generates arrays with varying sizes and mixed value types (integers, strings, floats).
     *
     * @return Generator
     */
    public static function identityPipelineRoundTripProvider(): Generator
    {
        for ($i = 0; $i < 110; $i++) {
            $size = random_int(1, 50);
            $input = [];
            for ($j = 0; $j < $size; $j++) {
                $type = random_int(0, 2);
                $input[] = match ($type) {
                    0 => random_int(-1000, 1000),
                    1 => 'str_' . random_int(0, 9999),
                    2 => random_int(-1000, 1000) / 10.0,
                };
            }
            yield "size={$size},i={$i}" => [$input];
        }
    }

    /**
     * Property 4: Identity Pipeline Round-Trip
     *
     * For any non-empty array of values, passing the array through a DataFlow
     * pipeline with an identity transformation (returning each item unchanged)
     * SHALL produce output equal to the input.
     *
     * **Validates: Requirements 21.7**
     *
     * @param array $input The non-empty input array.
     * @return void
     */
    #[Test]
    #[DataProvider('identityPipelineRoundTripProvider')]
    public function identityPipelineRoundTrip(array $input): void
    {
        $collected = [];

        $flow = new DataFlow();
        $flow->from($input);
        $flow->transform(fn(mixed $data) => $data);
        $flow->load(function (mixed $data) use (&$collected) {
            $collected[] = $data;
            return $data;
        });
        $flow->run();

        $this->assertSame(
            $input,
            $collected,
            sprintf(
                'Identity pipeline output does not match input. Input size: %d, Output size: %d',
                count($input),
                count($collected)
            )
        );
    }
}
