<?php

namespace App\Support\Suspect;

use App\Models\SuspectRuleSet;

/**
 * Priority bands from the provisional thresholds, then the clinical floor:
 * a met clinical rule lifts low or medium to evaluation_required. High stays
 * high (and is still referred). A met rule is never low.
 */
class Priority
{
    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const LOW = 'low';

    public const EVALUATION_REQUIRED = 'evaluation_required';

    public static function resolve(int $score, SuspectRuleSet $ruleSet, bool $clinicalFloor): string
    {
        $band = match (true) {
            $score >= $ruleSet->high_threshold => self::HIGH,
            $score >= $ruleSet->medium_threshold => self::MEDIUM,
            default => self::LOW,
        };

        if ($clinicalFloor && $band !== self::HIGH) {
            return self::EVALUATION_REQUIRED;
        }

        return $band;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::HIGH, self::MEDIUM, self::LOW, self::EVALUATION_REQUIRED];
    }
}
