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

    public static function timezone(): string
    {
        return (string) config('app.timezone');
    }
}
