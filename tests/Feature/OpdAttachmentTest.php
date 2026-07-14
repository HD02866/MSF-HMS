<?php

namespace Tests\Feature;

use App\Models\OpdAttachment;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpdAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $opdNurse;
    private User $cardOfficer;
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
        $opdRoom      = Room::where('room_code', 'OPD8')->first();

        $patient = Patient::create([
            'card_number'          => '55500-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '55500',
            'dependent_no'         => 0,
            'full_name'            => 'Attachment Test Patient',
            'gender'               => 'Female',
            'date_of_birth'        => '1992-08-12',
            'status'               => 'Active',
            'created_by'           => $this->admin->id,
            'updated_by'           => $this->admin->id,
        ]);

        $visit = Visit::create([
            'patient_id'   => $patient->id,
            'room_id'      => $opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 5,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $patient->id,
            'room_id'      => $opdRoom->id,
            'queue_number' => 5,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_opd_nurse_can_upload_attachment(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('opd_attachments', [
            'opd_queue_id' => $this->queueEntry->id,
            'original_name'=> 'report.pdf',
            'type'         => 'pdf',
            'uploaded_by'  => $this->opdNurse->id,
        ]);
    }

    public function test_admin_can_upload_attachment(): void
    {
        $file = UploadedFile::fake()->create('scan.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->admin)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_attachments', [
            'opd_queue_id' => $this->queueEntry->id,
            'uploaded_by'  => $this->admin->id,
        ]);
    }

    public function test_card_officer_cannot_upload_attachment(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

        $response = $this->actingAs($this->cardOfficer)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('opd_attachments', ['opd_queue_id' => $this->queueEntry->id]);
    }

    public function test_unauthenticated_cannot_upload(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');

        $response = $this->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
            'attachments' => [$file],
        ]);

        $response->assertRedirect('/login');
    }

    // ── Multiple files ────────────────────────────────────────────────────────

    public function test_multiple_files_can_be_uploaded_at_once(): void
    {
        $files = [
            UploadedFile::fake()->create('note1.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('scan1.jpg', 150, 'image/jpeg'),
            UploadedFile::fake()->create('report.txt', 10, 'text/plain'),
        ];

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => $files,
            ]);

        $response->assertRedirect();
        $this->assertEquals(3, OpdAttachment::where('opd_queue_id', $this->queueEntry->id)->count());
    }

    // ── Type detection ────────────────────────────────────────────────────────

    public function test_image_file_gets_image_type(): void
    {
        $file = UploadedFile::fake()->create('photo.png', 120, 'image/png');

        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $this->assertDatabaseHas('opd_attachments', [
            'opd_queue_id' => $this->queueEntry->id,
            'type'         => 'image',
        ]);
    }

    public function test_pdf_file_gets_pdf_type(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $this->assertDatabaseHas('opd_attachments', [
            'opd_queue_id' => $this->queueEntry->id,
            'type'         => 'pdf',
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_upload_without_files_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", []);

        $response->assertSessionHasErrors('attachments');
    }

    public function test_file_over_10mb_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'); // 11 MB

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertSessionHasErrors('attachments.0');
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 50, 'application/octet-stream');

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $response->assertSessionHasErrors('attachments.0');
    }

    // ── Consultation page loads attachments ───────────────────────────────────

    public function test_consultation_page_lists_attachments(): void
    {
        OpdAttachment::create([
            'opd_queue_id'  => $this->queueEntry->id,
            'patient_id'    => $this->queueEntry->patient_id,
            'uploaded_by'   => $this->opdNurse->id,
            'original_name' => 'test-scan.pdf',
            'stored_path'   => 'opd-attachments/test-scan.pdf',
            'mime_type'     => 'application/pdf',
            'file_size'     => 204800,
            'type'          => 'pdf',
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertInertia(fn ($page) =>
            $page->count('attachments', 1)
                 ->where('attachments.0.original_name', 'test-scan.pdf')
                 ->where('attachments.0.type', 'pdf')
        );
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_cannot_download_attachment_belonging_to_different_encounter(): void
    {
        // Create a second encounter
        $opdRoom = Room::where('room_code', 'OPD4')->first();
        $visit2  = Visit::create([
            'patient_id'   => $this->queueEntry->patient_id,
            'room_id'      => $opdRoom->id,
            'assigned_by'  => $this->admin->id,
            'visit_date'   => today()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 9,
            'status'       => 'Assigned',
        ]);
        $otherQueue = OpdQueue::create([
            'visit_id'     => $visit2->id,
            'patient_id'   => $this->queueEntry->patient_id,
            'room_id'      => $opdRoom->id,
            'queue_number' => 9,
            'arrived_at'   => now(),
            'status'       => 'Waiting',
        ]);

        // Attachment belongs to the OTHER encounter
        $att = OpdAttachment::create([
            'opd_queue_id'  => $otherQueue->id,
            'patient_id'    => $this->queueEntry->patient_id,
            'uploaded_by'   => $this->admin->id,
            'original_name' => 'other.pdf',
            'stored_path'   => 'opd-attachments/other.pdf',
            'mime_type'     => 'application/pdf',
            'file_size'     => 1024,
            'type'          => 'pdf',
        ]);

        // Try to download it via the wrong queue entry — should return 404
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/attachments/{$att->id}/download");

        $response->assertNotFound();
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    public function test_audit_log_created_on_upload(): void
    {
        $file = UploadedFile::fake()->create('audit-test.jpg', 100, 'image/jpeg');

        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", [
                'attachments' => [$file],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'OPD Attachment Uploaded',
            'user_id' => $this->opdNurse->id,
        ]);
    }

    // ── Existing functionality not broken ─────────────────────────────────────

    public function test_clinical_notes_still_work_with_attachments_present(): void
    {
        $file = UploadedFile::fake()->create('note.pdf', 50, 'application/pdf');
        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/attachments", ['attachments' => [$file]]);

        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'diagnosis' => 'Still works',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_clinical_notes', ['diagnosis' => 'Still works']);
    }
}
