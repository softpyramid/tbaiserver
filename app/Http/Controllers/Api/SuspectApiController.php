<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartSuspectAssessmentRequest;
use App\Http\Requests\SuspectAnswersRequest;
use App\Models\SuspectAssessment;
use App\Models\Team;
use App\Models\User;
use App\Support\Suspect\AssessmentPresenter;
use App\Support\Suspect\AssessmentRecorder;
use App\Support\Suspect\RuleSetResolver;
use App\Support\Suspect\SuspectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suspect-Finding screening API, Phase 1. Session auth like /api/command.
 * Facility staff screen at their own facility; a TB-AI admin may read any
 * screening. The score is a provisional prioritisation score, never a
 * probability, and never overrules a clinical rule.
 */
class SuspectApiController extends Controller
{
    public function __construct(
        private readonly RuleSetResolver $resolver,
        private readonly AssessmentRecorder $recorder,
        private readonly AssessmentPresenter $presenter,
        private readonly SuspectAccess $access,
    ) {}

    public function ruleSet(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->isTbaiAdmin() || $this->access->isStaffAnywhere($user), 403);

        $ruleSet = $this->resolver->current();

        if ($ruleSet === null) {
            return $this->unavailable();
        }

        return response()->json($this->presenter->ruleSet($ruleSet));
    }

    public function start(StartSuspectAssessmentRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $team = Team::query()->where('slug', $request->string('facility')->toString())->first();
        $facility = $this->access->staffFacility($user, $team);
        abort_if($facility === null, 403, __('entry.suspect_staff_only'));

        $ruleSet = $this->resolver->current();

        if ($ruleSet === null) {
            return $this->unavailable();
        }

        $assessment = $this->recorder->start(
            $user,
            $facility,
            $ruleSet,
            $request->string('age_band')->toString(),
            $request->string('sex')->toString(),
        );

        return response()->json(
            $this->presenter->inProgress($assessment, [], $this->recorder->evaluate($assessment)),
            201,
        );
    }

    public function answers(SuspectAnswersRequest $request, SuspectAssessment $assessment): JsonResponse
    {
        $this->authorizeStaff($request, $assessment);

        if (! $this->resolver->servable($assessment->ruleSet)) {
            return $this->unavailable();
        }

        abort_if($assessment->isCompleted(), 409, __('entry.suspect_already_complete'));

        $removed = $this->recorder->answer($assessment, $request->answers());

        return response()->json(
            $this->presenter->inProgress($assessment, $removed, $this->recorder->evaluate($assessment)),
        );
    }

    public function complete(Request $request, SuspectAssessment $assessment): JsonResponse
    {
        $this->authorizeStaff($request, $assessment);

        if (! $this->resolver->servable($assessment->ruleSet)) {
            return $this->unavailable();
        }

        abort_if($assessment->isCompleted(), 409, __('entry.suspect_already_complete'));

        $this->recorder->complete($assessment);

        return response()->json($this->presenter->result($assessment->refresh()));
    }

    public function show(Request $request, SuspectAssessment $assessment): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->access->canRead($user, $assessment), 404);

        if (! $assessment->isCompleted()) {
            return response()->json(
                $this->presenter->inProgress($assessment, [], $this->recorder->evaluate($assessment)),
            );
        }

        return response()->json($this->presenter->result($assessment));
    }

    /**
     * Only staff of the screening's facility may change it. Anyone else who
     * cannot read it gets a 404, so screenings at other facilities stay hidden.
     */
    private function authorizeStaff(Request $request, SuspectAssessment $assessment): void
    {
        $user = $this->user($request);

        if ($this->access->isStaff($user, $assessment)) {
            return;
        }

        abort_if($user->isTbaiAdmin(), 403, __('entry.suspect_staff_only'));
        abort(404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => RuleSetResolver::unavailableMessage()], 503);
    }
}
