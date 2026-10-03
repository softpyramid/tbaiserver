<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an admin edit. Do not pass patient identifiers.
     *
     * @param  array<string, mixed>  $properties
     */
    public function write(User $actor, string $action, ?Model $subject = null, array $properties = []): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject::class,
            'subject_id' => $subject === null ? null : $subject->getKey(),
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
