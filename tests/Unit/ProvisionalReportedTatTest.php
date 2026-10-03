<?php

use App\Models\DailyReturn;
use App\Support\ProvisionalReportedTat;
use Tests\TestCase;

uses(TestCase::class);

test('provisional tat is the average of reported medians weighted by tested count', function () {
    $average = ProvisionalReportedTat::average([
        new DailyReturn(['median_tat_days' => '2', 'tested' => 10]),
        new DailyReturn(['median_tat_days' => '4', 'tested' => 30]),
        new DailyReturn(['median_tat_days' => null, 'tested' => 100]),
        new DailyReturn(['median_tat_days' => '9', 'tested' => 0]),
    ]);

    expect($average)->toBe(3.5)
        ->and(ProvisionalReportedTat::LABEL)->toBe(
            'Average of reported median TAT (days), weighted by tested count',
        );
});

test('provisional tat is empty when nothing was tested', function () {
    expect(ProvisionalReportedTat::average([
        new DailyReturn(['median_tat_days' => null, 'tested' => 4]),
    ]))->toBeNull();
});
