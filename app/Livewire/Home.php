<?php

namespace App\Livewire;

use App\Filament\Actions\StartLeadImportAction;
use App\Models\Import;
use App\Models\Lead;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Support\ImportTranslator;
use App\Services\Lead\Contracts\LeadServiceInterface;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Home extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use RestrictsFileUploadsToSchemaComponents;

    /**
     * @var array{
     *     processed: int,
     *     imported: int,
     *     failed: int,
     *     is_failed: bool,
     *     message: string,
     *     report_url: string
     * }|null
     */
    public ?array $importResult = null;

    public function showImportResult(Import $import): void
    {
        $isFailed = $import->status === ImportStatusEnumerator::Failed;

        $this->importResult = [
            'processed' => $import->processed_count,
            'imported' => $import->imported_count,
            'failed' => $import->failed_count,
            'is_failed' => $isFailed,
            'message' => $isFailed
                ? ImportTranslator::get('import.flash.failed', [
                    'error' => (string) ($import->fatal_error ?? ImportTranslator::get('import.statuses.failed')),
                ])
                : ImportTranslator::get('import.flash.summary', [
                    'processed' => $import->processed_count,
                    'imported' => $import->imported_count,
                    'failed' => $import->failed_count,
                ]),
            'report_url' => route('imports.show', $import),
        ];
    }

    public function dismissImportResult(): void
    {
        $this->importResult = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Lead::query())
            ->columns([
                TextColumn::make('external_id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s'),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('utm_campaign')
                    ->searchable(),
                TextColumn::make('product')
                    ->searchable(),
                TextColumn::make('budget_uah')
                    ->numeric(decimalPlaces: 0),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('manager')
                    ->searchable(),
                TextColumn::make('comment')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('next_contact_at')
                    ->dateTime('Y-m-d H:i:s'),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(function (Lead $record): bool {
                        return app(LeadServiceInterface::class)->delete($record);
                    }),
            ]);
    }

    public function createImportAction(): Action
    {
        return StartLeadImportAction::make();
    }

    public function clearLeadsAction(): Action
    {
        return Action::make('clearLeads')
            ->label(__('import.clear_table'))
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->button()
            ->requiresConfirmation()
            ->modalHeading(__('import.clear_table_heading'))
            ->modalDescription(__('import.clear_table_description'))
            ->modalSubmitActionLabel(__('import.clear_table'))
            ->action(function (): void {
                $deleted = app(LeadServiceInterface::class)->deleteAll();

                Notification::make()
                    ->title(__('import.flash.cleared', ['count' => $deleted]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.home')
            ->layout('layouts::app');
    }
}
