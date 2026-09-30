<?php

namespace App\Livewire\Import;

use App\Models\Import;
use App\Models\ImportFailure;
use App\Services\Import\Enumerators\ImportStatusEnumerator;
use App\Services\Import\Support\ImportTranslator;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ImportShow extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @phpstan-ignore property.uninitialized */
    public Import $import;

    public function mount(Import $import): void
    {
        $this->import = $import;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportFailure::query()
                    ->where('import_id', $this->import->id)
                    ->orderBy('row_number')
            )
            ->columns([
                TextColumn::make('row_number')
                    ->label(ImportTranslator::get('import.row_number')),
                TextColumn::make('external_id')
                    ->label(ImportTranslator::get('import.external_id')),
                TextColumn::make('errors')
                    ->label(ImportTranslator::get('import.error_reasons'))
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }

                        $parts = [];

                        foreach ($state as $field => $message) {
                            $parts[] = $field.': '.$message;
                        }

                        return implode('; ', $parts);
                    })
                    ->wrap(),
                TextColumn::make('raw_values')
                    ->label(ImportTranslator::get('import.raw_values'))
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state)) {
                            return (string) $state;
                        }

                        return collect($state)
                            ->map(fn (mixed $value, string|int $key): string => $key.'='.(string) $value)
                            ->implode(', ');
                    })
                    ->wrap(),
            ])
            ->paginated([25, 50, 100]);
    }

    public function render(): View
    {
        $this->import->refresh();

        $status = $this->import->status;

        return view('livewire.import.import-show', [
            'statusLabel' => ImportTranslator::get('import.statuses.'.$status->value),
            'isRunning' => $status === ImportStatusEnumerator::Running,
        ])->layout('layouts::app');
    }
}
