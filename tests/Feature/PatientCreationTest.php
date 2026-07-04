<?php

namespace Tests\Feature;

use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_card_officer_can_create_employee_patient(): void
    {
        $user = User::where('username', 'admin')->first();
        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel = RelationshipType::where('name', 'Employee')->first();

        $response = $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no' => '97266',
            'dependent_no' => 0,
            'full_name' => 'Test Employee',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patients', [
            'card_number' => '97266-0',
            'full_name' => 'Test Employee',
        ]);
    }

    public function test_child_over_18_is_rejected(): void
    {
        $user = User::where('username', 'admin')->first();
        $familyType = PatientType::where('name', 'Family')->first();
        $childRel = RelationshipType::where('name', 'Child')->first();

        $response = $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $familyType->id,
            'relationship_type_id' => $childRel->id,
            'employee_no' => '97266',
            'dependent_no' => 2,
            'full_name' => 'Adult Child',
            'date_of_birth' => now()->subYears(20)->toDateString(),
        ]);

        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_duplicate_card_number_is_rejected_with_helpful_message(): void
    {
        $user = User::where('username', 'admin')->first();
        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel = RelationshipType::where('name', 'Employee')->first();

        $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no' => '97266',
            'dependent_no' => 0,
            'full_name' => 'Existing Employee',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
        ])->assertRedirect();

        $response = $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no' => '97266',
            'dependent_no' => 0,
            'full_name' => 'Duplicate Employee',
            'gender' => 'Male',
            'date_of_birth' => '1985-05-05',
        ]);

        $response->assertSessionHasErrors(['employee_no', 'dependent_no']);
    }
}
