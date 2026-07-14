<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'role_id',
        'department_id',
        'phone',
        'avatar_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedVisits(): HasMany
    {
        return $this->hasMany(Visit::class, 'assigned_by');
    }

    public function dailyRegisters(): HasMany
    {
        return $this->hasMany(DailyRegister::class, 'created_by');
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    /**
     * Full public URL for the user avatar.
     * Stored as a relative path: "images/avatars/avatar_xxx.jpg"
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return rtrim(config('app.url'), '/').'/'.$this->avatar_path;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function canManagePatients(): bool
    {
        return $this->hasRole('Admin', 'Card Officer', 'Department Head');
    }

    public function canViewReportsOnly(): bool
    {
        return $this->hasRole('General Manager');
    }
}
