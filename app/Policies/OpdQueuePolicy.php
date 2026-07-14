<?php

namespace App\Policies;

use App\Models\OpdQueue;
use App\Models\User;

class OpdQueuePolicy
{
    /** OPD Nurse, Admin, Department Head can view the queue */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin', 'OPD Nurse', 'Department Head');
    }

    public function view(User $user, OpdQueue $queue): bool
    {
        return $this->viewAny($user);
    }

    /** OPD Nurse and Admin can update queue status */
    public function update(User $user, OpdQueue $queue): bool
    {
        return $user->hasRole('Admin', 'OPD Nurse');
    }
}
