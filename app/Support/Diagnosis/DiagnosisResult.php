<?php

namespace App\Support\Diagnosis;

/**
 * What an engine returns. simulated is true for any engine that does not
 * really look at the image; the pages then show the demonstration banner.
 */
final readonly class DiagnosisResult
{
    public const POSITIVE = 'positive';

    public const NEGATIVE = 'negative';

    public const INDETERMINATE = 'indeterminate';

    public const RESULTS = [self::POSITIVE, self::NEGATIVE, self::INDETERMINATE];

    /**
     * @param  list<DiagnosisFinding>  $findings
     */
    public function __construct(
        public string $result,
        public ?string $grade,
        public array $findings,
        public string $engine,
        public string $engineVersion,
        public bool $simulated,
    ) {}

    /**
     * @return list<array{x: float, y: float, w: float, h: float, label: string, confidence: float}>
     */
    public function findingsArray(): array
    {
        return array_map(fn (DiagnosisFinding $finding): array => $finding->toArray(), $this->findings);
    }
}
