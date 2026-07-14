<?php

namespace App\Modules\OPD\Services;

use App\Models\OpdNotification;
use App\Models\OpdQueue;
use App\Models\OpdClinicalNote;
use App\Models\OpdAttachment;
use App\Models\DailyRegister;
use App\Models\LabNotification;
use App\Models\LabRequest;
use App\Models\PharmacyNotification;
use App\Models\Referral;
use App\Models\ConsultationRequestNotification;
use App\Models\ReferralNotification;
use App\Models\Room;
use App\Models\SickLeave;
use App\Models\Visit;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpdService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    // ── Queue entry creation (called from VisitService after room assignment) ─

    public function enqueue(Visit $visit): OpdQueue
    {
        return DB::transaction(function () use ($visit) {
            $visit->load(['patient', 'room']);

            $entry = OpdQueue::create([
                'visit_id'      => $visit->id,
                'patient_id'    => $visit->patient_id,
                'room_id'       => $visit->room_id,
                'queue_number'  => $visit->queue_number,
                'arrived_at'    => now(),
                'status'        => 'Waiting',
            ]);

            // Create notification for the OPD room
            OpdNotification::create([
                'opd_queue_id'    => $entry->id,
                'room_id'         => $visit->room_id,
                'patient_id'      => $visit->patient_id,
                'patient_name'    => $visit->patient->full_name,
                'card_number'     => $visit->patient->card_number,
                'queue_number'    => $visit->queue_number,
                'assignment_time' => now(),
                'is_read'         => false,
            ]);

            $this->auditLogService->log('OPD Queue Entry Created', $entry, null, $entry->toArray());

            return $entry->load(['patient', 'room']);
        });
    }

    // ── Queue status update ────────────────────────────────────────────────

    public function updateStatus(
        OpdQueue $entry,
        string $status,
        int $userId,
        ?int $destinationRoomId = null,
    ): OpdQueue {
        if (! array_key_exists($status, OpdQueue::STATUSES)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid queue status.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $status, $userId, $destinationRoomId) {
            $old = $entry->toArray();

            $updates = ['status' => $status];

            if ($status === 'Called' && ! $entry->called_at) {
                $updates['called_at'] = now();
            }

            if (in_array($status, ['Completed', 'Transferred', 'Cancelled'], true)) {
                $updates['completed_at'] = now();
            }

            $entry->update($updates);

            // ── ISSUE 3: Update Visit.status for terminal statuses ──────────
            $entry->loadMissing('visit');
            if ($entry->visit && $entry->visit->status !== $status) {
                $entry->visit->update(['status' => $status]);
            }

            // ── ISSUE 2: Create Visit + OpdQueue in destination room ────────
            if ($status === 'Transferred' && $destinationRoomId) {
                $this->transferPatient($entry, $destinationRoomId, $userId);
            }

            $this->auditLogService->log('OPD Status Updated', $entry, $old, $entry->fresh()->toArray(), $userId);

            return $entry->fresh(['patient', 'room']);
        });
    }

    // ── Transfer patient to another room ─────────────────────────────────────

    /**
     * Create a new Visit + OpdQueue (if OPD room) in the destination room
     * and notify the destination room.
     */
    private function transferPatient(OpdQueue $entry, int $destinationRoomId, int $userId): void
    {
        $entry->loadMissing('visit', 'patient');

        $destinationRoom = Room::findOrFail($destinationRoomId);
        $visit = $entry->visit;

        // Create a new Visit record for the destination room
        $newVisit = Visit::create([
            'patient_id'  => $entry->patient_id,
            'room_id'     => $destinationRoomId,
            'assigned_by' => $userId,
            'visit_date'  => now()->toDateString(),
            'visit_time'  => now()->toTimeString(),
            'queue_number'=> $visit?->queue_number,
            'remarks'     => 'Transferred from '.($entry->room?->room_name ?? 'unknown room'),
            'status'      => 'Assigned',
        ]);

        // If destination is an OPD room, create an OpdQueue entry
        if (in_array($destinationRoom->room_code, OpdQueue::OPD_ROOM_CODES, true)) {
            $queueEntry = OpdQueue::create([
                'visit_id'     => $newVisit->id,
                'patient_id'   => $entry->patient_id,
                'room_id'      => $destinationRoomId,
                'queue_number' => $visit?->queue_number,
                'arrived_at'   => now(),
                'status'       => 'Waiting',
            ]);

            // Notify the destination OPD room
            OpdNotification::create([
                'opd_queue_id'    => $queueEntry->id,
                'room_id'         => $destinationRoomId,
                'patient_id'      => $entry->patient_id,
                'patient_name'    => $entry->patient->full_name ?? 'Unknown',
                'card_number'     => $entry->patient->card_number ?? '',
                'queue_number'    => $visit?->queue_number ?? 0,
                'assignment_time' => now(),
                'is_read'         => false,
            ]);
        }
    }

    // ── Call next patient (FIFO) ───────────────────────────────────────────

    public function callNext(int $roomId, int $userId): ?OpdQueue
    {
        $next = OpdQueue::where('room_id', $roomId)
            ->where('status', 'Waiting')
            ->whereDate('arrived_at', today())
            ->orderBy('queue_number')
            ->first();

        if (! $next) {
            return null;
        }

        return $this->updateStatus($next, 'Called', $userId);
    }

    // ── Queue list for a room ──────────────────────────────────────────────

    public function queueForRoom(int $roomId, ?string $status = null): Collection
    {
        return OpdQueue::query()
            ->with(['patient:id,full_name,card_number,gender,date_of_birth', 'room:id,room_name'])
            ->where('room_id', $roomId)
            ->whereDate('arrived_at', today())
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('queue_number')
            ->get();
    }

    // ── Dashboard stats for an OPD room ───────────────────────────────────

    public function dashboardStats(int $roomId): array
    {
        $base = OpdQueue::where('room_id', $roomId)->whereDate('arrived_at', today());

        $waiting   = (clone $base)->where('status', 'Waiting')->count();
        $total     = (clone $base)->count();
        $completed = (clone $base)->where('status', 'Completed')->count();
        $transferred = (clone $base)->where('status', 'Transferred')->count();

        $current = (clone $base)
            ->with('patient:id,full_name,card_number,gender,date_of_birth')
            ->where('status', 'In Consultation')
            ->orderByDesc('called_at')
            ->first();

        // Unread = OPD room-assignment notifications + lab + pharmacy + referral + consultation request
        $unread = OpdNotification::where('room_id', $roomId)->where('is_read', false)->count()
                + LabNotification::where('room_id', $roomId)->where('is_read', false)->count()
                + PharmacyNotification::where('room_id', $roomId)->where('is_read', false)->count()
                + ReferralNotification::where('opd_room_id', $roomId)->where('is_read', false)->count()
                + ConsultationRequestNotification::where('room_id', $roomId)->where('is_read', false)->count();

        return [
            'waiting'       => $waiting,
            'total_today'   => $total,
            'completed'     => $completed,
            'transferred'   => $transferred,
            'unread'        => $unread,
            'current'       => $current,
        ];
    }

    // ── Notifications for a room ───────────────────────────────────────────

    /**
     * Returns a merged, time-sorted list of OPD room-assignment notifications
     * AND lab status notifications for the given room.
     * The frontend bell renders all of them using a unified shape.
     */
    public function notifications(int $roomId, int $limit = 20): Collection
    {
        // OPD room-assignment notifications
        $opdNotifs = OpdNotification::where('room_id', $roomId)
            ->orderByDesc('assignment_time')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'              => 'opd-'.$n->id,
                'type'            => 'opd_assigned',
                'patient_name'    => $n->patient_name,
                'card_number'     => $n->card_number,
                'event_label'     => 'OPD Assigned',
                'test_names'      => [],
                'notified_at'     => $n->assignment_time->toDateTimeString(),
                'is_read'         => $n->is_read,
            ]);

        // Lab status notifications
        $labNotifs = LabNotification::where('room_id', $roomId)
            ->orderByDesc('notified_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'           => 'lab-'.$n->id,
                'type'         => $n->event,
                'patient_name' => $n->patient_name,
                'card_number'  => $n->card_number,
                'event_label'  => LabNotification::EVENT_LABELS[$n->event] ?? $n->event,
                'test_names'   => $n->test_names,
                'notified_at'  => $n->notified_at->toDateTimeString(),
                'is_read'      => $n->is_read,
            ]);

        // Pharmacy notifications
        $pharmacyNotifs = PharmacyNotification::where('room_id', $roomId)
            ->orderByDesc('notified_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'           => 'pharmacy-'.$n->id,
                'type'         => $n->event,
                'patient_name' => $n->patient_name,
                'card_number'  => $n->card_number,
                'event_label'  => PharmacyNotification::EVENT_LABELS[$n->event] ?? $n->event,
                'test_names'   => $n->medicine_names ?? [],
                'notified_at'  => $n->notified_at->toDateTimeString(),
                'is_read'      => $n->is_read,
            ]);

        // Referral / Sick Leave notifications
        $referralNotifs = ReferralNotification::where('opd_room_id', $roomId)
            ->orderByDesc('notified_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'           => 'referral-'.$n->id,
                'type'         => $n->type,
                'patient_name' => $n->patient_name,
                'card_number'  => $n->card_number,
                'event_label'  => ReferralNotification::EVENT_LABELS[$n->type] ?? $n->type,
                'test_names'   => [],
                'notified_at'  => $n->notified_at->toDateTimeString(),
                'is_read'      => $n->is_read,
            ]);

        // Consultation request notifications
        $crNotifs = ConsultationRequestNotification::where('room_id', $roomId)
            ->orderByDesc('notified_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id'           => 'cr-'.$n->id,
                'type'         => $n->event,
                'patient_name' => $n->patient_name,
                'card_number'  => $n->card_number,
                'event_label'  => ConsultationRequestNotification::EVENT_LABELS[$n->event] ?? $n->event,
                'test_names'   => [],
                'notified_at'  => $n->notified_at->toDateTimeString(),
                'is_read'      => $n->is_read,
            ]);

        return $opdNotifs->concat($labNotifs)->concat($pharmacyNotifs)->concat($referralNotifs)->concat($crNotifs)
            ->sortByDesc('notified_at')
            ->take($limit)
            ->values();
    }

    public function markAllRead(int $roomId): void
    {
        OpdNotification::where('room_id', $roomId)->where('is_read', false)->update(['is_read' => true]);
        LabNotification::where('room_id', $roomId)->where('is_read', false)->update(['is_read' => true]);
        PharmacyNotification::where('room_id', $roomId)->where('is_read', false)->update(['is_read' => true]);
        ReferralNotification::where('opd_room_id', $roomId)->where('is_read', false)->update(['is_read' => true]);
        ConsultationRequestNotification::where('room_id', $roomId)->where('is_read', false)->update(['is_read' => true]);
    }

    // ── Attachments ─────────────────────────────────────────────────────────

    /**
     * Store one uploaded file and create an OpdAttachment record.
     * Files are saved directly in public/opd-attachments/ — no symlink needed.
     */
    public function saveAttachment(
        OpdQueue $entry,
        \Illuminate\Http\UploadedFile $file,
        int $userId,
    ): OpdAttachment {
        return DB::transaction(function () use ($entry, $file, $userId) {
            $mimeType  = $file->getMimeType() ?? 'application/octet-stream';
            $type      = OpdAttachment::resolveType($mimeType);
            $filename  = uniqid('opd_att_', true).'_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('opd-attachments'), $filename);

            $attachment = OpdAttachment::create([
                'opd_queue_id'  => $entry->id,
                'patient_id'    => $entry->patient_id,
                'uploaded_by'   => $userId,
                'original_name' => $file->getClientOriginalName(),
                'stored_path'   => 'opd-attachments/'.$filename,
                'mime_type'     => $mimeType,
                'file_size'     => $file->getSize(),
                'type'          => $type,
            ]);

            $this->auditLogService->log(
                'OPD Attachment Uploaded',
                $attachment,
                null,
                ['original_name' => $attachment->original_name, 'type' => $type],
                $userId,
            );

            return $attachment->load('uploadedBy:id,full_name');
        });
    }

    /**
     * Load all attachments for a queue entry, most recent first.
     */
    public function attachmentsForEncounter(int $opdQueueId): \Illuminate\Support\Collection
    {
        return OpdAttachment::query()
            ->with('uploadedBy:id,full_name')
            ->select(['id', 'opd_queue_id', 'original_name', 'stored_path', 'mime_type', 'file_size', 'type', 'uploaded_by', 'created_at'])
            ->where('opd_queue_id', $opdQueueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'url'           => $a->url,
                'mime_type'     => $a->mime_type,
                'file_size'     => $a->file_size_human,
                'type'          => $a->type,
                'uploaded_by'   => $a->uploadedBy?->full_name,
                'uploaded_at'   => $a->created_at->toDateTimeString(),
            ]);
    }

    // ── Clinical Notes ──────────────────────────────────────────────────────

    /**
     * Save a new clinical note for a queue entry.
     * Each call creates a new record — previous notes are never overwritten.
     */
    public function saveClinicalNote(OpdQueue $entry, array $data, int $userId): OpdClinicalNote
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            $note = OpdClinicalNote::create([
                'opd_queue_id'           => $entry->id,
                'patient_id'             => $entry->patient_id,
                'created_by'             => $userId,
                'chief_complaint'        => $data['chief_complaint']        ?? null,
                'history'                => $data['history']                ?? null,
                'physical_examination'   => $data['physical_examination']   ?? null,
                'diagnosis'              => $data['diagnosis']              ?? null,
                'treatment_plan'         => $data['treatment_plan']         ?? null,
                'follow_up_instructions' => $data['follow_up_instructions'] ?? null,
                'temperature'            => $data['temperature']            ?? null,
                'systolic_bp'            => $data['systolic_bp']            ?? null,
                'diastolic_bp'           => $data['diastolic_bp']           ?? null,
                'pulse_rate'             => $data['pulse_rate']             ?? null,
                'respiratory_rate'       => $data['respiratory_rate']       ?? null,
                'spo2'                   => $data['spo2']                   ?? null,
                'weight'                 => $data['weight']                 ?? null,
                'height'                 => $data['height']                 ?? null,
                'bmi'                    => $data['bmi']                    ?? null,
                'random_blood_sugar'     => $data['random_blood_sugar']     ?? null,
            ]);

            $this->auditLogService->log(
                'OPD Clinical Note Saved',
                $note,
                null,
                $note->toArray(),
                $userId,
            );

            return $note->load('creator:id,full_name');
        });
    }

    /**
     * Load all clinical notes for a patient, most recent first.
     */
    public function clinicalNotesForPatient(int $patientId, int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return OpdClinicalNote::query()
            ->with([
                'creator:id,full_name',
                'opdQueue:id,queue_number,arrived_at,room_id',
                'opdQueue.room:id,room_name',
            ])
            ->select([
                'id', 'opd_queue_id', 'patient_id', 'created_by', 'created_at',
                'chief_complaint', 'history', 'physical_examination',
                'diagnosis', 'treatment_plan', 'follow_up_instructions',
                'temperature', 'systolic_bp', 'diastolic_bp', 'pulse_rate',
                'respiratory_rate', 'spo2', 'weight', 'height', 'bmi', 'random_blood_sugar',
            ])
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'notes_page');
    }

    // ── Complete a consultation (atomic) ──────────────────────────────────

    /**
     * Atomically complete a consultation:
     *  1. Save / update the clinical note (creates a new record — never overwrites).
     *  2. Mark the OPD queue entry as Completed (sets completed_at).
     *  3. Mark the associated Visit as Completed.
     *  4. Emit a single audit-log entry that captures the whole outcome.
     *
     * This is the single "save everything + close" operation triggered when
     * a clinician clicks "Complete Consultation".
     */
    public function completeConsultation(
        OpdQueue $entry,
        array    $noteData,
        int      $userId,
    ): OpdQueue {
        return DB::transaction(function () use ($entry, $noteData, $userId) {
            // 1. Save clinical note (non-destructive: creates a new version every time)
            $hasContent = array_filter($noteData, fn ($v) => filled($v));
            if ($hasContent) {
                $note = OpdClinicalNote::create([
                    'opd_queue_id'           => $entry->id,
                    'patient_id'             => $entry->patient_id,
                    'created_by'             => $userId,
                    'chief_complaint'        => $noteData['chief_complaint']        ?? null,
                    'history'                => $noteData['history']                ?? null,
                    'physical_examination'   => $noteData['physical_examination']   ?? null,
                    'diagnosis'              => $noteData['diagnosis']              ?? null,
                    'treatment_plan'         => $noteData['treatment_plan']         ?? null,
                    'follow_up_instructions' => $noteData['follow_up_instructions'] ?? null,
                    'temperature'            => $noteData['temperature']            ?? null,
                    'systolic_bp'            => $noteData['systolic_bp']            ?? null,
                    'diastolic_bp'           => $noteData['diastolic_bp']           ?? null,
                    'pulse_rate'             => $noteData['pulse_rate']             ?? null,
                    'respiratory_rate'       => $noteData['respiratory_rate']       ?? null,
                    'spo2'                   => $noteData['spo2']                   ?? null,
                    'weight'                 => $noteData['weight']                 ?? null,
                    'height'                 => $noteData['height']                 ?? null,
                    'bmi'                    => $noteData['bmi']                    ?? null,
                    'random_blood_sugar'     => $noteData['random_blood_sugar']     ?? null,
                ]);
            }

            // 2. Mark OPD queue as Completed
            $oldQueue = $entry->toArray();
            $entry->update([
                'status'       => 'Completed',
                'completed_at' => now(),
            ]);

            // 3. Mark the associated Visit as Completed (never overwrite if already set)
            $entry->loadMissing('visit');
            if ($entry->visit && $entry->visit->status !== 'Completed') {
                $entry->visit->update(['status' => 'Completed']);
            }

            // 4. Single audit log entry
            $this->auditLogService->log(
                'OPD Consultation Completed',
                $entry->fresh(),
                $oldQueue,
                array_merge(
                    $entry->fresh()->toArray(),
                    ['clinical_note_saved' => isset($note)],
                ),
                $userId,
            );

            return $entry->fresh(['patient', 'room', 'visit', 'clinicalNote']);
        });
    }

    // ── Check if a room is an OPD room ────────────────────────────────────

    public static function isOpdRoom(Room $room): bool
    {
        return in_array($room->room_code, OpdQueue::OPD_ROOM_CODES, true);
    }

    // ── Patient history ────────────────────────────────────────────────────

    /**
     * Load a patient's complete history from existing data sources.
     *
     * The paginated sections (visits, registers, opd_encounters) drive the
     * tabular views with their own pagination controls.
     *
     * The timeline is built from ALL records (not just the current page) so
     * the chronological view is always complete. OPD encounters include a
     * brief clinical-note summary when one exists.
     */
    public function patientHistory(int $patientId, int $perPage = 10): array
    {
        // 1. Previous visits (paginated for the table view)
        $visits = Visit::query()
            ->with(['room:id,room_name,room_code', 'assignedBy:id,full_name'])
            ->select(['id', 'patient_id', 'room_id', 'assigned_by', 'visit_date', 'visit_time', 'queue_number', 'remarks', 'status'])
            ->where('patient_id', $patientId)
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->paginate($perPage, ['*'], 'visits_page');

        // 2. Daily register entries (paginated for the table view)
        $registers = DailyRegister::query()
            ->with(['creator:id,full_name'])
            ->select(['id', 'patient_id', 'register_type', 'record_date', 'department_name', 'referred_from', 'days_given', 'created_by'])
            ->where('patient_id', $patientId)
            ->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'registers_page');

        // 3. Referral history (non-paginated list from dedicated referrals table)
        $referrals = Referral::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('date')
            ->get();

        // 4. Sick leave history (non-paginated list from dedicated sick_leaves table)
        $sickLeaves = SickLeave::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('start_date')
            ->get();

        // 5. OPD encounters (paginated for the table view), with latest note for summary
        $opdEncounters = OpdQueue::query()
            ->with([
                'room:id,room_name,room_code',
                'clinicalNote:id,opd_queue_id,diagnosis,chief_complaint,temperature,systolic_bp,diastolic_bp,pulse_rate,respiratory_rate,spo2,weight,height,bmi,random_blood_sugar,created_at',
            ])
            ->select(['id', 'patient_id', 'room_id', 'queue_number', 'arrived_at', 'called_at', 'completed_at', 'status'])
            ->where('patient_id', $patientId)
            ->orderByDesc('arrived_at')
            ->paginate($perPage, ['*'], 'opd_page');

        // 6. Completed lab requests for the dedicated lab history section (paginated)
        $labResults = LabRequest::query()
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

        // ── Timeline ────────────────────────────────────────────────────────
        // Pull ALL records (no pagination limit) for a complete chronological view.

        $allVisits = Visit::query()
            ->with(['room:id,room_name', 'assignedBy:id,full_name'])
            ->select(['id', 'patient_id', 'room_id', 'assigned_by', 'visit_date', 'visit_time', 'queue_number', 'remarks', 'status'])
            ->where('patient_id', $patientId)
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->get();

        $allRegisters = DailyRegister::query()
            ->with(['creator:id,full_name'])
            ->select(['id', 'patient_id', 'register_type', 'record_date', 'department_name', 'referred_from', 'days_given', 'created_by'])
            ->where('patient_id', $patientId)
            ->orderByDesc('record_date')
            ->get();

        $allOpdEncounters = OpdQueue::query()
            ->with([
                'room:id,room_name',
                'clinicalNote:id,opd_queue_id,diagnosis,chief_complaint,temperature,systolic_bp,diastolic_bp,pulse_rate,respiratory_rate,spo2,weight,height,bmi,random_blood_sugar',
            ])
            ->select(['id', 'patient_id', 'room_id', 'queue_number', 'arrived_at', 'called_at', 'completed_at', 'status'])
            ->where('patient_id', $patientId)
            ->orderByDesc('arrived_at')
            ->get();

        $timelineItems = collect();

        foreach ($allVisits as $v) {
            $timelineItems->push([
                'type'        => 'visit',
                'date'        => $v->visit_date->toDateString(),
                'time'        => $v->visit_time,
                'title'       => 'Room Assignment — '.($v->room?->room_name ?? '—'),
                'detail'      => $v->remarks,
                'badge'       => $v->status,
                'badge_color' => 'bg-green-100 text-green-800',
                'meta'        => 'Assigned by: '.($v->assignedBy?->full_name ?? '—'),
            ]);
        }

        foreach ($allRegisters as $r) {
            $typeLabel = DailyRegister::TYPES[$r->register_type] ?? $r->register_type;
            $detail    = array_values(array_filter([
                $r->department_name ? 'Dept: '.$r->department_name : null,
                $r->referred_from   ? 'From: '.$r->referred_from   : null,
                $r->days_given      ? $r->days_given.' days leave'  : null,
            ]));
            $timelineItems->push([
                'type'        => 'register',
                'date'        => $r->record_date->toDateString(),
                'time'        => null,
                'title'       => 'Register — '.$typeLabel,
                'detail'      => $detail ? implode(' · ', $detail) : null,
                'badge'       => $typeLabel,
                'badge_color' => match ($r->register_type) {
                    'family'              => 'bg-yellow-100 text-yellow-800',
                    'employee'            => 'bg-green-100 text-green-800',
                    'os'                  => 'bg-blue-100 text-blue-800',
                    'referral_accident'   => 'bg-red-100 text-red-600',
                    'referral_sick_leave' => 'bg-red-200 text-red-800',
                    default               => 'bg-gray-100 text-gray-600',
                },
                'meta'        => 'Recorded by: '.($r->creator?->full_name ?? '—'),
            ]);
        }

        // Referral timeline entries
        $allReferrals = Referral::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('date')
            ->get();

        foreach ($allReferrals as $ref) {
            $detail = array_values(array_filter([
                '→ '.$ref->destination,
                $ref->diagnosis ? 'Dx: '.mb_strimwidth($ref->diagnosis, 0, 80, '…') : null,
                $ref->doctor_nurse_name ? 'Dr/Nurse: '.$ref->doctor_nurse_name : null,
            ]));
            $timelineItems->push([
                'type'        => 'referral',
                'date'        => $ref->date->toDateString(),
                'time'        => null,
                'title'       => 'Referral — '.$ref->destination,
                'detail'      => implode(' · ', $detail),
                'badge'       => 'Referral',
                'badge_color' => 'bg-teal-100 text-teal-700',
                'meta'        => 'By: '.($ref->requestedBy?->full_name ?? '—'),
            ]);
        }

        // Sick leave timeline entries
        $allSickLeaves = SickLeave::query()
            ->with(['requestedBy:id,full_name'])
            ->where('patient_id', $patientId)
            ->orderByDesc('start_date')
            ->get();

        foreach ($allSickLeaves as $sl) {
            $detail = array_values(array_filter([
                $sl->days.' day'.($sl->days !== 1 ? 's' : ''),
                $sl->start_date->toDateString().' → '.$sl->end_date->toDateString(),
                $sl->diagnosis ? 'Dx: '.mb_strimwidth($sl->diagnosis, 0, 80, '…') : null,
                'Employee: '.$sl->employee_name,
            ]));
            $timelineItems->push([
                'type'        => 'sick_leave',
                'date'        => $sl->start_date->toDateString(),
                'time'        => null,
                'title'       => 'Sick Leave — '.$sl->employee_name,
                'detail'      => implode(' · ', $detail),
                'badge'       => 'Sick Leave',
                'badge_color' => 'bg-amber-100 text-amber-700',
                'meta'        => 'By: '.($sl->requestedBy?->full_name ?? '—'),
            ]);
        }

        foreach ($allOpdEncounters as $q) {
            // Build a brief clinical summary from the latest note (if any)
            $noteSummary = null;
            if ($q->clinicalNote) {
                $parts = array_values(array_filter([
                    $q->clinicalNote->chief_complaint
                        ? 'CC: '.mb_strimwidth($q->clinicalNote->chief_complaint, 0, 80, '…')
                        : null,
                    $q->clinicalNote->diagnosis
                        ? 'Dx: '.mb_strimwidth($q->clinicalNote->diagnosis, 0, 80, '…')
                        : null,
                    $q->clinicalNote->systolic_bp && $q->clinicalNote->diastolic_bp
                        ? 'BP '.$q->clinicalNote->systolic_bp.'/'.$q->clinicalNote->diastolic_bp
                        : null,
                    $q->clinicalNote->temperature
                        ? 'Temp '.$q->clinicalNote->temperature.'°C'
                        : null,
                ]));
                $noteSummary = $parts ? implode(' · ', $parts) : null;
            }

            $detail = array_values(array_filter([
                'Queue #'.$q->queue_number,
                $noteSummary,
            ]));

            $timelineItems->push([
                'type'        => 'opd',
                'date'        => $q->arrived_at->toDateString(),
                'time'        => $q->arrived_at->toTimeString(),
                'title'       => 'OPD Encounter — '.($q->room?->room_name ?? '—'),
                'detail'      => implode(' · ', $detail),
                'badge'       => $q->status,
                'badge_color' => match ($q->status) {
                    'Completed'       => 'bg-gray-100 text-gray-600',
                    'In Consultation' => 'bg-green-100 text-green-800',
                    'Transferred'     => 'bg-purple-100 text-purple-800',
                    'Cancelled'       => 'bg-red-100 text-red-600',
                    default           => 'bg-yellow-100 text-yellow-800',
                },
                'meta'        => null,
            ]);
        }

        // Sort timeline by date + time descending (most recent first)
        $timeline = $timelineItems
            ->sortByDesc(fn ($item) => $item['date'].($item['time'] ? ' '.$item['time'] : ' 00:00:00'))
            ->values();

        // Pull ALL completed lab requests for the timeline (no pagination limit)
        $allLabRequests = LabRequest::query()
            ->with([
                'tests.result',
                'labQueue:id,lab_request_id,status,completed_at',
            ])
            ->where('patient_id', $patientId)
            ->whereHas('labQueue', fn ($q) => $q->where('status', 'Completed'))
            ->orderByDesc('request_date')
            ->get();

        foreach ($allLabRequests as $lr) {
            $testCount    = $lr->tests->count();
            $resultCount  = $lr->tests->filter(fn ($t) => $t->result !== null)->count();
            $completedAt  = $lr->labQueue?->completed_at;

            $timelineItems->push([
                'type'        => 'lab',
                'date'        => $completedAt ? $completedAt->toDateString() : $lr->request_date->toDateString(),
                'time'        => $completedAt ? $completedAt->toTimeString() : null,
                'title'       => 'Lab Results — '.$testCount.' test'.($testCount !== 1 ? 's' : ''),
                'detail'      => $resultCount.' of '.$testCount.' results recorded'
                    .($lr->priority === 'Urgent' ? ' · 🔴 Urgent' : ''),
                'badge'       => 'Completed',
                'badge_color' => 'bg-teal-100 text-teal-700',
                'meta'        => null,
            ]);
        }

        // Re-sort after adding lab items
        $timeline = $timelineItems
            ->sortByDesc(fn ($item) => $item['date'].($item['time'] ? ' '.$item['time'] : ' 00:00:00'))
            ->values();

        return [
            'visits'         => $visits,
            'registers'      => $registers,
            'referrals'      => $referrals,
            'sick_leaves'    => $sickLeaves,
            'opd_encounters' => $opdEncounters,
            'lab_results'    => $labResults,
            'timeline'       => $timeline,
        ];
    }
}
