<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\OpdQueue;
use App\Modules\OPD\Requests\StoreLabRequestRequest;
use App\Modules\OPD\Services\LabService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LabRequestController extends Controller
{
    public function __construct(
        private readonly LabService $labService,
    ) {}

    /**
     * Show the lab request form for a given OPD queue entry.
     * The queue entry must be active (Waiting / Called / In Consultation).
     */
    public function create(OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load([
            'patient:id,full_name,card_number,gender,date_of_birth',
            'room:id,room_name',
        ]);

        return Inertia::render('OPD/LabRequest', [
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
            'test_catalog'    => LabRequest::TEST_CATALOG,
            'priorities'      => LabRequest::PRIORITIES,
            'today'           => now()->toDateString(),
            'requester_name'  => auth()->user()?->full_name ?? '',
            // Previous requests for this encounter (read-only list)
            'prior_requests'  => $this->labService->requestsForEncounter($opdQueue->id),
        ]);
    }

    /**
     * Persist the lab request and its selected tests.
     * Redirects back to the lab request page so the nurse can submit
     * additional panels if needed, or navigate back to consultation.
     */
    public function store(StoreLabRequestRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->labService->createRequest($opdQueue, $request->validated(), $request->user()->id);

        return redirect()
            ->route('opd.lab.create', $opdQueue)
            ->with('success', 'Lab request submitted successfully.');
    }
}
