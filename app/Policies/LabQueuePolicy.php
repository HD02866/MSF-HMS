<?php

namespace App\Policies;

use App\Models\LabQueue;
use App\Models\User;

class LabQueuePolicy
{
    /**
     * Lab Technician, Admin, and OPD Nurse can view the queue.
     * Department Head can view for oversight.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin', 'Lab Technician', 'OPD Nurse', 'Department Head', 'Pharmacist');
    }

    public function view(User $user, LabQueue $labQueue): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Lab Technician and Admin can update queue status.
     */
    public function update(User $user, LabQueue $labQueue): bool
    {
        return $user->hasRole('Admin', 'Lab Technician');
    }
}
