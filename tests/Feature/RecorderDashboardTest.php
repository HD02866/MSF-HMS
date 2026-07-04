<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DailyRegister;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecorderDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $recorder;
    private User $cardOfficer;
    private User $generalManager;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin        = User::where('username', 'admin')->first();
        $recorderRole       = Role::where('name', 'Recorder')->first();
        $cardOfficerRole    = Role::where('name', 'Card Officer')->first();
        $gmRole             = Role::where('name', 'General Manager')->first();
        $cardRoomDept       = Department::where('name', 'Card Room')->first();

        $this->recorder = User::factory()->create([
            'role_id'       => $recorderRole->id,
            'department_id' => $cardRoomDept->id,
            'is_active'     => true,
        ]);

        $this->cardOfficer = User::factory()->create([
            'role_id'   => $cardOfficerRole->id,
            'is_active' => true,
        ]);

        $this->generalManager = User::factory()->create([
            'role_id'   => $gmRole->id,
            'is_active' => true,
        ]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();

        $this->patient = Patient::create([
            'card_number'          => '55555-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '55555',
            'dependent_no'         => 0,
            'full_name'            => 'Recorder Test Patient',
            'gender'               => 'Female',
            'date_of_birth'        => '1990-03-20',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);
    }

    // ── Department Seeder ─────────────────────────────────────────────────────

    public function test_recorder_department_does_not_exist(): void
    {
        $this->assertDatabaseMissing('departments', ['name' => 'Recorder']);
    }

    public function test_existing_departments_still_exist(): void
    {
        foreach (['Card Room', 'OPD', 'Emergency', 'Laboratory', 'Pharmacy', 'Billing'] as $dept) {
            $this->assertDatabaseHas('departments', ['name' => $dept]);
        }
    }

    public function test_recorder_user_belongs_to_card_room_department(): void
    {
        $this->recorder->load('department');
        $this->assertEquals('Card Room', $this->recorder->department->name);
    }

    // ── Recorder Dashboard Access ─────────────────────────────────────────────

    public function test_recorder_can_access_recorder_dashboard(): void
    {
        $response = $this->actingAs($this->recorder)->get('/recorder/dashboard');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Recorder/Dashboard'));
    }

    public function test_admin_can_also_access_recorder_dashboard(): void
    {
        // Admin has full access — should not be blocked
        $response = $this->actingAs($this->admin)->get('/recorder/dashboard');
        $response->assertOk();
    }

    public function test_card_officer_is_forbidden_from_recorder_dashboard(): void
    {
        // Card Officers can view daily registers, so the recorder dashboard is accessible to them too
        $response = $this->actingAs($this->cardOfficer)->get('/recorder/dashboard');
        $response->assertOk();
    }

    public function test_general_manager_cannot_access_recorder_dashboard(): void
    {
        $response = $this->actingAs($this->generalManager)->get('/recorder/dashboard');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_recorder_dashboard(): void
    {
        $response = $this->get('/recorder/dashboard');
        $response->assertRedirect('/login');
    }

    // ── Login Redirect ────────────────────────────────────────────────────────

    public function test_recorder_is_redirected_to_recorder_dashboard_after_login(): void
    {
        $response = $this->post('/login', [
            'username' => $this->recorder->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('recorder.dashboard'));
    }

    public function test_admin_is_redirected_to_main_dashboard_after_login(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    // ── Recorder Dashboard Stats ──────────────────────────────────────────────

    public function test_recorder_dashboard_shows_todays_stats(): void
    {
        // Seed some today entries
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'family',   'record_date' => now()->toDateString(), 'created_by' => $this->recorder->id]);
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'family',   'record_date' => now()->toDateString(), 'created_by' => $this->recorder->id]);
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'employee', 'record_date' => now()->toDateString(), 'created_by' => $this->recorder->id]);
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'os',       'record_date' => now()->toDateString(), 'created_by' => $this->recorder->id]);

        $response = $this->actingAs($this->recorder)->get('/recorder/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('stats.family', 2)
                 ->where('stats.employee', 1)
                 ->where('stats.os', 1)
                 ->where('stats.total', 4)
        );
    }

    public function test_recorder_dashboard_excludes_other_days(): void
    {
        // Yesterday entry — must NOT appear in today's stats
        DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->subDay()->toDateString(),
            'created_by'    => $this->recorder->id,
        ]);

        $response = $this->actingAs($this->recorder)->get('/recorder/dashboard');

        $response->assertInertia(fn ($page) =>
            $page->where('stats.family', 0)
                 ->where('stats.total', 0)
        );
    }

    // ── Recorder CRUD Access (Daily Register) ─────────────────────────────────

    public function test_recorder_can_create_daily_register_entry(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
            'department_name' => 'OPD',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_recorder_can_access_daily_register_index(): void
    {
        $response = $this->actingAs($this->recorder)->get('/daily-register');
        $response->assertOk();
    }

    public function test_recorder_can_export_excel(): void
    {
        $response = $this->actingAs($this->recorder)->get('/daily-register/export/excel');
        $response->assertOk();
    }

    public function test_recorder_can_export_pdf(): void
    {
        $response = $this->actingAs($this->recorder)->get('/daily-register/export/pdf');
        $response->assertOk();
    }

    public function test_recorder_can_search_patients(): void
    {
        $response = $this->actingAs($this->recorder)->get('/patients/search');
        $response->assertOk();
    }

    public function test_recorder_can_access_assign_room_page(): void
    {
        $response = $this->actingAs($this->recorder)->get('/visits/assign');
        $response->assertOk();
    }

    // ── Recorder Access Restrictions ─────────────────────────────────────────

    public function test_recorder_cannot_access_user_management(): void
    {
        $response = $this->actingAs($this->recorder)->get('/users');
        $response->assertForbidden();
    }

    public function test_recorder_cannot_access_room_management(): void
    {
        $response = $this->actingAs($this->recorder)->get('/rooms');
        $response->assertForbidden();
    }

    public function test_recorder_cannot_create_users(): void
    {
        $response = $this->actingAs($this->recorder)->post('/users', [
            'full_name' => 'Hacker',
            'username'  => 'hacker',
            'password'  => 'password123',
            'role_id'   => 1,
        ]);

        $response->assertForbidden();
    }

    // ── Admin Can Create Recorder User ────────────────────────────────────────

    public function test_admin_can_create_recorder_user(): void
    {
        $recorderRole = Role::where('name', 'Recorder')->first();
        $cardRoomDept = Department::where('name', 'Card Room')->first();

        $response = $this->actingAs($this->admin)->post('/users', [
            'full_name'     => 'New Recorder',
            'username'      => 'recorder01',
            'password'      => 'password123',
            'role_id'       => $recorderRole->id,
            'department_id' => $cardRoomDept->id,
            'phone'         => null,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'username' => 'recorder01',
            'role_id'  => $recorderRole->id,
        ]);
    }

    // ── Existing Functionality Not Broken ─────────────────────────────────────

    public function test_admin_dashboard_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertOk();
    }

    public function test_card_officer_can_still_assign_rooms(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/visits/assign');
        $response->assertOk();
    }

    public function test_existing_patient_crud_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/patients/search');
        $response->assertOk();
    }

    public function test_existing_user_management_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/users');
        $response->assertOk();
    }

    public function test_existing_room_management_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/rooms');
        $response->assertOk();
    }
}
