<?php

namespace Tests\Support;

use ZipArchive;

final class XlsxFixtureBuilder
{
    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function create(string $absolutePath, array $rows): void
    {
        self::createFromGenerator($absolutePath, (static function () use ($rows): \Generator {
            foreach ($rows as $row) {
                yield $row;
            }
        })());
    }

    /**
     * @param  \Generator<int, list<string|int|float|null>>  $rows
     */
    public static function createFromGenerator(string $absolutePath, \Generator $rows): void
    {
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }

        $tempDir = sys_get_temp_dir().'/xlsx_fixture_'.uniqid('', true);
        mkdir($tempDir.'/xl/worksheets', 0755, true);
        mkdir($tempDir.'/xl/_rels', 0755, true);
        mkdir($tempDir.'/_rels', 0755, true);

        $shared = [];
        $sharedIndex = [];
        $sheetPath = $tempDir.'/xl/worksheets/sheet1.xml';
        $sheetHandle = fopen($sheetPath, 'wb');

        if ($sheetHandle === false) {
            throw new \RuntimeException('Unable to write sheet XML.');
        }

        fwrite(
            $sheetHandle,
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'
        );

        $excelRow = 0;

        foreach ($rows as $cells) {
            $excelRow++;
            $cellXml = '';

            foreach ($cells as $columnIndex => $value) {
                $letter = self::columnLetter($columnIndex);
                $reference = $letter.$excelRow;

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_int($value) || is_float($value)) {
                    $cellXml .= '<c r="'.$reference.'"><v>'.$value.'</v></c>';

                    continue;
                }

                $string = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= '<c r="'.$reference.'" t="inlineStr"><is><t>'.$string.'</t></is></c>';
            }

            fwrite($sheetHandle, '<row r="'.$excelRow.'">'.$cellXml.'</row>');
        }

        fwrite($sheetHandle, '</sheetData></worksheet>');
        fclose($sheetHandle);

        file_put_contents(
            $tempDir.'/xl/sharedStrings.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"></sst>'
        );

        file_put_contents(
            $tempDir.'/xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>'
        );

        file_put_contents(
            $tempDir.'/xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>'
        );

        file_put_contents(
            $tempDir.'/_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>'
        );

        file_put_contents(
            $tempDir.'/[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>'
        );

        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Unable to create XLSX fixture.');
        }

        $zip->addFile($tempDir.'/[Content_Types].xml', '[Content_Types].xml');
        $zip->addFile($tempDir.'/_rels/.rels', '_rels/.rels');
        $zip->addFile($tempDir.'/xl/workbook.xml', 'xl/workbook.xml');
        $zip->addFile($tempDir.'/xl/_rels/workbook.xml.rels', 'xl/_rels/workbook.xml.rels');
        $zip->addFile($tempDir.'/xl/worksheets/sheet1.xml', 'xl/worksheets/sheet1.xml');
        $zip->addFile($tempDir.'/xl/sharedStrings.xml', 'xl/sharedStrings.xml');
        $zip->close();

        self::deleteDirectory($tempDir);
    }

    private static function deleteDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
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
