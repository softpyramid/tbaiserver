<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Joins a city name to the Sindh district GeoJSON `match` property.
 */
final class DistrictMatch
{
    public static function key(string $name): string
    {
        $flat = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $name));

        return match ($flat) {
            'karachiwest', 'westkarachi' => 'karachi-west',
            'karachieast', 'eastkarachi' => 'karachi-east',
            'karachicentral', 'centralkarachi' => 'karachi-central',
            'karachisouth', 'southkarachi' => 'karachi-south',
            'korangi', 'korangikarachi' => 'korangi',
            'malir', 'malirkarachi' => 'malir',
            'kambarshahdadkot', 'qambarshahdadkot', 'qambershahdadkot' => 'kambar-shahdadkot',
            'mirpurkhas' => 'mirpurkhas',
            'umerkot' => 'umerkot',
            'shaheedbenazirabad', 'nawabshah' => 'shaheed-benazirabad',
            'naushahroferoze', 'naushehroferoze' => 'naushahro-feroze',
            default => Str::slug($name),
        };
    }
}
