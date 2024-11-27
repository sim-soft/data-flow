<?php

namespace Simsoft\DataFlow\Traits;

use Simsoft\DataFlow\Payload;

/**
 * PayloadHandling trait.
 */
trait PayloadHandling
{
    /** @var Payload|null */
    protected ?Payload $payload = null;

    /**
     * Set payload.
     *
     * @param ?Payload $payload
     * @return $this
     */
    public function setPayload(?Payload $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Get payload.
     *
     * @return Payload|null
     */
    public function &getPayload(): ?Payload
    {
        return $this->payload;
    }
}
