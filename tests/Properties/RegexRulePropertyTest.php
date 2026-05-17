<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\RegexRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * RegexRulePropertyTest
 *
 * Feature: enterprise-resilience, Property 21: Regex rule matches preg_match
 *
 * For any string value and for any valid regex pattern, the `regex` rule SHALL pass
 * if and only if `preg_match($pattern, $value)` returns 1.
 *
 * **Validates: Requirements 8.10**
 */
#[CoversClass(RegexRule::class)]
class RegexRulePropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /** Known valid regex patterns for testing. */
    private const PATTERNS = [
        '/^[a-z]+$/',
        '/\d+/',
        '/^[A-Z]/',
        '/^[A-Za-z0-9]+$/',
        '/\s/',
        '/^.{3,10}$/',
        '/[!@#$%]/',
        '/^\d{2,4}$/',
    ];

    /**
     * Generate a random string value.
     */
    private function randomString(): string
    {
        $length = random_int(0, 30);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 !@#$%\t\n";
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, \strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * Pick a random pattern from the known set.
     */
    private function randomPattern(): string
    {
        return self::PATTERNS[random_int(0, \count(self::PATTERNS) - 1)];
    }

    /**
     * Generate a random non-string value.
     */
    private function randomNonString(): mixed
    {
        $type = random_int(0, 4);
        return match ($type) {
            0 => random_int(PHP_INT_MIN >> 16, PHP_INT_MAX >> 16),
            1 => random_int(-10000, 10000) / 100.0 + 0.1,
            2 => (bool)random_int(0, 1),
            3 => null,
            4 => [random_int(0, 10), 'abc'],
        };
    }

    /**
     * Property 21: RegexRule passes iff preg_match(pattern, value) === 1
     *
     * For any random string value and any known pattern, RegexRule->passes(value)
     * SHALL return true if and only if preg_match(pattern, value) === 1.
     *
     * **Validates: Requirements 8.10**
     */
    #[Test]
    public function regexRulePassesIffPregMatchReturnsOne(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $pattern = $this->randomPattern();
            $value = $this->randomString();
            $rule = new RegexRule($pattern);

            $expected = preg_match($pattern, $value) === 1;
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'RegexRule("%s")->passes("%s") must return %s (iteration %d)',
                    $pattern,
                    $value,
                    $expected ? 'true' : 'false',
                    $i,
                ),
            );
        }
    }

    /**
     * Property 21: RegexRule passes for strings that match the pattern
     *
     * For each known pattern, generate strings that are known to match and verify
     * RegexRule->passes() returns true.
     *
     * **Validates: Requirements 8.10**
     */
    #[Test]
    public function regexRulePassesForMatchingStrings(): void
    {
        // Pattern => generator of matching strings
        $generators = [
            '/^[a-z]+$/' => fn() => $this->randomLowerAlpha(),
            '/\d+/' => fn() => (string)random_int(0, 99999),
            '/^[A-Z]/' => fn() => \chr(random_int(65, 90)) . $this->randomString(),
        ];

        foreach ($generators as $pattern => $generator) {
            $rule = new RegexRule($pattern);

            for ($i = 0; $i < self::ITERATIONS; $i++) {
                $value = $generator();

                $this->assertTrue(
                    $rule->passes($value),
                    \sprintf(
                        'RegexRule("%s")->passes("%s") must return true (iteration %d)',
                        $pattern,
                        $value,
                        $i,
                    ),
                );
            }
        }
    }

    /**
     * Property 21: Non-string values always fail
     *
     * For any non-string value (int, float, bool, null, array), RegexRule->passes()
     * SHALL always return false regardless of the pattern.
     *
     * **Validates: Requirements 8.10**
     */
    #[Test]
    public function regexRuleAlwaysFailsForNonStringValues(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $pattern = $this->randomPattern();
            $value = $this->randomNonString();
            $rule = new RegexRule($pattern);

            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'RegexRule("%s")->passes(%s) must return false for non-string (iteration %d)',
                    $pattern,
                    get_debug_type($value),
                    $i,
                ),
            );
        }
    }

    /**
     * Property 21: Consistency across all patterns for same value
     *
     * For any random string value, the RegexRule result SHALL be consistent with
     * preg_match across all known patterns.
     *
     * **Validates: Requirements 8.10**
     */
    #[Test]
    public function regexRuleConsistentAcrossAllPatterns(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomString();

            foreach (self::PATTERNS as $pattern) {
                $rule = new RegexRule($pattern);
                $expected = preg_match($pattern, $value) === 1;
                $result = $rule->passes($value);

                $this->assertSame(
                    $expected,
                    $result,
                    \sprintf(
                        'RegexRule("%s")->passes("%s") must match preg_match result (iteration %d)',
                        $pattern,
                        $value,
                        $i,
                    ),
                );
            }
        }
    }

    /**
     * Generate a random lowercase alpha string (guaranteed to match /^[a-z]+$/).
     */
    private function randomLowerAlpha(): string
    {
        $length = random_int(1, 20);
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= \chr(random_int(97, 122));
        }
        return $str;
    }
}
