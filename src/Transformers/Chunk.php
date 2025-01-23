<?php

namespace Simsoft\DataFlow\Transformers;

use Iterator;
use Simsoft\DataFlow\Transformer;

/**
 * Chunk class
 *
 * Chunk data into batch
 */
class Chunk extends Transformer
{
    /**
     * Constructor
     *
     * @param int $chunkSize
     */
    public function __construct(protected int $chunkSize = 20)
    {

    }

    /**
     * @inheritDoc
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        if ($dataFrame) {
            $chunk = []; // Temporary array to hold items

            foreach ($dataFrame as $item) {
                $chunk[] = $item; // Add item to the current chunk
                if (count($chunk) === $this->chunkSize) {
                    yield $chunk; // Add the chunk to the result
                    $chunk = []; // Reset the current chunk
                }
            }

            if ($chunk) {
                yield $chunk; // Add the remaining items, if any
            }
        }
    }
}
