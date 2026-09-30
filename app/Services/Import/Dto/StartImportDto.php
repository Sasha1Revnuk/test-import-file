<?php

namespace App\Services\Import\Dto;

readonly class StartImportDto
{
    public function __construct(
        public string $absolutePath,
        public string $originalFilename,
        public ColumnMappingDto $columnMapping,
        public bool $hasHeaderRow = true,
    ) {
    }
}
