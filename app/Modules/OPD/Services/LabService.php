<?php

namespace App\Modules\OPD\Services;

use App\Models\LabQueue;
use App\Models\LabNotification;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabResult;
use App\Models\OpdQueue;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    // ── Create request + auto-enqueue ──────────────────────────────────────

    /**
     * Create a new lab request with its tests and immediately place it
     * into the lab queue with status "Pending". All inserts are atomic.
     */
    public function createRequest(OpdQueue $entry, array $data, int $userId): LabRequest
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            // 1. Header row
            $labRequest = LabRequest::create([
                'opd_queue_id'   => $entry->id,
                'patient_id'     => $entry->patient_id,
                'requested_by'   => $userId,
                'requester_name' => $data['requester_name'] ?? null,
                'signature_data' => $data['signature_data'] ?? null,
                'request_date'   => $data['request_date'],
                'priority'       => $data['priority'],
                'clinical_notes' => $data['clinical_notes'] ?? null,
            ]);

            // 2. Individual test rows
            foreach ($data['tests'] as $testName) {
                LabRequestTest::create([
                    'lab_request_id' => $labRequest->id,
                    'test_name'      => trim($testName),
                ]);
            }

            // 3. Auto-enqueue — every new request starts as Pending
            LabQueue::create([
                'lab_request_id' => $labRequest->id,
                'patient_id'     => $entry->patient_id,
                'status'         => 'Pending',
            ]);

            $this->auditLogService->log(
                'Lab Request Created',
                $labRequest,
                null,
                array_merge($labRequest->toArray(), ['tests' => $data['tests']]),
                $userId,
            );

            return $labRequest->load(['tests', 'requestedBy:id,full_name', 'labQueue']);
        });
    }

    // ── Queue status update ────────────────────────────────────────────────

    /**
     * Advance a lab queue entry to the next status.
     * Validates the transition is allowed before applying it.
     */
    public function updateQueueStatus(LabQueue $entry, string $newStatus, int $userId): LabQueue
    {
        if (! array_key_exists($newStatus, LabQueue::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status.'],
            ]);
        }

        if (! $entry->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$entry->status} to {$newStatus}."],
            ]);
        }

        return DB::transaction(function () use ($entry, $newStatus, $userId) {
            $old = $entry->toArray();

            $timestamps = match ($newStatus) {
                'Received'   => ['received_at'   => now()],
                'Processing' => ['processing_at' => now()],
                'Completed'  => ['completed_at'  => now()],
                'Cancelled'  => ['cancelled_at'  => now()],
                default      => [],
            };

            $entry->update(array_merge(
                ['status' => $newStatus, 'updated_by' => $userId],
                $timestamps,
            ));

            // Fire lab notification to the OPD room that originated the request
            if (in_array($newStatus, ['Received', 'Completed'], true)) {
                $entry->loadMissing([
                    'labRequest.opdQueue',
                    'labRequest.tests',
                    'patient',
                ]);

                $labRequest = $entry->labRequest;
                $roomId     = $labRequest?->opdQueue?->room_id;

                if ($roomId && $labRequest && $entry->patient) {
                    LabNotification::create([
                        'lab_request_id' => $labRequest->id,
                        'room_id'        => $roomId,
                        'patient_id'     => $entry->patient_id,
                        'patient_name'   => $entry->patient->full_name,
                        'card_number'    => $entry->patient->card_number,
                        'event'          => $newStatus === 'Received'
                            ? LabNotification::EVENT_RECEIVED
                            : LabNotification::EVENT_COMPLETED,
                        'test_names'     => $labRequest->tests->pluck('test_name')->all(),
                        'notified_at'    => now(),
                        'is_read'        => false,
                    ]);
                }
            }

            $this->auditLogService->log(
                'Lab Queue Status Updated',
                $entry->fresh(),
                $old,
                $entry->fresh()->toArray(),
                $userId,
            );

            return $entry->fresh(['labRequest.tests', 'labRequest.requestedBy:id,full_name', 'patient', 'updatedBy:id,full_name']);
        });
    }

    // ── Queue list ─────────────────────────────────────────────────────────

    /**
     * Return the active lab queue for today, ordered by:
     *   1. Urgent requests first
     *   2. FIFO within the same priority
     *
     * Optionally filter by status.
     */
    public function queue(?string $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return LabQueue::query()
            ->with([
                'patient:id,full_name,card_number,gender,date_of_birth',
                'labRequest:id,lab_request_id,priority,clinical_notes,request_date,requested_by',
                'labRequest.tests:id,lab_request_id,test_name',
                'labRequest.requestedBy:id,full_name',
                'updatedBy:id,full_name',
            ])
            ->whereDate('created_at', today())
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderByRaw("CASE WHEN (SELECT priority FROM lab_requests WHERE lab_requests.id = lab_queue.lab_request_id) = 'Urgent' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * Summary counts for the queue today — used for stat tiles.
     */
    public function queueStats(): array
    {
        $base = LabQueue::query()->whereDate('created_at', today());

        $counts = [];
        foreach (array_keys(LabQueue::STATUSES) as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    // ── Requests for an encounter (used by LabRequestController) ──────────

    public function requestsForEncounter(int $opdQueueId): Collection
    {
        return LabRequest::query()
            ->with([
                'tests.result.performedBy:id,full_name',
                'requestedBy:id,full_name',
                'labQueue:id,lab_request_id,status',
            ])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LabRequest $r) => [
                'id'             => $r->id,
                'request_date'   => $r->request_date->toDateString(),
                'priority'       => $r->priority,
                'clinical_notes' => $r->clinical_notes,
                'requested_by'   => $r->requestedBy?->full_name,
                'created_at'     => $r->created_at->toDateTimeString(),
                'tests'          => $r->tests->map(fn ($t) => [
                    'id'        => $t->id,
                    'test_name' => $t->test_name,
                    'result'    => $t->result ? [
                        'result'       => $t->result->result,
                        'remarks'      => $t->result->remarks,
                        'result_date'  => $t->result->result_date->toDateString(),
                        'performed_by' => $t->result->performedBy?->full_name,
                    ] : null,
                ])->all(),
                'queue_status'   => $r->labQueue?->status ?? 'Pending',
            ]);
    }

    // ── Result entry ───────────────────────────────────────────────────────

    /**
     * Save results for one or more tests in a request.
     * Each test gets its own LabResult row. Re-saving is prevented by the
     * unique constraint on lab_request_test_id.
     *
     * Automatically marks the queue entry Completed when all tests have results.
     */
    public function saveResults(LabQueue $queueEntry, array $results, int $userId): void
    {
        DB::transaction(function () use ($queueEntry, $results, $userId) {
            $labRequest = $queueEntry->labRequest()->with('tests')->firstOrFail();

            foreach ($results as $testId => $data) {
                if (empty(trim($data['result'] ?? ''))) {
                    continue; // skip blank entries — do not save empty rows
                }

                LabResult::updateOrCreate(
                    ['lab_request_test_id' => (int) $testId],
                    [
                        'lab_request_id'      => $labRequest->id,
                        'patient_id'          => $queueEntry->patient_id,
                        'performed_by'        => $userId,
                        'result'              => trim($data['result']),
                        'remarks'             => filled($data['remarks'] ?? '') ? trim($data['remarks']) : null,
                        'result_date'         => $data['result_date'],
                    ]
                );
            }

            // Auto-complete: if every test now has a result, mark queue Completed
            $labRequest->loadMissing('tests.result');
            $allDone = $labRequest->tests->every(fn ($t) => $t->result !== null);

            if ($allDone && $queueEntry->status !== 'Completed') {
                $this->updateQueueStatus($queueEntry, 'Completed', $userId);
            }

            $this->auditLogService->log(
                'Lab Results Saved',
                $queueEntry->fresh(),
                null,
                ['results_entered' => count($results), 'auto_completed' => $allDone],
                $userId,
            );
        });
    }

    // ── Lab history for patient ────────────────────────────────────────────

    /**
     * All completed lab requests for a patient, newest first.
     * Used by the patient history page.
     */
    public function labHistoryForPatient(int $patientId, int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return LabRequest::query()
            ->with([
                'tests.result.performedBy:id,full_name',
                'requestedBy:id,full_name',
                'labQueue:id,lab_request_id,status,completed_at',
            ])
            ->where('patient_id', $patientId)
            ->whereHas('labQueue', fn ($q) => $q->where('status', 'Completed'))
            ->orderByDesc('request_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'lab_page');
    }
}
