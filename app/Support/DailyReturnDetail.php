<?php

namespace App\Support;

use App\Models\DailyReturn;
use Inertia\Inertia;
use Inertia\Response;

class DailyReturnDetail
{
    public function __construct(private ReturnRevisionPresenter $revisions) {}

    public function render(DailyReturn $return): Response
    {
        $return->loadMissing([
            'facility.team',
            'facility.city.province',
            'submitter',
            'revisions.editor',
        ]);

        $latest = $return->revisions->sortByDesc('id')->first();

        return Inertia::render('resources/show', [
            'title' => __('entry.detail_title'),
            'summary' => [
                ['label' => __('entry.column_date'), 'value' => EntryDate::day($return->reported_on)],
                ['label' => __('entry.column_facility'), 'value' => $return->facility->team->name],
                ['label' => __('entry.city'), 'value' => $return->facility->city->name],
                ['label' => __('entry.province'), 'value' => $return->facility->city->province->name],
                ['label' => __('entry.column_suspects'), 'value' => (string) $return->suspects],
                ['label' => __('entry.column_tested'), 'value' => (string) $return->tested],
                ['label' => __('entry.column_positive'), 'value' => (string) $return->positive],
                ['label' => __('entry.column_negative'), 'value' => (string) $return->negative],
                ['label' => __('entry.column_started'), 'value' => (string) $return->started_treatment],
                ['label' => __('entry.column_follow_up'), 'value' => (string) $return->follow_up],
                ['label' => __('entry.column_tat'), 'value' => $return->median_tat_days === null ? __('entry.not_available') : (string) $return->median_tat_days],
                ['label' => __('entry.column_positivity'), 'value' => $this->revisions->positivity($return->positive, $return->tested)],
                ['label' => __('entry.column_edited_by'), 'value' => $latest?->editor === null ? $return->submitter->name : $latest->editor->name],
            ],
            'historyTitle' => __('entry.history'),
            'historyEmpty' => __('entry.no_updates'),
            'historyColumns' => [
                ['key' => 'when', 'label' => __('entry.column_when')],
                ['key' => 'who', 'label' => __('entry.column_who')],
                ['key' => 'field', 'label' => __('entry.column_field')],
                ['key' => 'old', 'label' => __('entry.column_old')],
                ['key' => 'new', 'label' => __('entry.column_new')],
            ],
            'history' => $this->revisions->lines($return),
        ]);
    }
}
