<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpdClinicalNote extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'created_by',
        'chief_complaint',
        'history',
        'physical_examination',
        'diagnosis',
        'treatment_plan',
        'follow_up_instructions',
        'temperature',
        'systolic_bp',
        'diastolic_bp',
        'pulse_rate',
        'respiratory_rate',
        'spo2',
        'weight',
        'height',
        'bmi',
        'random_blood_sugar',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** True if at least one vital sign is recorded. */
    public function hasVitalSigns(): bool
    {
        return (bool) (
            $this->temperature ||
            $this->systolic_bp ||
            $this->diastolic_bp ||
            $this->pulse_rate ||
            $this->respiratory_rate ||
            $this->spo2 ||
            $this->weight ||
            $this->height ||
            $this->random_blood_sugar
        );
    }

    /** True if at least one clinical field or vital sign has content. */
    public function hasContent(): bool
    {
        return (bool) (
            $this->chief_complaint ||
            $this->history ||
            $this->physical_examination ||
            $this->diagnosis ||
            $this->treatment_plan ||
            $this->follow_up_instructions ||
            $this->hasVitalSigns()
        );
    }
}
