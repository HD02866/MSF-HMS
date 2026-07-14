<?php

namespace App\Modules\CardRoom\Services;

use App\Models\DailyRegister;
use App\Modules\CardRoom\Exports\DailyRegisterExport;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyRegisterService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function list(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return DailyRegister::query()
            ->with([
                'patient:id,card_number,full_name,gender,date_of_birth',
                'creator:id,full_name',
            ])
            ->when($filters['record_date'] ?? null, fn ($q, $v) => $q->whereDate('record_date', $v))
            ->when($filters['register_type'] ?? null, fn ($q, $v) => $q->where('register_type', $v))
            ->when($filters['search_name'] ?? null, function ($q, $v) {
                $q->whereHas('patient', fn ($pq) => $pq->where('full_name', 'ilike', "%{$v}%"));
            })
            ->when($filters['search_id'] ?? null, function ($q, $v) {
                $q->whereHas('patient', fn ($pq) => $pq->where('card_number', 'ilike', "%{$v}%"));
            })
            ->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function listForExport(array $filters): Collection
    {
        return DailyRegister::query()
            ->with([
                'patient:id,card_number,full_name,gender,date_of_birth',
                'creator:id,full_name',
            ])
            ->when($filters['record_date'] ?? null, fn ($q, $v) => $q->whereDate('record_date', $v))
            ->when($filters['register_type'] ?? null, fn ($q, $v) => $q->where('register_type', $v))
            ->when($filters['search_name'] ?? null, function ($q, $v) {
                $q->whereHas('patient', fn ($pq) => $pq->where('full_name', 'ilike', "%{$v}%"));
            })
            ->when($filters['search_id'] ?? null, function ($q, $v) {
                $q->whereHas('patient', fn ($pq) => $pq->where('card_number', 'ilike', "%{$v}%"));
            })
            ->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data, int $userId): DailyRegister
    {
        return DB::transaction(function () use ($data, $userId) {
            $register = DailyRegister::create([
                'patient_id'      => $data['patient_id'],
                'register_type'   => $data['register_type'],
                'record_date'     => $data['record_date'],
                'department_name' => $data['department_name'] ?? null,
                'referred_from'   => $data['referred_from'] ?? null,
                'days_given'      => $data['days_given'] ?? null,
                'created_by'      => $userId,
            ]);

            $this->auditLogService->log('Daily Register Created', $register, null, $register->load('patient')->toArray(), $userId);

            return $register->load(['patient', 'creator']);
        });
    }

    public function update(DailyRegister $register, array $data, int $userId): DailyRegister
    {
        return DB::transaction(function () use ($register, $data, $userId) {
            $old = $register->toArray();

            $register->update([
                'register_type'   => $data['register_type'],
                'record_date'     => $data['record_date'],
                'department_name' => $data['department_name'] ?? null,
                'referred_from'   => $data['referred_from'] ?? null,
                'days_given'      => $data['days_given'] ?? null,
            ]);

            $this->auditLogService->log('Daily Register Updated', $register, $old, $register->fresh()->toArray(), $userId);

            return $register->fresh(['patient', 'creator']);
        });
    }

    public function delete(DailyRegister $register, int $userId): void
    {
        DB::transaction(function () use ($register, $userId) {
            $old = $register->toArray();
            $register->delete();
            $this->auditLogService->log('Daily Register Deleted', $register, $old, null, $userId);
        });
    }

    /**
     * Today's register counts — used by the Recorder Dashboard.
     * Single aggregated query instead of one COUNT per type.
     */
    public function todayStats(): array
    {
        $today = today()->toDateString();

        $rows = DailyRegister::query()
            ->selectRaw('register_type, COUNT(*) as total')
            ->whereDate('record_date', $today)
            ->groupBy('register_type')
            ->pluck('total', 'register_type');

        $counts = [];
        foreach (array_keys(DailyRegister::TYPES) as $type) {
            $counts[$type] = (int) ($rows[$type] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function summary(array $filters): array
    {
        $rows = DailyRegister::query()
            ->selectRaw('register_type, COUNT(*) as total')
            ->when($filters['record_date'] ?? null, fn ($q, $v) => $q->whereDate('record_date', $v))
            ->when($filters['register_type'] ?? null, fn ($q, $v) => $q->where('register_type', $v))
            ->groupBy('register_type')
            ->pluck('total', 'register_type');

        $counts = [];
        foreach (array_keys(DailyRegister::TYPES) as $type) {
            $counts[$type] = (int) ($rows[$type] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function summaryByPeriod(string $period, ?Carbon $date = null): array
    {
        $date = $date ?? now();
        [$start, $end] = $this->periodRange($period, $date);

        // Single aggregated query — replaces 5 separate COUNTs
        $rows = DailyRegister::query()
            ->selectRaw('register_type, COUNT(*) as total')
            ->whereBetween('record_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('register_type')
            ->pluck('total', 'register_type');

        $counts = [];
        foreach (array_keys(DailyRegister::TYPES) as $type) {
            $counts[$type] = (int) ($rows[$type] ?? 0);
        }
        $counts['total']      = array_sum($counts);
        $counts['start_date'] = $start->toDateString();
        $counts['end_date']   = $end->toDateString();

        return $counts;
    }

    public function exportExcel(Collection $registers, string $filename): BinaryFileResponse
    {
        return Excel::download(new DailyRegisterExport($registers), $filename);
    }

    public function exportPdf(Collection $registers, array $filters): StreamedResponse
    {
        $types   = DailyRegister::TYPES;
        $summary = [];
        foreach (array_keys($types) as $type) {
            $summary[$type] = $registers->where('register_type', $type)->count();
        }
        $summary['total'] = $registers->count();

        $html = view('exports.daily-register-pdf', compact('registers', 'types', 'summary', 'filters'))->render();

        $filename = 'daily-register-'.now()->toDateString().'.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    private function periodRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'weekly'  => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default   => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }
}
