<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Modules\OPD\Services\OpdReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpdReportController extends Controller
{
    public function __construct(
        private readonly OpdReportService $reportService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OpdQueue::class);

        $period = $request->get('period', 'daily');
        $date   = $request->filled('date') ? Carbon::parse($request->get('date')) : now();

        return Inertia::render('OPD/Reports', [
            'disease'  => $this->reportService->diseaseStats($period, $date),
            'doctor'   => $this->reportService->doctorStats($period, $date),
            'room'     => $this->reportService->roomStats($period, $date),
            'lab'      => $this->reportService->labStats($period, $date),
            'medicine' => $this->reportService->medicineStats($period, $date),
            'period'   => $period,
            'date'     => $date->toDateString(),
        ]);
    }
}
