<?php

namespace App\Policies;

use App\Models\DailyRegister;
use App\Models\User;

class DailyRegisterPolicy
{
    /** Card Officer, Recorder, Department Head, Admin can view */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin', 'Card Officer', 'Department Head', 'Recorder');
    }

    public function view(User $user, DailyRegister $register): bool
    {
        return $this->viewAny($user);
    }

    /** Admin, Card Officer, and Recorder can create / edit / delete / export */
    public function create(User $user): bool
    {
        return $user->hasRole('Admin', 'Card Officer', 'Recorder');
    }

    public function update(User $user, DailyRegister $register): bool
    {
        return $user->hasRole('Admin', 'Card Officer', 'Recorder');
    }

    public function delete(User $user, DailyRegister $register): bool
    {
        return $user->hasRole('Admin', 'Card Officer', 'Recorder');
    }

    public function export(User $user): bool
    {
        return $user->hasRole('Admin', 'Card Officer', 'Recorder');
    }
}
