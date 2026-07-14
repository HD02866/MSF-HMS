<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpdAttachment extends Model
{
    protected $fillable = [
        'opd_queue_id',
        'patient_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    // ── Type constants ──────────────────────────────────────────────────────

    public const TYPES = ['image', 'pdf', 'document', 'other'];

    // ── Relationships ───────────────────────────────────────────────────────

    public function opdQueue(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    /** Full public URL for downloading the attachment */
    public function getUrlAttribute(): string
    {
        return rtrim(config('app.url'), '/').'/'.$this->stored_path;
    }

    /** Human-readable file size */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1).' KB';
        return round($bytes / 1048576, 1).' MB';
    }

    /** Determine type from MIME type */
    public static function resolveType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if ($mimeType === 'application/pdf') return 'pdf';
        if (in_array($mimeType, ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'], true)) return 'document';
        return 'other';
    }
}
