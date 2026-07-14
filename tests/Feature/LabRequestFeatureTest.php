<?php

namespace Tests\Feature;

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

class LabRequestFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User    $admin;
    private User    $opdNurse;
    private User    $cardOfficer;
    private OpdQueue $queueEntry;
    private Patient  $patient;

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
        $opdRoom      = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '44400-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '44400',
            'dependent_no'         => 0,
            'full_name'            => 'Lab Test Patient',
            'gender'               => 'Male',
            'date_of_birth'        => '1988-04-10',
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

    // ── 1. Access control ─────────────────────────────────────────────────────

    public function test_opd_nurse_can_view_lab_request_form(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('OPD/LabRequest')
                ->where('queue_entry.id', $this->queueEntry->id)
                ->where('patient.id', $this->patient->id)
                ->has('test_catalog')
                ->has('priorities')
        );
    }

    public function test_admin_can_view_lab_request_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertOk();
    }

    public function test_card_officer_cannot_view_lab_request_form(): void
    {
        $response = $this->actingAs($this->cardOfficer)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get("/opd/consultation/{$this->queueEntry->id}/lab");
        $response->assertRedirect('/login');
    }

    // ── 2. Store / Validation ─────────────────────────────────────────────────

    public function test_opd_nurse_can_submit_lab_request(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)', 'Malaria Rapid Test (RDT)'],
                'clinical_notes' => 'Fever for 3 days.',
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
            ]
        );

        $response->assertRedirect(route('opd.lab.create', $this->queueEntry));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id'  => $this->queueEntry->id,
            'patient_id'    => $this->patient->id,
            'requested_by'  => $this->opdNurse->id,
            'priority'      => 'Normal',
            'clinical_notes'=> 'Fever for 3 days.',
        ]);
    }

    public function test_tests_are_stored_as_individual_rows(): void
    {
        $tests = ['CBC (Complete Blood Count)', 'Haemoglobin (Hgb)', 'Creatinine'];

        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => $tests,
                'priority'     => 'Urgent',
                'request_date' => today()->toDateString(),
            ]
        );

        $labRequest = LabRequest::where('opd_queue_id', $this->queueEntry->id)->first();
        $this->assertNotNull($labRequest);

        foreach ($tests as $test) {
            $this->assertDatabaseHas('lab_request_tests', [
                'lab_request_id' => $labRequest->id,
                'test_name'      => $test,
            ]);
        }

        $this->assertEquals(3, LabRequestTest::where('lab_request_id', $labRequest->id)->count());
    }

    public function test_urgent_priority_is_stored_correctly(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['HIV Rapid Test'],
                'priority'     => 'Urgent',
                'request_date' => today()->toDateString(),
            ]
        );

        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id' => $this->queueEntry->id,
            'priority'     => 'Urgent',
        ]);
    }

    public function test_clinical_notes_are_optional(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['Urinalysis (Routine)'],
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
                // no clinical_notes
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id'   => $this->queueEntry->id,
            'clinical_notes' => null,
        ]);
    }

    // ── 3. Validation rules ───────────────────────────────────────────────────

    public function test_tests_array_is_required(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
                // no tests
            ]
        );

        $response->assertSessionHasErrors('tests');
    }

    public function test_empty_tests_array_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => [],
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
            ]
        );

        $response->assertSessionHasErrors('tests');
    }

    public function test_invalid_priority_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['CBC (Complete Blood Count)'],
                'priority'     => 'STAT', // not a valid priority
                'request_date' => today()->toDateString(),
            ]
        );

        $response->assertSessionHasErrors('priority');
    }

    public function test_missing_request_date_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'    => ['CBC (Complete Blood Count)'],
                'priority' => 'Normal',
                // no request_date
            ]
        );

        $response->assertSessionHasErrors('request_date');
    }

    public function test_clinical_notes_over_max_length_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)'],
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
                'clinical_notes' => str_repeat('x', 3001),
            ]
        );

        $response->assertSessionHasErrors('clinical_notes');
    }

    public function test_card_officer_cannot_submit_lab_request(): void
    {
        $response = $this->actingAs($this->cardOfficer)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['CBC (Complete Blood Count)'],
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
            ]
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('lab_requests', ['opd_queue_id' => $this->queueEntry->id]);
    }

    // ── 4. Database integration ───────────────────────────────────────────────

    public function test_multiple_requests_can_be_submitted_for_same_encounter(): void
    {
        // First request
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            ['tests' => ['CBC (Complete Blood Count)'], 'priority' => 'Normal', 'request_date' => today()->toDateString()]
        );

        // Second request — different panel
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            ['tests' => ['Urinalysis (Routine)', 'Urine Microscopy'], 'priority' => 'Urgent', 'request_date' => today()->toDateString()]
        );

        $this->assertEquals(
            2,
            LabRequest::where('opd_queue_id', $this->queueEntry->id)->count()
        );
        $this->assertEquals(
            3, // 1 + 2 tests across both requests
            LabRequestTest::whereIn(
                'lab_request_id',
                LabRequest::where('opd_queue_id', $this->queueEntry->id)->pluck('id')
            )->count()
        );
    }

    public function test_audit_log_is_created_on_lab_request(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            ['tests' => ['Malaria Rapid Test (RDT)'], 'priority' => 'Urgent', 'request_date' => today()->toDateString()]
        );

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Lab Request Created',
            'user_id' => $this->opdNurse->id,
        ]);
    }

    // ── 5. Prior requests shown on page ──────────────────────────────────────

    public function test_form_shows_prior_requests_for_encounter(): void
    {
        // Create a prior request directly
        $labRequest = LabRequest::create([
            'opd_queue_id'  => $this->queueEntry->id,
            'patient_id'    => $this->patient->id,
            'requested_by'  => $this->opdNurse->id,
            'request_date'  => today()->toDateString(),
            'priority'      => 'Normal',
            'clinical_notes'=> null,
        ]);
        LabRequestTest::create(['lab_request_id' => $labRequest->id, 'test_name' => 'CBC (Complete Blood Count)']);

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertInertia(fn ($page) =>
            $page->count('prior_requests', 1)
                ->where('prior_requests.0.priority', 'Normal')
                // tests is now an array of objects {id, test_name, result}
                ->where('prior_requests.0.tests.0.test_name', 'CBC (Complete Blood Count)')
        );
    }

    // ── 6. Requester name & signature ────────────────────────────────────────

    public function test_requester_name_is_passed_to_form(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertInertia(fn ($page) =>
            $page->has('requester_name')
                ->where('requester_name', $this->opdNurse->full_name)
        );
    }

    public function test_requester_name_is_stored_when_submitted(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)'],
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
                'requester_name' => 'Dr. Alemayehu',
            ]
        );

        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id'  => $this->queueEntry->id,
            'requester_name'=> 'Dr. Alemayehu',
        ]);
    }

    public function test_signature_data_is_stored_when_submitted(): void
    {
        $fakeSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)'],
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
                'requester_name' => 'Nurse Fatuma',
                'signature_data' => $fakeSignature,
            ]
        );

        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id'  => $this->queueEntry->id,
            'requester_name'=> 'Nurse Fatuma',
            'signature_data'=> $fakeSignature,
        ]);
    }

    public function test_requester_name_and_signature_are_optional(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'        => ['CBC (Complete Blood Count)'],
                'priority'     => 'Normal',
                'request_date' => today()->toDateString(),
                // no requester_name or signature_data
            ]
        );

        $this->assertDatabaseHas('lab_requests', [
            'opd_queue_id'   => $this->queueEntry->id,
            'requester_name' => null,
            'signature_data' => null,
        ]);
    }

    public function test_invalid_signature_format_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)'],
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
                'signature_data' => 'not-a-valid-image-data-url',
            ]
        );

        $response->assertSessionHasErrors('signature_data');
    }

    public function test_requester_name_max_length_is_enforced(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/lab",
            [
                'tests'          => ['CBC (Complete Blood Count)'],
                'priority'       => 'Normal',
                'request_date'   => today()->toDateString(),
                'requester_name' => str_repeat('x', 151),
            ]
        );

        $response->assertSessionHasErrors('requester_name');
    }

    // ── 7. Existing consultation not broken ───────────────────────────────────

    public function test_existing_consultation_page_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('OPD/Consultation'));
    }

    public function test_existing_complete_consultation_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/complete",
            ['status' => 'Completed']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('opd_queue', [
            'id'     => $this->queueEntry->id,
            'status' => 'Completed',
        ]);
    }
}
