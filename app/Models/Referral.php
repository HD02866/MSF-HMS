<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'requested_by',
        'destination',
        'reason',
        'diagnosis',
        'doctor_nurse_name',
        'signature_data',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public const DESTINATIONS = [
        'Emergency'        => 'Emergency',
        'MCH'              => 'MCH',
        'TB Clinic'        => 'TB Clinic',
        'Surgery'          => 'Surgery',
        'Internal Medicine' => 'Internal Medicine',
        'Eye Clinic'       => 'Eye Clinic',
        'Other'            => 'Other',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
