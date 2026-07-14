<?php

namespace App\Modules\OPD\Services;

use App\Models\OpdQueue;
use App\Models\OpdClinicalNote;
use App\Models\Patient;
use App\Models\PatientType;
use App\Models\Room;
use App\Models\LabRequest;
use App\Models\PharmacyRequest;
use App\Models\PharmacyRequestItem;
use App\Models\Referral;
use App\Models\SickLeave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HmisReportService
{
    private const COMPLETED_STATUSES = ['Completed', 'Transferred'];

    public const AGE_GROUPS = [
        '0–14'  => [0, 14],
        '15–24' => [15, 24],
        '25–34' => [25, 34],
        '35–44' => [35, 44],
        '45–54' => [45, 54],
        '55–64' => [55, 64],
        '65+'   => [65, 200],
    ];

    // ── Overview ──────────────────────────────────────────────────────

    public function overview(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $encounters = OpdQueue::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end]);

        $totalEncounters = (clone $encounters)->count();
        $completedCount  = (clone $encounters)->where('status', 'Completed')->count();

        $uniquePatients = (clone $encounters)->distinct('patient_id')->count('patient_id');

        $labRequests = LabRequest::whereBetween('request_date', [$start, $end])->count();
        $prescriptions = PharmacyRequest::whereBetween('request_date', [$start, $end])->count();
        $referrals = Referral::whereBetween('date', [$start, $end])->count();
        $sickLeaves = SickLeave::whereBetween('start_date', [$start, $end])->count();

        $avgWaitMinutes = 0;
        if ($totalEncounters > 0) {
            $completedEntries = (clone $encounters)
                ->whereNotNull('called_at')
                ->get(['arrived_at', 'called_at']);
            $totalWait = $completedEntries->sum(fn ($e) => $e->arrived_at->diffInMinutes($e->called_at));
            $avgWaitMinutes = (int) round($totalWait / max($totalEncounters, 1));
        }

        return [
            'period'              => $period,
            'start_date'          => $start->toDateString(),
            'end_date'            => $end->toDateString(),
            'total_encounters'    => $totalEncounters,
            'unique_patients'     => $uniquePatients,
            'lab_requests'        => $labRequests,
            'prescriptions'       => $prescriptions,
            'referrals'           => $referrals,
            'sick_leaves'         => $sickLeaves,
            'completion_rate'     => $totalEncounters > 0 ? (int) round(($completedCount / $totalEncounters) * 100) : 0,
            'avg_wait_minutes'    => $avgWaitMinutes,
        ];
    }

    // ── Patient Demographics ─────────────────────────────────────────

    public function patientDemographics(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $patientIds = OpdQueue::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->pluck('patient_id')
            ->unique();

        $patients = $patientIds->isNotEmpty()
            ? Patient::whereIn('id', $patientIds)->get()
            : collect();

        // By patient type
        $typeGroups = $patients->groupBy(fn ($p) => $p->patientType?->name ?? 'Unknown');
        $byType = $typeGroups->map(fn ($group) => $group->count())
            ->sortDesc()
            ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
            ->values();

        // By gender
        $genderGroups = $patients->groupBy(fn ($p) => $p->gender ?? 'Unknown');
        $byGender = $genderGroups->map(fn ($group) => $group->count())
            ->sortDesc()
            ->map(fn ($count, $label) => ['label' => $label, 'count' => $count])
            ->values();

        // By age group
        $byAge = collect();
        foreach (self::AGE_GROUPS as $label => [$min, $max]) {
            $count = $patients->filter(fn ($p) => $p->date_of_birth !== null)
                ->filter(fn ($p) => $p->date_of_birth->age >= $min && $p->date_of_birth->age <= $max)
                ->count();
            if ($count > 0) {
                $byAge->push(['label' => $label, 'count' => $count]);
            }
        }

        return [
            'period'       => $period,
            'start_date'   => $start->toDateString(),
            'end_date'     => $end->toDateString(),
            'total_patients' => $patients->count(),
            'by_type'      => $byType->values(),
            'by_gender'    => $byGender->values(),
            'by_age'       => $byAge->values(),
        ];
    }

    // ── Disease Statistics ────────────────────────────────────────────

    public function diseaseStatistics(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $totalEncounters = OpdQueue::query()
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->count();

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

        $complaints = OpdClinicalNote::query()
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

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total_encounters'  => $totalEncounters,
            'by_diagnosis'      => $diagnoses->map(fn ($r) => ['label' => $r->diagnosis, 'count' => $r->total])->values(),
            'by_complaint'      => $complaints->map(fn ($r) => ['label' => $r->chief_complaint, 'count' => $r->total])->values(),
        ];
    }

    // ── Laboratory ────────────────────────────────────────────────────

    public function laboratoryReport(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $requests = LabRequest::query()
            ->with('tests')
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

    // ── Pharmacy ──────────────────────────────────────────────────────

    public function pharmacyReport(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $prescriptions = PharmacyRequest::query()
            ->with('items.medicine')
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
            'period'             => $period,
            'start_date'         => $start->toDateString(),
            'end_date'           => $end->toDateString(),
            'total_prescriptions'=> $prescriptions->count(),
            'total_items'        => $totalItems,
            'external'           => $externalCount,
            'internal'           => $prescriptions->count() - $externalCount,
            'by_medicine'        => $byMedicine,
            'by_category'        => $byCategory,
        ];
    }

    // ── Referrals ─────────────────────────────────────────────────────

    public function referralReport(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $referrals = Referral::query()
            ->with('requestedBy:id,full_name')
            ->whereBetween('date', [$start, $end])
            ->get();

        $byDestination = $referrals->groupBy('destination')
            ->map(fn ($group, $dest) => ['label' => $dest, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        $byDoctor = $referrals->groupBy(fn ($r) => $r->doctor_nurse_name ?? 'Unknown')
            ->map(fn ($group, $name) => ['label' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total_referrals'   => $referrals->count(),
            'by_destination'    => $byDestination,
            'by_doctor'         => $byDoctor,
        ];
    }

    // ── Sick Leave ────────────────────────────────────────────────────

    public function sickLeaveReport(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $sickLeaves = SickLeave::query()
            ->with('requestedBy:id,full_name')
            ->whereBetween('start_date', [$start, $end])
            ->get();

        $byEmployee = $sickLeaves->groupBy('employee_name')
            ->map(fn ($group, $name) => ['label' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(20)
            ->values();

        $byDiagnosis = $sickLeaves->groupBy('diagnosis')
            ->map(fn ($group, $dx) => ['label' => $dx, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(20)
            ->values();

        $totalDays = $sickLeaves->sum('days');
        $avgDays = $sickLeaves->isNotEmpty() ? round($totalDays / $sickLeaves->count(), 1) : 0;

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total_sick_leaves' => $sickLeaves->count(),
            'total_days'        => $totalDays,
            'avg_days'          => $avgDays,
            'by_employee'       => $byEmployee,
            'by_diagnosis'      => $byDiagnosis,
        ];
    }

    // ── Completed Visits ──────────────────────────────────────────────

    public function completedVisits(string $period, Carbon $date): array
    {
        [$start, $end] = $this->periodRange($period, $date);

        $entries = OpdQueue::query()
            ->with(['room:id,room_name,room_code', 'clinicalNote.creator:id,full_name'])
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->whereBetween('arrived_at', [$start, $end])
            ->get();

        $total = $entries->count();
        $completed = $entries->where('status', 'Completed')->count();
        $transferred = $entries->where('status', 'Transferred')->count();

        // By room
        $byRoom = $entries->groupBy(fn ($e) => $e->room?->room_name ?? 'Unknown')
            ->map(fn ($group, $name) => [
                'label'      => $name,
                'count'      => $group->count(),
                'completed'  => $group->where('status', 'Completed')->count(),
                'transferred'=> $group->where('status', 'Transferred')->count(),
            ])
            ->sortByDesc('count')
            ->values();

        // By doctor/nurse
        $byDoctor = $entries->filter(fn ($e) => $e->clinicalNote)
            ->groupBy(fn ($e) => $e->clinicalNote->creator?->full_name ?? 'Unknown')
            ->map(fn ($group, $name) => ['label' => $name, 'count' => $group->count()])
            ->sortByDesc('count')
            ->values();

        // Duration stats
        $withDuration = $entries->filter(fn ($e) => $e->called_at && $e->completed_at);
        $durations = $withDuration->map(fn ($e) => $e->called_at->diffInMinutes($e->completed_at));
        $avgDuration = $durations->isNotEmpty() ? (int) round($durations->avg()) : 0;
        $maxDuration = $durations->isNotEmpty() ? (int) $durations->max() : 0;
        $minDuration = $durations->isNotEmpty() ? (int) $durations->min() : 0;

        return [
            'period'            => $period,
            'start_date'        => $start->toDateString(),
            'end_date'          => $end->toDateString(),
            'total'             => $total,
            'completed'         => $completed,
            'transferred'       => $transferred,
            'avg_duration_mins' => $avgDuration,
            'max_duration_mins' => $maxDuration,
            'min_duration_mins' => $minDuration,
            'by_room'           => $byRoom,
            'by_doctor'         => $byDoctor,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    public function periodRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'weekly'  => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            'yearly'  => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
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
