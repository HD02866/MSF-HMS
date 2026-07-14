import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';

// ── Types ──────────────────────────────────────────────────────────────────────
interface LabQueueInfo {
    id: number;
    status: string;
}

interface PatientInfo {
    id: number;
    full_name: string;
    card_number: string;
    gender: string | null;
    age: number | null;
}

interface LabRequestInfo {
    id: number;
    priority: string;
    request_date: string;
    clinical_notes: string | null;
    requested_by: string | null;
}

interface ExistingResult {
    id: number;
    result: string;
    remarks: string | null;
    result_date: string;
    performed_by: string | null;
}

interface TestItem {
    id: number;
    test_name: string;
    result: ExistingResult | null;
}

interface Props {
    lab_queue: LabQueueInfo;
    patient: PatientInfo;
    lab_request: LabRequestInfo;
    tests: TestItem[];
    today: string;
}

// ── Result row for one test ────────────────────────────────────────────────────
function TestResultRow({
    test,
    today,
    resultValue: result,
    remarks,
    resultDate,
    resultError,
    remarksError,
    dateError,
    onChangeResult,
    onChangeRemarks,
    onChangeDate,
    isCompleted,
}: {
    test: TestItem;
    today: string;
    resultValue: string;
    remarks: string;
    resultDate: string;
    resultError?: string;
    remarksError?: string;
    dateError?: string;
    onChangeResult: (v: string) => void;
    onChangeRemarks: (v: string) => void;
    onChangeDate: (v: string) => void;
    isCompleted: boolean;
}) {
    const hasExisting = test.result !== null;

    return (
        <div className={`rounded-xl border p-4 space-y-3 ${hasExisting ? 'bg-green-50 border-green-200' : 'bg-white'}`}>
            {/* Test name + existing result badge */}
            <div className="flex items-start justify-between gap-3 flex-wrap">
                <p className="font-semibold text-gray-800 text-sm">{test.test_name}</p>
                {hasExisting && (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 border border-green-200 shrink-0">
                        ✓ Result saved
                        {test.result!.performed_by && ` · ${test.result!.performed_by}`}
                    </span>
                )}
            </div>

            {/* Existing result display */}
            {hasExisting && (
                <div className="text-xs text-gray-600 bg-white rounded-lg px-3 py-2 border border-green-100 space-y-1">
                    <p><span className="font-medium text-gray-700">Result:</span> {test.result!.result}</p>
                    {test.result!.remarks && (
                        <p><span className="font-medium text-gray-700">Remarks:</span> {test.result!.remarks}</p>
                    )}
                    <p><span className="font-medium text-gray-700">Date:</span> {test.result!.result_date}</p>
                    {isCompleted && (
                        <p className="text-xs text-gray-400 italic pt-0.5">Results are locked after the request is Completed.</p>
                    )}
                </div>
            )}

            {/* Entry fields — shown when not yet completed (or overriding) */}
            {!isCompleted && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {/* Result value */}
                    <div className="md:col-span-1">
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Result {!hasExisting && <span className="text-gray-400">(required if entering)</span>}
                        </label>
                        <input
                            type="text"
                            value={result}
                            onChange={(e) => onChangeResult(e.target.value)}
                            placeholder={hasExisting ? 'Update result…' : 'e.g. 12.5 g/dL, Positive, Negative…'}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                        {resultError && <p className="text-red-500 text-xs mt-1">{resultError}</p>}
                    </div>

                    {/* Remarks */}
                    <div className="md:col-span-1">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Remarks <span className="text-gray-400">(optional)</span></label>
                        <input
                            type="text"
                            value={remarks}
                            onChange={(e) => onChangeRemarks(e.target.value)}
                            placeholder="Reference range, interpretation…"
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                        {remarksError && <p className="text-red-500 text-xs mt-1">{remarksError}</p>}
                    </div>

                    {/* Result date */}
                    <div className="md:col-span-1">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Result Date <span className="text-red-500">*</span></label>
                        <input
                            type="date"
                            value={resultDate}
                            onChange={(e) => onChangeDate(e.target.value)}
                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                        {dateError && <p className="text-red-500 text-xs mt-1">{dateError}</p>}
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function LabResultEntry({ lab_queue, patient, lab_request, tests, today }: Props) {
    const isCompleted = lab_queue.status === 'Completed';
    const isCancelled = lab_queue.status === 'Cancelled';
    const isReadOnly  = isCompleted || isCancelled;

    // Build initial form values — pre-fill with existing results if any
    type ResultsMap = Record<string, { result: string; remarks: string; result_date: string }>;

    const initialResults: ResultsMap = {};
    tests.forEach((t) => {
        initialResults[String(t.id)] = {
            result:      t.result?.result      ?? '',
            remarks:     t.result?.remarks     ?? '',
            result_date: t.result?.result_date ?? today,
        };
    });

    const { data, setData, post, processing, errors } = useForm<{ results: ResultsMap }>({
        results: initialResults,
    });

    function setField(testId: number, field: 'result' | 'remarks' | 'result_date', value: string) {
        setData('results', {
            ...data.results,
            [String(testId)]: {
                ...data.results[String(testId)],
                [field]: value,
            },
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/lab/queue/${lab_queue.id}/results`, { preserveScroll: true });
    }

    const filledCount = Object.values(data.results).filter((r) => r.result.trim() !== '').length;
    const totalCount  = tests.length;

    return (
        <AppLayout title="Enter Lab Results">
            <Head title={`Results — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        🧪
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">
                            {isReadOnly ? 'Lab Results' : 'Enter Lab Results'}
                        </h1>
                        <p className="text-green-100 text-sm mt-0.5">
                            {patient.full_name}
                            <span className="font-mono ml-2 opacity-80">{patient.card_number}</span>
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold
                        ${lab_queue.status === 'Completed' ? 'bg-green-100 text-green-900'
                        : lab_queue.status === 'Cancelled' ? 'bg-red-100 text-red-700'
                        : 'bg-yellow-100 text-yellow-900'}`}
                    >
                        {lab_queue.status}
                    </span>
                    <a
                        href="/lab/queue"
                        className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                    >
                        ← Back to Queue
                    </a>
                </div>
            </div>

            {/* Patient + request info strip */}
            <div className="bg-white border rounded-xl px-5 py-4 mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <p className="text-xs text-gray-400 mb-0.5">Gender</p>
                    <p className="font-medium text-gray-800">{patient.gender ?? '—'}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-400 mb-0.5">Age</p>
                    <p className="font-medium text-gray-800">{patient.age != null ? `${patient.age} yrs` : '—'}</p>
                </div>
                <div>
                    <p className="text-xs text-gray-400 mb-0.5">Priority</p>
                    <p className={`font-semibold ${lab_request.priority === 'Urgent' ? 'text-red-600' : 'text-gray-700'}`}>
                        {lab_request.priority === 'Urgent' ? '🔴 ' : ''}{lab_request.priority}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-gray-400 mb-0.5">Request Date</p>
                    <p className="font-medium text-gray-800">{lab_request.request_date}</p>
                </div>
                {lab_request.clinical_notes && (
                    <div className="col-span-2 sm:col-span-4">
                        <p className="text-xs text-gray-400 mb-0.5">Clinical Notes</p>
                        <p className="text-sm text-gray-700 bg-blue-50 rounded-md px-3 py-2 border border-blue-100">
                            {lab_request.clinical_notes}
                        </p>
                    </div>
                )}
            </div>

            {/* Cancelled notice */}
            {isCancelled && (
                <div className="mb-6 rounded-xl bg-red-50 border border-red-200 px-5 py-4 text-sm text-red-700">
                    This lab request was cancelled. Results cannot be entered.
                </div>
            )}

            {/* Results form */}
            <form onSubmit={submit} className="space-y-4">
                {/* Progress counter */}
                {!isReadOnly && (
                    <div className="flex items-center justify-between text-sm text-gray-600 mb-2">
                        <span>
                            {filledCount} of {totalCount} test{totalCount !== 1 ? 's' : ''} filled
                        </span>
                        {errors.results && (
                            <span className="text-red-500 text-xs">{errors.results}</span>
                        )}
                    </div>
                )}

                {/* One card per test */}
                {tests.map((test) => (
                    <TestResultRow
                        key={test.id}
                        test={test}
                        today={today}
                        resultValue={data.results[String(test.id)]?.result ?? ''}
                        remarks={data.results[String(test.id)]?.remarks ?? ''}
                        resultDate={data.results[String(test.id)]?.result_date ?? today}
                        resultError={(errors as any)[`results.${test.id}.result`]}
                        remarksError={(errors as any)[`results.${test.id}.remarks`]}
                        dateError={(errors as any)[`results.${test.id}.result_date`]}
                        onChangeResult={(v) => setField(test.id, 'result', v)}
                        onChangeRemarks={(v) => setField(test.id, 'remarks', v)}
                        onChangeDate={(v) => setField(test.id, 'result_date', v)}
                        isCompleted={isReadOnly}
                    />
                ))}

                {/* Submit */}
                {!isReadOnly && (
                    <div className="bg-white rounded-xl border shadow-sm p-5 flex items-center justify-between flex-wrap gap-4">
                        <p className="text-xs text-gray-400">
                            {filledCount === 0
                                ? 'Fill in at least one result to save.'
                                : `Saving ${filledCount} result${filledCount !== 1 ? 's' : ''}. When all tests are filled, the request will be marked Completed automatically.`
                            }
                        </p>
                        <div className="flex gap-3">
                            <a
                                href="/lab/queue"
                                className="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </a>
                            <Button
                                type="submit"
                                disabled={processing || filledCount === 0}
                            >
                                {processing ? 'Saving…' : '💾 Save Results'}
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
