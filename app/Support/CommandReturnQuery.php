<?php

namespace App\Support;

use App\Enums\Sector;
use App\Models\City;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Daily returns inside the viewer's command-center scope, summed by facility and month.
 * Query parameters that point outside that scope are dropped.
 */
class CommandReturnQuery
{
    private ?string $droppedNotice = null;

    /**
     * @var array<string, list<CommandReturnRow>>
     */
    private array $memo = [];

    private ?int $memoRequestId = null;

    public function __construct(private DataScope $scope) {}

    public function notice(): ?string
    {
        return $this->droppedNotice;
    }

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
        $notes = [];
        $requestedProvince = $request->integer('province');
        $provinceId = $this->acceptedProvinceId($request, $user);

        if ($requestedProvince > 0 && $provinceId === null) {
            $notes[] = __('entry.filter_dropped_scope');
        }

        $requestedDistrict = $request->integer('district');
        $cityId = $lockedCityId ?? $this->acceptedCityId($request, $user, $provinceId);

        if ($lockedCityId !== null && $provinceId !== null && ! City::query()->whereKey($lockedCityId)->where('province_id', $provinceId)->exists()) {
            $provinceId = null;
        }

        if ($lockedCityId === null && $requestedDistrict > 0 && $cityId === null) {
            $known = City::query()->whereKey($requestedDistrict)->first(['id', 'province_id']);
            $mismatch = $provinceId !== null && $known !== null && (int) $known->province_id !== $provinceId;

            if ($mismatch) {
                $notes[] = __('entry.filter_dropped_district');
            } elseif (! in_array(__('entry.filter_dropped_scope'), $notes, true)) {
                $notes[] = __('entry.filter_dropped_scope');
            }
        }

        $sector = $lockedSector ?? $this->acceptedSector($request);
        $requestedSector = $request->string('sector')->toString();

        if ($lockedSector === null && $requestedSector !== '' && $requestedSector !== 'all' && $sector === null && ! in_array(__('entry.filter_dropped_scope'), $notes, true)) {
            $notes[] = __('entry.filter_dropped_scope');
        }

        $facilityId = $lockedCityId === null && $lockedSector === null
            ? $this->acceptedFacilityId($request, $user)
            : null;

        if ($lockedCityId === null && $lockedSector === null && $request->integer('facility') > 0 && $facilityId === null && ! in_array(__('entry.filter_dropped_scope'), $notes, true)) {
            $notes[] = __('entry.filter_dropped_scope');
        }

        $this->droppedNotice = $notes === [] ? null : implode(' ', array_values(array_unique($notes)));

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
        $requestId = spl_object_id(request());

        if ($this->memoRequestId !== $requestId) {
            $this->memoRequestId = $requestId;
            $this->memo = [];
        }

        $sector = $selection['sector'] instanceof Sector ? $selection['sector']->value : '';
        $key = implode('|', [
            $user->id,
            $selection['period']->from,
            $selection['period']->to,
            (string) $selection['provinceId'],
            (string) $selection['cityId'],
            $sector,
            (string) $selection['facilityId'],
        ]);

        return $this->memo[$key] ??= $this->aggregate($user, $selection);
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
    private function aggregate(User $user, array $selection): array
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

        $aggregated = DB::table('daily_returns as r')
            ->whereIn('r.facility_id', (clone $facilities)->select('facilities.id'))
            ->where('r.reported_on', '>=', $selection['period']->from)
            ->where('r.reported_on', '<', $selection['period']->endExclusive())
            ->groupBy('r.facility_id')
            ->groupByRaw(match (DB::connection()->getDriverName()) {
                'sqlite' => "strftime('%Y-%m', r.reported_on)",
                'pgsql' => "to_char(r.reported_on, 'YYYY-MM')",
                default => "DATE_FORMAT(r.reported_on, '%Y-%m')",
            })
            ->selectRaw(
                'r.facility_id as facility_id, MIN(r.reported_on) as reported_on,'
                .' COALESCE(SUM(r.suspects),0) as suspects, COALESCE(SUM(r.tested),0) as tested,'
                .' COALESCE(SUM(r.positive),0) as positive, COALESCE(SUM(r.negative),0) as negative,'
                .' COALESCE(SUM(r.started_treatment),0) as started_treatment, COALESCE(SUM(r.follow_up),0) as follow_up,'
                .' COALESCE(SUM(CASE WHEN r.median_tat_days IS NOT NULL AND r.tested > 0 THEN r.median_tat_days * r.tested ELSE 0 END),0) as tat_weighted,'
                .' COALESCE(SUM(CASE WHEN r.median_tat_days IS NOT NULL AND r.tested > 0 THEN r.tested ELSE 0 END),0) as tat_weight,'
                .' COUNT(*) as return_count'
            )
            ->get();

        if ($aggregated->isEmpty()) {
            return [];
        }

        $lookup = Facility::query()
            ->with(['team', 'city'])
            ->whereIn('id', $aggregated->pluck('facility_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($aggregated as $row) {
            $facility = $lookup->get($row->facility_id);

            if ($facility === null) {
                continue;
            }

            $rows[] = new CommandReturnRow(
                facilityId: (int) $row->facility_id,
                facilityName: $facility->team->name,
                cityId: (int) $facility->city_id,
                cityName: $facility->city->name,
                reportedOn: substr((string) $row->reported_on, 0, 10),
                suspects: (int) $row->suspects,
                tested: (int) $row->tested,
                positive: (int) $row->positive,
                negative: (int) $row->negative,
                startedTreatment: (int) $row->started_treatment,
                followUp: (int) $row->follow_up,
                tatWeighted: (float) $row->tat_weighted,
                tatWeight: (float) $row->tat_weight,
                returnCount: (int) $row->return_count,
            );
        }

        return $rows;
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
