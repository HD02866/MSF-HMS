<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Modules\OPD\Requests\StoreSickLeaveRequest;
use App\Modules\OPD\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SickLeaveController extends Controller
{
    public function __construct(
        private readonly ReferralService $service,
    ) {}

    public function create(OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load('patient:id,full_name,card_number,gender,age');

        return Inertia::render('OPD/SickLeave', [
            'queue_entry'    => $opdQueue->only('id', 'queue_number', 'status'),
            'patient'        => $opdQueue->patient,
            'room_name'      => $opdQueue->room->room_name ?? '—',
            'today'          => now()->toDateString(),
            'requester_name' => $this->getFullName(),
        ]);
    }

    public function store(StoreSickLeaveRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->service->createSickLeave($opdQueue, $request->validated(), $request->user()->id);

        return redirect()->route('opd.consultation.show', $opdQueue)
            ->with('success', 'Sick leave created successfully. The Recorder has been notified.');
    }

    private function getFullName(): string
    {
        return auth()->user()?->full_name ?? '';
    }
}
