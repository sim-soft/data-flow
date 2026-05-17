<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Interfaces\ValidationRule;
use Simsoft\DataFlow\Rules\RequiredRule;

class RequiredRuleTest extends TestCase
{
    private RequiredRule $rule;

    protected function setUp(): void
    {
        $this->rule = new RequiredRule();
    }

    public function testImplementsValidationRule(): void
    {
        $this->assertInstanceOf(ValidationRule::class, $this->rule);
    }

    public function testFailsOnNull(): void
    {
        $this->assertFalse($this->rule->passes(null));
    }

    public function testFailsOnEmptyString(): void
    {
        $this->assertFalse($this->rule->passes(''));
    }

    public function testFailsOnEmptyArray(): void
    {
        $this->assertFalse($this->rule->passes([]));
    }

    public function testPassesOnNonEmptyString(): void
    {
        $this->assertTrue($this->rule->passes('hello'));
    }

    public function testPassesOnZero(): void
    {
        $this->assertTrue($this->rule->passes(0));
    }

    public function testPassesOnFalse(): void
    {
        $this->assertTrue($this->rule->passes(false));
    }

    public function testPassesOnNonEmptyArray(): void
    {
        $this->assertTrue($this->rule->passes([1, 2, 3]));
    }

    public function testMessage(): void
    {
        $this->assertSame('The email field is required.', $this->rule->message('email'));
    }
}
