<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Modules\CardRoom\Requests\StorePatientRequest;
use App\Modules\CardRoom\Requests\UpdatePatientRequest;
use App\Modules\CardRoom\Services\PatientService;
use App\Services\ReferenceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly ReferenceDataService $ref,
    ) {}

    public function search(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);

        return Inertia::render('Patients/Search', [
            'patients'     => $this->patientService->search($request->only([
                'card_number', 'employee_no', 'insurance_no', 'full_name', 'phone', 'patient_type_id',
            ])),
            'patientTypes' => $this->ref->activePatientTypes(),
            'filters'      => $request->only([
                'card_number', 'employee_no', 'insurance_no', 'full_name', 'phone', 'patient_type_id',
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Patient::class);

        return Inertia::render('Patients/Create', [
            'patientTypes'      => $this->ref->activePatientTypes(),
            'relationshipTypes' => $this->ref->relationshipTypes(),
        ]);
    }

    public function store(StorePatientRequest $request): RedirectResponse
    {
        $this->authorize('create', Patient::class);

        // Merge the uploaded file into validated data so the service can handle it
        $data = array_merge($request->validated(), [
            'photo' => $request->file('photo'),
        ]);

        $patient = $this->patientService->create($data, $request->user()->id);

        if ($request->boolean('assign_room')) {
            return redirect()->route('visits.assign', ['patient_id' => $patient->id])
                ->with('success', 'Patient created successfully.');
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient card created successfully.');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        $patient->load(['patientType', 'relationshipType', 'visits.room', 'visits.assignedBy']);

        return Inertia::render('Patients/Show', [
            'patient' => $patient,
        ]);
    }

    public function edit(Patient $patient): Response
    {
        $this->authorize('update', $patient);

        return Inertia::render('Patients/Edit', [
            'patient'           => $patient->load(['patientType', 'relationshipType']),
            'patientTypes'      => $this->ref->activePatientTypes(),
            'relationshipTypes' => $this->ref->relationshipTypes(),
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = array_merge($request->validated(), [
            'photo' => $request->file('photo'),
        ]);

        $this->patientService->update($patient, $data, $request->user()->id);

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient updated successfully.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        $this->patientService->deactivate($patient, auth()->id());

        return redirect()->route('patients.search')
            ->with('success', 'Patient deactivated successfully.');
    }
}
