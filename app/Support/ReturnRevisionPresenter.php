<?php

namespace App\Support;

use App\Models\DailyReturn;
use App\Models\DailyReturnRevision;

class ReturnRevisionPresenter
{
    /**
     * @return list<array{when: string, who: string, field: string, old: string, new: string}>
     */
    public function lines(DailyReturn $return): array
    {
        $return->loadMissing(['revisions.editor']);

        $lines = [];

        foreach ($return->revisions->sortByDesc(fn (DailyReturnRevision $revision): string => $revision->changed_at->format('Y-m-d H:i:s').$revision->id) as $revision) {
            foreach (DailyReturn::TRACKED as $field) {
                $from = $revision->old_values[$field] ?? null;
                $to = $revision->new_values[$field] ?? null;

                if ($from === $to) {
                    continue;
                }

                $lines[] = [
                    'when' => $revision->changed_at
                        ->timezone(EntryDate::timezone())
                        ->format('j M Y, H:i'),
                    'who' => $revision->editor->name,
                    'field' => $this->fieldLabel($field),
                    'old' => $this->display($from),
                    'new' => $this->display($to),
                ];
            }
        }

        return $lines;
    }

    /**
     * @return list<array{when: string, who: string, field: string, old: string, new: string}>
     */
    public function changeLines(DailyReturnRevision $revision): array
    {
        $revision->loadMissing('editor');

        $when = $revision->changed_at
            ->timezone(EntryDate::timezone())
            ->format('j M Y, H:i');
        $who = $revision->editor->name;
        $lines = [];

        foreach (DailyReturn::TRACKED as $field) {
            $from = $revision->old_values[$field] ?? null;
            $to = $revision->new_values[$field] ?? null;

            if ($from === $to) {
                continue;
            }

            $lines[] = [
                'when' => $when,
                'who' => $who,
                'field' => $this->fieldLabel($field),
                'old' => $this->display($from),
                'new' => $this->display($to),
            ];
        }

        return $lines;
    }

    public function positivity(int $positive, int $tested): string
    {
        if ($tested === 0) {
            return __('entry.not_available');
        }

        $percent = round(($positive / $tested) * 100, 1);
        $formatted = number_format($percent, 1, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return $trimmed.'%';
    }

    private function fieldLabel(string $field): string
    {
        $key = match ($field) {
            'suspects' => 'field_suspects',
            'tested' => 'field_tested',
            'positive' => 'field_positive',
            'negative' => 'field_negative',
            'started_treatment' => 'field_started_treatment',
            'follow_up' => 'field_follow_up',
            'median_tat_days' => 'field_median_tat_days',
            default => null,
        };

        if ($key === null) {
            return $field;
        }

        return __('entry.'.$key);
    }

    private function display(int|float|null $value): string
    {
        if ($value === null) {
            return __('entry.empty');
        }

        return (string) $value;
    }
}
