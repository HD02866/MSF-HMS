<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Caches slow-changing reference data that is queried on almost every page.
 *
 * TTL: 1 hour.  Cache is busted explicitly whenever a record is mutated.
 * Never cache: patients, visits, daily_registers.
 */
class ReferenceDataService
{
    private const TTL = 3600; // 1 hour in seconds

    // ── Rooms ──────────────────────────────────────────────────────────────

    public function activeRooms(): Collection
    {
        return Cache::remember('ref.rooms.active', self::TTL, fn () =>
            Room::where('is_active', true)
                ->orderBy('room_name')
                ->get(['id', 'room_name', 'room_code'])
        );
    }

    public function allRooms(): Collection
    {
        return Cache::remember('ref.rooms.all', self::TTL, fn () =>
            Room::orderBy('room_name')->get()
        );
    }

    public function activeRoomNames(): Collection
    {
        return Cache::remember('ref.rooms.names', self::TTL, fn () =>
            Room::where('is_active', true)->orderBy('room_name')->pluck('room_name')
        );
    }

    public function bustRooms(): void
    {
        Cache::forget('ref.rooms.active');
        Cache::forget('ref.rooms.all');
        Cache::forget('ref.rooms.names');
    }

    // ── Patient Types ──────────────────────────────────────────────────────

    public function activePatientTypes(): Collection
    {
        return Cache::remember('ref.patient_types.active', self::TTL, fn () =>
            PatientType::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function activePatientTypeNames(): Collection
    {
        return Cache::remember('ref.patient_types.names', self::TTL, fn () =>
            PatientType::where('is_active', true)->orderBy('name')->pluck('name')
        );
    }

    public function bustPatientTypes(): void
    {
        Cache::forget('ref.patient_types.active');
        Cache::forget('ref.patient_types.names');
    }

    // ── Relationship Types ─────────────────────────────────────────────────

    public function relationshipTypes(): Collection
    {
        return Cache::remember('ref.relationship_types', self::TTL, fn () =>
            RelationshipType::orderBy('name')->get(['id', 'name'])
        );
    }

    public function bustRelationshipTypes(): void
    {
        Cache::forget('ref.relationship_types');
    }

    // ── Departments ────────────────────────────────────────────────────────

    public function activeDepartments(): Collection
    {
        return Cache::remember('ref.departments.active', self::TTL, fn () =>
            Department::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public function bustDepartments(): void
    {
        Cache::forget('ref.departments.active');
    }

    // ── Roles ──────────────────────────────────────────────────────────────

    public function roles(): Collection
    {
        return Cache::remember('ref.roles', self::TTL, fn () =>
            Role::orderBy('name')->get(['id', 'name'])
        );
    }

    public function bustRoles(): void
    {
        Cache::forget('ref.roles');
    }
}
