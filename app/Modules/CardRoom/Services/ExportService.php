<?php

namespace App\Modules\CardRoom\Services;

use App\Modules\CardRoom\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function exportReportCsv(string $period, ?Carbon $date = null): StreamedResponse
    {
        $report = $this->reportService->report($period, $date ?? now());
        $filename = "hms-report-{$period}-{$report['start_date']}.csv";

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['MSF HMS Report']);
            fputcsv($handle, ['Period', $report['period']]);
            fputcsv($handle, ['From', $report['start_date']]);
            fputcsv($handle, ['To', $report['end_date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Total Visits', $report['total_visits']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Patient Type', 'Count']);
            foreach ($report['by_patient_type'] as $type => $count) {
                fputcsv($handle, [$type, $count]);
            }
            fputcsv($handle, []);
            fputcsv($handle, ['Room', 'Visits']);
            foreach ($report['by_room'] as $room => $count) {
                fputcsv($handle, [$room, $count]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportReportPdf(string $period, ?Carbon $date = null): StreamedResponse
    {
        $report = $this->reportService->report($period, $date ?? now());
        $html = view('exports.report-pdf', compact('report'))->render();
        $filename = "hms-report-{$period}-{$report['start_date']}.html";

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }

    public function exportVisitRegisterCsv(Collection $visits): StreamedResponse
    {
        $filename = 'visit-register-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($visits) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Time', 'Card No', 'Name', 'Type', 'Room', 'Assigned By']);
            foreach ($visits as $visit) {
                fputcsv($handle, [
                    $visit->visit_time,
                    $visit->patient?->card_number,
                    $visit->patient?->full_name,
                    $visit->patient?->patientType?->name,
                    $visit->room?->room_name,
                    $visit->assignedBy?->full_name,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
