<?php

namespace Simsoft\DataFlow\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Payload;

/**
 * PayloadTest class.
 *
 * Tests for the Payload shared state container.
 */
#[CoversClass(Payload::class)]
class PayloadTest extends TestCase
{
    /**
     * Test constructor with initial attributes are accessible via property syntax.
     * Validates: Requirements 3.1
     */
    #[Test]
    public function constructorWithInitialAttributes(): void
    {
        $payload = new Payload(['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Alice', $payload->name);
        $this->assertSame(30, $payload->age);
    }

    /**
     * Test property set and get via magic methods.
     * Validates: Requirements 3.2
     */
    #[Test]
    public function propertySetAndGet(): void
    {
        $payload = new Payload();

        $payload->title = 'Engineer';

        $this->assertSame('Engineer', $payload->title);
    }

    /**
     * Test __isset returns true for existing attribute.
     * Validates: Requirements 3.3
     */
    #[Test]
    public function issetReturnsTrueForExistingAttribute(): void
    {
        $payload = new Payload(['color' => 'blue']);

        $this->assertTrue(isset($payload->color));
    }

    /**
     * Test __isset returns false for non-existing attribute.
     * Validates: Requirements 3.4
     */
    #[Test]
    public function issetReturnsFalseForNonExistingAttribute(): void
    {
        $payload = new Payload(['color' => 'blue']);

        $this->assertFalse(isset($payload->missing));
    }

    /**
     * Test __unset removes an attribute.
     * Validates: Requirements 3.5
     */
    #[Test]
    public function unsetRemovesAttribute(): void
    {
        $payload = new Payload(['key' => 'value']);

        unset($payload->key);

        $this->assertFalse(isset($payload->key));
        $this->assertNull($payload->key);
    }

    /**
     * Test offsetExists with a string key returns correct ArrayAccess behavior.
     * Validates: Requirements 3.6
     */
    #[Test]
    public function offsetExistsWithStringKey(): void
    {
        $payload = new Payload(['item' => 'data']);

        $this->assertTrue(isset($payload['item']));
        $this->assertFalse(isset($payload['nonexistent']));
    }

    /**
     * Test offsetGet with a non-string key returns null.
     * Validates: Requirements 3.7
     */
    #[Test]
    public function offsetGetWithNonStringKeyReturnsNull(): void
    {
        $payload = new Payload(['foo' => 'bar']);

        $this->assertNull($payload[0]);
        $this->assertNull($payload[123]);
    }

    /**
     * Test offsetSet with a string key stores the value.
     * Validates: Requirements 3.8
     */
    #[Test]
    public function offsetSetWithStringKey(): void
    {
        $payload = new Payload();

        $payload['language'] = 'PHP';

        $this->assertSame('PHP', $payload['language']);
        $this->assertSame('PHP', $payload->language);
    }

    /**
     * Test getAttribute returns value or null for missing keys.
     * Validates: Requirements 3.9
     */
    #[Test]
    public function getAttributeReturnsValueOrNull(): void
    {
        $payload = new Payload(['status' => 'active']);

        $this->assertSame('active', $payload->getAttribute('status'));
        $this->assertNull($payload->getAttribute('missing'));
    }

    /**
     * Test reset reverts all attributes to initial constructor state.
     * Validates: Requirements 3.10
     */
    #[Test]
    public function resetRevertsToInitialState(): void
    {
        $payload = new Payload(['name' => 'Alice', 'age' => 30]);

        $payload->name = 'Bob';
        $payload->extra = 'added';

        $payload->reset();

        $this->assertSame('Alice', $payload->name);
        $this->assertSame(30, $payload->age);
        $this->assertNull($payload->getAttribute('extra'));
    }
}
