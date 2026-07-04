<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Modules\CardRoom\Services\ExportService;
use App\Modules\CardRoom\Services\VisitService;
use App\Services\ReferenceDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitRegisterController extends Controller
{
    public function __construct(
        private readonly VisitService $visitService,
        private readonly ExportService $exportService,
        private readonly ReferenceDataService $ref,
    ) {}

    public function index(Request $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Visit::class);

        $filters = $request->only(['visit_date', 'room_id', 'patient_type_id']);

        if ($request->get('export') === 'csv') {
            $visits = Visit::query()
                ->with(['patient.patientType', 'room', 'assignedBy'])
                ->when($filters['visit_date'] ?? null, fn ($q, $v) => $q->whereDate('visit_date', $v))
                ->when($filters['room_id'] ?? null, fn ($q, $v) => $q->where('room_id', $v))
                ->when($filters['patient_type_id'] ?? null, function ($q, $v) {
                    $q->whereHas('patient', fn ($pq) => $pq->where('patient_type_id', $v));
                })
                ->orderByDesc('visit_date')
                ->orderByDesc('visit_time')
                ->get();

            return $this->exportService->exportVisitRegisterCsv($visits);
        }

        return Inertia::render('Visits/Register', [
            'visits'       => $this->visitService->register($filters),
            'rooms'        => $this->ref->activeRooms()->map(fn ($r) => ['id' => $r->id, 'room_name' => $r->room_name]),
            'patientTypes' => $this->ref->activePatientTypes(),
            'filters'      => $filters,
        ]);
    }
}
