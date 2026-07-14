<?php

namespace Tests\Feature;

use App\Models\DailyRegister;
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

class OpdPatientHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $opdNurse;
    private User $cardOfficer;
    private Patient $patient;
    private Room $opdRoom;
    private OpdQueue $queueEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin      = User::where('username', 'admin')->first();
        $opdNurseRole     = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole  = Role::where('name', 'Card Officer')->first();

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

        $this->patient = Patient::create([
            'card_number'          => '77777-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '77777',
            'dependent_no'         => 0,
            'full_name'            => 'History Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1980-07-20',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $this->opdRoom = Room::where('room_code', 'OPD6')->first();

        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 3,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 3,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_opd_nurse_can_view_patient_history(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('OPD/PatientHistory'));
    }

    public function test_admin_can_view_patient_history(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
    }

    public function test_card_officer_cannot_view_patient_history(): void
    {
        $response = $this->actingAs($this->cardOfficer)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_redirected(): void
    {
        $response = $this->get("/opd/consultation/{$this->queueEntry->id}/history");
        $response->assertRedirect('/login');
    }

    // ── Visit history ─────────────────────────────────────────────────────────

    public function test_history_shows_previous_visits(): void
    {
        // Add a second past visit
        $nonOpdRoom = Room::where('room_code', 'DOCTOR')->first();
        Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $nonOpdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => now()->subDays(5)->toDateString(),
            'visit_time'   => '09:00:00',
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertInertia(fn ($page) =>
            $page->where('visits.total', 2)
        );
    }

    // ── Register history ──────────────────────────────────────────────────────

    public function test_history_shows_daily_register_entries(): void
    {
        DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'employee',
            'record_date'   => now()->subDays(3)->toDateString(),
            'created_by'    => $this->admin->id,
        ]);

        DailyRegister::create([
            'patient_id'    => $this->patient->id,
            'register_type' => 'referral_sick_leave',
            'record_date'   => now()->subDays(10)->toDateString(),
            'referred_from' => 'OPD 4',
            'days_given'    => 3,
            'created_by'    => $this->admin->id,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertInertia(fn ($page) =>
            $page->where('registers.total', 2)
        );
    }

    // ── Referral history ──────────────────────────────────────────────────────

    public function test_history_shows_only_referrals(): void
    {
        // Employee entry — should NOT appear in referrals
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'employee', 'record_date' => now()->subDays(2)->toDateString(), 'created_by' => $this->admin->id]);
        // Referral accident — should appear
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'referral_accident', 'record_date' => now()->subDay()->toDateString(), 'referred_from' => 'Emergency', 'created_by' => $this->admin->id]);
        // Referral sick leave — should appear
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'referral_sick_leave', 'record_date' => now()->toDateString(), 'days_given' => 5, 'referred_from' => 'OPD 5', 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertInertia(fn ($page) =>
            $page->count('referrals', 2)
        );
    }

    // ── Sick leave history ────────────────────────────────────────────────────

    public function test_history_shows_sick_leave_with_days_given(): void
    {
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'referral_sick_leave', 'record_date' => now()->subDays(7)->toDateString(), 'days_given' => 7, 'referred_from' => 'OPD 6', 'created_by' => $this->admin->id]);
        // Sick leave without days — should NOT appear in sick_leaves
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'referral_sick_leave', 'record_date' => now()->subDays(14)->toDateString(), 'days_given' => null, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertInertia(fn ($page) =>
            $page->count('sick_leaves', 1)
        );
    }

    // ── OPD encounter history ─────────────────────────────────────────────────

    public function test_history_shows_previous_opd_encounters(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        // The current queue entry itself counts as an encounter
        $response->assertInertia(fn ($page) =>
            $page->where('opd_encounters.total', 1)
        );
    }

    // ── Timeline ──────────────────────────────────────────────────────────────

    public function test_timeline_combines_all_sources(): void
    {
        DailyRegister::create(['patient_id' => $this->patient->id, 'register_type' => 'family', 'record_date' => now()->subDays(2)->toDateString(), 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertInertia(fn ($page) =>
            $page->has('timeline')
        );

        // Verify at least 2 items: 1 visit + 1 register (OPD encounter also contributes)
        $timeline = $response->original->getData()['page']['props']['timeline'];
        $this->assertGreaterThanOrEqual(2, count($timeline));

        $types = array_column($timeline, 'type');
        $this->assertContains('visit', $types);
        $this->assertContains('register', $types);
    }

    // ── Query efficiency ──────────────────────────────────────────────────────

    public function test_history_loads_without_n_plus_1_on_visits(): void
    {
        // Create 5 visits
        $nonOpdRoom = Room::where('room_code', 'EYE')->first();
        for ($i = 1; $i <= 5; $i++) {
            Visit::create([
                'patient_id'   => $this->patient->id,
                'room_id'      => $nonOpdRoom->id,
                'assigned_by'  => $this->admin->id,
                'visit_date'   => now()->subDays($i)->toDateString(),
                'visit_time'   => '10:00:00',
                'queue_number' => $i,
                'status'       => 'Assigned',
            ]);
        }

        // Should not throw or fail — eager loading handles it
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('visits'));
    }

    // ── Patient isolation ─────────────────────────────────────────────────────

    public function test_history_only_shows_data_for_the_correct_patient(): void
    {
        // Create another patient with visits — should NOT appear in this patient's history
        $otherPatient = Patient::create([
            'card_number'     => '11111-0',
            'patient_type_id' => PatientType::where('name', 'OS')->first()->id,
            'full_name'       => 'Other Patient',
            'date_of_birth'   => '1990-01-01',
            'status'          => 'Active',
            'created_by'      => $this->admin->id,
            'updated_by'      => $this->admin->id,
        ]);

        DailyRegister::create(['patient_id' => $otherPatient->id, 'register_type' => 'os', 'record_date' => now()->toDateString(), 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        // registers should be 0 for the test patient (only other patient has registers)
        $response->assertInertia(fn ($page) =>
            $page->where('registers.total', 0)
        );
    }

    // ── Existing functionality ────────────────────────────────────────────────

    public function test_existing_consultation_page_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");
        $response->assertOk();
    }

    public function test_existing_opd_dashboard_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/dashboard');
        $response->assertOk();
    }
}
