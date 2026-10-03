<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IntendedUrl
{
    /**
     * Drop a remembered "intended" URL the person may not open.
     *
     * A guest who was bounced to the login page keeps the page they asked for.
     * That page can be stale: the last person on a shared device may have been
     * an admin. After sign-in the person should land on their own role home,
     * not on a 403 or a page that belongs to someone else.
     */
    public function discardIfNotPermitted(Request $request, User $user): void
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && ! $this->permits($user, $intended)) {
            $request->session()->forget('url.intended');
        }
    }

    public function permits(User $user, string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if ($host !== null && $host !== request()->getHost()) {
            return false;
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');

        try {
            $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpException) {
            return false;
        }

        if ($route->isFallback) {
            return false;
        }

        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $this->routeIsOpenTo($user, $route, $path);
    }

    private function routeIsOpenTo(User $user, Route $route, string $path): bool
    {
        if (str_starts_with(ltrim($path, '/'), 'admin')) {
            return false;
        }

        $name = (string) $route->getName();

        if (str_starts_with($name, 'command.')) {
            return Gate::forUser($user)->allows('viewCommandCenter');
        }

        $slug = $route->parameter('current_team');

        if (! is_string($slug)) {
            return true;
        }

        $team = Team::query()->where('slug', $slug)->first();

        if (! $team instanceof Team || $team->facility === null || ! $user->belongsToTeam($team)) {
            return false;
        }

        return match (true) {
            $name === 'returns.history' => Gate::forUser($user)->allows('viewReturns', $team),
            in_array($name, ['facilities.members', 'facilities.invites', 'facilities.roles', 'facilities.activity'], true) => Gate::forUser($user)->allows('viewRoster', $team),
            $name === 'facilities.edit' => Gate::forUser($user)->allows('openProfile', $team->facility),
            default => true,
        };
    }
}
