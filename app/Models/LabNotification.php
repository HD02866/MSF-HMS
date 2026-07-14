<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabNotification extends Model
{
    protected $fillable = [
        'lab_request_id',
        'room_id',
        'patient_id',
        'patient_name',
        'card_number',
        'event',
        'test_names',
        'notified_at',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'test_names'   => 'array',
            'notified_at'  => 'datetime',
            'is_read'      => 'boolean',
        ];
    }

    // ── Event constants ─────────────────────────────────────────────────────

    public const EVENT_RECEIVED  = 'lab_received';
    public const EVENT_COMPLETED = 'lab_completed';

    public const EVENT_LABELS = [
        'lab_received'  => '🧪 Lab Received',
        'lab_completed' => '✅ Lab Completed',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
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
