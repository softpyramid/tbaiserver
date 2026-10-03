<?php

use App\Enums\MapIndicator;
use App\Support\CommandPeriod;
use App\Support\CommandTotals;
use App\Support\DistrictMatch;
use Tests\TestCase;

uses(TestCase::class);

test('map indicators use the live formulas and stay empty when the divisor is zero', function () {
    $covered = CommandTotals::summarize([
        commandReturnRow(suspects: 10, tested: 4, positive: 1, negative: 3, started: 0, tat: 2),
    ]);
    $untested = CommandTotals::summarize([
        commandReturnRow(suspects: 8, tested: 0, positive: 0, negative: 0, started: 0),
    ]);
    $noSuspects = CommandTotals::summarize([
        commandReturnRow(suspects: 0, tested: 0, positive: 0, negative: 0, started: 0),
    ]);

    expect(MapIndicator::Suspects->read($covered))->toBe(10)
        ->and(MapIndicator::Positivity->read($covered))->toBe(0.25)
        ->and(MapIndicator::ProvisionalTat->read($covered))->toBe(2.0)
        ->and(MapIndicator::TreatmentGap->read($covered))->toBe(1)
        ->and(MapIndicator::TestingCoverage->read($covered))->toBe(0.4)
        ->and(MapIndicator::Positivity->read($untested))->toBeNull()
        ->and(MapIndicator::TestingCoverage->read($untested))->toBe(0.0)
        ->and(MapIndicator::Positivity->read($noSuspects))->toBeNull()
        ->and(MapIndicator::TestingCoverage->read($noSuspects))->toBeNull()
        ->and(CommandTotals::testingCoverage(0, 0))->toBeNull()
        ->and(CommandTotals::positivity(1, 0))->toBeNull();
});

test('a 12 month trend is each month of daily returns and the previous window has the same length', function () {
    $period = CommandPeriod::trailingMonths('2026-10-03', 12);
    $buckets = CommandTotals::monthBuckets([
        commandReturnRow(suspects: 7, tested: 4, positive: 1, negative: 3, started: 1, on: '2026-02-01'),
        commandReturnRow(suspects: 99, tested: 9, positive: 9, negative: 0, started: 9, on: '2025-10-01'),
    ], $period);

    expect($period->from)->toBe('2025-11-01')
        ->and($period->to)->toBe('2026-10-03')
        ->and($buckets)->toHaveCount(12)
        ->and(collect($buckets)->firstWhere('key', '2026-02'))->toMatchArray([
            'suspects' => 7,
            'tested' => 4,
            'positive' => 1,
            'started_treatment' => 1,
        ])
        ->and(collect($buckets)->sum('suspects'))->toBe(7);

    $current = new CommandPeriod('2026-02-01', '2026-02-28');

    expect($current->previous()->from)->toBe('2026-01-04')
        ->and($current->previous()->to)->toBe('2026-01-31');
});

test('district names match the Sindh boundary file', function () {
    expect(DistrictMatch::key('Karachi West'))->toBe('karachi-west')
        ->and(DistrictMatch::key('West Karachi'))->toBe('karachi-west')
        ->and(DistrictMatch::key('Qamber Shahdadkot'))->toBe('kambar-shahdadkot')
        ->and(DistrictMatch::key('Thatta'))->toBe('thatta');
});
