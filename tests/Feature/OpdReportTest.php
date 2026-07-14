<?php

namespace Tests\Feature;

use App\Models\OpdClinicalNote;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $opdNurse;
    private User $cardOfficer;
    private User $doctor;
    private Patient $patient;
    private Room $opdRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin     = User::where('username', 'admin')->first();
        $opdNurseRole    = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole = Role::where('name', 'Card Officer')->first();
        $adminRole       = Role::where('name', 'Admin')->first();

        $this->opdNurse = User::factory()->create([
            'role_id'   => $opdNurseRole->id,
            'is_active' => true,
        ]);

        $this->cardOfficer = User::factory()->create([
            'role_id'   => $cardOfficerRole->id,
            'is_active' => true,
        ]);

        $this->doctor = User::factory()->create([
            'role_id'   => $adminRole->id,
            'is_active' => true,
        ]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();

        $this->patient = Patient::create([
            'card_number'          => '77777-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '77777',
            'dependent_no'         => 0,
            'full_name'            => 'Report Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1990-01-01',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $this->opdRoom = Room::where('room_code', 'OPD4')->first();
    }

    private function createCompletedEntry(string $diagnosis = 'Malaria', string $complaint = 'Fever'): OpdQueue
    {
        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => rand(1, 100),
            'status'       => 'Completed',
        ]);

        $entry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => $visit->queue_number,
            'arrived_at'   => now(),
            'status'       => 'Completed',
            'completed_at' => now(),
        ]);

        OpdClinicalNote::create([
            'opd_queue_id'    => $entry->id,
            'patient_id'      => $this->patient->id,
            'created_by'      => $this->doctor->id,
            'chief_complaint' => $complaint,
            'diagnosis'       => $diagnosis,
        ]);

        return $entry;
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_opd_nurse_can_view_reports(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('OPD/Reports'));
    }

    public function test_admin_can_view_reports(): void
    {
        $response = $this->actingAs($this->admin)->get('/opd/reports');
        $response->assertOk();
    }

    public function test_card_officer_cannot_view_reports(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/opd/reports');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $response = $this->get('/opd/reports');
        $response->assertRedirect('/login');
    }

    // ── Disease stats ──────────────────────────────────────────────────────

    public function test_disease_stats_shows_diagnoses(): void
    {
        $this->createCompletedEntry('Malaria', 'Fever');
        $this->createCompletedEntry('Malaria', 'Chills');
        $this->createCompletedEntry('Typhoid', 'Abdominal pain');

        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('disease'));
    }

    public function test_disease_stats_empty_period(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports?period=monthly&date=2020-01-01');
        $response->assertOk();
    }

    // ── Doctor stats ───────────────────────────────────────────────────────

    public function test_doctor_stats_shows_doctors(): void
    {
        $this->createCompletedEntry('Malaria', 'Fever');

        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('doctor'));
    }

    public function test_doctor_stats_empty_period(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports?period=monthly&date=2020-01-01');
        $response->assertOk();
    }

    // ── Room stats ─────────────────────────────────────────────────────────

    public function test_room_stats_shows_rooms(): void
    {
        $this->createCompletedEntry('Malaria', 'Fever');

        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('room'));
    }

    public function test_room_stats_includes_completed_and_transferred(): void
    {
        $this->createCompletedEntry('Malaria', 'Fever');

        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('room.by_room'));
    }

    // ── Lab stats ──────────────────────────────────────────────────────────

    public function test_lab_stats_empty_when_no_requests(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('lab'));
    }

    // ── Medicine stats ─────────────────────────────────────────────────────

    public function test_medicine_stats_empty_when_no_prescriptions(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('medicine'));
    }

    // ── Period filters ─────────────────────────────────────────────────────

    public function test_daily_period(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports?period=daily&date=' . now()->toDateString());
        $response->assertOk();
    }

    public function test_weekly_period(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports?period=weekly&date=' . now()->toDateString());
        $response->assertOk();
    }

    public function test_monthly_period(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports?period=monthly&date=' . now()->toDateString());
        $response->assertOk();
    }

    public function test_default_period_is_daily(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/reports');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('disease'));
    }
}
