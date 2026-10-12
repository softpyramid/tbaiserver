<?php

namespace App\Models;

use App\Concerns\GuardsSuspectRuleSet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A clinical rule the score never overrules. A rule that is not configured
 * (a placeholder waiting on the approved national criterion) is never
 * evaluated.
 *
 * @property int $id
 * @property int $rule_set_id
 * @property string $code
 * @property string $label
 * @property array<string, mixed>|null $condition
 * @property string $effect
 * @property string $priority_floor
 * @property string $reason_code
 * @property string $next_action
 * @property string|null $criterion_reference
 * @property bool $configured
 * @property int $sort
 */
#[Fillable(['rule_set_id', 'code', 'label', 'condition', 'effect', 'priority_floor', 'reason_code', 'next_action', 'criterion_reference', 'configured', 'sort'])]
class SuspectClinicalRule extends Model
{
    use GuardsSuspectRuleSet;

    public const EFFECT_REQUIRE_EVALUATION = 'require_evaluation';

    public const EFFECT_CLINICAL_ALERT = 'clinical_alert';

    public const FLOOR_EVALUATION_REQUIRED = 'evaluation_required';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'configured' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function isTestingCriterion(): bool
    {
        return $this->effect === self::EFFECT_REQUIRE_EVALUATION;
    }

    public function isAlert(): bool
    {
        return $this->effect === self::EFFECT_CLINICAL_ALERT;
    }
}
