<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Medicine;
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
        $roles = ['Admin', 'Card Officer', 'Department Head', 'General Manager', 'Recorder', 'OPD Nurse', 'Lab Technician', 'Pharmacist'];
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

        // ── Seed common medicines ──────────────────────────────────────────
        $medicines = [
            ['name' => 'Paracetamol 500mg', 'generic_name' => 'Acetaminophen', 'category' => 'Analgesic', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 0.50, 'quantity_in_stock' => 1000, 'minimum_stock_level' => 100],
            ['name' => 'Paracetamol 120mg/5ml', 'generic_name' => 'Acetaminophen', 'category' => 'Analgesic', 'form' => 'Syrup', 'unit' => 'bottle', 'unit_price' => 2.00, 'quantity_in_stock' => 200, 'minimum_stock_level' => 20],
            ['name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotic', 'form' => 'Capsule', 'unit' => 'pieces', 'unit_price' => 1.00, 'quantity_in_stock' => 500, 'minimum_stock_level' => 50],
            ['name' => 'Amoxicillin 250mg/5ml', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotic', 'form' => 'Syrup', 'unit' => 'bottle', 'unit_price' => 3.00, 'quantity_in_stock' => 150, 'minimum_stock_level' => 20],
            ['name' => 'Metronidazole 400mg', 'generic_name' => 'Metronidazole', 'category' => 'Antibiotic', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 0.80, 'quantity_in_stock' => 400, 'minimum_stock_level' => 50],
            ['name' => 'Ciprofloxacin 500mg', 'generic_name' => 'Ciprofloxacin', 'category' => 'Antibiotic', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 1.50, 'quantity_in_stock' => 300, 'minimum_stock_level' => 30],
            ['name' => 'Artemether/Lumefantrine 20/120mg', 'generic_name' => 'AL Combi', 'category' => 'Antimalarial', 'form' => 'Tablet', 'unit' => 'pack', 'unit_price' => 2.50, 'quantity_in_stock' => 250, 'minimum_stock_level' => 30],
            ['name' => 'Sulfadoxine/Pyrimethamine', 'generic_name' => 'SP', 'category' => 'Antimalarial', 'form' => 'Tablet', 'unit' => 'pack', 'unit_price' => 1.00, 'quantity_in_stock' => 200, 'minimum_stock_level' => 20],
            ['name' => 'Ibuprofen 400mg', 'generic_name' => 'Ibuprofen', 'category' => 'Analgesic', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 0.60, 'quantity_in_stock' => 600, 'minimum_stock_level' => 50],
            ['name' => 'Omeprazole 20mg', 'generic_name' => 'Omeprazole', 'category' => 'Gastrointestinal', 'form' => 'Capsule', 'unit' => 'pieces', 'unit_price' => 1.20, 'quantity_in_stock' => 350, 'minimum_stock_level' => 30],
            ['name' => 'Cetirizine 10mg', 'generic_name' => 'Cetirizine', 'category' => 'Antihistamine', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 0.70, 'quantity_in_stock' => 400, 'minimum_stock_level' => 40],
            ['name' => 'Oral Rehydration Salts', 'generic_name' => 'ORS', 'category' => 'Electrolyte', 'form' => 'Powder', 'unit' => 'sachet', 'unit_price' => 0.30, 'quantity_in_stock' => 800, 'minimum_stock_level' => 100],
            ['name' => 'Salbutamol Inhaler', 'generic_name' => 'Salbutamol', 'category' => 'Respiratory', 'form' => 'Inhaler', 'unit' => 'piece', 'unit_price' => 5.00, 'quantity_in_stock' => 50, 'minimum_stock_level' => 10],
            ['name' => 'Metformin 500mg', 'generic_name' => 'Metformin', 'category' => 'Antidiabetic', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 0.90, 'quantity_in_stock' => 500, 'minimum_stock_level' => 50],
            ['name' => 'Amlodipine 5mg', 'generic_name' => 'Amlodipine', 'category' => 'Antihypertensive', 'form' => 'Tablet', 'unit' => 'pieces', 'unit_price' => 1.00, 'quantity_in_stock' => 400, 'minimum_stock_level' => 40],
        ];

        foreach ($medicines as $medicine) {
            Medicine::firstOrCreate(
                ['name' => $medicine['name']],
                $medicine
            );
        }
    }
}
