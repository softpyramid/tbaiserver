<?php

namespace App\Support;

use App\Enums\ProgrammeRole;
use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\ProgrammeAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DataScope
{
    /**
     * Limit a facility query to the viewer's programme scope, or to their own facilities.
     *
     * @param  Builder<Facility>  $query
     */
    public function constrainFacilities(Builder $query, User $user): void
    {
        if ($user->isTbaiAdmin()) {
            return;
        }

        $assignments = $user->programmeAssignments()->get();

        if ($assignments->isEmpty()) {
            $teamIds = $user->teams()->pluck('teams.id');
            $query->whereIn('facilities.team_id', $teamIds);

            return;
        }

        $query->where(function (Builder $outer) use ($assignments): void {
            foreach ($assignments as $assignment) {
                if ($assignment->role === ProgrammeRole::TbaiAdmin) {
                    continue;
                }

                $outer->orWhere(function (Builder $inner) use ($assignment): void {
                    $this->applyAssignment($inner, $assignment);
                });
            }
        });
    }

    /**
     * @param  Builder<City>  $query
     */
    public function constrainCities(Builder $query, User $user): void
    {
        if ($user->isTbaiAdmin()) {
            return;
        }

        $assignments = $user->programmeAssignments()->get();

        if ($assignments->isEmpty()) {
            $facilityCityIds = Facility::query()
                ->whereIn('team_id', $user->teams()->pluck('teams.id'))
                ->pluck('city_id');
            $query->whereIn('cities.id', $facilityCityIds);

            return;
        }

        $query->where(function (Builder $outer) use ($assignments): void {
            foreach ($assignments as $assignment) {
                match ($assignment->role) {
                    ProgrammeRole::TbaiAdmin => null,
                    ProgrammeRole::CityOfficer => $outer->orWhere('cities.id', $assignment->scope_id),
                    ProgrammeRole::ProvincialViewer => $outer->orWhere('cities.province_id', $assignment->scope_id),
                    ProgrammeRole::CountryViewer => $outer->orWhereHas(
                        'province',
                        fn (Builder $province) => $province->where('country_id', $assignment->scope_id),
                    ),
                };
            }
        });
    }

    public function allowsFacility(User $user, int $facilityId): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        $query = Facility::query()->whereKey($facilityId);
        $this->constrainFacilities($query, $user);

        return $query->exists();
    }

    public function label(User $user): string
    {
        if ($user->isTbaiAdmin()) {
            return __('entry.scope_all');
        }

        $labels = $user->programmeAssignments()
            ->get()
            ->map(fn (ProgrammeAssignment $assignment): string => $this->assignmentLabel($assignment))
            ->filter(fn (string $label): bool => $label !== '')
            ->unique()
            ->values();

        if ($labels->isEmpty()) {
            return '';
        }

        return $labels->implode(', ');
    }

    /**
     * @param  Builder<Facility>  $query
     */
    private function applyAssignment(Builder $query, ProgrammeAssignment $assignment): void
    {
        match ($assignment->role) {
            ProgrammeRole::TbaiAdmin => null,
            ProgrammeRole::CityOfficer => $query->where('facilities.city_id', $assignment->scope_id),
            ProgrammeRole::ProvincialViewer => $query->whereHas(
                'city',
                fn (Builder $city) => $city->where('province_id', $assignment->scope_id),
            ),
            ProgrammeRole::CountryViewer => $query->whereHas(
                'city.province',
                fn (Builder $province) => $province->where('country_id', $assignment->scope_id),
            ),
        };
    }

    private function assignmentLabel(ProgrammeAssignment $assignment): string
    {
        return match ($assignment->role) {
            ProgrammeRole::TbaiAdmin => ResourceResponse::line('entry.scope_all'),
            ProgrammeRole::CityOfficer => $this->placeName(City::query()->whereKey($assignment->scope_id)),
            ProgrammeRole::ProvincialViewer => $this->placeName(Province::query()->whereKey($assignment->scope_id)),
            ProgrammeRole::CountryViewer => $this->placeName(Country::query()->whereKey($assignment->scope_id)),
        };
    }

    /**
     * @param  Builder<City>|Builder<Province>|Builder<Country>  $query
     */
    private function placeName(Builder $query): string
    {
        $name = $query->value('name');

        return is_string($name) ? $name : '';
    }
}
