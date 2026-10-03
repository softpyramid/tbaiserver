<?php

namespace App\Support;

class ScreeningScore
{
    public const NOT_READY = 'Screening questions are being finalised with the TB programme';

    /**
     * Questions shipped in config. Empty until the TB programme confirms the sheet.
     *
     * @return list<array{key: string, label: string, points: int}>
     */
    public static function questions(): array
    {
        $questions = config('screening.questions');

        if (! is_array($questions)) {
            return [];
        }

        $normalized = [];

        foreach ($questions as $question) {
            if (! is_array($question) || ! isset($question['key'], $question['label'])) {
                continue;
            }

            $key = $question['key'];
            $label = $question['label'];

            if (! is_string($key) || $key === '' || ! is_string($label) || $label === '') {
                continue;
            }

            $points = $question['points'] ?? 1;

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'points' => is_numeric($points) ? max(0, (int) $points) : 1,
            ];
        }

        return $normalized;
    }

    /**
     * Suspect cutoff. Null until the TB programme confirms it.
     */
    public static function cutoff(): ?int
    {
        $cutoff = config('screening.cutoff');

        if ($cutoff === null || $cutoff === '' || ! is_numeric($cutoff)) {
            return null;
        }

        return (int) $cutoff;
    }

    /**
     * Score is the sum of each question's points when the answer is yes.
     * A no answer adds nothing. is_suspect is true only when a cutoff is set
     * and the score is at least that cutoff.
     *
     * @param  array<string, mixed>  $answers
     * @return array{score: int, is_suspect: bool}
     */
    public function evaluate(array $answers): array
    {
        $score = 0;

        foreach (self::questions() as $question) {
            if (($answers[$question['key']] ?? null) === 'yes') {
                $score += $question['points'];
            }
        }

        $cutoff = self::cutoff();

        return [
            'score' => $score,
            'is_suspect' => $cutoff !== null && $score >= $cutoff,
        ];
    }

    public static function confirmation(int $score, bool $isSuspect): string
    {
        if ($isSuspect) {
            return "Screening saved. Score: {$score}. This person is a suspect.";
        }

        return "Screening saved. Score: {$score}. This person is not a suspect.";
    }
}
