<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\MinRule;

class MinRuleTest extends TestCase
{
    public function testPassesWhenValueEqualsMin(): void
    {
        $rule = new MinRule(5);
        $this->assertTrue($rule->passes(5));
    }

    public function testPassesWhenValueGreaterThanMin(): void
    {
        $rule = new MinRule(5);
        $this->assertTrue($rule->passes(10));
    }

    public function testFailsWhenValueLessThanMin(): void
    {
        $rule = new MinRule(5);
        $this->assertFalse($rule->passes(3));
    }

    public function testWorksWithFloats(): void
    {
        $rule = new MinRule(1.5);
        $this->assertTrue($rule->passes(1.5));
        $this->assertTrue($rule->passes(2.0));
        $this->assertFalse($rule->passes(1.0));
    }

    public function testFailsOnNonNumeric(): void
    {
        $rule = new MinRule(0);
        $this->assertFalse($rule->passes('abc'));
    }

    public function testMessage(): void
    {
        $rule = new MinRule(10);
        $this->assertSame('The age field must be at least 10.', $rule->message('age'));
    }
}
