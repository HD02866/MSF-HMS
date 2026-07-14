<?php

namespace App\Modules\OPD\Services;

use App\Models\OpdQueue;
use App\Models\OpdClinicalNote;
use App\Models\Room;
use App\Modules\OPD\Exports\OpdRegisterExport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpdRegisterService
{
    private const COMPLETED_STATUSES = ['Completed', 'Transferred'];

    public function list(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function listForExport(array $filters): Collection
    {
        return $this->baseQuery($filters)->get();
    }

    public function summary(array $filters): array
    {
        $base = $this->baseQuery($filters);

        $completed = (clone $base)->where('status', 'Completed')->count();
        $transferred = (clone $base)->where('status', 'Transferred')->count();

        return [
            'completed'   => $completed,
            'transferred' => $transferred,
            'total'       => $completed + $transferred,
        ];
    }

    public function doctorOptions(): Collection
    {
        $userIds = OpdClinicalNote::query()
            ->select('created_by')
            ->distinct()
            ->whereNotNull('created_by')
            ->pluck('created_by');

        if ($userIds->isEmpty()) {
            return collect();
        }

        return \App\Models\User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'full_name'])
            ->map(fn ($user) => [
                'id'        => $user->id,
                'full_name' => $user->full_name,
            ])
            ->values();
    }

    public function roomOptions(): Collection
    {
        return Room::query()
            ->whereIn('room_code', OpdQueue::OPD_ROOM_CODES)
            ->where('is_active', true)
            ->orderBy('room_name')
            ->get(['id', 'room_name', 'room_code']);
    }

    public function exportExcel(Collection $rows, string $filename): BinaryFileResponse
    {
        return Excel::download(new OpdRegisterExport($rows), $filename);
    }

    public function exportPdf(Collection $rows, array $filters): StreamedResponse
    {
        $summary = [
            'completed'   => $rows->where('status', 'Completed')->count(),
            'transferred' => $rows->where('status', 'Transferred')->count(),
            'total'       => $rows->count(),
        ];

        $html = view('exports.opd-register-pdf', compact('rows', 'summary', 'filters'))->render();

        $filename = 'opd-register-'.now()->toDateString().'.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    private function baseQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = OpdQueue::query()
            ->with([
                'patient:id,card_number,full_name,gender,date_of_birth,patient_type_id',
                'patient.patientType:id,name',
                'room:id,room_name,room_code',
                'clinicalNote:id,opd_queue_id,chief_complaint,diagnosis,treatment_plan,created_by',
                'clinicalNote.creator:id,full_name',
            ])
            ->whereIn('status', self::COMPLETED_STATUSES);

        // Period filter
        $period = $filters['period'] ?? 'daily';
        $date   = ! empty($filters['date']) ? Carbon::parse($filters['date']) : now();
        [$start, $end] = $this->periodRange($period, $date);
        $query->whereBetween('arrived_at', [$start, $end]);

        // Room filter
        if (! empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        // Patient type filter
        if (! empty($filters['patient_type_id'])) {
            $query->whereHas('patient', fn ($q) => $q->where('patient_type_id', $filters['patient_type_id']));
        }

        // Doctor/nurse filter (clinical note creator)
        if (! empty($filters['doctor_id'])) {
            $query->whereHas('clinicalNote', fn ($q) => $q->where('created_by', $filters['doctor_id']));
        }

        // Status filter
        if (! empty($filters['status']) && in_array($filters['status'], self::COMPLETED_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('arrived_at');
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
