<?php

namespace Simsoft\DataFlow;

use ArrayAccess;

/**
 * Payload class.
 *
 * @implements ArrayAccess<string|int, mixed>
 */
class Payload implements ArrayAccess
{
    /** @var mixed[] Initial attributes state. */
    protected array $initAttributes = [];

    /**
     * Constructor.
     *
     * @param string[] $attributes Payload attribute.
     */
    public function __construct(protected array $attributes = [])
    {
        $this->initAttributes = $this->attributes;
    }

    /**
     * Setter
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Isset attribute.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Unset attribute.
     *
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->__isset($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->__get($offset) : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        is_string($offset) && $this->__set($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        is_string($offset) && $this->__unset($offset);
    }

    /**
     * Get attribute by name.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Reset to initial state.
     *
     * @return void
     */
    public function reset(): void
    {
        unset($this->attributes);
        $this->attributes = $this->initAttributes;
    }
}
