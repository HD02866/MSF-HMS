<?php

namespace App\Policies;

use App\Models\PharmacyQueue;
use App\Models\User;

class PharmacyQueuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin', 'Pharmacist', 'OPD Nurse', 'Department Head');
    }

    public function view(User $user, PharmacyQueue $pharmacyQueue): bool
    {
        return $user->hasRole('Admin', 'Pharmacist', 'OPD Nurse', 'Department Head');
    }

    public function update(User $user, PharmacyQueue $pharmacyQueue): bool
    {
        return $user->hasRole('Admin', 'Pharmacist');
    }
}
