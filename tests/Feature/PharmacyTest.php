<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\OpdQueue;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\PharmacyNotification;
use App\Models\PharmacyQueue;
use App\Models\PharmacyRequest;
use App\Models\PharmacyRequestItem;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $opdNurse;
    private User     $pharmacist;
    private Patient  $patient;
    private OpdQueue $queueEntry;
    private Room     $opdRoom;
    private Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->admin     = User::where('username', 'admin')->first();
        $opdNurseRole    = Role::where('name', 'OPD Nurse')->first();
        $pharmacistRole  = Role::where('name', 'Pharmacist')->first();

        $this->opdNurse   = User::factory()->create(['role_id' => $opdNurseRole->id, 'is_active' => true]);
        $this->pharmacist = User::factory()->create(['role_id' => $pharmacistRole->id, 'is_active' => true]);

        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel  = RelationshipType::where('name', 'Employee')->first();
        $this->opdRoom = Room::where('room_code', 'OPD4')->first();

        $this->patient = Patient::create([
            'card_number'          => '88800-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '88800',
            'dependent_no'         => 0,
            'full_name'            => 'Pharmacy Test Patient',
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
            'queue_number' => 1,
            'status'       => 'Assigned',
        ]);

        $this->queueEntry = OpdQueue::create([
            'visit_id'     => $visit->id,
            'patient_id'   => $this->patient->id,
            'room_id'      => $this->opdRoom->id,
            'queue_number' => 1,
            'arrived_at'   => now(),
            'status'       => 'In Consultation',
            'called_at'    => now(),
        ]);

        $this->medicine = Medicine::create([
            'name'                 => 'Test Paracetamol 500mg',
            'generic_name'         => 'Acetaminophen',
            'category'             => 'Analgesic',
            'form'                 => 'Tablet',
            'unit'                 => 'pieces',
            'unit_price'           => 0.50,
            'quantity_in_stock'    => 100,
            'minimum_stock_level'  => 10,
            'is_active'            => true,
        ]);
    }

    // ── 1. Access control ───────────────────────────────────────────────────

    public function test_opd_nurse_can_view_prescription_form(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/prescription");

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('Pharmacy/Prescription')
                ->where('queue_entry.id', $this->queueEntry->id)
                ->where('patient.id', $this->patient->id)
                ->has('medicines')
        );
    }

    public function test_admin_can_view_prescription_form(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/opd/consultation/{$this->queueEntry->id}/prescription");

        $response->assertOk();
    }

    public function test_pharmacist_can_view_queue(): void
    {
        $response = $this->actingAs($this->pharmacist)->get('/pharmacy/queue');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Pharmacy/Queue'));
    }

    public function test_pharmacist_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->pharmacist)->get('/pharmacy/dashboard');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Pharmacy/Dashboard'));
    }

    // ── 2. Prescription creation ────────────────────────────────────────────

    public function test_opd_nurse_can_submit_prescription(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date'   => today()->toDateString(),
                'prescriber_name'=> 'Dr. Test',
                'clinical_notes' => 'Fever for 3 days',
                'items'          => [
                    [
                        'medicine_id'   => $this->medicine->id,
                        'medicine_name' => $this->medicine->name,
                        'dosage'        => '500mg',
                        'frequency'     => 'Twice daily',
                        'duration'      => '5 days',
                        'quantity'      => 10,
                        'notes'         => null,
                    ],
                ],
            ]
        );

        $response->assertRedirect(route('opd.prescription.create', $this->queueEntry));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pharmacy_requests', [
            'opd_queue_id'   => $this->queueEntry->id,
            'patient_id'     => $this->patient->id,
            'prescribed_by'  => $this->opdNurse->id,
            'prescriber_name'=> 'Dr. Test',
            'clinical_notes' => 'Fever for 3 days',
        ]);

        $this->assertDatabaseHas('pharmacy_request_items', [
            'medicine_name' => $this->medicine->name,
            'dosage'        => '500mg',
            'quantity'      => 10,
        ]);

        $this->assertDatabaseHas('pharmacy_queue', [
            'patient_id' => $this->patient->id,
            'status'     => 'Pending',
        ]);
    }

    public function test_prescription_creates_notification_to_opd_room(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_id' => $this->medicine->id, 'medicine_name' => $this->medicine->name, 'quantity' => 5],
                ],
            ]
        );

        $this->assertDatabaseHas('pharmacy_notifications', [
            'room_id'        => $this->opdRoom->id,
            'patient_id'     => $this->patient->id,
            'event'          => PharmacyNotification::EVENT_SUBMITTED,
            'is_read'        => false,
        ]);
    }

    public function test_external_prescription_is_stored(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date'   => today()->toDateString(),
                'is_external'    => true,
                'external_notes' => 'Not available in pharmacy',
                'items'          => [
                    ['medicine_name' => 'External Medicine X', 'quantity' => 1],
                ],
            ]
        );

        $this->assertDatabaseHas('pharmacy_requests', [
            'opd_queue_id'   => $this->queueEntry->id,
            'is_external'    => true,
            'external_notes' => 'Not available in pharmacy',
        ]);
    }

    // ── 3. Validation ───────────────────────────────────────────────────────

    public function test_items_array_is_required(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
            ]
        );

        $response->assertSessionHasErrors('items');
    }

    public function test_empty_items_array_is_rejected(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [],
            ]
        );

        $response->assertSessionHasErrors('items');
    }

    public function test_medicine_name_is_required_per_item(): void
    {
        $response = $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['quantity' => 5],
                ],
            ]
        );

        $response->assertSessionHasErrors('items.0.medicine_name');
    }

    // ── 4. Dispensing ───────────────────────────────────────────────────────

    public function test_pharmacist_can_dispense(): void
    {
        // Create a prescription
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_id' => $this->medicine->id, 'medicine_name' => $this->medicine->name, 'quantity' => 5],
                ],
            ]
        );

        $queue = PharmacyQueue::where('patient_id', $this->patient->id)->first();

        $this->actingAs($this->pharmacist)->post("/pharmacy/queue/{$queue->id}/status", [
            'status' => 'Dispensed',
        ]);

        $this->assertDatabaseHas('pharmacy_queue', [
            'id'     => $queue->id,
            'status' => 'Dispensed',
        ]);

        // Inventory should be decremented
        $this->medicine->refresh();
        $this->assertEquals(95, $this->medicine->quantity_in_stock);
    }

    public function test_dispensing_creates_notification_to_opd(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_id' => $this->medicine->id, 'medicine_name' => $this->medicine->name, 'quantity' => 5],
                ],
            ]
        );

        $queue = PharmacyQueue::where('patient_id', $this->patient->id)->first();

        $this->actingAs($this->pharmacist)->post("/pharmacy/queue/{$queue->id}/status", [
            'status' => 'Dispensed',
        ]);

        $this->assertDatabaseHas('pharmacy_notifications', [
            'room_id' => $this->opdRoom->id,
            'event'   => PharmacyNotification::EVENT_DISPENSED,
            'is_read' => false,
        ]);
    }

    public function test_pharmacist_can_cancel(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_name' => 'Test Medicine', 'quantity' => 1],
                ],
            ]
        );

        $queue = PharmacyQueue::where('patient_id', $this->patient->id)->first();

        $this->actingAs($this->pharmacist)->post("/pharmacy/queue/{$queue->id}/status", [
            'status' => 'Cancelled',
        ]);

        $this->assertDatabaseHas('pharmacy_queue', [
            'id'     => $queue->id,
            'status' => 'Cancelled',
        ]);
    }

    public function test_opd_nurse_cannot_update_pharmacy_queue(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_name' => 'Test Medicine', 'quantity' => 1],
                ],
            ]
        );

        $queue = PharmacyQueue::where('patient_id', $this->patient->id)->first();

        $this->actingAs($this->opdNurse)->post("/pharmacy/queue/{$queue->id}/status", [
            'status' => 'Dispensed',
        ]);

        $this->assertDatabaseHas('pharmacy_queue', [
            'id'     => $queue->id,
            'status' => 'Pending',
        ]);
    }

    // ── 5. Inventory validation ─────────────────────────────────────────────

    public function test_medicine_search_endpoint(): void
    {
        $response = $this->actingAs($this->opdNurse)->get('/pharmacy/medicines/search?q=Test');
        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => $this->medicine->name]);
    }

    public function test_availability_check(): void
    {
        $response = $this->actingAs($this->opdNurse)->post('/pharmacy/medicines/availability', [
            'medicine_id' => $this->medicine->id,
            'quantity'    => 5,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['available' => true]);
    }

    public function test_availability_check_insufficient(): void
    {
        $response = $this->actingAs($this->opdNurse)->post('/pharmacy/medicines/availability', [
            'medicine_id' => $this->medicine->id,
            'quantity'    => 200,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['available' => false]);
    }

    // ── 6. Audit logging ────────────────────────────────────────────────────

    public function test_audit_log_created_on_prescription(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_name' => 'Test Medicine', 'quantity' => 1],
                ],
            ]
        );

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Prescription Created',
            'user_id' => $this->opdNurse->id,
        ]);
    }

    public function test_audit_log_created_on_dispense(): void
    {
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_name' => 'Test Medicine', 'quantity' => 1],
                ],
            ]
        );

        $queue = PharmacyQueue::where('patient_id', $this->patient->id)->first();

        $this->actingAs($this->pharmacist)->post("/pharmacy/queue/{$queue->id}/status", [
            'status' => 'Dispensed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'Prescription Dispensed',
            'user_id' => $this->pharmacist->id,
        ]);
    }

    // ── 7. Prior prescriptions shown ────────────────────────────────────────

    public function test_prescription_form_shows_prior_prescriptions(): void
    {
        // Create a prior prescription
        $this->actingAs($this->opdNurse)->post(
            "/opd/consultation/{$this->queueEntry->id}/prescription",
            [
                'request_date' => today()->toDateString(),
                'items'        => [
                    ['medicine_name' => 'Prior Medicine', 'quantity' => 5],
                ],
            ]
        );

        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/prescription");

        $response->assertInertia(fn ($page) =>
            $page->count('prior_prescriptions', 1)
        );
    }

    // ── 8. Existing functionality not broken ────────────────────────────────

    public function test_existing_lab_request_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}/lab");

        $response->assertOk();
    }

    public function test_existing_consultation_still_works(): void
    {
        $response = $this->actingAs($this->opdNurse)
            ->get("/opd/consultation/{$this->queueEntry->id}");

        $response->assertOk();
    }

    public function test_existing_lab_queue_still_works(): void
    {
        $response = $this->actingAs($this->pharmacist)->get('/lab/queue');
        $response->assertOk();
    }
}
