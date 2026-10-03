<?php

use App\Models\DailyReturn;
use App\Support\CommandPeriod;
use App\Support\CommandReturnRow;
use App\Support\CommandTotals;
use App\Support\ProvisionalReportedTat;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('command totals sum counts and skip a fake positivity when nothing was tested', function () {
    $summary = CommandTotals::summarize([
        commandReturnRow(facilityId: 1, suspects: 10, tested: 0, positive: 0, negative: 0, started: 0, followUp: 2),
        commandReturnRow(facilityId: 1, suspects: 5, tested: 4, positive: 1, negative: 3, started: 4, followUp: 1, on: '2026-02-01'),
    ]);

    expect($summary['suspects'])->toBe(15)
        ->and($summary['tested'])->toBe(4)
        ->and($summary['positive'])->toBe(1)
        ->and($summary['negative'])->toBe(3)
        ->and($summary['started_treatment'])->toBe(4)
        ->and($summary['follow_up'])->toBe(3)
        ->and($summary['positivity'])->toBe(0.25)
        ->and($summary['treatment_gap'])->toBe(-3)
        ->and($summary['active_facilities'])->toBe(1)
        ->and(CommandTotals::positivity(3, 0))->toBeNull();
});

test('active facilities counts each facility with a return once', function () {
    $summary = CommandTotals::summarize([
        commandReturnRow(facilityId: 1),
        commandReturnRow(facilityId: 1, on: '2026-01-02'),
        commandReturnRow(facilityId: 2, on: '2026-01-03'),
    ]);

    expect($summary['active_facilities'])->toBe(2);
});

test('positivity is positive divided by tested', function () {
    expect(CommandTotals::positivity(1, 4))->toBe(0.25)
        ->and(CommandTotals::treatmentGap(9, 4))->toBe(5);
});

test('monthly running totals carry forward through empty months and ignore rows outside the range', function () {
    $period = new CommandPeriod('2026-01-01', '2026-03-31');
    $months = CommandTotals::runningMonths([
        commandReturnRow(suspects: 10, tested: 8, positive: 2, started: 1, on: '2026-01-15'),
        commandReturnRow(suspects: 5, tested: 4, positive: 1, started: 1, on: '2026-03-02'),
        commandReturnRow(suspects: 100, tested: 100, positive: 100, started: 100, on: '2025-12-31'),
    ], $period);

    expect($months)->toHaveCount(3)
        ->and($months[0])->toMatchArray([
            'key' => '2026-01',
            'label' => 'Jan 2026',
            'suspects' => 10,
            'tested' => 8,
            'positive' => 2,
            'started_treatment' => 1,
        ])
        ->and($months[1])->toMatchArray([
            'key' => '2026-02',
            'suspects' => 10,
            'tested' => 8,
            'positive' => 2,
            'started_treatment' => 1,
        ])
        ->and($months[2])->toMatchArray([
            'key' => '2026-03',
            'suspects' => 15,
            'tested' => 12,
            'positive' => 3,
            'started_treatment' => 2,
        ]);
});

test('provisional tat reuses the weighted reported median', function () {
    $summary = CommandTotals::summarize([
        commandReturnRow(tested: 10, tat: 2),
        commandReturnRow(facilityId: 2, tested: 30, tat: 4, on: '2026-01-02'),
        commandReturnRow(facilityId: 3, tested: 100, tat: null, on: '2026-01-03'),
        commandReturnRow(facilityId: 4, tested: 0, tat: 9, on: '2026-01-04'),
    ]);

    expect($summary['provisional_tat'])->toBe(ProvisionalReportedTat::average([
        new DailyReturn(['median_tat_days' => 2, 'tested' => 10]),
        new DailyReturn(['median_tat_days' => 4, 'tested' => 30]),
        new DailyReturn(['median_tat_days' => null, 'tested' => 100]),
        new DailyReturn(['median_tat_days' => 9, 'tested' => 0]),
    ]))->toBe(3.5);
});

test('year to date follows the Asia/Karachi calendar', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 00:30:00', 'Asia/Karachi'));

    $period = CommandPeriod::yearToDate();

    expect($period->from)->toBe('2026-01-01')
        ->and($period->to)->toBe('2026-01-01');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 12:00:00', 'Asia/Karachi'));
    $later = CommandPeriod::fromRequest(Request::create('/command', 'GET'));

    expect($later->from)->toBe('2026-01-01')
        ->and($later->to)->toBe('2026-10-03');

    $invalid = CommandPeriod::fromRequest(Request::create('/command', 'GET', [
        'from' => '2026-02-31',
        'to' => 'not-a-date',
    ]));

    expect($invalid->from)->toBe('2026-01-01')
        ->and($invalid->to)->toBe('2026-10-03');
});

function commandReturnRow(
    int $facilityId = 1,
    int $suspects = 1,
    int $tested = 1,
    int $positive = 0,
    int $negative = 1,
    int $started = 0,
    int $followUp = 0,
    string $on = '2026-01-01',
    ?float $tat = null,
): CommandReturnRow {
    return new CommandReturnRow(
        facilityId: $facilityId,
        facilityName: 'BMU '.$facilityId,
        cityId: 1,
        cityName: 'Karachi West',
        reportedOn: $on,
        suspects: $suspects,
        tested: $tested,
        positive: $positive,
        negative: $negative,
        startedTreatment: $started,
        followUp: $followUp,
        medianTatDays: $tat,
    );
}
