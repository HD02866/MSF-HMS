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
        'referred_from',
        'days_given',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'days_given'  => 'integer',
        ];
    }

    // ── Register type constants ─────────────────────────────────────────────

    public const TYPES = [
        'family'              => 'Family',
        'employee'            => 'Employee',
        'os'                  => 'Out Service (OS)',
        'referral_accident'   => 'Referral Accident',
        'referral_sick_leave' => 'Referral Sick Leave',
    ];

    // Types that require the Department field
    public const TYPES_WITH_DEPARTMENT = ['family', 'employee', 'referral_accident', 'referral_sick_leave'];

    // Types that show the Referred From + Days Given fields
    public const TYPES_WITH_REFERRAL = ['referral_accident', 'referral_sick_leave'];

    // Dropdown options for "Referred From"
    public const REFERRAL_SOURCES = [
        'OPD 4',
        'OPD 5',
        'OPD 6',
        'OPD 7',
        'Doctor Room',
        'Emergency',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->register_type] ?? $this->register_type;
    }
}
