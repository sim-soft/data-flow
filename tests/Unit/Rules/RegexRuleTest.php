<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\RegexRule;

class RegexRuleTest extends TestCase
{
    public function testPassesWhenValueMatchesPattern(): void
    {
        $rule = new RegexRule('/^[A-Z]+$/');
        $this->assertTrue($rule->passes('HELLO'));
    }

    public function testFailsWhenValueDoesNotMatch(): void
    {
        $rule = new RegexRule('/^[A-Z]+$/');
        $this->assertFalse($rule->passes('hello'));
        $this->assertFalse($rule->passes('Hello'));
    }

    public function testFailsOnNonString(): void
    {
        $rule = new RegexRule('/^\d+$/');
        $this->assertFalse($rule->passes(123));
        $this->assertFalse($rule->passes(null));
    }

    public function testPassesWithComplexPattern(): void
    {
        $rule = new RegexRule('/^[A-Za-z ]+$/');
        $this->assertTrue($rule->passes('John Doe'));
        $this->assertFalse($rule->passes('John123'));
    }

    public function testMessage(): void
    {
        $rule = new RegexRule('/^[A-Z]+$/');
        $this->assertSame('The code field format is invalid.', $rule->message('code'));
    }
}
