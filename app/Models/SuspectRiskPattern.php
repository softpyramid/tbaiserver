<?php

namespace App\Models;

use App\Concerns\GuardsSuspectRuleSet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A risk pattern. Inactive until clinically approved; inactive patterns are
 * never evaluated or shown.
 *
 * @property int $id
 * @property int $rule_set_id
 * @property string $code
 * @property string $reason
 * @property array<string, mixed> $condition
 * @property bool $active
 */
#[Fillable(['rule_set_id', 'code', 'reason', 'condition', 'active'])]
class SuspectRiskPattern extends Model
{
    use GuardsSuspectRuleSet;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'active' => 'boolean',
        ];
    }
}
