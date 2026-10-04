<?php

namespace App\Http\Controllers\Api;

use App\Enums\Sector;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Support\CommandApi\AiTestsBuilder;
use App\Support\CommandApi\CumulativeBuilder;
use App\Support\CommandApi\DriversBuilder;
use App\Support\CommandApi\FrameBuilder;
use App\Support\CommandApi\IncidenceBuilder;
use App\Support\CommandApi\IntelligenceBuilder;
use App\Support\CommandApi\MapBuilder;
use App\Support\CommandApi\SectorBuilder;
use App\Support\CommandApi\Selection;
use App\Support\CommandApi\SelectionResolver;
use App\Support\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only JSON for the command center pages. One endpoint per page.
 * Every figure is built through DataScope, so a role only receives its own scope.
 */
class CommandApiController extends Controller
{
    public function __construct(private SelectionResolver $resolver) {}

    public function frame(Request $request, FrameBuilder $frame): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond(['scope_name' => $s->scopeName], $frame->build($s, $user));
    }

    public function cumulative(Request $request, CumulativeBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);
        $data = $builder->build($s, $user);
        $hasReturns = $data['has_returns'];
        unset($data['has_returns']);

        return $this->respond($this->meta($s) + ['has_returns' => $hasReturns, 'generated_at' => now()->toIso8601String()], $data);
    }

    public function intelligence(Request $request, IntelligenceBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond($this->meta($s), $builder->build($s, $user));
    }

    public function sector(Request $request, string $sector, SectorBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user, Sector::from($sector), true);
        $sort = $request->string('sort')->toString() ?: 'suspects';
        $direction = $request->string('direction')->toString() ?: 'desc';

        return $this->respond([
            'scope_name' => $s->scopeName,
            'sector' => $sector,
            'filters' => $s->filters(false),
            'sort' => ['key' => $sort, 'direction' => $direction],
        ], $builder->table($s, $user, $sort, $direction));
    }

    public function bmus(Request $request, string $sector, City $city, SectorBuilder $builder, DataScope $scope): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($scope->allowsCity($user, $city->id), 403);

        $s = $this->resolver->resolve($request, $user, Sector::from($sector), true)->withCity($city->id);

        return $this->respond([
            'sector' => $sector,
            'filters' => ['from' => $s->from, 'to' => $s->to],
        ], $builder->bmus($s, $user, $city));
    }

    public function map(Request $request, MapBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);
        $indicator = $request->string('indicator')->toString() ?: 'positivity';
        $selected = $request->integer('selected') ?: null;

        return $this->respond([
            'sector' => $s->sector->value ?? 'all',
            'indicator' => $indicator,
            'filters' => $s->filters(false),
        ], $builder->build($s, $user, $indicator, $selected));
    }

    public function drivers(Request $request, DriversBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond(['filters' => $s->filters()], $builder->build($s, $user));
    }

    public function aiTests(Request $request, AiTestsBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond([
            'filters' => ['sector' => 'all', 'province' => $s->filters()['province'], 'district' => $s->filters()['district']],
        ], $builder->build($s, $user));
    }

    public function incidence(Request $request, IncidenceBuilder $builder, DataScope $scope): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);
        $province = $s->provinceId !== null ? Province::query()->find($s->provinceId) : null;

        if ($province === null) {
            $visible = City::query();
            $scope->constrainCities($visible, $user);
            $province = Province::query()
                ->whereIn('id', $visible->select('cities.province_id'))
                ->orderByRaw("CASE WHEN slug = 'sindh' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->first();
        }

        abort_if($province === null, 404);

        return $this->respond([
            'province' => ['id' => $province->id, 'name' => $province->name],
            'unit' => null,
            'source' => null,
            'y_axis' => ['min' => 0, 'max' => 100],
        ], $builder->build($province));
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(Selection $s): array
    {
        return ['scope_name' => $s->scopeName, 'filters' => $s->filters()];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $data
     */
    private function respond(array $meta, array $data): JsonResponse
    {
        return response()->json(
            $meta === [] ? ['data' => $data] : ['meta' => $meta, 'data' => $data],
            200,
            [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
