<?php

namespace App\Modules\OPD\Services;

use App\Models\DailyRegister;
use App\Models\OpdQueue;
use App\Models\Referral;
use App\Models\ReferralNotification;
use App\Models\SickLeave;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    // ── Referral ────────────────────────────────────────────────────────────

    public function createReferral(OpdQueue $entry, array $data, int $userId): Referral
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            $referral = Referral::create([
                'opd_queue_id'       => $entry->id,
                'patient_id'         => $entry->patient_id,
                'requested_by'       => $userId,
                'destination'        => $data['destination'],
                'reason'             => $data['reason'],
                'diagnosis'          => $data['diagnosis'],
                'doctor_nurse_name'  => $data['doctor_nurse_name'],
                'signature_data'     => $data['signature_data'] ?? null,
                'date'               => $data['date'],
            ]);

            // Auto-create DailyRegister entry so it appears in the Recorder's Daily Register
            $entry->loadMissing('patient', 'room');
            DailyRegister::create([
                'patient_id'      => $entry->patient_id,
                'register_type'   => 'referral_accident',
                'record_date'     => $data['date'],
                'department_name' => $data['destination'],
                'referred_from'   => $entry->room?->room_name ?? 'OPD',
                'created_by'      => $userId,
            ]);

            // Notify OPD room (so OPD bell panel shows confirmation)
            ReferralNotification::create([
                'type'         => ReferralNotification::EVENT_REFERRAL_CREATED,
                'record_id'    => $referral->id,
                'record_type'  => 'referral',
                'patient_id'   => $entry->patient_id,
                'patient_name' => $entry->patient?->full_name ?? '—',
                'card_number'  => $entry->patient?->card_number ?? '—',
                'opd_room_id'  => $entry->room_id,
                'is_read'      => false,
                'notified_at'  => now(),
            ]);

            $this->auditLogService->log(
                'Referral Created',
                $referral,
                null,
                $referral->fresh()->toArray(),
                $userId,
            );

            return $referral->fresh(['requestedBy:id,full_name']);
        });
    }

    // ── Sick Leave ──────────────────────────────────────────────────────────

    public function createSickLeave(OpdQueue $entry, array $data, int $userId): SickLeave
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            $sickLeave = SickLeave::create([
                'opd_queue_id'    => $entry->id,
                'patient_id'      => $entry->patient_id,
                'requested_by'    => $userId,
                'employee_name'   => $data['employee_name'],
                'days'            => $data['days'],
                'start_date'      => $data['start_date'],
                'end_date'        => $data['end_date'],
                'diagnosis'       => $data['diagnosis'],
                'recommendation'  => $data['recommendation'] ?? null,
                'signature_data'  => $data['signature_data'] ?? null,
            ]);

            // Auto-create DailyRegister entry so it appears in the Recorder's Daily Register
            $entry->loadMissing('patient', 'room');
            DailyRegister::create([
                'patient_id'      => $entry->patient_id,
                'register_type'   => 'referral_sick_leave',
                'record_date'     => $data['start_date'],
                'department_name' => $data['employee_name'],
                'referred_from'   => $entry->room?->room_name ?? 'OPD',
                'days_given'      => $data['days'],
                'created_by'      => $userId,
            ]);

            // Notify OPD room (so OPD bell panel shows confirmation)
            ReferralNotification::create([
                'type'         => ReferralNotification::EVENT_SICK_LEAVE_CREATED,
                'record_id'    => $sickLeave->id,
                'record_type'  => 'sick_leave',
                'patient_id'   => $entry->patient_id,
                'patient_name' => $entry->patient?->full_name ?? '—',
                'card_number'  => $entry->patient?->card_number ?? '—',
                'opd_room_id'  => $entry->room_id,
                'is_read'      => false,
                'notified_at'  => now(),
            ]);

            $this->auditLogService->log(
                'Sick Leave Created',
                $sickLeave,
                null,
                $sickLeave->fresh()->toArray(),
                $userId,
            );

            return $sickLeave->fresh(['requestedBy:id,full_name']);
        });
    }

    // ── History queries ─────────────────────────────────────────────────────

    public function referralsForPatient(int $patientId): \Illuminate\Support\Collection
    {
        return Referral::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('date')
            ->get();
    }

    public function sickLeavesForPatient(int $patientId): \Illuminate\Support\Collection
    {
        return SickLeave::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('start_date')
            ->get();
    }

    public function referralsForEncounter(int $opdQueueId): \Illuminate\Support\Collection
    {
        return Referral::query()
            ->with(['requestedBy:id,full_name'])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('date')
            ->get();
    }

    public function sickLeavesForEncounter(int $opdQueueId): \Illuminate\Support\Collection
    {
        return SickLeave::query()
            ->with(['requestedBy:id,full_name'])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('start_date')
            ->get();
    }

    // ── Notifications ───────────────────────────────────────────────────────

    public function notifications(int $roomId, int $limit = 20): \Illuminate\Support\Collection
    {
        return ReferralNotification::query()
            ->where('opd_room_id', $roomId)
            ->orderByDesc('notified_at')
            ->limit($limit)
            ->get();
    }
}
