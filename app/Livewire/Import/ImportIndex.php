<?php

namespace App\Livewire\Import;

use App\Models\Import;
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

class ImportIndex extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Import::query()->latest('id'))
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('original_filename')
                    ->label(__('import.filename')),
                TextColumn::make('status')
                    ->label(__('import.status'))
                    ->formatStateUsing(
                        function (ImportStatusEnumerator|string $state): string {
                            $value = $state instanceof ImportStatusEnumerator ? $state->value : $state;

                            return ImportTranslator::get('import.statuses.'.$value);
                        }
                    ),
                TextColumn::make('processed_count')
                    ->label(__('import.processed')),
                TextColumn::make('imported_count')
                    ->label(__('import.imported')),
                TextColumn::make('failed_count')
                    ->label(__('import.failed')),
                TextColumn::make('started_at')
                    ->label(__('import.started_at'))
                    ->dateTime('Y-m-d H:i:s'),
                TextColumn::make('finished_at')
                    ->label(__('import.finished_at'))
                    ->dateTime('Y-m-d H:i:s'),
            ])
            ->recordUrl(fn (Import $record): string => route('imports.show', $record));
    }

    public function render(): View
    {
        return view('livewire.import.import-index')
            ->layout('layouts::app');
    }
}
