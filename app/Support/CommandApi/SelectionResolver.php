<?php

namespace App\Support\CommandApi;

use App\Enums\ProgrammeRole;
use App\Enums\Sector;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Support\CommandPeriod;
use App\Support\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SelectionResolver
{
    public const INDICATORS = ['suspects', 'positivity', 'median_tat', 'lost_to_follow_up', 'treatment_gap', 'testing_coverage', 'notification_rate', 'ai_tests'];

    public const SORT_KEYS = ['district', 'suspects', 'tested', 'positive', 'positivity', 'started_pct', 'success_pct', 'ltfu_pct', 'tat_days', 'mdr_rr', 'ai_tests', 'bmus'];

    public function __construct(private DataScope $scope) {}

    /**
     * @param  ?Sector  $forcedSector  Set by the path on sector pages; the query parameter is ignored then.
     *
     * @throws ValidationException
     */
    public function resolve(Request $request, User $user, ?Sector $forcedSector = null, bool $forceSector = false): Selection
    {
        Validator::make($request->query(), [
            'sector' => ['nullable', 'in:all,public,private,ppm'],
            'province' => ['nullable', 'regex:/^(all|\d+)$/'],
            'district' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'indicator' => ['nullable', 'in:'.implode(',', self::INDICATORS)],
            'selected' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORT_KEYS)],
            'direction' => ['nullable', 'in:asc,desc'],
        ])->validate();

        $locks = $this->locks($user);
        $period = CommandPeriod::fromRequest($request);
        $provinceId = $this->province($request, $user, $locks);
        $cityId = $this->city($request, $user, $locks, $provinceId);

        if ($cityId !== null && $provinceId === null) {
            $provinceId = City::query()->whereKey($cityId)->value('province_id');
        }

        $sector = $forceSector ? $forcedSector : $this->sector($request);
        $provinceName = $provinceId === null ? null : Province::query()->whereKey($provinceId)->value('name');
        $scopeName = $cityId !== null
            ? (string) City::query()->whereKey($cityId)->value('name')
            : ($provinceName ?? $this->scope->label($user));

        return new Selection($sector, $provinceId, $cityId, $period->from, $period->to, $scopeName, $provinceName, $locks);
    }

    public function locks(User $user): ScopeLocks
    {
        if ($user->isTbaiAdmin()) {
            return new ScopeLocks('admin', null, null);
        }

        $assignments = $user->programmeAssignments;

        if ($assignments->contains(fn ($a) => $a->role === ProgrammeRole::CountryViewer)) {
            return new ScopeLocks('country', null, null);
        }

        $provinces = $assignments->filter(fn ($a) => $a->role === ProgrammeRole::ProvincialViewer);

        if ($provinces->isNotEmpty()) {
            return new ScopeLocks('province', $provinces->count() === 1 ? $provinces->first()->scope_id : null, null);
        }

        $cities = $assignments->filter(fn ($a) => $a->role === ProgrammeRole::CityOfficer);

        if ($cities->isNotEmpty()) {
            $cityId = $cities->count() === 1 ? $cities->first()->scope_id : null;
            $provinceId = $cityId === null ? null : City::query()->whereKey($cityId)->value('province_id');

            return new ScopeLocks($cities->count() === 1 ? 'district' : 'province', $provinceId, $cityId);
        }

        return new ScopeLocks('admin', null, null);
    }

    private function province(Request $request, User $user, ScopeLocks $locks): ?int
    {
        if ($locks->provinceLocked() && $locks->provinceId !== null) {
            return $locks->provinceId;
        }

        $value = $request->query('province');

        if ($value === 'all') {
            return null;
        }

        if (is_string($value) && ctype_digit($value) && $this->provinceVisible($user, (int) $value)) {
            return (int) $value;
        }

        $sindh = Province::query()->where('slug', 'sindh')->value('id');

        return $sindh !== null && $this->provinceVisible($user, (int) $sindh) ? (int) $sindh : null;
    }

    private function provinceVisible(User $user, int $provinceId): bool
    {
        $cities = City::query()->where('province_id', $provinceId);
        $this->scope->constrainCities($cities, $user);

        return $cities->exists();
    }

    private function city(Request $request, User $user, ScopeLocks $locks, ?int $provinceId): ?int
    {
        if ($locks->districtLocked() && $locks->districtId !== null) {
            return $locks->districtId;
        }

        $value = $request->query('district');

        if (! is_string($value)) {
            return null;
        }

        $cityId = (int) $value;

        if ($cityId <= 0 || ! $this->scope->allowsCity($user, $cityId)) {
            return null;
        }

        if ($provinceId !== null && ! City::query()->whereKey($cityId)->where('province_id', $provinceId)->exists()) {
            return null;
        }

        return $cityId;
    }

    private function sector(Request $request): ?Sector
    {
        $value = $request->query('sector');

        return is_string($value) ? Sector::tryFrom($value) : null;
    }
}
