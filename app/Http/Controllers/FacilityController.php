<?php

namespace App\Http\Controllers;

use App\Enums\FacilityKind;
use App\Enums\FacilityStatus;
use App\Enums\Sector;
use App\Http\Requests\SaveFacilityRequest;
use App\Models\City;
use App\Models\Facility;
use App\Models\Taluka;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends Controller
{
    /**
     * List facilities for TB-AI admins.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Facility::class);

        $facilities = Facility::query()
            ->with(['team', 'city'])
            ->get()
            ->sortBy(fn (Facility $facility): string => mb_strtolower($facility->team->name))
            ->values()
            ->map(fn (Facility $facility): array => [
                'slug' => $facility->team->slug,
                'name' => $facility->team->name,
                'sector' => $facility->sector->label(),
                'city' => $facility->city->name,
                'active' => $facility->status === FacilityStatus::Active,
            ])
            ->all();

        return Inertia::render('facilities/index', [
            'facilities' => $facilities,
        ]);
    }

    /**
     * Show the form that creates a facility and its team.
     */
    public function create(): Response
    {
        Gate::authorize('create', Facility::class);

        return Inertia::render('facilities/create', $this->formOptions());
    }

    /**
     * Create the non-personal team and the facility row together.
     */
    public function store(SaveFacilityRequest $request): RedirectResponse
    {
        $facility = DB::transaction(function () use ($request): Facility {
            $team = Team::create([
                'name' => $request->string('name')->toString(),
                'is_personal' => false,
            ]);

            return Facility::create([
                'team_id' => $team->id,
                ...$this->attributes($request),
            ]);
        });

        $facility->load('team');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facility created.')]);

        return to_route('facilities.edit', ['current_team' => $facility->team->slug]);
    }

    /**
     * Show the facility profile.
     */
    public function edit(Team $current_team): Response
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        Gate::authorize('view', $facility);

        return Inertia::render('facilities/edit', [
            'teamSlug' => $current_team->slug,
            'facility' => [
                'name' => $current_team->name,
                'sector' => $facility->sector->value,
                'kind' => $facility->kind->value,
                'cityId' => $facility->city_id,
                'talukaId' => $facility->taluka_id,
                'code' => $facility->code ?? '',
                'active' => $facility->status === FacilityStatus::Active,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the facility and keep its team slug unique.
     */
    public function update(SaveFacilityRequest $request, Team $current_team): RedirectResponse
    {
        $facility = $current_team->facility;
        abort_unless($facility !== null, 404);

        $facility = DB::transaction(function () use ($request, $facility): Facility {
            $team = Team::query()->whereKey($facility->team_id)->lockForUpdate()->firstOrFail();
            $team->update(['name' => $request->string('name')->toString()]);
            $facility->update($this->attributes($request));
            $facility->setRelation('team', $team->refresh());

            return $facility;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facility profile saved.')]);

        return to_route('facilities.edit', ['current_team' => $facility->team->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveFacilityRequest $request): array
    {
        $code = $request->validated('code');

        return [
            'city_id' => $request->integer('city_id'),
            'taluka_id' => $request->filled('taluka_id') ? $request->integer('taluka_id') : null,
            'sector' => Sector::from($request->string('sector')->toString()),
            'kind' => FacilityKind::from($request->string('kind')->toString()),
            'code' => is_string($code) && $code !== '' ? $code : null,
            'status' => $request->boolean('active') ? FacilityStatus::Active : FacilityStatus::Inactive,
        ];
    }

    /**
     * @return array{
     *     sectors: list<array{value: string, label: string}>,
     *     kinds: list<array{value: string, label: string}>,
     *     cities: list<array{id: int, name: string}>,
     *     talukas: list<array{id: int, cityId: int, name: string}>
     * }
     */
    private function formOptions(): array
    {
        return [
            'sectors' => Sector::options(),
            'kinds' => FacilityKind::options(),
            'cities' => City::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (City $city): array => [
                    'id' => $city->id,
                    'name' => $city->name,
                ])
                ->all(),
            'talukas' => Taluka::query()
                ->orderBy('name')
                ->get(['id', 'city_id', 'name'])
                ->map(fn (Taluka $taluka): array => [
                    'id' => $taluka->id,
                    'cityId' => $taluka->city_id,
                    'name' => $taluka->name,
                ])
                ->all(),
        ];
    }
}
