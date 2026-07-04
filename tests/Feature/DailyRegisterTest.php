<?php

namespace Tests\Feature;

use App\Models\DailyRegister;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyRegisterTest extends TestCase
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

        $this->admin = User::where('username', 'admin')->first();

        $recorderRole = Role::where('name', 'Recorder')->first();
        $cardOfficerRole = Role::where('name', 'Card Officer')->first();
        $gmRole = Role::where('name', 'General Manager')->first();

        $this->recorder = User::factory()->create([
            'role_id' => $recorderRole->id,
            'is_active' => true,
        ]);

        $this->cardOfficer = User::factory()->create([
            'role_id' => $cardOfficerRole->id,
            'is_active' => true,
        ]);

        $this->generalManager = User::factory()->create([
            'role_id' => $gmRole->id,
            'is_active' => true,
        ]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel = RelationshipType::where('name', 'Employee')->first();

        $this->patient = Patient::create([
            'card_number'          => '12345-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '12345',
            'dependent_no'         => 0,
            'full_name'            => 'Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1985-06-15',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);
    }

    // ── View Access ──────────────────────────────────────────────────────────

    public function test_admin_can_view_daily_register_index(): void
    {
        $response = $this->actingAs($this->admin)->get('/daily-register');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('DailyRegister/Index'));
    }

    public function test_recorder_can_view_daily_register_index(): void
    {
        $response = $this->actingAs($this->recorder)->get('/daily-register');
        $response->assertOk();
    }

    public function test_card_officer_can_view_daily_register_index(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/daily-register');
        $response->assertOk();
    }

    public function test_general_manager_cannot_view_daily_register(): void
    {
        $response = $this->actingAs($this->generalManager)->get('/daily-register');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/daily-register');
        $response->assertRedirect('/login');
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_recorder_can_create_family_register_entry(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'family',
            'record_date'     => now()->toDateString(),
            'department_name' => 'OPD',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('daily_registers', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'created_by'    => $this->recorder->id,
        ]);
    }

    public function test_admin_can_create_employee_register_entry(): void
    {
        $response = $this->actingAs($this->admin)->post('/daily-register', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'employee',
            'record_date'     => now()->toDateString(),
            'department_name' => 'Emergency',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('daily_registers', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'employee',
            'department_name' => 'Emergency',
        ]);
    }

    public function test_recorder_can_create_os_entry_without_department(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'os',
            'record_date'   => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('daily_registers', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'os',
            'department_name' => null,
        ]);
    }

    public function test_card_officer_cannot_create_register_entry(): void
    {
        $response = $this->actingAs($this->cardOfficer)->post('/daily-register', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', []);
        $response->assertSessionHasErrors(['patient_id', 'register_type', 'record_date']);
    }

    public function test_store_validates_patient_exists(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'    => 99999,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('patient_id');
    }

    public function test_store_validates_invalid_register_type(): void
    {
        $response = $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'invalid_type',
            'record_date'   => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('register_type');
    }

    public function test_audit_log_is_created_on_register_entry(): void
    {
        $this->actingAs($this->recorder)->post('/daily-register', [
            'patient_id'    => $this->patient->id,
            'register_type' => 'referral_accident',
            'record_date'   => now()->toDateString(),
            'department_name' => 'Emergency',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Daily Register Created',
            'user_id' => $this->recorder->id,
        ]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_recorder_can_update_register_entry(): void
    {
        $entry = DailyRegister::create([
            'patient_id'      => $this->patient->id,
            'register_type'   => 'family',
            'record_date'     => now()->toDateString(),
            'department_name' => 'OPD',
            'created_by'      => $this->recorder->id,
        ]);

        $response = $this->actingAs($this->recorder)->put("/daily-register/{$entry->id}", [
            'register_type'   => 'employee',
            'record_date'     => now()->toDateString(),
            'department_name' => 'Emergency',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('daily_registers', [
            'id'              => $entry->id,
            'register_type'   => 'employee',
            'department_name' => 'Emergency',
        ]);
    }

    public function test_card_officer_cannot_update_register_entry(): void
    {
        $entry = DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
            'created_by'    => $this->admin->id,
        ]);

        $response = $this->actingAs($this->cardOfficer)->put("/daily-register/{$entry->id}", [
            'register_type' => 'employee',
            'record_date'   => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_recorder_can_delete_register_entry(): void
    {
        $entry = DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'os',
            'record_date'   => now()->toDateString(),
            'created_by'    => $this->recorder->id,
        ]);

        $response = $this->actingAs($this->recorder)->delete("/daily-register/{$entry->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('daily_registers', ['id' => $entry->id]);
    }

    public function test_admin_can_delete_register_entry(): void
    {
        $entry = DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
            'created_by'    => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete("/daily-register/{$entry->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('daily_registers', ['id' => $entry->id]);
    }

    public function test_card_officer_cannot_delete_register_entry(): void
    {
        $entry = DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'family',
            'record_date'   => now()->toDateString(),
            'created_by'    => $this->admin->id,
        ]);

        $response = $this->actingAs($this->cardOfficer)->delete("/daily-register/{$entry->id}");
        $response->assertForbidden();
    }

    // ── Filtering / Summary ───────────────────────────────────────────────────

    public function test_index_defaults_to_today_date(): void
    {
        $response = $this->actingAs($this->admin)->get('/daily-register');
        $response->assertInertia(fn ($page) =>
            $page->where('filters.record_date', now()->toDateString())
        );
    }

    public function test_summary_counts_match_database(): void
    {
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'family',   'record_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'family',   'record_date' => now()->toDateString(), 'created_by' => $this->admin->id]);
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'employee', 'record_date' => now()->toDateString(), 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->get('/daily-register?record_date='.now()->toDateString());

        $response->assertInertia(fn ($page) =>
            $page->where('summary.family', 2)
                 ->where('summary.employee', 1)
                 ->where('summary.total', 3)
        );
    }

    // ── Export ────────────────────────────────────────────────────────────────

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

    public function test_general_manager_cannot_export(): void
    {
        $response = $this->actingAs($this->generalManager)->get('/daily-register/export/excel');
        $response->assertForbidden();
    }

    // ── Existing Functionality Not Broken ─────────────────────────────────────

    public function test_existing_patient_crud_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/patients/search');
        $response->assertOk();
    }

    public function test_existing_visit_register_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/visits/register');
        $response->assertOk();
    }

    public function test_existing_rooms_management_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/rooms');
        $response->assertOk();
    }

    public function test_existing_user_management_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/users');
        $response->assertOk();
    }

    public function test_existing_reports_still_work(): void
    {
        $response = $this->actingAs($this->admin)->get('/reports');
        $response->assertOk();
    }
}
