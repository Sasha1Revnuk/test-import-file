<?php

namespace App\Services\Import\Xlsx;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

final class XlsxRowReader
{
    /**
     * @return array<string, string>
     */
    public function previewFirstRow(string $absolutePath): array
    {
        foreach ($this->rows($absolutePath) as $row) {
            /** @var array{row_number: int, cells: array<string, string>} $row */
            return $row['cells'];
        }

        return [];
    }

    /**
     * @return Generator<int, array{row_number: int, cells: array<string, string>}>
     */
    public function rows(string $absolutePath): Generator
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("XLSX file not found: {$absolutePath}");
        }

        $sharedStrings = $this->loadSharedStrings($absolutePath);
        $extractedSheet = $this->extractSheetToTempFile($absolutePath);

        $reader = new XMLReader();

        if (! $reader->open($extractedSheet)) {
            @unlink($extractedSheet);

            throw new RuntimeException('Unable to open worksheet sheet1.xml from XLSX archive.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $rowNumberAttr = $reader->getAttribute('r');
                $rowNumber = $rowNumberAttr !== null && $rowNumberAttr !== ''
                    ? (int) $rowNumberAttr
                    : 0;

                $depth = $reader->depth;
                $cells = [];

                if ($reader->isEmptyElement) {
                    yield [
                        'row_number' => $rowNumber,
                        'cells' => $cells,
                    ];

                    continue;
                }

                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::END_ELEMENT
                        && $reader->localName === 'row'
                        && $reader->depth === $depth
                    ) {
                        break;
                    }

                    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                        continue;
                    }

                    $reference = $reader->getAttribute('r') ?? '';
                    $letter = XlsxCellValue::columnLetterFromCellReference($reference);

                    if ($letter === '') {
                        continue;
                    }

                    $type = $reader->getAttribute('t') ?? '';
                    $value = $this->readCellValue($reader, $type, $sharedStrings);

                    if ($value !== null) {
                        $cells[$letter] = $value;
                    }
                }

                yield [
                    'row_number' => $rowNumber,
                    'cells' => $cells,
                ];
            }
        } finally {
            $reader->close();
            @unlink($extractedSheet);
        }
    }

    private function extractSheetToTempFile(string $absolutePath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Unable to open XLSX archive: {$absolutePath}");
        }

        try {
            $stream = $zip->getStream('xl/worksheets/sheet1.xml');

            if ($stream === false) {
                throw new RuntimeException('Worksheet sheet1.xml is missing from XLSX archive.');
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_sheet_');

            if ($tempFile === false) {
                fclose($stream);

                throw new RuntimeException('Unable to create temporary sheet file.');
            }

            $destination = fopen($tempFile, 'wb');

            if ($destination === false) {
                fclose($stream);
                @unlink($tempFile);

                throw new RuntimeException('Unable to open temporary sheet file for writing.');
            }

            stream_copy_to_stream($stream, $destination);
            fclose($stream);
            fclose($destination);

            return $tempFile;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function loadSharedStrings(string $absolutePath): array
    {
        if (! $this->zipHasEntry($absolutePath, 'xl/sharedStrings.xml')) {
            return [];
        }

        $uri = 'zip://'.$absolutePath.'#xl/sharedStrings.xml';
        $reader = new XMLReader();

        if (! $reader->open($uri)) {
            return [];
        }

        $strings = [];

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }

                $strings[] = $this->readSharedStringItem($reader);
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    private function zipHasEntry(string $absolutePath, string $entry): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Unable to open XLSX archive: {$absolutePath}");
        }

        try {
            return $zip->locateName($entry) !== false;
        } finally {
            $zip->close();
        }
    }

    private function readSharedStringItem(XMLReader $reader): string
    {
        if ($reader->isEmptyElement) {
            return '';
        }

        $depth = $reader->depth;
        $parts = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT
                && $reader->localName === 'si'
                && $reader->depth === $depth
            ) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                $parts[] = $reader->readString();
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function readCellValue(XMLReader $reader, string $type, array $sharedStrings): ?string
    {
        if ($reader->isEmptyElement) {
            return null;
        }

        $depth = $reader->depth;
        $inlineParts = [];
        $rawValue = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT
                && $reader->localName === 'c'
                && $reader->depth === $depth
            ) {
                break;
            }

            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->localName === 'v') {
                $rawValue = $reader->readString();
            } elseif ($reader->localName === 't') {
                $inlineParts[] = $reader->readString();
            }
        }

        if ($type === 'inlineStr') {
            $joined = implode('', $inlineParts);

            return $joined === '' ? null : $joined;
        }

        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if ($type === 's') {
            $index = (int) $rawValue;

            return $sharedStrings[$index] ?? null;
        }

        if ($type === 'b') {
            return $rawValue === '1' ? '1' : '0';
        }

        return $rawValue;
    }
}
