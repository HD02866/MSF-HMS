import { Head, router, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { useState, useCallback } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface Patient {
    id: number;
    full_name: string;
    gender: string | null;
    age: number;
    card_number: string;
}

interface RegisterEntry {
    id: number;
    register_type: string;
    record_date: string;
    department_name: string | null;
    referred_from: string | null;
    days_given: number | null;
    patient: Patient;
    creator: { full_name: string } | null;
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
    family: number;
    employee: number;
    os: number;
    referral_accident: number;
    referral_sick_leave: number;
    total: number;
}

interface Filters {
    record_date: string;
    register_type: string;
    search_name: string;
    search_id: string;
}

interface Props {
    registers: Paginated<RegisterEntry>;
    summary: Summary;
    types: Record<string, string>;
    referral_sources: string[];
    filters: Filters;
    canManage: boolean;
}

// ── Constants ──────────────────────────────────────────────────────────────────
const ROW_COLORS: Record<string, string> = {
    family:              'bg-yellow-50 border-l-4 border-l-yellow-400',
    employee:            'bg-green-50 border-l-4 border-l-green-500',
    os:                  'bg-blue-50 border-l-4 border-l-blue-400',
    referral_accident:   'bg-red-50 border-l-4 border-l-red-300',
    referral_sick_leave: 'bg-red-100 border-l-4 border-l-red-600',
};

const TYPE_BADGE_COLORS: Record<string, string> = {
    family:              'bg-yellow-100 text-yellow-800',
    employee:            'bg-green-100 text-green-800',
    os:                  'bg-blue-100 text-blue-800',
    referral_accident:   'bg-red-100 text-red-600',
    referral_sick_leave: 'bg-red-200 text-red-800',
};

const TYPES_WITH_DEPT    = ['family', 'employee', 'referral_accident', 'referral_sick_leave'];
const TYPES_WITH_REFERRAL = ['referral_accident', 'referral_sick_leave'];

// ── Add Entry Modal ────────────────────────────────────────────────────────────
function AddEntryModal({
    types,
    referralSources,
    onClose,
}: {
    types: Record<string, string>;
    referralSources: string[];
    onClose: () => void;
}) {
    const form = useForm({
        patient_id:      '',
        register_type:   'family',
        record_date:     new Date().toISOString().split('T')[0],
        department_name: '',
        referred_from:   '',
        days_given:      '',
    });

    const [searchId, setSearchId]         = useState('');
    const [foundPatient, setFoundPatient] = useState<Patient | null>(null);
    const [searching, setSearching]       = useState(false);
    const [searchError, setSearchError]   = useState('');

    const needsDept     = TYPES_WITH_DEPT.includes(form.data.register_type);
    const needsReferral = TYPES_WITH_REFERRAL.includes(form.data.register_type);

    async function lookupPatient() {
        if (!searchId.trim()) return;
        setSearching(true);
        setSearchError('');
        setFoundPatient(null);
        try {
            const res = await fetch(
                `/daily-register/patient-search?card_number=${encodeURIComponent(searchId.trim())}`,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
            );
            if (!res.ok) throw new Error();
            const json = await res.json();
            const patients: Patient[] = json.data ?? [];
            if (patients.length === 0) {
                setSearchError('No active patient found with that card number.');
                form.setData('patient_id', '');
            } else {
                setFoundPatient(patients[0]);
                form.setData('patient_id', String(patients[0].id));
            }
        } catch {
            setSearchError('Search failed. Please try again.');
        } finally {
            setSearching(false);
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/daily-register', { onSuccess: () => onClose() });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <h2 className="text-lg font-semibold text-gray-800 mb-4">Add Register Entry</h2>
                <form onSubmit={submit} className="space-y-4">

                    {/* Patient Lookup */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Patient ID / Card Number <span className="text-red-500">*</span>
                        </label>
                        <div className="flex gap-2">
                            <Input
                                value={searchId}
                                onChange={(e) => setSearchId(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), lookupPatient())}
                                placeholder="e.g. 97266-0"
                                className="flex-1"
                            />
                            <Button type="button" variant="secondary" onClick={lookupPatient} disabled={searching}>
                                {searching ? '…' : 'Find'}
                            </Button>
                        </div>
                        {searchError && <p className="text-red-500 text-xs mt-1">{searchError}</p>}
                        {form.errors.patient_id && <p className="text-red-500 text-xs mt-1">{form.errors.patient_id}</p>}
                    </div>

                    {foundPatient && (
                        <div className="rounded-md bg-green-50 border border-green-200 p-3 text-sm space-y-1">
                            <p><span className="font-medium text-gray-600">Name:</span> {foundPatient.full_name}</p>
                            <div className="flex gap-4">
                                <p><span className="font-medium text-gray-600">Sex:</span> {foundPatient.gender ?? '—'}</p>
                                <p><span className="font-medium text-gray-600">Age:</span> {foundPatient.age ?? '—'}</p>
                            </div>
                        </div>
                    )}

                    {/* Register Type */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Register Type <span className="text-red-500">*</span>
                        </label>
                        <Select
                            value={form.data.register_type}
                            onChange={(e) => form.setData('register_type', e.target.value)}
                        >
                            {Object.entries(types).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </Select>
                        {form.errors.register_type && <p className="text-red-500 text-xs mt-1">{form.errors.register_type}</p>}
                    </div>

                    {/* Date */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Date <span className="text-red-500">*</span>
                        </label>
                        <Input
                            type="date"
                            value={form.data.record_date}
                            onChange={(e) => form.setData('record_date', e.target.value)}
                        />
                        {form.errors.record_date && <p className="text-red-500 text-xs mt-1">{form.errors.record_date}</p>}
                    </div>

                    {/* Department (Family, Employee, Referral types) */}
                    {needsDept && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <Input
                                value={form.data.department_name}
                                onChange={(e) => form.setData('department_name', e.target.value)}
                                placeholder="e.g. OPD, Emergency"
                            />
                            {form.errors.department_name && <p className="text-red-500 text-xs mt-1">{form.errors.department_name}</p>}
                        </div>
                    )}

                    {/* Referral fields — only for Referral Accident & Referral Sick Leave */}
                    {needsReferral && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Sent By (Referred From) <span className="text-red-500">*</span>
                                </label>
                                <Select
                                    value={form.data.referred_from}
                                    onChange={(e) => form.setData('referred_from', e.target.value)}
                                >
                                    <option value="">— Select source —</option>
                                    {referralSources.map((src) => (
                                        <option key={src} value={src}>{src}</option>
                                    ))}
                                </Select>
                                {form.errors.referred_from && <p className="text-red-500 text-xs mt-1">{form.errors.referred_from}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Days Given
                                </label>
                                <Select
                                    value={form.data.days_given}
                                    onChange={(e) => form.setData('days_given', e.target.value)}
                                >
                                    <option value="">— Select days —</option>
                                    {Array.from({ length: 30 }, (_, i) => i + 1).map((d) => (
                                        <option key={d} value={d}>{d} {d === 1 ? 'day' : 'days'}</option>
                                    ))}
                                </Select>
                                {form.errors.days_given && <p className="text-red-500 text-xs mt-1">{form.errors.days_given}</p>}
                            </div>
                        </>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing || !form.data.patient_id}>
                            {form.processing ? 'Saving…' : 'Add Entry'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Edit Modal ─────────────────────────────────────────────────────────────────
function EditModal({
    entry,
    types,
    referralSources,
    onClose,
}: {
    entry: RegisterEntry;
    types: Record<string, string>;
    referralSources: string[];
    onClose: () => void;
}) {
    const form = useForm({
        register_type:   entry.register_type,
        record_date:     entry.record_date,
        department_name: entry.department_name ?? '',
        referred_from:   entry.referred_from ?? '',
        days_given:      entry.days_given ? String(entry.days_given) : '',
    });

    const needsDept     = TYPES_WITH_DEPT.includes(form.data.register_type);
    const needsReferral = TYPES_WITH_REFERRAL.includes(form.data.register_type);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/daily-register/${entry.id}`, { onSuccess: () => onClose() });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
                <h2 className="text-lg font-semibold text-gray-800 mb-1">Edit Register Entry</h2>
                <p className="text-sm text-gray-500 mb-4">{entry.patient.full_name} — {entry.patient.card_number}</p>
                <form onSubmit={submit} className="space-y-4">

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Register Type</label>
                        <Select value={form.data.register_type} onChange={(e) => form.setData('register_type', e.target.value)}>
                            {Object.entries(types).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </Select>
                        {form.errors.register_type && <p className="text-red-500 text-xs mt-1">{form.errors.register_type}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <Input
                            type="date"
                            value={form.data.record_date}
                            onChange={(e) => form.setData('record_date', e.target.value)}
                        />
                        {form.errors.record_date && <p className="text-red-500 text-xs mt-1">{form.errors.record_date}</p>}
                    </div>

                    {needsDept && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <Input
                                value={form.data.department_name}
                                onChange={(e) => form.setData('department_name', e.target.value)}
                                placeholder="e.g. OPD, Emergency"
                            />
                            {form.errors.department_name && <p className="text-red-500 text-xs mt-1">{form.errors.department_name}</p>}
                        </div>
                    )}

                    {needsReferral && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Sent By (Referred From)</label>
                                <Select
                                    value={form.data.referred_from}
                                    onChange={(e) => form.setData('referred_from', e.target.value)}
                                >
                                    <option value="">— Select source —</option>
                                    {referralSources.map((src) => (
                                        <option key={src} value={src}>{src}</option>
                                    ))}
                                </Select>
                                {form.errors.referred_from && <p className="text-red-500 text-xs mt-1">{form.errors.referred_from}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Days Given</label>
                                <Select
                                    value={form.data.days_given}
                                    onChange={(e) => form.setData('days_given', e.target.value)}
                                >
                                    <option value="">— Select days —</option>
                                    {Array.from({ length: 30 }, (_, i) => i + 1).map((d) => (
                                        <option key={d} value={d}>{d} {d === 1 ? 'day' : 'days'}</option>
                                    ))}
                                </Select>
                                {form.errors.days_given && <p className="text-red-500 text-xs mt-1">{form.errors.days_given}</p>}
                            </div>
                        </>
                    )}

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Delete Confirm Modal ───────────────────────────────────────────────────────
function DeleteModal({ entry, onClose }: { entry: RegisterEntry; onClose: () => void }) {
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(`/daily-register/${entry.id}`, {
            onFinish: () => { setProcessing(false); onClose(); },
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
                <h2 className="text-lg font-semibold text-gray-800 mb-2">Delete Entry</h2>
                <p className="text-sm text-gray-600 mb-6">
                    Remove register entry for <span className="font-semibold">{entry.patient.full_name}</span>?
                    <br /><span className="text-xs text-red-500">This action cannot be undone.</span>
                </p>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="button" variant="danger" disabled={processing} onClick={handleDelete}>
                        {processing ? 'Deleting…' : 'Delete'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// ── Summary Card ──────────────────────────────────────────────────────────────
function SummaryCard({ label, value, colorClass }: { label: string; value: number; colorClass: string }) {
    return (
        <div className={`rounded-lg border p-3 text-center ${colorClass}`}>
            <p className="text-2xl font-bold">{value}</p>
            <p className="text-xs font-medium mt-0.5 opacity-80">{label}</p>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function DailyRegisterIndex({
    registers, summary, types, referral_sources, filters, canManage,
}: Props) {
    const [showAdd, setShowAdd]         = useState(false);
    const [editEntry, setEditEntry]     = useState<RegisterEntry | null>(null);
    const [deleteEntry, setDeleteEntry] = useState<RegisterEntry | null>(null);
    const [localFilters, setLocalFilters] = useState(filters);

    const applyFilters = useCallback((newFilters: Partial<Filters>) => {
        const merged = { ...localFilters, ...newFilters };
        setLocalFilters(merged);
        router.get('/daily-register', merged as Record<string, string>, { preserveState: true, replace: true });
    }, [localFilters]);

    function exportUrl(type: 'excel' | 'pdf') {
        const params = new URLSearchParams();
        if (localFilters.record_date)   params.set('record_date', localFilters.record_date);
        if (localFilters.register_type) params.set('register_type', localFilters.register_type);
        if (localFilters.search_name)   params.set('search_name', localFilters.search_name);
        if (localFilters.search_id)     params.set('search_id', localFilters.search_id);
        return `/daily-register/export/${type}?${params.toString()}`;
    }

    return (
        <AppLayout title="Daily Register">
            <Head title="Daily Register" />

            {showAdd && (
                <AddEntryModal
                    types={types}
                    referralSources={referral_sources}
                    onClose={() => setShowAdd(false)}
                />
            )}
            {editEntry && (
                <EditModal
                    entry={editEntry}
                    types={types}
                    referralSources={referral_sources}
                    onClose={() => setEditEntry(null)}
                />
            )}
            {deleteEntry && (
                <DeleteModal entry={deleteEntry} onClose={() => setDeleteEntry(null)} />
            )}

            {/* Summary Cards */}
            <div className="grid grid-cols-3 sm:grid-cols-6 gap-3 mb-5">
                <SummaryCard label="Total"          value={summary.total}               colorClass="bg-gray-50 text-gray-700" />
                <SummaryCard label="Family"         value={summary.family}              colorClass="bg-yellow-50 text-yellow-800" />
                <SummaryCard label="Employee"       value={summary.employee}            colorClass="bg-green-50 text-green-800" />
                <SummaryCard label="OS"             value={summary.os}                  colorClass="bg-blue-50 text-blue-800" />
                <SummaryCard label="Ref. Accident"  value={summary.referral_accident}   colorClass="bg-red-50 text-red-600" />
                <SummaryCard label="Ref. Sick Leave" value={summary.referral_sick_leave} colorClass="bg-red-100 text-red-800" />
            </div>

            {/* Filters + Actions */}
            <div className="bg-white rounded-xl border shadow-sm p-4 mb-5">
                <div className="flex flex-wrap gap-3 items-end">
                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Date</label>
                        <Input type="date" value={localFilters.record_date}
                            onChange={(e) => applyFilters({ record_date: e.target.value })} />
                    </div>
                    <div className="flex-1 min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Register Type</label>
                        <Select value={localFilters.register_type}
                            onChange={(e) => applyFilters({ register_type: e.target.value })}>
                            <option value="">All Types</option>
                            {Object.entries(types).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </Select>
                    </div>
                    <div className="flex-1 min-w-[160px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Search by Name</label>
                        <Input value={localFilters.search_name}
                            onChange={(e) => applyFilters({ search_name: e.target.value })}
                            placeholder="Patient name…" />
                    </div>
                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Search by ID</label>
                        <Input value={localFilters.search_id}
                            onChange={(e) => applyFilters({ search_id: e.target.value })}
                            placeholder="Card number…" />
                    </div>
                    <div className="flex gap-2 shrink-0">
                        {canManage && (
                            <Button onClick={() => setShowAdd(true)}>+ Add Entry</Button>
                        )}
                        <a href={exportUrl('excel')} className="px-4 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">Export Excel</a>
                        <a href={exportUrl('pdf')}   className="px-4 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-700">Export PDF</a>
                    </div>
                </div>
            </div>

            {/* Color legend */}
            <div className="flex flex-wrap gap-2 mb-3">
                {Object.entries(types).map(([key, label]) => (
                    <span key={key} className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${TYPE_BADGE_COLORS[key] ?? 'bg-gray-100 text-gray-700'}`}>
                        {label}
                    </span>
                ))}
            </div>

            {/* Register Table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-700">Register Entries</h3>
                    <span className="text-xs text-gray-500">
                        {registers.total} record{registers.total !== 1 ? 's' : ''}
                        {registers.from != null && ` — showing ${registers.from}–${registers.to}`}
                    </span>
                </div>

                {registers.data.length === 0 ? (
                    <div className="px-5 py-12 text-center text-gray-400 text-sm">
                        No register entries found for the selected filters.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b">
                                <tr>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">#</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Type</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">ID Number</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Patient Name</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Sex</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Age</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Department</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Referred From</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Days</th>
                                    {canManage && (
                                        <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {registers.data.map((entry, index) => (
                                    <tr key={entry.id} className={`border-t ${ROW_COLORS[entry.register_type] ?? ''}`}>
                                        <td className="px-4 py-3 text-gray-400 text-xs">{(registers.from ?? 1) + index}</td>
                                        <td className="px-4 py-3 text-gray-700">{entry.record_date}</td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${TYPE_BADGE_COLORS[entry.register_type] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {types[entry.register_type] ?? entry.register_type}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-gray-600 text-xs">{entry.patient.card_number}</td>
                                        <td className="px-4 py-3 font-medium text-gray-800">{entry.patient.full_name}</td>
                                        <td className="px-4 py-3 text-gray-600">{entry.patient.gender ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{entry.patient.age ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{entry.department_name ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">{entry.referred_from ?? '—'}</td>
                                        <td className="px-4 py-3 text-gray-600">
                                            {entry.days_given ? `${entry.days_given}d` : '—'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    <button onClick={() => setEditEntry(entry)}
                                                        className="px-2.5 py-1 text-xs font-medium rounded bg-yellow-50 text-yellow-700 border border-yellow-200 hover:bg-yellow-100">
                                                        Edit
                                                    </button>
                                                    <button onClick={() => setDeleteEntry(entry)}
                                                        className="px-2.5 py-1 text-xs font-medium rounded bg-red-50 text-red-700 border border-red-200 hover:bg-red-100">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {registers.links.length > 3 && (
                    <div className="px-5 py-3 border-t bg-gray-50 flex flex-wrap gap-1 justify-center">
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
        </AppLayout>
    );
}
