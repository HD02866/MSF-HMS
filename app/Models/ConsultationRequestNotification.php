<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationRequestNotification extends Model
{
    protected $fillable = [
        'consultation_request_id',
        'room_id',
        'patient_id',
        'patient_name',
        'card_number',
        'event',
        'destination',
        'priority',
        'notified_at',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'is_read'     => 'boolean',
        ];
    }

    // ── Event constants ─────────────────────────────────────────────────────

    public const EVENT_SENT     = 'consultation_request_sent';
    public const EVENT_ACCEPTED = 'consultation_request_accepted';
    public const EVENT_REJECTED = 'consultation_request_rejected';
    public const EVENT_COMPLETED = 'consultation_request_completed';

    public const EVENT_LABELS = [
        self::EVENT_SENT     => '📋 Consultation Request Sent',
        self::EVENT_ACCEPTED => '✅ Consultation Request Accepted',
        self::EVENT_REJECTED => '❌ Consultation Request Rejected',
        self::EVENT_COMPLETED => '✅ Consultation Completed',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function consultationRequest(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class);
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
