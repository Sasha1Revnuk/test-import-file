<?php

namespace App\Services\Import\Contracts;

use App\Models\Import;
use App\Services\Import\Dto\StartImportDto;

interface ImportServiceInterface
{
    public function start(StartImportDto $dto): Import;

    /**
     * @return array<string, string>
     */
    public function previewColumns(string $absolutePath): array;
}
