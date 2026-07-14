<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    protected $fillable = [
        'lab_request_id',
        'lab_request_test_id',
        'patient_id',
        'performed_by',
        'result',
        'remarks',
        'result_date',
    ];

    protected function casts(): array
    {
        return [
            'result_date' => 'date',
        ];
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function labRequestTest(): BelongsTo
    {
        return $this->belongsTo(LabRequestTest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
