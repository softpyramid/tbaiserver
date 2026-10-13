<?php

use App\Enums\SuspectRuleSetStatus;
use App\Enums\TeamRole;
use App\Models\SuspectRuleSet;
use App\Support\Suspect\AssessmentRecorder;
use App\Support\Suspect\RuleSetResolver;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('suspect_screening');
});

afterEach(fn () => Carbon::setTestNow());

const PAUSED_TEXT = 'Screening is paused until an approved rule set is published. Existing screenings are still available to view.';

/**
 * Approve both seeded drafts without publishing them: nothing is servable.
 */
function pauseScreening(): void
{
    SuspectRuleSet::query()->each(function (SuspectRuleSet $ruleSet): void {
        $ruleSet->forceFill(['status' => SuspectRuleSetStatus::Approved])->save();
    });
}

test('an approved but unpublished rule set pauses screening on every facility page', function () {
    Carbon::setTestNow('2026-10-12 09:00:00');
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff(TeamRole::Member);
    $recorder = app(AssessmentRecorder::class);
    $assessment = $recorder->start($user, $facility, $ruleSet, 'Test Patient', 'REG-TEST-1', '25_44', 'female');
    $recorder->complete($assessment);

    pauseScreening();

    expect(app(RuleSetResolver::class)->current())->toBeNull();

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/index')
            ->where('unavailable', PAUSED_TEXT)
            ->where('canScreen', false)
            ->has('rows', 1)
            ->where('rows.0.id', $assessment->uuid)
            ->where('rows.0.draft', true)
            ->where('i18n.entry.suspect_paused_title', 'Screening is paused'));

    $this->actingAs($user)
        ->get(route('suspect.create', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/new')
            ->where('unavailable', PAUSED_TEXT)
            ->where('indexUrl', route('suspect.index', $facility->team)));

    $this->actingAs($user)
        ->get(route('suspect.show', [$facility->team, $assessment->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('result.assessment_id', $assessment->uuid)
            ->where('newUrl', null));
});

test('with a servable rule set the pages offer a new screening', function () {
    suspectDraftRuleSet();
    [$user, $facility] = suspectStaff(TeamRole::Admin);

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unavailable', null)
            ->where('canScreen', true)
            ->where('newUrl', route('suspect.create', $facility->team)));
});

test('the draft badge follows the rule set state when the screening started', function () {
    Carbon::setTestNow('2026-10-10 09:00:00');
    $ruleSet = suspectDraftRuleSet();
    [$user, $facility] = suspectStaff(TeamRole::Owner);
    $recorder = app(AssessmentRecorder::class);
    $before = $recorder->start($user, $facility, $ruleSet, 'Test Patient One', 'REG-TEST-1', '25_44', 'female');

    Carbon::setTestNow('2026-10-11 09:00:00');
    $ruleSet->forceFill(['status' => SuspectRuleSetStatus::Approved, 'published_at' => now()])->save();

    Carbon::setTestNow('2026-10-12 09:00:00');
    $after = $recorder->start($user, $facility, $ruleSet->fresh(), 'Test Patient Two', 'REG-TEST-2', '25_44', 'male');

    expect($before->fresh()->usedDraftRuleSet())->toBeTrue()
        ->and($after->fresh()->usedDraftRuleSet())->toBeFalse();

    $this->actingAs($user)
        ->get(route('suspect.index', $facility->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('rows.0.id', $after->uuid)
            ->where('rows.0.draft', false)
            ->where('rows.1.id', $before->uuid)
            ->where('rows.1.draft', true));
});
