<?php

namespace App\Support\CommandApi;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Support\DataScope;

final class FrameBuilder
{
    public function __construct(private DataScope $scope) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Selection $s, User $user): array
    {
        $flags = [
            'drivers' => (bool) config('tbai.features.drivers'),
            'interventions' => (bool) config('tbai.features.interventions'),
            'incidence' => (bool) config('tbai.features.incidence'),
            'ai_tests' => (bool) config('tbai.features.ai_tests'),
        ];

        return [
            'title' => 'TB-AI Sindh Govt. Super Intelligence Command Center',
            'scope' => [
                'label' => $s->locks->level === 'admin' ? $this->scope->label($user) : $s->scopeName,
                'level' => $s->locks->level,
                'province_id' => $s->locks->provinceId,
                'district_id' => $s->locks->districtId,
                'locks' => [
                    'province' => $s->locks->provinceLocked(),
                    'district' => $s->locks->districtLocked(),
                ],
            ],
            'screens' => $this->screens($flags),
            'flags' => $flags,
            'filters' => $s->filters(),
            'options' => [
                'provinces' => $this->provinces($user),
                'districts' => $this->districts($s, $user),
                'sectors' => [
                    ['value' => 'all', 'label' => 'All'],
                    ['value' => 'public', 'label' => 'Public'],
                    ['value' => 'private', 'label' => 'Private'],
                    ['value' => 'ppm', 'label' => 'PPM'],
                ],
            ],
            'months' => Labels::months($s->months()),
            'assets' => [
                'base' => '/cc-assets/',
                'backgrounds' => ['default' => 'bg-default.jpg', 'lab' => 'bg-lab.jpg', 'ajrak' => 'bg-ajrak.jpg'],
                'logos' => ['sindh_govt' => 'logo-sindh-govt.png', 'tbai' => 'logo-tbai.png'],
                'webp' => true,
            ],
            'geometry' => [
                'url' => '/geo/sindh-districts.geojson',
                'match_property' => 'match',
                'attribution' => 'District boundaries: OCHA COD-AB, CC BY-IGO, simplified. Indicative only.',
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $flags
     * @return list<array<string, mixed>>
     */
    private function screens(array $flags): array
    {
        $all = [
            [1, 'cumulative', 'Cumulative Data', ['Cumulative Data'], '/command', 'lab', 'default', null],
            [2, 'intelligence', 'Intelligence Engine', ['Intelligence Engine'], '/command/alerts', 'ajrak', 'alt', null],
            [3, 'public', 'Public Registered', ['Public Registered'], '/command/sectors/public', 'ajrak', 'alt', null],
            [4, 'private', 'Private Registered', ['Private Registered'], '/command/sectors/private', 'ajrak', 'default', null],
            [5, 'ppm', 'PPM Registered', ['PPM Registered'], '/command/sectors/ppm', 'ajrak', 'default', null],
            [6, 'drivers', 'TB Drivers & Intervention Intelligence', ['TB Drivers & Intervention', 'Intelligence'], '/command/drivers', 'ajrak', 'default', 'drivers'],
            [7, 'map', 'Geospatial Heatmapping', ['Geospatial Heatmapping'], '/command/map', 'ajrak', 'default', null],
            [8, 'ai_tests', 'AI Assisted Testing', ['AI Assisted Testing'], '/command/ai-tests', 'ajrak', 'default', 'ai_tests'],
            [9, 'incidence', 'Incidence', ['Incidence'], '/command/incidence', 'ajrak', 'default', 'incidence'],
        ];
        $api = ['cumulative' => 'cumulative', 'intelligence' => 'intelligence', 'public' => 'sectors/public', 'private' => 'sectors/private', 'ppm' => 'sectors/ppm', 'drivers' => 'drivers', 'map' => 'map', 'ai_tests' => 'ai-tests', 'incidence' => 'incidence'];
        $screens = [];

        foreach ($all as [$number, $key, $label, $lines, $page, $background, $accent, $flag]) {
            if ($flag !== null && ! ($flags[$flag] ?? false)) {
                continue;
            }

            $screens[] = [
                'number' => $number,
                'key' => $key,
                'label' => $label,
                'label_lines' => $lines,
                'page_url' => $page,
                'api_url' => '/api/command/'.$api[$key],
                'background' => $background,
                'accent' => $accent,
            ];
        }

        return $screens;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function provinces(User $user): array
    {
        $ids = City::query();
        $this->scope->constrainCities($ids, $user);

        return array_values(Province::query()
            ->whereIn('id', $ids->select('cities.province_id'))
            ->orderBy('name')
            ->get()
            ->map(fn (Province $p): array => ['value' => (string) $p->id, 'label' => $p->name])
            ->all());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function districts(Selection $s, User $user): array
    {
        $query = City::query()->orderBy('name');
        $this->scope->constrainCities($query, $user);

        if ($s->provinceId !== null) {
            $query->where('cities.province_id', $s->provinceId);
        }

        return array_values($query->get()
            ->map(fn (City $c): array => ['value' => (string) $c->id, 'label' => $c->name])
            ->all());
    }
}
