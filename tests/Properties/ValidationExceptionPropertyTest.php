<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\ValidationException;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\SchemaValidator;

/**
 * ValidationExceptionPropertyTest
 *
 * Feature: enterprise-resilience, Property 14: Validation exception contains field and rule
 *
 * For any field name and for any validation rule that fails, the thrown exception
 * SHALL contain the field name (via getFieldName()) and the name of the failing rule
 * (via getRuleName() as a non-empty string), and getMessage() SHALL contain the field name.
 *
 * **Validates: Requirements 7.5**
 */
#[CoversClass(SchemaValidator::class)]
#[CoversClass(ValidationException::class)]
class ValidationExceptionPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random field name (alphabetic, 3-20 chars).
     */
    private function randomFieldName(): string
    {
        $length = random_int(3, 20);
        $name = '';
        for ($i = 0; $i < $length; $i++) {
            $name .= chr(random_int(97, 122)); // a-z
        }
        return $name;
    }

    /**
     * Property 14: ValidationException getFieldName() returns the exact field name that failed.
     *
     * **Validates: Requirements 7.5**
     */
    #[Test]
    public function validationExceptionContainsExactFieldName(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            // Use 'int' rule with a string value to trigger failure
            $schema = [$fieldName => 'required|int'];
            $validator = new SchemaValidator($schema);

            $row = [$fieldName => 'not_an_integer'];

            try {
                $iterator = $validator(new \ArrayIterator([$row]));
                iterator_to_array($iterator);
                $this->fail(sprintf(
                    'Expected ValidationException for field "%s" (iteration %d)',
                    $fieldName,
                    $i
                ));
            } catch (ValidationException $e) {
                $this->assertSame(
                    $fieldName,
                    $e->getFieldName(),
                    sprintf(
                        'getFieldName() must return exact field name "%s" (iteration %d)',
                        $fieldName,
                        $i
                    )
                );
            }
        }
    }

    /**
     * Property 14: ValidationException getRuleName() returns a non-empty string.
     *
     * **Validates: Requirements 7.5**
     */
    #[Test]
    public function validationExceptionRuleNameIsNonEmpty(): void
    {
        // Test with various rule types that will fail
        $ruleConfigs = [
            'int' => 'not_an_integer',   // string fails int rule
            'string' => 42,                  // int fails string rule
            'float' => 'abc',              // string fails float rule
            'email' => 'not-an-email',     // invalid email
            'min:10' => 5,                  // below minimum
            'max:5' => 10,                 // above maximum
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            // Pick a random rule that will fail
            $ruleNames = array_keys($ruleConfigs);
            $ruleKey = $ruleNames[array_rand($ruleNames)];
            $failingValue = $ruleConfigs[$ruleKey];

            $schema = [$fieldName => 'required|' . $ruleKey];
            $validator = new SchemaValidator($schema);

            $row = [$fieldName => $failingValue];

            try {
                $iterator = $validator(new \ArrayIterator([$row]));
                iterator_to_array($iterator);
                $this->fail(sprintf(
                    'Expected ValidationException for rule "%s" on field "%s" (iteration %d)',
                    $ruleKey,
                    $fieldName,
                    $i
                ));
            } catch (ValidationException $e) {
                $this->assertNotEmpty(
                    $e->getRuleName(),
                    sprintf(
                        'getRuleName() must return a non-empty string for rule "%s" (iteration %d)',
                        $ruleKey,
                        $i
                    )
                );
                $this->assertIsString(
                    $e->getRuleName(),
                    sprintf(
                        'getRuleName() must return a string for rule "%s" (iteration %d)',
                        $ruleKey,
                        $i
                    )
                );
            }
        }
    }

    /**
     * Property 14: ValidationException getMessage() contains the field name.
     *
     * **Validates: Requirements 7.5**
     */
    #[Test]
    public function validationExceptionMessageContainsFieldName(): void
    {
        // Test with various rule types that will fail
        $ruleConfigs = [
            'int' => 'not_an_integer',
            'string' => 42,
            'float' => 'abc',
            'email' => 'not-an-email',
            'min:10' => 5,
            'max:5' => 10,
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            // Pick a random rule that will fail
            $ruleNames = array_keys($ruleConfigs);
            $ruleKey = $ruleNames[array_rand($ruleNames)];
            $failingValue = $ruleConfigs[$ruleKey];

            $schema = [$fieldName => 'required|' . $ruleKey];
            $validator = new SchemaValidator($schema);

            $row = [$fieldName => $failingValue];

            try {
                $iterator = $validator(new \ArrayIterator([$row]));
                iterator_to_array($iterator);
                $this->fail(sprintf(
                    'Expected ValidationException for field "%s" (iteration %d)',
                    $fieldName,
                    $i
                ));
            } catch (ValidationException $e) {
                $this->assertStringContainsString(
                    $fieldName,
                    $e->getMessage(),
                    sprintf(
                        'getMessage() must contain field name "%s" (iteration %d), got: "%s"',
                        $fieldName,
                        $i,
                        $e->getMessage()
                    )
                );
            }
        }
    }

    /**
     * Property 14: Required rule failure reports correct field and rule name.
     *
     * **Validates: Requirements 7.5**
     */
    #[Test]
    public function requiredRuleFailureReportsCorrectFieldAndRule(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $fieldName = $this->randomFieldName();

            $schema = [$fieldName => 'required'];
            $validator = new SchemaValidator($schema);

            // Row missing the required field entirely
            $row = ['other_field' => 'value'];

            try {
                $iterator = $validator(new \ArrayIterator([$row]));
                iterator_to_array($iterator);
                $this->fail(sprintf(
                    'Expected ValidationException for missing required field "%s" (iteration %d)',
                    $fieldName,
                    $i
                ));
            } catch (ValidationException $e) {
                $this->assertSame(
                    $fieldName,
                    $e->getFieldName(),
                    sprintf(
                        'getFieldName() must return "%s" for required rule failure (iteration %d)',
                        $fieldName,
                        $i
                    )
                );
                $this->assertSame(
                    'required',
                    $e->getRuleName(),
                    sprintf(
                        'getRuleName() must return "required" for missing field (iteration %d)',
                        $i
                    )
                );
                $this->assertStringContainsString(
                    $fieldName,
                    $e->getMessage(),
                    sprintf(
                        'getMessage() must contain field name "%s" for required rule (iteration %d)',
                        $fieldName,
                        $i
                    )
                );
            }
        }
    }
}
