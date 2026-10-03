<?php

namespace App\Support;

use App\Enums\AlertComparator;
use App\Enums\AlertMetric;
use App\Enums\AlertSubject;
use App\Enums\Sector;
use App\Models\AlertRule;
use App\Models\Facility;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Live alerts from daily returns. A rule with missing inputs is not triggered
 * and does not receive a stand-in number.
 */
class CommandAlerts
{
    public function __construct(private CommandReturnQuery $returns) {}

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array<string, mixed>
     */
    public function present(User $user, array $selection): array
    {
        $canEdit = $user->isTbaiAdmin();
        $rules = AlertRule::query()->orderBy('id')->get();
        $currentRows = $this->returns->rows($user, $selection);
        $previousSelection = $selection;
        $previousSelection['period'] = $selection['period']->previous();
        $previousRows = $this->returns->rows($user, $previousSelection);
        $alerts = [];
        $ruleRows = [];

        foreach ($rules as $rule) {
            $matches = $this->evaluate($rule, $user, $selection, $currentRows, $previousRows);
            $status = $matches === [] ? 'not_triggered' : 'triggered';

            foreach ($matches as $match) {
                $alerts[] = $match;
            }

            $ruleRows[] = [
                'id' => $rule->id,
                'metric' => $rule->metric->value,
                'metricLabel' => $rule->metric->label(),
                'comparator' => $rule->comparator->value,
                'comparatorLabel' => $rule->comparator->label(),
                'threshold' => $this->thresholdString($rule->threshold),
                'severity' => $rule->severity,
                'label' => $rule->label,
                'status' => $status,
                'statusLabel' => $status === 'triggered'
                    ? __('entry.alert_triggered')
                    : __('entry.alert_not_triggered'),
                'updateUrl' => $canEdit ? route('command.alerts.update', $rule) : null,
            ];
        }

        return [
            'badge' => __('entry.alerts_thresholds_pending'),
            'canEdit' => $canEdit,
            'rules' => $ruleRows,
            'alerts' => $alerts,
            'metricOptions' => AlertMetric::options(),
            'comparatorOptions' => AlertComparator::options(),
            'alertLabels' => [
                'metric' => __('entry.alert_column_metric'),
                'comparator' => __('entry.alert_column_comparator'),
                'threshold' => __('entry.alert_column_threshold'),
                'severity' => __('entry.alert_column_severity'),
                'label' => __('entry.alert_column_label'),
                'status' => __('entry.alert_column_status'),
                'save' => __('entry.save'),
                'alerts' => __('entry.alert_list_heading'),
                'rules' => __('entry.alert_rules_heading'),
            ],
        ];
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @param  list<CommandReturnRow>  $currentRows
     * @param  list<CommandReturnRow>  $previousRows
     * @return list<array<string, mixed>>
     */
    private function evaluate(
        AlertRule $rule,
        User $user,
        array $selection,
        array $currentRows,
        array $previousRows,
    ): array {
        $metric = $rule->metric;
        $comparator = $rule->comparator;
        $subject = $rule->subject;

        $matches = match ($metric) {
            AlertMetric::TreatmentGap => $this->treatmentGap($rule, $comparator, $subject, $selection, $currentRows),
            AlertMetric::TatRatio => $this->tatRatio($rule, $comparator, $subject, $selection, $currentRows, $previousRows),
            AlertMetric::PositivityStreak => $this->positivityStreak($rule, $comparator, $subject, $user, $selection),
        };

        return $matches;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @param  list<CommandReturnRow>  $rows
     * @return list<array<string, mixed>>
     */
    private function treatmentGap(
        AlertRule $rule,
        AlertComparator $comparator,
        AlertSubject $subject,
        array $selection,
        array $rows,
    ): array {
        $alerts = [];

        foreach ($this->groups($rows, $subject) as $placeId => $group) {
            if ($group['rows'] === []) {
                continue;
            }

            $summary = CommandTotals::summarize($group['rows']);
            $gap = (float) $summary['treatment_gap'];

            if (! $comparator->matches($gap, (float) $rule->threshold)) {
                continue;
            }

            $alert = $this->alert($rule, $subject, $placeId, $group, $selection);

            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @param  list<CommandReturnRow>  $currentRows
     * @param  list<CommandReturnRow>  $previousRows
     * @return list<array<string, mixed>>
     */
    private function tatRatio(
        AlertRule $rule,
        AlertComparator $comparator,
        AlertSubject $subject,
        array $selection,
        array $currentRows,
        array $previousRows,
    ): array {
        $current = $this->groups($currentRows, $subject);
        $previous = $this->groups($previousRows, $subject);
        $alerts = [];

        foreach ($current as $placeId => $group) {
            $currentTat = CommandTotals::summarize($group['rows'])['provisional_tat'];
            $previousTat = isset($previous[$placeId])
                ? CommandTotals::summarize($previous[$placeId]['rows'])['provisional_tat']
                : null;

            if ($currentTat === null || $previousTat === null || $previousTat == 0.0) {
                continue;
            }

            $ratio = $currentTat / $previousTat;

            if (! $comparator->matches($ratio, (float) $rule->threshold)) {
                continue;
            }

            $alert = $this->alert($rule, $subject, $placeId, $group, $selection);

            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return list<array<string, mixed>>
     */
    private function positivityStreak(
        AlertRule $rule,
        AlertComparator $comparator,
        AlertSubject $subject,
        User $user,
        array $selection,
    ): array {
        $threshold = (float) $rule->threshold;
        $rises = (int) round($threshold);

        if (abs($threshold - $rises) > 0.0001 || $rises < 1) {
            return [];
        }

        $end = CarbonImmutable::parse($selection['period']->to, EntryDate::timezone())->startOfMonth();
        $start = $end->subMonths($rises);
        $window = new CommandPeriod($start->toDateString(), $selection['period']->to);
        $windowSelection = $selection;
        $windowSelection['period'] = $window;
        $rows = $this->returns->rows($user, $windowSelection);
        $months = $window->months();
        $byPlace = [];

        foreach ($rows as $row) {
            $placeId = $subject === AlertSubject::District ? $row->cityId : $row->facilityId;
            $month = substr($row->reportedOn, 0, 7);
            $byPlace[$placeId]['name'] = $subject === AlertSubject::District ? $row->cityName : $row->facilityName;
            $byPlace[$placeId]['cityId'] = $row->cityId;
            $byPlace[$placeId]['facilityId'] = $row->facilityId;
            $byPlace[$placeId]['months'][$month][] = $row;
        }

        $alerts = [];

        foreach ($byPlace as $placeId => $group) {
            $values = [];
            $missing = false;

            foreach ($months as $month) {
                $summary = CommandTotals::summarize($group['months'][$month] ?? []);

                if ($summary['positivity'] === null) {
                    $missing = true;
                    break;
                }

                $values[] = $summary['positivity'];
            }

            if ($missing || $values === []) {
                continue;
            }

            $increases = 0;

            for ($index = 1, $count = count($values); $index < $count; $index++) {
                if ($values[$index] > $values[$index - 1]) {
                    $increases++;
                }
            }

            if (! $comparator->matches((float) $increases, (float) $rises)) {
                continue;
            }

            $alert = $this->alert($rule, $subject, (int) $placeId, [
                'name' => $group['name'],
                'cityId' => $group['cityId'],
                'facilityId' => $group['facilityId'],
                'rows' => [],
            ], $selection);

            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param  list<CommandReturnRow>  $rows
     * @return array<int, array{name: string, cityId: int, facilityId: int, rows: list<CommandReturnRow>}>
     */
    private function groups(array $rows, AlertSubject $subject): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $placeId = $subject === AlertSubject::District ? $row->cityId : $row->facilityId;
            $groups[$placeId]['name'] = $subject === AlertSubject::District ? $row->cityName : $row->facilityName;
            $groups[$placeId]['cityId'] = $row->cityId;
            $groups[$placeId]['facilityId'] = $row->facilityId;
            $groups[$placeId]['rows'][] = $row;
        }

        return $groups;
    }

    /**
     * @param  array{name: string, cityId: int, facilityId: int, rows: list<CommandReturnRow>}  $group
     * @param  array{
     *     period: CommandPeriod,
     *     provinceId: int|null,
     *     cityId: int|null,
     *     sector: Sector|null,
     *     facilityId: int|null
     * }  $selection
     * @return array<string, mixed>|null
     */
    private function alert(
        AlertRule $rule,
        AlertSubject $subject,
        int $placeId,
        array $group,
        array $selection,
    ): ?array {
        $query = [
            'from' => $selection['period']->from,
            'to' => $selection['period']->to,
        ];

        if ($selection['sector'] !== null) {
            $query['sector'] = $selection['sector']->value;
        }

        if ($subject === AlertSubject::District) {
            return [
                'id' => $rule->id.'-district-'.$placeId,
                'label' => $rule->label,
                'severity' => $rule->severity,
                'metric' => $rule->metric->value,
                'place' => [
                    'type' => 'district',
                    'id' => (string) $group['cityId'],
                    'name' => $group['name'],
                ],
                'href' => route('command.map', [
                    ...$query,
                    'district' => $group['cityId'],
                ]),
            ];
        }

        $facility = Facility::query()->find($group['facilityId']);

        if ($facility === null) {
            return null;
        }

        return [
            'id' => $rule->id.'-facility-'.$placeId,
            'label' => $rule->label,
            'severity' => $rule->severity,
            'metric' => $rule->metric->value,
            'place' => [
                'type' => 'facility',
                'id' => (string) $group['facilityId'],
                'name' => $group['name'],
            ],
            'href' => route('command.districts.show', [
                'sector' => $facility->sector->value,
                'city' => $group['cityId'],
                'facility' => $group['facilityId'],
                ...$query,
            ]),
        ];
    }

    private function thresholdString(mixed $value): string
    {
        $formatted = number_format((float) $value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
