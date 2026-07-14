<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyNotification extends Model
{
    protected $fillable = [
        'pharmacy_request_id',
        'room_id',
        'patient_id',
        'patient_name',
        'card_number',
        'event',
        'medicine_names',
        'notified_at',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'medicine_names' => 'array',
            'notified_at'    => 'datetime',
            'is_read'        => 'boolean',
        ];
    }

    // ── Event constants ──────────────────────────────────────────────────────
    public const EVENT_SUBMITTED = 'pharmacy_submitted';
    public const EVENT_DISPENSED = 'pharmacy_dispensed';

    public const EVENT_LABELS = [
        self::EVENT_SUBMITTED => '💊 Prescription Sent',
        self::EVENT_DISPENSED => '✅ Medicine Dispensed',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function pharmacyRequest(): BelongsTo
    {
        return $this->belongsTo(PharmacyRequest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
