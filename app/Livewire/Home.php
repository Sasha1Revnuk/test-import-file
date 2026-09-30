<?php

namespace App\Livewire;

use App\Models\Lead;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Contracts\HasSchemas;
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

    public function table(Table $table): Table
    {
        return $table
            ->query(Lead::query())
            ->columns([
                TextColumn::make('external_id'),
                TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s'),
                TextColumn::make('first_name'),
                TextColumn::make('last_name'),
                TextColumn::make('phone'),
                TextColumn::make('email'),
                TextColumn::make('city'),
                TextColumn::make('source'),
                TextColumn::make('utm_campaign'),
                TextColumn::make('product'),
                TextColumn::make('budget_uah')
                    ->numeric(decimalPlaces: 0),
                TextColumn::make('status'),
                TextColumn::make('manager'),
                TextColumn::make('comment')
                    ->wrap(),
                TextColumn::make('next_contact_at')
                    ->dateTime('Y-m-d H:i:s'),
            ]);
    }

    public function render(): View
    {
        return view('livewire.home')
            ->layout('layouts::app');
    }
}
