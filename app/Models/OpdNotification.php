<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpdNotification extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'room_id',
        'patient_id',
        'patient_name',
        'card_number',
        'queue_number',
        'assignment_time',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'assignment_time' => 'datetime',
            'is_read'         => 'boolean',
        ];
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
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
