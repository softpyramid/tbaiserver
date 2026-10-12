<?php

namespace App\Policies;

use App\Models\SlidePhoto;
use App\Models\User;

class SlidePhotoPolicy
{
    /**
     * A TB-AI admin can view every slide photo. Anyone else must work the
     * returns of the facility the photo was taken for (not a frontline worker).
     */
    public function view(User $user, SlidePhoto $photo): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        $team = $photo->facility->team;

        return $user->worksReturnsAt($team);
    }

    /**
     * The uploader can delete their own photo while they still belong to the
     * facility. A TB-AI admin can remove any photo, for example one that shows
     * a patient name by mistake.
     */
    public function delete(User $user, SlidePhoto $photo): bool
    {
        if ($user->isTbaiAdmin()) {
            return true;
        }

        return $photo->uploaded_by === $user->id
            && $user->worksReturnsAt($photo->facility->team);
    }
}
