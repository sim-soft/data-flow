<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\InRule;

class InRuleTest extends TestCase
{
    public function testPassesWhenValueInList(): void
    {
        $rule = new InRule(['active', 'inactive', 'pending']);
        $this->assertTrue($rule->passes('active'));
        $this->assertTrue($rule->passes('pending'));
    }

    public function testFailsWhenValueNotInList(): void
    {
        $rule = new InRule(['active', 'inactive', 'pending']);
        $this->assertFalse($rule->passes('deleted'));
        $this->assertFalse($rule->passes(''));
    }

    public function testWorksWithNumericValues(): void
    {
        $rule = new InRule([1, 2, 3]);
        $this->assertTrue($rule->passes(1));
        $this->assertFalse($rule->passes(4));
    }

    public function testMessage(): void
    {
        $rule = new InRule(['a', 'b', 'c']);
        $this->assertSame('The status field must be one of: a, b, c.', $rule->message('status'));
    }
}
