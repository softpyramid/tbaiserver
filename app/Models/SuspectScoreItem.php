<?php

namespace App\Models;

use App\Concerns\GuardsSuspectRuleSet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One weighted item of the provisional prioritisation score.
 * Component S is symptoms, E exposure, H history.
 *
 * @property int $id
 * @property int $rule_set_id
 * @property string $component
 * @property string $key
 * @property string $label
 * @property array<string, mixed> $condition
 * @property int $points
 * @property int $sort
 */
#[Fillable(['rule_set_id', 'component', 'key', 'label', 'condition', 'points', 'sort'])]
class SuspectScoreItem extends Model
{
    use GuardsSuspectRuleSet;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'points' => 'integer',
            'sort' => 'integer',
        ];
    }
}
