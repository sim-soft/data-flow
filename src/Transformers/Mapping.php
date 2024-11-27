<?php

namespace Simsoft\DataFlow\Transformers;

use Simsoft\DataFlow\Transformer;
use Iterator;

/**
 * Mapping class
 */
class Mapping extends Transformer
{
    /** @var bool Return as new dataframe. Default: false. */
    protected bool $newDataFrame = false;

    /**
     * Constructor.
     *
     * @param string[]|callable[] $mappings Mapping config.
     */
    public function __construct(protected array $mappings)
    {

    }

    /**
     * Whether to return as new dataframe or not.
     *
     * @return $this
     */
    public function newDataFrame(): static
    {
        $this->newDataFrame = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        if ($dataFrame) {
            foreach ($dataFrame as $index => $value) {
                $attributes = !$this->newDataFrame && is_array($value) ? $value : [];
                foreach ($this->mappings as $to => $from) {
                    $attributes[$to] = match (true) {
                        is_callable($from) => $from($value, $index) ?? null,
                        is_array($value) && array_key_exists($from, $value) => $value[$from],
                        default => $from,
                    };
                }
                yield $index => $attributes;
            }
        }
    }
}
