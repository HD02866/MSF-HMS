<?php

namespace Tests\Feature;

use App\Models\DailyRegister;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\Referral;
use App\Models\ReferralNotification;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralTest extends TestCase
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
            'card_number'          => '56000-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '56000',
            'dependent_no'         => 0,
            'full_name'            => 'Referral Test Patient',
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
            ->get(route('opd.referral.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_admin_can_view_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('opd.referral.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_card_officer_cannot_view_form(): void
    {
        $this->actingAs($this->cardOfficer)
            ->get(route('opd.referral.create', $this->queueEntry))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('opd.referral.create', $this->queueEntry))
            ->assertRedirect();
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function test_opd_nurse_can_submit_referral(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Surgery',
                'reason'            => 'Patient needs surgical evaluation',
                'diagnosis'         => 'Appendicitis',
                'doctor_nurse_name' => 'Dr. Ahmed',
                'date'              => today()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('referrals', [
            'patient_id'  => $this->patient->id,
            'destination' => 'Surgery',
            'reason'      => 'Patient needs surgical evaluation',
            'diagnosis'   => 'Appendicitis',
        ]);
    }

    public function test_referral_creates_daily_register_entry(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Emergency',
                'reason'            => 'Chest pain',
                'diagnosis'         => 'Suspected MI',
                'doctor_nurse_name' => 'Dr. Smith',
                'date'              => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('daily_registers', [
            'patient_id'      => $this->patient->id,
            'register_type'   => 'referral_accident',
            'department_name' => 'Emergency',
        ]);
    }

    public function test_referral_creates_notification(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Eye Clinic',
                'reason'            => 'Vision problems',
                'diagnosis'         => 'Conjunctivitis',
                'doctor_nurse_name' => 'Dr. Lee',
                'date'              => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('referral_notifications', [
            'patient_id'   => $this->patient->id,
            'record_type'  => 'referral',
            'opd_room_id'  => $this->opdRoom->id,
            'type'         => 'referral_created',
        ]);
    }

    public function test_audit_log_is_created(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'MCH',
                'reason'            => 'Prenatal care',
                'diagnosis'         => 'Pregnancy',
                'doctor_nurse_name' => 'Dr. Fatima',
                'date'              => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'Referral Created',
            'table_name' => (new Referral)->getTable(),
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_destination_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'reason'            => 'Test',
                'diagnosis'         => 'Test',
                'doctor_nurse_name' => 'Dr. Test',
                'date'              => today()->toDateString(),
            ])
            ->assertSessionHasErrors('destination');
    }

    public function test_reason_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Surgery',
                'diagnosis'         => 'Test',
                'doctor_nurse_name' => 'Dr. Test',
                'date'              => today()->toDateString(),
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_diagnosis_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Surgery',
                'reason'            => 'Test',
                'doctor_nurse_name' => 'Dr. Test',
                'date'              => today()->toDateString(),
            ])
            ->assertSessionHasErrors('diagnosis');
    }

    public function test_doctor_nurse_name_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination' => 'Surgery',
                'reason'      => 'Test',
                'diagnosis'   => 'Test',
                'date'        => today()->toDateString(),
            ])
            ->assertSessionHasErrors('doctor_nurse_name');
    }

    public function test_invalid_destination_is_rejected(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.referral.store', $this->queueEntry), [
                'destination'       => 'Nonexistent',
                'reason'            => 'Test',
                'diagnosis'         => 'Test',
                'doctor_nurse_name' => 'Dr. Test',
                'date'              => today()->toDateString(),
            ])
            ->assertSessionHasErrors('destination');
    }

    public function test_all_destinations_are_valid(): void
    {
        foreach (Referral::DESTINATIONS as $dest) {
            $this->assertDatabaseCount('referrals', 0);

            $this->actingAs($this->opdNurse)
                ->post(route('opd.referral.store', $this->queueEntry), [
                    'destination'       => $dest,
                    'reason'            => 'Test for '.$dest,
                    'diagnosis'         => 'Test diagnosis',
                    'doctor_nurse_name' => 'Dr. Test',
                    'date'              => today()->toDateString(),
                ]);

            $this->assertDatabaseHas('referrals', [
                'destination' => $dest,
            ]);

            Referral::query()->delete();
            DailyRegister::query()->delete();
            ReferralNotification::query()->delete();
        }
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
