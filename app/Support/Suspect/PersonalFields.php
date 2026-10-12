<?php

namespace App\Support\Suspect;

use App\Http\Requests\StoreScreeningRequest;

/**
 * Fields that must never reach a suspect screening: the flat screening's
 * list plus MR numbers. A named screening carries the patient's name and
 * register number, but only at the top level of the start request; inside
 * answers, on any other endpoint and in a query string they are refused too.
 */
class PersonalFields
{
    /**
     * Accepted only at the top level of the start request body.
     *
     * @var list<string>
     */
    public const NAMED = ['patient_name', 'register_number'];

    /**
     * Never accepted anywhere.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [...StoreScreeningRequest::PERSONAL, 'mr_number'];
    }

    /**
     * Field paths in this input that carry a personal key, at the top level
     * or inside answers. The named fields are allowed at the top level only
     * when $namedAllowed is true (the start request body).
     *
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    public static function found(array $input, bool $namedAllowed = false): array
    {
        $topLevel = $namedAllowed ? self::keys() : [...self::keys(), ...self::NAMED];
        $found = [];

        foreach ($topLevel as $key) {
            if (array_key_exists($key, $input)) {
                $found[] = $key;
            }
        }

        $answers = $input['answers'] ?? null;

        if (is_array($answers)) {
            foreach ([...self::keys(), ...self::NAMED] as $key) {
                if (array_key_exists($key, $answers)) {
                    $found[] = 'answers.'.$key;
                }
            }
        }

        return $found;
    }
}
