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
    destinations: Record<string, string>;
    today: string;
    requester_name: string;
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function Referral({
    queue_entry, patient, room_name,
    destinations, today,
    requester_name: defaultRequesterName,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        destination:       '',
        reason:            '',
        diagnosis:         '',
        doctor_nurse_name: defaultRequesterName,
        signature_data:    '',
        date:              today,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${queue_entry.id}/referral`, { preserveScroll: true });
    }

    return (
        <AppLayout title="Create Referral">
            <Head title={`Referral — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-teal-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        🏥
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Referral</h1>
                        <p className="text-teal-100 text-sm mt-0.5">
                            {patient.full_name}
                            <span className="font-mono ml-2 opacity-80">{patient.card_number}</span>
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-teal-100">
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

                {/* Destination & Date */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Referral Details</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Destination <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.destination}
                                onChange={(e) => setData('destination', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                            >
                                <option value="">Select destination…</option>
                                {Object.entries(destinations).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            {errors.destination && <p className="text-red-500 text-xs mt-1">{errors.destination}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                            />
                            {errors.date && <p className="text-red-500 text-xs mt-1">{errors.date}</p>}
                        </div>
                    </div>
                </div>

                {/* Reason */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-3">Reason <span className="text-red-500">*</span></h3>
                    <textarea
                        value={data.reason}
                        onChange={(e) => setData('reason', e.target.value)}
                        rows={3}
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                        placeholder="Reason for the referral…"
                    />
                    {errors.reason && <p className="text-red-500 text-xs mt-1">{errors.reason}</p>}
                </div>

                {/* Diagnosis */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-3">Diagnosis <span className="text-red-500">*</span></h3>
                    <textarea
                        value={data.diagnosis}
                        onChange={(e) => setData('diagnosis', e.target.value)}
                        rows={3}
                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                        placeholder="Current diagnosis…"
                    />
                    {errors.diagnosis && <p className="text-red-500 text-xs mt-1">{errors.diagnosis}</p>}
                </div>

                {/* Doctor/Nurse & Signature */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Requester Information</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Doctor / Nurse <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={data.doctor_nurse_name}
                                onChange={(e) => setData('doctor_nurse_name', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                                placeholder="Attending doctor or nurse name…"
                            />
                            {errors.doctor_nurse_name && <p className="text-red-500 text-xs mt-1">{errors.doctor_nurse_name}</p>}
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
                        {processing ? 'Submitting…' : '🏥 Submit Referral'}
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
