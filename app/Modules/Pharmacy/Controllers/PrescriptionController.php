<?php

namespace App\Modules\Pharmacy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\OpdQueue;
use App\Modules\Pharmacy\Requests\StorePrescriptionRequest;
use App\Modules\Pharmacy\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {}

    public function create(OpdQueue $opdQueue): Response
    {
        $this->authorize('update', $opdQueue);

        $opdQueue->load([
            'patient:id,full_name,card_number,gender,date_of_birth',
            'room:id,room_name',
        ]);

        return Inertia::render('Pharmacy/Prescription', [
            'queue_entry' => [
                'id'           => $opdQueue->id,
                'queue_number' => $opdQueue->queue_number,
                'status'       => $opdQueue->status,
            ],
            'patient' => [
                'id'          => $opdQueue->patient->id,
                'full_name'   => $opdQueue->patient->full_name,
                'card_number' => $opdQueue->patient->card_number,
                'gender'      => $opdQueue->patient->gender,
                'age'         => $opdQueue->patient->date_of_birth?->age,
            ],
            'room_name'       => $opdQueue->room?->room_name,
            'today'           => now()->toDateString(),
            'prescriber_name' => auth()->user()?->full_name ?? '',
            'medicines'       => Medicine::active()->orderBy('name')->get([
                'id', 'name', 'generic_name', 'form', 'unit', 'unit_price', 'quantity_in_stock', 'minimum_stock_level',
            ]),
            'prior_prescriptions' => $this->pharmacyService->prescriptionsForEncounter($opdQueue->id),
        ]);
    }

    public function store(StorePrescriptionRequest $request, OpdQueue $opdQueue): RedirectResponse
    {
        $this->authorize('update', $opdQueue);

        $this->pharmacyService->createPrescription($opdQueue, $request->validated(), $request->user()->id);

        return redirect()
            ->route('opd.prescription.create', $opdQueue)
            ->with('success', 'Prescription submitted to pharmacy successfully.');
    }

    public function searchMedicines(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $results = $this->pharmacyService->searchMedicines($request->input('q'));

        return response()->json($results);
    }

    public function checkAvailability(Request $request)
    {
        $request->validate([
            'medicine_id'  => ['required', 'integer', 'exists:medicines,id'],
            'quantity'     => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->pharmacyService->checkAvailability(
            (int) $request->input('medicine_id'),
            (int) $request->input('quantity'),
        );

        return response()->json($result);
    }
}
