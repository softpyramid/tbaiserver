<?php

namespace App\Models;

use App\Concerns\GuardsSuspectRuleSet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $rule_set_id
 * @property string $key
 * @property string $section
 * @property string $prompt
 * @property string|null $help
 * @property list<string> $options
 * @property array<string, mixed>|null $ask_when
 * @property int $sort
 */
#[Fillable(['rule_set_id', 'key', 'section', 'prompt', 'help', 'options', 'ask_when', 'sort'])]
class SuspectQuestion extends Model
{
    use GuardsSuspectRuleSet;

    public const SECTION_PROFILE = 'profile';

    public const SECTION_SYMPTOMS = 'symptoms';

    public const SECTION_EXPOSURE_HISTORY = 'exposure_history';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'ask_when' => 'array',
            'sort' => 'integer',
        ];
    }

    /**
     * Profile questions (age band, sex) live on the assessment, not as answers.
     */
    public function isProfile(): bool
    {
        return $this->section === self::SECTION_PROFILE;
    }
}
