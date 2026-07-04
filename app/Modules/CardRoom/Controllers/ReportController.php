<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CardRoom\Services\ExportService;
use App\Modules\CardRoom\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ExportService $exportService,
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->get('period', 'daily');
        $date = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        return Inertia::render('Reports/Index', [
            'report' => $this->reportService->report($period, $date),
            'period' => $period,
            'date' => $date->toDateString(),
        ]);
    }

    public function export(Request $request, string $type, string $period): StreamedResponse|RedirectResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        return match ($type) {
            'excel', 'csv' => $this->exportService->exportReportCsv($period, $date),
            'pdf' => $this->exportService->exportReportPdf($period, $date),
            default => back()->with('error', 'Invalid export type.'),
        };
    }
}
