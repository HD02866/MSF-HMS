<?php

namespace App\Modules\CardRoom\Services;

use App\Models\Patient;
use App\Models\PatientType;
use App\Models\RelationshipType;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Patient::query()
            ->with(['patientType:id,name', 'relationshipType:id,name'])
            ->select([
                'id', 'card_number', 'patient_type_id', 'relationship_type_id',
                'full_name', 'gender', 'date_of_birth', 'employee_no',
                'insurance_no', 'phone', 'status', 'updated_at',
            ])
            ->when($filters['card_number'] ?? null, fn ($q, $v) => $q->where('card_number', 'ilike', "%{$v}%"))
            ->when($filters['employee_no'] ?? null, fn ($q, $v) => $q->where('employee_no', 'ilike', "%{$v}%"))
            ->when($filters['insurance_no'] ?? null, fn ($q, $v) => $q->where('insurance_no', 'ilike', "%{$v}%"))
            ->when($filters['full_name'] ?? null, fn ($q, $v) => $q->where('full_name', 'ilike', "%{$v}%"))
            ->when($filters['phone'] ?? null, fn ($q, $v) => $q->where('phone', 'ilike', "%{$v}%"))
            ->when($filters['patient_type_id'] ?? null, fn ($q, $v) => $q->where('patient_type_id', $v))
            ->where('status', 'Active')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data, int $userId): Patient
    {
        $this->validateBusinessRules($data);

        return DB::transaction(function () use ($data, $userId) {
            $patientType = PatientType::findOrFail($data['patient_type_id']);
            $cardNumber  = $this->generateCardNumber($data, $patientType);
            $this->ensureUniqueCardNumber($cardNumber, $data);

            // Handle optional photo upload — stored directly in public/images/patients/
            $photoPath = null;
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $filename  = uniqid('patient_', true).'.'.$data['photo']->getClientOriginalExtension();
                $photoPath = $data['photo']->move(public_path('images/patients'), $filename)
                    ? 'images/patients/'.$filename
                    : null;
            }

            $patient = Patient::create([
                ...Arr::except($data, ['assign_room', 'photo', 'os_card_number']),
                'card_number' => $cardNumber,
                'photo_path'  => $photoPath,
                'status'      => 'Active',
                'created_by'  => $userId,
                'updated_by'  => $userId,
            ]);

            $this->auditLogService->log('Patient Created', $patient, null, $patient->toArray(), $userId);

            return $patient->load(['patientType', 'relationshipType']);
        });
    }

    public function update(Patient $patient, array $data, int $userId): Patient
    {
        $this->validateBusinessRules(array_merge($patient->toArray(), $data));

        return DB::transaction(function () use ($patient, $data, $userId) {
            $oldValues = $patient->toArray();

            // Handle optional photo replacement — stored directly in public/images/patients/
            $updateFields = Arr::except($data, ['photo']);
            if (isset($data['photo']) && $data['photo'] instanceof UploadedFile) {
                // Delete the old file if it exists
                if ($patient->photo_path && file_exists(public_path($patient->photo_path))) {
                    @unlink(public_path($patient->photo_path));
                }
                $filename = uniqid('patient_', true).'.'.$data['photo']->getClientOriginalExtension();
                $moved    = $data['photo']->move(public_path('images/patients'), $filename);
                if ($moved) {
                    $updateFields['photo_path'] = 'images/patients/'.$filename;
                }
            }

            // Apply new card number if provided and non-empty, otherwise keep existing
            if (! empty($updateFields['card_number'])) {
                $updateFields['card_number'] = strtoupper(trim($updateFields['card_number']));
            } else {
                unset($updateFields['card_number']); // keep the existing value
            }

            $patient->update([...$updateFields, 'updated_by' => $userId]);

            $updated = $patient->fresh(['patientType', 'relationshipType']);

            $this->auditLogService->log('Patient Updated', $patient, $oldValues, $updated->toArray(), $userId);

            return $updated;
        });
    }

    public function deactivate(Patient $patient, int $userId): Patient
    {
        return DB::transaction(function () use ($patient, $userId) {
            $oldValues = $patient->toArray();
            $patient->update(['status' => 'Inactive', 'updated_by' => $userId]);

            $this->auditLogService->log('Patient Deactivated', $patient, $oldValues, $patient->fresh()->toArray(), $userId);

            return $patient->fresh();
        });
    }

    public function generateCardNumber(array $data, PatientType $patientType): string
    {
        if ($patientType->requiresEmployeeInfo()) {
            $employeeNo  = $data['employee_no'] ?? null;
            $dependentNo = $data['dependent_no'] ?? 0;

            if (! $employeeNo) {
                throw ValidationException::withMessages([
                    'employee_no' => ['Employee number is required for this patient type.'],
                ]);
            }

            return sprintf('%s-%d', $employeeNo, $dependentNo);
        }

        // For OS and all other types: use manually entered card number if provided,
        // otherwise fall back to auto-generated OS-XXXX
        if (! empty($data['os_card_number'])) {
            return strtoupper(trim($data['os_card_number']));
        }

        return 'OS-'.strtoupper(uniqid());
    }

    private function validateBusinessRules(array $data): void
    {
        $patientType = PatientType::find($data['patient_type_id'] ?? null);
        if (! $patientType) {
            return;
        }

        if ($patientType->requiresInsuranceNo() && empty($data['insurance_no'])) {
            throw ValidationException::withMessages([
                'insurance_no' => ['Insurance number is required for Insurance patients.'],
            ]);
        }

        if ($patientType->requiresEmployeeInfo()) {
            if (empty($data['relationship_type_id'])) {
                throw ValidationException::withMessages([
                    'relationship_type_id' => ['Relationship is required for Employee group patients.'],
                ]);
            }

            $relationship = RelationshipType::find($data['relationship_type_id']);
            if ($relationship?->isChild() && ! empty($data['date_of_birth'])) {
                $dob = is_string($data['date_of_birth'])
                    ? Carbon::parse($data['date_of_birth'])
                    : $data['date_of_birth'];

                if ($dob->age >= 18) {
                    throw ValidationException::withMessages([
                        'date_of_birth' => ['Dependent child exceeded age limit. Service not allowed under family account.'],
                    ]);
                }
            }
        }

        if ($patientType->name === 'OS' && ! empty($data['relationship_type_id'])) {
            throw ValidationException::withMessages([
                'relationship_type_id' => ['OS patients must not use Employee relationship.'],
            ]);
        }
    }

    private function ensureUniqueCardNumber(string $cardNumber, array $data): void
    {
        $existing = Patient::query()
            ->where('card_number', $cardNumber)
            ->first();

        if (! $existing) {
            return;
        }

        $messages = [
            'employee_no' => [
                "Card number {$cardNumber} is already assigned to {$existing->full_name}. Search for this patient instead of creating a duplicate.",
            ],
        ];

        if (! empty($data['employee_no'])) {
            $nextDependent = $this->suggestNextDependentNo($data['employee_no']);

            if ($nextDependent !== null) {
                $messages['dependent_no'] = [
                    "Dependent number {$data['dependent_no']} is already used for employee {$data['employee_no']}. Try dependent number {$nextDependent} instead.",
                ];
            }
        }

        throw ValidationException::withMessages($messages);
    }

    private function suggestNextDependentNo(string $employeeNo): ?int
    {
        $used = Patient::query()
            ->where('employee_no', $employeeNo)
            ->pluck('dependent_no')
            ->map(fn ($value) => (int) $value);

        for ($dependentNo = 0; $dependentNo <= 99; $dependentNo++) {
            if (! $used->contains($dependentNo)) {
                return $dependentNo;
            }
        }

        return null;
    }
}
