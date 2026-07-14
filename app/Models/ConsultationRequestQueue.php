<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationRequestQueue extends Model
{
    protected $table = 'consultation_request_queue';

    protected $fillable = [
        'consultation_request_id',
        'patient_id',
        'status',
        'updated_by',
        'response_notes',
        'accepted_at',
        'rejected_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at'  => 'datetime',
            'rejected_at'  => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    // ── Status constants ────────────────────────────────────────────────────

    public const STATUSES = [
        'Pending'   => 'Pending',
        'Accepted'  => 'Accepted',
        'Rejected'  => 'Rejected',
        'Completed' => 'Completed',
        'Cancelled' => 'Cancelled',
    ];

    public const ACTIVE_STATUSES = ['Pending', 'Accepted'];

    public const TRANSITIONS = [
        'Pending'   => ['Accepted', 'Rejected', 'Cancelled'],
        'Accepted'  => ['Completed', 'Cancelled'],
        'Rejected'  => [],
        'Completed' => [],
        'Cancelled' => [],
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function consultationRequest(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class);
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
