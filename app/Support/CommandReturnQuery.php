<?php

namespace App\Support;

use App\Enums\Sector;
use App\Models\City;
use App\Models\DailyReturn;
use App\Models\Facility;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Daily returns inside the viewer's command-center scope.
 * Query parameters that point outside that scope are dropped.
 */
class CommandReturnQuery
{
    public function __construct(private DataScope $scope) {}

    /**
     * @return array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }
     */
    public function selection(Request $request, User $user, ?Sector $lockedSector, ?int $lockedCityId): array
    {
        $period = CommandPeriod::fromRequest($request);
        $provinceId = $this->acceptedProvinceId($request, $user);
        $cityId = $lockedCityId ?? $this->acceptedCityId($request, $user, $provinceId);

        if ($lockedCityId !== null && $provinceId !== null && ! City::query()->whereKey($lockedCityId)->where('province_id', $provinceId)->exists()) {
            $provinceId = null;
        }

        $sector = $lockedSector ?? $this->acceptedSector($request);
        $facilityId = $lockedCityId === null && $lockedSector === null
            ? $this->acceptedFacilityId($request, $user)
            : null;

        return [
            'period' => $period,
            'provinceId' => $provinceId,
            'cityId' => $cityId,
            'sector' => $sector,
            'facilityId' => $facilityId,
        ];
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<CommandReturnRow>
     */
    public function rows(User $user, array $selection): array
    {
        $facilities = Facility::query();
        $this->scope->constrainFacilities($facilities, $user);

        if ($selection['provinceId'] !== null && $selection['cityId'] === null) {
            $provinceId = $selection['provinceId'];
            $facilities->whereHas(
                'city',
                fn (Builder $city) => $city->where('province_id', $provinceId),
            );
        }

        if ($selection['cityId'] !== null) {
            $facilities->where('city_id', $selection['cityId']);
        }

        if ($selection['sector'] !== null) {
            $facilities->where('sector', $selection['sector']->value);
        }

        if ($selection['facilityId'] !== null) {
            $facilities->whereKey($selection['facilityId']);
        }

        return DailyReturn::query()
            ->with(['facility.team', 'facility.city'])
            ->whereIn('facility_id', (clone $facilities)->select('facilities.id'))
            ->whereDate('reported_on', '>=', $selection['period']->from)
            ->whereDate('reported_on', '<=', $selection['period']->to)
            ->get()
            ->map(function (DailyReturn $return): CommandReturnRow {
                $reportedOn = $return->reported_on;

                return new CommandReturnRow(
                    facilityId: $return->facility_id,
                    facilityName: $return->facility->team->name,
                    cityId: $return->facility->city_id,
                    cityName: $return->facility->city->name,
                    reportedOn: $reportedOn instanceof CarbonInterface
                        ? $reportedOn->format('Y-m-d')
                        : (string) $reportedOn,
                    suspects: $return->suspects,
                    tested: $return->tested,
                    positive: $return->positive,
                    negative: $return->negative,
                    startedTreatment: $return->started_treatment,
                    followUp: $return->follow_up,
                    medianTatDays: $return->median_tat_days === null || $return->median_tat_days === ''
                        ? null
                        : (float) $return->median_tat_days,
                );
            })
            ->all();
    }

    private function acceptedProvinceId(Request $request, User $user): ?int
    {
        $provinceId = $request->integer('province');

        if ($provinceId <= 0) {
            return null;
        }

        $cities = City::query()->where('province_id', $provinceId);
        $this->scope->constrainCities($cities, $user);

        return $cities->exists() ? $provinceId : null;
    }

    private function acceptedCityId(Request $request, User $user, ?int $provinceId): ?int
    {
        $cityId = $request->integer('district');

        if ($cityId <= 0 || ! $this->scope->allowsCity($user, $cityId)) {
            return null;
        }

        if ($provinceId !== null && ! City::query()->whereKey($cityId)->where('province_id', $provinceId)->exists()) {
            return null;
        }

        return $cityId;
    }

    private function acceptedSector(Request $request): ?Sector
    {
        $value = $request->string('sector')->toString();

        if ($value === '' || $value === 'all') {
            return null;
        }

        return Sector::tryFrom($value);
    }

    private function acceptedFacilityId(Request $request, User $user): ?int
    {
        $facilityId = $request->integer('facility');

        if ($facilityId <= 0 || ! $this->scope->allowsFacility($user, $facilityId)) {
            return null;
        }

        return $facilityId;
    }
}
