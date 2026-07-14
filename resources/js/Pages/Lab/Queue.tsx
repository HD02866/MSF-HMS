import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface Patient {
    id: number;
    full_name: string;
    card_number: string;
    gender: string | null;
    date_of_birth: string | null;
}

interface LabRequestInfo {
    id: number;
    priority: string;
    clinical_notes: string | null;
    request_date: string;
    requested_by: { full_name: string } | null;
    tests: { id: number; test_name: string }[];
}

interface QueueEntry {
    id: number;
    status: string;
    received_at: string | null;
    processing_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    patient: Patient;
    lab_request: LabRequestInfo;
    updated_by: { full_name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

interface Stats {
    Pending: number;
    Received: number;
    Processing: number;
    Completed: number;
    Cancelled: number;
    total: number;
}

interface Props {
    queue: Paginated<QueueEntry>;
    stats: Stats;
    statuses: Record<string, string>;
    active_statuses: string[];
    transitions: Record<string, string[]>;
    filter_status: string | null;
}

// ── Status visual config ───────────────────────────────────────────────────────
const STATUS_CONFIG: Record<string, { badge: string; tile: string }> = {
    Pending:    { badge: 'bg-yellow-100 text-yellow-800',  tile: 'border-yellow-300 bg-yellow-50 text-yellow-800' },
    Received:   { badge: 'bg-blue-100 text-blue-800',     tile: 'border-blue-300 bg-blue-50 text-blue-800' },
    Processing: { badge: 'bg-purple-100 text-purple-800', tile: 'border-purple-300 bg-purple-50 text-purple-800' },
    Completed:  { badge: 'bg-gray-100 text-gray-600',     tile: 'border-gray-300 bg-gray-50 text-gray-700' },
    Cancelled:  { badge: 'bg-red-100 text-red-600',       tile: 'border-red-300 bg-red-50 text-red-600' },
};

// ── Stat tile ─────────────────────────────────────────────────────────────────
function StatTile({ label, value, color, active, onClick }: {
    label: string;
    value: number;
    color: string;
    active?: boolean;
    onClick?: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-xl border-2 p-4 text-center transition-all w-full
                ${color}
                ${active ? 'ring-2 ring-offset-1 ring-green-500' : 'hover:shadow-md'}
            `}
        >
            <p className="text-3xl font-bold">{value}</p>
            <p className="text-xs font-medium mt-1 opacity-80">{label}</p>
        </button>
    );
}

// ── Status action buttons for a queue entry ───────────────────────────────────
function StatusActions({
    entry,
    transitions,
}: {
    entry: QueueEntry;
    transitions: Record<string, string[]>;
}) {
    const allowed = transitions[entry.status] ?? [];
    if (allowed.length === 0) return <span className="text-xs text-gray-400">—</span>;

    return (
        <div className="flex flex-wrap gap-1.5">
            {allowed.map((nextStatus) => (
                <StatusButton
                    key={nextStatus}
                    entryId={entry.id}
                    targetStatus={nextStatus}
                />
            ))}
        </div>
    );
}

function StatusButton({ entryId, targetStatus }: { entryId: number; targetStatus: string }) {
    const form = useForm({ status: targetStatus });

    const styleMap: Record<string, string> = {
        Received:   'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100',
        Processing: 'bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100',
        Completed:  'bg-green-50 text-green-700 border-green-200 hover:bg-green-100',
        Cancelled:  'bg-red-50 text-red-700 border-red-200 hover:bg-red-100',
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(`/lab/queue/${entryId}/status`, { preserveScroll: true });
            }}
        >
            <button
                type="submit"
                disabled={form.processing}
                className={`px-2.5 py-1 text-xs font-medium rounded border transition-colors disabled:opacity-50
                    ${styleMap[targetStatus] ?? 'bg-gray-50 text-gray-700 border-gray-200'}`}
            >
                {targetStatus}
            </button>
        </form>
    );
}

// ── Expanded row with tests + clinical notes ──────────────────────────────────
function ExpandedRow({ entry }: { entry: QueueEntry }) {
    return (
        <div className="px-4 py-3 bg-blue-50 border-t text-sm space-y-2">
            {/* Tests */}
            <div>
                <p className="text-xs font-semibold text-gray-600 mb-1.5">Requested Tests</p>
                <div className="flex flex-wrap gap-1.5">
                    {entry.lab_request.tests.map((t) => (
                        <span key={t.id} className="inline-flex px-2 py-0.5 rounded-md bg-white border border-blue-200 text-blue-800 text-xs">
                            {t.test_name}
                        </span>
                    ))}
                </div>
            </div>

            {/* Clinical notes */}
            {entry.lab_request.clinical_notes && (
                <div>
                    <p className="text-xs font-semibold text-gray-600 mb-1">Clinical Notes</p>
                    <p className="text-xs text-gray-700 bg-white rounded-md px-3 py-2 border border-blue-100">
                        {entry.lab_request.clinical_notes}
                    </p>
                </div>
            )}

            {/* Timeline stamps */}
            <div className="flex flex-wrap gap-4 text-xs text-gray-400 pt-1">
                {entry.received_at && (
                    <span>Received: {new Date(entry.received_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}</span>
                )}
                {entry.processing_at && (
                    <span>Processing: {new Date(entry.processing_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}</span>
                )}
                {entry.completed_at && (
                    <span>Completed: {new Date(entry.completed_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}</span>
                )}
                {entry.cancelled_at && (
                    <span className="text-red-400">Cancelled: {new Date(entry.cancelled_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}</span>
                )}
                {entry.updated_by && (
                    <span>Last updated by: {entry.updated_by.full_name}</span>
                )}
            </div>
        </div>
    );
}

// ── Pagination ────────────────────────────────────────────────────────────────
function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <div className="flex flex-wrap gap-1 mt-4 justify-end">
            {links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        className={`px-3 py-1 text-xs rounded border ${link.active
                            ? 'bg-green-600 text-white border-green-600'
                            : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}
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

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function LabQueue({
    queue, stats, statuses, active_statuses, transitions, filter_status,
}: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);

    const today = new Date().toLocaleDateString('en-GB', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });

    function applyFilter(status: string | null) {
        const params: Record<string, string> = {};
        if (status) params.status = status;
        router.get('/lab/queue', params, { preserveState: false });
    }

    function toggleExpand(id: number) {
        setExpandedId((prev) => (prev === id ? null : id));
    }

    return (
        <AppLayout title="Laboratory Queue">
            <Head title="Laboratory Queue" />

            {/* Header banner */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-5 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-xl font-bold">🔬 Laboratory Queue</h1>
                    <p className="text-green-100 text-sm mt-0.5">{today}</p>
                </div>
                <button
                    type="button"
                    onClick={() => applyFilter(null)}
                    className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                >
                    🔄 Refresh
                </button>
            </div>

            {/* Stat tiles — click to filter */}
            <div className="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-5">
                <StatTile
                    label="Total"
                    value={stats.total}
                    color="border-gray-300 bg-gray-50 text-gray-700"
                    active={filter_status === null}
                    onClick={() => applyFilter(null)}
                />
                {(Object.keys(statuses) as Array<keyof Stats>).map((s) => (
                    <StatTile
                        key={s}
                        label={s}
                        value={stats[s] as number}
                        color={STATUS_CONFIG[s]?.tile ?? 'border-gray-300 bg-gray-50 text-gray-700'}
                        active={filter_status === s}
                        onClick={() => applyFilter(s)}
                    />
                ))}
            </div>

            {/* Active filter strip */}
            {filter_status && (
                <div className="mb-4 flex items-center gap-3">
                    <span className="text-sm text-gray-600">
                        Showing: <strong>{filter_status}</strong>
                    </span>
                    <button
                        type="button"
                        onClick={() => applyFilter(null)}
                        className="text-xs text-gray-400 hover:text-red-500 hover:underline"
                    >
                        Clear filter ×
                    </button>
                </div>
            )}

            {/* Queue table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-700">Today's Requests</h3>
                    <span className="text-xs text-gray-500">
                        {queue.total} request{queue.total !== 1 ? 's' : ''}
                        {queue.from != null && ` — showing ${queue.from}–${queue.to}`}
                    </span>
                </div>

                {queue.data.length === 0 ? (
                    <div className="px-5 py-12 text-center text-gray-400 text-sm">
                        {filter_status
                            ? `No ${filter_status.toLowerCase()} requests today.`
                            : 'No lab requests received today.'}
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 border-b">
                                    <tr>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600 w-8"></th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Patient</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Card No</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Priority</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Tests</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Requested</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                        <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {queue.data.map((entry) => {
                                        const isExpanded = expandedId === entry.id;
                                        const isUrgent = entry.lab_request.priority === 'Urgent';

                                        return (
                                            <>
                                                <tr
                                                    key={entry.id}
                                                    className={`border-t hover:bg-gray-50 cursor-pointer
                                                        ${isUrgent ? 'border-l-4 border-l-red-400' : ''}
                                                    `}
                                                    onClick={() => toggleExpand(entry.id)}
                                                >
                                                    {/* Expand indicator */}
                                                    <td className="px-3 py-3 text-gray-300 text-xs">
                                                        {isExpanded ? '▼' : '▶'}
                                                    </td>
                                                    <td className="px-4 py-3 font-medium text-gray-800">
                                                        {entry.patient.full_name}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-gray-500 text-xs">
                                                        {entry.patient.card_number}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                                                            ${isUrgent
                                                                ? 'bg-red-100 text-red-700'
                                                                : 'bg-gray-100 text-gray-600'}`}
                                                        >
                                                            {isUrgent ? '🔴 ' : ''}
                                                            {entry.lab_request.priority}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-600 text-xs">
                                                        {entry.lab_request.tests.length} test{entry.lab_request.tests.length !== 1 ? 's' : ''}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-500 text-xs">
                                                        {new Date(entry.created_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                                                        {entry.lab_request.requested_by && (
                                                            <span className="block text-gray-400">
                                                                by {entry.lab_request.requested_by.full_name}
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                                            ${STATUS_CONFIG[entry.status]?.badge ?? 'bg-gray-100 text-gray-600'}`}
                                                        >
                                                            {entry.status}
                                                        </span>
                                                    </td>
                                    <td className="px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex flex-col items-end gap-1.5">
                                                            <StatusActions entry={entry} transitions={transitions} />
                                                            {/* Show "Enter Results" for Processing entries */}
                                                            {entry.status === 'Processing' && (
                                                                <a
                                                                    href={`/lab/queue/${entry.id}/results`}
                                                                    className="px-2.5 py-1 text-xs font-medium rounded border bg-teal-50 text-teal-700 border-teal-200 hover:bg-teal-100"
                                                                >
                                                                    📝 Enter Results
                                                                </a>
                                                            )}
                                                            {/* Allow viewing/editing results for Completed too */}
                                                            {entry.status === 'Completed' && (
                                                                <a
                                                                    href={`/lab/queue/${entry.id}/results`}
                                                                    className="px-2.5 py-1 text-xs font-medium rounded border bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100"
                                                                >
                                                                    👁 View Results
                                                                </a>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>

                                                {/* Expanded detail row */}
                                                {isExpanded && (
                                                    <tr key={`${entry.id}-expanded`} className="border-t-0">
                                                        <td colSpan={8} className="p-0">
                                                            <ExpandedRow entry={entry} />
                                                        </td>
                                                    </tr>
                                                )}
                                            </>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <div className="px-5 pb-4">
                            <Pagination links={queue.links} />
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
