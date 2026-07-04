<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\AuditLogService;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomManagementController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReferenceDataService $ref,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Rooms/Index', [
            'rooms' => $this->ref->allRooms(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_name' => ['required', 'string', 'max:100'],
            'room_code' => ['required', 'string', 'max:50', 'unique:rooms,room_code'],
        ]);

        $room = Room::create([...$data, 'is_active' => true]);
        $this->ref->bustRooms();
        $this->auditLogService->log('Room Created', $room, null, $room->toArray());

        return back()->with('success', 'Room created.');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'room_name' => ['required', 'string', 'max:100'],
            'room_code' => ['required', 'string', 'max:50', 'unique:rooms,room_code,'.$room->id],
            'is_active' => ['boolean'],
        ]);

        $old = $room->toArray();
        $room->update($data);
        $this->ref->bustRooms();
        $this->auditLogService->log('Room Updated', $room, $old, $room->fresh()->toArray());

        return back()->with('success', 'Room updated.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->visits()->exists()) {
            return back()->with('error', 'Cannot delete this room because it has visit records linked to it.');
        }

        $old = $room->toArray();
        $room->delete();
        $this->ref->bustRooms();
        $this->auditLogService->log('Room Deleted', $room, $old, null);

        return back()->with('success', 'Room deleted.');
    }
}
