<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string $type
 * @property string $code
 * @property string $reason
 */
#[Fillable(['assessment_id', 'type', 'code', 'reason'])]
class SuspectAssessmentFlag extends Model
{
    public const TYPE_CLINICAL_RULE = 'clinical_rule';

    public const TYPE_RISK_PATTERN = 'risk_pattern';
}
