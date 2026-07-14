<?php

namespace App\Modules\Pharmacy\Services;

use App\Models\Medicine;
use App\Models\OpdQueue;
use App\Models\PharmacyNotification;
use App\Models\PharmacyQueue;
use App\Models\PharmacyRequest;
use App\Models\PharmacyRequestItem;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    // ── Create prescription + auto-enqueue ──────────────────────────────────
    public function createPrescription(OpdQueue $entry, array $data, int $userId): PharmacyRequest
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            // 1. Create request header
            $pharmacyRequest = PharmacyRequest::create([
                'opd_queue_id'   => $entry->id,
                'patient_id'     => $entry->patient_id,
                'prescribed_by'  => $userId,
                'prescriber_name'=> $data['prescriber_name'] ?? null,
                'request_date'   => $data['request_date'],
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'is_external'    => $data['is_external'] ?? false,
                'external_notes' => $data['external_notes'] ?? null,
            ]);

            // 2. Create line items
            foreach ($data['items'] as $item) {
                PharmacyRequestItem::create([
                    'pharmacy_request_id' => $pharmacyRequest->id,
                    'medicine_id'         => $item['medicine_id'] ?? null,
                    'medicine_name'       => $item['medicine_name'],
                    'dosage'              => $item['dosage'] ?? null,
                    'frequency'           => $item['frequency'] ?? null,
                    'duration'            => $item['duration'] ?? null,
                    'quantity'            => $item['quantity'] ?? 1,
                    'notes'               => $item['notes'] ?? null,
                ]);
            }

            // 3. Auto-enqueue into pharmacy queue
            PharmacyQueue::create([
                'pharmacy_request_id' => $pharmacyRequest->id,
                'patient_id'          => $entry->patient_id,
                'status'              => 'Pending',
            ]);

            // 4. Fire notification to OPD room
            $roomId = $entry->room_id;
            if ($roomId) {
                PharmacyNotification::create([
                    'pharmacy_request_id' => $pharmacyRequest->id,
                    'room_id'             => $roomId,
                    'patient_id'          => $entry->patient_id,
                    'patient_name'        => $entry->patient->full_name,
                    'card_number'         => $entry->patient->card_number,
                    'event'               => PharmacyNotification::EVENT_SUBMITTED,
                    'medicine_names'      => collect($data['items'])->pluck('medicine_name')->all(),
                    'notified_at'         => now(),
                    'is_read'             => false,
                ]);
            }

            $this->auditLogService->log(
                'Prescription Created',
                $pharmacyRequest,
                null,
                $pharmacyRequest->fresh()->toArray(),
                $userId,
            );

            return $pharmacyRequest->load(['items.medicine', 'pharmacyQueue']);
        });
    }

    // ── Dispense prescription ───────────────────────────────────────────────
    public function dispense(PharmacyQueue $queueEntry, int $userId): PharmacyQueue
    {
        if (! $queueEntry->canTransitionTo('Dispensed')) {
            throw ValidationException::withMessages([
                'status' => ['Cannot dispense this prescription.'],
            ]);
        }

        return DB::transaction(function () use ($queueEntry, $userId) {
            $old = $queueEntry->toArray();

            // Load request with items to check inventory
            $queueEntry->loadMissing('pharmacyRequest.items.medicine');

            // Deduct inventory for internal medicines
            if (! $queueEntry->pharmacyRequest->is_external) {
                $medicineIds = $queueEntry->pharmacyRequest->items
                    ->pluck('medicine_id')
                    ->filter()
                    ->values();

                if ($medicineIds->isNotEmpty()) {
                    $medicines = Medicine::query()
                        ->whereIn('id', $medicineIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($queueEntry->pharmacyRequest->items as $item) {
                        if ($item->medicine_id && isset($medicines[$item->medicine_id])) {
                            $medicine = $medicines[$item->medicine_id];
                            if ($medicine->quantity_in_stock >= $item->quantity) {
                                $medicine->decrement('quantity_in_stock', $item->quantity);
                            }
                        }
                    }
                }
            }

            $queueEntry->update([
                'status'      => 'Dispensed',
                'updated_by'  => $userId,
                'dispensed_at'=> now(),
            ]);

            // Fire notification to OPD room
            $roomId = $queueEntry->pharmacyRequest?->opdQueue?->room_id;
            if ($roomId) {
                PharmacyNotification::create([
                    'pharmacy_request_id' => $queueEntry->pharmacy_request_id,
                    'room_id'             => $roomId,
                    'patient_id'          => $queueEntry->patient_id,
                    'patient_name'        => $queueEntry->patient->full_name,
                    'card_number'         => $queueEntry->patient->card_number,
                    'event'               => PharmacyNotification::EVENT_DISPENSED,
                    'medicine_names'      => $queueEntry->pharmacyRequest->items->pluck('medicine_name')->all(),
                    'notified_at'         => now(),
                    'is_read'             => false,
                ]);
            }

            $this->auditLogService->log(
                'Prescription Dispensed',
                $queueEntry->fresh(),
                $old,
                $queueEntry->fresh()->toArray(),
                $userId,
            );

            return $queueEntry->fresh(['pharmacyRequest.items.medicine', 'patient', 'updatedBy:id,full_name']);
        });
    }

    // ── Cancel prescription ─────────────────────────────────────────────────
    public function cancel(PharmacyQueue $queueEntry, int $userId): PharmacyQueue
    {
        if (! $queueEntry->canTransitionTo('Cancelled')) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel this prescription.'],
            ]);
        }

        return DB::transaction(function () use ($queueEntry, $userId) {
            $old = $queueEntry->toArray();

            $queueEntry->update([
                'status'       => 'Cancelled',
                'updated_by'   => $userId,
                'cancelled_at' => now(),
            ]);

            $this->auditLogService->log(
                'Prescription Cancelled',
                $queueEntry->fresh(),
                $old,
                $queueEntry->fresh()->toArray(),
                $userId,
            );

            return $queueEntry->fresh(['pharmacyRequest.items', 'patient', 'updatedBy:id,full_name']);
        });
    }

    // ── Queue list ─────────────────────────────────────────────────────────
    public function queue(?string $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return PharmacyQueue::query()
            ->with([
                'patient:id,full_name,card_number,gender,date_of_birth',
                'pharmacyRequest:id,opd_queue_id,prescriber_name,request_date,clinical_notes,is_external',
                'pharmacyRequest.items:id,pharmacy_request_id,medicine_name,dosage,frequency,duration,quantity',
                'pharmacyRequest.prescribedBy:id,full_name',
                'updatedBy:id,full_name',
            ])
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    public function queueStats(): array
    {
        $base = PharmacyQueue::query();

        $counts = [];
        foreach (array_keys(PharmacyQueue::STATUSES) as $status) {
            $counts[$status] = (clone $base)->where('status', $status)->count();
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    // ── Dashboard stats ─────────────────────────────────────────────────────
    public function dashboardStats(): array
    {
        $today = PharmacyQueue::query()->whereDate('created_at', today());

        return [
            'pending'    => (clone $today)->where('status', 'Pending')->count(),
            'dispensed'  => (clone $today)->where('status', 'Dispensed')->count(),
            'cancelled'  => (clone $today)->where('status', 'Cancelled')->count(),
            'total'      => (clone $today)->count(),
        ];
    }

    // ── Recent queue for dashboard ──────────────────────────────────────────
    public function recentQueue(int $limit = 10): Collection
    {
        return PharmacyQueue::query()
            ->with([
                'patient:id,full_name,card_number',
                'pharmacyRequest:id,prescriber_name,is_external',
                'pharmacyRequest.items:id,pharmacy_request_id,medicine_name',
            ])
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    // ── Medicine inventory ──────────────────────────────────────────────────
    public function medicineInventory(?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return Medicine::query()
            ->active()
            ->when($search, fn ($q, $s) => $q->where(function ($query) use ($s) {
                $query->where('name', 'ilike', "%{$s}%")
                    ->orWhere('generic_name', 'ilike', "%{$s}%")
                    ->orWhere('category', 'ilike', "%{$s}%");
            }))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function inventoryStats(): array
    {
        $all = Medicine::active()->count();
        $low = Medicine::active()->lowStock()->count();
        $out = Medicine::active()->where('quantity_in_stock', 0)->count();

        return [
            'total'     => $all,
            'low_stock' => $low,
            'out_of_stock' => $out,
        ];
    }

    // ── Prescriptions for encounter ─────────────────────────────────────────
    public function prescriptionsForEncounter(int $opdQueueId): Collection
    {
        return PharmacyRequest::query()
            ->with([
                'items.medicine:id,name',
                'prescribedBy:id,full_name',
                'pharmacyQueue:id,pharmacy_request_id,status',
            ])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PharmacyRequest $r) => [
                'id'              => $r->id,
                'request_date'    => $r->request_date->toDateString(),
                'prescriber_name' => $r->prescriber_name,
                'is_external'     => $r->is_external,
                'clinical_notes'  => $r->clinical_notes,
                'created_at'      => $r->created_at->toDateTimeString(),
                'items'           => $r->items->map(fn ($item) => [
                    'id'            => $item->id,
                    'medicine_name' => $item->medicine_name,
                    'dosage'        => $item->dosage,
                    'frequency'     => $item->frequency,
                    'duration'      => $item->duration,
                    'quantity'      => $item->quantity,
                ])->all(),
                'queue_status'    => $r->pharmacyQueue?->status ?? 'Pending',
            ]);
    }

    // ── Pharmacy history for patient ────────────────────────────────────────
    public function pharmacyHistoryForPatient(int $patientId, int $perPage = 10): LengthAwarePaginator
    {
        return PharmacyRequest::query()
            ->with([
                'items.medicine:id,name',
                'prescribedBy:id,full_name',
                'pharmacyQueue:id,pharmacy_request_id,status,dispensed_at',
            ])
            ->where('patient_id', $patientId)
            ->whereHas('pharmacyQueue', fn ($q) => $q->where('status', 'Dispensed'))
            ->orderByDesc('request_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'pharmacy_page');
    }

    // ── Search medicines for prescription form ──────────────────────────────
    public function searchMedicines(string $query): Collection
    {
        $lowerQuery = strtolower($query);

        return Medicine::query()
            ->active()
            ->where(function ($q) use ($lowerQuery) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$lowerQuery}%"])
                    ->orWhereRaw('LOWER(generic_name) LIKE ?', ["%{$lowerQuery}%"]);
            })
            ->limit(20)
            ->get(['id', 'name', 'generic_name', 'form', 'unit', 'unit_price', 'quantity_in_stock']);
    }

    // ── Check medicine availability ─────────────────────────────────────────
    public function checkAvailability(int $medicineId, int $requestedQty): array
    {
        $medicine = Medicine::findOrFail($medicineId);

        return [
            'medicine'      => $medicine->name,
            'in_stock'      => $medicine->quantity_in_stock,
            'requested'     => $requestedQty,
            'available'     => $medicine->quantity_in_stock >= $requestedQty,
            'low_stock'     => $medicine->is_low_stock,
        ];
    }
}
