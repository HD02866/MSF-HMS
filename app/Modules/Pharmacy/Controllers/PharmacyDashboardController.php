<?php

namespace App\Modules\Pharmacy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pharmacy\Services\PharmacyService;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyDashboardController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Pharmacy/Dashboard', [
            'stats'        => $this->pharmacyService->dashboardStats(),
            'recent_queue' => $this->pharmacyService->recentQueue(),
            'inventory'    => $this->pharmacyService->inventoryStats(),
        ]);
    }
}
