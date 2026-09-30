<?php

namespace App\Filament\Actions;

use App\Services\Import\Contracts\ImportServiceInterface;
use App\Services\Import\Dto\ColumnMappingDto;
use App\Services\Import\Dto\StartImportDto;
use App\Services\Import\Enumerators\LeadFieldEnumerator;
use App\Services\Import\Support\ImportTranslator;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class StartLeadImportAction
{
    public static function make(): Action
    {
        return Action::make('createImport')
            ->label(ImportTranslator::get('import.create_title'))
            ->modalHeading(ImportTranslator::get('import.create_title'))
            ->modalSubmitActionLabel(ImportTranslator::get('import.start'))
            ->modalWidth('3xl')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes([
                'class' => 'fi-lead-import-modal',
            ], merge: true)
            ->icon(Heroicon::ArrowUpTray)
            ->button()
            ->color('primary')
            ->schema(fn (): array => self::schema())
            ->action(function (array $data, Component $livewire): void {
                self::run($data, $livewire);
            });
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function schema(): array
    {
        $fieldRows = [
            Grid::make(12)
                ->schema([
                    Placeholder::make('mapping_header_toggle')
                        ->hiddenLabel()
                        ->content('')
                        ->columnSpan(1),
                    Placeholder::make('mapping_header_field')
                        ->hiddenLabel()
                        ->content(ImportTranslator::get('import.field'))
                        ->extraAttributes(['class' => 'text-xs font-medium uppercase tracking-wide text-gray-500'])
                        ->columnSpan(7),
                    Placeholder::make('mapping_header_column')
                        ->hiddenLabel()
                        ->content(ImportTranslator::get('import.column_letter'))
                        ->extraAttributes(['class' => 'text-xs font-medium uppercase tracking-wide text-gray-500'])
                        ->columnSpan(4),
                ]),
        ];

        foreach (LeadFieldEnumerator::importable() as $index => $field) {
            $fieldKey = $field->value;
            $isRequired = $field->isRequired();
            $defaultLetter = self::columnLetter($index);

            $fieldRows[] = Grid::make(12)
                ->schema([
                    Toggle::make('selected.'.$fieldKey)
                        ->hiddenLabel()
                        ->default(true)
                        ->disabled($isRequired)
                        ->dehydrated()
                        ->live()
                        ->extraFieldWrapperAttributes([
                            'class' => 'fi-lead-import-toggle',
                        ])
                        ->columnSpan(1),
                    Placeholder::make('label.'.$fieldKey)
                        ->hiddenLabel()
                        ->content(ImportTranslator::get('import.fields.'.$fieldKey))
                        ->extraAttributes([
                            'class' => 'fi-lead-import-field-label',
                        ])
                        ->columnSpan(7),
                    TextInput::make('columns.'.$fieldKey)
                        ->hiddenLabel()
                        ->maxLength(3)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->default($defaultLetter)
                        ->visible(fn (Get $get): bool => $isRequired || (bool) $get('selected.'.$fieldKey))
                        ->required(fn (Get $get): bool => $isRequired || (bool) $get('selected.'.$fieldKey))
                        ->columnSpan(4),
                ])
                ->extraAttributes([
                    'class' => 'fi-lead-import-mapping-row',
                ]);
        }

        return [
            FileUpload::make('file')
                ->label(ImportTranslator::get('import.upload_label'))
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'application/octet-stream',
                ])
                ->rules(['file', 'mimes:xlsx,xls'])
                ->storeFiles(false)
                ->visibility('private')
                ->required(),
            Checkbox::make('has_header_row')
                ->label(ImportTranslator::get('import.has_header_row'))
                ->default(true),
            Section::make(ImportTranslator::get('import.column_mapping'))
                ->schema($fieldRows)
                ->compact()
                ->extraAttributes([
                    'class' => 'fi-lead-import-mapping',
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function run(array $data, Component $livewire): void
    {
        $file = self::resolveUploadedFile($data['file'] ?? null);

        if ($file === null) {
            Notification::make()
                ->title(ImportTranslator::get('import.validation.file_required'))
                ->danger()
                ->send();

            return;
        }

        $selected = is_array($data['selected'] ?? null) ? $data['selected'] : [];
        $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
        $mapping = [];

        foreach (LeadFieldEnumerator::importable() as $field) {
            $isSelected = $field->isRequired() || (bool) ($selected[$field->value] ?? false);

            if (! $isSelected) {
                continue;
            }

            $letter = strtoupper(trim((string) ($columns[$field->value] ?? '')));

            if ($letter === '') {
                Notification::make()
                    ->title(ImportTranslator::get('import.validation.column_required'))
                    ->danger()
                    ->send();

                return;
            }

            $mapping[$field->value] = $letter;
        }

        if (! array_key_exists(LeadFieldEnumerator::ExternalId->value, $mapping)) {
            Notification::make()
                ->title(ImportTranslator::get('import.validation.external_id_required'))
                ->danger()
                ->send();

            return;
        }

        $storedPath = $file->getRealPath() ?: $file->getPathname();

        if ($storedPath === '') {
            Notification::make()
                ->title(ImportTranslator::get('import.validation.file_required'))
                ->danger()
                ->send();

            return;
        }

        try {
            $import = app(ImportServiceInterface::class)->start(new StartImportDto(
                absolutePath: $storedPath,
                originalFilename: $file->getClientOriginalName(),
                columnMapping: ColumnMappingDto::fromArray($mapping),
                hasHeaderRow: (bool) ($data['has_header_row'] ?? true),
            ));
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        if (method_exists($livewire, 'showImportResult')) {
            $livewire->showImportResult($import);
        }

        if (method_exists($livewire, 'resetTable')) {
            $livewire->resetTable();
        }
    }

    private static function resolveUploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (is_array($state)) {
            $first = Arr::first($state);

            return $first instanceof TemporaryUploadedFile ? $first : null;
        }

        return null;
    }

    private static function columnLetter(int $index): string
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
