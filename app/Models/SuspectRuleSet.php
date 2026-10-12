<?php

namespace App\Models;

use App\Enums\SuspectRuleSetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A versioned set of suspect screening questions, weights, clinical rules and
 * risk patterns. Only a draft can be edited. Approved, published and retired
 * sets are immutable: a change starts a new draft through a clone.
 *
 * @property int $id
 * @property string $version
 * @property SuspectRuleSetStatus $status
 * @property string $engine_version
 * @property int $high_threshold
 * @property int $medium_threshold
 * @property int $refer_threshold
 * @property int|null $cloned_from_id
 * @property int|null $created_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $clinical_signoff_reference
 * @property string|null $approval_note
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SuspectQuestion> $questions
 * @property-read Collection<int, SuspectScoreItem> $scoreItems
 * @property-read Collection<int, SuspectClinicalRule> $clinicalRules
 * @property-read Collection<int, SuspectRiskPattern> $riskPatterns
 * @property-read User|null $approver
 * @property-read User|null $creator
 */
#[Fillable([
    'version', 'status', 'engine_version', 'high_threshold', 'medium_threshold', 'refer_threshold',
    'cloned_from_id', 'created_by', 'submitted_at', 'approved_by', 'approved_at',
    'clinical_signoff_reference', 'approval_note', 'published_at',
])]
class SuspectRuleSet extends Model
{
    /**
     * Columns a locked set may still change: retiring and publishing.
     *
     * @var list<string>
     */
    private const LIFECYCLE_COLUMNS = ['status', 'published_at', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (SuspectRuleSet $ruleSet): void {
            $original = SuspectRuleSetStatus::tryFrom((string) $ruleSet->getRawOriginal('status'));

            if ($original !== SuspectRuleSetStatus::Approved && $original !== SuspectRuleSetStatus::Retired) {
                return;
            }

            $changed = array_diff(array_keys($ruleSet->getDirty()), self::LIFECYCLE_COLUMNS);

            if ($changed !== [] || $original === SuspectRuleSetStatus::Retired) {
                throw new LogicException('Approved, published and retired rule sets cannot be changed.');
            }
        });

        static::deleting(function (SuspectRuleSet $ruleSet): void {
            if ($ruleSet->isLocked()) {
                throw new LogicException('Approved, published and retired rule sets cannot be deleted.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SuspectRuleSetStatus::class,
            'high_threshold' => 'integer',
            'medium_threshold' => 'integer',
            'refer_threshold' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === SuspectRuleSetStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === SuspectRuleSetStatus::Approved && $this->published_at !== null;
    }

    /**
     * Approved (published or not) and retired sets never change again.
     */
    public function isLocked(): bool
    {
        return $this->status === SuspectRuleSetStatus::Approved
            || $this->status === SuspectRuleSetStatus::Retired;
    }

    /**
     * Approved with a named approver and a recorded clinical sign-off.
     */
    public function hasClinicalApproval(): bool
    {
        return $this->approved_at !== null
            && $this->approved_by !== null
            && is_string($this->clinical_signoff_reference)
            && trim($this->clinical_signoff_reference) !== '';
    }

    /**
     * @return HasMany<SuspectQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SuspectQuestion::class, 'rule_set_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<SuspectScoreItem, $this>
     */
    public function scoreItems(): HasMany
    {
        return $this->hasMany(SuspectScoreItem::class, 'rule_set_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<SuspectClinicalRule, $this>
     */
    public function clinicalRules(): HasMany
    {
        return $this->hasMany(SuspectClinicalRule::class, 'rule_set_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<SuspectRiskPattern, $this>
     */
    public function riskPatterns(): HasMany
    {
        return $this->hasMany(SuspectRiskPattern::class, 'rule_set_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
