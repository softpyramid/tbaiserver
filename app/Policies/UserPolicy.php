<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Impersonation;

class UserPolicy
{
    /**
     * "Log in as this user": the actor must manage the target and strictly
     * outrank them (see Impersonation::outranks), and must not already be
     * impersonating someone.
     */
    public function impersonate(User $actor, User $target): bool
    {
        $impersonation = app(Impersonation::class);

        if ($impersonation->active()) {
            return false;
        }

        return $impersonation->outranks($actor, $target);
    }
}
