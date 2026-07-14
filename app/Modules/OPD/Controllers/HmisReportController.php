<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Modules\OPD\Exports\HmisReportExport;
use App\Modules\OPD\Services\HmisReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HmisReportController extends Controller
{
    public function __construct(
        private readonly HmisReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OpdQueue::class);

        $period = $request->get('period', 'daily');
        $date   = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        return Inertia::render('OPD/HmisReports', [
            'overview'     => $this->service->overview($period, $date),
            'demographics' => $this->service->patientDemographics($period, $date),
            'disease'      => $this->service->diseaseStatistics($period, $date),
            'laboratory'   => $this->service->laboratoryReport($period, $date),
            'pharmacy'     => $this->service->pharmacyReport($period, $date),
            'referrals'    => $this->service->referralReport($period, $date),
            'sickLeave'    => $this->service->sickLeaveReport($period, $date),
            'visits'       => $this->service->completedVisits($period, $date),
            'period'       => $period,
            'date'         => $date->toDateString(),
        ]);
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $period = $request->get('period', 'daily');
        $date   = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        $data = [
            'overview'     => $this->service->overview($period, $date),
            'demographics' => $this->service->patientDemographics($period, $date),
            'disease'      => $this->service->diseaseStatistics($period, $date),
            'laboratory'   => $this->service->laboratoryReport($period, $date),
            'pharmacy'     => $this->service->pharmacyReport($period, $date),
            'referrals'    => $this->service->referralReport($period, $date),
            'sickLeave'    => $this->service->sickLeaveReport($period, $date),
            'visits'       => $this->service->completedVisits($period, $date),
        ];

        $filename = 'hmis-report-'.$period.'-'.$date->toDateString().'.xlsx';

        return Excel::download(new HmisReportExport($data), $filename);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $period = $request->get('period', 'daily');
        $date   = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        $data = [
            'overview'     => $this->service->overview($period, $date),
            'demographics' => $this->service->patientDemographics($period, $date),
            'disease'      => $this->service->diseaseStatistics($period, $date),
            'laboratory'   => $this->service->laboratoryReport($period, $date),
            'pharmacy'     => $this->service->pharmacyReport($period, $date),
            'referrals'    => $this->service->referralReport($period, $date),
            'sickLeave'    => $this->service->sickLeaveReport($period, $date),
            'visits'       => $this->service->completedVisits($period, $date),
            'period'       => $period,
            'date'         => $date->toDateString(),
        ];

        $html = view('exports.hmis-report-pdf', $data)->render();

        $filename = 'hmis-report-'.$period.'-'.$date->toDateString().'.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, ['Content-Type' => 'text/html']);
    }
}
