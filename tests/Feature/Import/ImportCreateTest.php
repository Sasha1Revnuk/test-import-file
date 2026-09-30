<?php

namespace Tests\Feature\Import;

use App\Livewire\Home;
use App\Models\Import;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\XlsxFixtureBuilder;
use Tests\TestCase;

class ImportCreateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leads_page_renders_with_create_button_and_active_nav(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('import.leads'), false)
            ->assertSee(__('import.create_title'), false);
    }

    #[Test]
    public function imports_index_renders(): void
    {
        $this->get(route('imports.index'))
            ->assertOk()
            ->assertSee(__('import.title'), false);
    }

    #[Test]
    public function create_import_action_requires_file(): void
    {
        Livewire::test(Home::class)
            ->callAction('createImport', data: [
                'has_header_row' => true,
                'selected' => $this->selectedFields(['external_id']),
                'columns' => $this->defaultColumns(),
            ])
            ->assertHasActionErrors(['file']);
    }

    #[Test]
    public function create_import_action_imports_uploaded_workbook(): void
    {
        Storage::fake('local');

        $path = storage_path('framework/testing/upload-leads.xlsx');
        XlsxFixtureBuilder::create($path, [
            ['external_id', 'first_name'],
            ['LD-100', 'Taras'],
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'leads.xlsx',
            (string) file_get_contents($path),
        );

        $component = Livewire::test(Home::class)
            ->callAction('createImport', data: [
                'file' => $file,
                'has_header_row' => true,
                'selected' => $this->selectedFields(['external_id', 'first_name']),
                'columns' => [
                    ...$this->defaultColumns(),
                    'external_id' => 'A',
                    'first_name' => 'B',
                ],
            ])
            ->assertHasNoActionErrors()
            ->assertNoRedirect()
            ->assertSee(__('import.flash.summary', [
                'processed' => 1,
                'imported' => 1,
                'failed' => 0,
            ]), false)
            ->assertSee(__('import.view_report'), false);

        $this->assertNotNull($component->get('importResult'));
        $this->assertSame(1, $component->get('importResult')['imported']);

        $import = Import::query()->first();

        $this->assertNotNull($import);
        $this->assertSame(route('imports.show', $import), $component->get('importResult')['report_url']);
        $this->assertSame(ImportStatusEnumerator::Completed, $import->status);
        $this->assertSame(1, $import->imported_count);
        $this->assertDatabaseHas('leads', [
            'external_id' => 'LD-100',
            'first_name' => 'Taras',
        ]);
    }

    /**
     * @param  list<string>  $enabled
     * @return array<string, bool>
     */
    private function selectedFields(array $enabled): array
    {
        $selected = [];

        foreach (LeadFieldEnumerator::importable() as $field) {
            $selected[$field->value] = in_array($field->value, $enabled, true);
        }

        return $selected;
    }

    /**
     * @return array<string, string>
     */
    private function defaultColumns(): array
    {
        $columns = [];

        foreach (LeadFieldEnumerator::importable() as $index => $field) {
            $columns[$field->value] = $this->columnLetter($index);
        }

        return $columns;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $modulo = ($index - 1) % 26;
            $letter = chr(65 + $modulo).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
