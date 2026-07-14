<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use App\Models\ConsultationRequestNotification;
use App\Models\ConsultationRequestQueue;
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

class ConsultationRequestTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $opdNurse;
    private User     $cardOfficer;
    private User     $deptHead;
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
        $deptHeadRole    = Role::where('name', 'Department Head')->first();

        $this->opdNurse = User::factory()->create([
            'role_id'   => $opdNurseRole->id,
            'is_active' => true,
        ]);

        $this->cardOfficer = User::factory()->create([
            'role_id'   => $cardOfficerRole->id,
            'is_active' => true,
        ]);

        $this->deptHead = User::factory()->create([
            'role_id'   => $deptHeadRole->id,
            'is_active' => true,
        ]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();
        $this->opdRoom = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '55500-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '55500',
            'dependent_no'         => 0,
            'full_name'            => 'Consult Request Patient',
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
            ->get(route('opd.consultation-request.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_admin_can_view_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('opd.consultation-request.create', $this->queueEntry))
            ->assertOk();
    }

    public function test_card_officer_cannot_view_form(): void
    {
        $this->actingAs($this->cardOfficer)
            ->get(route('opd.consultation-request.create', $this->queueEntry))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('opd.consultation-request.create', $this->queueEntry))
            ->assertRedirect();
    }

    // ── Submit request ─────────────────────────────────────────────────────

    public function test_opd_nurse_can_submit_request(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'      => 'Emergency',
                'reason'           => 'Patient has severe chest pain',
                'clinical_summary' => 'BP 160/100, HR 110',
                'priority'         => 'Urgent',
                'request_date'     => today()->toDateString(),
                'requester_name'   => 'Nurse Fatuma',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consultation_requests', [
            'opd_queue_id' => $this->queueEntry->id,
            'patient_id'   => $this->patient->id,
            'destination'  => 'Emergency',
            'reason'       => 'Patient has severe chest pain',
            'priority'     => 'Urgent',
        ]);
    }

    public function test_request_creates_queue_entry(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'MCH',
                'reason'        => 'Prenatal consultation needed',
                'priority'      => 'Normal',
                'request_date'  => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('consultation_request_queue', [
            'patient_id' => $this->patient->id,
            'status'     => 'Pending',
        ]);
    }

    public function test_request_creates_notification(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'Surgery',
                'reason'        => 'Suspected appendicitis',
                'priority'      => 'Normal',
                'request_date'  => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('consultation_request_notifications', [
            'room_id'      => $this->opdRoom->id,
            'patient_id'   => $this->patient->id,
            'event'        => ConsultationRequestNotification::EVENT_SENT,
            'destination'  => 'Surgery',
            'is_read'      => false,
        ]);
    }

    public function test_request_with_signature(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'     => 'Eye Clinic',
                'reason'          => 'Vision problems',
                'priority'        => 'Normal',
                'request_date'    => today()->toDateString(),
                'requester_name'  => 'Dr. Ahmed',
                'signature_data'  => 'data:image/png;base64,abc123',
            ]);

        $request = ConsultationRequest::first();
        $this->assertNotNull($request->signature_data);
        $this->assertEquals('Dr. Ahmed', $request->requester_name);
    }

    // ── Validation ─────────────────────────────────────────────────────────

    public function test_destination_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'reason'       => 'Some reason',
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
            ])
            ->assertSessionHasErrors('destination');
    }

    public function test_reason_is_required(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'Emergency',
                'priority'      => 'Normal',
                'request_date'  => today()->toDateString(),
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_invalid_destination_is_rejected(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'InvalidDept',
                'reason'        => 'Some reason',
                'priority'      => 'Normal',
                'request_date'  => today()->toDateString(),
            ])
            ->assertSessionHasErrors('destination');
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'Emergency',
                'reason'        => 'Some reason',
                'priority'      => 'Critical',
                'request_date'  => today()->toDateString(),
            ])
            ->assertSessionHasErrors('priority');
    }

    public function test_all_destinations_are_valid(): void
    {
        foreach (ConsultationRequest::DESTINATIONS as $dest) {
            $this->actingAs($this->opdNurse)
                ->post(route('opd.consultation-request.store', $this->queueEntry), [
                    'destination'   => $dest,
                    'reason'        => "Request for {$dest}",
                    'priority'      => 'Normal',
                    'request_date'  => today()->toDateString(),
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('consultation_requests', [
                'destination' => $dest,
            ]);
        }
    }

    public function test_audit_log_is_created(): void
    {
        $this->actingAs($this->opdNurse)
            ->post(route('opd.consultation-request.store', $this->queueEntry), [
                'destination'   => 'Internal Medicine',
                'reason'        => 'Cardiac evaluation',
                'priority'      => 'Urgent',
                'request_date'  => today()->toDateString(),
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'Consultation Request Created',
            'table_name' => (new ConsultationRequest)->getTable(),
        ]);
    }

    // ── Prior requests for encounter ───────────────────────────────────────

    public function test_form_shows_prior_requests(): void
    {
        ConsultationRequest::create([
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'requested_by'    => $this->opdNurse->id,
            'destination'     => 'Eye Clinic',
            'reason'          => 'Previous eye issue',
            'priority'        => 'Normal',
            'request_date'    => today()->toDateString(),
        ]);

        $this->actingAs($this->opdNurse)
            ->get(route('opd.consultation-request.create', $this->queueEntry))
            ->assertOk();
    }

    // ── Queue access control ───────────────────────────────────────────────

    public function test_admin_can_view_queue(): void
    {
        $this->actingAs($this->admin)
            ->get(route('consultation-requests.queue.index'))
            ->assertOk();
    }

    public function test_dept_head_can_view_queue(): void
    {
        $this->actingAs($this->deptHead)
            ->get(route('consultation-requests.queue.index'))
            ->assertOk();
    }

    public function test_card_officer_cannot_view_queue(): void
    {
        $this->actingAs($this->cardOfficer)
            ->get(route('consultation-requests.queue.index'))
            ->assertForbidden();
    }

    // ── Queue status updates ───────────────────────────────────────────────

    private function createQueueEntry(string $status = 'Pending'): ConsultationRequestQueue
    {
        $cr = ConsultationRequest::create([
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'requested_by'    => $this->opdNurse->id,
            'destination'     => 'Emergency',
            'reason'          => 'Severe pain',
            'priority'        => 'Urgent',
            'request_date'    => today()->toDateString(),
        ]);

        return ConsultationRequestQueue::create([
            'consultation_request_id' => $cr->id,
            'patient_id'              => $this->patient->id,
            'status'                  => $status,
        ]);
    }

    public function test_admin_can_accept(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Accepted',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consultation_request_queue', [
            'id'     => $entry->id,
            'status' => 'Accepted',
        ]);
    }

    public function test_dept_head_can_reject(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->deptHead)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status'         => 'Rejected',
                'response_notes' => 'No specialist available today',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consultation_request_queue', [
            'id'             => $entry->id,
            'status'         => 'Rejected',
            'response_notes' => 'No specialist available today',
        ]);
    }

    public function test_accept_creates_notification_to_opd_room(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Accepted',
            ]);

        $this->assertDatabaseHas('consultation_request_notifications', [
            'room_id' => $this->opdRoom->id,
            'event'   => ConsultationRequestNotification::EVENT_ACCEPTED,
        ]);
    }

    public function test_completed_creates_notification(): void
    {
        $entry = $this->createQueueEntry('Accepted');

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Completed',
            ]);

        $this->assertDatabaseHas('consultation_request_notifications', [
            'room_id' => $this->opdRoom->id,
            'event'   => ConsultationRequestNotification::EVENT_COMPLETED,
        ]);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $entry = $this->createQueueEntry('Completed');

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Accepted',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_cannot_go_from_rejected_to_anything(): void
    {
        $entry = $this->createQueueEntry('Rejected');

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Completed',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_pending_can_be_cancelled(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Cancelled',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('consultation_request_queue', [
            'id'     => $entry->id,
            'status' => 'Cancelled',
        ]);
    }

    public function test_card_officer_cannot_update_queue(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->cardOfficer)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Accepted',
            ])
            ->assertForbidden();
    }

    public function test_audit_log_on_status_update(): void
    {
        $entry = $this->createQueueEntry();

        $this->actingAs($this->admin)
            ->post(route('consultation-requests.queue.status', $entry), [
                'status' => 'Accepted',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'     => 'Consultation Request Queue Updated',
            'table_name' => (new ConsultationRequestQueue)->getTable(),
        ]);
    }

    // ── Existing features still work ───────────────────────────────────────

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
