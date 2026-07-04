<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;class Patient extends Model
{
    protected $fillable = [
        'card_number',
        'patient_type_id',
        'relationship_type_id',
        'employee_no',
        'insurance_no',
        'dependent_no',
        'full_name',
        'gender',
        'date_of_birth',
        'phone',
        'address',
        'woreda',
        'kebele',
        'house_no',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dependent_no' => 'integer',
        ];
    }

    public function patientType(): BelongsTo
    {
        return $this->belongsTo(PatientType::class);
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function dailyRegisters(): HasMany
    {
        return $this->hasMany(DailyRegister::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
}
