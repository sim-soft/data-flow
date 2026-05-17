<?php

namespace Simsoft\DataFlow\Tests;

use Iterator;
use ArrayIterator;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class for the DataFlow test suite.
 *
 * Provides common helper methods for iterator handling and fixture file access.
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Convert an Iterator to an array (consuming it).
     *
     * @param Iterator $iterator The iterator to convert.
     * @return array The resulting array with preserved keys.
     */
    protected function iteratorToArray(Iterator $iterator): array
    {
        $result = [];
        foreach ($iterator as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * Create an ArrayIterator from an array.
     *
     * @param array $data The source array.
     * @return ArrayIterator The resulting iterator.
     */
    protected function arrayToIterator(array $data): ArrayIterator
    {
        return new ArrayIterator($data);
    }

    /**
     * Get path to a test fixture file.
     *
     * @param string $filename The fixture filename.
     * @return string The full path to the fixture file.
     */
    protected function fixturePath(string $filename): string
    {
        return __DIR__ . '/fixtures/' . $filename;
    }
}
