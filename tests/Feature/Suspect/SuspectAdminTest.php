<?php

use App\Enums\SuspectRuleSetStatus;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\SuspectRuleSet;
use App\Support\Suspect\RuleSetResolver;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    enableFeature('suspect_screening');
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Karachi'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array<string, mixed>
 */
function suspectDraftForm(SuspectRuleSet $ruleSet, array $points = [], array $overrides = []): array
{
    $ruleSet->load(['scoreItems', 'clinicalRules', 'riskPatterns']);

    $items = [];

    foreach ($ruleSet->scoreItems as $item) {
        $items[$item->id] = $points[$item->key] ?? $item->points;
    }

    return [
        'high_threshold' => $ruleSet->high_threshold,
        'medium_threshold' => $ruleSet->medium_threshold,
        'low_threshold' => $ruleSet->low_threshold,
        'refer_threshold' => $ruleSet->refer_threshold,
        'items' => $items,
        ...$overrides,
    ];
}

function approveSuspectRuleSet(SuspectRuleSet $ruleSet, $admin): void
{
    test()->actingAs($admin)->post(route('admin.suspect-rules.submit', $ruleSet))->assertRedirect();
    test()->actingAs($admin)
        ->post(route('admin.suspect-rules.approve', $ruleSet), ['clinical_signoff_reference' => 'Clinical review memo 1'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

test('only a tb-ai admin can open or change suspect rules', function () {
    $ruleSet = suspectDraftRuleSet();
    [$staff] = suspectStaff(TeamRole::Owner);

    $this->actingAs($staff)->get(route('admin.suspect-rules.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.suspect-rules.show', $ruleSet))->assertForbidden();
    $this->actingAs($staff)->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet))->assertForbidden();
    $this->actingAs($staff)->post(route('admin.suspect-rules.clone', $ruleSet))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.suspect-assessments.index'))->assertForbidden();

    $admin = suspectAdmin();
    $this->actingAs($admin)->get(route('admin.suspect-rules.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.suspect-assessments.index'))->assertOk();

    config(['tbai.features.suspect_screening' => false]);
    $this->actingAs($admin)->get(route('admin.suspect-rules.index'))->assertNotFound();
});

test('weights, thresholds, the criterion and patterns are editable in a draft and audited', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();
    $criterion = $ruleSet->clinicalRules->firstWhere('code', 'TESTING_CRITERION_MET');
    $pattern = $ruleSet->riskPatterns->firstWhere('code', 'PERSISTENT_COUGH_WITH_TB_CONTACT');

    $this->actingAs($admin)
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, ['cough_current' => 12], [
            'refer_threshold' => 35,
            'criteria' => [$criterion->id => ['condition_item' => 'cough_gte_2_weeks', 'reference' => 'NTP manual section 3', 'configured' => true]],
            'patterns' => [$pattern->id => true],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $ruleSet->refresh()->load(['scoreItems', 'clinicalRules', 'riskPatterns']);
    $criterion->refresh();

    expect($ruleSet->scoreItems->firstWhere('key', 'cough_current')->points)->toBe(12)
        ->and($ruleSet->refer_threshold)->toBe(35)
        ->and($criterion->configured)->toBeTrue()
        ->and($criterion->criterion_reference)->toBe('NTP manual section 3')
        ->and($criterion->condition)->toBe($ruleSet->scoreItems->firstWhere('key', 'cough_gte_2_weeks')->condition)
        ->and($pattern->refresh()->active)->toBeTrue();

    $log = AuditLog::query()->where('action', 'suspect_rules.updated')->sole();

    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties['changes']['points.cough_current'])->toBe(['from' => 8, 'to' => 12])
        ->and($log->properties['changes']['refer_threshold'])->toBe(['from' => 40, 'to' => 35]);
});

test('the criterion cannot be marked configured without a condition', function () {
    $ruleSet = suspectDraftRuleSet();
    $criterion = $ruleSet->clinicalRules->firstWhere('code', 'TESTING_CRITERION_MET');

    $this->actingAs(suspectAdmin())
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], [
            'criteria' => [$criterion->id => ['condition_item' => '', 'reference' => '', 'configured' => true]],
        ]))
        ->assertSessionHasErrors('criteria.'.$criterion->id.'.condition_item');

    expect($criterion->refresh()->configured)->toBeFalse();
});

test('an approved rule set is read-only', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();
    approveSuspectRuleSet($ruleSet, $admin);

    $this->actingAs($admin)
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, ['cough_current' => 50]))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.suspect-rules.submit', $ruleSet))
        ->assertSessionHasErrors('status');

    expect($ruleSet->scoreItems()->where('key', 'cough_current')->value('points'))->toBe(8);

    $this->actingAs($admin)
        ->get(route('admin.suspect-rules.show', $ruleSet))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('suspect/rules-show')
            ->where('ruleSet.editable', false)
            ->where('ruleSet.status', 'approved')
            ->where('ruleSet.approvedBy', $admin->name)
            ->where('ruleSet.signoffReference', 'Clinical review memo 1')
            ->where('actions.publish', true)
            ->where('actions.approve', false));
});

test('cloning copies every row into the next free draft version', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();

    $this->actingAs($admin)->post(route('admin.suspect-rules.clone', $ruleSet))->assertRedirect();

    $clone = SuspectRuleSet::query()->where('cloned_from_id', $ruleSet->id)->sole();

    // draft.2 is seeded, so the first clone of draft.1 is draft.3.
    expect($clone->version)->toBe('2026.10-draft.3')
        ->and($clone->low_threshold)->toBe(0)
        ->and($clone->status)->toBe(SuspectRuleSetStatus::Draft)
        ->and($clone->created_by)->toBe($admin->id)
        ->and($clone->questions()->count())->toBe(19)
        ->and($clone->scoreItems()->count())->toBe(14)
        ->and($clone->clinicalRules()->count())->toBe(2)
        ->and($clone->riskPatterns()->count())->toBe(4)
        ->and(AuditLog::query()->where('action', 'suspect_rules.cloned')->value('subject_id'))->toBe($clone->id);

    $this->actingAs($admin)->post(route('admin.suspect-rules.clone', $clone))->assertRedirect();

    expect(SuspectRuleSet::query()->latest('id')->value('version'))->toBe('2026.10-draft.4');

    $draftTwo = suspectDraftTwoRuleSet();
    $this->actingAs($admin)->post(route('admin.suspect-rules.clone', $draftTwo))->assertRedirect();
    $twoClone = SuspectRuleSet::query()->where('cloned_from_id', $draftTwo->id)->sole();

    expect([$twoClone->high_threshold, $twoClone->medium_threshold, $twoClone->low_threshold, $twoClone->refer_threshold])
        ->toBe([70, 40, 20, 70]);
});

test('approval needs a clinical sign-off reference and records the approver', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();

    $this->actingAs($admin)->post(route('admin.suspect-rules.submit', $ruleSet))->assertRedirect();

    expect($ruleSet->refresh()->status)->toBe(SuspectRuleSetStatus::PendingApproval);

    $this->actingAs($admin)
        ->post(route('admin.suspect-rules.approve', $ruleSet), ['clinical_signoff_reference' => ''])
        ->assertSessionHasErrors('clinical_signoff_reference');

    expect($ruleSet->refresh()->status)->toBe(SuspectRuleSetStatus::PendingApproval);

    $this->actingAs($admin)
        ->post(route('admin.suspect-rules.approve', $ruleSet), [
            'clinical_signoff_reference' => 'Dr reviewer memo 12 Oct 2026',
            'approval_note' => 'Reviewed weights',
        ])
        ->assertSessionHasNoErrors();

    $ruleSet->refresh();

    expect($ruleSet->status)->toBe(SuspectRuleSetStatus::Approved)
        ->and($ruleSet->approved_by)->toBe($admin->id)
        ->and($ruleSet->approved_at)->not->toBeNull()
        ->and($ruleSet->clinical_signoff_reference)->toBe('Dr reviewer memo 12 Oct 2026')
        ->and(AuditLog::query()->where('action', 'suspect_rules.submitted')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'suspect_rules.approved')->count())->toBe(1);
});

test('publishing retires the previously published set and the resolver serves the new one', function () {
    $first = suspectDraftRuleSet();
    $admin = suspectAdmin();
    approveSuspectRuleSet($first, $admin);
    $this->actingAs($admin)->post(route('admin.suspect-rules.publish', $first))->assertSessionHasNoErrors();

    expect($first->refresh()->isPublished())->toBeTrue()
        ->and(app(RuleSetResolver::class)->current()?->id)->toBe($first->id);

    $this->actingAs($admin)->post(route('admin.suspect-rules.clone', $first));
    $second = SuspectRuleSet::query()->where('cloned_from_id', $first->id)->sole();
    approveSuspectRuleSet($second, $admin);
    $this->actingAs($admin)->post(route('admin.suspect-rules.publish', $second))->assertSessionHasNoErrors();

    expect($first->refresh()->status)->toBe(SuspectRuleSetStatus::Retired)
        ->and($second->refresh()->isPublished())->toBeTrue()
        ->and(SuspectRuleSet::query()->where('status', 'approved')->whereNotNull('published_at')->count())->toBe(1)
        ->and(app(RuleSetResolver::class)->current()?->id)->toBe($second->id);

    $log = AuditLog::query()->where('action', 'suspect_rules.published')->latest('id')->first();
    expect($log->properties['retired'])->toBe([$first->version]);

    $this->actingAs($admin)->post(route('admin.suspect-rules.retire', $second))->assertSessionHasNoErrors();

    expect($second->refresh()->status)->toBe(SuspectRuleSetStatus::Retired)
        ->and(AuditLog::query()->where('action', 'suspect_rules.retired')->count())->toBe(1);

    $this->actingAs($admin)
        ->patch(route('admin.suspect-rules.update', $second), suspectDraftForm($second))
        ->assertForbidden();
});

test('production serves an approved published set and refuses a draft', function () {
    $ruleSet = suspectDraftRuleSet();
    $admin = suspectAdmin();
    app()->instance('env', 'production');

    expect(app(RuleSetResolver::class)->current())->toBeNull();

    $ruleSet->forceFill([
        'status' => 'approved',
        'approved_by' => $admin->id,
        'approved_at' => now(),
        'clinical_signoff_reference' => 'Ref',
        'published_at' => now(),
    ])->save();

    expect(app(RuleSetResolver::class)->current()?->id)->toBe($ruleSet->id);
});

test('a draft cannot be published without approval', function () {
    $ruleSet = suspectDraftRuleSet();

    $this->actingAs(suspectAdmin())
        ->post(route('admin.suspect-rules.publish', $ruleSet))
        ->assertSessionHasErrors('status');

    expect($ruleSet->refresh()->published_at)->toBeNull();
});

test('the low threshold is editable in a draft, audited and shown', function () {
    $ruleSet = suspectDraftTwoRuleSet();
    $admin = suspectAdmin();

    $this->actingAs($admin)
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], ['low_threshold' => 25]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ruleSet->refresh()->low_threshold)->toBe(25)
        ->and(AuditLog::query()->where('action', 'suspect_rules.updated')->sole()->properties['changes'])
        ->toBe(['low_threshold' => ['from' => 20, 'to' => 25]]);

    $this->actingAs($admin)
        ->get(route('admin.suspect-rules.show', $ruleSet))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ruleSet.thresholds', ['high' => 70, 'medium' => 40, 'low' => 25, 'refer' => 70]));
});

test('the low threshold cannot be above the medium threshold', function () {
    $ruleSet = suspectDraftTwoRuleSet();

    $this->actingAs(suspectAdmin())
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], ['low_threshold' => 41]))
        ->assertSessionHasErrors('low_threshold');

    $this->actingAs(suspectAdmin())
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], ['medium_threshold' => 71]))
        ->assertSessionHasErrors('medium_threshold');

    $this->actingAs(suspectAdmin())
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], ['low_threshold' => null]))
        ->assertSessionHasErrors('low_threshold');

    expect($ruleSet->refresh()->low_threshold)->toBe(20)
        ->and($ruleSet->medium_threshold)->toBe(40);
});

test('the low threshold of an approved set cannot be changed', function () {
    $ruleSet = suspectDraftTwoRuleSet();
    $admin = suspectAdmin();
    approveSuspectRuleSet($ruleSet, $admin);

    $this->actingAs($admin)
        ->patch(route('admin.suspect-rules.update', $ruleSet), suspectDraftForm($ruleSet, [], ['low_threshold' => 5]))
        ->assertForbidden();

    expect($ruleSet->refresh()->low_threshold)->toBe(20)
        ->and(fn () => $ruleSet->update(['low_threshold' => 5]))->toThrow(LogicException::class);
});
