<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManagePatients() || $user->canViewReportsOnly() || $user->hasRole('Recorder');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->canManagePatients();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->canManagePatients();
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->canManagePatients();
    }
}
