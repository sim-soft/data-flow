<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\BetweenRule;

class BetweenRuleTest extends TestCase
{
    public function testPassesWhenValueInRange(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertTrue($rule->passes(5));
    }

    public function testPassesWhenValueEqualsMin(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertTrue($rule->passes(1));
    }

    public function testPassesWhenValueEqualsMax(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertTrue($rule->passes(10));
    }

    public function testFailsWhenValueBelowMin(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertFalse($rule->passes(0));
    }

    public function testFailsWhenValueAboveMax(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertFalse($rule->passes(11));
    }

    public function testFailsOnNonNumeric(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertFalse($rule->passes('abc'));
    }

    public function testMessage(): void
    {
        $rule = new BetweenRule(1, 10);
        $this->assertSame('The age field must be between 1 and 10.', $rule->message('age'));
    }
}
