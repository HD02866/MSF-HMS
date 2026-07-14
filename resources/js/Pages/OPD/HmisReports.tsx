import { Head, router } from '@inertiajs/react';
import AppLayout, { Button, StatCard } from '@/Layouts/AppLayout';
import { useState } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface CountItem { label: string; count: number; }

interface Overview {
    period: string;
    start_date: string;
    end_date: string;
    total_encounters: number;
    unique_patients: number;
    lab_requests: number;
    prescriptions: number;
    referrals: number;
    sick_leaves: number;
    completion_rate: number;
    avg_wait_minutes: number;
}

interface Demographics {
    total_patients: number;
    by_type: CountItem[];
    by_gender: CountItem[];
    by_age: CountItem[];
}

interface Disease {
    total_encounters: number;
    by_diagnosis: CountItem[];
    by_complaint: CountItem[];
}

interface Laboratory {
    total_requests: number;
    completed: number;
    pending: number;
    urgent: number;
    by_panel: CountItem[];
    by_test: CountItem[];
}

interface Pharmacy {
    total_prescriptions: number;
    total_items: number;
    external: number;
    internal: number;
    by_medicine: CountItem[];
    by_category: CountItem[];
}

interface Referral {
    total_referrals: number;
    by_destination: CountItem[];
    by_doctor: CountItem[];
}

interface SickLeaveReport {
    total_sick_leaves: number;
    total_days: number;
    avg_days: number;
    by_employee: CountItem[];
    by_diagnosis: CountItem[];
}

interface Visits {
    total: number;
    completed: number;
    transferred: number;
    avg_duration_mins: number;
    max_duration_mins: number;
    min_duration_mins: number;
    by_room: { label: string; count: number; completed: number; transferred: number; }[];
    by_doctor: CountItem[];
}

interface Props {
    overview: Overview;
    demographics: Demographics;
    disease: Disease;
    laboratory: Laboratory;
    pharmacy: Pharmacy;
    referrals: Referral;
    sickLeave: SickLeaveReport;
    visits: Visits;
    period: string;
    date: string;
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const TABS = ['overview', 'demographics', 'disease', 'laboratory', 'pharmacy', 'referrals', 'sick_leave', 'visits'] as const;
type Tab = typeof TABS[number];

const TAB_LABELS: Record<Tab, string> = {
    overview:     'Overview',
    demographics: 'Demographics',
    disease:      'Disease',
    laboratory:   'Laboratory',
    pharmacy:     'Pharmacy',
    referrals:    'Referrals',
    sick_leave:   'Sick Leave',
    visits:       'Visits',
};

const PERIODS = ['daily', 'weekly', 'monthly', 'yearly'] as const;

// ── Reusable Components ────────────────────────────────────────────────────────

function BarChart({ items, maxItems = 15 }: { items: CountItem[]; maxItems?: number }) {
    if (items.length === 0) return <p className="text-sm text-gray-400 text-center py-4">No data.</p>;
    const data = items.slice(0, maxItems);
    const maxCount = data[0]?.count ?? 1;

    return (
        <div className="space-y-2">
            {data.map((item) => (
                <div key={item.label} className="flex items-center gap-3">
                    <span className="text-xs text-gray-600 w-48 truncate shrink-0" title={item.label}>{item.label}</span>
                    <div className="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                        <div
                            className="bg-green-600 h-full rounded-full transition-all"
                            style={{ width: `${Math.max((item.count / maxCount) * 100, 2)}%` }}
                        />
                    </div>
                    <span className="text-xs font-semibold text-gray-700 w-10 text-right">{item.count}</span>
                </div>
            ))}
        </div>
    );
}

function TableChart({ items, columns, maxItems = 20 }: { items: any[]; columns: { key: string; label: string; render?: (v: any) => React.ReactNode }[]; maxItems?: number }) {
    if (items.length === 0) return <p className="text-sm text-gray-400 text-center py-4">No data.</p>;
    const data = items.slice(0, maxItems);

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-gray-50">
                        {columns.map((col) => (
                            <th key={col.key} className="text-left px-4 py-2 text-xs font-medium text-gray-500">{col.label}</th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr key={i} className="border-b last:border-0">
                            {columns.map((col) => (
                                <td key={col.key} className="px-4 py-2 text-gray-700">
                                    {col.render ? col.render(row[col.key]) : row[col.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── Tab Content ────────────────────────────────────────────────────────────────

function OverviewTab({ data }: { data: Overview }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="Total Encounters" value={data.total_encounters} />
                <StatCard label="Unique Patients" value={data.unique_patients} />
                <StatCard label="Completion Rate" value={`${data.completion_rate}%`} />
                <StatCard label="Avg Wait Time" value={`${data.avg_wait_minutes}m`} />
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="Lab Requests" value={data.lab_requests} />
                <StatCard label="Prescriptions" value={data.prescriptions} />
                <StatCard label="Referrals" value={data.referrals} />
                <StatCard label="Sick Leaves" value={data.sick_leaves} />
            </div>
        </div>
    );
}

function DemographicsTab({ data }: { data: Demographics }) {
    return (
        <div className="space-y-6">
            <StatCard label="Total Unique Patients" value={data.total_patients} />

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Patient Type</h4>
                <BarChart items={data.by_type} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Gender</h4>
                <BarChart items={data.by_gender} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Age Group</h4>
                <BarChart items={data.by_age} />
            </div>
        </div>
    );
}

function DiseaseTab({ data }: { data: Disease }) {
    return (
        <div className="space-y-6">
            <StatCard label="Total OPD Encounters" value={data.total_encounters} />

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Top Diagnoses</h4>
                <BarChart items={data.by_diagnosis} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Top Chief Complaints</h4>
                <BarChart items={data.by_complaint} />
            </div>
        </div>
    );
}

function LaboratoryTab({ data }: { data: Laboratory }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="Total Requests" value={data.total_requests} />
                <StatCard label="Completed" value={data.completed} />
                <StatCard label="Pending" value={data.pending} />
                <StatCard label="Urgent" value={data.urgent} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Panel</h4>
                <BarChart items={data.by_panel} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Most Requested Tests</h4>
                <TableChart
                    items={data.by_test}
                    columns={[
                        { key: 'label', label: 'Test Name' },
                        { key: 'count', label: 'Count' },
                    ]}
                />
            </div>
        </div>
    );
}

function PharmacyTab({ data }: { data: Pharmacy }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <StatCard label="Total Prescriptions" value={data.total_prescriptions} />
                <StatCard label="Total Items" value={data.total_items} />
                <StatCard label="Internal" value={data.internal} />
                <StatCard label="External" value={data.external} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Most Prescribed Medicines</h4>
                <BarChart items={data.by_medicine} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Category</h4>
                <TableChart
                    items={data.by_category}
                    columns={[
                        { key: 'label', label: 'Category' },
                        { key: 'count', label: 'Count' },
                    ]}
                />
            </div>
        </div>
    );
}

function ReferralsTab({ data }: { data: Referral }) {
    return (
        <div className="space-y-6">
            <StatCard label="Total Referrals" value={data.total_referrals} />

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Destination</h4>
                <BarChart items={data.by_destination} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Doctor/Nurse</h4>
                <BarChart items={data.by_doctor} />
            </div>
        </div>
    );
}

function SickLeaveTab({ data }: { data: SickLeaveReport }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard label="Total Sick Leaves" value={data.total_sick_leaves} />
                <StatCard label="Total Days" value={data.total_days} />
                <StatCard label="Avg Days per Leave" value={data.avg_days} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Employee</h4>
                <BarChart items={data.by_employee} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Diagnosis</h4>
                <BarChart items={data.by_diagnosis} />
            </div>
        </div>
    );
}

function VisitsTab({ data }: { data: Visits }) {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <StatCard label="Total Visits" value={data.total} />
                <StatCard label="Completed" value={data.completed} />
                <StatCard label="Transferred" value={data.transferred} />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard label="Avg Duration" value={`${data.avg_duration_mins}m`} />
                <StatCard label="Min Duration" value={`${data.min_duration_mins}m`} />
                <StatCard label="Max Duration" value={`${data.max_duration_mins}m`} />
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Room</h4>
                {data.by_room.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-4">No data.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-gray-50">
                                    <th className="text-left px-4 py-2 text-xs font-medium text-gray-500">Room</th>
                                    <th className="text-right px-4 py-2 text-xs font-medium text-gray-500">Total</th>
                                    <th className="text-right px-4 py-2 text-xs font-medium text-gray-500">Completed</th>
                                    <th className="text-right px-4 py-2 text-xs font-medium text-gray-500">Transferred</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.by_room.map((item) => (
                                    <tr key={item.label} className="border-b last:border-0">
                                        <td className="px-4 py-2 font-medium text-gray-800">{item.label}</td>
                                        <td className="px-4 py-2 text-right">{item.count}</td>
                                        <td className="px-4 py-2 text-right text-green-700">{item.completed}</td>
                                        <td className="px-4 py-2 text-right text-purple-700">{item.transferred}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">By Doctor/Nurse</h4>
                <BarChart items={data.by_doctor} />
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function HmisReports({ overview, demographics, disease, laboratory, pharmacy, referrals, sickLeave, visits, period, date }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('overview');

    function changePeriod(newPeriod: string) {
        router.get('/opd/hmis-reports', { period: newPeriod, date }, { preserveState: true });
    }

    function changeDate(newDate: string) {
        router.get('/opd/hmis-reports', { period, date: newDate }, { preserveState: true });
    }

    function exportUrl(type: 'excel' | 'pdf') {
        return `/opd/hmis-reports/export/${type}?period=${period}&date=${date}`;
    }

    function handlePrint() {
        window.open(exportUrl('pdf'), '_blank');
    }

    return (
        <AppLayout title="HMIS Reports">
            <Head title="HMIS Reports" />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 className="text-xl font-bold">HMIS Reports</h1>
                    <p className="text-green-100 text-sm mt-0.5">
                        {period.charAt(0).toUpperCase() + period.slice(1)} Report — {overview.start_date} to {overview.end_date}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <a
                        href={exportUrl('excel')}
                        className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                    >
                        Export Excel
                    </a>
                    <a
                        href={exportUrl('pdf')}
                        className="bg-white hover:bg-gray-50 text-green-700 border border-green-200 px-4 py-2 rounded-md text-sm font-semibold"
                    >
                        Export PDF
                    </a>
                    <Button variant="secondary" onClick={handlePrint}>
                        Print
                    </Button>
                </div>
            </div>

            {/* Period Controls */}
            <div className="bg-white rounded-xl border shadow-sm p-4 mb-6 flex items-center gap-4 flex-wrap">
                <div className="flex items-center gap-2">
                    {PERIODS.map((p) => (
                        <button
                            key={p}
                            onClick={() => changePeriod(p)}
                            className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                                period === p
                                    ? 'bg-green-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {p.charAt(0).toUpperCase() + p.slice(1)}
                        </button>
                    ))}
                </div>
                <input
                    type="date"
                    value={date}
                    onChange={(e) => changeDate(e.target.value)}
                    className="rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                />
            </div>

            {/* Tabs */}
            <div className="flex flex-wrap gap-1 border-b mb-6">
                {TABS.map((tab) => (
                    <button
                        key={tab}
                        onClick={() => setActiveTab(tab)}
                        className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                            activeTab === tab
                                ? 'border-green-600 text-green-700 bg-green-50'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        {TAB_LABELS[tab]}
                    </button>
                ))}
            </div>

            {/* Tab Content */}
            {activeTab === 'overview' && <OverviewTab data={overview} />}
            {activeTab === 'demographics' && <DemographicsTab data={demographics} />}
            {activeTab === 'disease' && <DiseaseTab data={disease} />}
            {activeTab === 'laboratory' && <LaboratoryTab data={laboratory} />}
            {activeTab === 'pharmacy' && <PharmacyTab data={pharmacy} />}
            {activeTab === 'referrals' && <ReferralsTab data={referrals} />}
            {activeTab === 'sick_leave' && <SickLeaveTab data={sickLeave} />}
            {activeTab === 'visits' && <VisitsTab data={visits} />}
        </AppLayout>
    );
}
