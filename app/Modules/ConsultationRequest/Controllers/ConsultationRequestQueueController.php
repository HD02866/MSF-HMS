<?php

namespace App\Modules\ConsultationRequest\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ConsultationRequest;
use App\Models\ConsultationRequestNotification;
use App\Models\ConsultationRequestQueue;
use App\Modules\OPD\Services\ConsultationRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationRequestQueueController extends Controller
{
    public function __construct(
        private readonly ConsultationRequestService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ConsultationRequestQueue::class);

        $status      = $request->string('status')->toString() ?: null;
        $destination = $request->string('destination')->toString() ?: null;

        if ($status && ! array_key_exists($status, ConsultationRequestQueue::STATUSES)) {
            $status = null;
        }
        if ($destination && ! array_key_exists($destination, ConsultationRequest::DESTINATIONS)) {
            $destination = null;
        }

        return Inertia::render('ConsultationRequest/Queue', [
            'queue'           => $this->service->queue($status, $destination),
            'stats'           => $this->service->queueStats(),
            'statuses'        => ConsultationRequestQueue::STATUSES,
            'active_statuses' => ConsultationRequestQueue::ACTIVE_STATUSES,
            'transitions'     => ConsultationRequestQueue::TRANSITIONS,
            'destinations'    => ConsultationRequest::DESTINATIONS,
            'filter_status'   => $status,
            'filter_destination' => $destination,
            'unread_count'    => ConsultationRequestNotification::where('is_read', false)->count(),
        ]);
    }

    public function updateStatus(Request $request, ConsultationRequestQueue $consultationRequestQueue): RedirectResponse
    {
        $this->authorize('update', $consultationRequestQueue);

        $data = $request->validate([
            'status'         => ['required', 'string', Rule::in(array_keys(ConsultationRequestQueue::STATUSES))],
            'response_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->updateQueueStatus(
            $consultationRequestQueue,
            $data['status'],
            $request->user()->id,
            $data['response_notes'] ?? null,
        );

        return back()->with('success', "Status updated to {$data['status']}.");
    }
}
