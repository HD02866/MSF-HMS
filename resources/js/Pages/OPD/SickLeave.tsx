import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import SignatureCanvas from '@/components/SignatureCanvas';

// ── Types ──────────────────────────────────────────────────────────────────────
interface QueueEntry {
    id: number;
    queue_number: number;
    status: string;
}

interface Patient {
    id: number;
    full_name: string;
    card_number: string;
    gender: string | null;
    age: number | null;
}

interface Props {
    queue_entry: QueueEntry;
    patient: Patient;
    room_name: string | null;
    today: string;
    requester_name: string;
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function SickLeave({
    queue_entry, patient, room_name,
    today,
    requester_name: defaultRequesterName,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        employee_name:  '',
        days:           '',
        start_date:     today,
        end_date:       '',
        diagnosis:      '',
        recommendation: '',
        signature_data: '',
    });

    // Auto-calculate end_date when start_date or days changes
    function handleStartChange(val: string) {
        setData('start_date', val);
        if (data.days && val) {
            const start = new Date(val);
            start.setDate(start.getDate() + parseInt(data.days, 10) - 1);
            setData('end_date', start.toISOString().slice(0, 10));
        }
    }

    function handleDaysChange(val: string) {
        setData('days', val);
        if (data.start_date && val) {
            const start = new Date(data.start_date);
            start.setDate(start.getDate() + parseInt(val, 10) - 1);
            setData('end_date', start.toISOString().slice(0, 10));
        }
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${queue_entry.id}/sick-leave`, { preserveScroll: true });
    }

    return (
        <AppLayout title="Create Sick Leave">
            <Head title={`Sick Leave — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-amber-600 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        📄
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Sick Leave</h1>
                        <p className="text-amber-100 text-sm mt-0.5">
                            {patient.full_name}
                            <span className="font-mono ml-2 opacity-80">{patient.card_number}</span>
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-amber-100">
                        {room_name ?? '—'} · Queue #{queue_entry.queue_number}
                    </span>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-md text-sm font-medium"
                    >
                        🖨️ Print
                    </button>
                    <a
                        href={`/opd/consultation/${queue_entry.id}`}
                        className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                    >
                        ← Back to Consultation
                    </a>
                </div>
            </div>

            {/* Patient summary strip */}
            <div className="bg-white border rounded-xl px-5 py-3 mb-6 flex flex-wrap gap-6 text-sm">
                <div><span className="text-gray-400 text-xs">Gender</span><p className="font-medium text-gray-800">{patient.gender ?? '—'}</p></div>
                <div><span className="text-gray-400 text-xs">Age</span><p className="font-medium text-gray-800">{patient.age != null ? `${patient.age} yrs` : '—'}</p></div>
                <div><span className="text-gray-400 text-xs">Queue Status</span><p className="font-medium text-gray-800">{queue_entry.status}</p></div>
            </div>

            {/* Form */}
            <form onSubmit={submit} className="space-y-6">

                {/* Employee & Days */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Sick Leave Details</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Employee Name <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.employee_name}
                                onChange={(e) => setData('employee_name', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="Employee taking sick leave…"
                            />
                            {errors.employee_name && <p className="text-red-500 text-xs mt-1">{errors.employee_name}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Number of Days <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                min={1}
                                max={365}
                                value={data.days}
                                onChange={(e) => handleDaysChange(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="e.g. 3"
                            />
                            {errors.days && <p className="text-red-500 text-xs mt-1">{errors.days}</p>}
                        </div>
                    </div>
                </div>

                {/* Dates */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Dates</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Start Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.start_date}
                                onChange={(e) => handleStartChange(e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                            />
                            {errors.start_date && <p className="text-red-500 text-xs mt-1">{errors.start_date}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                End Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.end_date}
                                onChange={(e) => setData('end_date', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                            />
                            {errors.end_date && <p className="text-red-500 text-xs mt-1">{errors.end_date}</p>}
                        </div>
                    </div>
                </div>

                {/* Diagnosis */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-3">Diagnosis <span className="text-red-500">*</span></h3>
                    <textarea
                        value={data.diagnosis}
                        onChange={(e) => setData('diagnosis', e.target.value)}
                        rows={3}
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="Diagnosis for the sick leave…"
                    />
                    {errors.diagnosis && <p className="text-red-500 text-xs mt-1">{errors.diagnosis}</p>}
                </div>

                {/* Recommendation */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-3">Recommendation</h3>
                    <textarea
                        value={data.recommendation}
                        onChange={(e) => setData('recommendation', e.target.value)}
                        rows={3}
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="Optional recommendations…"
                    />
                    {errors.recommendation && <p className="text-red-500 text-xs mt-1">{errors.recommendation}</p>}
                </div>

                {/* Signature */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Signature</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Issued By</label>
                            <input
                                type="text"
                                value={defaultRequesterName}
                                readOnly
                                className="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Signature</label>
                            <SignatureCanvas
                                onChange={(sig) => setData('signature_data', sig)}
                                error={errors.signature_data}
                            />
                        </div>
                    </div>
                </div>

                {/* Submit */}
                <div className="flex items-center gap-4 pt-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Submitting…' : '📄 Submit Sick Leave'}
                    </Button>
                    <a
                        href={`/opd/consultation/${queue_entry.id}`}
                        className="text-sm text-gray-500 hover:text-gray-700 font-medium"
                    >
                        Cancel
                    </a>
                </div>
            </form>
        </AppLayout>
    );
}
