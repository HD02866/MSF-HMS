<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Models\Referral;
use App\Modules\OPD\Requests\StoreReferralRequest;
use App\Modules\OPD\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $service,
    ) {}

    public function index(): Response
    {
        $referrals = \App\Models\Referral::query()
            ->with(['patient:id,full_name,card_number,gender,date_of_birth', 'requestedBy:id,full_name', 'opdQueue.room:id,room_name'])
            ->orderByDesc('date')
            ->paginate(25);

        return Inertia::render('Recorder/ReferralSickLeaveList', [
            'referrals'    => $referrals,
            'sick_leaves'  => \App\Models\SickLeave::query()
                ->with(['patient:id,full_name,card_number,gender,date_of_birth', 'requestedBy:id,full_name', 'opdQueue.room:id,room_name'])
                ->orderByDesc('start_date')
                ->paginate(25, ['*'], 'sick_leaves_page'),
            'active_tab'   => 'referrals',
        ]);
    }

    public function create(OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load('patient:id,full_name,card_number,gender,age');

        return Inertia::render('OPD/Referral', [
            'queue_entry'   => $opdQueue->only('id', 'queue_number', 'status'),
            'patient'       => $opdQueue->patient,
            'room_name'     => $opdQueue->room->room_name ?? '—',
            'destinations'  => Referral::DESTINATIONS,
            'today'         => now()->toDateString(),
            'requester_name'=> $this->getFullName(),
        ]);
    }

    public function store(StoreReferralRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->service->createReferral($opdQueue, $request->validated(), $request->user()->id);

        return redirect()->route('opd.consultation.show', $opdQueue)
            ->with('success', 'Referral created successfully. The Recorder has been notified.');
    }

    private function getFullName(): string
    {
        return auth()->user()?->full_name ?? '';
    }
}
