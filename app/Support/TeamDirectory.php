<?php

namespace App\Support;

use App\Enums\Sector;
use App\Enums\TeamRole;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TeamDirectory
{
    /**
     * Administration list of facility teams.
     * District is the city record. That is the programme district.
     *
     * @return array<string, mixed>
     */
    public function page(Request $request): array
    {
        $district = $request->string('district')->toString();
        $sector = $request->string('sector')->toString();

        $query = Team::query();

        if ($district !== '' && ctype_digit($district)) {
            $query->whereHas('facility', fn ($facilities) => $facilities->where('city_id', (int) $district));
        }

        $sectorEnum = Sector::tryFrom($sector);

        if ($sectorEnum instanceof Sector) {
            $query->whereHas('facility', fn ($facilities) => $facilities->where('sector', $sectorEnum));
        }

        $teamIds = (clone $query)->pluck('id');
        $pending = TeamInvitation::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('accepted_at')
            ->where(function ($invitations): void {
                $invitations->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();
        $members = Membership::query()->whereIn('team_id', $teamIds)->count();

        $page = $query
            ->with(['facility.city', 'memberships.user'])
            ->withCount('members')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $facilityIds = $page->getCollection()
            ->map(fn (Team $team): ?int => $team->facility?->id)
            ->filter()
            ->values();

        $returnAt = DailyReturn::query()
            ->whereIn('facility_id', $facilityIds)
            ->selectRaw('facility_id, max(updated_at) as last_at')
            ->groupBy('facility_id')
            ->pluck('last_at', 'facility_id');

        $rows = $page->getCollection()->map(function (Team $team) use ($returnAt): array {
            $facility = $team->facility;
            $city = $facility?->city?->name ?? '';
            $inCharge = $team->memberships
                ->filter(fn ($membership): bool => $membership->role === TeamRole::Owner)
                ->map(fn ($membership): string => $membership->user->name)
                ->filter()
                ->values();
            $seen = $team->memberships
                ->map(fn ($membership): ?CarbonInterface => $membership->user->last_seen_at)
                ->filter()
                ->sortBy(fn (CarbonInterface $at): int => $at->getTimestamp())
                ->last();
            $returnStamp = $facility === null ? null : $returnAt->get($facility->id);
            $candidates = collect($seen instanceof CarbonInterface ? [$seen] : []);

            if (is_string($returnStamp) && $returnStamp !== '') {
                $candidates->push(Carbon::parse($returnStamp));
            }

            $latest = $candidates->sortBy(fn (CarbonInterface $at): int => $at->getTimestamp())->last();

            return [
                'id' => (string) $team->id,
                'href' => $facility === null ? null : route('facilities.members', $team),
                'team' => $team->name,
                'type' => $facility === null
                    ? __('entry.team_type_personal')
                    : __('entry.team_type_facility'),
                'city' => $city,
                'district' => $city,
                'members' => (string) $team->getAttribute('members_count'),
                'inCharge' => $inCharge->isEmpty() ? __('entry.not_available') : $inCharge->implode(', '),
                'lastActivity' => LastActive::describe($latest instanceof CarbonInterface ? $latest : null)['label'],
            ];
        })->values()->all();

        $teams = $teamIds->count();

        return [
            'title' => __('entry.nav_teams'),
            'description' => __('entry.teams_description'),
            'cards' => [
                [
                    'key' => 'teams',
                    'label' => __('entry.teams_card_teams'),
                    'figure' => trans_choice('entry.count_teams', $teams, ['count' => $teams]),
                    'caption' => __('entry.teams_card_teams_caption'),
                ],
                [
                    'key' => 'members',
                    'label' => __('entry.team_card_members'),
                    'figure' => trans_choice('entry.count_members', $members, ['count' => $members]),
                    'caption' => __('entry.teams_card_members_caption'),
                ],
                [
                    'key' => 'pending',
                    'label' => __('entry.team_card_pending'),
                    'figure' => trans_choice('entry.count_invites', $pending, ['count' => $pending]),
                    'caption' => __('entry.team_card_pending_caption'),
                ],
            ],
            'columns' => [
                ['key' => 'team', 'label' => __('entry.column_team')],
                ['key' => 'type', 'label' => __('entry.column_type')],
                ['key' => 'city', 'label' => __('entry.city')],
                ['key' => 'district', 'label' => __('entry.column_district')],
                ['key' => 'members', 'label' => __('entry.column_members')],
                ['key' => 'inCharge', 'label' => __('entry.column_in_charge')],
                ['key' => 'lastActivity', 'label' => __('entry.last_activity')],
            ],
            'filters' => [
                'district' => $district,
                'sector' => $sector,
            ],
            'districts' => City::query()->orderBy('name')->get(['id', 'name'])->map(fn (City $city): array => [
                'value' => (string) $city->id,
                'label' => $city->name,
            ])->all(),
            'sectors' => Sector::options(),
            'rows' => $rows,
            'pagination' => [
                'showing' => $page->total() === 0 ? '' : __('entry.showing_rows', [
                    'from' => (string) $page->firstItem(),
                    'to' => (string) $page->lastItem(),
                    'total' => (string) $page->total(),
                ]),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
            'labels' => [
                'district' => __('entry.column_district'),
                'sector' => __('entry.sector'),
                'any' => __('entry.any'),
                'apply' => __('entry.apply'),
                'clear' => __('entry.clear'),
                'empty' => __('entry.admin_teams_empty'),
                'previous' => __('entry.previous_page'),
                'next' => __('entry.next_page'),
                'open' => __('entry.open_members'),
            ],
        ];
    }
}
