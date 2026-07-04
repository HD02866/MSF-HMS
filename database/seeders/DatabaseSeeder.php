<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Admin', 'Card Officer', 'Department Head', 'General Manager', 'Recorder'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $departments = ['Card Room', 'OPD', 'Emergency', 'Laboratory', 'Pharmacy', 'Billing'];
        foreach ($departments as $department) {
            Department::firstOrCreate(['name' => $department]);
        }

        $patientTypes = [
            'Employee', 'Family', 'OS', 'Insurance',
            'Federal Police', 'Defense Police', 'Kuteba', 'Staff', 'free',
        ];
        foreach ($patientTypes as $type) {
            PatientType::firstOrCreate(['name' => $type]);
        }

        $relationships = ['Employee', 'Wife', 'Child'];
        foreach ($relationships as $relationship) {
            RelationshipType::firstOrCreate(['name' => $relationship]);
        }

        $rooms = [
            ['room_name' => 'OPD 4', 'room_code' => 'OPD4'],
            ['room_name' => 'OPD 5', 'room_code' => 'OPD5'],
            ['room_name' => 'OPD 6', 'room_code' => 'OPD6'],
            ['room_name' => 'OPD 7', 'room_code' => 'OPD7'],
            ['room_name' => 'OPD 8', 'room_code' => 'OPD8'],
            ['room_name' => 'Eye', 'room_code' => 'EYE'],
            ['room_name' => 'Emergency', 'room_code' => 'EMERGENCY'],
            ['room_name' => 'Under 5', 'room_code' => 'UNDER5'],
            ['room_name' => 'Doctor Room', 'room_code' => 'DOCTOR'],
        ];
        foreach ($rooms as $room) {
            Room::firstOrCreate(['room_code' => $room['room_code']], $room);
        }

        $cardRoom = Department::where('name', 'Card Room')->first();
        $adminRole = Role::where('name', 'Admin')->first();

        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@msf-hms.local',
                'password' => 'password',
                'role_id' => $adminRole->id,
                'department_id' => $cardRoom->id,
                'phone' => null,
                'is_active' => true,
            ]
        );
    }
}
