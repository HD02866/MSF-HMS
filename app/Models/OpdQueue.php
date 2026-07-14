<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OpdQueue extends Model
{
    protected $table = 'opd_queue';

    protected $fillable = [
        'visit_id',
        'patient_id',
        'room_id',
        'queue_number',
        'arrived_at',
        'called_at',
        'completed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'arrived_at'   => 'datetime',
            'called_at'    => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Valid statuses ──────────────────────────────────────────────────────

    public const STATUSES = [
        'Waiting'         => 'Waiting',
        'Called'          => 'Called',
        'In Consultation' => 'In Consultation',
        'Completed'       => 'Completed',
        'Transferred'     => 'Transferred',
        'Cancelled'       => 'Cancelled',
    ];

    // OPD room codes — rooms that feed into OPD queues
    public const OPD_ROOM_CODES = ['OPD4', 'OPD5', 'OPD6', 'OPD7', 'OPD8', 'EMERGENCY'];

    // ── Relationships ───────────────────────────────────────────────────────

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function notification(): HasOne
    {
        return $this->hasOne(OpdNotification::class);
    }

    public function clinicalNote(): HasOne
    {
        return $this->hasOne(OpdClinicalNote::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(OpdAttachment::class)->orderByDesc('created_at');
    }

    public function labRequests(): HasMany
    {
        return $this->hasMany(\App\Models\LabRequest::class)->orderByDesc('created_at');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, ['Waiting', 'Called', 'In Consultation'], true);
    }

    public function waitingMinutes(): int
    {
        return (int) $this->arrived_at->diffInMinutes(now());
    }
}
