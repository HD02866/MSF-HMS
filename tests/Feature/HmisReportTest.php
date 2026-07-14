<?php

namespace Tests\Feature;

use App\Models\OpdQueue;
use App\Models\OpdClinicalNote;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Visit;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabQueue;
use App\Models\PharmacyRequest;
use App\Models\PharmacyRequestItem;
use App\Models\Medicine;
use App\Models\Referral;
use App\Models\SickLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HmisReportTest extends TestCase
{
    use RefreshDatabase;

    private User     $admin;
    private User     $opdNurse;
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
        $deptHeadRole    = Role::where('name', 'Department Head')->first();

        $this->opdNurse = User::factory()->create([
            'role_id'   => $opdNurseRole->id,
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
            'card_number'          => '57000-0',
            'patient_type_id'      => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no'          => '57000',
            'dependent_no'         => 0,
            'full_name'            => 'HMIS Test Patient',
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
            'arrived_at'   => now()->subMinutes(30),
            'called_at'    => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(5),
            'status'       => 'Completed',
        ]);

        OpdClinicalNote::create([
            'opd_queue_id'     => $this->queueEntry->id,
            'patient_id'       => $this->patient->id,
            'created_by'       => $this->opdNurse->id,
            'chief_complaint'  => 'Headache and fever',
            'diagnosis'        => 'Malaria',
            'treatment_plan'   => 'ACT course',
            'temperature'      => 38.5,
            'systolic_bp'      => 120,
            'diastolic_bp'     => 80,
            'pulse_rate'       => 88,
            'respiratory_rate' => 18,
            'spo2'             => 98,
            'weight'           => 70,
            'height'           => 170,
            'bmi'              => 24.2,
        ]);

        $labRequest = LabRequest::create([
            'opd_queue_id'   => $this->queueEntry->id,
            'patient_id'     => $this->patient->id,
            'requested_by'   => $this->opdNurse->id,
            'requester_name' => $this->opdNurse->full_name,
            'request_date'   => today(),
            'priority'       => 'Normal',
        ]);

        LabRequestTest::create([
            'lab_request_id' => $labRequest->id,
            'test_name'      => 'Malaria Rapid Test (RDT)',
        ]);

        LabQueue::create([
            'lab_request_id' => $labRequest->id,
            'patient_id'     => $this->patient->id,
            'status'         => 'Completed',
            'updated_by'     => $this->admin->id,
            'completed_at'   => now(),
        ]);

        $medicine = Medicine::create([
            'name'                => 'Paracetamol 500mg',
            'generic_name'        => 'Paracetamol',
            'category'            => 'Analgesic',
            'form'                => 'Tablet',
            'unit'                => 'Tablet',
            'unit_price'          => 0.50,
            'quantity_in_stock'   => 1000,
            'minimum_stock_level' => 100,
            'is_active'           => true,
        ]);

        $pharmacyRequest = PharmacyRequest::create([
            'opd_queue_id'   => $this->queueEntry->id,
            'patient_id'     => $this->patient->id,
            'prescribed_by'  => $this->opdNurse->id,
            'prescriber_name'=> $this->opdNurse->full_name,
            'request_date'   => today(),
            'is_external'    => false,
        ]);

        PharmacyRequestItem::create([
            'pharmacy_request_id' => $pharmacyRequest->id,
            'medicine_id'         => $medicine->id,
            'medicine_name'       => 'Paracetamol 500mg',
            'dosage'              => '500mg',
            'frequency'           => 'TDS',
            'duration'            => '5 days',
            'quantity'            => 15,
        ]);

        Referral::create([
            'opd_queue_id'      => $this->queueEntry->id,
            'patient_id'        => $this->patient->id,
            'requested_by'      => $this->opdNurse->id,
            'destination'       => 'Emergency',
            'reason'            => 'Severe malaria with complications',
            'diagnosis'         => 'Severe malaria',
            'doctor_nurse_name' => $this->opdNurse->full_name,
            'date'              => today(),
        ]);

        SickLeave::create([
            'opd_queue_id'      => $this->queueEntry->id,
            'patient_id'        => $this->patient->id,
            'requested_by'      => $this->opdNurse->id,
            'employee_name'     => 'HMIS Test Patient',
            'days'              => 3,
            'start_date'        => today(),
            'end_date'          => today()->addDays(3),
            'diagnosis'         => 'Malaria',
            'recommendation'    => 'Rest at home',
        ]);
    }

    // ── Authorization Tests ───────────────────────────────────────────

    public function test_opd_nurse_can_access_hmis_reports(): void
    {
        $this->actingAs($this->opdNurse)
            ->get('/opd/hmis-reports')
            ->assertStatus(200);
    }

    public function test_admin_can_access_hmis_reports(): void
    {
        $this->actingAs($this->admin)
            ->get('/opd/hmis-reports')
            ->assertStatus(200);
    }

    public function test_dept_head_can_access_hmis_reports(): void
    {
        $this->actingAs($this->deptHead)
            ->get('/opd/hmis-reports')
            ->assertStatus(200);
    }

    // ── Component & Structure Tests ───────────────────────────────────

    public function test_page_renders_correct_component(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page->component('OPD/HmisReports'));
    }

    public function test_overview_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('overview.total_encounters')
                ->has('overview.unique_patients')
                ->has('overview.lab_requests')
                ->has('overview.prescriptions')
                ->has('overview.referrals')
                ->has('overview.sick_leaves')
                ->has('overview.completion_rate')
                ->has('overview.avg_wait_minutes')
            );
    }

    public function test_overview_counts_today_encounters(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->where('overview.total_encounters', 1)
                ->where('overview.unique_patients', 1)
                ->where('overview.lab_requests', 1)
                ->where('overview.prescriptions', 1)
                ->where('overview.referrals', 1)
                ->where('overview.sick_leaves', 1)
            );
    }

    public function test_demographics_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('demographics.total_patients')
                ->has('demographics.by_type')
                ->has('demographics.by_gender')
                ->has('demographics.by_age')
            );
    }

    public function test_disease_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('disease.total_encounters')
                ->has('disease.by_diagnosis')
                ->has('disease.by_complaint')
            );
    }

    public function test_laboratory_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('laboratory.total_requests')
                ->has('laboratory.completed')
                ->has('laboratory.pending')
                ->has('laboratory.urgent')
                ->has('laboratory.by_panel')
                ->has('laboratory.by_test')
            );
    }

    public function test_pharmacy_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('pharmacy.total_prescriptions')
                ->has('pharmacy.total_items')
                ->has('pharmacy.internal')
                ->has('pharmacy.external')
                ->has('pharmacy.by_medicine')
                ->has('pharmacy.by_category')
            );
    }

    public function test_referrals_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('referrals.total_referrals')
                ->has('referrals.by_destination')
                ->has('referrals.by_doctor')
            );
    }

    public function test_sick_leave_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('sickLeave.total_sick_leaves')
                ->has('sickLeave.total_days')
                ->has('sickLeave.avg_days')
                ->has('sickLeave.by_employee')
                ->has('sickLeave.by_diagnosis')
            );
    }

    public function test_visits_has_required_keys(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=' . now()->toDateString())
            ->assertInertia(fn ($page) => $page
                ->has('visits.total')
                ->has('visits.completed')
                ->has('visits.transferred')
                ->has('visits.avg_duration_mins')
                ->has('visits.max_duration_mins')
                ->has('visits.min_duration_mins')
                ->has('visits.by_room')
                ->has('visits.by_doctor')
            );
    }

    // ── Period Tests ──────────────────────────────────────────────────

    public function test_yearly_period_works(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=yearly&date=' . now()->toDateString())
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('overview.period', 'yearly')
                ->where('overview.start_date', now()->startOfYear()->toDateString())
                ->where('overview.end_date', now()->endOfYear()->toDateString())
            );
    }

    public function test_weekly_period_works(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=weekly&date=' . now()->toDateString())
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('overview.period', 'weekly')
                ->where('overview.start_date', now()->startOfWeek()->toDateString())
                ->where('overview.end_date', now()->endOfWeek()->toDateString())
            );
    }

    public function test_monthly_period_works(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=monthly&date=' . now()->toDateString())
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('overview.period', 'monthly')
                ->where('overview.start_date', now()->startOfMonth()->toDateString())
                ->where('overview.end_date', now()->endOfMonth()->toDateString())
            );
    }

    // ── Export Tests ──────────────────────────────────────────────────

    public function test_export_excel_returns_download(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports/export/excel?period=daily&date=' . now()->toDateString())
            ->assertStatus(200);
    }

    public function test_export_pdf_returns_download(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports/export/pdf?period=daily&date=' . now()->toDateString())
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }

    // ── Empty Data Tests ──────────────────────────────────────────────

    public function test_reports_with_no_data_returns_zeros(): void
    {
        $this->actingAs($this->opdNurse);

        $this->get('/opd/hmis-reports?period=daily&date=2020-01-01')
            ->assertInertia(fn ($page) => $page
                ->where('overview.total_encounters', 0)
                ->where('overview.lab_requests', 0)
                ->where('overview.prescriptions', 0)
                ->where('overview.referrals', 0)
                ->where('overview.sick_leaves', 0)
            );
    }

    // ── Service Unit Tests ────────────────────────────────────────────

    public function test_service_period_range_daily(): void
    {
        $service = new \App\Modules\OPD\Services\HmisReportService();
        $date = now();
        [$start, $end] = $service->periodRange('daily', $date);

        $this->assertEquals($date->copy()->startOfDay()->toDateTimeString(), $start->toDateTimeString());
        $this->assertEquals($date->copy()->endOfDay()->toDateTimeString(), $end->toDateTimeString());
    }

    public function test_service_period_range_yearly(): void
    {
        $service = new \App\Modules\OPD\Services\HmisReportService();
        $date = now();
        [$start, $end] = $service->periodRange('yearly', $date);

        $this->assertEquals($date->copy()->startOfYear()->toDateTimeString(), $start->toDateTimeString());
        $this->assertEquals($date->copy()->endOfYear()->toDateTimeString(), $end->toDateTimeString());
    }
}
