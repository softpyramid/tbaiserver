<?php

namespace App\Support\CommandApi;

use App\Models\City;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Support\Facades\DB;

/**
 * The four invented example patients, limited to the districts the viewer may see.
 */
final class AiTestsBuilder
{
    public const LEVELS = ['high' => 70, 'moderate' => 40, 'low' => 0];

    public function __construct(private DataScope $scope) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user): array
    {
        $visible = City::query();
        $this->scope->constrainCities($visible, $user);

        if ($s->cityId !== null) {
            $visible->where('cities.id', $s->cityId);
        } elseif ($s->provinceId !== null) {
            $visible->where('cities.province_id', $s->provinceId);
        }

        $ids = $visible->pluck('cities.id')->all();
        $rows = DB::table('ai_test_fixtures')
            ->whereIn('city_id', $ids)
            ->orderBy('sort')
            ->get();

        $patients = $rows->map(fn (object $r): array => [
            'id' => $r->code,
            'summary' => $r->summary,
            'location' => $r->location,
            'symptoms' => $r->symptoms,
            'probability' => (int) $r->probability,
            'laboratory' => $r->laboratory,
            'history' => $r->history,
            'city_id' => (int) $r->city_id,
            'level' => $this->level((int) $r->probability),
        ])->values()->all();

        return [
            'patients' => $patients,
            'levels' => array_map(fn (int $min): array => ['min' => $min], self::LEVELS),
            'default_patient' => $patients[0]['id'] ?? null,
        ];
    }

    private function level(int $probability): string
    {
        foreach (self::LEVELS as $name => $min) {
            if ($probability >= $min) {
                return $name;
            }
        }

        return 'low';
    }
}
