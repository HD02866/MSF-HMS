import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
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
    destination: string;
    reason: string;
    clinical_summary: string | null;
    priority: string;
    request_date: string;
    requested_by: string | null;
    created_at: string;
    queue_status: string;
}

interface Props {
    queue_entry: QueueEntry;
    patient: Patient;
    room_name: string | null;
    destinations: Record<string, string>;
    priorities: Record<string, string>;
    today: string;
    requester_name: string;
    prior_requests: PriorRequest[];
}

// ── Priority badge ─────────────────────────────────────────────────────────────
function priorityBadge(priority: string) {
    return priority === 'Urgent'
        ? 'bg-red-100 text-red-700 border border-red-200'
        : 'bg-gray-100 text-gray-600 border border-gray-200';
}

function statusBadge(status: string) {
    switch (status) {
        case 'Accepted':  return 'bg-green-100 text-green-700';
        case 'Rejected':  return 'bg-red-100 text-red-600';
        case 'Completed': return 'bg-teal-100 text-teal-700';
        case 'Cancelled': return 'bg-red-100 text-red-600';
        default:          return 'bg-yellow-100 text-yellow-700';
    }
}

// ── Prior requests list ────────────────────────────────────────────────────────
function PriorRequestsList({ requests }: { requests: PriorRequest[] }) {
    if (requests.length === 0) return null;

    return (
        <div className="bg-white rounded-xl border shadow-sm overflow-hidden mb-6">
            <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-700">Previous Consultation Requests This Encounter</h3>
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
                                <span className="text-sm font-medium text-gray-700">→ {req.destination}</span>
                                <span className="text-xs text-gray-500">{req.request_date}</span>
                                {req.requested_by && (
                                    <span className="text-xs text-gray-400">by {req.requested_by}</span>
                                )}
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${statusBadge(req.queue_status)}`}>
                                    {req.queue_status}
                                </span>
                            </div>
                        </div>
                        <p className="text-xs text-gray-600 bg-gray-50 rounded-md px-3 py-2 mt-1">
                            {req.reason}
                        </p>
                        {req.clinical_summary && (
                            <p className="text-xs text-gray-500 bg-blue-50 rounded-md px-3 py-2 mt-1">
                                <span className="font-medium text-blue-700">Clinical Summary:</span> {req.clinical_summary}
                            </p>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function ConsultationRequest({
    queue_entry, patient, room_name,
    destinations, priorities, today,
    requester_name: defaultRequesterName,
    prior_requests,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        destination:      '',
        reason:           '',
        clinical_summary: '',
        priority:         'Normal',
        request_date:     today,
        requester_name:   defaultRequesterName,
        signature_data:   '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${queue_entry.id}/consultation-request`, { preserveScroll: true });
    }

    return (
        <AppLayout title="Consultation Request">
            <Head title={`Consultation Request — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        📋
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Consultation Request</h1>
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

            {/* Prior requests */}
            <PriorRequestsList requests={prior_requests} />

            {/* Request form */}
            <form onSubmit={submit} className="space-y-6">

                {/* Destination & Priority */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Request Details</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {/* Destination */}
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Destination <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.destination}
                                onChange={(e) => setData('destination', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                            >
                                <option value="">Select destination…</option>
                                {Object.entries(destinations).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            {errors.destination && <p className="text-red-500 text-xs mt-1">{errors.destination}</p>}
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

                        {/* Request Date */}
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
                    </div>
                </div>

                {/* Reason */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Reason for Consultation <span className="text-red-500">*</span>
                    </label>
                    <textarea
                        rows={3}
                        value={data.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                        placeholder="Describe the reason for requesting this consultation…"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
                    />
                    {errors.reason && <p className="text-red-500 text-xs mt-1">{errors.reason}</p>}
                </div>

                {/* Clinical Summary */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Clinical Summary
                        <span className="ml-1 text-xs font-normal text-gray-400">(optional — relevant clinical context)</span>
                    </label>
                    <textarea
                        rows={4}
                        value={data.clinical_summary}
                        onChange={(e) => setData('clinical_summary', e.target.value)}
                        placeholder="e.g. Patient presents with chest pain and shortness of breath. Vitals: BP 160/100, HR 110, SpO₂ 92%. History of hypertension."
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
                    />
                    {errors.clinical_summary && <p className="text-red-500 text-xs mt-1">{errors.clinical_summary}</p>}
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
                        {data.destination && data.reason
                            ? `${data.destination} · ${data.priority} priority · ${data.request_date}`
                            : 'Select a destination and provide a reason to submit.'
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
                            disabled={processing || !data.destination || !data.reason}
                            className={data.priority === 'Urgent' ? 'bg-red-600 hover:bg-red-700' : ''}
                        >
                            {processing ? 'Submitting…' : `📋 Submit Request${data.priority === 'Urgent' ? ' (Urgent)' : ''}`}
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
