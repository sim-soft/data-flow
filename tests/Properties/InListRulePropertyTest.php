<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\InRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * InListRulePropertyTest
 *
 * Feature: enterprise-resilience, Property 20: In-list rule
 *
 * For any value and for any list of allowed values, the `in` rule SHALL pass
 * if and only if the value is a member of the list (using in_array with strict: false).
 *
 * **Validates: Requirements 8.9**
 */
#[CoversClass(InRule::class)]
class InListRulePropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random scalar value (string, int, or float).
     */
    private function randomScalar(): string|int|float
    {
        $type = random_int(0, 2);

        return match ($type) {
            0 => $this->randomString(),
            1 => random_int(-1000, 1000),
            2 => random_int(-10000, 10000) / 100.0,
        };
    }

    /**
     * Generate a random string.
     */
    private function randomString(): string
    {
        $length = random_int(1, 20);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, \strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * Generate a random allowed list of scalar values.
     *
     * @return array<int, string|int|float>
     */
    private function randomAllowedList(): array
    {
        $size = random_int(1, 10);
        $list = [];
        for ($i = 0; $i < $size; $i++) {
            $list[] = $this->randomScalar();
        }
        return $list;
    }

    /**
     * Generate a value guaranteed NOT to be in the given list (using in_array strict: false).
     */
    private function valueNotInList(array $allowed): string
    {
        // Generate a unique string that cannot loosely match any list element
        do {
            $candidate = '__NOT_IN_LIST__' . $this->randomString() . '__' . random_int(100000, 999999);
        } while (\in_array($candidate, $allowed, false));

        return $candidate;
    }

    /**
     * Property 20: InRule passes for any value that is in the allowed list
     *
     * For any random allowed list and a value picked from that list, InRule::passes()
     * SHALL return true.
     *
     * **Validates: Requirements 8.9**
     */
    #[Test]
    public function inRulePassesForValueInAllowedList(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $allowed = $this->randomAllowedList();
            // Pick a random value from the allowed list
            $value = $allowed[random_int(0, \count($allowed) - 1)];

            $rule = new InRule($allowed);

            $this->assertTrue(
                $rule->passes($value),
                \sprintf(
                    'InRule must pass for value %s in allowed list [%s] (iteration %d)',
                    \var_export($value, true),
                    \implode(', ', \array_map(fn($v) => \var_export($v, true), $allowed)),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 20: InRule fails for any value that is NOT in the allowed list
     *
     * For any random allowed list and a value guaranteed not to be in that list,
     * InRule::passes() SHALL return false.
     *
     * **Validates: Requirements 8.9**
     */
    #[Test]
    public function inRuleFailsForValueNotInAllowedList(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $allowed = $this->randomAllowedList();
            $value = $this->valueNotInList($allowed);

            $rule = new InRule($allowed);

            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'InRule must fail for value %s not in allowed list [%s] (iteration %d)',
                    \var_export($value, true),
                    \implode(', ', \array_map(fn($v) => \var_export($v, true), $allowed)),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 20: InRule passes iff in_array with strict: false
     *
     * For any random allowed list and any random value, InRule::passes() SHALL return
     * the same result as in_array($value, $allowed, false).
     *
     * **Validates: Requirements 8.9**
     */
    #[Test]
    public function inRuleMatchesInArrayLooseComparison(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $allowed = $this->randomAllowedList();
            $value = $this->randomScalar();

            $rule = new InRule($allowed);
            $expected = \in_array($value, $allowed, false);
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'InRule::passes() must return %s for value %s with allowed list [%s] (iteration %d)',
                    $expected ? 'true' : 'false',
                    \var_export($value, true),
                    \implode(', ', \array_map(fn($v) => \var_export($v, true), $allowed)),
                    $i,
                ),
            );
        }
    }
}
