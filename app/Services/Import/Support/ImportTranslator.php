<?php

namespace App\Services\Import\Support;

final class ImportTranslator
{
    /**
     * @param  array<string, \Closure(string): string|bool|float|int|string|null>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $translated = __($key, $replace);

        return is_string($translated) ? $translated : $key;
    }
}
