import { Head, router } from '@inertiajs/react';
import AppLayout, { Button, Input, StatCard } from '@/Layouts/AppLayout';

interface Props {
    report: {
        total_visits: number;
        by_patient_type: Record<string, number>;
        by_room: Record<string, number>;
        start_date: string;
        end_date: string;
    };
    period: string;
    date: string;
}

export default function ReportsIndex({ report, period, date }: Props) {
    const setPeriod = (p: string) => {
        router.get('/reports', { period: p, date });
    };

    return (
        <AppLayout title="Reports">
            <Head title="Reports" />

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
                <Input type="date" defaultValue={date} onChange={(e) => router.get('/reports', { period, date: e.target.value })} className="w-auto" />
                <a href={`/reports/export/csv/${period}?date=${date}`} className="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-green-600 text-white hover:bg-green-700">Export Excel</a>
                <a href={`/reports/export/pdf/${period}?date=${date}`} className="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-yellow-400 text-gray-900 hover:bg-yellow-500">Export PDF</a>
            </div>

            <p className="text-sm text-gray-500 mb-4">
                Period: {report.start_date} to {report.end_date}
            </p>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard label="Total Visits" value={report.total_visits} />
                {Object.entries(report.by_patient_type).map(([type, count]) => (
                    <StatCard key={type} label={type} value={count} />
                ))}
            </div>

            <div className="bg-white rounded-lg border p-4">
                <h3 className="font-semibold mb-4">Room Utilization</h3>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    {Object.entries(report.by_room).map(([room, count]) => (
                        <div key={room} className="flex justify-between text-sm border-b pb-2">
                            <span>{room}</span>
                            <span className="font-semibold text-green-700">{count}</span>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
