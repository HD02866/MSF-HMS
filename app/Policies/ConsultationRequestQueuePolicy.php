<?php

namespace App\Policies;

use App\Models\ConsultationRequestQueue;
use App\Models\User;

class ConsultationRequestQueuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin', 'Department Head', 'General Manager', 'OPD Nurse');
    }

    public function view(User $user, ConsultationRequestQueue $queue): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ConsultationRequestQueue $queue): bool
    {
        return $user->hasRole('Admin', 'Department Head');
    }
}
