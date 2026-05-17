<?php

namespace Simsoft\DataFlow\Tests\Transformers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\ValidationException;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\SchemaValidator;

/**
 * SchemaValidatorTest class
 */
#[CoversClass(SchemaValidator::class)]
class SchemaValidatorTest extends TestCase
{
    #[Test]
    public function validRowPassesThrough(): void
    {
        $validator = new SchemaValidator([
            'name' => 'required|string',
            'age' => 'required|int|min:0',
        ]);

        $input = $this->arrayToIterator([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $result = $this->iteratorToArray($validator($input));

        $this->assertCount(2, $result);
        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Bob', $result[1]['name']);
    }

    #[Test]
    public function missingRequiredFieldThrowsValidationException(): void
    {
        $validator = new SchemaValidator([
            'email' => 'required|email',
        ]);

        $input = $this->arrayToIterator([
            ['name' => 'Alice'],
        ]);

        $this->expectException(ValidationException::class);

        $this->iteratorToArray($validator($input));
    }

    #[Test]
    public function invalidFieldValueThrowsValidationException(): void
    {
        $validator = new SchemaValidator([
            'age' => 'required|int',
        ]);

        $input = $this->arrayToIterator([
            ['age' => 'not-an-int'],
        ]);

        $this->expectException(ValidationException::class);

        $this->iteratorToArray($validator($input));
    }

    #[Test]
    public function optionalFieldMissingSkipsValidation(): void
    {
        $validator = new SchemaValidator([
            'nickname' => 'string',
        ]);

        $input = $this->arrayToIterator([
            ['name' => 'Alice'],
        ]);

        $result = $this->iteratorToArray($validator($input));

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    #[Test]
    public function optionalFieldNullSkipsValidation(): void
    {
        $validator = new SchemaValidator([
            'nickname' => 'string',
        ]);

        $input = $this->arrayToIterator([
            ['nickname' => null, 'name' => 'Alice'],
        ]);

        $result = $this->iteratorToArray($validator($input));

        $this->assertCount(1, $result);
    }

    #[Test]
    public function fieldsNotInSchemaPassThroughUnchanged(): void
    {
        $validator = new SchemaValidator([
            'name' => 'required|string',
        ]);

        $input = $this->arrayToIterator([
            ['name' => 'Alice', 'extra' => 'data', 'count' => 42],
        ]);

        $result = $this->iteratorToArray($validator($input));

        $this->assertSame('data', $result[0]['extra']);
        $this->assertSame(42, $result[0]['count']);
    }

    #[Test]
    public function closureRuleFailureThrowsValidationException(): void
    {
        $validator = new SchemaValidator([
            'score' => fn(mixed $v): bool => is_numeric($v) && $v >= 0,
        ]);

        $input = $this->arrayToIterator([
            ['score' => -5],
        ]);

        $this->expectException(ValidationException::class);

        $this->iteratorToArray($validator($input));
    }

    #[Test]
    public function closureRulePassesWhenReturnsTrue(): void
    {
        $validator = new SchemaValidator([
            'score' => fn(mixed $v): bool => is_numeric($v) && $v >= 0,
        ]);

        $input = $this->arrayToIterator([
            ['score' => 10],
        ]);

        $result = $this->iteratorToArray($validator($input));

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['score']);
    }

    #[Test]
    public function validationExceptionContainsFieldName(): void
    {
        $validator = new SchemaValidator([
            'email' => 'required|email',
        ]);

        $input = $this->arrayToIterator([
            ['email' => 'not-an-email'],
        ]);

        try {
            $this->iteratorToArray($validator($input));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('email', $e->getFieldName());
            $this->assertStringContainsString('email', $e->getMessage());
        }
    }

    #[Test]
    public function nullDataframeYieldsNoItems(): void
    {
        $validator = new SchemaValidator([
            'name' => 'required|string',
        ]);

        $result = $this->iteratorToArray($validator(null));

        $this->assertSame([], $result);
    }

    #[Test]
    public function multipleRulesAppliedInOrder(): void
    {
        $validator = new SchemaValidator([
            'age' => 'required|int|min:0|max:150',
        ]);

        $input = $this->arrayToIterator([
            ['age' => 200],
        ]);

        $this->expectException(ValidationException::class);

        $this->iteratorToArray($validator($input));
    }
}
