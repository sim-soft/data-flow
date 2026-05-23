<?php

namespace Simsoft\DataFlow\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Simsoft\DataFlow\Interfaces\ValidationRule;
use Simsoft\DataFlow\RuleParser;
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
use Simsoft\DataFlow\Tests\TestCase;

class RuleParserTest extends TestCase
{
    public function test_parses_single_rule_from_string(): void
    {
        $rules = RuleParser::parse('required');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
    }

    public function test_parses_pipe_delimited_string(): void
    {
        $rules = RuleParser::parse('required|string|email');

        $this->assertCount(3, $rules);
        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
        $this->assertInstanceOf(StringRule::class, $rules[1]);
        $this->assertInstanceOf(EmailRule::class, $rules[2]);
    }

    public function test_parses_all_simple_rules(): void
    {
        $rules = RuleParser::parse('required|string|int|float|email');

        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
        $this->assertInstanceOf(StringRule::class, $rules[1]);
        $this->assertInstanceOf(IntRule::class, $rules[2]);
        $this->assertInstanceOf(FloatRule::class, $rules[3]);
        $this->assertInstanceOf(EmailRule::class, $rules[4]);
    }

    public function test_parses_min_rule_with_parameter(): void
    {
        $rules = RuleParser::parse('min:5');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(MinRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes(5));
        $this->assertFalse($rules[0]->passes(4));
    }

    public function test_parses_max_rule_with_parameter(): void
    {
        $rules = RuleParser::parse('max:100');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(MaxRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes(100));
        $this->assertFalse($rules[0]->passes(101));
    }

    public function test_parses_between_rule_with_parameters(): void
    {
        $rules = RuleParser::parse('between:1,10');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(BetweenRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes(5));
        $this->assertFalse($rules[0]->passes(11));
    }

    public function test_parses_in_rule_with_parameters(): void
    {
        $rules = RuleParser::parse('in:a,b,c');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(InRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes('a'));
        $this->assertFalse($rules[0]->passes('d'));
    }

    public function test_parses_regex_rule_with_pattern(): void
    {
        $rules = RuleParser::parse('regex:/^[a-z]+$/i');

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(RegexRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes('hello'));
        $this->assertFalse($rules[0]->passes('123'));
    }

    public function test_parses_array_of_rule_strings(): void
    {
        $rules = RuleParser::parse(['required', 'string', 'min:3']);

        $this->assertCount(3, $rules);
        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
        $this->assertInstanceOf(StringRule::class, $rules[1]);
        $this->assertInstanceOf(MinRule::class, $rules[2]);
    }

    public function test_wraps_closure_in_closure_rule(): void
    {
        $closure = fn(mixed $v): bool => $v > 0;
        $rules = RuleParser::parse([$closure]);

        $this->assertCount(1, $rules);
        $this->assertInstanceOf(ClosureRule::class, $rules[0]);
        $this->assertTrue($rules[0]->passes(1));
        $this->assertFalse($rules[0]->passes(-1));
    }

    public function test_parses_mixed_array_with_strings_and_closures(): void
    {
        $closure = fn(mixed $v): bool => $v !== 'bad';
        $rules = RuleParser::parse(['required', $closure, 'email']);

        $this->assertCount(3, $rules);
        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
        $this->assertInstanceOf(ClosureRule::class, $rules[1]);
        $this->assertInstanceOf(EmailRule::class, $rules[2]);
    }

    public function test_throws_for_unknown_rule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule: unknown');

        RuleParser::parse('unknown');
    }

    public function test_parses_complex_pipe_delimited_string(): void
    {
        $rules = RuleParser::parse('required|int|min:0|max:150');

        $this->assertCount(4, $rules);
        $this->assertInstanceOf(RequiredRule::class, $rules[0]);
        $this->assertInstanceOf(IntRule::class, $rules[1]);
        $this->assertInstanceOf(MinRule::class, $rules[2]);
        $this->assertInstanceOf(MaxRule::class, $rules[3]);
    }

    public function test_all_parsed_rules_implement_validation_rule_interface(): void
    {
        $rules = RuleParser::parse('required|string|int|float|email|min:1|max:10|between:1,5|in:x,y|regex:/^a/');

        foreach ($rules as $rule) {
            $this->assertInstanceOf(ValidationRule::class, $rule);
        }
    }

    public function test_custom_rule_can_be_registered_and_parsed(): void
    {
        $customRule = new class implements ValidationRule {
            public function passes(mixed $value): bool
            {
                return $value === 'snowflake';
            }

            public function message(string $field): string
            {
                return "The {$field} must be the snowflake.";
            }
        };

        RuleParser::register('snowflake', $customRule::class);

        try {
            $rules = RuleParser::parse('snowflake');

            $this->assertCount(1, $rules);
            $this->assertInstanceOf(ValidationRule::class, $rules[0]);
            $this->assertTrue($rules[0]->passes('snowflake'));
            $this->assertFalse($rules[0]->passes('not-it'));
        } finally {
            // Clean-up: remove the registration so other tests are not affected.
            $reflection = new \ReflectionClass(RuleParser::class);
            $registry = $reflection->getProperty('registry');
            $registry->setAccessible(true);
            $registry->setValue(null, null);
        }
    }

    public function test_custom_rule_factory_receives_params(): void
    {
        $captured = null;

        RuleParser::register(
            'tagged',
            \stdClass::class, // unused because factory provided
            function (?string $params) use (&$captured): ValidationRule {
                $captured = $params;
                return new class implements ValidationRule {
                    public function passes(mixed $value): bool
                    {
                        return true;
                    }

                    public function message(string $field): string
                    {
                        return "field {$field}";
                    }
                };
            },
        );

        try {
            RuleParser::parse('tagged:hello,world');
            $this->assertSame('hello,world', $captured);
        } finally {
            $reflection = new \ReflectionClass(RuleParser::class);
            $registry = $reflection->getProperty('registry');
            $registry->setAccessible(true);
            $registry->setValue(null, null);
        }
    }
}
