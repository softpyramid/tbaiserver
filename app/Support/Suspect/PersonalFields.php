<?php

namespace App\Support\Suspect;

use App\Http\Requests\StoreScreeningRequest;

/**
 * Fields that must never reach a suspect screening: the flat screening's
 * list plus patient names and record numbers.
 */
class PersonalFields
{
    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [...StoreScreeningRequest::PERSONAL, 'patient_name', 'mr_number'];
    }

    /**
     * Field paths in this input that carry a personal key, at the top level
     * or inside answers.
     *
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    public static function found(array $input): array
    {
        $keys = self::keys();
        $found = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $found[] = $key;
            }
        }

        $answers = $input['answers'] ?? null;

        if (is_array($answers)) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $answers)) {
                    $found[] = 'answers.'.$key;
                }
            }
        }

        return $found;
    }
}
