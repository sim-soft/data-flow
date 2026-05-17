<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\FloatRule;
use Simsoft\DataFlow\Rules\IntRule;
use Simsoft\DataFlow\Rules\StringRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * TypeCheckingRulesPropertyTest
 *
 * Feature: enterprise-resilience, Property 17: Type checking rules
 *
 * For any value, the `string` rule SHALL pass iff is_string($value) is true,
 * the `int` rule SHALL pass iff is_int($value) is true, and the `float` rule
 * SHALL pass iff is_float($value) || is_int($value) is true.
 *
 * **Validates: Requirements 8.2, 8.3, 8.4**
 */
#[CoversClass(StringRule::class)]
#[CoversClass(IntRule::class)]
#[CoversClass(FloatRule::class)]
class TypeCheckingRulesPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random value of any type.
     */
    private function randomValue(): mixed
    {
        $type = random_int(0, 5);

        return match ($type) {
            0 => $this->randomString(),
            1 => random_int(PHP_INT_MIN >> 16, PHP_INT_MAX >> 16),
            2 => (random_int(-100000, 100000) / 1000.0) + 0.1, // ensure float (avoid .0)
            3 => (bool)random_int(0, 1),
            4 => range(0, random_int(0, 5)),
            5 => null,
        };
    }

    /**
     * Generate a random string.
     */
    private function randomString(): string
    {
        $length = random_int(0, 50);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 !@#$%';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * Property 17: StringRule passes iff is_string($value) is true
     *
     * For any randomly generated value, StringRule::passes() SHALL return true
     * if and only if the value is of type string.
     *
     * **Validates: Requirements 8.2**
     */
    #[Test]
    public function stringRulePassesIffValueIsString(): void
    {
        $rule = new StringRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomValue();
            $expected = is_string($value);
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                sprintf(
                    'StringRule::passes() must return %s for value of type %s (iteration %d)',
                    $expected ? 'true' : 'false',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 17: StringRule always passes for any string input
     *
     * For any randomly generated string value, StringRule::passes() SHALL return true.
     *
     * **Validates: Requirements 8.2**
     */
    #[Test]
    public function stringRuleAlwaysPassesForStrings(): void
    {
        $rule = new StringRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomString();

            $this->assertTrue(
                $rule->passes($value),
                sprintf('StringRule must pass for string "%s" (iteration %d)', $value, $i),
            );
        }
    }

    /**
     * Property 17: StringRule always fails for non-string types
     *
     * For any int, float, bool, array, or null value, StringRule::passes() SHALL return false.
     *
     * **Validates: Requirements 8.2**
     */
    #[Test]
    public function stringRuleFailsForNonStringTypes(): void
    {
        $rule = new StringRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a non-string value
            $type = random_int(0, 3);
            $value = match ($type) {
                0 => random_int(PHP_INT_MIN >> 16, PHP_INT_MAX >> 16),
                1 => random_int(-100000, 100000) / 1000.0 + 0.1,
                2 => (bool)random_int(0, 1),
                3 => null,
            };

            $this->assertFalse(
                $rule->passes($value),
                sprintf(
                    'StringRule must fail for %s value (iteration %d)',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 17: IntRule passes iff is_int($value) is true
     *
     * For any randomly generated value, IntRule::passes() SHALL return true
     * if and only if the value is of type integer.
     *
     * **Validates: Requirements 8.3**
     */
    #[Test]
    public function intRulePassesIffValueIsInt(): void
    {
        $rule = new IntRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomValue();
            $expected = is_int($value);
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                sprintf(
                    'IntRule::passes() must return %s for value of type %s (iteration %d)',
                    $expected ? 'true' : 'false',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 17: IntRule always passes for any integer input
     *
     * For any randomly generated integer value, IntRule::passes() SHALL return true.
     *
     * **Validates: Requirements 8.3**
     */
    #[Test]
    public function intRuleAlwaysPassesForIntegers(): void
    {
        $rule = new IntRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = random_int(PHP_INT_MIN >> 16, PHP_INT_MAX >> 16);

            $this->assertTrue(
                $rule->passes($value),
                sprintf('IntRule must pass for integer %d (iteration %d)', $value, $i),
            );
        }
    }

    /**
     * Property 17: IntRule always fails for non-integer types
     *
     * For any string, float, bool, array, or null value, IntRule::passes() SHALL return false.
     *
     * **Validates: Requirements 8.3**
     */
    #[Test]
    public function intRuleFailsForNonIntTypes(): void
    {
        $rule = new IntRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a non-int value
            $type = random_int(0, 3);
            $value = match ($type) {
                0 => $this->randomString(),
                1 => random_int(-100000, 100000) / 1000.0 + 0.1,
                2 => (bool)random_int(0, 1),
                3 => null,
            };

            $this->assertFalse(
                $rule->passes($value),
                sprintf(
                    'IntRule must fail for %s value (iteration %d)',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 17: FloatRule passes iff is_float($value) || is_int($value) is true
     *
     * For any randomly generated value, FloatRule::passes() SHALL return true
     * if and only if the value is of type float or integer.
     *
     * **Validates: Requirements 8.4**
     */
    #[Test]
    public function floatRulePassesIffValueIsFloatOrInt(): void
    {
        $rule = new FloatRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomValue();
            $expected = is_float($value) || is_int($value);
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                sprintf(
                    'FloatRule::passes() must return %s for value of type %s (iteration %d)',
                    $expected ? 'true' : 'false',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 17: FloatRule always passes for any float input
     *
     * For any randomly generated float value, FloatRule::passes() SHALL return true.
     *
     * **Validates: Requirements 8.4**
     */
    #[Test]
    public function floatRuleAlwaysPassesForFloats(): void
    {
        $rule = new FloatRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = random_int(-100000, 100000) / 1000.0 + 0.1;

            $this->assertTrue(
                $rule->passes($value),
                sprintf('FloatRule must pass for float %f (iteration %d)', $value, $i),
            );
        }
    }

    /**
     * Property 17: FloatRule always passes for any integer input
     *
     * For any randomly generated integer value, FloatRule::passes() SHALL return true.
     *
     * **Validates: Requirements 8.4**
     */
    #[Test]
    public function floatRuleAlwaysPassesForIntegers(): void
    {
        $rule = new FloatRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = random_int(PHP_INT_MIN >> 16, PHP_INT_MAX >> 16);

            $this->assertTrue(
                $rule->passes($value),
                sprintf('FloatRule must pass for integer %d (iteration %d)', $value, $i),
            );
        }
    }

    /**
     * Property 17: FloatRule always fails for non-numeric types
     *
     * For any string, bool, array, or null value, FloatRule::passes() SHALL return false.
     *
     * **Validates: Requirements 8.4**
     */
    #[Test]
    public function floatRuleFailsForNonNumericTypes(): void
    {
        $rule = new FloatRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a non-numeric value (string, bool, null)
            $type = random_int(0, 2);
            $value = match ($type) {
                0 => $this->randomString(),
                1 => (bool)random_int(0, 1),
                2 => null,
            };

            $this->assertFalse(
                $rule->passes($value),
                sprintf(
                    'FloatRule must fail for %s value (iteration %d)',
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }
}
