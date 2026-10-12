<?php

namespace App\Http\Controllers;

use App\Enums\SuspectRuleSetStatus;
use App\Models\SuspectClinicalRule;
use App\Models\SuspectQuestion;
use App\Models\SuspectRiskPattern;
use App\Models\SuspectRuleSet;
use App\Models\SuspectScoreItem;
use App\Models\User;
use App\Support\AdminTabs;
use App\Support\AuditLogger;
use App\Support\EntryDate;
use App\Support\ResourceResponse;
use App\Support\Suspect\Condition;
use App\Support\Suspect\RuleSetWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TB-AI admin pages for suspect screening rule sets. Weights, thresholds,
 * the testing criterion and risk patterns are edited only in a draft.
 * Approval records the named clinical reviewer's sign-off reference.
 */
class SuspectRuleSetAdminController extends Controller
{
    public function __construct(
        private readonly RuleSetWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        $query = SuspectRuleSet::query()
            ->with(['approver', 'creator'])
            ->orderByDesc('id');

        $columns = [
            ['key' => 'version', 'label' => __('entry.suspect_column_version')],
            ['key' => 'status', 'label' => __('entry.column_status')],
            ['key' => 'published', 'label' => __('entry.suspect_column_published')],
            ['key' => 'approved_by', 'label' => __('entry.suspect_column_approved_by')],
            ['key' => 'signoff', 'label' => __('entry.suspect_column_signoff')],
            ['key' => 'created', 'label' => __('entry.suspect_column_created')],
        ];
        $cells = fn (SuspectRuleSet $ruleSet): array => [
            'version' => $ruleSet->version,
            'status' => $ruleSet->status->label(),
            'published' => $ruleSet->isPublished() && $ruleSet->published_at !== null
                ? EntryDate::dateTime($ruleSet->published_at)
                : '',
            'approved_by' => $ruleSet->approver === null ? '' : $ruleSet->approver->name,
            'signoff' => $ruleSet->clinical_signoff_reference ?? '',
            'created' => $ruleSet->created_at === null ? '' : EntryDate::display($ruleSet->created_at),
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'suspect-rule-sets.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (SuspectRuleSet $ruleSet): array => array_values($cells($ruleSet))),
            );
        }

        $page = $query->paginate(20)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_suspect_rules'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (SuspectRuleSet $ruleSet): array => [
                'id' => (string) $ruleSet->id,
                'href' => route('admin.suspect-rules.show', $ruleSet),
                'cells' => $cells($ruleSet),
                'controls' => [[
                    'kind' => 'link',
                    'url' => route('admin.suspect-rules.show', $ruleSet),
                    'label' => __('entry.suspect_open_rule_set'),
                ]],
            ])->values()->all(),
            filters: [],
            sort: 'version',
            direction: 'desc',
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'title' => __('entry.nav_suspect_rules'),
                'description' => __('entry.suspect_rules_description'),
                'tabs' => AdminTabs::suspect('rules'),
                'empty' => [
                    'title' => __('entry.nav_suspect_rules'),
                    'body' => __('entry.suspect_rules_empty'),
                ],
            ],
        );
    }

    public function show(SuspectRuleSet $suspectRuleSet): Response
    {
        Gate::authorize('administer');

        $ruleSet = $suspectRuleSet->load(['questions', 'scoreItems', 'clinicalRules', 'riskPatterns', 'approver', 'creator']);
        $source = $ruleSet->cloned_from_id === null ? null : SuspectRuleSet::query()->find($ruleSet->cloned_from_id);
        $editable = $ruleSet->isDraft();
        $criterionOptions = $ruleSet->scoreItems
            ->map(fn (SuspectScoreItem $item): array => ['value' => $item->key, 'label' => $item->label])
            ->values()->all();

        return Inertia::render('suspect/rules-show', [
            'tabs' => AdminTabs::suspect('rules'),
            'ruleSet' => [
                'id' => $ruleSet->id,
                'version' => $ruleSet->version,
                'status' => $ruleSet->status->value,
                'statusLabel' => $ruleSet->status->label(),
                'engineVersion' => $ruleSet->engine_version,
                'published' => $ruleSet->isPublished(),
                'publishedAt' => $ruleSet->published_at === null ? null : EntryDate::dateTime($ruleSet->published_at),
                'clonedFrom' => $source?->version,
                'createdBy' => $ruleSet->creator?->name,
                'submittedAt' => $ruleSet->submitted_at === null ? null : EntryDate::dateTime($ruleSet->submitted_at),
                'approvedBy' => $ruleSet->approver?->name,
                'approvedAt' => $ruleSet->approved_at === null ? null : EntryDate::dateTime($ruleSet->approved_at),
                'signoffReference' => $ruleSet->clinical_signoff_reference,
                'approvalNote' => $ruleSet->approval_note,
                'editable' => $editable,
                'thresholds' => [
                    'high' => $ruleSet->high_threshold,
                    'medium' => $ruleSet->medium_threshold,
                    'low' => $ruleSet->low_threshold,
                    'refer' => $ruleSet->refer_threshold,
                ],
            ],
            'questions' => $ruleSet->questions->map(fn (SuspectQuestion $question): array => [
                'id' => $question->id,
                'key' => $question->key,
                'section' => ResourceResponse::line('entry.suspect_section_'.$question->section),
                'prompt' => $question->prompt,
                'options' => implode(', ', $question->options),
                'askWhen' => Condition::describe($question->ask_when),
            ])->values()->all(),
            'scoreItems' => $ruleSet->scoreItems->map(fn (SuspectScoreItem $item): array => [
                'id' => $item->id,
                'component' => $item->component,
                'key' => $item->key,
                'label' => $item->label,
                'condition' => Condition::describe($item->condition),
                'points' => $item->points,
            ])->values()->all(),
            'clinicalRules' => $ruleSet->clinicalRules->map(fn (SuspectClinicalRule $rule): array => [
                'id' => $rule->id,
                'code' => $rule->code,
                'label' => $rule->label,
                'effect' => $rule->effect,
                'condition' => $rule->condition === null ? null : Condition::describe($rule->condition),
                'conditionItem' => $this->matchingItem($ruleSet, $rule),
                'nextAction' => $rule->next_action,
                'reference' => $rule->criterion_reference,
                'configured' => $rule->configured,
                'editable' => $editable && $rule->isTestingCriterion(),
            ])->values()->all(),
            'riskPatterns' => $ruleSet->riskPatterns->map(fn (SuspectRiskPattern $pattern): array => [
                'id' => $pattern->id,
                'code' => $pattern->code,
                'reason' => $pattern->reason,
                'condition' => Condition::describe($pattern->condition),
                'active' => $pattern->active,
            ])->values()->all(),
            'criterionOptions' => $criterionOptions,
            'actions' => [
                'clone' => true,
                'submit' => $ruleSet->status === SuspectRuleSetStatus::Draft,
                'approve' => $ruleSet->status === SuspectRuleSetStatus::PendingApproval,
                'publish' => $ruleSet->status === SuspectRuleSetStatus::Approved && $ruleSet->published_at === null,
                'retire' => $ruleSet->status !== SuspectRuleSetStatus::Retired,
            ],
            'urls' => [
                'index' => route('admin.suspect-rules.index'),
                'update' => route('admin.suspect-rules.update', $ruleSet),
                'clone' => route('admin.suspect-rules.clone', $ruleSet),
                'submit' => route('admin.suspect-rules.submit', $ruleSet),
                'approve' => route('admin.suspect-rules.approve', $ruleSet),
                'publish' => route('admin.suspect-rules.publish', $ruleSet),
                'retire' => route('admin.suspect-rules.retire', $ruleSet),
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        abort_unless($suspectRuleSet->isDraft(), 403, __('entry.suspect_rule_set_locked'));

        $data = $request->validate([
            'high_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'medium_threshold' => ['required', 'integer', 'min:0', 'max:100', 'lte:high_threshold'],
            'low_threshold' => ['required', 'integer', 'min:0', 'max:100', 'lte:medium_threshold'],
            'refer_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'items' => ['array'],
            'items.*' => ['required', 'integer', 'min:0', 'max:100'],
            'criteria' => ['array'],
            'criteria.*.condition_item' => ['nullable', 'string', 'max:64'],
            'criteria.*.reference' => ['nullable', 'string', 'max:255'],
            'criteria.*.configured' => ['boolean'],
            'patterns' => ['array'],
            'patterns.*' => ['boolean'],
        ]);

        $actor = $this->actor($request);
        $ruleSet = $suspectRuleSet->load(['scoreItems', 'clinicalRules', 'riskPatterns']);
        $items = $ruleSet->scoreItems->keyBy('id');
        $rules = $ruleSet->clinicalRules->keyBy('id');
        $patterns = $ruleSet->riskPatterns->keyBy('id');
        $byKey = $ruleSet->scoreItems->keyBy('key');
        $changes = [];

        foreach (array_keys($data['items'] ?? []) as $id) {
            if (! $items->has($id)) {
                throw ValidationException::withMessages(['items.'.$id => __('entry.suspect_not_in_rule_set')]);
            }
        }

        foreach ($data['criteria'] ?? [] as $id => $criterion) {
            $rule = $rules->get($id);

            if (! $rule instanceof SuspectClinicalRule || ! $rule->isTestingCriterion()) {
                throw ValidationException::withMessages(['criteria.'.$id => __('entry.suspect_not_in_rule_set')]);
            }

            $itemKey = $criterion['condition_item'] ?? null;

            if (is_string($itemKey) && $itemKey !== '' && ! $byKey->has($itemKey)) {
                throw ValidationException::withMessages(['criteria.'.$id.'.condition_item' => __('entry.suspect_not_in_rule_set')]);
            }

            if (($criterion['configured'] ?? false) && ($itemKey === null || $itemKey === '')) {
                throw ValidationException::withMessages(['criteria.'.$id.'.condition_item' => __('entry.suspect_criterion_needs_condition')]);
            }
        }

        foreach (array_keys($data['patterns'] ?? []) as $id) {
            if (! $patterns->has($id)) {
                throw ValidationException::withMessages(['patterns.'.$id => __('entry.suspect_not_in_rule_set')]);
            }
        }

        DB::transaction(function () use ($ruleSet, $data, $items, $rules, $patterns, $byKey, &$changes): void {
            $thresholds = [
                'high_threshold' => (int) $data['high_threshold'],
                'medium_threshold' => (int) $data['medium_threshold'],
                'low_threshold' => (int) $data['low_threshold'],
                'refer_threshold' => (int) $data['refer_threshold'],
            ];

            foreach ($thresholds as $column => $value) {
                if ($ruleSet->getAttribute($column) !== $value) {
                    $changes[$column] = ['from' => $ruleSet->getAttribute($column), 'to' => $value];
                }
            }

            $ruleSet->fill($thresholds)->save();

            foreach ($data['items'] ?? [] as $id => $points) {
                $item = $items->get($id);

                if ($item instanceof SuspectScoreItem && $item->points !== (int) $points) {
                    $changes['points.'.$item->key] = ['from' => $item->points, 'to' => (int) $points];
                    $item->update(['points' => (int) $points]);
                }
            }

            foreach ($data['criteria'] ?? [] as $id => $criterion) {
                $rule = $rules->get($id);

                if (! $rule instanceof SuspectClinicalRule) {
                    continue;
                }

                $itemKey = $criterion['condition_item'] ?? null;
                $source = is_string($itemKey) && $itemKey !== '' ? $byKey->get($itemKey) : null;

                $rule->fill([
                    'condition' => $source instanceof SuspectScoreItem ? $source->condition : null,
                    'criterion_reference' => $criterion['reference'] ?? null,
                    'configured' => (bool) ($criterion['configured'] ?? false),
                ]);

                if ($rule->isDirty()) {
                    $changes['criterion.'.$rule->code] = [
                        'condition_item' => $source?->key,
                        'reference' => $rule->criterion_reference,
                        'configured' => $rule->configured,
                    ];
                    $rule->save();
                }
            }

            foreach ($data['patterns'] ?? [] as $id => $active) {
                $pattern = $patterns->get($id);

                if ($pattern instanceof SuspectRiskPattern && $pattern->active !== (bool) $active) {
                    $changes['pattern.'.$pattern->code] = ['from' => $pattern->active, 'to' => (bool) $active];
                    $pattern->update(['active' => (bool) $active]);
                }
            }
        });

        $this->audit->write($actor, 'suspect_rules.updated', $ruleSet, [
            'version' => $ruleSet->version,
            'changes' => $changes,
        ]);

        return back()->with('status', __('entry.suspect_rule_set_saved'));
    }

    public function cloneToDraft(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        $actor = $this->actor($request);

        $draft = $this->workflow->cloneToDraft($suspectRuleSet, $actor);

        $this->audit->write($actor, 'suspect_rules.cloned', $draft, [
            'version' => $draft->version,
            'cloned_from' => $suspectRuleSet->version,
        ]);

        return redirect()->route('admin.suspect-rules.show', $draft)
            ->with('status', __('entry.suspect_rule_set_cloned', ['version' => $draft->version]));
    }

    public function submit(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        $actor = $this->actor($request);

        if (! $suspectRuleSet->isDraft()) {
            throw ValidationException::withMessages(['status' => __('entry.suspect_only_draft_submit')]);
        }

        $this->workflow->submit($suspectRuleSet);
        $this->audit->write($actor, 'suspect_rules.submitted', $suspectRuleSet, ['version' => $suspectRuleSet->version]);

        return back()->with('status', __('entry.suspect_rule_set_submitted'));
    }

    public function approve(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        $actor = $this->actor($request);

        $data = $request->validate([
            'clinical_signoff_reference' => ['required', 'string', 'max:255'],
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($suspectRuleSet->status !== SuspectRuleSetStatus::PendingApproval) {
            throw ValidationException::withMessages(['status' => __('entry.suspect_only_pending_approve')]);
        }

        $this->workflow->approve(
            $suspectRuleSet,
            $actor,
            trim($data['clinical_signoff_reference']),
            isset($data['approval_note']) ? trim($data['approval_note']) : null,
        );

        $this->audit->write($actor, 'suspect_rules.approved', $suspectRuleSet, [
            'version' => $suspectRuleSet->version,
            'clinical_signoff_reference' => $suspectRuleSet->clinical_signoff_reference,
        ]);

        return back()->with('status', __('entry.suspect_rule_set_approved'));
    }

    public function publish(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        $actor = $this->actor($request);

        if ($suspectRuleSet->status !== SuspectRuleSetStatus::Approved || $suspectRuleSet->published_at !== null || ! $suspectRuleSet->hasClinicalApproval()) {
            throw ValidationException::withMessages(['status' => __('entry.suspect_only_approved_publish')]);
        }

        $retired = $this->workflow->publish($suspectRuleSet);

        $this->audit->write($actor, 'suspect_rules.published', $suspectRuleSet, [
            'version' => $suspectRuleSet->version,
            'retired' => $retired,
        ]);

        return back()->with('status', __('entry.suspect_rule_set_published'));
    }

    public function retire(Request $request, SuspectRuleSet $suspectRuleSet): RedirectResponse
    {
        Gate::authorize('administer');
        $actor = $this->actor($request);

        if ($suspectRuleSet->status === SuspectRuleSetStatus::Retired) {
            throw ValidationException::withMessages(['status' => __('entry.suspect_already_retired')]);
        }

        $this->workflow->retire($suspectRuleSet);
        $this->audit->write($actor, 'suspect_rules.retired', $suspectRuleSet, ['version' => $suspectRuleSet->version]);

        return back()->with('status', __('entry.suspect_rule_set_retired'));
    }

    /**
     * The score item whose condition the testing criterion copies, if any.
     */
    private function matchingItem(SuspectRuleSet $ruleSet, SuspectClinicalRule $rule): ?string
    {
        if ($rule->condition === null) {
            return null;
        }

        foreach ($ruleSet->scoreItems as $item) {
            if ($item->condition == $rule->condition) {
                return $item->key;
            }
        }

        return null;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
