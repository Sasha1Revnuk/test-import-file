<?php

namespace App\Models;

use App\Services\Import\Enumerators\ImportStatusEnumerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $original_filename
 * @property string $stored_path
 * @property array<string, string> $column_mapping
 * @property bool $has_header_row
 * @property ImportStatusEnumerator $status
 * @property int $processed_count
 * @property int $imported_count
 * @property int $failed_count
 * @property string|null $fatal_error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class Import extends Model
{
    /** @use HasFactory<\Database\Factories\ImportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'original_filename',
        'stored_path',
        'column_mapping',
        'has_header_row',
        'status',
        'processed_count',
        'imported_count',
        'failed_count',
        'fatal_error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'has_header_row' => 'boolean',
            'status' => ImportStatusEnumerator::class,
            'processed_count' => 'integer',
            'imported_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportFailure, $this>
     */
    public function failures(): HasMany
    {
        return $this->hasMany(ImportFailure::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
