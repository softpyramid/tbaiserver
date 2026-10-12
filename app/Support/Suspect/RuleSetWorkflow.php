<?php

namespace App\Support\Suspect;

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectRuleSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Draft, submit, approve, publish, retire. Only a draft is edited; any
 * change to an approved set starts from a clone. Publishing retires the
 * previously published set, so only one is ever published.
 */
class RuleSetWorkflow
{
    /**
     * Next draft version for this month, for example 2026.10-draft.2.
     */
    public function nextVersion(): string
    {
        $prefix = now((string) config('app.timezone'))->format('Y.m').'-draft.';
        $highest = 0;

        foreach (SuspectRuleSet::query()->where('version', 'like', $prefix.'%')->pluck('version') as $version) {
            $suffix = substr((string) $version, strlen($prefix));

            if (ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $prefix.($highest + 1);
    }

    /**
     * Copy every row into a new draft.
     */
    public function cloneToDraft(SuspectRuleSet $source, User $actor): SuspectRuleSet
    {
        return DB::transaction(function () use ($source, $actor): SuspectRuleSet {
            $draft = SuspectRuleSet::query()->create([
                'version' => $this->nextVersion(),
                'status' => SuspectRuleSetStatus::Draft,
                'engine_version' => ScoringEngine::ENGINE_VERSION,
                'high_threshold' => $source->high_threshold,
                'medium_threshold' => $source->medium_threshold,
                'low_threshold' => $source->low_threshold,
                'refer_threshold' => $source->refer_threshold,
                'cloned_from_id' => $source->id,
                'created_by' => $actor->id,
            ]);

            foreach ($source->questions as $question) {
                $draft->questions()->create($question->only(['key', 'section', 'prompt', 'help', 'options', 'ask_when', 'sort']));
            }

            foreach ($source->scoreItems as $item) {
                $draft->scoreItems()->create($item->only(['component', 'key', 'label', 'condition', 'points', 'sort']));
            }

            foreach ($source->clinicalRules as $rule) {
                $draft->clinicalRules()->create($rule->only(['code', 'label', 'condition', 'effect', 'priority_floor', 'reason_code', 'next_action', 'criterion_reference', 'configured', 'sort']));
            }

            foreach ($source->riskPatterns as $pattern) {
                $draft->riskPatterns()->create($pattern->only(['code', 'reason', 'condition', 'active']));
            }

            return $draft;
        });
    }

    public function submit(SuspectRuleSet $ruleSet): void
    {
        $ruleSet->forceFill([
            'status' => SuspectRuleSetStatus::PendingApproval,
            'submitted_at' => now(),
        ])->save();
    }

    public function approve(SuspectRuleSet $ruleSet, User $approver, string $reference, ?string $note): void
    {
        $ruleSet->forceFill([
            'status' => SuspectRuleSetStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'clinical_signoff_reference' => $reference,
            'approval_note' => $note,
        ])->save();
    }

    /**
     * Publish this approved set and retire the one published before it.
     *
     * @return list<string> Versions that were retired.
     */
    public function publish(SuspectRuleSet $ruleSet): array
    {
        return DB::transaction(function () use ($ruleSet): array {
            $previous = SuspectRuleSet::query()
                ->where('status', SuspectRuleSetStatus::Approved)
                ->whereNotNull('published_at')
                ->whereKeyNot($ruleSet->id)
                ->lockForUpdate()
                ->get();

            foreach ($previous as $old) {
                $old->forceFill(['status' => SuspectRuleSetStatus::Retired])->save();
            }

            $ruleSet->forceFill(['published_at' => now()])->save();

            return array_values($previous->map(fn (SuspectRuleSet $old): string => $old->version)->all());
        });
    }

    public function retire(SuspectRuleSet $ruleSet): void
    {
        $ruleSet->forceFill(['status' => SuspectRuleSetStatus::Retired])->save();
    }
}
