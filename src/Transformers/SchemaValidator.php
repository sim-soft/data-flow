<?php

namespace Simsoft\DataFlow\Transformers;

use Closure;
use Iterator;
use Simsoft\DataFlow\Exceptions\ValidationException;
use Simsoft\DataFlow\Interfaces\ValidationRule;
use Simsoft\DataFlow\Rules\RequiredRule;
use Simsoft\DataFlow\RuleParser;
use Simsoft\DataFlow\Transformer;

/**
 * SchemaValidator - validates each row against a declared schema.
 *
 * Fields not in the schema pass through unchanged.
 * Fields without a "required" rule that are missing/null skip validation.
 */
final class SchemaValidator extends Transformer
{
    /** @var array<string, ValidationRule[]> Parsed rules per field (cached). */
    private array $parsedRules = [];

    /** @var array<string, bool> Whether each field has a required rule. */
    private array $fieldIsRequired = [];

    /**
     * Constructor.
     *
     * @param array<string, string|array<int, string|Closure>|Closure> $schema
     */
    public function __construct(private readonly array $schema)
    {
        $this->parseSchema();
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        if ($dataFrame) {
            foreach ($dataFrame as $index => $row) {
                $this->validateRow($row);
                yield $index => $row;
            }
        }
    }

    /**
     * Parse the schema into ValidationRule arrays and determine required fields.
     */
    private function parseSchema(): void
    {
        foreach ($this->schema as $field => $rules) {
            // If the rule is a single Closure, wrap it in an array for RuleParser
            if ($rules instanceof Closure) {
                $rules = [$rules];
            }

            $parsed = RuleParser::parse($rules);
            $this->parsedRules[$field] = $parsed;

            // Determine if this field has a required rule
            $this->fieldIsRequired[$field] = false;
            foreach ($parsed as $rule) {
                if ($rule instanceof RequiredRule) {
                    $this->fieldIsRequired[$field] = true;
                    break;
                }
            }
        }
    }

    /**
     * Validate a single row against the schema.
     *
     * @param mixed $row
     * @throws ValidationException
     */
    private function validateRow(mixed $row): void
    {
        if (!is_array($row)) {
            return;
        }

        foreach ($this->parsedRules as $field => $rules) {
            $fieldExists = array_key_exists($field, $row);
            $value = $fieldExists ? $row[$field] : null;

            // If field is not required and is missing or null, skip all validation
            if (!$this->fieldIsRequired[$field] && (!$fieldExists || $value === null)) {
                continue;
            }

            foreach ($rules as $rule) {
                // For required rule, check field existence
                if ($rule instanceof RequiredRule && !$fieldExists) {
                    throw new ValidationException(
                        $field,
                        'required',
                        $rule->message($field),
                    );
                }

                if (!$rule->passes($value)) {
                    throw new ValidationException(
                        $field,
                        $this->getRuleName($rule),
                        $rule->message($field),
                    );
                }
            }
        }
    }

    /**
     * Get the short name of a validation rule.
     */
    private function getRuleName(ValidationRule $rule): string
    {
        $class = get_class($rule);
        $shortName = basename(str_replace('\\', '/', $class));

        // Remove "Rule" suffix and lowercase
        return lcfirst(str_replace('Rule', '', $shortName));
    }
}
