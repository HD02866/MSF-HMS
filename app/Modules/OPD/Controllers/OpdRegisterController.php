<?php

namespace App\Modules\OPD\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpdQueue;
use App\Modules\OPD\Services\OpdRegisterService;
use App\Services\ReferenceDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpdRegisterController extends Controller
{
    public function __construct(
        private readonly OpdRegisterService $opdRegisterService,
        private readonly ReferenceDataService $referenceDataService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OpdQueue::class);

        $filters = $request->only(['period', 'date', 'room_id', 'patient_type_id', 'doctor_id', 'status']);

        // Defaults
        if (empty($filters['period'])) {
            $filters['period'] = 'daily';
        }
        if (empty($filters['date'])) {
            $filters['date'] = now()->toDateString();
        }

        return Inertia::render('OPD/Register', [
            'registers'     => $this->opdRegisterService->list($filters),
            'summary'       => $this->opdRegisterService->summary($filters),
            'filters'       => $filters,
            'rooms'         => $this->opdRegisterService->roomOptions(),
            'patient_types' => $this->referenceDataService->activePatientTypes(),
            'doctors'       => $this->opdRegisterService->doctorOptions(),
            'statuses'      => OpdQueue::STATUSES,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $filters = $request->only(['period', 'date', 'room_id', 'patient_type_id', 'doctor_id', 'status']);
        $rows    = $this->opdRegisterService->listForExport($filters);
        $filename = 'opd-register-'.now()->toDateString().'.xlsx';

        return $this->opdRegisterService->exportExcel($rows, $filename);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', OpdQueue::class);

        $filters = $request->only(['period', 'date', 'room_id', 'patient_type_id', 'doctor_id', 'status']);
        $rows    = $this->opdRegisterService->listForExport($filters);

        return $this->opdRegisterService->exportPdf($rows, $filters);
    }
}
