<?php

namespace App\Support;

use Carbon\CarbonInterface;

class LastActive
{
    /**
     * @return array{label: string, activeNow: bool}
     */
    public static function describe(?CarbonInterface $seen): array
    {
        if ($seen === null) {
            return [
                'label' => __('entry.not_available'),
                'activeNow' => false,
            ];
        }

        if ($seen->greaterThanOrEqualTo(now()->subMinutes(5))) {
            return [
                'label' => __('entry.active_now'),
                'activeNow' => true,
            ];
        }

        $minutes = (int) abs($seen->diffInMinutes(now()));

        if ($minutes < 60) {
            $count = max(1, $minutes);

            return [
                'label' => trans_choice('entry.minutes_ago', $count, ['count' => $count]),
                'activeNow' => false,
            ];
        }

        $hours = (int) abs($seen->diffInHours(now()));

        if ($hours < 24) {
            return [
                'label' => trans_choice('entry.hours_ago', $hours, ['count' => $hours]),
                'activeNow' => false,
            ];
        }

        if ($seen->isYesterday()) {
            return [
                'label' => __('entry.yesterday'),
                'activeNow' => false,
            ];
        }

        return [
            'label' => $seen->timezone((string) config('app.timezone'))->format('j M Y'),
            'activeNow' => false,
        ];
    }
}
