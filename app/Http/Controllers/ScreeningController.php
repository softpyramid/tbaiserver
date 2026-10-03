<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScreeningRequest;
use App\Models\ScreeningSession;
use App\Models\Team;
use App\Support\EntryDate;
use App\Support\ScreeningScore;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    /**
     * Show the suspect screening form.
     */
    public function create(Request $request, Team $current_team): Response
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);
        abort_unless($request->user() !== null, 403);

        Gate::authorize('screen', $facility);

        $questions = ScreeningScore::questions();

        return Inertia::render('screening/new', [
            'facilityName' => $current_team->name,
            'dateLabel' => EntryDate::label(),
            'teamSlug' => $current_team->slug,
            'notice' => $questions === [] ? ScreeningScore::NOT_READY : null,
            'questions' => array_map(
                fn (array $question): array => [
                    'key' => $question['key'],
                    'label' => $question['label'],
                ],
                $questions,
            ),
            'confirmation' => $request->session()->get('screeningConfirmation'),
        ]);
    }

    /**
     * Store answers, score, and suspect flag. This does not change the daily return.
     */
    public function store(StoreScreeningRequest $request, Team $current_team): RedirectResponse
    {
        $user = $request->user();
        $facility = $current_team->facility;
        abort_unless($user !== null, 403);
        abort_unless($facility !== null, 404);

        $answers = [];

        foreach (ScreeningScore::questions() as $question) {
            $answers[$question['key']] = $request->string('answers.'.$question['key'])->toString();
        }

        $result = app(ScreeningScore::class)->evaluate($answers);
        $message = ScreeningScore::confirmation($result['score'], $result['is_suspect']);

        ScreeningSession::query()->create([
            'facility_id' => $facility->id,
            'user_id' => $user->id,
            'occurred_on' => CarbonImmutable::now((string) config('app.timezone'))->toDateString(),
            'answers' => $answers,
            'score' => $result['score'],
            'is_suspect' => $result['is_suspect'],
        ]);

        return to_route('screening.create', ['current_team' => $current_team->slug])
            ->with('screeningConfirmation', [
                'score' => $result['score'],
                'isSuspect' => $result['is_suspect'],
                'message' => $message,
            ]);
    }
}
