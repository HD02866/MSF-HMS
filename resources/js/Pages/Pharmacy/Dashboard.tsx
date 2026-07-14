import { Head, Link } from '@inertiajs/react';
import AppLayout, { StatCard } from '@/Layouts/AppLayout';

interface QueueEntry {
    id: number;
    patient: { id: number; full_name: string; card_number: string } | null;
    pharmacy_request: {
        id: number;
        prescriber_name: string | null;
        is_external: boolean;
        items: { medicine_name: string }[];
    } | null;
    status: string;
    created_at: string;
}

interface Props {
    stats: { pending: number; dispensed: number; cancelled: number; total: number };
    recent_queue: QueueEntry[];
    inventory: { total: number; low_stock: number; out_of_stock: number };
}

const STATUS_COLORS: Record<string, string> = {
    Pending:   'bg-yellow-100 text-yellow-700',
    Dispensed: 'bg-teal-100 text-teal-700',
    Cancelled: 'bg-red-100 text-red-600',
};

export default function Dashboard({ stats, recent_queue, inventory }: Props) {
    return (
        <AppLayout title="Pharmacy Dashboard">
            <Head title="Pharmacy Dashboard" />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        💊
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Pharmacy Dashboard</h1>
                        <p className="text-green-100 text-sm mt-0.5">Medicine dispensing &amp; inventory overview</p>
                    </div>
                </div>
                <Link
                    href="/pharmacy/queue"
                    className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                >
                    View Queue →
                </Link>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <StatCard label="Pending" value={stats.pending} />
                <StatCard label="Dispensed Today" value={stats.dispensed} />
                <StatCard label="Cancelled" value={stats.cancelled} />
                <StatCard label="Total Today" value={stats.total} />
            </div>

            {/* Inventory summary */}
            <div className="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <h3 className="font-semibold text-gray-800 mb-4">Inventory Summary</h3>
                <div className="grid grid-cols-3 gap-4">
                    <div className="text-center p-3 bg-gray-50 rounded-lg">
                        <p className="text-2xl font-bold text-green-700">{inventory.total}</p>
                        <p className="text-xs text-gray-500 mt-1">Active Medicines</p>
                    </div>
                    <div className="text-center p-3 bg-yellow-50 rounded-lg">
                        <p className="text-2xl font-bold text-yellow-600">{inventory.low_stock}</p>
                        <p className="text-xs text-gray-500 mt-1">Low Stock</p>
                    </div>
                    <div className="text-center p-3 bg-red-50 rounded-lg">
                        <p className="text-2xl font-bold text-red-600">{inventory.out_of_stock}</p>
                        <p className="text-xs text-gray-500 mt-1">Out of Stock</p>
                    </div>
                </div>
            </div>

            {/* Recent prescriptions */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50">
                    <h3 className="font-semibold text-gray-800">Recent Prescriptions</h3>
                </div>
                {recent_queue.length === 0 ? (
                    <div className="px-5 py-8 text-center text-sm text-gray-400">
                        No prescriptions today.
                    </div>
                ) : (
                    <div className="divide-y">
                        {recent_queue.map((entry) => (
                            <div key={entry.id} className="px-5 py-3 flex items-center justify-between gap-4">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-800 truncate">
                                        {entry.patient?.full_name ?? '—'}
                                        <span className="font-mono text-xs text-gray-400 ml-2">{entry.patient?.card_number}</span>
                                    </p>
                                    <p className="text-xs text-gray-500 mt-0.5 truncate">
                                        {entry.pharmacy_request?.items.map((i) => i.medicine_name).join(', ') || '—'}
                                        {entry.pharmacy_request?.prescriber_name && (
                                            <span className="ml-2">by {entry.pharmacy_request.prescriber_name}</span>
                                        )}
                                    </p>
                                </div>
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold shrink-0 ${STATUS_COLORS[entry.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                    {entry.status}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
