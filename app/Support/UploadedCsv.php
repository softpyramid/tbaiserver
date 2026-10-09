<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Reads an uploaded CSV into lowercase-headed associative rows. Lines whose
 * column count differs from the header are dropped, as are empty files, so
 * importers decide how strictly to treat malformed input.
 */
final class UploadedCsv
{
    /**
     * @return list<array<string, string>>
     */
    public static function read(?UploadedFile $file): array
    {
        if ($file === null) {
            return [];
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            return [];
        }

        $headers = array_map('mb_strtolower', $headers);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) !== count($headers)) {
                continue;
            }

            $rows[] = array_combine($headers, array_map('trim', $line)) ?: [];
        }

        fclose($handle);

        return $rows;
    }
}
