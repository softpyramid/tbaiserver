<?php

namespace App\Support\Diagnosis;

/**
 * What an engine receives: the test, the stored slide image, and the free
 * text the user typed. reference is the diagnosis uuid, stable for a record.
 */
final readonly class DiagnosisRequest
{
    public function __construct(
        public string $reference,
        public string $testType,
        public string $disk,
        public string $imagePath,
        public ?string $symptoms = null,
        public ?string $notes = null,
    ) {}
}
