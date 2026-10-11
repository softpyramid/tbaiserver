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
use App\Support\CommandCache;
use App\Support\DataScope;
use Closure;
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

        return $this->respond(['scope_name' => $s->scopeName], $this->cached('frame', $request, $user, $s, fn () => $frame->build($s, $user)));
    }

    public function cumulative(Request $request, CumulativeBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);
        $data = $this->cached('cumulative', $request, $user, $s, fn () => $builder->build($s, $user));
        $hasReturns = $data['has_returns'];
        unset($data['has_returns']);

        return $this->respond($this->meta($s) + ['has_returns' => $hasReturns, 'generated_at' => now()->toIso8601String()], $data);
    }

    public function intelligence(Request $request, IntelligenceBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond($this->meta($s), $this->cached('intelligence', $request, $user, $s, fn () => $builder->build($s, $user)));
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
        ], $this->cached('sector', $request, $user, $s, fn () => $builder->table($s, $user, $sort, $direction)));
    }

    public function bmus(Request $request, string $sector, City $city, SectorBuilder $builder, DataScope $scope): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($scope->allowsCity($user, $city->id), 403);

        $s = $this->resolver->resolve($request, $user, Sector::from($sector), true)->withCity($city->id);

        return $this->respond([
            'sector' => $sector,
            'filters' => ['from' => $s->from, 'to' => $s->to],
        ], $this->cached('bmus', $request, $user, $s, fn () => $builder->bmus($s, $user, $city)));
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
        ], $this->cached('map', $request, $user, $s, fn () => $builder->build($s, $user, $indicator, $selected)));
    }

    public function drivers(Request $request, DriversBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond(['filters' => $s->filters()], $this->cached('drivers', $request, $user, $s, fn () => $builder->build($s, $user)));
    }

    public function aiTests(Request $request, AiTestsBuilder $builder): JsonResponse
    {
        $user = $this->user($request);
        $s = $this->resolver->resolve($request, $user);

        return $this->respond([
            'filters' => ['sector' => 'all', 'province' => $s->filters()['province'], 'district' => $s->filters()['district']],
        ], $this->cached('ai-tests', $request, $user, $s, fn () => $builder->build($s, $user)));
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

        $data = $this->cached('incidence', $request, $user, $s, fn () => $builder->build($province), [
            'resolved_province' => $province->id,
        ]);
        $values = array_filter(array_merge(
            array_column($data['yearly'], 'estimate'),
            array_column($data['yearly'], 'target'),
        ));
        $max = $values === [] ? 100 : ceil(max(100, max($values)) / 50) * 50;

        return $this->respond([
            'province' => ['id' => $province->id, 'name' => $province->name],
            'unit' => __('entry.notification_per_100k'),
            'source' => null,
            'y_axis' => ['min' => 0, 'max' => (int) $max],
        ], $data);
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

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function cached(string $endpoint, Request $request, User $user, Selection $s, Closure $build, array $extra = []): array
    {
        $value = CommandCache::remember($endpoint, $user, [
            'from' => $s->from,
            'to' => $s->to,
            'province' => $s->provinceId,
            'district' => $s->cityId,
            'sector' => $s->sector === null ? null : $s->sector->value,
            'sort' => $request->string('sort')->toString(),
            'direction' => $request->string('direction')->toString(),
            'map_sector' => $request->string('sector')->toString(),
            'indicator' => $request->string('indicator')->toString(),
            'selected' => $request->integer('selected') ?: null,
        ] + $extra, $build);

        if (! is_array($value)) {
            throw new \RuntimeException('The command cache did not return a payload.');
        }

        return $value;
    }
}
