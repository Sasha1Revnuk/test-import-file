<?php

namespace Tests\Unit\Services\Import;

use App\Models\ImportFailure;
use App\Models\Lead;
use App\Services\Import\Contracts\ImportServiceInterface;
use App\Services\Import\Dto\ColumnMappingDto;
use App\Services\Import\Dto\StartImportDto;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use App\Services\Import\Xlsx\XlsxCellValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\XlsxFixtureBuilder;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fixturePath = storage_path('framework/testing/leads-fixture.xlsx');
    }

    private function service(): ImportServiceInterface
    {
        return $this->app->make(ImportServiceInterface::class);
    }

    #[Test]
    public function preview_columns_returns_first_row_cells(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name', 'email'],
            ['LD-1', 'Ivan', 'ivan@example.com'],
        ]);

        $preview = $this->service()->previewColumns($this->fixturePath);

        $this->assertSame('external_id', $preview['A']);
        $this->assertSame('first_name', $preview['B']);
        $this->assertSame('email', $preview['C']);
    }

    #[Test]
    public function start_imports_valid_rows_and_skips_header(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name', 'email', 'budget_uah', 'created_at'],
            ['LD-1', 'Ivan', 'ivan@example.com', 1000, '2025-07-10 05:06:54'],
            ['LD-2', 'Olena', 'olena@example.com', 2000, 45818.21243055556],
        ]);

        $import = $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::FirstName->value => 'B',
            LeadFieldEnumerator::Email->value => 'C',
            LeadFieldEnumerator::BudgetUah->value => 'D',
            LeadFieldEnumerator::CreatedAt->value => 'E',
        ]));

        $this->assertSame(ImportStatusEnumerator::Completed, $import->status);
        $this->assertSame(2, $import->processed_count);
        $this->assertSame(2, $import->imported_count);
        $this->assertSame(0, $import->failed_count);
        $this->assertDatabaseCount(Lead::class, 2);
        $this->assertDatabaseHas(Lead::class, [
            'external_id' => 'LD-1',
            'first_name' => 'Ivan',
            'email' => 'ivan@example.com',
            'budget_uah' => 1000,
        ]);
        $this->assertDatabaseHas(Lead::class, [
            'external_id' => 'LD-2',
            'first_name' => 'Olena',
        ]);
    }

    #[Test]
    public function start_rejects_duplicate_external_id_in_file(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name'],
            ['LD-1', 'Ivan'],
            ['LD-1', 'Olena'],
        ]);

        $import = $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::FirstName->value => 'B',
        ]));

        $this->assertSame(1, $import->imported_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertDatabaseCount(Lead::class, 1);
        $this->assertDatabaseCount(ImportFailure::class, 1);
        $this->assertDatabaseHas(ImportFailure::class, [
            'external_id' => 'LD-1',
            'row_number' => 3,
        ]);
    }

    #[Test]
    public function start_rejects_duplicate_external_id_already_in_database(): void
    {
        Lead::factory()->create(['external_id' => 'LD-1']);

        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name'],
            ['LD-1', 'Ivan'],
            ['LD-2', 'Olena'],
        ]);

        $import = $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::FirstName->value => 'B',
        ]));

        $this->assertSame(1, $import->imported_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertDatabaseCount(Lead::class, 2);
    }

    #[Test]
    public function start_imports_budget_from_excel_float_string(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'budget_uah'],
            ['LD-FLOAT', '23700.0'],
        ]);

        $import = $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::BudgetUah->value => 'B',
        ]));

        $this->assertSame(ImportStatusEnumerator::Completed, $import->status);
        $this->assertSame(1, $import->imported_count);
        $this->assertSame(0, $import->failed_count);
        $this->assertDatabaseHas(Lead::class, [
            'external_id' => 'LD-FLOAT',
            'budget_uah' => 23700,
        ]);
    }

    #[Test]
    public function start_stores_invalid_email_as_failure(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'email'],
            ['LD-1', 'not-an-email'],
        ]);

        $import = $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::Email->value => 'B',
        ]));

        $this->assertSame(0, $import->imported_count);
        $this->assertSame(1, $import->failed_count);
        $this->assertDatabaseCount(Lead::class, 0);
        $this->assertDatabaseCount(ImportFailure::class, 1);
    }

    #[Test]
    public function start_leaves_unmapped_fields_null(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name', 'city'],
            ['LD-1', 'Ivan', 'Kyiv'],
        ]);

        $this->service()->start($this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::FirstName->value => 'B',
        ]));

        $this->assertDatabaseHas(Lead::class, [
            'external_id' => 'LD-1',
            'first_name' => 'Ivan',
            'city' => null,
            'email' => null,
        ]);
    }

    #[Test]
    public function start_requires_external_id_mapping(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ColumnMappingDto::fromArray([
            LeadFieldEnumerator::FirstName->value => 'B',
        ]);
    }

    #[Test]
    public function excel_serial_converts_to_datetime_string(): void
    {
        $this->assertSame(
            '2025-06-10 05:05:54',
            XlsxCellValue::fromExcelSerial(45818.21243055556),
        );
    }

    #[Test]
    public function repeated_import_does_not_duplicate_leads(): void
    {
        XlsxFixtureBuilder::create($this->fixturePath, [
            ['external_id', 'first_name'],
            ['LD-1', 'Ivan'],
        ]);

        $dto = $this->dto([
            LeadFieldEnumerator::ExternalId->value => 'A',
            LeadFieldEnumerator::FirstName->value => 'B',
        ]);

        $this->service()->start($dto);
        $second = $this->service()->start($dto);

        $this->assertSame(0, $second->imported_count);
        $this->assertSame(1, $second->failed_count);
        $this->assertDatabaseCount(Lead::class, 1);
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function dto(array $mapping, bool $hasHeaderRow = true): StartImportDto
    {
        return new StartImportDto(
            absolutePath: $this->fixturePath,
            originalFilename: 'leads.xlsx',
            columnMapping: ColumnMappingDto::fromArray($mapping),
            hasHeaderRow: $hasHeaderRow,
        );
    }
}
