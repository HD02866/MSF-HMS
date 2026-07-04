<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManagePatients() || $user->hasRole('General Manager', 'Recorder');
    }

    public function create(User $user): bool
    {
        return $user->canManagePatients() || $user->hasRole('Recorder');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $this->viewAny($user);
    }
}
