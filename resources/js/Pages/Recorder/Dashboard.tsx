import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

// Register type → display label, colors, quick-add url fragment
const TYPE_CONFIG: Record<string, {
    label: string;
    bg: string;
    border: string;
    text: string;
    icon: string;
    accent: string;
}> = {
    family: {
        label: 'Family',
        bg: 'bg-yellow-50',
        border: 'border-yellow-300',
        text: 'text-yellow-800',
        icon: '👨‍👩‍👧‍👦',
        accent: 'bg-yellow-400',
    },
    employee: {
        label: 'Employee',
        bg: 'bg-green-50',
        border: 'border-green-400',
        text: 'text-green-800',
        icon: '👤',
        accent: 'bg-green-500',
    },
    os: {
        label: 'Out Service (OS)',
        bg: 'bg-blue-50',
        border: 'border-blue-400',
        text: 'text-blue-800',
        icon: '🏥',
        accent: 'bg-blue-500',
    },
    referral_accident: {
        label: 'Referral Accident',
        bg: 'bg-red-50',
        border: 'border-red-300',
        text: 'text-red-700',
        icon: '🚨',
        accent: 'bg-red-400',
    },
    referral_sick_leave: {
        label: 'Referral Sick Leave',
        bg: 'bg-red-100',
        border: 'border-red-500',
        text: 'text-red-800',
        icon: '🩺',
        accent: 'bg-red-600',
    },
};

interface Stats {
    family: number;
    employee: number;
    os: number;
    referral_accident: number;
    referral_sick_leave: number;
    total: number;
}

interface Props {
    stats: Stats;
    types: Record<string, string>;
    today: string;
}

function StatCard({
    type,
    count,
}: {
    type: string;
    count: number;
}) {
    const cfg = TYPE_CONFIG[type];
    if (!cfg) return null;

    return (
        <div className={`rounded-xl border-2 ${cfg.border} ${cfg.bg} p-5 flex flex-col gap-2`}>
            <div className="flex items-center justify-between">
                <span className="text-2xl">{cfg.icon}</span>
                <span className={`text-3xl font-bold ${cfg.text}`}>{count}</span>
            </div>
            <p className={`text-sm font-semibold ${cfg.text}`}>{cfg.label}</p>
            <Link
                href={`/daily-register?register_type=${type}&record_date=${new Date().toISOString().split('T')[0]}`}
                className={`mt-1 text-xs font-medium ${cfg.text} underline underline-offset-2 opacity-70 hover:opacity-100`}
            >
                View entries →
            </Link>
        </div>
    );
}

export default function RecorderDashboard({ stats, types, today }: Props) {
    const typeKeys = Object.keys(types) as Array<keyof Stats>;

    // Navigate to daily register pre-filtered by type for quick add
    function quickAdd(type: string) {
        router.get('/daily-register', { register_type: type, record_date: today });
    }

    return (
        <AppLayout title="Recorder Dashboard">
            <Head title="Recorder Dashboard" />

            {/* Welcome banner */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-xl font-bold">Good day, Recorder</h1>
                    <p className="text-green-100 text-sm mt-0.5">
                        Daily Register — <span className="font-medium">{today}</span>
                    </p>
                </div>
                <Link
                    href="/daily-register"
                    className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                >
                    Open Full Register →
                </Link>
            </div>

            {/* Total summary */}
            <div className="bg-white rounded-xl border shadow-sm px-6 py-4 mb-6 flex items-center gap-4">
                <div className="text-4xl font-bold text-green-700">{stats.total}</div>
                <div>
                    <p className="text-sm font-semibold text-gray-700">Total Records Today</p>
                    <p className="text-xs text-gray-400">All register types combined</p>
                </div>
            </div>

            {/* Per-type stat cards */}
            <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
                {typeKeys.map((type) => (
                    <StatCard key={type} type={type} count={stats[type] as number} />
                ))}
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h3 className="text-sm font-semibold text-gray-700 mb-4">Quick Add Entry</h3>
                <div className="flex flex-wrap gap-3">
                    {typeKeys.map((type) => {
                        const cfg = TYPE_CONFIG[type];
                        if (!cfg) return null;
                        return (
                            <button
                                key={type}
                                onClick={() => quickAdd(type)}
                                className={`flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 ${cfg.border} ${cfg.bg} ${cfg.text} text-sm font-medium hover:shadow-md transition-shadow`}
                            >
                                <span>{cfg.icon}</span>
                                + {cfg.label}
                            </button>
                        );
                    })}
                </div>
            </div>

            {/* Navigation shortcuts */}
            <div className="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link
                    href="/patients/search"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">🔍</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Patient Search</p>
                </Link>
                <Link
                    href="/visits/assign"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">🚪</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Assign Room</p>
                </Link>
                <Link
                    href="/daily-register/export/excel"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">📊</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Export Excel</p>
                </Link>
                <Link
                    href="/daily-register/export/pdf"
                    className="bg-white border rounded-xl px-4 py-4 text-center hover:shadow-md transition-shadow group"
                >
                    <div className="text-2xl mb-1">📄</div>
                    <p className="text-sm font-medium text-gray-700 group-hover:text-green-700">Export PDF</p>
                </Link>
            </div>
        </AppLayout>
    );
}
