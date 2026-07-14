import { Head, router } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { useState, useCallback } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface Patient {
    id: number;
    full_name: string;
    gender: string | null;
    date_of_birth: string | null;
    age: number | null;
    card_number: string;
    patient_type: string | null;
}

interface ClinicalNote {
    id: number;
    chief_complaint: string | null;
    diagnosis: string | null;
    treatment_plan: string | null;
    creator: { full_name: string } | null;
}

interface RegisterRow {
    id: number;
    queue_number: number;
    status: string;
    arrived_at: string;
    patient: Patient;
    room: { room_name: string } | null;
    clinical_note: ClinicalNote | null;
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

interface Summary {
    completed: number;
    transferred: number;
    total: number;
}

interface Filters {
    period: string;
    date: string;
    room_id: string;
    patient_type_id: string;
    doctor_id: string;
    status: string;
}

interface Room {
    id: number;
    room_name: string;
    room_code: string;
}

interface PatientType {
    id: number;
    name: string;
}

interface Doctor {
    id: number;
    full_name: string;
}

interface Props {
    registers: Paginated<RegisterRow>;
    summary: Summary;
    filters: Filters;
    rooms: Room[];
    patient_types: PatientType[];
    doctors: Doctor[];
    statuses: Record<string, string>;
}

// ── Summary Card ───────────────────────────────────────────────────────────────
function SummaryCard({ label, value, colorClass }: { label: string; value: number; colorClass: string }) {
    return (
        <div className={`rounded-lg border p-3 text-center ${colorClass}`}>
            <p className="text-2xl font-bold">{value}</p>
            <p className="text-xs font-medium mt-0.5 opacity-80">{label}</p>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function OpdRegister({
    registers, summary, filters, rooms, patient_types, doctors, statuses,
}: Props) {
    const [localFilters, setLocalFilters] = useState(filters);

    const applyFilters = useCallback((newFilters: Partial<Filters>) => {
        const merged = { ...localFilters, ...newFilters };
        setLocalFilters(merged);
        router.get('/opd/register', merged as Record<string, string>, { preserveState: true, replace: true });
    }, [localFilters]);

    function exportUrl(type: 'excel' | 'pdf') {
        const params = new URLSearchParams();
        Object.entries(localFilters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });
        return `/opd/register/export/${type}?${params.toString()}`;
    }

    function handlePrint() {
        window.print();
    }

    return (
        <AppLayout title="OPD Register">
            <Head title="OPD Register" />

            {/* Summary Cards */}
            <div className="grid grid-cols-3 gap-3 mb-5 no-print">
                <SummaryCard label="Total" value={summary.total} colorClass="bg-gray-50 text-gray-700" />
                <SummaryCard label="Completed" value={summary.completed} colorClass="bg-green-50 text-green-800" />
                <SummaryCard label="Transferred" value={summary.transferred} colorClass="bg-purple-50 text-purple-800" />
            </div>

            {/* Filters + Actions */}
            <div className="bg-white rounded-xl border shadow-sm p-4 mb-5 no-print">
                <div className="flex flex-wrap gap-3 items-end">
                    {/* Period */}
                    <div className="flex-1 min-w-[130px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Period</label>
                        <Select value={localFilters.period}
                            onChange={(e) => applyFilters({ period: e.target.value })}>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </Select>
                    </div>

                    {/* Date */}
                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                        <Input type="date" value={localFilters.date}
                            onChange={(e) => applyFilters({ date: e.target.value })} />
                    </div>

                    {/* Room */}
                    <div className="flex-1 min-w-[150px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Room</label>
                        <Select value={localFilters.room_id}
                            onChange={(e) => applyFilters({ room_id: e.target.value })}>
                            <option value="">All Rooms</option>
                            {rooms.map((r) => (
                                <option key={r.id} value={r.id}>{r.room_name}</option>
                            ))}
                        </Select>
                    </div>

                    {/* Patient Type */}
                    <div className="flex-1 min-w-[150px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Patient Type</label>
                        <Select value={localFilters.patient_type_id}
                            onChange={(e) => applyFilters({ patient_type_id: e.target.value })}>
                            <option value="">All Types</option>
                            {patient_types.map((pt) => (
                                <option key={pt.id} value={pt.id}>{pt.name}</option>
                            ))}
                        </Select>
                    </div>

                    {/* Doctor/Nurse */}
                    <div className="flex-1 min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Doctor/Nurse</label>
                        <Select value={localFilters.doctor_id}
                            onChange={(e) => applyFilters({ doctor_id: e.target.value })}>
                            <option value="">All</option>
                            {doctors.map((d) => (
                                <option key={d.id} value={d.id}>{d.full_name}</option>
                            ))}
                        </Select>
                    </div>

                    {/* Status */}
                    <div className="flex-1 min-w-[130px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <Select value={localFilters.status}
                            onChange={(e) => applyFilters({ status: e.target.value })}>
                            <option value="">Completed & Transferred</option>
                            <option value="Completed">Completed Only</option>
                            <option value="Transferred">Transferred Only</option>
                        </Select>
                    </div>

                    {/* Export Actions */}
                    <div className="flex gap-2 shrink-0">
                        <a href={exportUrl('excel')}
                            className="px-4 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">
                            Export Excel
                        </a>
                        <a href={exportUrl('pdf')}
                            className="px-4 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">
                            Export PDF
                        </a>
                        <Button type="button" variant="secondary" onClick={handlePrint}>
                            Print
                        </Button>
                    </div>
                </div>
            </div>

            {/* Register Table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between no-print">
                    <h3 className="text-sm font-semibold text-gray-700">OPD Consultation Register</h3>
                    <span className="text-xs text-gray-500">
                        {registers.total} record{registers.total !== 1 ? 's' : ''}
                        {registers.from != null && ` — showing ${registers.from}–${registers.to}`}
                    </span>
                </div>

                {registers.data.length === 0 ? (
                    <div className="px-5 py-12 text-center text-gray-400 text-sm">
                        No completed consultations found for the selected filters.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b">
                                <tr>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">#</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Date</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Room</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Queue</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Card No.</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Patient Name</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Sex</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Age</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Type</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Chief Complaint</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Diagnosis</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Doctor/Nurse</th>
                                    <th className="text-left px-3 py-3 font-medium text-gray-600">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {registers.data.map((row, index) => (
                                    <tr key={row.id} className="border-t hover:bg-gray-50">
                                        <td className="px-3 py-2.5 text-gray-400 text-xs">{(registers.from ?? 1) + index}</td>
                                        <td className="px-3 py-2.5 text-gray-700 whitespace-nowrap">
                                            {new Date(row.arrived_at).toLocaleDateString('en-GB')}
                                        </td>
                                        <td className="px-3 py-2.5 text-gray-700">{row.room?.room_name ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-gray-700">#{row.queue_number}</td>
                                        <td className="px-3 py-2.5 font-mono text-gray-600 text-xs">{row.patient.card_number}</td>
                                        <td className="px-3 py-2.5 font-medium text-gray-800">{row.patient.full_name}</td>
                                        <td className="px-3 py-2.5 text-gray-600">{row.patient.gender ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-gray-600">{row.patient.age ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-gray-600">{row.patient.patient_type ?? '—'}</td>
                                        <td className="px-3 py-2.5 text-gray-600 text-xs max-w-[160px] truncate" title={row.clinical_note?.chief_complaint ?? ''}>
                                            {row.clinical_note?.chief_complaint ?? '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-gray-600 text-xs max-w-[160px] truncate" title={row.clinical_note?.diagnosis ?? ''}>
                                            {row.clinical_note?.diagnosis ?? '—'}
                                        </td>
                                        <td className="px-3 py-2.5 text-gray-600">{row.clinical_note?.creator?.full_name ?? '—'}</td>
                                        <td className="px-3 py-2.5">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                row.status === 'Completed'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-purple-100 text-purple-800'
                                            }`}>
                                                {row.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {registers.links.length > 3 && (
                    <div className="px-5 py-3 border-t bg-gray-50 flex flex-wrap gap-1 justify-center no-print">
                        {registers.links.map((link, i) => (
                            link.url ? (
                                <button key={i} onClick={() => router.get(link.url!)}
                                    className={`px-3 py-1.5 text-xs rounded border ${link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ) : (
                                <span key={i}
                                    className="px-3 py-1.5 text-xs rounded border bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed"
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            )
                        ))}
                    </div>
                )}
            </div>

            {/* Print-only header */}
            <div className="hidden print:block print:mt-4">
                <div className="text-center border-b-2 border-black pb-2 mb-4">
                    <h1 className="text-lg font-bold">Metahara Sugar Factory Hospital</h1>
                    <p className="text-sm">OPD — Consultation Register</p>
                    <p className="text-xs text-gray-500 mt-1">
                        Period: {localFilters.period === 'daily' ? 'Daily' : localFilters.period === 'weekly' ? 'Weekly' : 'Monthly'}
                        {localFilters.date ? ` — ${localFilters.date}` : ''}
                        {localFilters.status ? ` | Status: ${localFilters.status}` : ''}
                        &nbsp;|&nbsp; Generated: {new Date().toLocaleDateString('en-GB')}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
