<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientType extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function requiresEmployeeInfo(): bool
    {
        return in_array($this->name, ['Employee', 'Family'], true);
    }

    public function requiresInsuranceNo(): bool
    {
        return $this->name === 'Insurance';
    }
}
