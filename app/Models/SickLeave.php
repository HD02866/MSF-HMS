<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SickLeave extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'requested_by',
        'employee_name',
        'days',
        'start_date',
        'end_date',
        'diagnosis',
        'recommendation',
        'signature_data',
    ];

    protected function casts(): array
    {
        return [
            'days'       => 'integer',
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

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
