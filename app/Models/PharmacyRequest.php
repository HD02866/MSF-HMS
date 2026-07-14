<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PharmacyRequest extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'prescribed_by',
        'prescriber_name',
        'request_date',
        'clinical_notes',
        'is_external',
        'external_notes',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'is_external'  => 'boolean',
        ];
    }

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyRequestItem::class);
    }

    public function pharmacyQueue(): HasOne
    {
        return $this->hasOne(PharmacyQueue::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PharmacyNotification::class);
    }
}
