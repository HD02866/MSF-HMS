<?php

namespace App\Modules\CardRoom\Services;

use App\Models\Patient;
use App\Models\Visit;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function assignRoom(Patient $patient, int $roomId, int $userId, ?string $remarks = null): Visit
    {
        if (! $patient->isActive()) {
            throw ValidationException::withMessages([
                'patient_id' => ['Cannot assign room to an inactive patient.'],
            ]);
        }

        $this->validateChildAgeForAssignment($patient);

        return DB::transaction(function () use ($patient, $roomId, $userId, $remarks) {
            $visit = Visit::create([
                'patient_id'   => $patient->id,
                'room_id'      => $roomId,
                'assigned_by'  => $userId,
                'visit_date'   => now()->toDateString(),
                'visit_time'   => now()->toTimeString(),
                'queue_number' => $this->nextQueueNumber($roomId),
                'remarks'      => $remarks,
                'status'       => 'Assigned',
            ]);

            // Single load — used for both audit log and return value
            $visit->load(['patient.patientType', 'patient.relationshipType', 'room', 'assignedBy']);

            $this->auditLogService->log(
                'Room Assigned',
                $visit,
                null,
                $visit->toArray(),
                $userId,
            );

            return $visit;
        });
    }

    public function register(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Visit::query()
            ->with(['patient.patientType', 'room', 'assignedBy'])
            ->when($filters['visit_date'] ?? null, fn ($q, $v) => $q->whereDate('visit_date', $v))
            ->when($filters['room_id'] ?? null, fn ($q, $v) => $q->where('room_id', $v))
            ->when($filters['patient_type_id'] ?? null, function ($q, $v) {
                $q->whereHas('patient', fn ($pq) => $pq->where('patient_type_id', $v));
            })
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function nextQueueNumber(int $roomId): int
    {
        $max = Visit::where('room_id', $roomId)
            ->whereDate('visit_date', today())
            ->max('queue_number');

        return ($max ?? 0) + 1;
    }

    private function validateChildAgeForAssignment(Patient $patient): void
    {
        if ($patient->relationshipType?->isChild() && $patient->age >= 18) {
            throw ValidationException::withMessages([
                'patient_id' => ['Dependent child exceeded age limit. Service not allowed under family account.'],
            ]);
        }
    }
}
