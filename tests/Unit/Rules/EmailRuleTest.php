<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\EmailRule;

class EmailRuleTest extends TestCase
{
    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EmailRule();
    }

    public function testPassesOnValidEmail(): void
    {
        $this->assertTrue($this->rule->passes('user@example.com'));
        $this->assertTrue($this->rule->passes('test.name+tag@domain.org'));
    }

    public function testFailsOnInvalidEmail(): void
    {
        $this->assertFalse($this->rule->passes('not-an-email'));
        $this->assertFalse($this->rule->passes('@missing-local.com'));
        $this->assertFalse($this->rule->passes('missing@'));
        $this->assertFalse($this->rule->passes(''));
    }

    public function testFailsOnNonString(): void
    {
        $this->assertFalse($this->rule->passes(123));
        $this->assertFalse($this->rule->passes(null));
    }

    public function testMessage(): void
    {
        $this->assertSame('The email field must be a valid email address.', $this->rule->message('email'));
    }
}
