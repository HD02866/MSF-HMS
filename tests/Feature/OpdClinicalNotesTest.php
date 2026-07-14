<?php

namespace Tests\Feature;

use App\Models\OpdClinicalNote;
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

class OpdClinicalNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $opdNurse;
    private User $cardOfficer;
    private Patient $patient;
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
        $opdRoom      = Room::where('room_code', 'OPD7')->first();

        $this->patient = Patient::create([
            'card_number'          => '66666-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '66666',
            'dependent_no'         => 0,
            'full_name'            => 'Clinical Notes Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1975-03-10',
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
            'queue_number' => 2,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $opdRoom->id,
            'queue_number' => 2,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_opd_nurse_can_save_clinical_note(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'chief_complaint' => 'Headache for 3 days',
                'diagnosis'       => 'Tension headache',
                'treatment_plan'  => 'Paracetamol 500mg TDS',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'chief_complaint' => 'Headache for 3 days',
            'diagnosis'       => 'Tension headache',
            'created_by'      => $this->opdNurse->id,
        ]);
    }

    public function test_admin_can_save_clinical_note(): void
    {
        $response = $this->actingAs($this->admin)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'chief_complaint' => 'Fever',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id' => $this->queueEntry->id,
            'created_by'   => $this->admin->id,
        ]);
    }

    public function test_card_officer_cannot_save_clinical_note(): void
    {
        $response = $this->actingAs($this->cardOfficer)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'chief_complaint' => 'Unauthorized',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('opd_clinical_notes', [
            'opd_queue_id' => $this->queueEntry->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_save_note(): void
    {
        $response = $this->post("/opd/consultation/{$this->queueEntry->id}/notes", [
            'chief_complaint' => 'Test',
        ]);
        $response->assertRedirect('/login');
    }

    // ── All 6 fields save correctly ───────────────────────────────────────────

    public function test_all_six_clinical_fields_save_correctly(): void
    {
        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'chief_complaint'        => 'Chest pain',
                'history'                => 'Hypertension for 5 years',
                'physical_examination'   => 'BP 150/90, HR 88',
                'diagnosis'              => 'Hypertensive urgency',
                'treatment_plan'         => 'Amlodipine 5mg OD',
                'follow_up_instructions' => 'Return in 1 week',
            ]);

        $note = OpdClinicalNote::where('opd_queue_id', $this->queueEntry->id)->first();

        $this->assertNotNull($note);
        $this->assertEquals('Chest pain',              $note->chief_complaint);
        $this->assertEquals('Hypertension for 5 years',$note->history);
        $this->assertEquals('BP 150/90, HR 88',        $note->physical_examination);
        $this->assertEquals('Hypertensive urgency',    $note->diagnosis);
        $this->assertEquals('Amlodipine 5mg OD',       $note->treatment_plan);
        $this->assertEquals('Return in 1 week',        $note->follow_up_instructions);
    }

    // ── Partial save (not all fields required) ────────────────────────────────

    public function test_clinical_note_can_be_saved_with_only_some_fields(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'diagnosis' => 'Malaria',
            ]);

        $response->assertRedirect();

        $note = OpdClinicalNote::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertEquals('Malaria', $note->diagnosis);
        $this->assertNull($note->chief_complaint);
        $this->assertNull($note->treatment_plan);
    }

    public function test_empty_submission_saves_empty_note(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", []);

        // All fields nullable — should succeed
        $response->assertRedirect();
        $this->assertDatabaseHas('opd_clinical_notes', [
            'opd_queue_id' => $this->queueEntry->id,
        ]);
    }

    // ── Previous notes are never overwritten ──────────────────────────────────

    public function test_saving_second_note_creates_new_record_not_overwrite(): void
    {
        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'diagnosis' => 'First diagnosis',
            ]);

        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'diagnosis' => 'Second diagnosis',
            ]);

        $count = OpdClinicalNote::where('opd_queue_id', $this->queueEntry->id)->count();
        $this->assertEquals(2, $count);

        $this->assertDatabaseHas('opd_clinical_notes', ['diagnosis' => 'First diagnosis']);
        $this->assertDatabaseHas('opd_clinical_notes', ['diagnosis' => 'Second diagnosis']);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_fields_over_max_length_are_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'chief_complaint' => str_repeat('a', 2001), // max 2000
            ]);

        $response->assertSessionHasErrors('chief_complaint');
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    public function test_audit_log_created_on_note_save(): void
    {
        $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/notes", [
                'diagnosis' => 'Audit test',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'OPD Clinical Note Saved',
            'user_id' => $this->opdNurse->id,
        ]);
    }

    // ── Consultation page loads existing note ─────────────────────────────────

    public function test_consultation_page_shows_existing_note_pre_filled(): void
    {
        OpdClinicalNote::create([
            'opd_queue_id'    => $this->queueEntry->id,
            'patient_id'      => $this->patient->id,
            'created_by'      => $this->opdNurse->id,
            'chief_complaint' => 'Pre-existing complaint',
            'diagnosis'       => 'Pre-existing diagnosis',
        ]);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertInertia(fn ($page) =>
            $page->where('clinical_note.chief_complaint', 'Pre-existing complaint')
                 ->where('clinical_note.diagnosis', 'Pre-existing diagnosis')
        );
    }

    public function test_consultation_page_passes_null_when_no_note_exists(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertInertia(fn ($page) =>
            $page->where('clinical_note', null)
        );
    }

    // ── Existing functionality not broken ─────────────────────────────────────

    public function test_complete_consultation_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->post("/opd/consultation/{$this->queueEntry->id}/complete", [
                'status' => 'Completed',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Completed',
        ]);
    }

    public function test_patient_history_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/history");

        $response->assertOk();
    }
}
