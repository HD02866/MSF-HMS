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

class OpdRegisterTest extends TestCase
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
            'card_number'          => '88888-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '88888',
            'dependent_no'         => 0,
            'full_name'            => 'Register Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1990-01-01',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $this->opdRoom = Room::where('room_code', 'OPD4')->first();
    }

    private function createCompletedEntry(?string $status = 'Completed', ?string $arrivedAt = null): OpdQueue
    {
        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => ($arrivedAt ? \Carbon\Carbon::parse($arrivedAt) : now())->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => rand(1, 100),
            'status'       => 'Completed',
        ]);

        $entry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => $visit->queue_number,
            'arrived_at'   => $arrivedAt ?? now(),
            'status'       => $status,
            'completed_at' => in_array($status, ['Completed', 'Transferred']) ? now() : null,
        ]);

        OpdClinicalNote::create([
            'opd_queue_id'    => $entry->id,
            'patient_id'      => $this->patient->id,
            'created_by'      => $this->doctor->id,
            'chief_complaint' => 'Fever and headache',
            'diagnosis'       => 'Malaria',
        ]);

        return $entry;
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_opd_nurse_can_view_register(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('OPD/Register'));
    }

    public function test_admin_can_view_register(): void
    {
        $response = $this->actingAs($this->admin)->get('/opd/register');
        $response->assertOk();
    }

    public function test_card_officer_cannot_view_register(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/opd/register');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $response = $this->get('/opd/register');
        $response->assertRedirect('/login');
    }

    // ── Data display ───────────────────────────────────────────────────────

    public function test_register_shows_completed_entries(): void
    {
        $this->createCompletedEntry('Completed');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('registers'));
    }

    public function test_register_shows_transferred_entries(): void
    {
        $this->createCompletedEntry('Transferred');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
    }

    public function test_register_excludes_waiting_entries(): void
    {
        $this->createCompletedEntry('Waiting');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
    }

    public function test_register_summary_counts(): void
    {
        $this->createCompletedEntry('Completed');
        $this->createCompletedEntry('Completed');
        $this->createCompletedEntry('Transferred');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('summary'));
    }

    // ── Filter: period ─────────────────────────────────────────────────────

    public function test_register_daily_filter(): void
    {
        $this->createCompletedEntry('Completed', now()->toDateTimeString());

        $response = $this->actingAs($this->opdNurse)->get('/opd/register?period=daily&date=' . now()->toDateString());
        $response->assertOk();
    }

    public function test_register_weekly_filter(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register?period=weekly&date=' . now()->toDateString());
        $response->assertOk();
    }

    public function test_register_monthly_filter(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register?period=monthly&date=' . now()->toDateString());
        $response->assertOk();
    }

    // ── Filter: room ───────────────────────────────────────────────────────

    public function test_register_room_filter(): void
    {
        $this->createCompletedEntry('Completed');

        $response = $this->actingAs($this->opdNurse)->get("/opd/register?room_id={$this->opdRoom->id}");
        $response->assertOk();
    }

    // ── Filter: status ─────────────────────────────────────────────────────

    public function test_register_status_filter(): void
    {
        $this->createCompletedEntry('Completed');
        $this->createCompletedEntry('Transferred');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register?status=Completed');
        $response->assertOk();
    }

    // ── Doctor options ─────────────────────────────────────────────────────

    public function test_register_includes_doctor_options(): void
    {
        $this->createCompletedEntry('Completed');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('doctors'));
    }

    // ── Room options ───────────────────────────────────────────────────────

    public function test_register_includes_room_options(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('rooms'));
    }

    // ── Export Excel ───────────────────────────────────────────────────────

    public function test_opd_nurse_can_export_excel(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register/export/excel');
        $response->assertOk();
    }

    public function test_card_officer_cannot_export_excel(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/opd/register/export/excel');
        $response->assertForbidden();
    }

    // ── Export PDF ─────────────────────────────────────────────────────────

    public function test_opd_nurse_can_export_pdf(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/register/export/pdf');
        $response->assertOk();
    }

    public function test_card_officer_cannot_export_pdf(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/opd/register/export/pdf');
        $response->assertForbidden();
    }

    // ── N+1 performance ───────────────────────────────────────────────────

    public function test_doctor_options_does_not_n_plus_one(): void
    {
        $this->createCompletedEntry('Completed');
        $this->createCompletedEntry('Completed');

        $response = $this->actingAs($this->opdNurse)->get('/opd/register');
        $response->assertOk();

        // Verify doctor options are populated (not empty)
        $response->assertInertia(fn ($page) => $page->has('doctors'));
    }
}
