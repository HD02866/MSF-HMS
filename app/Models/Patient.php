<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'card_number',
        'patient_type_id',
        'relationship_type_id',
        'employee_no',
        'insurance_no',
        'dependent_no',
        'full_name',
        'gender',
        'date_of_birth',
        'phone',
        'address',
        'woreda',
        'kebele',
        'house_no',
        'photo_path',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'dependent_no'  => 'integer',
        ];
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function patientType(): BelongsTo
    {
        return $this->belongsTo(PatientType::class);
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function dailyRegisters(): HasMany
    {
        return $this->hasMany(DailyRegister::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    /**
     * Full public URL for the patient photo.
     * photo_path is stored as a relative web path e.g. "images/patients/patient_abc.jpg"
     * so the URL is simply APP_URL + '/' + photo_path.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/'.$this->photo_path;
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }
}
