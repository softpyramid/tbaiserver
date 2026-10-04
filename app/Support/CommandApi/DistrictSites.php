<?php

namespace App\Support\CommandApi;

use App\Support\DistrictMatch;

/**
 * Map seat points and chart labels, as the prototype draws them.
 */
final class DistrictSites
{
    /**
     * District name to [longitude, latitude].
     *
     * @var array<string, array{0: float|int, 1: float|int}>
     */
    private const SEATS = [
        'jacobabad' => [68.44, 28.28], 'kashmore' => [69.58, 28.43], 'shikarpur' => [68.64, 27.96],
        'ghotki' => [69.32, 28], 'sukkur' => [68.86, 27.7], 'larkana' => [68.21, 27.56],
        'kambar-shahdadkot' => [67.99, 27.59], 'khairpur' => [68.76, 27.53], 'naushahro-feroze' => [68.12, 26.84],
        'dadu' => [67.78, 26.73], 'shaheed-benazirabad' => [68.41, 26.25], 'sanghar' => [68.95, 26.05],
        'jamshoro' => [68.28, 25.5], 'matiari' => [68.45, 25.65], 'hyderabad' => [68.37, 25.3],
        'tando-allahyar' => [68.72, 25.46], 'tando-muhammad-khan' => [68.54, 25.05], 'mirpurkhas' => [69.01, 25.53],
        'umerkot' => [69.74, 25.36], 'tharparkar' => [69.8, 24.7], 'badin' => [68.84, 24.66],
        'sujawal' => [68.07, 24.6], 'thatta' => [67.92, 24.95], 'malir' => [67.35, 25.1],
        'karachi-east' => [67.12, 24.93], 'karachi-central' => [67.03, 24.97], 'karachi-south' => [67.02, 24.86],
        'karachi-west' => [66.9, 25], 'korangi' => [67.15, 24.8],
    ];

    /** The prototype's hand drawn Sindh outline, [longitude, latitude]. */
    public const OUTLINE = [
        [67.85, 28.5], [68.6, 28.6], [69.3, 28.75], [70, 28.3], [70.2, 27.6], [70.6, 26.7], [70.2, 26],
        [70.6, 25.3], [71.1, 24.5], [70, 24.2], [69.5, 23.8], [68.8, 23.7], [68.1, 23.8], [67.45, 24.1],
        [67, 24.75], [66.7, 24.95], [66.85, 25.6], [67.15, 26.3], [67.1, 27], [67.5, 27.6],
    ];

    /**
     * @return array{0: float, 1: float}
     */
    public static function seat(string $name, float $latitude, float $longitude): array
    {
        $seat = self::SEATS[DistrictMatch::key($name)] ?? [round($longitude, 2), round($latitude, 2)];

        return [(float) $seat[0], (float) $seat[1]];
    }

    /** The prototype's shortN() for chart labels. */
    public static function shortName(string $name): string
    {
        return str_replace(
            ['Shaheed Benazirabad', 'Muhammad', 'Naushahro Feroze', 'Kambar Shahdadkot'],
            ['S. Benazirabad', 'M.', 'N. Feroze', 'Kambar-Shahdadkot'],
            $name,
        );
    }

    /** "BMU 3" for command center sites, the team name for anything else. */
    public static function facilityLabel(?string $code, string $name): string
    {
        if ($code !== null && preg_match('/^cc-[a-z]+-.+-(\d+)$/', $code, $match) === 1) {
            return 'BMU '.$match[1];
        }

        return $name;
    }
}
