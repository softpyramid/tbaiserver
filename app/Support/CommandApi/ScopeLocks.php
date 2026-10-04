<?php

namespace App\Support\CommandApi;

/**
 * What the signed-in role pins. The UI only hides filter controls from this.
 */
final readonly class ScopeLocks
{
    public function __construct(
        public string $level,
        public ?int $provinceId,
        public ?int $districtId,
    ) {}

    public function provinceLocked(): bool
    {
        return in_array($this->level, ['province', 'district'], true);
    }

    public function districtLocked(): bool
    {
        return $this->level === 'district';
    }
}
