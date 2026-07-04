<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->name === 'Admin';
    }

    public function isCardOfficer(): bool
    {
        return $this->name === 'Card Officer';
    }

    public function isDepartmentHead(): bool
    {
        return $this->name === 'Department Head';
    }

    public function isGeneralManager(): bool
    {
        return $this->name === 'General Manager';
    }

    public function isRecorder(): bool
    {
        return $this->name === 'Recorder';
    }
}
