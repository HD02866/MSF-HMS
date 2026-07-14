import { Head, Link } from '@inertiajs/react';
import AppLayout, { StatCard } from '@/Layouts/AppLayout';
import { formatTime } from '@/lib/utils';

interface DashboardProps {
    stats: {
        total_visits: number;
        by_patient_type: Record<string, number>;
        by_room: Record<string, number>;
        recent_assignments: Array<{
            time: string;
            patient: string;
            card_number: string;
            room: string;
            assigned_by: string;
        }>;
    };
}

export default function CardOfficerDashboard({ stats }: DashboardProps) {
    const today = new Date().toLocaleDateString('en-GB', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

    return (
        <AppLayout title="Card Officer Dashboard">
            <Head title="Card Officer Dashboard" />

            {/* Welcome banner */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-xl font-bold">Good day, Card Officer</h1>
                    <p className="text-green-100 text-sm mt-0.5">{today}</p>
                </div>
                <Link
                    href="/patients/search"
                    className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                >
                    Patient Search →
                </Link>
            </div>

            {/* Top stat cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard label="Today's Total Visits" value={stats.total_visits} />
                {Object.entries(stats.by_patient_type).slice(0, 3).map(([type, count]) => (
                    <StatCard key={type} label={type} value={count} />
                ))}
            </div>

            {/* Visits by type + Room utilization */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-lg border p-4">
                    <h3 className="font-semibold text-gray-800 mb-4">Visits by Patient Type</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {Object.entries(stats.by_patient_type).map(([type, count]) => (
                            <div key={type} className="flex justify-between text-sm border-b pb-2">
                                <span className="text-gray-600">{type}</span>
                                <span className="font-semibold text-green-700">{count}</span>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-lg border p-4">
                    <h3 className="font-semibold text-gray-800 mb-4">Room Utilization</h3>
                    <div className="grid grid-cols-2 gap-3">
                        {Object.entries(stats.by_room).map(([room, count]) => (
                            <div key={room} className="flex justify-between text-sm border-b pb-2">
                                <span className="text-gray-600">{room}</span>
                                <span className="font-semibold text-green-700">{count}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Recent Assignments */}
            <div className="mt-6 bg-white rounded-lg border overflow-hidden">
                <div className="px-4 py-3 border-b font-semibold text-gray-800">Recent Assignments</div>
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-gray-600">
                        <tr>
                            <th className="text-left px-4 py-2">Time</th>
                            <th className="text-left px-4 py-2">Patient</th>
                            <th className="text-left px-4 py-2">Card No</th>
                            <th className="text-left px-4 py-2">Room</th>
                            <th className="text-left px-4 py-2">Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        {stats.recent_assignments.map((row, i) => (
                            <tr key={i} className="border-t">
                                <td className="px-4 py-2">{formatTime(row.time)}</td>
                                <td className="px-4 py-2">{row.patient}</td>
                                <td className="px-4 py-2 font-mono">{row.card_number}</td>
                                <td className="px-4 py-2">{row.room}</td>
                                <td className="px-4 py-2">{row.assigned_by}</td>
                            </tr>
                        ))}
                        {stats.recent_assignments.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center text-gray-400">
                                    No assignments today
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Navigation shortcuts */}
            <div className="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link
                    href="/patients/create"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">➕</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">New Patient</p>
                </Link>
                <Link
                    href="/visits/assign"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">🚪</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Assign Room</p>
                </Link>
                <Link
                    href="/visits/register"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">📋</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Visit Register</p>
                </Link>
                <Link
                    href="/reports"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">📊</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Reports</p>
                </Link>
            </div>
        </AppLayout>
    );
}
