<?php

namespace Tests\Feature\Import;

use App\Models\Lead;
use App\Services\Import\Contracts\ImportServiceInterface;
use App\Services\Import\Dto\ColumnMappingDto;
use App\Services\Import\Dto\StartImportDto;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\XlsxFixtureBuilder;
use Tests\TestCase;

class ImportAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('acceptance')]
    public function imports_three_hundred_thousand_rows_within_thirty_seconds(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Acceptance import requires MySQL.');
        }

        Storage::disk('local')->makeDirectory('imports');

        $path = storage_path('framework/testing/leads-300k.xlsx');
        $totalRows = 300_000;

        XlsxFixtureBuilder::createFromGenerator($path, (static function () use ($totalRows): \Generator {
            yield ['external_id', 'first_name', 'budget_uah'];

            for ($i = 1; $i <= $totalRows; $i++) {
                yield [
                    $i,
                    1,
                    $i % 10000,
                ];
            }
        })());

        set_time_limit(30);
        $startedAt = microtime(true);

        $import = $this->app->make(ImportServiceInterface::class)->start(new StartImportDto(
            absolutePath: $path,
            originalFilename: 'leads-300k.xlsx',
            columnMapping: ColumnMappingDto::fromArray([
                LeadFieldEnumerator::ExternalId->value => 'A',
                LeadFieldEnumerator::FirstName->value => 'B',
                LeadFieldEnumerator::BudgetUah->value => 'C',
            ]),
            hasHeaderRow: true,
        ));

        $elapsed = microtime(true) - $startedAt;

        $this->assertLessThan(30, $elapsed, 'Import exceeded 30 seconds: '.$elapsed);
        $this->assertSame(ImportStatusEnumerator::Completed, $import->status);
        $this->assertSame($totalRows, $import->processed_count);
        $this->assertSame($totalRows, $import->imported_count + $import->failed_count);
        $this->assertSame($totalRows, Lead::query()->count());
    }
}
