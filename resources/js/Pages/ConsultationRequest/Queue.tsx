import { Head, router, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface QueueEntry {
    id: number;
    patient_id: number;
    status: string;
    response_notes: string | null;
    created_at: string;
    accepted_at: string | null;
    rejected_at: string | null;
    completed_at: string | null;
    patient: {
        id: number;
        full_name: string;
        card_number: string;
        gender: string | null;
        age: number | null;
    };
    consultation_request: {
        id: number;
        destination: string;
        reason: string;
        priority: string;
        request_date: string;
        requested_by: string | null;
        requester_name: string | null;
    };
    updated_by: { full_name: string } | null;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface Props {
    queue: Paginated<QueueEntry>;
    stats: Record<string, number>;
    statuses: Record<string, string>;
    active_statuses: string[];
    transitions: Record<string, string[]>;
    destinations: Record<string, string>;
    filter_status: string | null;
    filter_destination: string | null;
    unread_count: number;
}

// ── Pagination ─────────────────────────────────────────────────────────────────
function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <div className="flex flex-wrap gap-1 mt-3 justify-end">
            {links.map((link, i) =>
                link.url ? (
                    <button
                        key={i}
                        onClick={() => router.get(link.url, {}, { preserveState: true })}
                        className={`px-3 py-1 text-xs rounded border ${link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={i}
                        className="px-3 py-1 text-xs rounded border bg-gray-100 text-gray-400 cursor-not-allowed"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                )
            )}
        </div>
    );
}

// ── Status badge ───────────────────────────────────────────────────────────────
function StatusBadge({ status }: { status: string }) {
    const colors: Record<string, string> = {
        Pending:   'bg-yellow-100 text-yellow-700',
        Accepted:  'bg-green-100 text-green-700',
        Rejected:  'bg-red-100 text-red-600',
        Completed: 'bg-teal-100 text-teal-700',
        Cancelled: 'bg-gray-100 text-gray-500',
    };
    return (
        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${colors[status] ?? 'bg-gray-100 text-gray-600'}`}>
            {status}
        </span>
    );
}

// ── Status update modal ────────────────────────────────────────────────────────
function StatusUpdateModal({
    entry,
    transitions,
    onClose,
}: {
    entry: QueueEntry;
    transitions: Record<string, string[]>;
    onClose: () => void;
}) {
    const { data, setData, post, processing } = useForm({
        status: '',
        response_notes: '',
    });

    const allowed = transitions[entry.status] ?? [];

    function submit(status: string) {
        setData('status', status);
        post(`/consultation-requests/queue/${entry.id}/status`, {
            onSuccess: () => onClose(),
            preserveScroll: true,
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-gray-800">Update Status</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <div className="bg-gray-50 rounded-lg p-4 mb-4 text-sm">
                    <p className="font-medium text-gray-800">{entry.patient.full_name}</p>
                    <p className="text-gray-500 text-xs mt-0.5">{entry.patient.card_number} · {entry.consultation_request.destination}</p>
                    <p className="text-gray-600 text-xs mt-1">Reason: {entry.consultation_request.reason}</p>
                </div>

                <div className="mb-4">
                    <label className="block text-xs font-medium text-gray-600 mb-1">Response Notes</label>
                    <textarea
                        rows={3}
                        value={data.response_notes}
                        onChange={(e) => setData('response_notes', e.target.value)}
                        placeholder="Optional notes about the decision…"
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
                    />
                </div>

                <div className="flex flex-wrap gap-3">
                    {allowed.map((status) => (
                        <button
                            key={status}
                            onClick={() => submit(status)}
                            disabled={processing}
                            className={`px-4 py-2 rounded-md text-sm font-semibold ${
                                status === 'Accepted'
                                    ? 'bg-green-600 hover:bg-green-700 text-white'
                                    : status === 'Rejected'
                                        ? 'bg-red-600 hover:bg-red-700 text-white'
                                        : status === 'Completed'
                                            ? 'bg-teal-600 hover:bg-teal-700 text-white'
                                            : 'bg-gray-400 hover:bg-gray-500 text-white'
                            }`}
                        >
                            {processing ? 'Saving…' : status}
                        </button>
                    ))}
                    <button
                        onClick={onClose}
                        className="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function ConsultationRequestQueue({
    queue, stats, statuses, active_statuses, transitions,
    destinations, filter_status, filter_destination, unread_count,
}: Props) {
    const [updatingEntry, setUpdatingEntry] = useState<QueueEntry | null>(null);

    function setFilter(key: 'status' | 'destination', value: string) {
        const params: Record<string, string> = {};
        if (key === 'status') {
            params.status = value;
            if (filter_destination) params.destination = filter_destination;
        } else {
            params.destination = value;
            if (filter_status) params.status = filter_status;
        }
        if (!value) {
            if (key === 'status' && filter_destination) params.destination = filter_destination;
            if (key === 'destination' && filter_status) params.status = filter_status;
        }
        router.get(route('consultation-requests.queue.index'), params, { preserveState: true });
    }

    return (
        <AppLayout title="Consultation Requests Queue">
            <Head title="Consultation Requests Queue" />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-xl font-bold">Consultation Requests Queue</h1>
                    <p className="text-green-100 text-sm mt-1">Manage incoming consultation requests from OPD</p>
                </div>
                {unread_count > 0 && (
                    <span className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-400 text-gray-900">
                        🔔 {unread_count} unread
                    </span>
                )}
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
                {Object.entries(stats).map(([status, count]) => (
                    <button
                        key={status}
                        onClick={() => setFilter('status', filter_status === status ? '' : status)}
                        className={`rounded-xl border p-4 text-center transition-colors ${
                            filter_status === status
                                ? 'bg-green-50 border-green-400 ring-2 ring-green-200'
                                : 'bg-white border-gray-200 hover:bg-gray-50'
                        }`}
                    >
                        <p className="text-2xl font-bold text-gray-800">{count}</p>
                        <p className="text-xs text-gray-500 mt-1">{status}</p>
                    </button>
                ))}
            </div>

            {/* Filters */}
            <div className="bg-white rounded-xl border shadow-sm p-4 mb-6 flex flex-wrap items-center gap-4">
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select
                        value={filter_status ?? ''}
                        onChange={(e) => setFilter('status', e.target.value)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        <option value="">All statuses</option>
                        {Object.keys(statuses).map((s) => (
                            <option key={s} value={s}>{s}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">Destination</label>
                    <select
                        value={filter_destination ?? ''}
                        onChange={(e) => setFilter('destination', e.target.value)}
                        className="rounded-md border border-gray-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        <option value="">All destinations</option>
                        {Object.entries(destinations).map(([key, label]) => (
                            <option key={key} value={key}>{label}</option>
                        ))}
                    </select>
                </div>
                {(filter_status || filter_destination) && (
                    <button
                        onClick={() => router.get(route('consultation-requests.queue.index'), {}, { preserveState: true })}
                        className="text-xs text-red-500 hover:underline mt-5"
                    >
                        Clear filters
                    </button>
                )}
            </div>

            {/* Queue table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                {queue.data.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-10">No consultation requests found.</p>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 border-b">
                                    <tr>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Patient</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Destination</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Reason</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Priority</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Requested By</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Status</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Time</th>
                                        <th className="text-right px-4 py-3 text-xs font-medium text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {queue.data.map((entry) => {
                                        const allowed = transitions[entry.status] ?? [];
                                        return (
                                            <tr key={entry.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-3">
                                                    <p className="font-medium text-gray-800">{entry.patient.full_name}</p>
                                                    <p className="text-xs text-gray-400 font-mono">{entry.patient.card_number}</p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="text-sm font-medium text-orange-700">{entry.consultation_request.destination}</span>
                                                </td>
                                                <td className="px-4 py-3 max-w-xs">
                                                    <p className="text-gray-600 text-xs truncate" title={entry.consultation_request.reason}>
                                                        {entry.consultation_request.reason}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                                                        ${entry.consultation_request.priority === 'Urgent'
                                                            ? 'bg-red-100 text-red-700'
                                                            : 'bg-gray-100 text-gray-600'}`}
                                                    >
                                                        {entry.consultation_request.priority}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-600">
                                                    {entry.consultation_request.requester_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge status={entry.status} />
                                                </td>
                                                <td className="px-4 py-3 text-xs text-gray-500">
                                                    {new Date(entry.created_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {allowed.length > 0 && (
                                                        <button
                                                            onClick={() => setUpdatingEntry(entry)}
                                                            className="text-sm text-green-700 font-medium hover:underline"
                                                        >
                                                            Update
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="px-4 pb-3">
                            <Pagination links={queue.links} />
                        </div>
                    </>
                )}
            </div>

            {/* Status update modal */}
            {updatingEntry && (
                <StatusUpdateModal
                    entry={updatingEntry}
                    transitions={transitions}
                    onClose={() => setUpdatingEntry(null)}
                />
            )}
        </AppLayout>
    );
}
