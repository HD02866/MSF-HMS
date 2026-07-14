<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyRequestItem extends Model
{
    protected $fillable = [
        'pharmacy_request_id',
        'medicine_id',
        'medicine_name',
        'dosage',
        'frequency',
        'duration',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function pharmacyRequest(): BelongsTo
    {
        return $this->belongsTo(PharmacyRequest::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
