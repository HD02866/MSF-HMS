<?php

namespace App\Modules\Lab\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabQueue;
use App\Modules\Lab\Requests\StoreLabResultRequest;
use App\Modules\OPD\Services\LabService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LabResultController extends Controller
{
    public function __construct(
        private readonly LabService $labService,
    ) {}

    /**
     * Show the result entry form for a completed/processing queue entry.
     * Loads all tests for the request so the technician can enter one
     * result per test on a single screen.
     */
    public function create(LabQueue $labQueue): Response
    {
        $this->authorize('update', $labQueue);

        $labQueue->load([
            'patient:id,full_name,card_number,gender,date_of_birth',
            'labRequest.tests.result.performedBy:id,full_name',
            'labRequest.requestedBy:id,full_name',
        ]);

        $labRequest = $labQueue->labRequest;

        return Inertia::render('Lab/ResultEntry', [
            'lab_queue' => [
                'id'     => $labQueue->id,
                'status' => $labQueue->status,
            ],
            'patient' => [
                'id'          => $labQueue->patient->id,
                'full_name'   => $labQueue->patient->full_name,
                'card_number' => $labQueue->patient->card_number,
                'gender'      => $labQueue->patient->gender,
                'age'         => $labQueue->patient->date_of_birth?->age,
            ],
            'lab_request' => [
                'id'             => $labRequest->id,
                'priority'       => $labRequest->priority,
                'request_date'   => $labRequest->request_date->toDateString(),
                'clinical_notes' => $labRequest->clinical_notes,
                'requested_by'   => $labRequest->requestedBy?->full_name,
            ],
            'tests' => $labRequest->tests->map(fn ($t) => [
                'id'        => $t->id,
                'test_name' => $t->test_name,
                'result'    => $t->result ? [
                    'id'           => $t->result->id,
                    'result'       => $t->result->result,
                    'remarks'      => $t->result->remarks,
                    'result_date'  => $t->result->result_date->toDateString(),
                    'performed_by' => $t->result->performedBy?->full_name,
                ] : null,
            ])->all(),
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * Persist results for all filled-in tests.
     * Redirects back to the result entry page so the technician can
     * review or add missing entries, or navigate back to the queue.
     */
    public function store(StoreLabResultRequest $request, LabQueue $labQueue): RedirectResponse
    {
        $this->authorize('update', $labQueue);

        $this->labService->saveResults($labQueue, $request->validated()['results'], $request->user()->id);

        return redirect()
            ->route('lab.results.create', $labQueue)
            ->with('success', 'Results saved successfully.');
    }
}
