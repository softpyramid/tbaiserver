<?php

namespace App\Support\Patients;

/**
 * CNIC and phone formats for patient records. A CNIC is 13 digits, typed
 * with or without dashes (12345-1234567-1) and stored as digits. A phone is
 * a Pakistani mobile number, typed as 03XXXXXXXXX or +923XXXXXXXXX and
 * stored as +923XXXXXXXXX.
 */
class PatientIdentifiers
{
    public const CNIC_PATTERN = '/^(\d{5}-\d{7}-\d|\d{13})$/';

    public const PHONE_PATTERN = '/^(03\d{9}|\+923\d{9})$/';

    public static function isCnic(string $value): bool
    {
        return preg_match(self::CNIC_PATTERN, trim($value)) === 1;
    }

    /**
     * Digits of a valid CNIC, or null when the value is not one.
     */
    public static function cnicDigits(?string $value): ?string
    {
        if ($value === null || ! self::isCnic($value)) {
            return null;
        }

        return str_replace('-', '', trim($value));
    }

    public static function formatCnic(?string $digits): ?string
    {
        if ($digits === null || strlen($digits) !== 13) {
            return $digits;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
    }

    /**
     * Keyed hash of the CNIC digits, for exact search over the encrypted
     * column. Rotating APP_KEY means re-hashing every row.
     */
    public static function cnicHash(string $digits): string
    {
        return hash_hmac('sha256', $digits, (string) config('app.key'));
    }

    /**
     * The phone with spaces and dashes removed, as validated.
     */
    public static function compactPhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $compact = preg_replace('/[\s-]+/', '', $value);

        return is_string($compact) ? $compact : null;
    }

    public static function isPhone(string $value): bool
    {
        return preg_match(self::PHONE_PATTERN, (string) self::compactPhone($value)) === 1;
    }

    /**
     * +923XXXXXXXXX for a valid number, or null.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $compact = self::compactPhone($value);

        if ($compact === null || preg_match(self::PHONE_PATTERN, $compact) !== 1) {
            return null;
        }

        return str_starts_with($compact, '+92') ? $compact : '+92'.substr($compact, 1);
    }
}
