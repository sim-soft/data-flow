<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\ValidationException;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\SchemaValidator;

/**
 * OptionalFieldSkipsValidationPropertyTest
 *
 * Feature: enterprise-resilience, Property 22: Optional field skips validation when null or absent
 *
 * For any schema where a field is NOT marked as `required`, if the field value is null
 * or the field key is absent from the row, ALL other rules for that field SHALL be skipped
 * (no failure).
 *
 * **Validates: Requirements 8.11**
 */
#[CoversClass(SchemaValidator::class)]
class OptionalFieldSkipsValidationPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /** @var string[] Non-required rule combinations (no "required" in the pipe string) */
    private array $optionalRuleSets = [
        'string',
        'int',
        'float',
        'email',
        'int|min:0',
        'int|max:100',
        'int|between:1,50',
        'string|in:active,inactive',
        'float|min:0|max:1000',
        'int|min:1|max:999',
    ];

    /**
     * Generate a random field name.
     */
    private function randomFieldName(): string
    {
        $prefixes = ['field', 'col', 'attr', 'prop', 'data', 'val', 'item'];
        $prefix = $prefixes[array_rand($prefixes)];
        return $prefix . '_' . random_int(1, 9999);
    }

    /**
     * Generate a random optional rule set (without "required").
     */
    private function randomOptionalRuleSet(): string
    {
        return $this->optionalRuleSets[array_rand($this->optionalRuleSets)];
    }

    /**
     * Property 22: Optional field with absent key skips validation (no exception).
     *
     * When a field has rules WITHOUT "required" and the field key is absent from the row,
     * validation SHALL pass without throwing any exception.
     *
     * **Validates: Requirements 8.11**
     */
    #[Test]
    public function optionalFieldAbsentFromRowSkipsValidation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();
            $rules = $this->randomOptionalRuleSet();

            $schema = [$fieldName => $rules];
            $validator = new SchemaValidator($schema);

            // Row does NOT contain the field key at all
            $row = ['other_field' => 'some_value'];
            $dataFrame = new ArrayIterator([$row]);

            // Should not throw — validation is skipped for absent optional fields
            $result = iterator_to_array($validator($dataFrame));

            $this->assertCount(
                1,
                $result,
                sprintf(
                    'Absent optional field "%s" with rules "%s" must not cause validation failure (iteration %d)',
                    $fieldName,
                    $rules,
                    $i,
                ),
            );
        }
    }

    /**
     * Property 22: Optional field with null value skips validation (no exception).
     *
     * When a field has rules WITHOUT "required" and the field value is null,
     * validation SHALL pass without throwing any exception.
     *
     * **Validates: Requirements 8.11**
     */
    #[Test]
    public function optionalFieldWithNullValueSkipsValidation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();
            $rules = $this->randomOptionalRuleSet();

            $schema = [$fieldName => $rules];
            $validator = new SchemaValidator($schema);

            // Row contains the field key but value is null
            $row = [$fieldName => null];
            $dataFrame = new ArrayIterator([$row]);

            // Should not throw — validation is skipped for null optional fields
            $result = iterator_to_array($validator($dataFrame));

            $this->assertCount(
                1,
                $result,
                sprintf(
                    'Null optional field "%s" with rules "%s" must not cause validation failure (iteration %d)',
                    $fieldName,
                    $rules,
                    $i,
                ),
            );
        }
    }

    /**
     * Property 22: Optional field with non-null value applies normal validation.
     *
     * When a field has rules WITHOUT "required" and the field is present with a non-null value,
     * normal validation SHALL apply (valid values pass, invalid values fail).
     *
     * **Validates: Requirements 8.11**
     */
    #[Test]
    public function optionalFieldWithNonNullValueAppliesNormalValidation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            // Use "int" rule — provide a string value which should fail
            $schema = [$fieldName => 'int'];
            $validator = new SchemaValidator($schema);

            // Row contains the field with a non-null, invalid value (string instead of int)
            $row = [$fieldName => 'not_an_integer_' . random_int(1, 9999)];
            $dataFrame = new ArrayIterator([$row]);

            $threw = false;
            try {
                iterator_to_array($validator($dataFrame));
            } catch (ValidationException $e) {
                $threw = true;
                $this->assertSame(
                    $fieldName,
                    $e->getFieldName(),
                    sprintf('Exception field name must match "%s" (iteration %d)', $fieldName, $i),
                );
            }

            $this->assertTrue(
                $threw,
                sprintf(
                    'Optional field "%s" with non-null invalid value must trigger validation (iteration %d)',
                    $fieldName,
                    $i,
                ),
            );
        }
    }

    /**
     * Property 22: Optional field with valid non-null value passes validation.
     *
     * When a field has rules WITHOUT "required" and the field is present with a valid non-null value,
     * validation SHALL pass without exception.
     *
     * **Validates: Requirements 8.11**
     */
    #[Test]
    public function optionalFieldWithValidNonNullValuePassesValidation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            // Use "int|min:0" rule — provide a valid integer
            $schema = [$fieldName => 'int|min:0'];
            $validator = new SchemaValidator($schema);

            $validValue = random_int(0, 10000);
            $row = [$fieldName => $validValue];
            $dataFrame = new ArrayIterator([$row]);

            // Should not throw — value is valid
            $result = iterator_to_array($validator($dataFrame));

            $this->assertCount(
                1,
                $result,
                sprintf(
                    'Optional field "%s" with valid value %d must pass validation (iteration %d)',
                    $fieldName,
                    $validValue,
                    $i,
                ),
            );
        }
    }
}
