<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyRegister;
use App\Modules\CardRoom\Services\DailyRegisterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecorderDashboardController extends Controller
{
    public function __construct(
        private readonly DailyRegisterService $dailyRegisterService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize('viewAny', DailyRegister::class);

        return Inertia::render('Recorder/Dashboard', [
            'stats' => $this->dailyRegisterService->todayStats(),
            'types' => DailyRegister::TYPES,
            'today' => now()->toDateString(),
        ]);
    }
}
