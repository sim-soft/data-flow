<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\BetweenRule;
use Simsoft\DataFlow\Rules\MaxRule;
use Simsoft\DataFlow\Rules\MinRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * NumericBoundRulesPropertyTest
 *
 * Feature: enterprise-resilience, Property 19: Numeric bound rules
 *
 * For any numeric value V and bounds M, N: `min:N` passes iff V >= N;
 * `max:N` passes iff V <= N; `between:M,N` passes iff M <= V <= N.
 *
 * **Validates: Requirements 8.6, 8.7, 8.8**
 */
#[CoversClass(MinRule::class)]
#[CoversClass(MaxRule::class)]
#[CoversClass(BetweenRule::class)]
class NumericBoundRulesPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random numeric value (int or float).
     */
    private function randomNumericValue(): int|float
    {
        return random_int(0, 1) === 0
            ? random_int(-100000, 100000)
            : random_int(-100000, 100000) / 100.0;
    }

    /**
     * Generate a random bound value (float).
     */
    private function randomBound(): float
    {
        return random_int(-50000, 50000) / 100.0;
    }

    /**
     * Generate a random non-numeric value.
     */
    private function randomNonNumericValue(): mixed
    {
        $type = random_int(0, 3);

        return match ($type) {
            0 => 'abc' . \bin2hex(\random_bytes(3)),
            1 => (bool)random_int(0, 1),
            2 => null,
            3 => [random_int(0, 10)],
        };
    }

    /**
     * Property 19: MinRule passes iff is_numeric($value) && (float)$value >= min
     *
     * For any random numeric value and any random min bound, MinRule::passes()
     * SHALL return true if and only if the value is numeric and >= min.
     *
     * **Validates: Requirements 8.6**
     */
    #[Test]
    public function minRulePassesIffNumericValueIsGreaterThanOrEqualToMin(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $min = $this->randomBound();
            $rule = new MinRule($min);
            $value = $this->randomNumericValue();

            $expected = \is_numeric($value) && (float)$value >= $min;
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'MinRule(%f)::passes(%s) must return %s (iteration %d)',
                    $min,
                    \var_export($value, true),
                    $expected ? 'true' : 'false',
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: MinRule always fails for non-numeric values
     *
     * For any non-numeric value, MinRule::passes() SHALL return false regardless of min bound.
     *
     * **Validates: Requirements 8.6**
     */
    #[Test]
    public function minRuleFailsForNonNumericValues(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $min = $this->randomBound();
            $rule = new MinRule($min);
            $value = $this->randomNonNumericValue();

            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'MinRule(%f) must fail for non-numeric value %s (iteration %d)',
                    $min,
                    \get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: MaxRule passes iff is_numeric($value) && (float)$value <= max
     *
     * For any random numeric value and any random max bound, MaxRule::passes()
     * SHALL return true if and only if the value is numeric and <= max.
     *
     * **Validates: Requirements 8.7**
     */
    #[Test]
    public function maxRulePassesIffNumericValueIsLessThanOrEqualToMax(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $max = $this->randomBound();
            $rule = new MaxRule($max);
            $value = $this->randomNumericValue();

            $expected = \is_numeric($value) && (float)$value <= $max;
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'MaxRule(%f)::passes(%s) must return %s (iteration %d)',
                    $max,
                    \var_export($value, true),
                    $expected ? 'true' : 'false',
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: MaxRule always fails for non-numeric values
     *
     * For any non-numeric value, MaxRule::passes() SHALL return false regardless of max bound.
     *
     * **Validates: Requirements 8.7**
     */
    #[Test]
    public function maxRuleFailsForNonNumericValues(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $max = $this->randomBound();
            $rule = new MaxRule($max);
            $value = $this->randomNonNumericValue();

            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'MaxRule(%f) must fail for non-numeric value %s (iteration %d)',
                    $max,
                    \get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: BetweenRule passes iff is_numeric($value) && min <= (float)$value <= max
     *
     * For any random numeric value and any random min/max bounds (where min <= max),
     * BetweenRule::passes() SHALL return true if and only if the value is numeric
     * and falls within [min, max].
     *
     * **Validates: Requirements 8.8**
     */
    #[Test]
    public function betweenRulePassesIffNumericValueIsWithinBounds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = $this->randomBound();
            $b = $this->randomBound();
            $min = \min($a, $b);
            $max = \max($a, $b);

            $rule = new BetweenRule($min, $max);
            $value = $this->randomNumericValue();

            $numericValue = (float)$value;
            $expected = \is_numeric($value) && $numericValue >= $min && $numericValue <= $max;
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'BetweenRule(%f, %f)::passes(%s) must return %s (iteration %d)',
                    $min,
                    $max,
                    \var_export($value, true),
                    $expected ? 'true' : 'false',
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: BetweenRule always fails for non-numeric values
     *
     * For any non-numeric value, BetweenRule::passes() SHALL return false regardless of bounds.
     *
     * **Validates: Requirements 8.8**
     */
    #[Test]
    public function betweenRuleFailsForNonNumericValues(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = $this->randomBound();
            $b = $this->randomBound();
            $min = \min($a, $b);
            $max = \max($a, $b);

            $rule = new BetweenRule($min, $max);
            $value = $this->randomNonNumericValue();

            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'BetweenRule(%f, %f) must fail for non-numeric value %s (iteration %d)',
                    $min,
                    $max,
                    \get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: BetweenRule with value equal to min or max passes
     *
     * For any min/max bounds, a value exactly equal to min or max SHALL pass BetweenRule.
     *
     * **Validates: Requirements 8.8**
     */
    #[Test]
    public function betweenRulePassesForBoundaryValues(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $a = $this->randomBound();
            $b = $this->randomBound();
            $min = \min($a, $b);
            $max = \max($a, $b);

            $rule = new BetweenRule($min, $max);

            // Value exactly at min
            $this->assertTrue(
                $rule->passes($min),
                \sprintf(
                    'BetweenRule(%f, %f) must pass for value at min boundary %f (iteration %d)',
                    $min,
                    $max,
                    $min,
                    $i,
                ),
            );

            // Value exactly at max
            $this->assertTrue(
                $rule->passes($max),
                \sprintf(
                    'BetweenRule(%f, %f) must pass for value at max boundary %f (iteration %d)',
                    $min,
                    $max,
                    $max,
                    $i,
                ),
            );
        }
    }

    /**
     * Property 19: MinRule and MaxRule with numeric string values
     *
     * For any numeric string representation, MinRule and MaxRule SHALL evaluate
     * the numeric value correctly (is_numeric check + float cast).
     *
     * **Validates: Requirements 8.6, 8.7**
     */
    #[Test]
    public function numericBoundRulesWorkWithNumericStrings(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $numericValue = $this->randomNumericValue();
            $stringValue = (string)$numericValue;
            $bound = $this->randomBound();

            $minRule = new MinRule($bound);
            $maxRule = new MaxRule($bound);

            $expectedMin = (float)$stringValue >= $bound;
            $expectedMax = (float)$stringValue <= $bound;

            $this->assertSame(
                $expectedMin,
                $minRule->passes($stringValue),
                \sprintf(
                    'MinRule(%f)::passes("%s") must return %s (iteration %d)',
                    $bound,
                    $stringValue,
                    $expectedMin ? 'true' : 'false',
                    $i,
                ),
            );

            $this->assertSame(
                $expectedMax,
                $maxRule->passes($stringValue),
                \sprintf(
                    'MaxRule(%f)::passes("%s") must return %s (iteration %d)',
                    $bound,
                    $stringValue,
                    $expectedMax ? 'true' : 'false',
                    $i,
                ),
            );
        }
    }
}
