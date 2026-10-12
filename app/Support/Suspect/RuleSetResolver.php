<?php

namespace App\Support\Suspect;

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectRuleSet;
use App\Support\ResourceResponse;

/**
 * Release gate. The current rule set is the published approved one.
 * Outside production, with none published, the newest draft is used and
 * every screen says the weights are pending clinical sign-off. Production
 * never serves a set without a recorded clinical approval.
 */
class RuleSetResolver
{
    public function current(): ?SuspectRuleSet
    {
        $published = SuspectRuleSet::query()
            ->where('status', SuspectRuleSetStatus::Approved)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->latest('id')
            ->first();

        if ($published !== null) {
            return $this->servable($published) ? $published : null;
        }

        if (app()->isProduction()) {
            return null;
        }

        return SuspectRuleSet::query()
            ->where('status', SuspectRuleSetStatus::Draft)
            ->latest('id')
            ->first();
    }

    /**
     * Whether this set may score anything in this environment. An in-progress
     * screening keeps its own set, so this is checked again on every call.
     */
    public function servable(SuspectRuleSet $ruleSet): bool
    {
        if (! app()->isProduction()) {
            return true;
        }

        return $ruleSet->hasClinicalApproval()
            && ($ruleSet->status === SuspectRuleSetStatus::Approved || $ruleSet->status === SuspectRuleSetStatus::Retired);
    }

    public static function unavailableMessage(): string
    {
        return ResourceResponse::line('entry.suspect_unavailable');
    }
}
