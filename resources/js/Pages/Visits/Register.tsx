import { Head, router } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { formatTime } from '@/lib/utils';

interface Props {
    visits: { data: any[] };
    rooms: Array<{ id: number; room_name: string }>;
    patientTypes: Array<{ id: number; name: string }>;
    filters: Record<string, string>;
}

export default function VisitRegister({ visits, rooms, patientTypes, filters }: Props) {
    const filter = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        router.get('/visits/register', Object.fromEntries(form.entries()), { preserveState: true });
    };

    return (
        <AppLayout title="Visit Register">
            <Head title="Visit Register" />

            <form onSubmit={filter} className="bg-white rounded-lg border p-4 mb-6 flex flex-wrap gap-3 items-end">
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Date</label>
                    <Input type="date" name="visit_date" defaultValue={filters.visit_date ?? new Date().toISOString().substring(0, 10)} />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Room</label>
                    <Select name="room_id" defaultValue={filters.room_id ?? ''}>
                        <option value="">All Rooms</option>
                        {rooms.map((r) => <option key={r.id} value={r.id}>{r.room_name}</option>)}
                    </Select>
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">Patient Type</label>
                    <Select name="patient_type_id" defaultValue={filters.patient_type_id ?? ''}>
                        <option value="">All Types</option>
                        {patientTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </Select>
                </div>
                <Button type="submit">Filter</Button>
                <a
                    href={`/visits/register?export=csv&visit_date=${filters.visit_date ?? new Date().toISOString().substring(0, 10)}${filters.room_id ? `&room_id=${filters.room_id}` : ''}${filters.patient_type_id ? `&patient_type_id=${filters.patient_type_id}` : ''}`}
                    className="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-yellow-400 text-gray-900 hover:bg-yellow-500"
                >
                    Export CSV
                </a>
            </form>

            <div className="bg-white rounded-lg border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="text-left px-4 py-3">Time</th>
                            <th className="text-left px-4 py-3">Card No</th>
                            <th className="text-left px-4 py-3">Name</th>
                            <th className="text-left px-4 py-3">Type</th>
                            <th className="text-left px-4 py-3">Room</th>
                            <th className="text-left px-4 py-3">Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visits.data.map((v) => (
                            <tr key={v.id} className="border-t">
                                <td className="px-4 py-3">{formatTime(v.visit_time)}</td>
                                <td className="px-4 py-3 font-mono">{v.patient?.card_number}</td>
                                <td className="px-4 py-3">{v.patient?.full_name}</td>
                                <td className="px-4 py-3">{v.patient?.patient_type?.name}</td>
                                <td className="px-4 py-3">{v.room?.room_name}</td>
                                <td className="px-4 py-3">{v.assigned_by?.full_name}</td>
                            </tr>
                        ))}
                        {visits.data.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-gray-400">No visits for selected filters</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}
