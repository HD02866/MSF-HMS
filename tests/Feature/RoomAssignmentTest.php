<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_room_assignment_creates_visit_record(): void
    {
        $user = User::where('username', 'admin')->first();
        $employeeType = PatientType::where('name', 'Employee')->first();
        $employeeRel = RelationshipType::where('name', 'Employee')->first();
        $room = Room::where('room_code', 'OPD5')->first();

        $patient = Patient::create([
            'card_number' => '97266-0',
            'patient_type_id' => $employeeType->id,
            'relationship_type_id' => $employeeRel->id,
            'employee_no' => '97266',
            'dependent_no' => 0,
            'full_name' => 'Test Employee',
            'date_of_birth' => '1990-01-01',
            'status' => 'Active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post('/visits', [
            'patient_id' => $patient->id,
            'room_id' => $room->id,
        ]);

        $response->assertRedirect(route('visits.register'));
        $this->assertDatabaseHas('visits', [
            'patient_id' => $patient->id,
            'room_id' => $room->id,
            'assigned_by' => $user->id,
            'status' => 'Assigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Room Assigned',
            'user_id' => $user->id,
        ]);
    }
}
