<?php

namespace App\Support\Diagnosis;

/**
 * One highlighted region. x, y, w and h are fractions of the image width and
 * height (0 to 1) measured from the top left corner.
 */
final readonly class DiagnosisFinding
{
    public function __construct(
        public float $x,
        public float $y,
        public float $w,
        public float $h,
        public string $label,
        public float $confidence,
    ) {}

    /**
     * @return array{x: float, y: float, w: float, h: float, label: string, confidence: float}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'w' => $this->w,
            'h' => $this->h,
            'label' => $this->label,
            'confidence' => $this->confidence,
        ];
    }
}
