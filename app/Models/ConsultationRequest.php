<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationRequest extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'requested_by',
        'requester_name',
        'signature_data',
        'destination',
        'reason',
        'clinical_summary',
        'priority',
        'request_date',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
        ];
    }

    // ── Constants ────────────────────────────────────────────────────────────

    public const PRIORITIES = [
        'Normal' => 'Normal',
        'Urgent' => 'Urgent',
    ];

    public const DESTINATIONS = [
        'Emergency'         => 'Emergency',
        'MCH'               => 'MCH',
        'TB Clinic'         => 'TB Clinic',
        'Surgery'           => 'Surgery',
        'Internal Medicine' => 'Internal Medicine',
        'Eye Clinic'        => 'Eye Clinic',
        'Other'             => 'Other',
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

    public function queue(): HasOne
    {
        return $this->hasOne(ConsultationRequestQueue::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ConsultationRequestNotification::class);
    }
}
