<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Http\Requests\SaveDailyReturnRequest;
use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;
use App\Models\ScreeningSession;
use App\Models\Team;
use App\Support\EntryDate;
use App\Support\ProvisionalReportedTat;
use App\Support\ReturnDatePolicy;
use App\Support\SlidePhotoList;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyReturnController extends Controller
{
    /**
     * Show today's register, or another open date.
     */
    public function edit(Request $request, Team $current_team, ReturnDatePolicy $dates): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);
        // Frontline workers (screeners) belong to the team but never see the return.
        abort_unless($user->worksReturnsAt($current_team) || $user->isTbaiAdmin(), 403);

        $reportedOn = $this->reportedOn($request);

        if (! $dates->canView($user, $current_team, $reportedOn)) {
            $message = $user->teamRole($current_team) === TeamRole::Member
                ? __('entry.technician_date_window')
                : __('entry.date_closed');

            return to_route('returns.today', $current_team)->with('date_notice', $message);
        }

        $dailyReturn = DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->whereDate('reported_on', $reportedOn->toDateString())
            ->first();

        $yearReturns = $facility->dailyReturns()
            ->whereYear('reported_on', $reportedOn->year)
            ->get();

        $provisionalAverage = ProvisionalReportedTat::average($yearReturns);

        $screeningSuspects = config('tbai.features.screening')
            ? ScreeningSession::query()
                ->where('facility_id', $facility->id)
                ->whereDate('occurred_on', $reportedOn->toDateString())
                ->where('is_suspect', true)
                ->count()
            : null;

        $canSeeHistory = $dates->canSeeHistory($user, $current_team);
        $today = EntryDate::now()->startOfDay();
        $canChooseDate = $user->isTbaiAdmin()
            || ($user->teamRole($current_team)?->isAtLeast(TeamRole::Admin) ?? false);
        $dailyReturn?->loadMissing('submitter');

        return Inertia::render('returns/today', [
            'teamSlug' => $current_team->slug,
            'facility' => [
                'name' => $current_team->name,
                'sector' => $facility->sector->label(),
                'city' => $facility->city->name,
            ],
            'reportedOn' => $reportedOn->toDateString(),
            'reportedOnLabel' => EntryDate::label($reportedOn),
            'isToday' => $reportedOn->isSameDay($today),
            'todayDate' => $today->toDateString(),
            'yesterdayDate' => $today->subDay()->toDateString(),
            'canChooseDate' => $canChooseDate,
            'earliestDate' => $canChooseDate ? null : $today->subDay()->toDateString(),
            'latestDate' => $today->toDateString(),
            'canSave' => $user->worksReturnsAt($current_team),
            'returnUpdatedAt' => $dailyReturn?->updated_at?->toISOString(),
            'lastUpdated' => $this->lastUpdated($dailyReturn),
            'screeningSuspects' => $screeningSuspects,
            'return' => [
                'suspects' => $dailyReturn->suspects ?? 0,
                'tested' => $dailyReturn->tested ?? 0,
                'positive' => $dailyReturn->positive ?? 0,
                'negative' => $dailyReturn->negative ?? 0,
                'started_treatment' => $dailyReturn->started_treatment ?? 0,
                'follow_up' => $dailyReturn->follow_up ?? 0,
                'median_tat_days' => $dailyReturn?->median_tat_days,
            ],
            'provisionalTat' => [
                'label' => ProvisionalReportedTat::LABEL,
                'helper' => ProvisionalReportedTat::HELPER,
                'insufficient' => $provisionalAverage === null ? ProvisionalReportedTat::INSUFFICIENT : null,
                'value' => $provisionalAverage,
                'year' => $reportedOn->year,
            ],
            'history' => $canSeeHistory
                ? $this->history($dailyReturn)
                : null,
            'dateNotice' => $request->session()->get('date_notice'),
            'slides' => [
                'photos' => app(SlidePhotoList::class)->photos($dailyReturn, $user, $current_team),
                'uploadUrl' => $dailyReturn !== null && $user->worksReturnsAt($current_team) && $dates->canEdit($user, $current_team, $reportedOn)
                    ? route('returns.slides.store', ['current_team' => $current_team->slug, 'dailyReturn' => $dailyReturn->id])
                    : null,
                'needsSavedReturn' => $dailyReturn === null,
                ...app(SlidePhotoList::class)->limits(),
            ],
        ]);
    }

    /**
     * Create or update the single return for this facility and date.
     */
    public function update(SaveDailyReturnRequest $request, Team $current_team): RedirectResponse
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        $reportedOn = CarbonImmutable::parse(
            $request->string('reported_on')->toString(),
            (string) config('app.timezone'),
        )->toDateString();

        $median = $request->input('median_tat_days');

        $dailyReturn = DailyReturn::query()
            ->where('facility_id', $facility->id)
            ->whereDate('reported_on', $reportedOn)
            ->first() ?? new DailyReturn([
                'facility_id' => $facility->id,
                'reported_on' => $reportedOn,
            ]);

        $dailyReturn->fill([
            'submitted_by' => $request->user()->id,
            'suspects' => $request->integer('suspects'),
            'tested' => $request->integer('tested'),
            'positive' => $request->integer('positive'),
            'negative' => $request->integer('negative'),
            'started_treatment' => $request->integer('started_treatment'),
            'follow_up' => $request->integer('follow_up'),
            'median_tat_days' => $median === null || $median === '' ? null : $median,
        ]);
        $dailyReturn->save();

        Inertia::flash('savedReturn', true);

        return to_route('returns.today', [
            'current_team' => $current_team->slug,
            'reported_on' => $reportedOn,
        ]);
    }

    private function reportedOn(Request $request): CarbonImmutable
    {
        $value = $request->query('reported_on');
        $timezone = (string) config('app.timezone');

        if (! is_string($value) || $value === '') {
            return CarbonImmutable::now($timezone)->startOfDay();
        }

        try {
            return CarbonImmutable::parse($value, $timezone)->startOfDay();
        } catch (InvalidFormatException) {
            return CarbonImmutable::now($timezone)->startOfDay();
        }
    }

    /**
     * @return array{name: string, at: string, line: string}|null
     */
    private function lastUpdated(?DailyReturn $dailyReturn): ?array
    {
        if ($dailyReturn === null || $dailyReturn->updated_at === null) {
            return null;
        }

        $name = $dailyReturn->submitter->name;
        $at = EntryDate::time($dailyReturn->updated_at);

        return [
            'name' => $name,
            'at' => $at,
            'line' => __('entry.last_updated', [
                'name' => $name,
                'time' => $at,
            ]),
        ];
    }

    /**
     * @return list<array{changedAt: string, changedBy: string, changes: list<array{field: string, from: int|float|null, to: int|float|null}>}>
     */
    private function history(?DailyReturn $dailyReturn): array
    {
        if ($dailyReturn === null) {
            return [];
        }

        return $dailyReturn->revisions()
            ->with('editor')
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (DailyReturnRevision $revision): array {
                $changes = [];

                foreach (DailyReturn::TRACKED as $field) {
                    $from = $revision->old_values[$field] ?? null;
                    $to = $revision->new_values[$field] ?? null;

                    if ($from !== $to) {
                        $changes[] = [
                            'field' => $field,
                            'from' => $from,
                            'to' => $to,
                        ];
                    }
                }

                return [
                    'changedAt' => EntryDate::dateTime($revision->changed_at),
                    'changedBy' => $revision->editor->name,
                    'changes' => $changes,
                ];
            })
            ->all();
    }
}
