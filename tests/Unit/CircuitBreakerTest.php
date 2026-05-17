<?php

namespace Simsoft\DataFlow\Tests\Unit;

use Simsoft\DataFlow\CircuitBreaker;
use Simsoft\DataFlow\CircuitBreakerConfig;
use Simsoft\DataFlow\Enums\CircuitState;
use Simsoft\DataFlow\Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function test_starts_in_closed_state(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig());
        $this->assertSame(CircuitState::Closed, $cb->getState());
    }

    public function test_call_allowed_in_closed_state(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig());
        $this->assertTrue($cb->isCallAllowed());
    }

    public function test_transitions_to_open_after_reaching_failure_threshold(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 3));

        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame(CircuitState::Closed, $cb->getState());

        $cb->recordFailure();
        $this->assertSame(CircuitState::Open, $cb->getState());
    }

    public function test_call_not_allowed_in_open_state_before_cooldown(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 60000));
        $cb->recordFailure();

        $this->assertSame(CircuitState::Open, $cb->getState());
        $this->assertFalse($cb->isCallAllowed());
    }

    public function test_transitions_to_half_open_after_cooldown(): void
    {
        // Use a 1ms cooldown so it expires almost immediately
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 1));
        $cb->recordFailure();

        $this->assertSame(CircuitState::Open, $cb->getState());

        // Wait for cooldown to elapse
        usleep(2000); // 2ms

        $this->assertTrue($cb->isCallAllowed());
        $this->assertSame(CircuitState::HalfOpen, $cb->getState());
    }

    public function test_call_allowed_in_half_open_state(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 1));
        $cb->recordFailure();

        usleep(2000);
        $cb->isCallAllowed(); // triggers transition to HalfOpen

        $this->assertSame(CircuitState::HalfOpen, $cb->getState());
        $this->assertTrue($cb->isCallAllowed());
    }

    public function test_success_in_closed_state_resets_failure_counter(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 3));

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordSuccess(); // resets counter

        // Need 3 more failures to open (not 1)
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame(CircuitState::Closed, $cb->getState());

        $cb->recordFailure();
        $this->assertSame(CircuitState::Open, $cb->getState());
    }

    public function test_success_in_half_open_transitions_to_closed(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 1));
        $cb->recordFailure();

        usleep(2000);
        $cb->isCallAllowed(); // transition to HalfOpen

        $cb->recordSuccess();
        $this->assertSame(CircuitState::Closed, $cb->getState());
    }

    public function test_failure_in_half_open_transitions_to_open(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 1));
        $cb->recordFailure();

        usleep(2000);
        $cb->isCallAllowed(); // transition to HalfOpen

        $cb->recordFailure();
        $this->assertSame(CircuitState::Open, $cb->getState());
    }

    public function test_failure_in_half_open_restarts_cooldown(): void
    {
        $cb = new CircuitBreaker(new CircuitBreakerConfig(failureThreshold: 1, cooldownMs: 50));
        $cb->recordFailure();

        usleep(60000); // 60ms - wait for cooldown
        $cb->isCallAllowed(); // transition to HalfOpen

        $cb->recordFailure(); // back to Open, cooldown restarts

        // Immediately check - should still be Open (new cooldown hasn't elapsed)
        $this->assertSame(CircuitState::Open, $cb->getState());
        $this->assertFalse($cb->isCallAllowed());
    }
}
