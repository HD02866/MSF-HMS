<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralNotification extends Model
{
    protected $table = 'referral_notifications';

    protected $fillable = [
        'type',
        'record_id',
        'record_type',
        'patient_id',
        'patient_name',
        'card_number',
        'opd_room_id',
        'is_read',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'     => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    // ── Event constants ─────────────────────────────────────────────────────

    public const EVENT_REFERRAL_CREATED    = 'referral_created';
    public const EVENT_SICK_LEAVE_CREATED  = 'sick_leave_created';

    public const EVENT_LABELS = [
        self::EVENT_REFERRAL_CREATED   => '🏥 Referral Created',
        self::EVENT_SICK_LEAVE_CREATED => '📄 Sick Leave Created',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function opdRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'opd_room_id');
    }
}
