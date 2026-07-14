<?php

namespace App\Modules\Lab\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LabQueue;
use App\Modules\OPD\Services\LabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LabQueueController extends Controller
{
    public function __construct(
        private readonly LabService $labService,
    ) {}

    /**
     * Display the laboratory queue for today.
     * Supports filtering by status via ?status=Pending etc.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LabQueue::class);

        $status = $request->string('status')->toString() ?: null;
        if ($status && ! array_key_exists($status, LabQueue::STATUSES)) {
            $status = null;
        }

        return Inertia::render('Lab/Queue', [
            'queue'           => $this->labService->queue($status),
            'stats'           => $this->labService->queueStats(),
            'statuses'        => LabQueue::STATUSES,
            'active_statuses' => LabQueue::ACTIVE_STATUSES,
            'transitions'     => LabQueue::TRANSITIONS,
            'filter_status'   => $status,
        ]);
    }

    /**
     * Advance a lab queue entry to the next status.
     */
    public function updateStatus(Request $request, LabQueue $labQueue): RedirectResponse
    {
        $this->authorize('update', $labQueue);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(LabQueue::STATUSES))],
        ]);

        $this->labService->updateQueueStatus($labQueue, $data['status'], $request->user()->id);

        return back()->with('success', "Status updated to {$data['status']}.");
    }
}
