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
            $currentChunk = []; // Temporary array to hold items

            foreach ($dataFrame as $item) {
                $currentChunk[] = $item; // Add item to the current chunk
                if (count($currentChunk) === $this->chunkSize) {
                    yield $currentChunk; // Add the chunk to the result
                    $currentChunk = []; // Reset the current chunk
                }
            }

            if (!empty($currentChunk)) {
                yield $currentChunk; // Add the remaining items, if any
            }
        }
    }
}
