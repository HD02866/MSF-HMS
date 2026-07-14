<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabQueue extends Model
{
    protected $table = 'lab_queue';

    protected $fillable = [
        'lab_request_id',
        'patient_id',
        'status',
        'updated_by',
        'received_at',
        'processing_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at'   => 'datetime',
            'processing_at' => 'datetime',
            'completed_at'  => 'datetime',
            'cancelled_at'  => 'datetime',
        ];
    }

    // ── Status constants ────────────────────────────────────────────────────

    public const STATUSES = [
        'Pending'    => 'Pending',
        'Received'   => 'Received',
        'Processing' => 'Processing',
        'Completed'  => 'Completed',
        'Cancelled'  => 'Cancelled',
    ];

    /**
     * Which statuses are considered "active" (not terminal).
     */
    public const ACTIVE_STATUSES = ['Pending', 'Received', 'Processing'];

    /**
     * Allowed forward transitions for each status.
     * Cancellation is always allowed from any active status.
     */
    public const TRANSITIONS = [
        'Pending'    => ['Received', 'Cancelled'],
        'Received'   => ['Processing', 'Cancelled'],
        'Processing' => ['Completed', 'Cancelled'],
        'Completed'  => [],
        'Cancelled'  => [],
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
