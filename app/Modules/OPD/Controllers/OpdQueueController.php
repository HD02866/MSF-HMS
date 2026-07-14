<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Modules\OPD\Services\OpdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpdQueueController extends Controller
{
    public function __construct(
        private readonly OpdService $opdService,
    ) {}

    /** Update the status of a queue entry */
    public function updateStatus(Request $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(OpdQueue::STATUSES))],
        ]);

        $this->opdService->updateStatus($opdQueue, $data['status'], $request->user()->id);

        return back()->with('success', "Status updated to {$data['status']}.");
    }

    /** Call the next waiting patient for a room (FIFO) */
    public function callNext(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
        ]);

        $entry = $this->opdService->callNext($data['room_id'], $request->user()->id);

        if (! $entry) {
            return back()->with('error', 'No waiting patients in the queue.');
        }

        return back()->with('success', "Called: {$entry->patient->full_name} — Queue #{$entry->queue_number}");
    }

    /** Mark all notifications as read for a room */
    public function markNotificationsRead(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $data = $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
        ]);

        $this->opdService->markAllRead($data['room_id']);

        return response()->json(['success' => true]);
    }
}
