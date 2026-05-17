<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\StringRule;

class StringRuleTest extends TestCase
{
    private StringRule $rule;

    protected function setUp(): void
    {
        $this->rule = new StringRule();
    }

    public function testPassesOnString(): void
    {
        $this->assertTrue($this->rule->passes('hello'));
        $this->assertTrue($this->rule->passes(''));
    }

    public function testFailsOnNonString(): void
    {
        $this->assertFalse($this->rule->passes(123));
        $this->assertFalse($this->rule->passes(1.5));
        $this->assertFalse($this->rule->passes(true));
        $this->assertFalse($this->rule->passes(null));
        $this->assertFalse($this->rule->passes([]));
    }

    public function testMessage(): void
    {
        $this->assertSame('The name field must be a string.', $this->rule->message('name'));
    }
}
