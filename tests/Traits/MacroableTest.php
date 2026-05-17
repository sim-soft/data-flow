<?php

namespace Simsoft\DataFlow\Tests\Traits;

use BadMethodCallException;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Traits\Macroable;

/**
 * MacroableTest class
 *
 * Tests for the Macroable trait using an anonymous class.
 */
class MacroableTest extends TestCase
{
    /**
     * Create a fresh anonymous class that uses the Macroable trait.
     *
     * Returns a new class definition each time to avoid macro leakage between tests.
     *
     * @return object An instance using the Macroable trait.
     */
    private function createMacroableInstance(): object
    {
        return new class {
            use Macroable;

            public string $value = 'initial';

            /**
             * Reset macros for test isolation.
             */
            public static function clearMacros(): void
            {
                static::$macros = [];
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear macros before each test to ensure isolation
        $instance = $this->createMacroableInstance();
        $instance::clearMacros();
    }

    #[Test]
    public function macroRegistrationAndCalling(): void
    {
        $instance = $this->createMacroableInstance();

        $instance::macro('greet', function (string $name): string {
            return "Hello, {$name}!";
        });

        $result = $instance->greet('World');

        $this->assertSame('Hello, World!', $result);
    }

    #[Test]
    public function macroClosureBindsThisToCallingInstance(): void
    {
        $instance = $this->createMacroableInstance();

        $instance::macro('getValue', function (): string {
            return $this->value;
        });

        $instance->value = 'modified';
        $result = $instance->getValue();

        $this->assertSame('modified', $result);
    }

    #[Test]
    public function mixinRegistersPublicAndProtectedMethods(): void
    {
        $instance = $this->createMacroableInstance();

        $mixin = new class {
            public function publicMethod(): Closure
            {
                return function (): string {
                    return 'public';
                };
            }

            protected function protectedMethod(): Closure
            {
                return function (): string {
                    return 'protected';
                };
            }

            private function privateMethod(): Closure
            {
                return function (): string {
                    return 'private';
                };
            }
        };

        $instance::mixin($mixin);

        // Public and protected methods should be registered
        $this->assertSame('public', $instance->publicMethod());
        $this->assertSame('protected', $instance->protectedMethod());

        // Private method should NOT be registered
        $this->expectException(BadMethodCallException::class);
        $instance->privateMethod();
    }

    #[Test]
    public function mixinWithReplaceFalseDoesNotOverwriteExistingMacro(): void
    {
        $instance = $this->createMacroableInstance();

        // Register an existing macro
        $instance::macro('duplicate', function (): string {
            return 'original';
        });

        $mixin = new class {
            public function duplicate(): string
            {
                return 'replacement';
            }
        };

        // Mixin with replace: false should NOT overwrite
        $instance::mixin($mixin, replace: false);

        $result = $instance->duplicate();

        $this->assertSame('original', $result);
    }

    #[Test]
    public function mixinClosureRegistration(): void
    {
        $instance = $this->createMacroableInstance();

        $mixin = new class {
            public function closureMethod(): Closure
            {
                return function (string $input): string {
                    return strtoupper($input);
                };
            }
        };

        $instance::mixin($mixin);

        // The Closure returned by the method should be registered as the macro
        $result = $instance->closureMethod('hello');

        $this->assertSame('HELLO', $result);
    }

    #[Test]
    public function undefinedMethodThrowsBadMethodCallException(): void
    {
        $instance = $this->createMacroableInstance();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method nonExistentMethod does not exist.');

        $instance->nonExistentMethod();
    }

    #[Test]
    public function nonClosureCallableIsInvokedViaCallUserFuncArray(): void
    {
        $instance = $this->createMacroableInstance();

        // Register a non-Closure callable (array callable)
        $instance::macro('upperCase', 'strtoupper');

        $result = $instance->upperCase('hello');

        $this->assertSame('HELLO', $result);
    }
}
