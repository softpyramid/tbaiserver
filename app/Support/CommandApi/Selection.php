<?php

namespace App\Support\CommandApi;

use App\Enums\Sector;
use App\Support\CommandPeriod;

/**
 * The filters a command center API request resolved to, after scope locking.
 */
final readonly class Selection
{
    public function __construct(
        public ?Sector $sector,
        public ?int $provinceId,
        public ?int $cityId,
        public string $from,
        public string $to,
        public string $scopeName,
        public ?string $provinceName,
        public ScopeLocks $locks,
    ) {}

    public function withSector(?Sector $sector): self
    {
        return new self($sector, $this->provinceId, $this->cityId, $this->from, $this->to, $this->scopeName, $this->provinceName, $this->locks);
    }

    public function withPeriod(string $from, string $to): self
    {
        return new self($this->sector, $this->provinceId, $this->cityId, $from, $to, $this->scopeName, $this->provinceName, $this->locks);
    }

    public function withCity(?int $cityId): self
    {
        return new self($this->sector, $this->provinceId, $cityId, $this->from, $this->to, $this->scopeName, $this->provinceName, $this->locks);
    }

    /**
     * The same scope and filters over the period immediately before this one.
     */
    public function previous(): self
    {
        $period = (new CommandPeriod($this->from, $this->to))->previous();

        return $this->withPeriod($period->from, $period->to);
    }

    /**
     * @return list<string>
     */
    public function months(): array
    {
        $cursor = new \DateTimeImmutable(substr($this->from, 0, 7).'-01');
        $end = new \DateTimeImmutable(substr($this->to, 0, 7).'-01');
        $months = [];

        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(bool $withSector = true): array
    {
        $filters = [];

        if ($withSector) {
            $filters['sector'] = $this->sector->value ?? 'all';
        }

        return $filters + [
            'province' => $this->provinceId === null ? 'all' : (string) $this->provinceId,
            'district' => $this->cityId === null ? null : (string) $this->cityId,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
