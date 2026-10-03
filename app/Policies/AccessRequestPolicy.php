<?php

namespace App\Policies;

use App\Models\AccessRequest;
use App\Models\User;

class AccessRequestPolicy
{
    /**
     * Mail address of the administrator who may view stored requests.
     */
    public static function configuredAdministratorEmail(): ?string
    {
        $adminEmail = config('access.admin_email');

        if (! is_string($adminEmail)) {
            return null;
        }

        $adminEmail = trim($adminEmail);

        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $adminEmail;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTbaiAdmin() || $this->isAdministrator($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AccessRequest $accessRequest): bool
    {
        return $user->isTbaiAdmin() || $this->isAdministrator($user);
    }

    /**
     * Approve or reject a request. This does not create a user.
     */
    public function review(User $user, AccessRequest $accessRequest): bool
    {
        return $user->isTbaiAdmin() || $this->isAdministrator($user);
    }

    /**
     * Determine whether the user is the configured administrator.
     */
    private function isAdministrator(User $user): bool
    {
        $adminEmail = self::configuredAdministratorEmail();

        return $adminEmail !== null && strcasecmp($user->email, $adminEmail) === 0;
    }
}
