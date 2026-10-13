<?php

namespace App\Support\Diagnosis;

/**
 * Demo engine for the UX walkthrough. It never opens the image: every result
 * is positive, and the grade and 3 to 5 highlight boxes are picked from the
 * request reference (the diagnosis uuid), so the same record always gets the
 * same output. Nothing here is an AI or clinical result.
 *
 * To plug in a real engine, see DiagnosisEngine.
 */
class SimulatedDiagnosisEngine implements DiagnosisEngine
{
    public const NAME = 'simulated';

    public const VERSION = 'demo-1';

    public const GRADES = ['Scanty', '1+', '2+', '3+'];

    public function analyse(DiagnosisRequest $request): DiagnosisResult
    {
        $seed = $request->reference;
        $grade = self::GRADES[$this->pick($seed, 'grade', count(self::GRADES))];
        $count = 3 + $this->pick($seed, 'count', 3);
        $findings = [];

        for ($i = 0; $i < $count; $i++) {
            $w = round(0.06 + 0.08 * $this->unit($seed, "w{$i}"), 4);
            $h = round(0.06 + 0.08 * $this->unit($seed, "h{$i}"), 4);
            $x = round(0.22 + (0.56 - $w) * $this->unit($seed, "x{$i}"), 4);
            $y = round(0.2 + (0.5 - $h) * $this->unit($seed, "y{$i}"), 4);

            $findings[] = new DiagnosisFinding(
                x: $x,
                y: $y,
                w: $w,
                h: $h,
                label: 'AFB',
                confidence: round(0.6 + 0.35 * $this->unit($seed, "c{$i}"), 2),
            );
        }

        return new DiagnosisResult(
            result: DiagnosisResult::POSITIVE,
            grade: $grade,
            findings: $findings,
            engine: self::NAME,
            engineVersion: self::VERSION,
            simulated: true,
        );
    }

    /**
     * A stable number from 0 to 1 for this seed and slot.
     */
    private function unit(string $seed, string $slot): float
    {
        return hexdec(substr(hash('sha256', $seed.':'.$slot), 0, 8)) / 0xFFFFFFFF;
    }

    private function pick(string $seed, string $slot, int $size): int
    {
        return (int) hexdec(substr(hash('sha256', $seed.':'.$slot), 0, 8)) % $size;
    }
}
