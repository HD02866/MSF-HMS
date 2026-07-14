import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState } from 'react';
import SignatureCanvas from '@/components/SignatureCanvas';

// ── Types ──────────────────────────────────────────────────────────────────────
interface QueueEntry {
    id: number;
    queue_number: number;
    status: string;
}

interface Patient {
    id: number;
    full_name: string;
    card_number: string;
    gender: string | null;
    age: number | null;
}

interface PriorRequest {
    id: number;
    request_date: string;
    priority: string;
    clinical_notes: string | null;
    requested_by: string | null;
    created_at: string;
    tests: {
        id: number;
        test_name: string;
        result: {
            result: string;
            remarks: string | null;
            result_date: string;
            performed_by: string | null;
        } | null;
    }[];
    queue_status: string;
}

interface Props {
    queue_entry: QueueEntry;
    patient: Patient;
    room_name: string | null;
    test_catalog: Record<string, string[]>;
    priorities: Record<string, string>;
    today: string;
    requester_name: string;
    prior_requests: PriorRequest[];
}

// ── Priority badge colour ──────────────────────────────────────────────────────
function priorityBadge(priority: string) {
    return priority === 'Urgent'
        ? 'bg-red-100 text-red-700 border border-red-200'
        : 'bg-gray-100 text-gray-600 border border-gray-200';
}

// ── Single test checkbox ───────────────────────────────────────────────────────
function TestCheckbox({
    name,
    checked,
    onToggle,
}: {
    name: string;
    checked: boolean;
    onToggle: () => void;
}) {
    return (
        <label className={`flex items-center gap-2.5 px-3 py-2 rounded-lg border cursor-pointer transition-colors text-sm select-none
            ${checked
                ? 'bg-green-50 border-green-400 text-green-900 font-medium'
                : 'bg-white border-gray-200 text-gray-700 hover:border-green-300 hover:bg-green-50'
            }`}
        >
            <input
                type="checkbox"
                checked={checked}
                onChange={onToggle}
                className="accent-green-600 w-4 h-4 shrink-0"
            />
            <span className="leading-snug">{name}</span>
        </label>
    );
}

// ── Test panel (collapsible group) ────────────────────────────────────────────
function TestPanel({
    panelName,
    tests,
    selected,
    onToggle,
    onSelectAll,
    onClearAll,
}: {
    panelName: string;
    tests: string[];
    selected: Set<string>;
    onToggle: (name: string) => void;
    onSelectAll: (tests: string[]) => void;
    onClearAll: (tests: string[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const checkedCount = tests.filter((t) => selected.has(t)).length;
    const allChecked = checkedCount === tests.length;

    return (
        <div className="border rounded-xl overflow-hidden">
            {/* Panel header */}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
            >
                <div className="flex items-center gap-3">
                    <span className="font-semibold text-gray-700 text-sm">{panelName}</span>
                    {checkedCount > 0 && (
                        <span className="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-600 text-white text-xs font-bold">
                            {checkedCount}
                        </span>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    {open && (
                        <>
                            <button
                                type="button"
                                onClick={(e) => { e.stopPropagation(); onSelectAll(tests); }}
                                className="text-xs text-green-700 hover:underline font-medium"
                            >
                                All
                            </button>
                            <button
                                type="button"
                                onClick={(e) => { e.stopPropagation(); onClearAll(tests); }}
                                className="text-xs text-gray-500 hover:underline"
                            >
                                Clear
                            </button>
                        </>
                    )}
                    <span className="text-gray-400 text-xs">{open ? '▲' : '▼'}</span>
                </div>
            </button>

            {/* Tests grid */}
            {open && (
                <div className="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 bg-white">
                    {tests.map((test) => (
                        <TestCheckbox
                            key={test}
                            name={test}
                            checked={selected.has(test)}
                            onToggle={() => onToggle(test)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

// ── Previously submitted requests (read-only) ─────────────────────────────────
function PriorRequestsList({ requests }: { requests: PriorRequest[] }) {
    if (requests.length === 0) return null;

    return (
        <div className="bg-white rounded-xl border shadow-sm overflow-hidden mb-6">
            <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-700">Previous Requests This Encounter</h3>
                <span className="text-xs text-gray-500">{requests.length} request{requests.length !== 1 ? 's' : ''}</span>
            </div>
            <div className="divide-y">
                {requests.map((req) => (
                    <div key={req.id} className="px-5 py-4">
                        <div className="flex items-start justify-between gap-3 flex-wrap mb-2">
                            <div className="flex items-center gap-2">
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${priorityBadge(req.priority)}`}>
                                    {req.priority}
                                </span>
                                <span className="text-xs text-gray-500">{req.request_date}</span>
                                {req.requested_by && (
                                    <span className="text-xs text-gray-400">by {req.requested_by}</span>
                                )}
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                    ${req.queue_status === 'Completed'
                                        ? 'bg-teal-100 text-teal-700'
                                        : req.queue_status === 'Cancelled'
                                            ? 'bg-red-100 text-red-600'
                                            : 'bg-yellow-100 text-yellow-700'}`}
                                >
                                    {req.queue_status}
                                </span>
                            </div>
                            <span className="text-xs text-gray-400">
                                {new Date(req.created_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-1.5 mb-2">
                            {req.tests.map((test) => (
                                <span key={test.id} className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs border
                                    ${test.result
                                        ? 'bg-teal-50 text-teal-700 border-teal-200'
                                        : 'bg-blue-50 text-blue-700 border-blue-100'}`}
                                >
                                    {test.result && <span>✓ </span>}
                                    {test.test_name}
                                    {test.result && (
                                        <span className="ml-1 font-semibold">{test.result.result}</span>
                                    )}
                                </span>
                            ))}
                        </div>
                        {req.clinical_notes && (
                            <p className="text-xs text-gray-500 bg-gray-50 rounded-md px-3 py-2 mt-1">
                                {req.clinical_notes}
                            </p>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function LabRequest({
    queue_entry, patient, room_name,
    test_catalog, priorities, today,
    requester_name: defaultRequesterName,
    prior_requests,
}: Props) {
    // Selected tests — keyed Set for O(1) toggle/lookup
    const [selectedTests, setSelectedTests] = useState<Set<string>>(new Set());

    const { data, setData, post, processing, errors } = useForm<{
        tests: string[];
        clinical_notes: string;
        priority: string;
        request_date: string;
        requester_name: string;
        signature_data: string;
    }>({
        tests:           [],
        clinical_notes:  '',
        priority:        'Normal',
        request_date:    today,
        requester_name:  defaultRequesterName,
        signature_data:  '',
    });

    // Keep form data.tests in sync with the Set
    function syncTests(next: Set<string>) {
        setSelectedTests(next);
        setData('tests', Array.from(next));
    }

    function toggleTest(name: string) {
        const next = new Set(selectedTests);
        if (next.has(name)) next.delete(name);
        else next.add(name);
        syncTests(next);
    }

    function selectAll(tests: string[]) {
        const next = new Set(selectedTests);
        tests.forEach((t) => next.add(t));
        syncTests(next);
    }

    function clearAll(tests: string[]) {
        const next = new Set(selectedTests);
        tests.forEach((t) => next.delete(t));
        syncTests(next);
    }

    function clearAllSelected() {
        syncTests(new Set());
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${queue_entry.id}/lab`, { preserveScroll: true });
    }

    const totalSelected = selectedTests.size;

    return (
        <AppLayout title="Laboratory Request">
            <Head title={`Lab Request — ${patient.full_name}`} />

            {/* ── Screen layout ─────────────────────────────────────────────── */}
            <div className="lab-request-screen">
                {/* Header */}
                <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                    <div className="flex items-center gap-4">
                        <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                            🔬
                        </div>
                        <div>
                            <h1 className="text-xl font-bold leading-tight">Laboratory Request</h1>
                            <p className="text-green-100 text-sm mt-0.5">
                                {patient.full_name}
                                <span className="font-mono ml-2 opacity-80">{patient.card_number}</span>
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-green-100">
                            {room_name ?? '—'} · Queue #{queue_entry.queue_number}
                        </span>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-md text-sm font-medium"
                        >
                            🖨️ Print
                        </button>
                        <a
                            href={`/opd/consultation/${queue_entry.id}`}
                            className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                        >
                            ← Back to Consultation
                        </a>
                    </div>
                </div>

                {/* Patient summary strip */}
                <div className="bg-white border rounded-xl px-5 py-3 mb-6 flex flex-wrap gap-6 text-sm">
                    <div><span className="text-gray-400 text-xs">Gender</span><p className="font-medium text-gray-800">{patient.gender ?? '—'}</p></div>
                    <div><span className="text-gray-400 text-xs">Age</span><p className="font-medium text-gray-800">{patient.age != null ? `${patient.age} yrs` : '—'}</p></div>
                    <div><span className="text-gray-400 text-xs">Queue Status</span><p className="font-medium text-gray-800">{queue_entry.status}</p></div>
                </div>

                {/* Prior requests for this encounter */}
                <PriorRequestsList requests={prior_requests} />

                {/* Request form */}
                <form onSubmit={submit} className="space-y-6">

                    {/* Request meta — date, priority */}
                    <div className="bg-white rounded-xl border shadow-sm p-5">
                        <h3 className="font-semibold text-gray-800 mb-4">Request Details</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {/* Request date */}
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Request Date <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={data.request_date}
                                    onChange={(e) => setData('request_date', e.target.value)}
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                />
                                {errors.request_date && <p className="text-red-500 text-xs mt-1">{errors.request_date}</p>}
                            </div>

                            {/* Priority */}
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Priority <span className="text-red-500">*</span>
                                </label>
                                <div className="flex gap-3 mt-1">
                                    {Object.entries(priorities).map(([key, label]) => (
                                        <label key={key} className={`flex items-center gap-2 px-4 py-2 rounded-lg border cursor-pointer text-sm font-medium transition-colors
                                            ${data.priority === key
                                                ? key === 'Urgent'
                                                    ? 'bg-red-50 border-red-400 text-red-700'
                                                    : 'bg-green-50 border-green-400 text-green-700'
                                                : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="priority"
                                                value={key}
                                                checked={data.priority === key}
                                                onChange={() => setData('priority', key)}
                                                className="accent-green-600"
                                            />
                                            {key === 'Urgent' ? '🔴 ' : '🟢 '}{label}
                                        </label>
                                    ))}
                                </div>
                                {errors.priority && <p className="text-red-500 text-xs mt-1">{errors.priority}</p>}
                            </div>

                            {/* Requested by — display only */}
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">Requested By</label>
                                <p className="text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                                    {defaultRequesterName || 'Current user (auto-filled)'}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Test selection */}
                    <div className="bg-white rounded-xl border shadow-sm p-5">
                        <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                            <div>
                                <h3 className="font-semibold text-gray-800">Select Tests</h3>
                                <p className="text-xs text-gray-400 mt-0.5">Click a panel to expand, tick the tests required</p>
                            </div>
                            <div className="flex items-center gap-3">
                                {totalSelected > 0 && (
                                    <>
                                        <span className="text-sm font-semibold text-green-700">
                                            {totalSelected} test{totalSelected !== 1 ? 's' : ''} selected
                                        </span>
                                        <button
                                            type="button"
                                            onClick={clearAllSelected}
                                            className="text-xs text-gray-400 hover:text-red-500 hover:underline"
                                        >
                                            Clear all
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>

                        {errors.tests && (
                            <div className="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-xs text-red-700">
                                {errors.tests}
                            </div>
                        )}

                        <div className="space-y-3">
                            {Object.entries(test_catalog).map(([panel, tests]) => (
                                <TestPanel
                                    key={panel}
                                    panelName={panel}
                                    tests={tests}
                                    selected={selectedTests}
                                    onToggle={toggleTest}
                                    onSelectAll={selectAll}
                                    onClearAll={clearAll}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Selected tests summary chip row */}
                    {totalSelected > 0 && (
                        <div className="bg-green-50 border border-green-200 rounded-xl p-4">
                            <p className="text-xs font-semibold text-green-700 mb-2">
                                Selected Tests ({totalSelected})
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {Array.from(selectedTests).map((test) => (
                                    <span
                                        key={test}
                                        className="inline-flex items-center gap-1.5 pl-3 pr-2 py-1 rounded-full bg-white border border-green-300 text-green-800 text-xs font-medium"
                                    >
                                        {test}
                                        <button
                                            type="button"
                                            onClick={() => toggleTest(test)}
                                            className="text-green-400 hover:text-red-500 leading-none"
                                            aria-label={`Remove ${test}`}
                                        >
                                            ×
                                        </button>
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Clinical notes */}
                    <div className="bg-white rounded-xl border shadow-sm p-5">
                        <label className="block text-sm font-semibold text-gray-700 mb-2">
                            Clinical Notes
                            <span className="ml-1 text-xs font-normal text-gray-400">(optional — relevant history or indication for the lab)</span>
                        </label>
                        <textarea
                            rows={4}
                            value={data.clinical_notes}
                            onChange={(e) => setData('clinical_notes', e.target.value)}
                            placeholder="e.g. Patient presents with fever for 3 days. Suspect malaria. Please check CBC and Malaria RDT."
                            className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
                        />
                        {errors.clinical_notes && <p className="text-red-500 text-xs mt-1">{errors.clinical_notes}</p>}
                    </div>

                    {/* Requester & Signature */}
                    <div className="bg-white rounded-xl border shadow-sm p-5">
                        <h3 className="font-semibold text-gray-800 mb-4">Requester &amp; Signature</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Doctor / Nurse Name
                                </label>
                                <input
                                    type="text"
                                    value={data.requester_name}
                                    onChange={(e) => setData('requester_name', e.target.value)}
                                    placeholder="e.g. Dr. Alemayehu / Nurse Fatuma"
                                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                />
                                {errors.requester_name && <p className="text-red-500 text-xs mt-1">{errors.requester_name}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Digital Signature
                                </label>
                                <SignatureCanvas
                                    value={data.signature_data}
                                    onChange={(val) => setData('signature_data', val)}
                                />
                                {errors.signature_data && <p className="text-red-500 text-xs mt-1">{errors.signature_data}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Submit */}
                    <div className="bg-white rounded-xl border shadow-sm p-5 flex items-center justify-between flex-wrap gap-4">
                        <div className="text-xs text-gray-400">
                            {totalSelected === 0
                                ? 'Select at least one test to submit.'
                                : `${totalSelected} test${totalSelected !== 1 ? 's' : ''} · ${data.priority} priority · ${data.request_date}`
                            }
                        </div>
                        <div className="flex gap-3">
                            <a
                                href={`/opd/consultation/${queue_entry.id}`}
                                className="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                            >
                                Back to Consultation
                            </a>
                            <Button
                                type="submit"
                                disabled={processing || totalSelected === 0}
                                className={data.priority === 'Urgent' ? 'bg-red-600 hover:bg-red-700' : ''}
                            >
                                {processing ? 'Submitting…' : `🔬 Submit Request${data.priority === 'Urgent' ? ' (Urgent)' : ''}`}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>

            {/* ── Printable layout (hidden on screen, shown on print) ──────── */}
            <div className="lab-request-print">
                {/* Hospital header */}
                <div className="print-header">
                    <div className="print-logo">MSF</div>
                    <div className="print-header-text">
                        <h1>Metahara Sugar Factory Hospital</h1>
                        <h2>Laboratory Request Form</h2>
                    </div>
                </div>

                {/* Patient & Request info */}
                <table className="print-info-table">
                    <tbody>
                        <tr>
                            <td className="print-label">Patient Name</td>
                            <td className="print-value">{patient.full_name}</td>
                            <td className="print-label">Card Number</td>
                            <td className="print-value font-mono">{patient.card_number}</td>
                        </tr>
                        <tr>
                            <td className="print-label">Gender</td>
                            <td className="print-value">{patient.gender ?? '—'}</td>
                            <td className="print-label">Age</td>
                            <td className="print-value">{patient.age != null ? `${patient.age} years` : '—'}</td>
                        </tr>
                        <tr>
                            <td className="print-label">Request Date</td>
                            <td className="print-value">{data.request_date}</td>
                            <td className="print-label">Priority</td>
                            <td className="print-value">{data.priority}</td>
                        </tr>
                        <tr>
                            <td className="print-label">Room</td>
                            <td className="print-value">{room_name ?? '—'}</td>
                            <td className="print-label">Queue #</td>
                            <td className="print-value">{queue_entry.queue_number}</td>
                        </tr>
                    </tbody>
                </table>

                {/* Selected tests */}
                <div className="print-section">
                    <h3>Requested Tests</h3>
                    <table className="print-tests-table">
                        <thead>
                            <tr>
                                <th style={{ width: '40px' }}>#</th>
                                <th>Test Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            {Array.from(selectedTests).map((test, i) => (
                                <tr key={test}>
                                    <td className="text-center">{i + 1}</td>
                                    <td>{test}</td>
                                </tr>
                            ))}
                            {selectedTests.size === 0 && (
                                <tr>
                                    <td colSpan={2} className="text-center text-gray-400 italic">No tests selected</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Clinical notes */}
                {data.clinical_notes && (
                    <div className="print-section">
                        <h3>Clinical Notes</h3>
                        <p className="print-clinical-notes">{data.clinical_notes}</p>
                    </div>
                )}

                {/* Signature area */}
                <table className="print-signature-table">
                    <tbody>
                        <tr>
                            <td style={{ width: '50%' }}>
                                <div className="print-sig-block">
                                    <div className="print-sig-label">Requester Name</div>
                                    <div className="print-sig-value">{data.requester_name || '—'}</div>
                                </div>
                            </td>
                            <td style={{ width: '50%' }}>
                                <div className="print-sig-block">
                                    <div className="print-sig-label">Signature</div>
                                    <div className="print-sig-image">
                                        {data.signature_data ? (
                                            <img src={data.signature_data} alt="Signature" />
                                        ) : (
                                            <span className="print-sig-placeholder">No signature</span>
                                        )}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                {/* Footer */}
                <div className="print-footer">
                    <span>Date: {data.request_date}</span>
                    <span>Printed: {new Date().toLocaleDateString('en-GB')}</span>
                </div>
            </div>
        </AppLayout>
    );
}
