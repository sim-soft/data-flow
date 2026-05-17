<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\IntRule;

class IntRuleTest extends TestCase
{
    private IntRule $rule;

    protected function setUp(): void
    {
        $this->rule = new IntRule();
    }

    public function testPassesOnInteger(): void
    {
        $this->assertTrue($this->rule->passes(0));
        $this->assertTrue($this->rule->passes(42));
        $this->assertTrue($this->rule->passes(-1));
    }

    public function testFailsOnNonInteger(): void
    {
        $this->assertFalse($this->rule->passes(1.5));
        $this->assertFalse($this->rule->passes('123'));
        $this->assertFalse($this->rule->passes(true));
        $this->assertFalse($this->rule->passes(null));
    }

    public function testMessage(): void
    {
        $this->assertSame('The age field must be an integer.', $this->rule->message('age'));
    }
}
