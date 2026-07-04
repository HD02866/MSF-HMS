<?php

namespace Tests\Feature;

use App\Models\PatientType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_insurance_patient_requires_insurance_number(): void
    {
        $user = User::where('username', 'admin')->first();
        $insuranceType = PatientType::where('name', 'Insurance')->first();

        $response = $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $insuranceType->id,
            'full_name' => 'Insurance Patient',
            'date_of_birth' => '1985-05-15',
        ]);

        $response->assertSessionHasErrors('insurance_no');
    }

    public function test_insurance_patient_with_number_is_created(): void
    {
        $user = User::where('username', 'admin')->first();
        $insuranceType = PatientType::where('name', 'Insurance')->first();

        $response = $this->actingAs($user)->post('/patients', [
            'patient_type_id' => $insuranceType->id,
            'insurance_no' => 'INS-12345',
            'full_name' => 'Insurance Patient',
            'date_of_birth' => '1985-05-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patients', [
            'insurance_no' => 'INS-12345',
            'full_name' => 'Insurance Patient',
        ]);
    }
}
