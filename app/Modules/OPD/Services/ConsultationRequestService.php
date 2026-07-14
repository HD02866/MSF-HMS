<?php

namespace App\Modules\OPD\Services;

use App\Models\ConsultationRequest;
use App\Models\ConsultationRequestNotification;
use App\Models\ConsultationRequestQueue;
use App\Models\OpdQueue;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationRequestService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    // ── Create request + auto-enqueue ──────────────────────────────────────

    public function createRequest(OpdQueue $entry, array $data, int $userId): ConsultationRequest
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            $request = ConsultationRequest::create([
                'opd_queue_id'    => $entry->id,
                'patient_id'      => $entry->patient_id,
                'requested_by'    => $userId,
                'requester_name'  => $data['requester_name'] ?? null,
                'signature_data'  => $data['signature_data'] ?? null,
                'destination'     => $data['destination'],
                'reason'          => $data['reason'],
                'clinical_summary'=> $data['clinical_summary'] ?? null,
                'priority'        => $data['priority'],
                'request_date'    => $data['request_date'],
            ]);

            ConsultationRequestQueue::create([
                'consultation_request_id' => $request->id,
                'patient_id'              => $entry->patient_id,
                'status'                  => 'Pending',
            ]);

            // Notify receiving department (stored with destination for filtering)
            $entry->loadMissing('patient');
            ConsultationRequestNotification::create([
                'consultation_request_id' => $request->id,
                'room_id'                 => $entry->room_id,
                'patient_id'              => $entry->patient_id,
                'patient_name'            => $entry->patient->full_name,
                'card_number'             => $entry->patient->card_number,
                'event'                   => ConsultationRequestNotification::EVENT_SENT,
                'destination'             => $data['destination'],
                'priority'                => $data['priority'],
                'notified_at'             => now(),
                'is_read'                 => false,
            ]);

            $this->auditLogService->log(
                'Consultation Request Created',
                $request,
                null,
                $request->fresh()->toArray(),
                $userId,
            );

            return $request->load(['queue', 'requestedBy:id,full_name']);
        });
    }

    // ── Queue status update ────────────────────────────────────────────────

    public function updateQueueStatus(ConsultationRequestQueue $entry, string $newStatus, int $userId, ?string $responseNotes = null): ConsultationRequestQueue
    {
        if (! array_key_exists($newStatus, ConsultationRequestQueue::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid status.'],
            ]);
        }

        if (! $entry->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$entry->status} to {$newStatus}."],
            ]);
        }

        return DB::transaction(function () use ($entry, $newStatus, $userId, $responseNotes) {
            $old = $entry->toArray();

            $timestamps = match ($newStatus) {
                'Accepted'  => ['accepted_at'  => now()],
                'Rejected'  => ['rejected_at'  => now()],
                'Completed' => ['completed_at' => now()],
                'Cancelled' => ['cancelled_at' => now()],
                default      => [],
            };

            $entry->update(array_merge(
                [
                    'status'         => $newStatus,
                    'updated_by'     => $userId,
                    'response_notes' => $responseNotes,
                ],
                $timestamps,
            ));

            // Notify the originating OPD room about status changes
            $entry->loadMissing([
                'consultationRequest.opdQueue',
                'patient',
            ]);

            $cr          = $entry->consultationRequest;
            $roomId      = $cr?->opdQueue?->room_id;
            $eventMap    = [
                'Accepted'  => ConsultationRequestNotification::EVENT_ACCEPTED,
                'Rejected'  => ConsultationRequestNotification::EVENT_REJECTED,
                'Completed' => ConsultationRequestNotification::EVENT_COMPLETED,
            ];

            if ($roomId && isset($eventMap[$newStatus]) && $entry->patient) {
                ConsultationRequestNotification::create([
                    'consultation_request_id' => $cr->id,
                    'room_id'                 => $roomId,
                    'patient_id'              => $entry->patient_id,
                    'patient_name'            => $entry->patient->full_name,
                    'card_number'             => $entry->patient->card_number,
                    'event'                   => $eventMap[$newStatus],
                    'destination'             => $cr->destination,
                    'priority'                => $cr->priority,
                    'notified_at'             => now(),
                    'is_read'                 => false,
                ]);
            }

            $this->auditLogService->log(
                'Consultation Request Queue Updated',
                $entry->fresh(),
                $old,
                $entry->fresh()->toArray(),
                $userId,
            );

            return $entry->fresh([
                'consultationRequest.requestedBy:id,full_name',
                'patient',
                'updatedBy:id,full_name',
            ]);
        });
    }

    // ── Queue list ─────────────────────────────────────────────────────────

    public function queue(?string $status = null, ?string $destination = null, int $perPage = 25): LengthAwarePaginator
    {
        return ConsultationRequestQueue::query()
            ->with([
                'patient:id,full_name,card_number,gender,date_of_birth',
                'consultationRequest:id,opd_queue_id,destination,reason,priority,request_date,requested_by,requester_name',
                'consultationRequest.requestedBy:id,full_name',
                'updatedBy:id,full_name',
            ])
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($destination, fn ($q, $v) => $q->whereHas('consultationRequest', fn ($cq) => $cq->where('destination', $v)))
            ->orderByRaw("CASE WHEN (SELECT priority FROM consultation_requests WHERE consultation_requests.id = consultation_request_queue.consultation_request_id) = 'Urgent' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public function queueStats(): array
    {
        $base = ConsultationRequestQueue::query();

        $counts = [];
        foreach (array_keys(ConsultationRequestQueue::STATUSES) as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    // ── Requests for an encounter (OPD side history) ───────────────────────

    public function requestsForEncounter(int $opdQueueId): \Illuminate\Support\Collection
    {
        return ConsultationRequest::query()
            ->with([
                'requestedBy:id,full_name',
                'queue:id,consultation_request_id,status',
            ])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ConsultationRequest $r) => [
                'id'              => $r->id,
                'destination'     => $r->destination,
                'reason'          => $r->reason,
                'clinical_summary'=> $r->clinical_summary,
                'priority'        => $r->priority,
                'request_date'    => $r->request_date->toDateString(),
                'requested_by'    => $r->requestedBy?->full_name,
                'created_at'      => $r->created_at->toDateTimeString(),
                'queue_status'    => $r->queue?->status ?? 'Pending',
            ]);
    }
}
