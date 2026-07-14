<?php

namespace App\Modules\CardRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyRegister;
use App\Models\Patient;
use App\Modules\CardRoom\Requests\StoreDailyRegisterRequest;
use App\Modules\CardRoom\Services\DailyRegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyRegisterController extends Controller
{
    public function __construct(
        private readonly DailyRegisterService $dailyRegisterService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DailyRegister::class);

        $filters = $request->only(['record_date', 'register_type', 'search_name', 'search_id']);

        // Default to today if no date set
        if (empty($filters['record_date'])) {
            $filters['record_date'] = now()->toDateString();
        }

        return Inertia::render('DailyRegister/Index', [
            'registers'        => $this->dailyRegisterService->list($filters),
            'summary'          => $this->dailyRegisterService->summary($filters),
            'types'            => DailyRegister::TYPES,
            'referral_sources' => DailyRegister::REFERRAL_SOURCES,
            'filters'          => $filters,
            'canManage'        => $request->user()->hasRole('Admin', 'Card Officer', 'Recorder'),
        ]);
    }

    public function store(StoreDailyRegisterRequest $request): RedirectResponse
    {
        $this->authorize('create', DailyRegister::class);

        $this->dailyRegisterService->create($request->validated(), $request->user()->id);

        return back()->with('success', 'Register entry added successfully.');
    }

    public function update(Request $request, DailyRegister $dailyRegister): RedirectResponse
    {
        $this->authorize('update', $dailyRegister);

        $data = $request->validate([
            'register_type'   => ['required', 'string', Rule::in(array_keys(DailyRegister::TYPES))],
            'record_date'     => ['required', 'date'],
            'department_name' => ['nullable', 'string', 'max:100'],
            'referred_from'   => ['nullable', 'string', Rule::in(DailyRegister::REFERRAL_SOURCES)],
            'days_given'      => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $this->dailyRegisterService->update($dailyRegister, $data, $request->user()->id);

        return back()->with('success', 'Register entry updated.');
    }

    public function destroy(DailyRegister $dailyRegister): RedirectResponse
    {
        $this->authorize('delete', $dailyRegister);

        $this->dailyRegisterService->delete($dailyRegister, auth()->id());

        return back()->with('success', 'Register entry deleted.');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize('export', DailyRegister::class);

        $filters   = $request->only(['record_date', 'register_type', 'search_name', 'search_id']);
        $registers = $this->dailyRegisterService->listForExport($filters);
        $filename  = 'daily-register-'.now()->toDateString().'.xlsx';

        return $this->dailyRegisterService->exportExcel($registers, $filename);
    }

    public function exportPdf(Request $request): StreamedResponse
    {
        $this->authorize('export', DailyRegister::class);

        $filters   = $request->only(['record_date', 'register_type', 'search_name', 'search_id']);
        $registers = $this->dailyRegisterService->listForExport($filters);

        return $this->dailyRegisterService->exportPdf($registers, $filters);
    }

    /**
     * Return patient data for auto-fill — lookup by card number (JSON).
     */
    public function patientInfo(Patient $patient): JsonResponse
    {
        $this->authorize('create', DailyRegister::class);

        return response()->json([
            'id'          => $patient->id,
            'full_name'   => $patient->full_name,
            'gender'      => $patient->gender,
            'age'         => $patient->date_of_birth?->age,
            'card_number' => $patient->card_number,
        ]);
    }

    /**
     * Search patients by card number for the daily register auto-fill form (JSON).
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $this->authorize('create', DailyRegister::class);

        $cardNumber = $request->string('card_number')->trim()->value();

        if (empty($cardNumber)) {
            return response()->json(['data' => []]);
        }

        $patients = Patient::where('card_number', 'ilike', '%'.$cardNumber.'%')
            ->where('status', 'Active')
            ->limit(10)
            ->get(['id', 'full_name', 'gender', 'date_of_birth', 'card_number'])
            ->map(fn ($p) => [
                'id'          => $p->id,
                'full_name'   => $p->full_name,
                'gender'      => $p->gender,
                'age'         => $p->date_of_birth?->age,
                'card_number' => $p->card_number,
            ]);

        return response()->json(['data' => $patients]);
    }
}
