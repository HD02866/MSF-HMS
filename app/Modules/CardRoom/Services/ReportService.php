<?php

namespace App\Modules\CardRoom\Services;

use App\Models\Visit;
use App\Services\ReferenceDataService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private readonly ReferenceDataService $ref,
    ) {}

    public function dashboardStats(): array
    {
        $today = today();

        // Single query reused for counts, rooms, and recent list
        $visitsToday = Visit::with(['patient.patientType', 'room', 'assignedBy'])
            ->whereDate('visit_date', $today)
            ->orderByDesc('visit_time')
            ->get();

        $byType = $this->countByPatientType($visitsToday);
        $byRoom = $this->countByRoom($visitsToday);

        $recent = $visitsToday
            ->take(10)
            ->map(fn (Visit $v) => [
                'time'        => $v->visit_time,
                'patient'     => $v->patient->full_name,
                'card_number' => $v->patient->card_number,
                'room'        => $v->room->room_name,
                'assigned_by' => $v->assignedBy->full_name,
            ]);

        return [
            'total_visits'       => $visitsToday->count(),
            'by_patient_type'    => $byType,
            'by_room'            => $byRoom,
            'recent_assignments' => $recent,
        ];
    }

    public function report(string $period, ?Carbon $date = null): array
    {
        $date = $date ?? now();
        [$start, $end] = $this->periodRange($period, $date);

        $visits = Visit::with(['patient.patientType', 'room'])
            ->whereBetween('visit_date', [$start, $end])
            ->get();

        return [
            'period'         => $period,
            'start_date'     => $start->toDateString(),
            'end_date'       => $end->toDateString(),
            'total_visits'   => $visits->count(),
            'by_patient_type' => $this->countByPatientType($visits),
            'by_room'        => $this->countByRoom($visits),
        ];
    }

    private function periodRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'weekly'  => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default   => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }

    private function countByPatientType(Collection $visits): array
    {
        // In-memory grouping — reference list served from cache
        $grouped = $visits->groupBy(fn (Visit $v) => $v->patient?->patientType?->name ?? 'Unknown');
        $types   = $this->ref->activePatientTypeNames();

        $counts = [];
        foreach ($types as $type) {
            $counts[$type] = $grouped->get($type, collect())->count();
        }

        return $counts;
    }

    private function countByRoom(Collection $visits): array
    {
        // In-memory grouping — reference list served from cache
        $grouped = $visits->groupBy(fn (Visit $v) => $v->room?->room_name ?? 'Unknown');
        $rooms   = $this->ref->activeRoomNames();

        $counts = [];
        foreach ($rooms as $room) {
            $counts[$room] = $grouped->get($room, collect())->count();
        }

        return $counts;
    }
}
