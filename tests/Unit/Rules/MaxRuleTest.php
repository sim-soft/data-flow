<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\MaxRule;

class MaxRuleTest extends TestCase
{
    public function testPassesWhenValueEqualsMax(): void
    {
        $rule = new MaxRule(100);
        $this->assertTrue($rule->passes(100));
    }

    public function testPassesWhenValueLessThanMax(): void
    {
        $rule = new MaxRule(100);
        $this->assertTrue($rule->passes(50));
    }

    public function testFailsWhenValueGreaterThanMax(): void
    {
        $rule = new MaxRule(100);
        $this->assertFalse($rule->passes(150));
    }

    public function testWorksWithFloats(): void
    {
        $rule = new MaxRule(9.9);
        $this->assertTrue($rule->passes(9.9));
        $this->assertTrue($rule->passes(5.0));
        $this->assertFalse($rule->passes(10.0));
    }

    public function testFailsOnNonNumeric(): void
    {
        $rule = new MaxRule(100);
        $this->assertFalse($rule->passes('abc'));
    }

    public function testMessage(): void
    {
        $rule = new MaxRule(100);
        $this->assertSame('The score field must not be greater than 100.', $rule->message('score'));
    }
}
