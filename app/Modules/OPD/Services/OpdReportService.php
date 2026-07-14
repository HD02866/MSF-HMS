<?php

namespace App\Modules\OPD\Services;

use App\Models\OpdQueue;
use App\Models\OpdClinicalNote;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabResult;
use App\Models\Medicine;
use App\Models\PharmacyRequest;
use App\Models\PharmacyRequestItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OpdReportService
{
    private const COMPLETED_STATUSES = ['Completed', 'Transferred'];

    public function diseaseStats(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $diagnoses = OpdClinicalNote::query()
            ->select('diagnosis', DB::raw('count(*) as total'))
            ->whereNotNull('diagnosis')
            ->where('diagnosis', '!=', '')
            ->whereHas('opdQueue', function ($q) use ($start, $end) {
                $q->whereIn('status', self::COMPLETED_STATUSES)
                  ->whereBetween('arrived_at', [$start, $end]);
            })
            ->groupBy('diagnosis')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $chiefComplaints = OpdClinicalNote::query()
            ->select('chief_complaint', DB::raw('count(*) as total'))
            ->whereNotNull('chief_complaint')
            ->where('chief_complaint', '!=', '')
            ->whereHas('opdQueue', function ($q) use ($start, $end) {
                $q->whereIn('status', self::COMPLETED_STATUSES)
                  ->whereBetween('arrived_at', [$start, $end]);
            })
            ->groupBy('chief_complaint')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $totalEncounters = OpdQueue::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->count();

        return [
            'period'          => $period,
            'start_date'      => $start->toDateString(),
            'end_date'        => $end->toDateString(),
            'total_encounters'=> $totalEncounters,
            'by_diagnosis'    => $diagnoses->map(fn ($r) => ['label' => $r->diagnosis, 'count' => $r->total])->values(),
            'by_complaint'    => $chiefComplaints->map(fn ($r) => ['label' => $r->chief_complaint, 'count' => $r->total])->values(),
        ];
    }

    public function doctorStats(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $byDoctor = OpdClinicalNote::query()
            ->select('created_by', DB::raw('count(*) as total'))
            ->whereHas('opdQueue', function ($q) use ($start, $end) {
                $q->whereIn('status', self::COMPLETED_STATUSES)
                  ->whereBetween('arrived_at', [$start, $end]);
            })
            ->groupBy('created_by')
            ->orderByDesc('total')
            ->get();

        $userIds = $byDoctor->pluck('created_by')->filter()->values();
        $users = $userIds->isNotEmpty()
            ? User::whereIn('id', $userIds)->get(['id', 'full_name'])->keyBy('id')
            : collect();

        $totalEncounters = OpdQueue::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->count();

        return [
            'period'          => $period,
            'start_date'      => $start->toDateString(),
            'end_date'        => $end->toDateString(),
            'total_encounters'=> $totalEncounters,
            'by_doctor'       => $byDoctor->map(fn ($r) => [
                'doctor_name' => $users->get($r->created_by)?->full_name ?? 'Unknown',
                'count'       => $r->total,
            ])->values(),
        ];
    }

    public function roomStats(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $byRoom = OpdQueue::query()
            ->select('room_id', DB::raw('count(*) as total'), DB::raw("sum(case when status = 'Completed' then 1 else 0 end) as completed_count"), DB::raw("sum(case when status = 'Transferred' then 1 else 0 end) as transferred_count"))
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->groupBy('room_id')
            ->orderByDesc('total')
            ->get();

        $roomIds = $byRoom->pluck('room_id')->values();
        $rooms = $roomIds->isNotEmpty()
            ? \App\Models\Room::whereIn('id', $roomIds)->get(['id', 'room_name', 'room_code'])->keyBy('id')
            : collect();

        return [
            'period'          => $period,
            'start_date'      => $start->toDateString(),
            'end_date'        => $end->toDateString(),
            'total_encounters'=> $byRoom->sum('total'),
            'by_room'         => $byRoom->map(fn ($r) => [
                'room_name'   => $rooms->get($r->room_id)?->room_name ?? 'Unknown',
                'room_code'   => $rooms->get($r->room_id)?->room_code ?? '—',
                'total'       => $r->total,
                'completed'   => (int) $r->completed_count,
                'transferred' => (int) $r->transferred_count,
            ])->values(),
        ];
    }

    public function labStats(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $requests = LabRequest::query()
            ->whereBetween('request_date', [$start, $end])
            ->get();

        $byPanel = collect();
        foreach ($requests as $req) {
            foreach ($req->tests as $test) {
                $panel = $this->resolvePanel($test->test_name);
                $byPanel[$panel] = ($byPanel[$panel] ?? 0) + 1;
            }
        }
        $byPanel = $byPanel->map(fn ($count, $panel) => ['label' => $panel, 'count' => $count])
            ->sortByDesc('count')
            ->values();

        $byTest = $requests->flatMap(fn ($r) => $r->tests->pluck('test_name'))
            ->groupBy(fn ($name) => $name)
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->take(20)
            ->map(fn ($count, $name) => ['label' => $name, 'count' => $count])
            ->values();

        $completedCount = $requests->filter(fn ($r) => $r->labQueue?->status === 'Completed')->count();
        $urgentCount = $requests->where('priority', 'Urgent')->count();

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total_requests'    => $requests->count(),
            'completed'         => $completedCount,
            'pending'           => $requests->count() - $completedCount,
            'urgent'            => $urgentCount,
            'by_panel'          => $byPanel,
            'by_test'           => $byTest,
        ];
    }

    public function medicineStats(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $prescriptions = PharmacyRequest::query()
            ->whereBetween('request_date', [$start, $end])
            ->get();

        $byMedicine = $prescriptions->flatMap(fn ($r) => $r->items->pluck('medicine_name'))
            ->groupBy(fn ($name) => $name)
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->take(20)
            ->map(fn ($count, $name) => ['label' => $name, 'count' => $count])
            ->values();

        $byCategory = $prescriptions->flatMap(fn ($r) => $r->items->filter(fn ($i) => $i->medicine)->map(fn ($i) => $i->medicine->category ?? 'Unknown'))
            ->groupBy(fn ($cat) => $cat)
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->map(fn ($count, $cat) => ['label' => $cat, 'count' => $count])
            ->values();

        $totalItems = $prescriptions->sum(fn ($r) => $r->items->count());
        $externalCount = $prescriptions->where('is_external', true)->count();

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total_prescriptions'=> $prescriptions->count(),
            'total_items'       => $totalItems,
            'external'          => $externalCount,
            'internal'          => $prescriptions->count() - $externalCount,
            'by_medicine'       => $byMedicine,
            'by_category'       => $byCategory,
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

    private function resolvePanel(string $testName): string
    {
        foreach (LabRequest::TEST_CATALOG as $panel => $tests) {
            if (in_array($testName, $tests, true)) {
                return $panel;
            }
        }
        return 'Other';
    }
}
