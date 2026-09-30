<?php

namespace App\Services\Import\Dto;

use App\Services\Import\Enumerators\LeadFieldEnumerator;
use App\Services\Import\Support\ImportTranslator;
use InvalidArgumentException;

readonly class ColumnMappingDto
{
    /**
     * @param  array<string, string>  $fieldToColumn  field name => Excel column letter (A, B, AA)
     */
    public function __construct(
        public array $fieldToColumn,
    ) {
        if (! array_key_exists(LeadFieldEnumerator::ExternalId->value, $this->fieldToColumn)) {
            throw new InvalidArgumentException(ImportTranslator::get('import.validation.external_id_required'));
        }

        foreach ($this->fieldToColumn as $field => $letter) {
            if (LeadFieldEnumerator::tryFrom($field) === null) {
                throw new InvalidArgumentException(ImportTranslator::get('import.validation.unknown_field', ['field' => $field]));
            }

            if (preg_match('/^[A-Z]+$/', $letter) !== 1) {
                throw new InvalidArgumentException(ImportTranslator::get('import.validation.invalid_column', ['field' => $field]));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fieldToColumn
     */
    public static function fromArray(array $fieldToColumn): self
    {
        $normalized = [];

        foreach ($fieldToColumn as $field => $letter) {
            if ($letter === null || $letter === '') {
                continue;
            }

            $normalized[(string) $field] = strtoupper(trim((string) $letter));
        }

        return new self($normalized);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->fieldToColumn;
    }

    public function columnFor(LeadFieldEnumerator $field): ?string
    {
        return $this->fieldToColumn[$field->value] ?? null;
    }

    /**
     * @return list<LeadFieldEnumerator>
     */
    public function selectedFields(): array
    {
        $fields = [];

        foreach ($this->fieldToColumn as $field => $letter) {
            $enum = LeadFieldEnumerator::from($field);
            $fields[] = $enum;
        }

        return $fields;
    }
}
