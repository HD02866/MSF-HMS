<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabRequestTest extends Model
{
    protected $fillable = [
        'lab_request_id',
        'test_name',
    ];

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    /** The single result recorded for this test (null if not yet entered). */
    public function result(): HasOne
    {
        return $this->hasOne(LabResult::class);
    }
}
