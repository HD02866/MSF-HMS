<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Models\Room;
use App\Modules\OPD\Services\OpdService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpdDashboardController extends Controller
{
    public function __construct(
        private readonly OpdService $opdService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', OpdQueue::class);

        // Determine which room the nurse is assigned to.
        // Admin can pass ?room_id= to view any room.
        $user   = $request->user();
        $roomId = $request->integer('room_id', 0);

        if (! $roomId) {
            // Default: first active OPD room
            $room   = Room::whereIn('room_code', OpdQueue::OPD_ROOM_CODES)
                ->where('is_active', true)
                ->orderBy('room_name')
                ->first();
            $roomId = $room?->id ?? 0;
        }

        $opdRooms = Room::whereIn('room_code', OpdQueue::OPD_ROOM_CODES)
            ->where('is_active', true)
            ->orderBy('room_name')
            ->get(['id', 'room_name', 'room_code']);

        return Inertia::render('OPD/Dashboard', [
            'stats'         => $roomId ? $this->opdService->dashboardStats($roomId) : null,
            'queue'         => $roomId ? $this->opdService->queueForRoom($roomId) : collect(),
            'notifications' => $roomId ? $this->opdService->notifications($roomId) : collect(),
            'opd_rooms'     => $opdRooms,
            'current_room'  => $roomId ? $opdRooms->firstWhere('id', $roomId) : null,
            'statuses'      => OpdQueue::STATUSES,
        ]);
    }
}
