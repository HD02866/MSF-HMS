import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface ReferralRecord {
    id: number;
    destination: string;
    reason: string;
    diagnosis: string;
    doctor_nurse_name: string;
    date: string;
    created_at: string;
    patient: { id: number; full_name: string; card_number: string; gender: string | null; age: number | null } | null;
    requested_by: { full_name: string } | null;
    opd_queue: { queue_number: number; room: { room_name: string } | null } | null;
}

interface SickLeaveRecord {
    id: number;
    employee_name: string;
    days: number;
    start_date: string;
    end_date: string;
    diagnosis: string;
    recommendation: string | null;
    created_at: string;
    patient: { id: number; full_name: string; card_number: string; gender: string | null; age: number | null } | null;
    requested_by: { full_name: string } | null;
    opd_queue: { queue_number: number; room: { room_name: string } | null } | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    referrals: Paginated<ReferralRecord>;
    sick_leaves: Paginated<SickLeaveRecord>;
    active_tab: string;
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function ReferralSickLeaveList({ referrals, sick_leaves, active_tab }: Props) {
    const [tab, setTab] = useState<'referrals' | 'sick_leaves'>(active_tab as 'referrals' | 'sick_leaves');

    return (
        <AppLayout title="Referrals & Sick Leaves">
            <Head title="Referrals & Sick Leaves" />

            {/* Header */}
            <div className="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <div className="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-gray-800">Referrals & Sick Leaves</h1>
                        <p className="text-sm text-gray-500 mt-1">View all referrals and sick leaves created from OPD consultations.</p>
                    </div>
                    <div className="flex items-center gap-4 text-sm">
                        <div className="bg-teal-50 text-teal-700 px-3 py-1.5 rounded-lg font-medium">
                            {referrals.total} Referral{referrals.total !== 1 ? 's' : ''}
                        </div>
                        <div className="bg-amber-50 text-amber-700 px-3 py-1.5 rounded-lg font-medium">
                            {sick_leaves.total} Sick Leave{sick_leaves.total !== 1 ? 's' : ''}
                        </div>
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 mb-6">
                <button
                    onClick={() => setTab('referrals')}
                    className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
                        tab === 'referrals'
                            ? 'bg-teal-600 text-white shadow-sm'
                            : 'bg-white border text-gray-600 hover:bg-gray-50'
                    }`}
                >
                    🏥 Referrals ({referrals.total})
                </button>
                <button
                    onClick={() => setTab('sick_leaves')}
                    className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
                        tab === 'sick_leaves'
                            ? 'bg-amber-600 text-white shadow-sm'
                            : 'bg-white border text-gray-600 hover:bg-gray-50'
                    }`}
                >
                    📄 Sick Leaves ({sick_leaves.total})
                </button>
            </div>

            {/* Referrals Table */}
            {tab === 'referrals' && (
                <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                    {referrals.data.length === 0 ? (
                        <p className="text-center text-gray-400 py-8">No referrals found.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Date</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Patient</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Destination</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Diagnosis</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Doctor/Nurse</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Room</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {referrals.data.map((r) => (
                                        <tr key={r.id} className="border-b last:border-0 hover:bg-gray-50">
                                            <td className="px-4 py-3 text-gray-700">{r.date}</td>
                                            <td className="px-4 py-3">
                                                <p className="font-medium text-gray-800">{r.patient?.full_name ?? '—'}</p>
                                                <p className="text-xs text-gray-500">{r.patient?.card_number ?? ''}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-700">
                                                    {r.destination}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-gray-600 max-w-[200px] truncate">{r.diagnosis}</td>
                                            <td className="px-4 py-3 text-gray-600">{r.doctor_nurse_name}</td>
                                            <td className="px-4 py-3 text-gray-600 text-xs">{r.opd_queue?.room?.room_name ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-600 text-xs">{r.requested_by?.full_name ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {/* Sick Leaves Table */}
            {tab === 'sick_leaves' && (
                <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                    {sick_leaves.data.length === 0 ? (
                        <p className="text-center text-gray-400 py-8">No sick leaves found.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-gray-50">
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Employee</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Patient</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Days</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Start Date</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">End Date</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Diagnosis</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Room</th>
                                        <th className="text-left px-4 py-3 text-xs font-medium text-gray-500">Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sick_leaves.data.map((s) => (
                                        <tr key={s.id} className="border-b last:border-0 hover:bg-gray-50">
                                            <td className="px-4 py-3 font-medium text-gray-800">{s.employee_name}</td>
                                            <td className="px-4 py-3">
                                                <p className="font-medium text-gray-800">{s.patient?.full_name ?? '—'}</p>
                                                <p className="text-xs text-gray-500">{s.patient?.card_number ?? ''}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-semibold text-amber-700">{s.days} day{s.days !== 1 ? 's' : ''}</span>
                                            </td>
                                            <td className="px-4 py-3 text-gray-600">{s.start_date}</td>
                                            <td className="px-4 py-3 text-gray-600">{s.end_date}</td>
                                            <td className="px-4 py-3 text-gray-600 max-w-[200px] truncate">{s.diagnosis}</td>
                                            <td className="px-4 py-3 text-gray-600 text-xs">{s.opd_queue?.room?.room_name ?? '—'}</td>
                                            <td className="px-4 py-3 text-gray-600 text-xs">{s.requested_by?.full_name ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
