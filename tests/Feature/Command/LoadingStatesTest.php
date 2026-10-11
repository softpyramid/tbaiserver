<?php

use App\Enums\ProgrammeRole;
use App\Models\ProgrammeAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The loading states are front end only. These guard that the pages still
 * render with the same Inertia component and props, and that the shared
 * loading pieces keep their copy and wiring.
 */

function loadingStatesAdmin(): User
{
    $admin = User::factory()->create();
    ProgrammeAssignment::factory()->create(['user_id' => $admin->id, 'role' => ProgrammeRole::TbaiAdmin]);

    return $admin;
}

dataset('classic command pages', [
    'overview' => ['command.index', 'command/index', [
        'scope', 'screens', 'filters', 'options', 'show', 'labels', 'links', 'tat', 'title',
        'totals', 'kpis', 'cascade', 'months', 'notification', 'estimate', 'hasReturns', 'notCollected', 'attention',
    ]],
    'map' => ['command.map', 'command/map', [
        'scope', 'screens', 'filters', 'options', 'show', 'labels', 'links', 'tat', 'title',
        'boundaries', 'attribution', 'geometryUrl', 'indicator', 'indicatorLabel', 'indicators', 'districts',
        'hiddenMatches', 'hiddenNote', 'top', 'selectedId', 'trendHeading', 'topHeading', 'chooseDistrict',
        'listHeading', 'indicatorField',
    ]],
    'alerts' => ['command.alerts', 'command/alerts', [
        'scope', 'screens', 'filters', 'options', 'show', 'labels', 'links', 'tat', 'title',
        'badge', 'canEdit', 'rules', 'alerts', 'metricOptions', 'comparatorOptions', 'alertLabels',
    ]],
]);

test('classic command pages still render with the same component and props', function (string $route, string $component, array $props) {
    $this->actingAs(loadingStatesAdmin())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->hasAll($props)
            ->has('labels.apply')
            ->has('labels.clear'));
})->with('classic command pages');

test('the full-screen command center still renders with the same props', function () {
    $this->actingAs(loadingStatesAdmin())
        ->get(route('command.center'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('command/center')
            ->where('backUrl', route('command.index'))
            ->where('backLabel', __('entry.back_to_app')));
});

test('the loading copy and timeout live in one place', function () {
    $source = (string) file_get_contents(resource_path('js/lib/loading.ts'));

    expect($source)
        ->toContain("LOADING_TEXT = 'Loading...'")
        ->toContain("LOADING_MAP_TEXT = 'Loading map data...'")
        ->toContain("LOAD_ERROR_TEXT = 'Could not load.'")
        ->toContain("RETRY_TEXT = 'Retry'")
        ->toContain('PENDING_TIMEOUT_MS = 20_000');
});

test('the progress bar waits 150 ms and keeps the brand colour', function () {
    $app = (string) file_get_contents(resource_path('js/app.tsx'));

    expect($app)->toContain("color: '#29269b'")
        ->and($app)->toContain('delay: 150');
});

test('classic pages dim their panels and disable the filter buttons while a visit is pending', function () {
    $shell = (string) file_get_contents(resource_path('js/components/command-center.tsx'));

    expect($shell)
        ->toContain('useVisitPending()')
        ->toContain('<PanelLoading')
        ->toContain('onRetry={visit.retry}')
        ->and(substr_count($shell, 'disabled={pending}'))->toBe(2);
});

test('the loading components stay accessible and respect reduced motion', function () {
    $panel = (string) file_get_contents(resource_path('js/components/panel-loading.tsx'));
    $spinner = (string) file_get_contents(resource_path('js/components/spinner.tsx'));
    $skeleton = (string) file_get_contents(resource_path('js/components/skeleton.tsx'));

    expect($panel)
        ->toContain('aria-busy={pending}')
        ->toContain('role="status"')
        ->toContain('aria-live="polite"')
        ->toContain('type="button"')
        ->toContain('absolute inset-0');

    expect($spinner)->toContain('motion-reduce:animate-none')
        ->and($skeleton)->toContain('motion-reduce:animate-none');
});

test('the new loading files keep to the copy rules', function () {
    $files = [
        resource_path('js/lib/loading.ts'),
        resource_path('js/hooks/use-visit-pending.ts'),
        resource_path('js/components/spinner.tsx'),
        resource_path('js/components/skeleton.tsx'),
        resource_path('js/components/panel-loading.tsx'),
        resource_path('js/command-center/skeletons.tsx'),
    ];

    foreach ($files as $file) {
        expect($file)->toBeFile();

        $source = (string) file_get_contents($file);

        expect($source)
            ->not->toContain("\u{2014}", "{$file} has an em dash")
            ->not->toContain("\u{2013}", "{$file} has an en dash");

        expect(preg_match('/:\s*any\b|<any>|\bas any\b/', $source))->toBe(0, "{$file} uses any");
    }
});
