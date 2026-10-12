<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\SuspectAssessment;
use App\Models\SuspectQuestion;
use App\Models\SuspectScoreItem;
use App\Models\Team;
use App\Models\User;
use App\Support\EntryDate;
use App\Support\ResourceResponse;
use App\Support\Suspect\AssessmentPresenter;
use App\Support\Suspect\AssessmentRecorder;
use App\Support\Suspect\RuleSetResolver;
use App\Support\Suspect\SuspectAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Facility pages for the suspect screening. The questionnaire itself talks
 * to the JSON API; these pages list recent screenings and show a result.
 */
class SuspectScreeningController extends Controller
{
    public function __construct(
        private readonly RuleSetResolver $resolver,
        private readonly SuspectAccess $access,
    ) {}

    public function index(Request $request, Team $current_team): Response
    {
        [$user, $facility] = $this->facility($request, $current_team, allowAdmin: true);
        $ruleSet = $this->resolver->current();

        $rows = SuspectAssessment::query()
            ->where('facility_id', $facility->id)
            ->latest('created_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (SuspectAssessment $assessment): array => [
                'id' => $assessment->uuid,
                'date' => $assessment->created_at === null ? '' : EntryDate::dateTime($assessment->created_at),
                'ageBand' => AssessmentPresenter::optionLabel($assessment->age_band),
                'sex' => AssessmentPresenter::optionLabel($assessment->sex),
                'completed' => $assessment->isCompleted(),
                'score' => $assessment->isCompleted() ? $assessment->score : null,
                'priority' => $assessment->priority,
                'priorityLabel' => $assessment->priority === null ? null : ResourceResponse::line('entry.suspect_priority_'.$assessment->priority),
                'refer' => $assessment->refer_for_testing,
                'href' => route('suspect.show', ['current_team' => $current_team->slug, 'assessment' => $assessment->uuid]),
            ])->values()->all();

        return Inertia::render('suspect/index', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'teamSlug' => $current_team->slug,
            'unavailable' => $ruleSet === null ? RuleSetResolver::unavailableMessage() : null,
            'draftNotice' => $ruleSet !== null && ! $ruleSet->isPublished() ? __('entry.suspect_draft_banner') : null,
            'canScreen' => $this->access->staffFacility($user, $current_team) !== null,
            'newUrl' => route('suspect.create', ['current_team' => $current_team->slug]),
            'rows' => $rows,
        ]);
    }

    public function create(Request $request, Team $current_team): Response
    {
        $this->facility($request, $current_team, allowAdmin: false);
        $ruleSet = $this->resolver->current();

        return Inertia::render('suspect/new', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'teamSlug' => $current_team->slug,
            'unavailable' => $ruleSet === null ? RuleSetResolver::unavailableMessage() : null,
            'draftNotice' => $ruleSet !== null && ! $ruleSet->isPublished() ? __('entry.suspect_draft_banner') : null,
            'ruleSetVersion' => $ruleSet?->version,
            'profile' => [
                'ageBands' => $this->options(SuspectAssessment::AGE_BANDS),
                'sexes' => $this->options(SuspectAssessment::SEXES),
            ],
            'endpoints' => [
                'start' => route('api.suspect.assessments.store'),
                'assessments' => url('/api/v1/suspect/assessments'),
            ],
            'indexUrl' => route('suspect.index', ['current_team' => $current_team->slug]),
        ]);
    }

    public function show(Request $request, Team $current_team, SuspectAssessment $assessment, AssessmentPresenter $presenter, AssessmentRecorder $recorder): Response
    {
        [$user, $facility] = $this->facility($request, $current_team, allowAdmin: true);
        abort_unless($assessment->facility_id === $facility->id && $this->access->canRead($user, $assessment), 404);

        $ruleSet = $assessment->ruleSet;
        $result = $assessment->isCompleted()
            ? $presenter->result($assessment)
            : $presenter->inProgress($assessment, [], $recorder->evaluate($assessment));

        return Inertia::render('suspect/show', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'teamSlug' => $current_team->slug,
            'result' => $result,
            'ruleSet' => [
                'version' => $ruleSet->version,
                'status' => $ruleSet->status->value,
                'statusLabel' => $ruleSet->status->label(),
                'published' => $ruleSet->isPublished(),
            ],
            'questionLabels' => $ruleSet->questions
                ->mapWithKeys(fn (SuspectQuestion $question): array => [$question->key => $question->prompt])
                ->all(),
            'itemLabels' => $ruleSet->scoreItems
                ->mapWithKeys(fn (SuspectScoreItem $item): array => [strtoupper($item->key) => $item->label])
                ->all(),
            'priorityLabel' => is_string($result['priority'] ?? null)
                ? ResourceResponse::line('entry.suspect_priority_'.$result['priority'])
                : null,
            'ageBandLabel' => AssessmentPresenter::optionLabel($assessment->age_band),
            'sexLabel' => AssessmentPresenter::optionLabel($assessment->sex),
            'completedAt' => $assessment->completed_at === null ? null : EntryDate::dateTime($assessment->completed_at),
            'indexUrl' => route('suspect.index', ['current_team' => $current_team->slug]),
            'newUrl' => $this->access->staffFacility($user, $current_team) !== null
                ? route('suspect.create', ['current_team' => $current_team->slug])
                : null,
        ]);
    }

    /**
     * Facility staff, or (for reading) a TB-AI admin.
     *
     * @return array{0: User, 1: Facility}
     */
    private function facility(Request $request, Team $team, bool $allowAdmin): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $facility = $team->facility;
        abort_unless($facility instanceof Facility, 404);

        $staff = $this->access->staffFacility($user, $team) !== null;
        abort_unless($staff || ($allowAdmin && $user->isTbaiAdmin()), 403);

        return [$user, $facility];
    }

    /**
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    private function options(array $values): array
    {
        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => AssessmentPresenter::optionLabel($value),
        ], $values);
    }
}
