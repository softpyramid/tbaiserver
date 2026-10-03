<?php

namespace App\Models;

use App\Enums\AlertComparator;
use App\Enums\AlertMetric;
use App\Enums\AlertSubject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property AlertMetric $metric
 * @property AlertComparator $comparator
 * @property string $threshold
 * @property string $severity
 * @property string $label
 * @property AlertSubject $subject
 */
#[Fillable(['key', 'metric', 'comparator', 'threshold', 'severity', 'label', 'subject'])]
class AlertRule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric' => AlertMetric::class,
            'comparator' => AlertComparator::class,
            'subject' => AlertSubject::class,
            'threshold' => 'decimal:3',
        ];
    }
}
