<?php

namespace App\Support;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceResponse
{
    /**
     * @param  list<string>  $allowed
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function sort(Request $request, array $allowed, string $default, string $defaultDirection = 'asc'): array
    {
        $sort = $request->string('sort')->toString();

        if (! in_array($sort, $allowed, true)) {
            $sort = $default;
        }

        $requested = $request->string('direction')->toString();
        $fallback = $defaultDirection === 'desc' ? 'desc' : 'asc';
        $direction = match ($requested) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $fallback,
        };

        return [$sort, $direction];
    }

    public static function like(string $value): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%';
    }

    public static function exportUrl(Request $request): string
    {
        return $request->fullUrlWithQuery([
            'export' => 'csv',
            'page' => null,
        ]);
    }

    public static function line(string $key): string
    {
        $translated = __($key);

        return is_string($translated) ? $translated : $key;
    }

    public static function wantsCsv(Request $request): bool
    {
        return $request->query('export') === 'csv';
    }

    public static function date(Request $request, string $key): ?string
    {
        $value = $request->string($key)->toString();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * @param  list<array{key: string, label: string}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $filters
     * @param  array{title?: string, description?: string, tabs?: list<array<string, mixed>>, tiles?: list<array<string, mixed>>, empty?: array{title: string, body: string}}  $shell  Page shell extras: purpose line, tab strip, summary tiles, and an empty state.
     */
    public static function page(
        string $title,
        array $columns,
        array $rows,
        array $filters,
        string $sort,
        string $direction,
        ?int $from,
        ?int $to,
        int $total,
        ?string $previousUrl,
        ?string $nextUrl,
        ?string $createUrl,
        ?string $createLabel,
        ?string $exportUrl,
        array $shell = [],
    ): Response {
        return Inertia::render('resources/index', [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'showing' => $total === 0
                ? ''
                : __('entry.showing_rows', [
                    'from' => $from ?? 0,
                    'to' => $to ?? 0,
                    'total' => $total,
                ]),
            'previousUrl' => $previousUrl,
            'nextUrl' => $nextUrl,
            'createUrl' => $createUrl,
            'createLabel' => $createLabel,
            'exportUrl' => $exportUrl,
            'labels' => [
                'export' => __('entry.export_csv'),
                'apply' => __('entry.apply'),
                'clear' => __('entry.clear'),
                'search' => __('entry.search'),
                'previous' => __('entry.previous_page'),
                'next' => __('entry.next_page'),
                'empty' => __('entry.no_rows'),
                'sort' => __('entry.sort'),
            ],
            'status' => session('status'),
            'heading' => $shell['title'] ?? null,
            'description' => $shell['description'] ?? null,
            'tabs' => $shell['tabs'] ?? [],
            'tiles' => $shell['tiles'] ?? [],
            'empty' => $shell['empty'] ?? null,
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (string|int|float|null $value): string => $value === null ? '' : (string) $value,
                    $row,
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
