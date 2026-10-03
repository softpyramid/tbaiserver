<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class EntryDate
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function label(?CarbonInterface $date = null): string
    {
        $day = $date === null
            ? self::now()
            : CarbonImmutable::parse($date->toDateTimeString(), self::timezone());

        return $day->timezone(self::timezone())->format('j M Y');
    }

    public static function time(CarbonInterface $date): string
    {
        return $date->copy()->timezone(self::timezone())->format('H:i');
    }

    public static function day(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Calendar date for people reading a table, for example "4 Oct 2026".
     */
    public static function display(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return self::label($value);
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        return self::label(CarbonImmutable::parse($value, self::timezone()));
    }

    public static function timezone(): string
    {
        return (string) config('app.timezone');
    }
}
