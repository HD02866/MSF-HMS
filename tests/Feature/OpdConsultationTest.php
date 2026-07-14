<?php

namespace Tests\Feature;

use App\Models\OpdAttachment;
use App\Models\OpdClinicalNote;
use App\Models\OpdQueue;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpdConsultationTest extends TestCase
{
    use RefreshDatabase;

    private User    $opdNurse;
    private Patient $patient;
    private Room    $opdRoom;
    private Visit   $visit;
    private OpdQueue $queueEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->opdNurse = User::where('username', 'admin')->first();

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
            'date_of_birth'        => '1985-06-15',
            'status'               => 'Active',
            'created_by'           => $this->opdNurse->id,
        ]);

        $this->opdRoom = Room::where('room_code', 'OPD4')->first();

        $this->visit = Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->opdNurse->id,
            'visit_date'   => now()->toDateString(),
            'visit_time'   => now()->toTimeString(),
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $this->visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 1,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
        ]);
    }

    // ── 1. Encounter Save Logic ────────────────────────────────────────────────

    public function test_opd_nurse_can_save_clinical_note_for_encounter(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/notes",
            [
                'chief_complaint'        => 'Severe headache',
                'history'                => 'No prior conditions',
                'physical_examination'   => 'BP 120/80',
                'diagnosis'              => 'Tension headache',
                'treatment_plan'         => 'Paracetamol 500mg',
                'follow_up_instructions' => 'Return in 7 days if symptoms persist',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'chief_complaint' => 'Severe headache',
            'diagnosis'       => 'Tension headache',
        ]);
    }

    public function test_saving_note_creates_new_record_not_overwrite(): void
    {
        // Save first note
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/notes",
            ['chief_complaint' => 'Initial complaint', 'diagnosis' => 'Diagnosis A']
        );

        // Save second note
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/notes",
            ['chief_complaint' => 'Revised complaint', 'diagnosis' => 'Diagnosis B']
        );

        // Both records must exist — no overwrite
        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id'    => $this->queueEntry->id,
            'chief_complaint' => 'Initial complaint',
        ]);
        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id'    => $this->queueEntry->id,
            'chief_complaint' => 'Revised complaint',
        ]);

        $count = OpdClinicalNote::where('opd_queue_id', $this->queueEntry->id)->count();
        $this->assertEquals(2, $count);
    }

    public function test_clinical_note_can_be_partially_filled(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/notes",
            ['chief_complaint' => 'Cough', 'diagnosis' => 'URTI']
        );

        $response->assertRedirect();

        $note = OpdClinicalNote::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertEquals('Cough', $note->chief_complaint);
        $this->assertEquals('URTI', $note->diagnosis);
        $this->assertNull($note->history);
        $this->assertNull($note->treatment_plan);
    }

    // ── 2. Visit Completion ────────────────────────────────────────────────────

    public function test_completing_consultation_marks_queue_as_completed(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Completed']
        );

        $response->assertRedirect(route('opd.dashboard', ['room_id' => $this->opdRoom->id]));

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Completed',
        ]);

        $this->assertNotNull(
            OpdQueue::find($this->queueEntry->id)->completed_at,
            'completed_at must be set when consultation is Completed'
        );
    }

    public function test_completing_consultation_marks_visit_as_completed(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Completed']
        );

        $this->assertDatabaseHas('visits', [
            'id'     => $this->visit->id,
            'status' => 'Completed',
        ]);
    }

    public function test_completing_consultation_saves_clinical_note_atomically(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            [
                'status'          => 'Completed',
                'chief_complaint' => 'Fever',
                'diagnosis'       => 'Malaria',
                'treatment_plan'  => 'Artemether',
            ]
        );

        $response->assertRedirect();

        // Note saved
        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id'    => $this->queueEntry->id,
            'chief_complaint' => 'Fever',
            'diagnosis'       => 'Malaria',
        ]);

        // Queue also completed
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Completed',
        ]);
    }

    public function test_completing_consultation_without_note_still_marks_visit_completed(): void
    {
        // No clinical note fields provided — completion should still work
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Completed']
        );

        $this->assertDatabaseHas('visits', [
            'id'     => $this->visit->id,
            'status' => 'Completed',
        ]);

        // No note record should be created when fields are empty
        $this->assertDatabaseMissing('opd_clinical_notes', [
            'opd_queue_id' => $this->queueEntry->id,
        ]);
    }

    public function test_transfer_marks_queue_transferred_and_updates_visit(): void
    {
        $destRoom = \App\Models\Room::where('room_code', 'OPD5')->first();

        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            [
                'status'              => 'Transferred',
                'destination_room_id' => $destRoom->id,
            ]
        );

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Transferred',
        ]);

        // Visit is now marked Transferred on a Transfer
        $this->assertDatabaseHas('visits', [
            'id'     => $this->visit->id,
            'status' => 'Transferred',
        ]);

        // A new visit is created in the destination room
        $this->assertDatabaseHas('visits', [
            'patient_id' => $this->patient->id,
            'room_id'    => $destRoom->id,
            'status'     => 'Assigned',
        ]);
    }

    public function test_cancel_marks_queue_cancelled(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Cancelled']
        );

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Cancelled',
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Waiting'] // not a valid completion status
        );

        $response->assertSessionHasErrors('status');
    }

    // ── 3. Timeline Update ────────────────────────────────────────────────────

    public function test_completed_encounter_appears_in_patient_timeline(): void
    {
        // Complete the consultation with a clinical note
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            [
                'status'          => 'Completed',
                'chief_complaint' => 'Back pain',
                'diagnosis'       => 'Lumbar strain',
            ]
        );

        // Visit the history page — it will render via Inertia
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('OPD/PatientHistory')
                ->has('timeline')
                ->where(
                    'timeline.0.type',
                    fn ($type) => in_array($type, ['opd', 'visit'], true)
                )
        );
    }

    public function test_timeline_includes_opd_encounter_with_clinical_note_summary(): void
    {
        // Attach a note directly
        OpdClinicalNote::create([
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'created_by'      => $this->opdNurse->id,
            'chief_complaint' => 'Chest pain',
            'diagnosis'       => 'Angina',
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('timeline'));

        // At minimum the OPD encounter item with note summary must appear in timeline
        $timeline = $response->original->getData()['page']['props']['timeline'];
        $opdItem  = collect($timeline)->firstWhere('type', 'opd');
        $this->assertNotNull($opdItem, 'OPD encounter must appear in timeline');
        $this->assertStringContainsString('Chest pain', $opdItem['detail'] ?? '');
    }

    public function test_timeline_is_sorted_chronologically_descending(): void
    {
        // Add an older visit (yesterday)
        Visit::create([
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'assigned_by'  => $this->opdNurse->id,
            'visit_date'   => now()->subDays(5)->toDateString(),
            'visit_time'   => '09:00:00',
            'queue_number' => 2,
            'status'       => 'Assigned',
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
        $timeline = $response->original->getData()['page']['props']['timeline'];

        // Verify descending date order
        $dates = collect($timeline)->pluck('date')->all();
        $sortedDesc = collect($dates)->sortDesc()->values()->all();
        $this->assertEquals($sortedDesc, $dates, 'Timeline must be sorted most-recent first');
    }

    // ── 4. Authorization ──────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_consultation(): void
    {
        $response = $this->get("/opd/consultation/{$this->queueEntry->id}");
        $response->assertRedirect('/login');
    }

    public function test_consultation_page_can_be_shown_for_in_consultation_entry(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('OPD/Consultation')
                ->where('queue_entry.id', $this->queueEntry->id)
                ->where('patient.id', $this->patient->id)
        );
    }

    public function test_called_entry_auto_transitions_to_in_consultation_on_show(): void
    {
        $this->queueEntry->update(['status' => 'Called', 'called_at' => now()]);

        $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'In Consultation',
        ]);
    }

    // ── 5. Attachment ─────────────────────────────────────────────────────────

    public function test_opd_nurse_can_upload_attachment(): void
    {
        // Ensure public/opd-attachments directory exists
        if (!is_dir(public_path('opd-attachments'))) {
            mkdir(public_path('opd-attachments'), 0755, true);
        }

        $file = UploadedFile::fake()->create('lab_result.pdf', 512, 'application/pdf');

        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/attachments",
            ['attachments' => [$file]]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $attachment = OpdAttachment::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertNotNull($attachment);
        $this->assertEquals('lab_result.pdf', $attachment->original_name);
        $this->assertEquals('pdf', $attachment->type);

        // Clean up
        if (file_exists(public_path($attachment->stored_path))) {
            unlink(public_path($attachment->stored_path));
        }
    }

    public function test_attachment_download_returns_file(): void
    {
        // Create a real file to download
        if (!is_dir(public_path('opd-attachments'))) {
            mkdir(public_path('opd-attachments'), 0755, true);
        }
        $filename = 'opd_att_test_'.time().'.txt';
        file_put_contents(public_path('opd-attachments/'.$filename), 'test content');

        $attachment = OpdAttachment::create([
            'opd_queue_id'  => $this->queueEntry->id,
            'patient_id'    => $this->patient->id,
            'uploaded_by'   => $this->opdNurse->id,
            'original_name' => 'report.txt',
            'stored_path'   => 'opd-attachments/'.$filename,
            'mime_type'     => 'text/plain',
            'file_size'     => 12,
            'type'          => 'document',
        ]);

        $response = $this->actingAs($this->opdNurse)->get(
            "/opd/consultation/{$this->queueEntry->id}/attachments/{$attachment->id}/download"
        );

        $response->assertOk();
        $response->assertHeader('content-disposition');

        // Clean up
        if (file_exists(public_path('opd-attachments/'.$filename))) {
            unlink(public_path('opd-attachments/'.$filename));
        }
    }
}
