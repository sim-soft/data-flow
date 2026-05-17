<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\ClosureRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * ClosureRulePropertyTest
 *
 * Feature: enterprise-resilience, Property 15: Closure rule invocation
 *
 * For any field value and for any closure provided as a validation rule,
 * the SchemaValidator SHALL treat a `false` return from the closure as a
 * validation failure and a `true` return as passing.
 *
 * **Validates: Requirements 7.7**
 */
#[CoversClass(ClosureRule::class)]
class ClosureRulePropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Property 15: ClosureRule passes iff the closure returns true for the given value.
     *
     * For any random value and a closure that returns true based on a condition,
     * ClosureRule->passes(value) === closure(value).
     *
     * **Validates: Requirements 7.7**
     */
    #[Test]
    public function closureRulePassesIffClosureReturnsTrue(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random value
            $value = $this->generateRandomValue();

            // Generate a random closure with a deterministic condition
            $closure = $this->generateRandomClosure($i);

            $rule = new ClosureRule($closure);

            $expected = $closure($value);
            $actual = $rule->passes($value);

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    'ClosureRule->passes() must equal closure result for value %s (iteration %d)',
                    var_export($value, true),
                    $i
                )
            );
        }
    }

    /**
     * Property 15: ClosureRule always fails when closure always returns false.
     *
     * **Validates: Requirements 7.7**
     */
    #[Test]
    public function closureRuleAlwaysFailsWhenClosureReturnsFalse(): void
    {
        $closure = fn(mixed $value): bool => false;
        $rule = new ClosureRule($closure);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->generateRandomValue();

            $this->assertFalse(
                $rule->passes($value),
                sprintf(
                    'ClosureRule must fail when closure returns false for value %s (iteration %d)',
                    var_export($value, true),
                    $i
                )
            );
        }
    }

    /**
     * Property 15: ClosureRule always passes when closure always returns true.
     *
     * **Validates: Requirements 7.7**
     */
    #[Test]
    public function closureRuleAlwaysPassesWhenClosureReturnsTrue(): void
    {
        $closure = fn(mixed $value): bool => true;
        $rule = new ClosureRule($closure);

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->generateRandomValue();

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'ClosureRule must pass when closure returns true for value %s (iteration %d)',
                    var_export($value, true),
                    $i
                )
            );
        }
    }

    /**
     * Property 15: ClosureRule correctly reflects conditional closures.
     *
     * Tests with closures that have specific conditions (e.g., value > threshold).
     *
     * **Validates: Requirements 7.7**
     */
    #[Test]
    public function closureRuleReflectsConditionalClosures(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Random threshold
            $threshold = random_int(-1000, 1000);
            $closure = fn(mixed $value): bool => is_int($value) && $value > $threshold;

            $rule = new ClosureRule($closure);

            // Generate a random integer value
            $value = random_int(-2000, 2000);

            $expected = $closure($value);
            $actual = $rule->passes($value);

            $this->assertSame(
                $expected,
                $actual,
                sprintf(
                    'ClosureRule->passes(%d) must equal closure(%d) with threshold %d (iteration %d)',
                    $value,
                    $value,
                    $threshold,
                    $i
                )
            );
        }
    }

    /**
     * Generate a random value of various types.
     */
    private function generateRandomValue(): mixed
    {
        $type = random_int(0, 5);

        return match ($type) {
            0 => random_int(-10000, 10000),                          // int
            1 => random_int(-10000, 10000) / random_int(1, 100),     // float
            2 => $this->generateRandomString(random_int(0, 50)),     // string
            3 => (bool)random_int(0, 1),                            // bool
            4 => null,                                                // null
            5 => range(0, random_int(0, 5)),                         // array
        };
    }

    /**
     * Generate a random string of given length.
     */
    private function generateRandomString(int $length): string
    {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= chr(random_int(32, 126));
        }
        return $str;
    }

    /**
     * Generate a random closure based on iteration seed for variety.
     */
    private function generateRandomClosure(int $seed): Closure
    {
        $variant = $seed % 5;

        return match ($variant) {
            0 => fn(mixed $v): bool => is_int($v) && $v > 0,
            1 => fn(mixed $v): bool => is_string($v) && strlen($v) > 3,
            2 => fn(mixed $v): bool => $v !== null,
            3 => fn(mixed $v): bool => is_numeric($v),
            4 => fn(mixed $v): bool => is_array($v) || is_string($v),
        };
    }
}
