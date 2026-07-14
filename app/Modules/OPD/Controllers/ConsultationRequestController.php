<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConsultationRequest;
use App\Models\OpdQueue;
use App\Modules\OPD\Requests\StoreConsultationRequestRequest;
use App\Modules\OPD\Services\ConsultationRequestService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationRequestController extends Controller
{
    public function __construct(
        private readonly ConsultationRequestService $service,
    ) {}

    /**
     * Show the consultation request form for a given OPD queue entry.
     */
    public function create(OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load([
            'patient:id,full_name,card_number,gender,date_of_birth',
            'room:id,room_name',
        ]);

        return Inertia::render('OPD/ConsultationRequest', [
            'queue_entry' => [
                'id'           => $opdQueue->id,
                'queue_number' => $opdQueue->queue_number,
                'status'       => $opdQueue->status,
            ],
            'patient' => [
                'id'          => $opdQueue->patient->id,
                'full_name'   => $opdQueue->patient->full_name,
                'card_number' => $opdQueue->patient->card_number,
                'gender'      => $opdQueue->patient->gender,
                'age'         => $opdQueue->patient->date_of_birth?->age,
            ],
            'room_name'       => $opdQueue->room?->room_name,
            'destinations'    => ConsultationRequest::DESTINATIONS,
            'priorities'      => ConsultationRequest::PRIORITIES,
            'today'           => now()->toDateString(),
            'requester_name'  => auth()->user()?->full_name ?? '',
            'prior_requests'  => $this->service->requestsForEncounter($opdQueue->id),
        ]);
    }

    /**
     * Persist the consultation request and redirect back.
     */
    public function store(StoreConsultationRequestRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->service->createRequest($opdQueue, $request->validated(), $request->user()->id);

        return redirect()
            ->route('opd.consultation-request.create', $opdQueue)
            ->with('success', 'Consultation request submitted successfully.');
    }
}
