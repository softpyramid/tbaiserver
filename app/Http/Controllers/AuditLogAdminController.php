<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\EntryDate;
use App\Support\ResourceResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogAdminController extends Controller
{
    /**
     * Read-only list of recorded actions, newest first.
     */
    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('administer');

        [$sort, $direction] = ResourceResponse::sort($request, ['when'], 'when', 'desc');

        $query = AuditLog::query()
            ->with('actor')
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction);

        $search = trim($request->string('q')->toString());

        if ($search !== '') {
            $like = ResourceResponse::like($search);
            $query->where(function (Builder $inner) use ($like): void {
                $inner->where('action', 'like', $like)
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', $like));
            });
        }

        $columns = [
            ['key' => 'when', 'label' => __('entry.column_when')],
            ['key' => 'who', 'label' => __('entry.column_who')],
            ['key' => 'action', 'label' => __('entry.column_action')],
            ['key' => 'subject', 'label' => __('entry.column_subject')],
        ];

        $cells = fn (AuditLog $log): array => [
            'when' => EntryDate::dateTime($log->created_at),
            'who' => $log->actor->name ?? __('entry.not_available'),
            'action' => $log->action,
            'subject' => $log->subject_type === null
                ? __('entry.not_available')
                : class_basename($log->subject_type).' #'.$log->subject_id,
        ];

        if (ResourceResponse::wantsCsv($request)) {
            return ResourceResponse::csv(
                'audit-log.csv',
                array_column($columns, 'label'),
                $query->lazy(200)->map(fn (AuditLog $log): array => array_values($cells($log))),
            );
        }

        $page = $query->paginate(20)->withQueryString();

        return ResourceResponse::page(
            title: __('entry.nav_audit_log'),
            columns: $columns,
            rows: $page->getCollection()->map(fn (AuditLog $log): array => [
                'id' => (string) $log->id,
                'href' => null,
                'cells' => $cells($log),
                'controls' => [],
            ])->values()->all(),
            filters: [[
                'key' => 'q',
                'label' => __('entry.search'),
                'type' => 'search',
                'value' => $search,
            ]],
            sort: $sort,
            direction: $direction,
            from: $page->firstItem(),
            to: $page->lastItem(),
            total: $page->total(),
            previousUrl: $page->previousPageUrl(),
            nextUrl: $page->nextPageUrl(),
            createUrl: null,
            createLabel: null,
            exportUrl: ResourceResponse::exportUrl($request),
            shell: [
                'description' => __('entry.audit_log_description'),
                'empty' => [
                    'title' => __('entry.audit_log_empty_title'),
                    'body' => __('entry.audit_log_empty_body'),
                ],
            ],
        );
    }
}
