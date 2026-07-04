<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Modules\CardRoom\Requests\StoreVisitRequest;
use App\Modules\CardRoom\Services\VisitService;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function __construct(
        private readonly VisitService $visitService,
        private readonly ReferenceDataService $ref,
    ) {}

    public function create(Request $request): Response
    {
        $this->authorize('create', Visit::class);

        $patient = null;
        if ($request->filled('patient_id')) {
            $patient = Patient::with(['patientType', 'relationshipType'])
                ->findOrFail($request->integer('patient_id'));
            $this->authorize('view', $patient);
        }

        return Inertia::render('Visits/Assign', [
            'patient' => $patient,
            'rooms'   => $this->ref->activeRooms(),
        ]);
    }

    public function store(StoreVisitRequest $request): RedirectResponse
    {
        $this->authorize('create', Visit::class);

        $patient = Patient::findOrFail($request->integer('patient_id'));
        $this->authorize('view', $patient);

        $visit = $this->visitService->assignRoom(
            $patient,
            $request->integer('room_id'),
            $request->user()->id,
            $request->input('remarks'),
        );

        return redirect()->route('visits.register')
            ->with('success', "Patient assigned successfully. {$patient->full_name} was sent to {$visit->room->room_name}.");
    }
}
