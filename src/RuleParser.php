<?php

declare(strict_types=1);

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
 *
 * Custom rules can be plugged in by calling {@see RuleParser::register()}.
 */
final class RuleParser
{
    /**
     * Map of rule name => factory closure that returns a ValidationRule instance.
     *
     * Each factory receives the parameter string (everything after the first `:`)
     * or null when no parameters were supplied.
     *
     * @var array<string, Closure(?string): ValidationRule>|null
     */
    private static ?array $registry = null;

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
     * Register (or replace) a custom rule factory.
     *
     * The factory receives the parameter string (everything after the first `:`)
     * or null when the rule was used without parameters. It must return a
     * {@see ValidationRule} instance.
     *
     * If $factory is null, a default factory is generated that instantiates
     * $ruleClass with no arguments — useful for parameterless rules.
     *
     * @param string $name Rule name as it appears in pipe-delimited rule strings.
     * @param class-string<ValidationRule> $ruleClass Class implementing ValidationRule.
     * @param (Closure(?string): ValidationRule)|null $factory Optional factory closure.
     */
    public static function register(string $name, string $ruleClass, ?Closure $factory = null): void
    {
        $registry = self::registry();

        if ($factory === null) {
            $factory = static fn(?string $params): ValidationRule => new $ruleClass();
        }

        $registry[$name] = $factory;
        self::$registry = $registry;
    }

    /**
     * Return the rule registry, lazily bootstrapping defaults the first time
     * it is accessed.
     *
     * @return array<string, Closure(?string): ValidationRule>
     */
    private static function registry(): array
    {
        if (self::$registry === null) {
            self::$registry = self::defaultRegistry();
        }

        return self::$registry;
    }

    /**
     * Build the default rule registry containing the built-in validation rules.
     *
     * @return array<string, Closure(?string): ValidationRule>
     */
    private static function defaultRegistry(): array
    {
        return [
            'required' => static fn(?string $params): ValidationRule => new RequiredRule(),
            'string' => static fn(?string $params): ValidationRule => new StringRule(),
            'int' => static fn(?string $params): ValidationRule => new IntRule(),
            'float' => static fn(?string $params): ValidationRule => new FloatRule(),
            'email' => static fn(?string $params): ValidationRule => new EmailRule(),
            'min' => static fn(?string $params): ValidationRule => new MinRule((float)($params ?? '0')),
            'max' => static fn(?string $params): ValidationRule => new MaxRule((float)($params ?? '0')),
            'between' => static fn(?string $params): ValidationRule => self::parseBetweenRule($params),
            'in' => static fn(?string $params): ValidationRule => self::parseInRule($params),
            'regex' => static fn(?string $params): ValidationRule => new RegexRule($params ?? ''),
        ];
    }

    /**
     * Parse a single rule definition string into a ValidationRule instance.
     *
     * @throws InvalidArgumentException
     */
    private static function parseRule(string $rule): ValidationRule
    {
        $registry = self::registry();

        // Handle regex rule specially since the pattern may contain colons
        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            return $registry['regex']($pattern);
        }

        // Split rule name from parameters
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $params = $parts[1] ?? null;

        if (!isset($registry[$name])) {
            throw new InvalidArgumentException("Unknown validation rule: {$name}");
        }

        return $registry[$name]($params);
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
