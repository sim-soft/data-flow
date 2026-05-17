<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\FloatRule;

class FloatRuleTest extends TestCase
{
    private FloatRule $rule;

    protected function setUp(): void
    {
        $this->rule = new FloatRule();
    }

    public function testPassesOnFloat(): void
    {
        $this->assertTrue($this->rule->passes(1.5));
        $this->assertTrue($this->rule->passes(0.0));
        $this->assertTrue($this->rule->passes(-3.14));
    }

    public function testPassesOnInteger(): void
    {
        $this->assertTrue($this->rule->passes(42));
        $this->assertTrue($this->rule->passes(0));
    }

    public function testFailsOnNonNumeric(): void
    {
        $this->assertFalse($this->rule->passes('1.5'));
        $this->assertFalse($this->rule->passes(true));
        $this->assertFalse($this->rule->passes(null));
        $this->assertFalse($this->rule->passes([]));
    }

    public function testMessage(): void
    {
        $this->assertSame('The price field must be a float.', $this->rule->message('price'));
    }
}
