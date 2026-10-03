<?php

namespace App\Models;

use App\Enums\ProgrammeRole;
use App\Enums\ScopeType;
use Database\Factories\ProgrammeAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property ProgrammeRole $role
 * @property ScopeType|null $scope_type
 * @property int|null $scope_id
 */
#[Fillable(['user_id', 'role', 'scope_type', 'scope_id'])]
class ProgrammeAssignment extends Model
{
    /** @use HasFactory<ProgrammeAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ProgrammeRole::class,
            'scope_type' => ScopeType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
