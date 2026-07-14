<?php

namespace Tests\Feature;

use App\Models\DailyRegister;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\ReferralNotification;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\SickLeave;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SickLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $opdNurse;
    private User     $cardOfficer;
    private OpdQueue $queueEntry;
    private Patient  $patient;
    private Room     $opdRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin     = User::where('username', 'admin')->first();
        $opdNurseRole    = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole = Role::where('name', 'Card Officer')->first();

        $this->opdNurse = User::factory()->create([
            'role_id'   => $opdNurseRole->id,
            'is_active' => true,
        ]);

        $this->cardOfficer = User::factory()->create([
            'role_id'   => $cardOfficerRole->id,
            'is_active' => true,
        ]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();
        $this->opdRoom = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '57000-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '57000',
            'dependent_no'         => 0,
            'full_name'            => 'Sick Leave Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1990-01-15',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 5,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 5,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
        ]);
    }

    // ── Access control: Form ───────────────────────────────────────────────

    public function test_opd_nurse_can_view_form(): void
    {
        $this->actingAs($this->opdNurse)
            ->get(route('opd.sick-leave.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_admin_can_view_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('opd.sick-leave.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_card_officer_cannot_view_form(): void
    {
        $this->actingAs($this->cardOfficer)
            ->get(route('opd.sick-leave.create', $this->queueEntry))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('opd.sick-leave.create', $this->queueEntry))
            ->assertRedirect();
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function test_opd_nurse_can_submit_sick_leave(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'John Employee',
                'days'          => 3,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->addDays(2)->toDateString(),
                'diagnosis'     => 'Common cold',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sick_leaves', [
            'patient_id'    => $this->patient->id,
            'employee_name' => 'John Employee',
            'days'          => 3,
            'diagnosis'     => 'Common cold',
        ]);
    }

    public function test_sick_leave_creates_daily_register_entry(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Jane Worker',
                'days'          => 5,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->addDays(4)->toDateString(),
                'diagnosis'     => 'Flu',
            ]);

        $this->assertDatabaseHas('daily_registers', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'referral_sick_leave',
            'days_given'      => 5,
        ]);
    }

    public function test_sick_leave_creates_notification(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test Employee',
                'days'          => 2,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->addDay()->toDateString(),
                'diagnosis'     => 'Migraine',
            ]);

        $this->assertDatabaseHas('referral_notifications', [
            'patient_id'   => $this->patient->id,
            'record_type'  => 'sick_leave',
            'opd_room_id'  => $this->opdRoom->id,
            'type'         => 'sick_leave_created',
        ]);
    }

    public function test_audit_log_is_created(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Audit Employee',
                'days'          => 1,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->toDateString(),
                'diagnosis'     => 'Toothache',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'Sick Leave Created',
            'table_name' => (new SickLeave)->getTable(),
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_employee_name_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'days'       => 3,
                'start_date' => today()->toDateString(),
                'end_date'   => today()->addDays(2)->toDateString(),
                'diagnosis'  => 'Test',
            ])
            ->assertSessionHasErrors('employee_name');
    }

    public function test_days_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->addDays(2)->toDateString(),
                'diagnosis'     => 'Test',
            ])
            ->assertSessionHasErrors('days');
    }

    public function test_days_must_be_at_least_1(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'days'          => 0,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->toDateString(),
                'diagnosis'     => 'Test',
            ])
            ->assertSessionHasErrors('days');
    }

    public function test_start_date_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'days'          => 3,
                'end_date'      => today()->addDays(2)->toDateString(),
                'diagnosis'     => 'Test',
            ])
            ->assertSessionHasErrors('start_date');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'days'          => 3,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->subDay()->toDateString(),
                'diagnosis'     => 'Test',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_diagnosis_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'days'          => 3,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->addDays(2)->toDateString(),
            ])
            ->assertSessionHasErrors('diagnosis');
    }

    public function test_end_date_can_be_same_as_start_date(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.sick-leave.store', $this->queueEntry), [
                'employee_name' => 'Test',
                'days'          => 1,
                'start_date'    => today()->toDateString(),
                'end_date'      => today()->toDateString(),
                'diagnosis'     => 'Same day leave',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sick_leaves', [
            'employee_name' => 'Test',
            'days'          => 1,
        ]);
    }

    // ── Existing features still work ──────────────────────────────────────

    public function test_existing_consultation_page_still_works(): void
    {
        $this->actingAs($this->opdNurse)
            ->get(route('opd.consultation.show', $this->queueEntry))
            ->assertOk();
    }

    public function test_existing_complete_consultation_still_works(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation.complete', $this->queueEntry), [
                'status' => 'Completed',
            ])
            ->assertRedirect();
    }
}
