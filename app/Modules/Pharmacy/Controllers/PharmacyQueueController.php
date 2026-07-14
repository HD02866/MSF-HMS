<?php

namespace App\Modules\Pharmacy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PharmacyQueue;
use App\Modules\Pharmacy\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PharmacyQueueController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $queue = $this->pharmacyService->queue($status);
        $stats = $this->pharmacyService->queueStats();

        return Inertia::render('Pharmacy/Queue', [
            'queue'          => $queue,
            'stats'          => $stats,
            'current_status' => $status,
        ]);
    }

    public function updateStatus(Request $request, PharmacyQueue $pharmacyQueue): RedirectResponse
    {
        $this->authorize('update', $pharmacyQueue);

        $request->validate([
            'status' => ['required', 'string', 'in:Dispensed,Cancelled'],
        ]);

        $newStatus = $request->input('status');

        if ($newStatus === 'Dispensed') {
            $this->pharmacyService->dispense($pharmacyQueue, $request->user()->id);
        } elseif ($newStatus === 'Cancelled') {
            $this->pharmacyService->cancel($pharmacyQueue, $request->user()->id);
        }

        return redirect()
            ->route('pharmacy.queue.index')
            ->with('success', "Prescription {$newStatus} successfully.");
    }
}
