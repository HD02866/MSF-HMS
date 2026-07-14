import { Head, router, useForm } from '@inertiajs/react';
import AppLayout, { StatCard, Button } from '@/Layouts/AppLayout';
import { useState } from 'react';

interface QueueEntry {
    id: number;
    patient_id: number;
    status: string;
    created_at: string;
    patient: { id: number; full_name: string; card_number: string; gender: string | null; date_of_birth: string | null } | null;
    pharmacy_request: {
        id: number;
        prescriber_name: string | null;
        is_external: boolean;
        clinical_notes: string | null;
        items: { id: number; medicine_name: string; dosage: string | null; frequency: string | null; duration: string | null; quantity: number }[];
        prescribed_by: { id: number; full_name: string } | null;
    } | null;
    updated_by: { id: number; full_name: string } | null;
}

interface Props {
    queue: { data: QueueEntry[]; current_page: number; last_page: number; total: number; per_page: number };
    stats: { Pending: number; Dispensed: number; Cancelled: number; total: number };
    current_status: string | null;
}

const STATUS_CONFIG: Record<string, { badge: string; tile: string }> = {
    Pending:   { badge: 'bg-yellow-100 text-yellow-700 border border-yellow-200', tile: 'border-yellow-300 bg-yellow-50' },
    Dispensed: { badge: 'bg-teal-100 text-teal-700 border border-teal-200', tile: 'border-teal-300 bg-teal-50' },
    Cancelled: { badge: 'bg-red-100 text-red-600 border border-red-200', tile: 'border-red-300 bg-red-50' },
};

function ExpandedRow({ entry }: { entry: QueueEntry }) {
    const req = entry.pharmacy_request;
    return (
        <div className="px-5 py-4 bg-gray-50 border-t">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p className="text-xs font-semibold text-gray-500 mb-1">Medicines</p>
                    {req?.items.map((item) => (
                        <div key={item.id} className="text-sm text-gray-700 py-0.5">
                            {item.medicine_name}
                            {item.dosage && <span className="text-gray-400 ml-1">— {item.dosage}</span>}
                            {item.frequency && <span className="text-gray-400 ml-1">· {item.frequency}</span>}
                            {item.duration && <span className="text-gray-400 ml-1">· {item.duration}</span>}
                            <span className="text-gray-400 ml-1">× {item.quantity}</span>
                        </div>
                    ))}
                    {!req?.items.length && <p className="text-sm text-gray-400 italic">No items</p>}
                </div>
                <div>
                    {req?.clinical_notes && (
                        <div className="mb-2">
                            <p className="text-xs font-semibold text-gray-500 mb-1">Clinical Notes</p>
                            <p className="text-sm text-gray-600 bg-white rounded-md p-2 border">{req.clinical_notes}</p>
                        </div>
                    )}
                    <div className="text-xs text-gray-400 space-y-0.5">
                        <p>Prescribed by: {req?.prescriber_name || req?.prescribed_by?.full_name || '—'}</p>
                        {req?.is_external && <p className="text-orange-600 font-medium">External Prescription</p>}
                        {entry.updated_by && <p>Last updated by: {entry.updated_by.full_name}</p>}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function Queue({ queue, stats, current_status }: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const { post, processing } = useForm();

    function handleStatus(id: number, status: string) {
        post(`/pharmacy/queue/${id}/status`, {
            data: { status },
            preserveScroll: true,
        });
    }

    function filterByStatus(status: string | null) {
        const url = status ? `/pharmacy/queue?status=${status}` : '/pharmacy/queue';
        router.get(url);
    }

    return (
        <AppLayout title="Pharmacy Queue">
            <Head title="Pharmacy Queue" />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        💊
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Pharmacy Queue</h1>
                        <p className="text-green-100 text-sm mt-0.5">{queue.total} prescription{queue.total !== 1 ? 's' : ''} total</p>
                    </div>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                {(['Pending', 'Dispensed', 'Cancelled', 'total'] as const).map((key) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => filterByStatus(key === 'total' ? null : key)}
                        className={`text-left rounded-xl border-2 p-4 transition-colors ${
                            (key === 'total' && !current_status) || current_status === key
                                ? 'border-green-500 bg-green-50'
                                : 'border-gray-200 bg-white hover:border-green-300'
                        }`}
                    >
                        <p className="text-xs text-gray-500">{key === 'total' ? 'Total' : key}</p>
                        <p className="text-2xl font-bold text-green-700 mt-1">{key === 'total' ? stats.total : stats[key]}</p>
                    </button>
                ))}
            </div>

            {/* Queue table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                {queue.data.length === 0 ? (
                    <div className="px-5 py-12 text-center text-sm text-gray-400">
                        No prescriptions found.
                    </div>
                ) : (
                    <div className="divide-y">
                        {queue.data.map((entry) => (
                            <div key={entry.id}>
                                <div
                                    className="px-5 py-3 flex items-center justify-between gap-4 cursor-pointer hover:bg-gray-50"
                                    onClick={() => setExpandedId(expandedId === entry.id ? null : entry.id)}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-medium text-gray-800">
                                                {entry.patient?.full_name ?? '—'}
                                            </span>
                                            <span className="font-mono text-xs text-gray-400">{entry.patient?.card_number}</span>
                                            {entry.pharmacy_request?.is_external && (
                                                <span className="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">External</span>
                                            )}
                                        </div>
                                        <p className="text-xs text-gray-500 mt-0.5 truncate">
                                            {entry.pharmacy_request?.items.map((i) => i.medicine_name).join(', ') || 'No items'}
                                        </p>
                                    </div>
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 ${STATUS_CONFIG[entry.status]?.badge ?? ''}`}>
                                        {entry.status}
                                    </span>
                                </div>

                                {expandedId === entry.id && (
                                    <>
                                        <ExpandedRow entry={entry} />
                                        {entry.status === 'Pending' && (
                                            <div className="px-5 py-3 bg-gray-50 border-t flex items-center gap-3">
                                                <Button
                                                    onClick={() => handleStatus(entry.id, 'Dispensed')}
                                                    disabled={processing}
                                                    className="bg-teal-600 hover:bg-teal-700 text-white"
                                                >
                                                    ✓ Mark Dispensed
                                                </Button>
                                                <Button
                                                    onClick={() => handleStatus(entry.id, 'Cancelled')}
                                                    disabled={processing}
                                                    variant="danger"
                                                >
                                                    Cancel
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {queue.last_page > 1 && (
                    <div className="px-5 py-3 border-t flex items-center justify-between text-xs text-gray-500">
                        <span>Page {queue.current_page} of {queue.last_page}</span>
                        <div className="flex gap-2">
                            {queue.current_page > 1 && (
                                <button
                                    onClick={() => router.get(`/pharmacy/queue?page=${queue.current_page - 1}`)}
                                    className="px-3 py-1 rounded border hover:bg-gray-50"
                                >
                                    ← Prev
                                </button>
                            )}
                            {queue.current_page < queue.last_page && (
                                <button
                                    onClick={() => router.get(`/pharmacy/queue?page=${queue.current_page + 1}`)}
                                    className="px-3 py-1 rounded border hover:bg-gray-50"
                                >
                                    Next →
                                </button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
