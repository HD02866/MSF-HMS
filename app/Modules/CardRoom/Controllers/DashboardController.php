<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CardRoom\Services\ReportService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $this->reportService->dashboardStats(),
        ]);
    }
}
