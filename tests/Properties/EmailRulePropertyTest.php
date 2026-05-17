<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Rules\EmailRule;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * EmailRulePropertyTest
 *
 * Feature: enterprise-resilience, Property 18: Email rule matches filter_var
 *
 * For any string value, the `email` rule SHALL pass if and only if
 * filter_var($value, FILTER_VALIDATE_EMAIL) returns a non-false result.
 *
 * **Validates: Requirements 8.5**
 */
#[CoversClass(EmailRule::class)]
class EmailRulePropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random string that may or may not be a valid email.
     */
    private function randomString(): string
    {
        $strategy = random_int(0, 3);

        return match ($strategy) {
            // Completely random string
            0 => $this->randomArbitraryString(),
            // String that looks like an email (local@domain.tld)
            1 => $this->randomEmailLikeString(),
            // String with @ but possibly invalid
            2 => $this->randomStringWithAt(),
            // Edge cases: empty, whitespace, special chars
            3 => $this->randomEdgeCaseString(),
        };
    }

    /**
     * Generate a completely arbitrary random string.
     */
    private function randomArbitraryString(): string
    {
        $length = random_int(0, 80);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 !@#\$%^&*()_+-=[]{}|;':\",./<>?\t\n";
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, \strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * Generate a string that resembles an email address.
     */
    private function randomEmailLikeString(): string
    {
        $localChars = 'abcdefghijklmnopqrstuvwxyz0123456789._%+-';
        $domainChars = 'abcdefghijklmnopqrstuvwxyz0123456789-';
        $tlds = ['com', 'org', 'net', 'io', 'co', 'dev', 'info'];

        $localLen = random_int(1, 20);
        $local = '';
        for ($i = 0; $i < $localLen; $i++) {
            $local .= $localChars[random_int(0, \strlen($localChars) - 1)];
        }

        $domainLen = random_int(1, 15);
        $domain = '';
        for ($i = 0; $i < $domainLen; $i++) {
            $domain .= $domainChars[random_int(0, \strlen($domainChars) - 1)];
        }

        $tld = $tlds[random_int(0, \count($tlds) - 1)];

        return $local . '@' . $domain . '.' . $tld;
    }

    /**
     * Generate a string containing @ but possibly invalid as email.
     */
    private function randomStringWithAt(): string
    {
        $left = $this->randomArbitraryString();
        $right = $this->randomArbitraryString();
        return $left . '@' . $right;
    }

    /**
     * Generate edge case strings.
     */
    private function randomEdgeCaseString(): string
    {
        $cases = [
            '',
            ' ',
            '@',
            'user@',
            '@domain.com',
            'user@domain',
            'user@.com',
            '.user@domain.com',
            'user.@domain.com',
            'user..name@domain.com',
            'user@domain..com',
            'a@b.c',
            'test@example.com',
            'very.long.email.address.that.is.valid@subdomain.example.org',
        ];

        return $cases[random_int(0, \count($cases) - 1)];
    }

    /**
     * Property 18: EmailRule->passes(value) === (filter_var(value, FILTER_VALIDATE_EMAIL) !== false)
     *
     * For any random string value, the EmailRule result matches filter_var exactly.
     *
     * **Validates: Requirements 8.5**
     */
    #[Test]
    public function emailRuleMatchesFilterVar(): void
    {
        $rule = new EmailRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomString();
            $expected = \filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            $result = $rule->passes($value);

            $this->assertSame(
                $expected,
                $result,
                \sprintf(
                    'EmailRule::passes() must return %s for value "%s" (iteration %d). '
                    . 'filter_var returned %s.',
                    $expected ? 'true' : 'false',
                    $value,
                    $i,
                    var_export(\filter_var($value, FILTER_VALIDATE_EMAIL), true),
                ),
            );
        }
    }

    /**
     * Property 18: EmailRule always passes for valid email addresses
     *
     * For any string that filter_var accepts as a valid email, EmailRule SHALL pass.
     *
     * **Validates: Requirements 8.5**
     */
    #[Test]
    public function emailRulePassesForValidEmails(): void
    {
        $rule = new EmailRule();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $value = $this->randomEmailLikeString();

            // Only assert if filter_var considers it valid
            if (\filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                $this->assertTrue(
                    $rule->passes($value),
                    \sprintf(
                        'EmailRule must pass for valid email "%s" (iteration %d)',
                        $value,
                        $i,
                    ),
                );
            }
        }
    }

    /**
     * Property 18: EmailRule always fails for strings without @
     *
     * For any string that does not contain @, filter_var will reject it,
     * and EmailRule SHALL also fail.
     *
     * **Validates: Requirements 8.5**
     */
    #[Test]
    public function emailRuleFailsForStringsWithoutAt(): void
    {
        $rule = new EmailRule();
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._%+-';

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $length = random_int(0, 50);
            $value = '';
            for ($j = 0; $j < $length; $j++) {
                $value .= $chars[random_int(0, \strlen($chars) - 1)];
            }

            // Strings without @ are never valid emails per filter_var
            $this->assertFalse(
                $rule->passes($value),
                \sprintf(
                    'EmailRule must fail for string without @: "%s" (iteration %d)',
                    $value,
                    $i,
                ),
            );
        }
    }
}
