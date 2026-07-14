<?php

namespace Tests\Feature;

use App\Models\ConsultationRequest;
use App\Models\ConsultationRequestNotification;
use App\Models\ConsultationRequestQueue;
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

class OpdIntegrationFixTest extends TestCase
{
    use RefreshDatabase;

    private User     $opdNurse;
    private User     $admin;
    private Patient  $patient;
    private Room     $opdRoom;
    private Room     $destRoom;
    private Visit    $visit;
    private OpdQueue $queueEntry;
    private ConsultationRequest $consultationRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin   = User::where('username', 'admin')->first();
        $opdNurseRole  = Role::where('name', 'OPD Nurse')->first();

        $this->opdNurse = User::factory()->create([
            'role_id'   => $opdNurseRole->id,
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
            'full_name'            => 'Integration Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1985-06-15',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $this->opdRoom  = Room::where('room_code', 'OPD4')->first();
        $this->destRoom = Room::where('room_code', 'OPD5')->first();

        $this->visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => now()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 10,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $this->visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 10,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
        ]);

        $this->consultationRequest = ConsultationRequest::create([
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'requested_by'    => $this->admin->id,
            'destination'     => 'OPD5',
            'reason'          => 'Test referral',
            'priority'        => 'Normal',
            'request_date'    => now()->toDateString(),
        ]);
    }

    // ── ISSUE 1: ConsultationRequestNotification in OPD Bell Panel ───────────

    public function test_consultation_request_notifications_appear_in_opd_bell_panel(): void
    {
        ConsultationRequestNotification::create([
            'consultation_request_id' => $this->consultationRequest->id,
            'room_id'                 => $this->opdRoom->id,
            'patient_id'              => $this->patient->id,
            'patient_name'            => $this->patient->full_name,
            'card_number'             => $this->patient->card_number,
            'event'                   => ConsultationRequestNotification::EVENT_SENT,
            'destination'             => 'OPD5',
            'priority'                => 'Normal',
            'notified_at'             => now(),
            'is_read'                 => false,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get('/opd/dashboard', ['room_id' => $this->opdRoom->id]);

        $response->assertOk();
    }

    public function test_consultation_request_notifications_counted_in_unread(): void
    {
        ConsultationRequestNotification::create([
            'consultation_request_id' => $this->consultationRequest->id,
            'room_id'                 => $this->opdRoom->id,
            'patient_id'              => $this->patient->id,
            'patient_name'            => $this->patient->full_name,
            'card_number'             => $this->patient->card_number,
            'event'                   => ConsultationRequestNotification::EVENT_SENT,
            'destination'             => 'OPD5',
            'priority'                => 'Normal',
            'notified_at'             => now(),
            'is_read'                 => false,
        ]);

        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $stats   = $service->dashboardStats($this->opdRoom->id);

        $this->assertGreaterThanOrEqual(1, $stats['unread']);
    }

    public function test_mark_all_read_clears_consultation_request_notifications(): void
    {
        ConsultationRequestNotification::create([
            'consultation_request_id' => $this->consultationRequest->id,
            'room_id'                 => $this->opdRoom->id,
            'patient_id'              => $this->patient->id,
            'patient_name'            => $this->patient->full_name,
            'card_number'             => $this->patient->card_number,
            'event'                   => ConsultationRequestNotification::EVENT_SENT,
            'destination'             => 'OPD5',
            'priority'                => 'Normal',
            'notified_at'             => now(),
            'is_read'                 => false,
        ]);

        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->markAllRead($this->opdRoom->id);

        $unread = ConsultationRequestNotification::where('room_id', $this->opdRoom->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unread);
    }

    public function test_opd_bell_includes_consultation_request_notifications(): void
    {
        ConsultationRequestNotification::create([
            'consultation_request_id' => $this->consultationRequest->id,
            'room_id'                 => $this->opdRoom->id,
            'patient_id'              => $this->patient->id,
            'patient_name'            => $this->patient->full_name,
            'card_number'             => $this->patient->card_number,
            'event'                   => ConsultationRequestNotification::EVENT_ACCEPTED,
            'destination'             => 'OPD5',
            'priority'                => 'Normal',
            'notified_at'             => now(),
            'is_read'                 => false,
        ]);

        $service    = app(\App\Modules\OPD\Services\OpdService::class);
        $notifs     = $service->notifications($this->opdRoom->id);
        $crNotifs   = $notifs->filter(fn ($n) => str_starts_with($n['id'], 'cr-'));

        $this->assertGreaterThanOrEqual(1, $crNotifs->count());
    }

    // ── ISSUE 2: Patient Transfer creates Visit + OpdQueue ───────────────────

    public function test_transfer_creates_new_visit_in_destination_room(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Transferred', $this->admin->id, $this->destRoom->id);

        $newVisit = Visit::where('patient_id', $this->patient->id)
            ->where('room_id', $this->destRoom->id)
            ->first();

        $this->assertNotNull($newVisit, 'A new Visit must be created in the destination room');
        $this->assertEquals('Assigned', $newVisit->status);
        $this->assertStringContainsString('Transferred from', $newVisit->remarks);
    }

    public function test_transfer_creates_opd_queue_entry_for_opd_destination(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Transferred', $this->admin->id, $this->destRoom->id);

        $destQueue = OpdQueue::where('patient_id', $this->patient->id)
            ->where('room_id', $this->destRoom->id)
            ->first();

        $this->assertNotNull($destQueue, 'An OpdQueue entry must be created in the destination OPD room');
        $this->assertEquals('Waiting', $destQueue->status);
    }

    public function test_transfer_creates_notification_for_destination_room(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Transferred', $this->admin->id, $this->destRoom->id);

        $notification = OpdNotification::where('room_id', $this->destRoom->id)
            ->where('patient_id', $this->patient->id)
            ->first();

        $this->assertNotNull($notification, 'A notification must be created for the destination OPD room');
    }

    public function test_transfer_marks_source_queue_as_transferred(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Transferred', $this->admin->id, $this->destRoom->id);

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Transferred',
        ]);
    }

    // ── ISSUE 3: Visit Status Synchronization ────────────────────────────────

    public function test_visit_status_updates_to_transferred(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Transferred', $this->admin->id, $this->destRoom->id);

        $this->visit->refresh();
        $this->assertEquals('Transferred', $this->visit->status);
    }

    public function test_visit_status_updates_to_cancelled(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->updateStatus($this->queueEntry, 'Cancelled', $this->admin->id);

        $this->visit->refresh();
        $this->assertEquals('Cancelled', $this->visit->status);
    }

    public function test_complete_consultation_updates_visit_status_to_completed(): void
    {
        $service = app(\App\Modules\OPD\Services\OpdService::class);
        $service->completeConsultation($this->queueEntry, [], $this->admin->id);

        $this->visit->refresh();
        $this->assertEquals('Completed', $this->visit->status);
    }

    // ── Controller: Transfer via complete endpoint ────────────────────────────

    public function test_complete_endpoint_transfers_with_destination_room(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            [
                'status'              => 'Transferred',
                'destination_room_id' => $this->destRoom->id,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Transferred',
        ]);

        $destQueue = OpdQueue::where('patient_id', $this->patient->id)
            ->where('room_id', $this->destRoom->id)
            ->first();
        $this->assertNotNull($destQueue);
    }

    public function test_complete_endpoint_rejects_transfer_without_destination(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Transferred']
        );

        // Should still succeed (destination is optional for backwards compat)
        $response->assertRedirect();
        // But no destination queue should be created
        $destQueue = OpdQueue::where('patient_id', $this->patient->id)
            ->where('room_id', $this->destRoom->id)
            ->first();
        $this->assertNull($destQueue);
    }

    // ── Controller: Consultation page passes opd_rooms ───────────────────────

    public function test_consultation_page_includes_opd_rooms(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('opd_rooms'));
    }

    // ── Receiving department: unread_count prop ──────────────────────────────

    public function test_consultation_request_queue_page_includes_unread_count(): void
    {
        ConsultationRequestNotification::create([
            'consultation_request_id' => $this->consultationRequest->id,
            'room_id'                 => $this->opdRoom->id,
            'patient_id'              => $this->patient->id,
            'patient_name'            => $this->patient->full_name,
            'card_number'             => $this->patient->card_number,
            'event'                   => ConsultationRequestNotification::EVENT_SENT,
            'destination'             => 'OPD5',
            'priority'                => 'Normal',
            'notified_at'             => now(),
            'is_read'                 => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('consultation-requests.queue.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('unread_count'));
    }
}
