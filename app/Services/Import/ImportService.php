<?php

namespace App\Services\Import;

use App\Models\Import;
use App\Services\Import\Contracts\ImportServiceInterface;
use App\Services\Import\Dto\ColumnMappingDto;
use App\Services\Import\Dto\StartImportDto;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use App\Services\Import\Support\ImportTranslator;
use App\Services\Import\Xlsx\XlsxCellValue;
use App\Services\Import\Xlsx\XlsxRowReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportService implements ImportServiceInterface
{
    /**
     * MySQL prepared statements allow at most 65_535 placeholders.
     * Leave headroom for driver overhead and variable column counts.
     */
    private const int MAX_INSERT_PLACEHOLDERS = 60_000;

    public function __construct(
        private readonly XlsxRowReader $xlsxRowReader,
    ) {
    }

    public function previewColumns(string $absolutePath): array
    {
        return $this->xlsxRowReader->previewFirstRow($absolutePath);
    }

    public function start(StartImportDto $dto): Import
    {
        ignore_user_abort(true);
        set_time_limit(30);

        $storedRelativePath = $this->storeUploadedWorkbook($dto->absolutePath, $dto->originalFilename);

        $import = Import::query()->create([
            'original_filename' => $dto->originalFilename,
            'stored_path' => $storedRelativePath,
            'column_mapping' => $dto->columnMapping->toArray(),
            'has_header_row' => $dto->hasHeaderRow,
            'status' => ImportStatusEnumerator::Running,
            'processed_count' => 0,
            'imported_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
        ]);

        $state = new class () {
            public bool $committed = false;
        };
        $importId = $import->id;

        register_shutdown_function(function () use ($importId, $state): void {
            if ($state->committed) {
                return;
            }

            try {
                if (! app()->bound('db')) {
                    return;
                }

                $fresh = Import::query()->find($importId);

                if ($fresh === null || $fresh->status !== ImportStatusEnumerator::Running) {
                    return;
                }

                $fresh->forceFill([
                    'status' => ImportStatusEnumerator::Failed,
                    'fatal_error' => ImportTranslator::get('import.errors.fatal_interrupted'),
                    'finished_at' => now(),
                ])->save();
            } catch (Throwable) {
                // Application may already be torn down during tests.
            }
        });

        DB::disableQueryLog();

        try {
            DB::transaction(function () use ($import, $dto, $storedRelativePath): void {
                $absolutePath = Storage::disk('local')->path($storedRelativePath);
                $this->importWorkbook($import, $absolutePath, $dto->columnMapping, $dto->hasHeaderRow);
            });

            $state->committed = true;

            $import->refresh();

            return $import;
        } catch (Throwable $exception) {
            try {
                $import->forceFill([
                    'status' => ImportStatusEnumerator::Failed,
                    'fatal_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'finished_at' => now(),
                ])->save();
            } catch (Throwable) {
                // Prefer returning a failed import over bubbling a secondary save error.
            }

            $state->committed = true;

            return $import->refresh();
        }
    }

    private function insertChunkSize(int $columnCount): int
    {
        return max(1, intdiv(self::MAX_INSERT_PLACEHOLDERS, max(1, $columnCount)));
    }

    private function storeUploadedWorkbook(string $absolutePath, string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'xlsx';
        $relativePath = 'imports/'.uniqid('import_', true).'.'.$extension;
        $destination = Storage::disk('local')->path($relativePath);
        $directory = dirname($destination);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create import directory: {$directory}");
        }

        if (! copy($absolutePath, $destination)) {
            throw new \RuntimeException('Unable to store uploaded workbook.');
        }

        return $relativePath;
    }

    private function importWorkbook(
        Import $import,
        string $absolutePath,
        ColumnMappingDto $mapping,
        bool $hasHeaderRow,
    ): void {
        /** @var array<string, true> $seenExternalIds */
        $seenExternalIds = $this->loadExistingExternalIds();
        /** @var array<string, true> $fileExternalIds */
        $fileExternalIds = [];

        $selectedFields = $mapping->selectedFields();
        $fieldLetters = [];

        foreach ($selectedFields as $field) {
            $letter = $mapping->columnFor($field);

            if ($letter !== null) {
                $fieldLetters[$field->value] = $letter;
            }
        }

        $leadChunk = [];
        $failureChunk = [];
        $processed = 0;
        $imported = 0;
        $failed = 0;
        $isFirstRow = true;
        $now = now()->format('Y-m-d H:i:s');
        $leadChunkSize = $this->insertChunkSize(max(1, count($selectedFields)));
        $failureChunkSize = $this->insertChunkSize(7);

        foreach ($this->xlsxRowReader->rows($absolutePath) as $row) {
            if ($hasHeaderRow && $isFirstRow) {
                $isFirstRow = false;

                continue;
            }

            $isFirstRow = false;
            $processed++;

            $prepared = $this->prepareRow(
                $row['row_number'],
                $row['cells'],
                $selectedFields,
                $fieldLetters,
                $seenExternalIds,
                $fileExternalIds,
            );

            if ($prepared['errors'] !== []) {
                $failureChunk[] = [
                    'import_id' => $import->id,
                    'row_number' => $prepared['row_number'],
                    'external_id' => $prepared['external_id'],
                    'raw_values' => json_encode($prepared['raw_values'], JSON_UNESCAPED_UNICODE),
                    'errors' => json_encode($prepared['errors'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $failed++;

                if (count($failureChunk) >= $failureChunkSize) {
                    DB::table('import_failures')->insert($failureChunk);
                    $failureChunk = [];
                }

                continue;
            }

            $leadChunk[] = $prepared['lead'];
            $imported++;

            if (count($leadChunk) >= $leadChunkSize) {
                DB::table('leads')->insert($leadChunk);
                $leadChunk = [];
            }
        }

        if ($leadChunk !== []) {
            DB::table('leads')->insert($leadChunk);
        }

        if ($failureChunk !== []) {
            DB::table('import_failures')->insert($failureChunk);
        }

        $import->forceFill([
            'status' => ImportStatusEnumerator::Completed,
            'processed_count' => $processed,
            'imported_count' => $imported,
            'failed_count' => $failed,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, true>
     */
    private function loadExistingExternalIds(): array
    {
        $existing = [];

        DB::table('leads')
            ->select(['id', 'external_id'])
            ->orderBy('id')
            ->chunkById(10000, function ($rows) use (&$existing): void {
                foreach ($rows as $row) {
                    $existing[$row->external_id] = true;
                }
            }, 'id');

        return $existing;
    }

    /**
     * @param  list<LeadFieldEnumerator>  $selectedFields
     * @param  array<string, string>  $fieldLetters
     * @param  array<string, true>  $seenExternalIds
     * @param  array<string, true>  $fileExternalIds
     * @param  array<string, string>  $cells
     * @return array{
     *     row_number: int,
     *     external_id: string|null,
     *     raw_values: array<string, string|null>,
     *     errors: array<string, string>,
     *     lead: array<string, mixed>
     * }
     */
    private function prepareRow(
        int $rowNumber,
        array $cells,
        array $selectedFields,
        array $fieldLetters,
        array &$seenExternalIds,
        array &$fileExternalIds,
    ): array {
        $rawValues = [];
        $errors = [];
        $lead = [];

        foreach ($selectedFields as $field) {
            $letter = $fieldLetters[$field->value] ?? null;
            $raw = $letter !== null ? ($cells[$letter] ?? null) : null;
            $rawValues[$field->value] = $raw;

            $result = $this->normalizeField($field, $raw);

            if ($result['error'] !== null) {
                $errors[$field->value] = $result['error'];
            }

            $lead[$field->value] = $result['value'];
        }

        $externalId = $lead[LeadFieldEnumerator::ExternalId->value] ?? null;

        if (! is_string($externalId) || $externalId === '') {
            $errors[LeadFieldEnumerator::ExternalId->value] = ImportTranslator::get('import.errors.external_id_empty');
            $externalId = is_string($externalId) ? $externalId : null;
        } elseif (isset($fileExternalIds[$externalId])) {
            $errors[LeadFieldEnumerator::ExternalId->value] = ImportTranslator::get('import.errors.external_id_duplicate_file');
        } elseif (isset($seenExternalIds[$externalId])) {
            $errors[LeadFieldEnumerator::ExternalId->value] = ImportTranslator::get('import.errors.external_id_duplicate_db');
        } else {
            $fileExternalIds[$externalId] = true;
            $seenExternalIds[$externalId] = true;
        }

        $budgetKey = LeadFieldEnumerator::BudgetUah->value;

        if ($errors === [] && array_key_exists($budgetKey, $lead) && $lead[$budgetKey] !== null) {
            $lead[$budgetKey] = (int) $lead[$budgetKey];
        }

        return [
            'row_number' => $rowNumber,
            'external_id' => $externalId,
            'raw_values' => $rawValues,
            'errors' => $errors,
            'lead' => $lead,
        ];
    }

    /**
     * @return array{value: string|null, error: string|null}
     */
    private function normalizeField(LeadFieldEnumerator $field, ?string $raw): array
    {
        if ($field->isDateTime()) {
            $string = XlsxCellValue::asString($raw);

            if ($string === null) {
                return ['value' => null, 'error' => null];
            }

            $date = XlsxCellValue::asDateTime($string);

            if ($date === null) {
                return ['value' => null, 'error' => ImportTranslator::get('import.errors.invalid_datetime')];
            }

            return ['value' => $date, 'error' => null];
        }

        if ($field === LeadFieldEnumerator::BudgetUah) {
            $string = XlsxCellValue::asString($raw);

            if ($string === null) {
                return ['value' => null, 'error' => null];
            }

            $int = XlsxCellValue::asNonNegativeInteger($string);

            if ($int === null) {
                return ['value' => null, 'error' => ImportTranslator::get('import.errors.invalid_budget')];
            }

            return ['value' => (string) $int, 'error' => null];
        }

        if ($field === LeadFieldEnumerator::Email) {
            $string = XlsxCellValue::asString($raw);

            if ($string === null) {
                return ['value' => null, 'error' => null];
            }

            if (filter_var($string, FILTER_VALIDATE_EMAIL) === false) {
                return ['value' => null, 'error' => ImportTranslator::get('import.errors.invalid_email')];
            }

            return ['value' => $string, 'error' => null];
        }

        $string = XlsxCellValue::asString($raw);

        if ($string === null) {
            return ['value' => null, 'error' => null];
        }

        $maxLength = $field->maxLength();

        if ($maxLength !== null && strlen($string) > $maxLength) {
            return ['value' => null, 'error' => ImportTranslator::get('import.errors.too_long', ['max' => $maxLength])];
        }

        return ['value' => $string, 'error' => null];
    }
}
