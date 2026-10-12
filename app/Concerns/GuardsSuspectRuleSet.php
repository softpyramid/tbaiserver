<?php

namespace App\Concerns;

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectRuleSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Rows of a suspect rule set (questions, score items, clinical rules, risk
 * patterns) cannot change once their set is approved, published or retired.
 *
 * @mixin Model
 */
trait GuardsSuspectRuleSet
{
    protected static function bootGuardsSuspectRuleSet(): void
    {
        $guard = static function (Model $row): void {
            $status = SuspectRuleSet::query()
                ->whereKey($row->getAttribute('rule_set_id'))
                ->value('status');
            $status = $status instanceof SuspectRuleSetStatus ? $status : SuspectRuleSetStatus::tryFrom((string) $status);

            if ($status === SuspectRuleSetStatus::Approved || $status === SuspectRuleSetStatus::Retired) {
                throw new LogicException('Approved, published and retired rule sets cannot be changed.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<SuspectRuleSet, $this>
     */
    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(SuspectRuleSet::class, 'rule_set_id');
    }
}
