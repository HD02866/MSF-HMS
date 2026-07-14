<?php

namespace Tests\Feature;

use App\Models\LabQueue;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabResult;
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

class LabResultTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $labTech;
    private User     $opdNurse;
    private User     $cardOfficer;
    private Patient  $patient;
    private LabQueue $labQueue;
    private LabRequest $labRequest;
    private LabRequestTest $testA;
    private LabRequestTest $testB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin       = User::where('username', 'admin')->first();
        $labTechRole       = Role::where('name', 'Lab Technician')->first();
        $opdNurseRole      = Role::where('name', 'OPD Nurse')->first();
        $cardOfficerRole   = Role::where('name', 'Card Officer')->first();

        $this->labTech = User::factory()->create(['role_id' => $labTechRole->id, 'is_active' => true]);
        $this->opdNurse = User::factory()->create(['role_id' => $opdNurseRole->id, 'is_active' => true]);
        $this->cardOfficer = User::factory()->create(['role_id' => $cardOfficerRole->id, 'is_active' => true]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();
        $opdRoom      = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '66600-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '66600',
            'dependent_no'         => 0,
            'full_name'            => 'Result Test Patient',
            'gender'               => 'Female',
            'date_of_birth'        => '1992-11-05',
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
            'queue_number' => 3,
            'status'       => 'Assigned',
        ]);

        $opdQueue = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $opdRoom->id,
            'queue_number' => 3,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);

        $this->labRequest = LabRequest::create([
            'opd_queue_id'   => $opdQueue->id,
            'patient_id'     => $this->patient->id,
            'requested_by'   => $this->opdNurse->id,
            'request_date'   => today()->toDateString(),
            'priority'       => 'Normal',
            'clinical_notes' => 'Routine check',
        ]);

        $this->testA = LabRequestTest::create([
            'lab_request_id' => $this->labRequest->id,
            'test_name'      => 'CBC (Complete Blood Count)',
        ]);

        $this->testB = LabRequestTest::create([
            'lab_request_id' => $this->labRequest->id,
            'test_name'      => 'Haemoglobin (Hgb)',
        ]);

        $this->labQueue = LabQueue::create([
            'lab_request_id' => $this->labRequest->id,
            'patient_id'     => $this->patient->id,
            'status'         => 'Processing',
            'processing_at'  => now(),
        ]);
    }

    // ── 1. Result entry form access ───────────────────────────────────────────

    public function test_lab_tech_can_view_result_entry_form(): void
    {
        $response = $this->actingAs($this->labTech)
            ->get("/lab/queue/{$this->labQueue->id}/results");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Lab/ResultEntry')
                ->where('lab_queue.id', $this->labQueue->id)
                ->where('patient.id', $this->patient->id)
                ->has('tests', 2)
                ->has('lab_request')
        );
    }

    public function test_admin_can_view_result_entry_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/lab/queue/{$this->labQueue->id}/results");

        $response->assertOk();
    }

    public function test_card_officer_cannot_view_result_entry_form(): void
    {
        $response = $this->actingAs($this->cardOfficer)
            ->get("/lab/queue/{$this->labQueue->id}/results");

        $response->assertForbidden();
    }

    public function test_opd_nurse_cannot_view_result_entry_form(): void
    {
        // OPD Nurse can VIEW queue but cannot update (enter results)
        $response = $this->actingAs($this->opdNurse)
            ->get("/lab/queue/{$this->labQueue->id}/results");

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get("/lab/queue/{$this->labQueue->id}/results");
        $response->assertRedirect('/login');
    }

    // ── 2. Saving results ─────────────────────────────────────────────────────

    public function test_lab_tech_can_save_results_for_tests(): void
    {
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => [
                        'result'      => '14.2 g/dL',
                        'remarks'     => 'Normal range 12-16 g/dL',
                        'result_date' => today()->toDateString(),
                    ],
                    $this->testB->id => [
                        'result'      => '14.2 g/dL',
                        'remarks'     => '',
                        'result_date' => today()->toDateString(),
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('lab.results.create', $this->labQueue));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('lab_results', [
            'lab_request_test_id' => $this->testA->id,
            'result'              => '14.2 g/dL',
            'performed_by'        => $this->labTech->id,
            'patient_id'          => $this->patient->id,
        ]);
    }

    public function test_results_are_stored_per_test(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Positive', 'remarks' => '', 'result_date' => today()->toDateString()],
                    $this->testB->id => ['result' => '12.1 g/dL', 'remarks' => 'Low', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $this->assertEquals(2, LabResult::where('lab_request_id', $this->labRequest->id)->count());

        $this->assertDatabaseHas('lab_results', [
            'lab_request_test_id' => $this->testA->id,
            'result'              => 'Positive',
        ]);
        $this->assertDatabaseHas('lab_results', [
            'lab_request_test_id' => $this->testB->id,
            'result'              => '12.1 g/dL',
            'remarks'             => 'Low',
        ]);
    }

    public function test_blank_result_entries_are_skipped(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Positive', 'remarks' => '', 'result_date' => today()->toDateString()],
                    $this->testB->id => ['result' => '', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Only testA should have a result — testB was blank
        $this->assertEquals(1, LabResult::where('lab_request_id', $this->labRequest->id)->count());
        $this->assertDatabaseMissing('lab_results', ['lab_request_test_id' => $this->testB->id]);
    }

    public function test_remarks_are_optional(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Normal', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $this->assertDatabaseHas('lab_results', [
            'lab_request_test_id' => $this->testA->id,
            'remarks'             => null,
        ]);
    }

    public function test_result_can_be_updated_via_update_or_create(): void
    {
        // First save
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Initial', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Update
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Updated', 'remarks' => 'Corrected', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Only one record — updated in place
        $this->assertEquals(1, LabResult::where('lab_request_test_id', $this->testA->id)->count());
        $this->assertDatabaseHas('lab_results', [
            'lab_request_test_id' => $this->testA->id,
            'result'              => 'Updated',
        ]);
    }

    // ── 3. Auto-complete when all tests have results ───────────────────────────

    public function test_queue_auto_completes_when_all_tests_have_results(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => '14.2 g/dL', 'remarks' => '', 'result_date' => today()->toDateString()],
                    $this->testB->id => ['result' => '14.2 g/dL', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $this->assertDatabaseHas('lab_queue', [
            'id'     => $this->labQueue->id,
            'status' => 'Completed',
        ]);
        $this->assertNotNull(LabQueue::find($this->labQueue->id)->completed_at);
    }

    public function test_queue_not_completed_when_only_partial_results_entered(): void
    {
        // Only save one of two tests
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => '14.2 g/dL', 'remarks' => '', 'result_date' => today()->toDateString()],
                    // testB intentionally blank
                    $this->testB->id => ['result' => '', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Status stays Processing — not all tests have results
        $this->assertDatabaseHas('lab_queue', [
            'id'     => $this->labQueue->id,
            'status' => 'Processing',
        ]);
    }

    // ── 4. Validation ─────────────────────────────────────────────────────────

    public function test_results_array_is_required(): void
    {
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            []
        );

        $response->assertSessionHasErrors('results');
    }

    public function test_all_blank_results_is_rejected(): void
    {
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => '', 'remarks' => '', 'result_date' => today()->toDateString()],
                    $this->testB->id => ['result' => '', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $response->assertSessionHasErrors('results');
        $this->assertEquals(0, LabResult::count());
    }

    public function test_result_value_over_max_length_is_rejected(): void
    {
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => [
                        'result'      => str_repeat('x', 1001),
                        'remarks'     => '',
                        'result_date' => today()->toDateString(),
                    ],
                ],
            ]
        );

        $response->assertSessionHasErrors();
    }

    public function test_missing_result_date_is_rejected(): void
    {
        $response = $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Positive', 'remarks' => ''],
                ],
            ]
        );

        $response->assertSessionHasErrors();
    }

    public function test_card_officer_cannot_save_results(): void
    {
        $response = $this->actingAs($this->cardOfficer)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Positive', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $response->assertForbidden();
        $this->assertEquals(0, LabResult::count());
    }

    // ── 5. Audit log ──────────────────────────────────────────────────────────

    public function test_audit_log_created_on_result_save(): void
    {
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => 'Normal', 'remarks' => '', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Lab Results Saved',
            'user_id' => $this->labTech->id,
        ]);
    }

    // ── 6. Patient history integration ────────────────────────────────────────

    public function test_completed_lab_results_appear_in_patient_history(): void
    {
        // Complete the queue entry and save results
        $this->labQueue->update(['status' => 'Completed', 'completed_at' => now()]);

        LabResult::create([
            'lab_request_id'      => $this->labRequest->id,
            'lab_request_test_id' => $this->testA->id,
            'patient_id'          => $this->patient->id,
            'performed_by'        => $this->labTech->id,
            'result'              => '14.2 g/dL',
            'remarks'             => null,
            'result_date'         => today()->toDateString(),
        ]);

        // Access the patient history via an OPD queue the patient belongs to
        $opdRoom = Room::where('room_code', 'OPD4')->first();
        $opdQueueEntry = OpdQueue::where('patient_id', $this->patient->id)->first();

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$opdQueueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('lab_results')
                ->where('lab_results.total', 1)
        );
    }

    public function test_lab_results_appear_in_timeline(): void
    {
        $this->labQueue->update(['status' => 'Completed', 'completed_at' => now()]);

        LabResult::create([
            'lab_request_id'      => $this->labRequest->id,
            'lab_request_test_id' => $this->testA->id,
            'patient_id'          => $this->patient->id,
            'performed_by'        => $this->labTech->id,
            'result'              => 'Positive',
            'remarks'             => null,
            'result_date'         => today()->toDateString(),
        ]);

        $opdQueueEntry = OpdQueue::where('patient_id', $this->patient->id)->first();

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$opdQueueEntry->id}/history");

        $response->assertOk();

        $timeline = $response->original->getData()['page']['props']['timeline'];
        $labItem  = collect($timeline)->firstWhere('type', 'lab');

        $this->assertNotNull($labItem, 'Lab result should appear in timeline');
        $this->assertEquals('Completed', $labItem['badge']);
    }

    public function test_pending_lab_requests_do_not_appear_in_patient_history(): void
    {
        // labQueue is still 'Processing' — should NOT appear in history
        $opdQueueEntry = OpdQueue::where('patient_id', $this->patient->id)->first();

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$opdQueueEntry->id}/history");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->where('lab_results.total', 0)
        );
    }

    // ── 7. Previous results not overwritten on re-save ────────────────────────

    public function test_previous_results_preserved_not_deleted(): void
    {
        // Save testA
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testA->id => ['result' => '14.2 g/dL', 'remarks' => 'First save', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Save testB separately
        $this->actingAs($this->labTech)->post(
            "/lab/queue/{$this->labQueue->id}/results",
            [
                'results' => [
                    $this->testB->id => ['result' => '12.5 g/dL', 'remarks' => 'Second save', 'result_date' => today()->toDateString()],
                ],
            ]
        );

        // Both records must exist
        $this->assertEquals(2, LabResult::where('lab_request_id', $this->labRequest->id)->count());
        $this->assertDatabaseHas('lab_results', ['lab_request_test_id' => $this->testA->id, 'result' => '14.2 g/dL']);
        $this->assertDatabaseHas('lab_results', ['lab_request_test_id' => $this->testB->id, 'result' => '12.5 g/dL']);
    }

    // ── 8. Existing functionality not broken ──────────────────────────────────

    public function test_lab_queue_page_still_works(): void
    {
        $response = $this->actingAs($this->labTech)->get('/lab/queue');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Lab/Queue'));
    }

    public function test_lab_request_submission_still_auto_enqueues(): void
    {
        $opdQueueEntry = OpdQueue::where('patient_id', $this->patient->id)->first();

        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$opdQueueEntry->id}/lab",
            ['tests' => ['TSH'], 'priority' => 'Normal', 'request_date' => today()->toDateString()]
        );

        $response->assertRedirect();
        $this->assertEquals(2, LabQueue::where('patient_id', $this->patient->id)->count());
    }
}
