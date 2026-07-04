<?php

namespace Tests\Unit;

use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Modules\CardRoom\Services\PatientService;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class PatientServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_generates_card_number_for_employee_family(): void
    {
        $service = new PatientService(new AuditLogService);
        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel = RelationshipType::where('name', 'Employee')->first();

        $cardNumber = $service->generateCardNumber([
            'employee_no' => '97266',
            'dependent_no' => 1,
        ], $employeeType);

        $this->assertSame('97266-1', $cardNumber);
    }

    public function test_child_age_validation_throws_exception(): void
    {
        $this->expectException(ValidationException::class);

        $service = new PatientService(new AuditLogService);
        $familyType = PatientType::where('name', 'Family')->first();
        $childRel = RelationshipType::where('name', 'Child')->first();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('validateBusinessRules');
        $method->setAccessible(true);
        $method->invoke($service, [
            'patient_type_id' => $familyType->id,
            'relationship_type_id' => $childRel->id,
            'employee_no' => '97266',
            'dependent_no' => 2,
            'date_of_birth' => now()->subYears(20)->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
