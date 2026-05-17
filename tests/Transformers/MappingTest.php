<?php

namespace Simsoft\DataFlow\Tests\Transformers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\Mapping;

/**
 * MappingTest class.
 *
 * Tests for the Mapping transformer field remapping behavior.
 */
#[CoversClass(Mapping::class)]
class MappingTest extends TestCase
{
    /**
     * Test string-to-string mappings copy values from source keys to destination keys.
     * Validates: Requirements 12.1
     */
    #[Test]
    public function stringToStringMappingsCopyValues(): void
    {
        $mapping = new Mapping([
            'full_name' => 'name',
            'email_address' => 'email',
        ]);

        $dataFrame = $this->arrayToIterator([
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);

        $result = $this->iteratorToArray($mapping($dataFrame));

        $this->assertSame('Alice', $result[0]['full_name']);
        $this->assertSame('alice@example.com', $result[0]['email_address']);
        $this->assertSame('Bob', $result[1]['full_name']);
        $this->assertSame('bob@example.com', $result[1]['email_address']);
    }

    /**
     * Test callable mappings use the callable result as the destination value.
     * Validates: Requirements 12.2
     */
    #[Test]
    public function callableMappingsUseCallableResult(): void
    {
        $mapping = new Mapping([
            'full_name' => fn($row) => $row['first_name'] . ' ' . $row['last_name'],
            'age_doubled' => fn($row) => $row['age'] * 2,
        ]);

        $dataFrame = $this->arrayToIterator([
            ['first_name' => 'Alice', 'last_name' => 'Smith', 'age' => 30],
        ]);

        $result = $this->iteratorToArray($mapping($dataFrame));

        $this->assertSame('Alice Smith', $result[0]['full_name']);
        $this->assertSame(60, $result[0]['age_doubled']);
    }

    /**
     * Test missing source key uses the mapping value itself as a literal default.
     * Validates: Requirements 12.3
     */
    #[Test]
    public function missingSourceKeyUsesLiteralDefault(): void
    {
        $mapping = new Mapping([
            'status' => 'nonexistent_key',
        ]);

        $dataFrame = $this->arrayToIterator([
            ['name' => 'Alice'],
        ]);

        $result = $this->iteratorToArray($mapping($dataFrame));

        // When source key doesn't exist, the mapping value ('nonexistent_key') is used as literal
        $this->assertSame('nonexistent_key', $result[0]['status']);
    }

    /**
     * Test newDataFrame() produces output rows containing only the mapped keys.
     * Validates: Requirements 12.4
     */
    #[Test]
    public function newDataFrameOutputContainsOnlyMappedKeys(): void
    {
        $mapping = (new Mapping([
            'full_name' => 'name',
            'years' => 'age',
        ]))->newDataFrame();

        $dataFrame = $this->arrayToIterator([
            ['name' => 'Alice', 'age' => 30, 'email' => 'alice@example.com'],
        ]);

        $result = $this->iteratorToArray($mapping($dataFrame));

        $this->assertSame(['full_name' => 'Alice', 'years' => 30], $result[0]);
        $this->assertArrayNotHasKey('name', $result[0]);
        $this->assertArrayNotHasKey('age', $result[0]);
        $this->assertArrayNotHasKey('email', $result[0]);
    }

    /**
     * Test without newDataFrame() output rows retain original keys plus mapped keys.
     * Validates: Requirements 12.5
     */
    #[Test]
    public function withoutNewDataFrameRetainsOriginalKeysPlusMapped(): void
    {
        $mapping = new Mapping([
            'full_name' => 'name',
        ]);

        $dataFrame = $this->arrayToIterator([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $result = $this->iteratorToArray($mapping($dataFrame));

        // Original keys are retained
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('age', $result[0]);
        // Mapped key is added
        $this->assertArrayHasKey('full_name', $result[0]);
        $this->assertSame('Alice', $result[0]['full_name']);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame(30, $result[0]['age']);
    }

    /**
     * Test null dataframe produces no items.
     * Validates: Requirements 12.6
     */
    #[Test]
    public function nullDataFrameYieldsNoItems(): void
    {
        $mapping = new Mapping([
            'full_name' => 'name',
        ]);

        $result = $this->iteratorToArray($mapping(null));

        $this->assertEmpty($result);
    }
}
