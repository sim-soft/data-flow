<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\RequiredRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * RequiredRulePropertyTest
 *
 * Feature: enterprise-resilience, Property 16: Required rule semantics
 *
 * For any row, the `required` rule SHALL fail if and only if the field value is null,
 * an empty string, or the field key is absent from the row.
 *
 * **Validates: Requirements 8.1**
 */
#[CoversClass(RequiredRule::class)]
class RequiredRulePropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Property 16: RequiredRule fails for null, empty string, and empty array.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRuleFailsForNullEmptyStringAndEmptyArray(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Pick a random failing value
            $failingValues = [null, '', []];
            $value = $failingValues[array_rand($failingValues)];

            $this->assertFalse(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must fail for value %s (iteration %d)',
                    var_export($value, true),
                    $i
                )
            );
        }
    }

    /**
     * Property 16: RequiredRule passes for any non-empty string.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRulePassesForAnyNonEmptyString(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random non-empty string (1 to 100 chars)
            $length = random_int(1, 100);
            $value = '';
            for ($j = 0; $j < $length; $j++) {
                $value .= chr(random_int(32, 126));
            }

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must pass for non-empty string "%s" (iteration %d)',
                    $value,
                    $i
                )
            );
        }
    }

    /**
     * Property 16: RequiredRule passes for any non-empty array.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRulePassesForAnyNonEmptyArray(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random non-empty array (1 to 10 elements)
            $size = random_int(1, 10);
            $value = [];
            for ($j = 0; $j < $size; $j++) {
                $value[] = random_int(-1000, 1000);
            }

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must pass for non-empty array of size %d (iteration %d)',
                    count($value),
                    $i
                )
            );
        }
    }

    /**
     * Property 16: RequiredRule passes for any integer.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRulePassesForAnyInteger(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = random_int(PHP_INT_MIN >> 1, PHP_INT_MAX >> 1);

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must pass for integer %d (iteration %d)',
                    $value,
                    $i
                )
            );
        }
    }

    /**
     * Property 16: RequiredRule passes for any float.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRulePassesForAnyFloat(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a random float
            $value = (random_int(-100000, 100000)) / (random_int(1, 1000));

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must pass for float %f (iteration %d)',
                    $value,
                    $i
                )
            );
        }
    }

    /**
     * Property 16: RequiredRule passes for any boolean.
     *
     * **Validates: Requirements 8.1**
     */
    #[Test]
    public function requiredRulePassesForAnyBoolean(): void
    {
        $rule = new RequiredRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = (bool)random_int(0, 1);

            $this->assertTrue(
                $rule->passes($value),
                sprintf(
                    'RequiredRule must pass for boolean %s (iteration %d)',
                    var_export($value, true),
                    $i
                )
            );
        }
    }
}
