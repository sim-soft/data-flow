<?php

namespace Simsoft\DataFlow\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Rules\ClosureRule;

class ClosureRuleTest extends TestCase
{
    public function testPassesWhenClosureReturnsTrue(): void
    {
        $rule = new ClosureRule(fn(mixed $v): bool => $v > 0);
        $this->assertTrue($rule->passes(5));
    }

    public function testFailsWhenClosureReturnsFalse(): void
    {
        $rule = new ClosureRule(fn(mixed $v): bool => $v > 0);
        $this->assertFalse($rule->passes(-1));
    }

    public function testClosureReceivesValue(): void
    {
        $received = null;
        $rule = new ClosureRule(function (mixed $v) use (&$received): bool {
            $received = $v;
            return true;
        });

        $rule->passes('test-value');
        $this->assertSame('test-value', $received);
    }

    public function testOnlyTrueCountsAsPass(): void
    {
        // Truthy but not === true should fail
        $rule = new ClosureRule(fn(mixed $v) => 1);
        $this->assertFalse($rule->passes('anything'));
    }

    public function testMessage(): void
    {
        $rule = new ClosureRule(fn(mixed $v): bool => true);
        $this->assertSame('The custom field is invalid.', $rule->message('custom'));
    }
}
