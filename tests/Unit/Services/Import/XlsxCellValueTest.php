<?php

namespace Tests\Unit\Services\Import;

use App\Services\Import\Xlsx\XlsxCellValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XlsxCellValueTest extends TestCase
{
    #[Test]
    #[DataProvider('nonNegativeIntegerProvider')]
    public function as_non_negative_integer_parses_excel_numeric_values(?string $raw, ?int $expected): void
    {
        $this->assertSame($expected, XlsxCellValue::asNonNegativeInteger($raw));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?int}>
     */
    public static function nonNegativeIntegerProvider(): array
    {
        return [
            'null' => [null, null],
            'empty' => ['', null],
            'integer string' => ['23700', 23700],
            'excel float string' => ['23700.0', 23700],
            'excel float with fraction digits' => ['28500.0000001', 28500],
            'comma decimal' => ['23700,0', 23700],
            'zero' => ['0', 0],
            'negative' => ['-1', null],
            'true fraction' => ['12.5', null],
            'non numeric' => ['abc', null],
        ];
    }
}
