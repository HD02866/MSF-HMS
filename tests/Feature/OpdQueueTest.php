<?php

namespace Tests\Feature;

use App\Models\OpdNotification;
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

class OpdQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $opdNurse;
    private User $cardOfficer;
    private Patient $patient;
    private Room $opdRoom;
    private Room $nonOpdRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin       = User::where('username', 'admin')->first();
        $opdNurseRole      = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole   = Role::where('name', 'Card Officer')->first();

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
            'card_number'          => '99999-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '99999',
            'dependent_no'         => 0,
            'full_name'            => 'OPD Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1990-01-01',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $this->opdRoom    = Room::where('room_code', 'OPD4')->first();
        $this->nonOpdRoom = Room::where('room_code', 'DOCTOR')->first();
    }

    // ── Seeder ────────────────────────────────────────────────────────────────

    public function test_opd_nurse_role_is_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['name' => 'OPD Nurse']);
    }

    // ── Dashboard access ──────────────────────────────────────────────────────

    public function test_opd_nurse_can_access_opd_dashboard(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/opd/dashboard');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('OPD/Dashboard'));
    }

    public function test_admin_can_access_opd_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/opd/dashboard');
        $response->assertOk();
    }

    public function test_card_officer_cannot_access_opd_dashboard(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/opd/dashboard');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/opd/dashboard');
        $response->assertRedirect('/login');
    }

    // ── Auto-enqueue on room assignment ───────────────────────────────────────

    public function test_assigning_opd_room_creates_queue_entry(): void
    {
        $response = $this->actingAs($this->admin)->post('/visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->opdRoom->id,
        ]);

        $response->assertRedirect(route('visits.register'));

        $this->assertDatabaseHas('opd_queue', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->opdRoom->id,
            'status'     => 'Waiting',
        ]);
    }

    public function test_assigning_opd_room_creates_notification(): void
    {
        $this->actingAs($this->admin)->post('/visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->opdRoom->id,
        ]);

        $this->assertDatabaseHas('opd_notifications', [
            'room_id'      => $this->opdRoom->id,
            'patient_id'   => $this->patient->id,
            'card_number'  => $this->patient->card_number,
            'is_read'      => false,
        ]);
    }

    public function test_assigning_non_opd_room_does_not_create_queue_entry(): void
    {
        $this->actingAs($this->admin)->post('/visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->nonOpdRoom->id,
        ]);

        $this->assertDatabaseMissing('opd_queue', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->nonOpdRoom->id,
        ]);
    }

    // ── Queue entry creation ──────────────────────────────────────────────────

    private function createQueueEntry(string $status = 'Waiting'): OpdQueue
    {
        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => now()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        return OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 1,
            'arrived_at'   => now(),
            'status'       => $status,
        ]);
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function test_opd_nurse_can_call_patient(): void
    {
        $entry = $this->createQueueEntry('Waiting');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/queue/{$entry->id}/status", ['status' => 'Called']);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $entry->id,
            'status' => 'Called',
        ]);
    }

    public function test_opd_nurse_can_start_consultation(): void
    {
        $entry = $this->createQueueEntry('Called');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/queue/{$entry->id}/status", ['status' => 'In Consultation']);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $entry->id,
            'status' => 'In Consultation',
        ]);
    }

    public function test_opd_nurse_can_complete_patient(): void
    {
        $entry = $this->createQueueEntry('In Consultation');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/queue/{$entry->id}/status", ['status' => 'Completed']);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $entry->id,
            'status' => 'Completed',
        ]);
    }

    public function test_card_officer_cannot_update_queue_status(): void
    {
        $entry = $this->createQueueEntry('Waiting');

        $response = $this->actingAs($this->cardOfficer)
            ->post("/opd/queue/{$entry->id}/status", ['status' => 'Called']);

        $response->assertForbidden();
    }

    public function test_invalid_status_rejected(): void
    {
        $entry = $this->createQueueEntry('Waiting');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/queue/{$entry->id}/status", ['status' => 'INVALID']);

        $response->assertSessionHasErrors('status');
    }

    // ── Call next (FIFO) ──────────────────────────────────────────────────────

    public function test_call_next_picks_lowest_queue_number(): void
    {
        $visit1 = Visit::create(['patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'assigned_by' => $this->admin->id, 'visit_date' => today(), 'visit_time' => now()->toTimeString(), 'queue_number' => 1, 'status' => 'Assigned']);
        $visit2 = Visit::create(['patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'assigned_by' => $this->admin->id, 'visit_date' => today(), 'visit_time' => now()->toTimeString(), 'queue_number' => 2, 'status' => 'Assigned']);

        $entry1 = OpdQueue::create(['visit_id' => $visit1->id, 'patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'queue_number' => 1, 'arrived_at' => now(), 'status' => 'Waiting']);
        $entry2 = OpdQueue::create(['visit_id' => $visit2->id, 'patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'queue_number' => 2, 'arrived_at' => now(), 'status' => 'Waiting']);

        $this->actingAs($this->opdNurse)
            ->post('/opd/queue/call-next', ['room_id' => $this->opdRoom->id]);

        // Queue #1 should be called first (FIFO)
        $this->assertDatabaseHas('opd_queue', ['id' => $entry1->id, 'status' => 'Called']);
        $this->assertDatabaseHas('opd_queue', ['id' => $entry2->id, 'status' => 'Waiting']);
    }

    public function test_call_next_returns_error_when_no_waiting(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post('/opd/queue/call-next', ['room_id' => $this->opdRoom->id]);

        $response->assertSessionHas('error');
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    public function test_mark_notifications_read(): void
    {
        $visit = Visit::create(['patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'assigned_by' => $this->admin->id, 'visit_date' => today(), 'visit_time' => now()->toTimeString(), 'queue_number' => 1, 'status' => 'Assigned']);
        $entry = OpdQueue::create(['visit_id' => $visit->id, 'patient_id' => $this->patient->id, 'room_id' => $this->opdRoom->id, 'queue_number' => 1, 'arrived_at' => now(), 'status' => 'Waiting']);
        OpdNotification::create(['opd_queue_id' => $entry->id, 'room_id' => $this->opdRoom->id, 'patient_id' => $this->patient->id, 'patient_name' => $this->patient->full_name, 'card_number' => $this->patient->card_number, 'queue_number' => 1, 'assignment_time' => now(), 'is_read' => false]);

        $this->actingAs($this->opdNurse)
            ->post('/opd/notifications/mark-read', ['room_id' => $this->opdRoom->id]);

        $this->assertDatabaseMissing('opd_notifications', [
            'room_id' => $this->opdRoom->id,
            'is_read' => false,
        ]);
    }

    // ── Login redirect ────────────────────────────────────────────────────────

    public function test_opd_nurse_redirected_to_opd_dashboard_after_login(): void
    {
        $response = $this->post('/login', [
            'username' => $this->opdNurse->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('opd.dashboard'));
    }

    // ── Existing functionality not broken ─────────────────────────────────────

    public function test_existing_visit_assignment_still_works(): void
    {
        $response = $this->actingAs($this->admin)->post('/visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->nonOpdRoom->id,
        ]);

        $response->assertRedirect(route('visits.register'));
        $this->assertDatabaseHas('visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $this->nonOpdRoom->id,
        ]);
    }

    public function test_existing_admin_dashboard_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertOk();
    }

    public function test_existing_patient_search_still_works(): void
    {
        $response = $this->actingAs($this->admin)->get('/patients/search');
        $response->assertOk();
    }
}
