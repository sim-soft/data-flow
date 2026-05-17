<?php

namespace Simsoft\DataFlow;

use Closure;
use InvalidArgumentException;
use Simsoft\DataFlow\Interfaces\ValidationRule;
use Simsoft\DataFlow\Rules\BetweenRule;
use Simsoft\DataFlow\Rules\ClosureRule;
use Simsoft\DataFlow\Rules\EmailRule;
use Simsoft\DataFlow\Rules\FloatRule;
use Simsoft\DataFlow\Rules\InRule;
use Simsoft\DataFlow\Rules\IntRule;
use Simsoft\DataFlow\Rules\MaxRule;
use Simsoft\DataFlow\Rules\MinRule;
use Simsoft\DataFlow\Rules\RegexRule;
use Simsoft\DataFlow\Rules\RequiredRule;
use Simsoft\DataFlow\Rules\StringRule;

/**
 * RuleParser - parses pipe-delimited rule strings into ValidationRule arrays.
 */
final class RuleParser
{
    /** @var array<string, class-string<ValidationRule>> */
    private static array $ruleMap = [
        'required' => RequiredRule::class,
        'string' => StringRule::class,
        'int' => IntRule::class,
        'float' => FloatRule::class,
        'email' => EmailRule::class,
    ];

    /**
     * Parse a rule definition (string or array) into an array of ValidationRule instances.
     *
     * @param string|array<int, string|Closure> $rules
     * @return ValidationRule[]
     * @throws InvalidArgumentException
     */
    public static function parse(string|array $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $parsed = [];

        foreach ($rules as $rule) {
            if ($rule instanceof Closure) {
                $parsed[] = new ClosureRule($rule);
                continue;
            }

            $parsed[] = self::parseRule($rule);
        }

        return $parsed;
    }

    /**
     * Parse a single rule definition string into a ValidationRule instance.
     *
     * @throws InvalidArgumentException
     */
    private static function parseRule(string $rule): ValidationRule
    {
        // Handle regex rule specially since the pattern may contain colons
        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            return new RegexRule($pattern);
        }

        // Split rule name from parameters
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $params = $parts[1] ?? null;

        // Simple rules without parameters
        if (isset(self::$ruleMap[$name])) {
            return new (self::$ruleMap[$name])();
        }

        // Parameterized rules
        return match ($name) {
            'min' => new MinRule((float)$params),
            'max' => new MaxRule((float)$params),
            'between' => self::parseBetweenRule($params),
            'in' => self::parseInRule($params),
            default => throw new InvalidArgumentException("Unknown validation rule: {$name}"),
        };
    }

    /**
     * Parse a between rule with min,max parameters.
     */
    private static function parseBetweenRule(?string $params): BetweenRule
    {
        $values = explode(',', $params ?? '');

        return new BetweenRule((float)$values[0], (float)($values[1] ?? 0));
    }

    /**
     * Parse an in rule with comma-separated allowed values.
     */
    private static function parseInRule(?string $params): InRule
    {
        $values = explode(',', $params ?? '');

        return new InRule($values);
    }
}
