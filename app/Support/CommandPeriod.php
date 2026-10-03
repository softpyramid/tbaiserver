<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Inclusive calendar range in Asia/Karachi. The default is 1 January through today.
 */
final class CommandPeriod
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {}

    public static function yearToDate(?CarbonImmutable $today = null): self
    {
        $day = ($today ?? EntryDate::now())->timezone(EntryDate::timezone())->startOfDay();

        return new self($day->startOfYear()->toDateString(), $day->toDateString());
    }

    public static function fromRequest(Request $request): self
    {
        $default = self::yearToDate();
        $from = self::day($request, 'from') ?? $default->from;
        $to = self::day($request, 'to') ?? $default->to;

        if ($from > $to) {
            return new self($to, $from);
        }

        return new self($from, $to);
    }

    /**
     * @return list<string> Year-month keys from the start month through the end month.
     */
    public function months(): array
    {
        $cursor = CarbonImmutable::parse($this->from, EntryDate::timezone())->startOfMonth();
        $end = CarbonImmutable::parse($this->to, EntryDate::timezone())->startOfMonth();
        $keys = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $keys;
    }

    public function labelForMonth(string $month): string
    {
        return CarbonImmutable::parse($month.'-01', EntryDate::timezone())->format('M Y');
    }

    /**
     * The same number of days, ending the day before this range starts.
     */
    public function previous(): self
    {
        $from = CarbonImmutable::parse($this->from, EntryDate::timezone())->startOfDay();
        $to = CarbonImmutable::parse($this->to, EntryDate::timezone())->startOfDay();
        $span = (int) round($from->diffInDays($to));
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($span);

        return new self($previousFrom->toDateString(), $previousTo->toDateString());
    }

    /**
     * Calendar months ending on $endDate, including the month that contains it.
     */
    public static function trailingMonths(string $endDate, int $months): self
    {
        $end = CarbonImmutable::parse($endDate, EntryDate::timezone())->startOfDay();
        $count = max(1, $months);
        $start = $end->startOfMonth()->subMonths($count - 1);

        return new self($start->toDateString(), $end->toDateString());
    }

    private static function day(Request $request, string $key): ?string
    {
        $value = ResourceResponse::date($request, $key);

        if ($value === null) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value, EntryDate::timezone());
        } catch (Throwable) {
            return null;
        }

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
