<?php

namespace Tests\Feature;

use App\Models\LabQueue;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
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

class LabQueueTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $labTech;
    private User     $opdNurse;
    private User     $cardOfficer;
    private OpdQueue $queueEntry;
    private Patient  $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin       = User::where('username', 'admin')->first();
        $labTechRole       = Role::where('name', 'Lab Technician')->first();
        $opdNurseRole      = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole   = Role::where('name', 'Card Officer')->first();

        $this->labTech = User::factory()->create([
            'role_id'   => $labTechRole->id,
            'is_active' => true,
        ]);

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
        $opdRoom      = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '55500-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '55500',
            'dependent_no'         => 0,
            'full_name'            => 'Queue Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1985-03-12',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $opdRoom->id,
            'queue_number' => 1,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);
    }

    // ── Helper: create a LabRequest + LabQueue entry via the service ──────────

    private function makeLabRequest(string $priority = 'Normal', array $tests = ['CBC (Complete Blood Count)']): LabQueue
    {
        $labRequest = LabRequest::create([
            'opd_queue_id'   => $this->queueEntry->id,
            'patient_id'     => $this->patient->id,
            'requested_by'   => $this->opdNurse->id,
            'request_date'   => today()->toDateString(),
            'priority'       => $priority,
            'clinical_notes' => null,
        ]);

        foreach ($tests as $test) {
            LabRequestTest::create(['lab_request_id' => $labRequest->id, 'test_name' => $test]);
        }

        return LabQueue::create([
            'lab_request_id' => $labRequest->id,
            'patient_id'     => $this->patient->id,
            'status'         => 'Pending',
        ]);
    }

    // ── 1. Auto-enqueue on lab request submission ─────────────────────────────

    public function test_submitting_lab_request_auto_creates_queue_entry(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['CBC (Complete Blood Count)'],
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
            ]
        );

        $labRequest = LabRequest::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertNotNull($labRequest, 'Lab request should be created');

        $this->assertDatabaseHas('lab_queue', [
            'lab_request_id' => $labRequest->id,
            'patient_id'     => $this->patient->id,
            'status'         => 'Pending',
        ]);
    }

    public function test_urgent_request_is_enqueued_as_pending(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['Malaria Rapid Test (RDT)'],
                'priority'     => 'Urgent',
                'request_date' => today()->toDateString(),
            ]
        );

        $labRequest = LabRequest::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertDatabaseHas('lab_queue', [
            'lab_request_id' => $labRequest->id,
            'status'         => 'Pending',
        ]);
    }

    // ── 2. Queue page access control ──────────────────────────────────────────

    public function test_lab_technician_can_view_queue(): void
    {
        $response = $this->actingAs($this->labTech)->get('/lab/queue');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Lab/Queue')
                ->has('queue')
                ->has('stats')
                ->has('statuses')
                ->has('transitions')
        );
    }

    public function test_admin_can_view_queue(): void
    {
        $response = $this->actingAs($this->admin)->get('/lab/queue');
        $response->assertOk();
    }

    public function test_opd_nurse_can_view_queue(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/lab/queue');
        $response->assertOk();
    }

    public function test_card_officer_cannot_view_queue(): void
    {
        $response = $this->actingAs($this->cardOfficer)->get('/lab/queue');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/lab/queue');
        $response->assertRedirect('/login');
    }

    // ── 3. Queue ordering ─────────────────────────────────────────────────────

    public function test_urgent_requests_appear_before_normal_in_queue(): void
    {
        // Create normal first, then urgent
        $normal = $this->makeLabRequest('Normal');
        $urgent = $this->makeLabRequest('Urgent');

        $response = $this->actingAs($this->labTech)->get('/lab/queue');
        $response->assertOk();

        $queueData = $response->original->getData()['page']['props']['queue']['data'];

        // Urgent should be first
        $this->assertEquals($urgent->id, $queueData[0]['id']);
        $this->assertEquals($normal->id, $queueData[1]['id']);
    }

    public function test_same_priority_entries_ordered_fifo(): void
    {
        $first  = $this->makeLabRequest('Normal');
        // Simulate a slightly later creation time
        $second = $this->makeLabRequest('Normal');

        $response = $this->actingAs($this->labTech)->get('/lab/queue');
        $queueData = $response->original->getData()['page']['props']['queue']['data'];

        $this->assertEquals($first->id, $queueData[0]['id']);
        $this->assertEquals($second->id, $queueData[1]['id']);
    }

    // ── 4. Status management ──────────────────────────────────────────────────

    public function test_lab_tech_can_advance_pending_to_received(): void
    {
        $labQueue = $this->makeLabRequest();

        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Received']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lab_queue', [
            'id'     => $labQueue->id,
            'status' => 'Received',
        ]);

        $this->assertNotNull(LabQueue::find($labQueue->id)->received_at);
    }

    public function test_lab_tech_can_advance_received_to_processing(): void
    {
        $labQueue = $this->makeLabRequest();
        $labQueue->update(['status' => 'Received', 'received_at' => now()]);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Processing']
        );

        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Processing']);
        $this->assertNotNull(LabQueue::find($labQueue->id)->processing_at);
    }

    public function test_lab_tech_can_advance_processing_to_completed(): void
    {
        $labQueue = $this->makeLabRequest();
        $labQueue->update(['status' => 'Processing', 'processing_at' => now()]);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Completed']
        );

        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Completed']);
        $this->assertNotNull(LabQueue::find($labQueue->id)->completed_at);
    }

    public function test_lab_tech_can_cancel_from_pending(): void
    {
        $labQueue = $this->makeLabRequest();

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Cancelled']
        );

        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Cancelled']);
        $this->assertNotNull(LabQueue::find($labQueue->id)->cancelled_at);
    }

    public function test_lab_tech_can_cancel_from_received(): void
    {
        $labQueue = $this->makeLabRequest();
        $labQueue->update(['status' => 'Received', 'received_at' => now()]);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Cancelled']
        );

        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Cancelled']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $labQueue = $this->makeLabRequest(); // Pending

        // Cannot jump directly from Pending to Completed
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Completed']
        );

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Pending']);
    }

    public function test_completed_entry_cannot_be_changed(): void
    {
        $labQueue = $this->makeLabRequest();
        $labQueue->update(['status' => 'Completed', 'completed_at' => now()]);

        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Cancelled']
        );

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Completed']);
    }

    public function test_cancelled_entry_cannot_be_reactivated(): void
    {
        $labQueue = $this->makeLabRequest();
        $labQueue->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Pending']
        );

        $response->assertSessionHasErrors('status');
    }

    public function test_card_officer_cannot_update_status(): void
    {
        $labQueue = $this->makeLabRequest();

        $response = $this->actingAs($this->cardOfficer)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Received']
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('lab_queue', ['id' => $labQueue->id, 'status' => 'Pending']);
    }

    public function test_opd_nurse_cannot_update_lab_queue_status(): void
    {
        // OPD Nurse can VIEW but NOT update the lab queue
        $labQueue = $this->makeLabRequest();

        $response = $this->actingAs($this->opdNurse)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Received']
        );

        $response->assertForbidden();
    }

    public function test_invalid_status_string_is_rejected(): void
    {
        $labQueue = $this->makeLabRequest();

        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'InProgress'] // not a valid status
        );

        $response->assertSessionHasErrors('status');
    }

    // ── 5. Status update metadata ─────────────────────────────────────────────

    public function test_updated_by_is_recorded_on_status_change(): void
    {
        $labQueue = $this->makeLabRequest();

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Received']
        );

        $this->assertDatabaseHas('lab_queue', [
            'id'         => $labQueue->id,
            'updated_by' => $this->labTech->id,
        ]);
    }

    public function test_audit_log_created_on_status_change(): void
    {
        $labQueue = $this->makeLabRequest();

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$labQueue->id}/status",
            ['status' => 'Received']
        );

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Lab Queue Status Updated',
            'user_id' => $this->labTech->id,
        ]);
    }

    // ── 6. Queue stats ────────────────────────────────────────────────────────

    public function test_queue_stats_reflect_current_counts(): void
    {
        $pending    = $this->makeLabRequest('Normal');
        $received   = $this->makeLabRequest('Normal');
        $received->update(['status' => 'Received', 'received_at' => now()]);
        $processing = $this->makeLabRequest('Urgent');
        $processing->update(['status' => 'Processing', 'processing_at' => now()]);

        $response = $this->actingAs($this->labTech)->get('/lab/queue');

        $response->assertInertia(fn ($page) =>
            $page->where('stats.Pending', 1)
                ->where('stats.Received', 1)
                ->where('stats.Processing', 1)
                ->where('stats.total', 3)
        );
    }

    // ── 7. Status filter ──────────────────────────────────────────────────────

    public function test_filtering_by_status_returns_only_matching_entries(): void
    {
        $pending  = $this->makeLabRequest();
        $received = $this->makeLabRequest();
        $received->update(['status' => 'Received', 'received_at' => now()]);

        $response = $this->actingAs($this->labTech)->get('/lab/queue?status=Pending');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->where('filter_status', 'Pending')
                ->where('queue.total', 1)
        );
    }

    public function test_invalid_filter_status_falls_back_to_all(): void
    {
        $this->makeLabRequest();

        $response = $this->actingAs($this->labTech)->get('/lab/queue?status=Bogus');

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->where('filter_status', null)
        );
    }

    // ── 8. Existing functionality not broken ──────────────────────────────────

    public function test_existing_lab_request_submission_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            ['tests' => ['Urinalysis (Routine)'], 'priority' => 'Normal', 'request_date' => today()->toDateString()]
        );

        $response->assertRedirect(route('opd.lab.create', $this->queueEntry));
        $this->assertEquals(1, LabRequest::where('opd_queue_id', $this->queueEntry->id)->count());
        $this->assertEquals(1, LabQueue::whereHas('labRequest', fn ($q) => $q->where('opd_queue_id', $this->queueEntry->id))->count());
    }

    public function test_existing_consultation_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)->get("/opd/consultation/{$this->queueEntry->id}");
        $response->assertOk();
    }
}
