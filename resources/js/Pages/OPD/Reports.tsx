import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout, { Button, Input, StatCard } from '@/Layouts/AppLayout';

interface CountItem {
    label: string;
    count: number;
}

interface DiseaseReport {
    period: string;
    start_date: string;
    end_date: string;
    total_encounters: number;
    by_diagnosis: CountItem[];
    by_complaint: CountItem[];
}

interface DoctorReport {
    period: string;
    start_date: string;
    end_date: string;
    total_encounters: number;
    by_doctor: { doctor_name: string; count: number }[];
}

interface RoomReport {
    period: string;
    start_date: string;
    end_date: string;
    total_encounters: number;
    by_room: { room_name: string; room_code: string; total: number; completed: number; transferred: number }[];
}

interface LabReport {
    period: string;
    start_date: string;
    end_date: string;
    total_requests: number;
    completed: number;
    pending: number;
    urgent: number;
    by_panel: CountItem[];
    by_test: CountItem[];
}

interface MedicineReport {
    period: string;
    start_date: string;
    end_date: string;
    total_prescriptions: number;
    total_items: number;
    external: number;
    internal: number;
    by_medicine: CountItem[];
    by_category: CountItem[];
}

interface Props {
    disease: DiseaseReport;
    doctor: DoctorReport;
    room: RoomReport;
    lab: LabReport;
    medicine: MedicineReport;
    period: string;
    date: string;
}

type Tab = 'disease' | 'doctor' | 'room' | 'lab' | 'medicine';

const TABS: { key: Tab; label: string }[] = [
    { key: 'disease', label: 'Disease' },
    { key: 'doctor', label: 'Doctor' },
    { key: 'room', label: 'Room' },
    { key: 'lab', label: 'Laboratory' },
    { key: 'medicine', label: 'Medicine' },
];

function BarChart({ items, max }: { items: { label: string; count: number }[]; max: number }) {
    if (items.length === 0) {
        return <p className="text-sm text-gray-500 italic">No data for this period.</p>;
    }
    return (
        <div className="space-y-2">
            {items.map((item) => (
                <div key={item.label} className="flex items-center gap-3">
                    <span className="text-sm text-gray-700 w-48 truncate shrink-0" title={item.label}>
                        {item.label}
                    </span>
                    <div className="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                        <div
                            className="bg-green-600 h-full rounded-full transition-all"
                            style={{ width: `${max > 0 ? (item.count / max) * 100 : 0}%` }}
                        />
                    </div>
                    <span className="text-sm font-semibold text-green-700 w-12 text-right">{item.count}</span>
                </div>
            ))}
        </div>
    );
}

function TableChart({ items, max }: { items: { label: string; count: number }[]; max: number }) {
    if (items.length === 0) {
        return <p className="text-sm text-gray-500 italic">No data for this period.</p>;
    }
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b">
                        <th className="text-left py-2 font-medium text-gray-600">Item</th>
                        <th className="text-right py-2 font-medium text-gray-600 w-20">Count</th>
                        <th className="text-right py-2 font-medium text-gray-600 w-20">%</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map((item) => (
                        <tr key={item.label} className="border-b last:border-0">
                            <td className="py-2 text-gray-800 truncate max-w-xs" title={item.label}>{item.label}</td>
                            <td className="py-2 text-right font-semibold text-green-700">{item.count}</td>
                            <td className="py-2 text-right text-gray-500">
                                {max > 0 ? ((item.count / max) * 100).toFixed(1) : 0}%
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function OpdReports({ disease, doctor, room, lab, medicine, period, date }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('disease');

    const setPeriod = (p: string) => {
        router.get('/opd/reports', { period: p, date });
    };

    const setDate = (d: string) => {
        router.get('/opd/reports', { period, date: d });
    };

    return (
        <AppLayout title="OPD Reports">
            <Head title="OPD Reports" />

            {/* Period controls */}
            <div className="flex flex-wrap gap-3 mb-6 items-center">
                {(['daily', 'weekly', 'monthly'] as const).map((p) => (
                    <Button
                        key={p}
                        variant={period === p ? 'primary' : 'secondary'}
                        onClick={() => setPeriod(p)}
                    >
                        {p.charAt(0).toUpperCase() + p.slice(1)}
                    </Button>
                ))}
                <Input type="date" defaultValue={date} onChange={(e) => setDate(e.target.value)} className="w-auto" />
                <span className="text-sm text-gray-500">
                    {disease.start_date} to {disease.end_date}
                </span>
            </div>

            {/* Tabs */}
            <div className="flex gap-1 border-b mb-6 overflow-x-auto">
                {TABS.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => setActiveTab(tab.key)}
                        className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap ${
                            activeTab === tab.key
                                ? 'border-green-600 text-green-700'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Disease Stats */}
            {activeTab === 'disease' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <StatCard label="Total OPD Encounters" value={disease.total_encounters} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Top Diagnoses</h3>
                        <BarChart items={disease.by_diagnosis} max={disease.by_diagnosis[0]?.count ?? 0} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Top Chief Complaints</h3>
                        <BarChart items={disease.by_complaint} max={disease.by_complaint[0]?.count ?? 0} />
                    </div>
                </div>
            )}

            {/* Doctor Stats */}
            {activeTab === 'doctor' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <StatCard label="Total OPD Encounters" value={doctor.total_encounters} />
                        <StatCard label="Active Doctors/Nurses" value={doctor.by_doctor.length} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Encounters by Doctor / Nurse</h3>
                        <BarChart items={doctor.by_doctor.map((d) => ({ label: d.doctor_name, count: d.count }))} max={doctor.by_doctor[0]?.count ?? 0} />
                    </div>
                </div>
            )}

            {/* Room Stats */}
            {activeTab === 'room' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <StatCard label="Total OPD Encounters" value={room.total_encounters} />
                        <StatCard label="Active Rooms" value={room.by_room.length} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Room Utilization</h3>
                        {room.by_room.length === 0 ? (
                            <p className="text-sm text-gray-500 italic">No data for this period.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 font-medium text-gray-600">Room</th>
                                            <th className="text-left py-2 font-medium text-gray-600">Code</th>
                                            <th className="text-right py-2 font-medium text-gray-600">Total</th>
                                            <th className="text-right py-2 font-medium text-gray-600">Completed</th>
                                            <th className="text-right py-2 font-medium text-gray-600">Transferred</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {room.by_room.map((r) => (
                                            <tr key={r.room_code} className="border-b last:border-0">
                                                <td className="py-2 text-gray-800">{r.room_name}</td>
                                                <td className="py-2 text-gray-500">{r.room_code}</td>
                                                <td className="py-2 text-right font-semibold text-green-700">{r.total}</td>
                                                <td className="py-2 text-right text-gray-600">{r.completed}</td>
                                                <td className="py-2 text-right text-purple-600">{r.transferred}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Lab Stats */}
            {activeTab === 'lab' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <StatCard label="Total Lab Requests" value={lab.total_requests} />
                        <StatCard label="Completed" value={lab.completed} />
                        <StatCard label="Pending" value={lab.pending} />
                        <StatCard label="Urgent" value={lab.urgent} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Requests by Panel</h3>
                        <BarChart items={lab.by_panel} max={lab.by_panel[0]?.count ?? 0} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Most Requested Tests</h3>
                        <TableChart items={lab.by_test} max={lab.by_test[0]?.count ?? 0} />
                    </div>
                </div>
            )}

            {/* Medicine Stats */}
            {activeTab === 'medicine' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <StatCard label="Total Prescriptions" value={medicine.total_prescriptions} />
                        <StatCard label="Total Items" value={medicine.total_items} />
                        <StatCard label="Internal" value={medicine.internal} />
                        <StatCard label="External" value={medicine.external} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Most Prescribed Medicines</h3>
                        <BarChart items={medicine.by_medicine} max={medicine.by_medicine[0]?.count ?? 0} />
                    </div>

                    <div className="bg-white rounded-lg border p-5 shadow-sm">
                        <h3 className="font-semibold text-gray-800 mb-4">Prescriptions by Category</h3>
                        <TableChart items={medicine.by_category} max={medicine.by_category[0]?.count ?? 0} />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
