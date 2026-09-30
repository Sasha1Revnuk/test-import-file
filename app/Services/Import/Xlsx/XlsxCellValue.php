<?php

namespace App\Services\Import\Xlsx;

use Carbon\CarbonImmutable;
use Throwable;

final class XlsxCellValue
{
    public static function asString(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function asDateTime(?string $raw): ?string
    {
        $value = self::asString($raw);

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return self::fromExcelSerial((float) $value);
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromExcelSerial(float $serial): string
    {
        $seconds = (int) round(($serial - 25569) * 86400);

        return CarbonImmutable::createFromTimestampUTC($seconds)->format('Y-m-d H:i:s');
    }

    public static function asNonNegativeInteger(?string $raw): ?int
    {
        $value = self::asString($raw);

        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        if (! is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;

        if ($float < 0) {
            return null;
        }

        $int = (int) round($float);

        // Excel often serializes whole numbers as floats ("23700.0").
        // Reject true fractional values such as "12.5".
        if (abs($float - $int) > 0.001) {
            return null;
        }

        return $int;
    }

    public static function columnLetterFromCellReference(string $reference): string
    {
        if (preg_match('/^([A-Z]+)/', strtoupper($reference), $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }
}
