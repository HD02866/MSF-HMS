<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyQueue extends Model
{
    protected $table = 'pharmacy_queue';

    protected $fillable = [
        'pharmacy_request_id',
        'patient_id',
        'status',
        'updated_by',
        'dispensed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'dispensed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ── Status constants ──────────────────────────────────────────────────────
    public const STATUSES = [
        'Pending'   => 'Pending',
        'Dispensed' => 'Dispensed',
        'Cancelled' => 'Cancelled',
    ];

    public const TRANSITIONS = [
        'Pending'   => ['Dispensed', 'Cancelled'],
        'Dispensed' => [],
        'Cancelled' => [],
    ];

    public const ACTIVE_STATUSES = ['Pending'];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function pharmacyRequest(): BelongsTo
    {
        return $this->belongsTo(PharmacyRequest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
