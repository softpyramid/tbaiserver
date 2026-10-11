<?php

namespace App\Support;

use App\Enums\Sector;
use App\Models\User;
use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache for command center aggregates.
 * The key includes the viewer's scope, so one role never reads another's entry.
 */
final class CommandCache
{
    public const VERSION_KEY = 'cc:version';

    public const TAG = 'command-center';

    /**
     * @param  array<string, mixed>  $selection
     */
    public static function remember(string $endpoint, User $user, array $selection, Closure $build): mixed
    {
        $key = self::key($endpoint, $user, $selection);
        $store = self::supportsTags() ? Cache::tags([self::TAG]) : Cache::store();

        if ($store->has($key)) {
            return $store->get($key);
        }

        $value = $build();
        $store->put($key, $value, self::ttl($selection));

        return $value;
    }

    /**
     * @param  array<string, mixed>  $selection
     */
    public static function key(string $endpoint, User $user, array $selection): string
    {
        $normalised = self::normalise($selection);

        return 'cc:'.self::version().':'.$endpoint.':'.self::scopeHash($user).':'.sha1(json_encode($normalised, JSON_THROW_ON_ERROR));
    }

    /**
     * Five minutes while the period still includes today in Asia/Karachi.
     * Twenty-four hours once the period has closed.
     *
     * @param  array<string, mixed>  $selection
     */
    public static function ttl(array $selection): \DateTimeInterface
    {
        $from = (string) ($selection['from'] ?? '');
        $to = (string) ($selection['to'] ?? '');
        $today = EntryDate::now()->toDateString();
        $open = $from !== '' && $to !== '' && $from <= $today && $today <= $to;

        return $open ? now()->addMinutes(5) : now()->addHours(24);
    }

    public static function scopeHash(User $user): string
    {
        $assignments = $user->programmeAssignments
            ->map(fn ($assignment): array => [
                $assignment->role->value,
                $assignment->scope_type?->value,
                $assignment->scope_id,
            ])
            ->sortBy(fn (array $row): string => implode('|', array_map(
                fn (mixed $part): string => $part === null ? '' : (string) $part,
                $row,
            )))
            ->values()
            ->all();

        if ($assignments !== []) {
            $payload = ['admin' => $user->isTbaiAdmin(), 'assignments' => $assignments];
        } else {
            $teams = $user->teams()->pluck('teams.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $payload = ['admin' => false, 'teams' => $teams];
        }

        return sha1(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function version(): int
    {
        $current = Cache::get(self::VERSION_KEY);

        return is_numeric($current) ? (int) $current : 1;
    }

    public static function bump(): void
    {
        $next = self::version() + 1;
        Cache::forever(self::VERSION_KEY, $next);

        if (self::supportsTags()) {
            Cache::tags([self::TAG])->flush();
        }
    }

    public static function supportsTags(): bool
    {
        return Cache::getStore() instanceof TaggableStore;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private static function normalise(array $selection): array
    {
        $sector = $selection['sector'] ?? null;

        if ($sector instanceof Sector) {
            $sector = $sector->value;
        }

        return [
            'from' => (string) ($selection['from'] ?? ''),
            'to' => (string) ($selection['to'] ?? ''),
            'province' => $selection['province'] ?? null,
            'district' => $selection['district'] ?? null,
            'sector' => $sector,
            'facility' => $selection['facility'] ?? null,
            'sort' => (string) ($selection['sort'] ?? ''),
            'direction' => (string) ($selection['direction'] ?? ''),
            'map_sector' => (string) ($selection['map_sector'] ?? ''),
            'indicator' => (string) ($selection['indicator'] ?? ''),
            'selected' => $selection['selected'] ?? null,
            'requested_province' => $selection['requested_province'] ?? null,
            'requested_district' => $selection['requested_district'] ?? null,
            'requested_sector' => (string) ($selection['requested_sector'] ?? ''),
            'requested_facility' => $selection['requested_facility'] ?? null,
            'resolved_province' => $selection['resolved_province'] ?? null,
            'flags' => [
                'drivers' => (bool) config('tbai.features.drivers'),
                'interventions' => (bool) config('tbai.features.interventions'),
                'incidence' => (bool) config('tbai.features.incidence'),
                'ai_tests' => (bool) config('tbai.features.ai_tests'),
            ],
            'locale' => app()->getLocale(),
        ];
    }
}
