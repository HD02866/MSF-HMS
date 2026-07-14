<?php

namespace Tests\Feature;

use App\Models\LabNotification;
use App\Models\LabQueue;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
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

class LabNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $labTech;
    private User     $opdNurse;
    private Patient  $patient;
    private Room     $opdRoom;
    private OpdQueue $opdQueueEntry;
    private LabQueue $labQueue;
    private LabRequest $labRequest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin    = User::where('username', 'admin')->first();
        $labTechRole    = Role::where('name', 'Lab Technician')->first();
        $opdNurseRole   = Role::where('name', 'OPD Nurse')->first();

        $this->labTech  = User::factory()->create(['role_id' => $labTechRole->id, 'is_active' => true]);
        $this->opdNurse = User::factory()->create(['role_id' => $opdNurseRole->id, 'is_active' => true]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();
        $this->opdRoom = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '77700-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '77700',
            'dependent_no'         => 0,
            'full_name'            => 'Notification Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1987-09-01',
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
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        $this->opdQueueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 1,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);

        $this->labRequest = LabRequest::create([
            'opd_queue_id'   => $this->opdQueueEntry->id,
            'patient_id'     => $this->patient->id,
            'requested_by'   => $this->opdNurse->id,
            'request_date'   => today()->toDateString(),
            'priority'       => 'Normal',
            'clinical_notes' => null,
        ]);

        LabRequestTest::create(['lab_request_id' => $this->labRequest->id, 'test_name' => 'CBC (Complete Blood Count)']);
        LabRequestTest::create(['lab_request_id' => $this->labRequest->id, 'test_name' => 'Malaria Rapid Test (RDT)']);

        $this->labQueue = LabQueue::create([
            'lab_request_id' => $this->labRequest->id,
            'patient_id'     => $this->patient->id,
            'status'         => 'Pending',
        ]);
    }

    // ── 1. Notification creation on status transitions ─────────────────────

    public function test_advancing_to_received_creates_lab_notification(): void
    {
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $this->assertDatabaseHas('lab_notifications', [
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_RECEIVED,
            'is_read'        => false,
        ]);
    }

    public function test_advancing_to_completed_creates_lab_notification(): void
    {
        $this->labQueue->update(['status' => 'Processing', 'processing_at' => now()]);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Completed']
        );

        $this->assertDatabaseHas('lab_notifications', [
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'event'          => LabNotification::EVENT_COMPLETED,
            'is_read'        => false,
        ]);
    }

    public function test_advancing_to_processing_does_not_create_notification(): void
    {
        $this->labQueue->update(['status' => 'Received', 'received_at' => now()]);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Processing']
        );

        $this->assertEquals(0, LabNotification::where('lab_request_id', $this->labRequest->id)->count());
    }

    public function test_cancelling_does_not_create_notification(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Cancelled']
        );

        $this->assertEquals(0, LabNotification::where('lab_request_id', $this->labRequest->id)->count());
    }

    // ── 2. Notification content ────────────────────────────────────────────

    public function test_notification_contains_patient_name_and_card_number(): void
    {
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $notification = LabNotification::where('lab_request_id', $this->labRequest->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals($this->patient->full_name, $notification->patient_name);
        $this->assertEquals($this->patient->card_number, $notification->card_number);
    }

    public function test_notification_contains_test_names(): void
    {
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $notification = LabNotification::where('lab_request_id', $this->labRequest->id)->first();
        $this->assertIsArray($notification->test_names);
        $this->assertContains('CBC (Complete Blood Count)', $notification->test_names);
        $this->assertContains('Malaria Rapid Test (RDT)', $notification->test_names);
    }

    public function test_notification_contains_notification_date(): void
    {
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $notification = LabNotification::where('lab_request_id', $this->labRequest->id)->first();
        $this->assertNotNull($notification->notified_at);
    }

    public function test_notification_starts_as_unread(): void
    {
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $notification = LabNotification::where('lab_request_id', $this->labRequest->id)->first();
        $this->assertFalse($notification->is_read);
    }

    public function test_notification_is_sent_to_the_originating_opd_room(): void
    {
        // The lab request originated from opdRoom (OPD4)
        // The notification must go to that same room
        $this->labQueue->update(['status' => 'Pending']);

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/status",
            ['status' => 'Received']
        );

        $notification = LabNotification::where('lab_request_id', $this->labRequest->id)->first();
        $this->assertEquals($this->opdRoom->id, $notification->room_id);
    }

    // ── 3. Notification display on OPD dashboard ──────────────────────────

    public function test_lab_notifications_appear_in_opd_dashboard_notifications(): void
    {
        // Create a lab notification directly
        LabNotification::create([
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_RECEIVED,
            'test_names'     => ['CBC (Complete Blood Count)'],
            'notified_at'    => now(),
            'is_read'        => false,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/dashboard?room_id={$this->opdRoom->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('notifications')
        );

        $notifications = $response->original->getData()['page']['props']['notifications'];
        $labNotif = collect($notifications)->first(fn ($n) => str_starts_with((string) $n['id'], 'lab-'));

        $this->assertNotNull($labNotif, 'Lab notification should appear in OPD dashboard');
        $this->assertEquals($this->patient->full_name, $labNotif['patient_name']);
        $this->assertFalse($labNotif['is_read']);
    }

    public function test_opd_and_lab_notifications_merged_on_dashboard(): void
    {
        // Create both types
        OpdNotification::create([
            'opd_queue_id'    => $this->opdQueueEntry->id,
            'room_id'         => $this->opdRoom->id,
            'patient_id'      => $this->patient->id,
            'patient_name'    => $this->patient->full_name,
            'card_number'     => $this->patient->card_number,
            'queue_number'    => 1,
            'assignment_time' => now()->subMinutes(5),
            'is_read'         => false,
        ]);

        LabNotification::create([
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_COMPLETED,
            'test_names'     => ['CBC (Complete Blood Count)'],
            'notified_at'    => now(),
            'is_read'        => false,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/dashboard?room_id={$this->opdRoom->id}");

        $notifications = $response->original->getData()['page']['props']['notifications'];
        $this->assertGreaterThanOrEqual(2, count($notifications));
    }

    public function test_unread_count_includes_lab_notifications(): void
    {
        // Create one unread lab notification
        LabNotification::create([
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_COMPLETED,
            'test_names'     => ['CBC (Complete Blood Count)'],
            'notified_at'    => now(),
            'is_read'        => false,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/dashboard?room_id={$this->opdRoom->id}");

        $stats = $response->original->getData()['page']['props']['stats'];
        $this->assertGreaterThanOrEqual(1, $stats['unread']);
    }

    // ── 4. Read / Unread support ───────────────────────────────────────────

    public function test_mark_all_read_marks_lab_notifications_as_read(): void
    {
        LabNotification::create([
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_RECEIVED,
            'test_names'     => ['CBC (Complete Blood Count)'],
            'notified_at'    => now(),
            'is_read'        => false,
        ]);

        $this->actingAs($this->opdNurse)->post('/opd/notifications/mark-read', [
            'room_id' => $this->opdRoom->id,
        ]);

        $this->assertDatabaseHas('lab_notifications', [
            'lab_request_id' => $this->labRequest->id,
            'is_read'        => true,
        ]);
    }

    public function test_mark_all_read_marks_opd_notifications_as_read_too(): void
    {
        OpdNotification::create([
            'opd_queue_id'    => $this->opdQueueEntry->id,
            'room_id'         => $this->opdRoom->id,
            'patient_id'      => $this->patient->id,
            'patient_name'    => $this->patient->full_name,
            'card_number'     => $this->patient->card_number,
            'queue_number'    => 1,
            'assignment_time' => now(),
            'is_read'         => false,
        ]);

        $this->actingAs($this->opdNurse)->post('/opd/notifications/mark-read', [
            'room_id' => $this->opdRoom->id,
        ]);

        $this->assertDatabaseHas('opd_notifications', [
            'room_id' => $this->opdRoom->id,
            'is_read' => true,
        ]);
    }

    public function test_unread_count_drops_to_zero_after_mark_all_read(): void
    {
        LabNotification::create([
            'lab_request_id' => $this->labRequest->id,
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'patient_name'   => $this->patient->full_name,
            'card_number'    => $this->patient->card_number,
            'event'          => LabNotification::EVENT_RECEIVED,
            'test_names'     => ['Malaria Rapid Test (RDT)'],
            'notified_at'    => now(),
            'is_read'        => false,
        ]);

        // Mark all read
        $this->actingAs($this->opdNurse)->post('/opd/notifications/mark-read', [
            'room_id' => $this->opdRoom->id,
        ]);

        // Reload dashboard — unread should be 0
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/dashboard?room_id={$this->opdRoom->id}");

        $stats = $response->original->getData()['page']['props']['stats'];
        $this->assertEquals(0, $stats['unread']);
    }

    // ── 5. Previous OPD notification functionality not broken ─────────────

    public function test_existing_opd_notifications_still_work(): void
    {
        OpdNotification::create([
            'opd_queue_id'    => $this->opdQueueEntry->id,
            'room_id'         => $this->opdRoom->id,
            'patient_id'      => $this->patient->id,
            'patient_name'    => $this->patient->full_name,
            'card_number'     => $this->patient->card_number,
            'queue_number'    => 1,
            'assignment_time' => now(),
            'is_read'         => false,
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/dashboard?room_id={$this->opdRoom->id}");

        $response->assertOk();

        $notifications = $response->original->getData()['page']['props']['notifications'];
        $opdNotif = collect($notifications)->first(fn ($n) => str_starts_with((string) $n['id'], 'opd-'));
        $this->assertNotNull($opdNotif, 'OPD notification should still appear');
        $this->assertEquals($this->patient->full_name, $opdNotif['patient_name']);
    }

    public function test_auto_complete_via_results_also_fires_completed_notification(): void
    {
        // Set to Processing so results can be saved
        $this->labQueue->update(['status' => 'Processing', 'processing_at' => now()]);

        $tests = $this->labRequest->tests;

        // Save all test results — triggers auto-complete, which fires Completed notification
        $results = [];
        foreach ($tests as $t) {
            $results[$t->id] = ['result' => 'Normal', 'remarks' => '', 'result_date' => today()->toDateString()];
        }

        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            ['results' => $results]
        );

        $this->assertDatabaseHas('lab_notifications', [
            'lab_request_id' => $this->labRequest->id,
            'event'          => LabNotification::EVENT_COMPLETED,
        ]);
    }

    public function test_lab_queue_page_still_works(): void
    {
        $response = $this->actingAs($this->labTech)->get('/lab/queue');
        $response->assertOk();
    }

    public function test_opd_queue_status_update_does_not_create_lab_notifications(): void
    {
        // Ensure OPD queue status changes never touch lab_notifications
        $initialCount = LabNotification::count();

        $this->actingAs($this->opdNurse)->post(
            "/opd/queue/{$this->opdQueueEntry->id}/status",
            ['status' => 'Completed']
        );

        $this->assertEquals($initialCount, LabNotification::count());
    }
}
