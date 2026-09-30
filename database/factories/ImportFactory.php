<?php

namespace Database\Factories;

use App\Models\Import;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_filename' => 'leads.xlsx',
            'stored_path' => 'imports/example.xlsx',
            'column_mapping' => [
                LeadFieldEnumerator::ExternalId->value => 'A',
            ],
            'has_header_row' => true,
            'status' => ImportStatusEnumerator::Completed,
            'processed_count' => 0,
            'imported_count' => 0,
            'failed_count' => 0,
            'fatal_error' => null,
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
