<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyRegister;
use App\Models\OpdAttachment;
use App\Models\OpdQueue;
use App\Models\Room;
use App\Modules\OPD\Requests\StoreOpdClinicalNoteRequest;
use App\Modules\OPD\Services\OpdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OpdConsultationController extends Controller
{
    public function __construct(
        private readonly OpdService $opdService,
    ) {}

    /**
     * Show the consultation page for a queue entry.
     * Automatically transitions the status to "In Consultation" if not already.
     */
    public function show(Request $request, OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        // Auto-transition to "In Consultation" when the page is opened
        if ($opdQueue->status === 'Called') {
            $this->opdService->updateStatus($opdQueue, 'In Consultation', $request->user()->id);
            $opdQueue->refresh();
        }

        // Load all relationships needed for display
        $opdQueue->load([
            'patient.patientType:id,name',
            'patient.relationshipType:id,name',
            'room:id,room_name,room_code',
            'visit.assignedBy:id,full_name',
            'clinicalNote',
        ]);

        $patient = $opdQueue->patient;
        $visit   = $opdQueue->visit;

        return Inertia::render('OPD/Consultation', [
            'queue_entry' => [
                'id'           => $opdQueue->id,
                'queue_number' => $opdQueue->queue_number,
                'status'       => $opdQueue->status,
                'arrived_at'   => $opdQueue->arrived_at,
                'called_at'    => $opdQueue->called_at,
            ],
            'patient' => [
                'id'               => $patient->id,
                'full_name'        => $patient->full_name,
                'card_number'      => $patient->card_number,
                'employee_no'      => $patient->employee_no,
                'insurance_no'     => $patient->insurance_no,
                'gender'           => $patient->gender,
                'date_of_birth'    => $patient->date_of_birth?->toDateString(),
                'age'              => $patient->date_of_birth?->age,
                'phone'            => $patient->phone,
                'photo_url'        => $patient->photo_url,
                'patient_type'     => $patient->patientType?->name,
                'relationship_type'=> $patient->relationshipType?->name,
            ],
            'visit' => [
                'id'          => $visit->id,
                'visit_date'  => $visit->visit_date?->toDateString(),
                'visit_time'  => $visit->visit_time,
                'queue_number'=> $visit->queue_number,
                'remarks'     => $visit->remarks,
                'status'      => $visit->status,
                'room_name'   => $opdQueue->room?->room_name,
                'assigned_by' => $visit->assignedBy?->full_name,
            ],
            // Load any existing clinical note for this encounter
            'clinical_note' => $opdQueue->clinicalNote ? [
                'id'                     => $opdQueue->clinicalNote->id,
                'chief_complaint'        => $opdQueue->clinicalNote->chief_complaint,
                'history'                => $opdQueue->clinicalNote->history,
                'physical_examination'   => $opdQueue->clinicalNote->physical_examination,
                'diagnosis'              => $opdQueue->clinicalNote->diagnosis,
                'treatment_plan'         => $opdQueue->clinicalNote->treatment_plan,
                'follow_up_instructions' => $opdQueue->clinicalNote->follow_up_instructions,
                'temperature'            => $opdQueue->clinicalNote->temperature,
                'systolic_bp'            => $opdQueue->clinicalNote->systolic_bp,
                'diastolic_bp'           => $opdQueue->clinicalNote->diastolic_bp,
                'pulse_rate'             => $opdQueue->clinicalNote->pulse_rate,
                'respiratory_rate'       => $opdQueue->clinicalNote->respiratory_rate,
                'spo2'                   => $opdQueue->clinicalNote->spo2,
                'weight'                 => $opdQueue->clinicalNote->weight,
                'height'                 => $opdQueue->clinicalNote->height,
                'bmi'                    => $opdQueue->clinicalNote->bmi,
                'random_blood_sugar'     => $opdQueue->clinicalNote->random_blood_sugar,
            ] : null,
            'attachments' => $this->opdService->attachmentsForEncounter($opdQueue->id),
            'statuses'    => OpdQueue::STATUSES,
            'opd_rooms'   => Room::whereIn('room_code', \App\Models\OpdQueue::OPD_ROOM_CODES)
                ->where('is_active', true)
                ->orderBy('room_name')
                ->get(['id', 'room_name', 'room_code'])
                ->map(fn (Room $r) => ['id' => $r->id, 'name' => $r->room_name, 'code' => $r->room_code]),
        ]);
    }

    /**
     * Show patient history for a given OPD queue entry.
     * Uses the patient_id from the queue entry.
     */
    public function history(Request $request, OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load(['patient:id,full_name,card_number', 'room:id,room_name']);

        $history = $this->opdService->patientHistory(
            $opdQueue->patient_id,
            perPage: 10
        );

        return Inertia::render('OPD/PatientHistory', [
            'queue_entry' => [
                'id'           => $opdQueue->id,
                'queue_number' => $opdQueue->queue_number,
                'status'       => $opdQueue->status,
            ],
            'patient' => [
                'id'          => $opdQueue->patient->id,
                'full_name'   => $opdQueue->patient->full_name,
                'card_number' => $opdQueue->patient->card_number,
            ],
            'room_name'      => $opdQueue->room?->room_name,
            'visits'         => $history['visits'],
            'registers'      => $history['registers'],
            'referrals'      => $history['referrals'],
            'sick_leaves'    => $history['sick_leaves'],
            'opd_encounters' => $history['opd_encounters'],
            'lab_results'    => $history['lab_results'],
            'timeline'       => $history['timeline'],
            'register_types' => DailyRegister::TYPES,
        ]);
    }

    /**
     * Upload one or more attachments for this encounter.
     */
    public function storeAttachment(Request $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $request->validate([
            'attachments'   => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',  // 10 MB per file
                'mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt,xls,xlsx,csv',
            ],
        ]);

        foreach ($request->file('attachments') as $file) {
            $this->opdService->saveAttachment($opdQueue, $file, $request->user()->id);
        }

        $count = count($request->file('attachments'));
        return back()->with('success', "{$count} attachment".($count > 1 ? 's' : '').' uploaded successfully.');
    }

    /**
     * Download a single attachment by ID.
     * Only accessible to OPD Nurse and Admin — validates the attachment belongs to this encounter.
     */
    public function downloadAttachment(Request $request, OpdQueue $opdQueue, OpdAttachment $opdAttachment): BinaryFileResponse
    {
        $this->authorize('update', $opdQueue);

        // Ensure the attachment belongs to this queue entry
        abort_if($opdAttachment->opd_queue_id !== $opdQueue->id, 404);

        $path = public_path($opdAttachment->stored_path);
        abort_if(! file_exists($path), 404, 'File not found.');

        return response()->download($path, $opdAttachment->original_name);
    }

    /**
     * Save a clinical note for this encounter.
     * Creates a new record every time — previous notes are never overwritten.
     */
    public function storeNote(StoreOpdClinicalNoteRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->opdService->saveClinicalNote($opdQueue, $request->validated(), $request->user()->id);

        return back()->with('success', 'Clinical note saved successfully.');
    }

    public function complete(Request $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $data = $request->validate([
            'status'                 => ['required', 'string', Rule::in(['Completed', 'Transferred', 'Cancelled'])],
            'destination_room_id'    => ['nullable', 'integer', 'exists:rooms,id'],
            // Optional clinical note fields — saved atomically with completion
            'chief_complaint'        => ['nullable', 'string', 'max:2000'],
            'history'                => ['nullable', 'string', 'max:5000'],
            'physical_examination'   => ['nullable', 'string', 'max:5000'],
            'diagnosis'              => ['nullable', 'string', 'max:2000'],
            'treatment_plan'         => ['nullable', 'string', 'max:5000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:2000'],
            // Vital signs
            'temperature'            => ['nullable', 'numeric', 'between:30,45'],
            'systolic_bp'            => ['nullable', 'integer', 'between:60,300'],
            'diastolic_bp'           => ['nullable', 'integer', 'between:20,200'],
            'pulse_rate'             => ['nullable', 'integer', 'between:30,250'],
            'respiratory_rate'       => ['nullable', 'integer', 'between:5,60'],
            'spo2'                   => ['nullable', 'numeric', 'between:50,100'],
            'weight'                 => ['nullable', 'numeric', 'between:0.5,500'],
            'height'                 => ['nullable', 'numeric', 'between:20,250'],
            'bmi'                    => ['nullable', 'numeric', 'between:5,100'],
            'random_blood_sugar'     => ['nullable', 'numeric', 'between:1,100'],
        ]);

        $noteData = [
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
        ];

        if ($data['status'] === 'Completed') {
            // Atomic: save note + mark visit + mark queue Completed
            $this->opdService->completeConsultation($opdQueue, $noteData, $request->user()->id);
        } else {
            // Transferred / Cancelled — update status (+ create destination queue if transferred)
            $destinationRoomId = $data['status'] === 'Transferred'
                ? ($data['destination_room_id'] ?? null)
                : null;

            $this->opdService->updateStatus(
                $opdQueue,
                $data['status'],
                $request->user()->id,
                $destinationRoomId,
            );
        }

        $roomId = $opdQueue->room_id;

        return redirect()
            ->route('opd.dashboard', ['room_id' => $roomId])
            ->with('success', "Consultation {$data['status']}.");
    }
}
