<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRegister extends Model
{
    protected $fillable = [
        'patient_id',
        'register_type',
        'record_date',
        'department_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
        ];
    }

    // Valid register types
    public const TYPES = [
        'family'              => 'Family',
        'employee'            => 'Employee',
        'os'                  => 'Out Service (OS)',
        'referral_accident'   => 'Referral Accident',
        'referral_sick_leave' => 'Referral Sick Leave',
    ];

    // Types that require a department field
    public const TYPES_WITH_DEPARTMENT = ['family', 'employee', 'referral_accident', 'referral_sick_leave'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->register_type] ?? $this->register_type;
    }
}
