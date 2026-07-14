import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

// ── Types ──────────────────────────────────────────────────────────────────────
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

interface VisitRecord {
    id: number;
    visit_date: string;
    visit_time: string;
    queue_number: number | null;
    remarks: string | null;
    status: string;
    room: { room_name: string } | null;
    assigned_by: { full_name: string } | null;
}

interface RegisterRecord {
    id: number;
    register_type: string;
    record_date: string;
    department_name: string | null;
    referred_from: string | null;
    days_given: number | null;
    creator: { full_name: string } | null;
}

interface SickLeave {
    id: number;
    employee_name: string;
    days: number;
    start_date: string;
    end_date: string;
    diagnosis: string;
    recommendation: string | null;
    date: string;
    created_at: string;
    requested_by: { full_name: string } | null;
}

interface ReferralRecord {
    id: number;
    destination: string;
    reason: string;
    diagnosis: string;
    doctor_nurse_name: string;
    signature_data: string | null;
    date: string;
    created_at: string;
    requested_by: { full_name: string } | null;
}

interface OpdEncounter {
    id: number;
    queue_number: number;
    status: string;
    arrived_at: string;
    called_at: string | null;
    completed_at: string | null;
    room: { room_name: string } | null;
    clinical_note: {
        id: number;
        diagnosis: string | null;
        chief_complaint: string | null;
        temperature: number | null;
        systolic_bp: number | null;
        diastolic_bp: number | null;
        pulse_rate: number | null;
        respiratory_rate: number | null;
        spo2: number | null;
        weight: number | null;
        height: number | null;
        bmi: number | null;
        random_blood_sugar: number | null;
    } | null;
}

interface LabTestResult {
    id: number;
    test_name: string;
    result: {
        result: string;
        remarks: string | null;
        result_date: string;
        performed_by: string | null;
    } | null;
}

interface LabRequestRecord {
    id: number;
    request_date: string;
    priority: string;
    clinical_notes: string | null;
    requested_by: string | null;
    created_at: string;
    tests: LabTestResult[];
    lab_queue: {
        status: string;
        completed_at: string | null;
    } | null;
}

interface TimelineItem {
    type: string;
    date: string;
    time: string | null;
    title: string;
    detail: string | null;
    badge: string;
    badge_color: string;
    meta: string | null;
}

interface Props {
    queue_entry: { id: number; queue_number: number; status: string };
    patient: { id: number; full_name: string; card_number: string };
    room_name: string | null;
    visits: Paginated<VisitRecord>;
    registers: Paginated<RegisterRecord>;
    referrals: ReferralRecord[];
    sick_leaves: SickLeave[];
    opd_encounters: Paginated<OpdEncounter>;
    lab_results: Paginated<LabRequestRecord>;
    timeline: TimelineItem[];
    register_types: Record<string, string>;
}

// ── Pagination ─────────────────────────────────────────────────────────────────
function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;
    return (
        <div className="flex flex-wrap gap-1 mt-3 justify-end">
            {links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        className={`px-3 py-1 text-xs rounded border ${link.active ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'}`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={i}
                        className="px-3 py-1 text-xs rounded border bg-gray-100 text-gray-400 cursor-not-allowed"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                )
            )}
        </div>
    );
}

// ── Section wrapper ────────────────────────────────────────────────────────────
function Section({ title, count, children }: { title: string; count?: number; children: React.ReactNode }) {
    return (
        <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-700">{title}</h3>
                {count !== undefined && (
                    <span className="text-xs text-gray-500">{count} record{count !== 1 ? 's' : ''}</span>
                )}
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

// ── Timeline dot ───────────────────────────────────────────────────────────────
const TYPE_DOT: Record<string, string> = {
    visit:      'bg-green-500',
    register:   'bg-yellow-500',
    opd:        'bg-blue-500',
    lab:        'bg-teal-500',
    referral:   'bg-cyan-500',
    sick_leave: 'bg-amber-500',
};

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function PatientHistory({
    queue_entry, patient, room_name,
    visits, registers, referrals, sick_leaves, opd_encounters,
    lab_results,
    timeline, register_types,
}: Props) {

    return (
        <AppLayout title="Patient History">
            <Head title={`History — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-4 mb-6 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-lg font-bold">Patient History — {patient.full_name}</h1>
                    <p className="text-green-100 text-sm font-mono mt-0.5">{patient.card_number}</p>
                </div>
                <div className="flex gap-3">
                    <a
                        href={`/opd/consultation/${queue_entry.id}`}
                        className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-semibold"
                    >
                        ← Back to Consultation
                    </a>
                </div>
            </div>

            {/* ── Timeline ─────────────────────────────────────────────── */}
            <Section title="Timeline" count={timeline.length}>
                {timeline.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-4">No history found.</p>
                ) : (
                    <ol className="relative border-l-2 border-gray-200 ml-3 space-y-6">
                        {timeline.map((item, i) => (
                            <li key={i} className="ml-6">
                                {/* Timeline dot */}
                                <span className={`absolute -left-2.5 w-4 h-4 rounded-full border-2 border-white ${TYPE_DOT[item.type] ?? 'bg-gray-400'}`} />

                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-gray-800">{item.title}</p>
                                        {item.detail && (
                                            <p className="text-xs text-gray-600 mt-0.5">{item.detail}</p>
                                        )}
                                        {item.meta && (
                                            <p className="text-xs text-gray-400 mt-0.5">{item.meta}</p>
                                        )}
                                    </div>
                                    <div className="flex flex-col items-end gap-1 shrink-0">
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${item.badge_color}`}>
                                            {item.badge}
                                        </span>
                                        <span className="text-xs text-gray-400">
                                            {item.date}{item.time ? ' · ' + item.time.substring(0, 5) : ''}
                                        </span>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </Section>

            {/* ── Previous Visits ──────────────────────────────────────── */}
            <Section title="Previous Visits" count={visits.total}>
                {visits.data.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No visits recorded.</p>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Date</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Room</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Queue</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Status</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visits.data.map((v) => (
                                        <tr key={v.id} className="border-b last:border-0">
                                            <td className="py-2 pr-4">{v.visit_date}</td>
                                            <td className="py-2 pr-4">{v.room?.room_name ?? '—'}</td>
                                            <td className="py-2 pr-4">{v.queue_number ? `#${v.queue_number}` : '—'}</td>
                                            <td className="py-2 pr-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                    {v.status}
                                                </span>
                                            </td>
                                            <td className="py-2 text-gray-500 text-xs">{v.remarks ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={visits.links} />
                    </>
                )}
            </Section>

            {/* ── Daily Register History ────────────────────────────────── */}
            <Section title="Register History" count={registers.total}>
                {registers.data.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No register entries.</p>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Date</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Type</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Department</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Referred From</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {registers.data.map((r) => (
                                        <tr key={r.id} className="border-b last:border-0">
                                            <td className="py-2 pr-4">{r.record_date}</td>
                                            <td className="py-2 pr-4">
                                                <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    {register_types[r.register_type] ?? r.register_type}
                                                </span>
                                            </td>
                                            <td className="py-2 pr-4 text-gray-600">{r.department_name ?? '—'}</td>
                                            <td className="py-2 pr-4 text-gray-600">{r.referred_from ?? '—'}</td>
                                            <td className="py-2 text-gray-600">{r.days_given ? `${r.days_given}d` : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={registers.links} />
                    </>
                )}
            </Section>

            {/* ── Referral History ──────────────────────────────────────── */}
            <Section title="Referral History" count={referrals.length}>
                {referrals.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No referrals recorded.</p>
                ) : (
                    <div className="space-y-3">
                        {referrals.map((r) => (
                            <div key={r.id} className="p-3 rounded-lg bg-teal-50 border border-teal-100">
                                <div className="flex items-start justify-between gap-3 mb-1">
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-100 text-teal-700">
                                            → {r.destination}
                                        </span>
                                        {r.doctor_nurse_name && (
                                            <span className="text-xs text-gray-600">Dr/Nurse: {r.doctor_nurse_name}</span>
                                        )}
                                    </div>
                                    <span className="text-xs text-gray-500 shrink-0">{r.date}</span>
                                </div>
                                {r.reason && (
                                    <p className="text-xs text-gray-700 mt-1"><span className="font-medium">Reason:</span> {r.reason}</p>
                                )}
                                {r.diagnosis && (
                                    <p className="text-xs text-gray-700 mt-0.5"><span className="font-medium">Diagnosis:</span> {r.diagnosis}</p>
                                )}
                                {r.requested_by && (
                                    <p className="text-xs text-gray-500 mt-1">By: {r.requested_by.full_name}</p>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </Section>

            {/* ── Sick Leave History ────────────────────────────────────── */}
            <Section title="Sick Leave History" count={sick_leaves.length}>
                {sick_leaves.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No sick leave records.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left pb-2 text-xs font-medium text-gray-500">Employee</th>
                                    <th className="text-left pb-2 text-xs font-medium text-gray-500">Days</th>
                                    <th className="text-left pb-2 text-xs font-medium text-gray-500">Start Date</th>
                                    <th className="text-left pb-2 text-xs font-medium text-gray-500">End Date</th>
                                    <th className="text-left pb-2 text-xs font-medium text-gray-500">Diagnosis</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sick_leaves.map((s) => (
                                    <tr key={s.id} className="border-b last:border-0">
                                        <td className="py-2 pr-4 font-medium text-gray-800">{s.employee_name}</td>
                                        <td className="py-2 pr-4 font-semibold text-amber-700">{s.days} day{s.days !== 1 ? 's' : ''}</td>
                                        <td className="py-2 pr-4 text-gray-600">{s.start_date}</td>
                                        <td className="py-2 pr-4 text-gray-600">{s.end_date}</td>
                                        <td className="py-2 text-gray-600 max-w-[200px] truncate">{s.diagnosis}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Section>

            {/* ── OPD Encounters ────────────────────────────────────────── */}
            <Section title="OPD Encounters" count={opd_encounters.total}>
                {opd_encounters.data.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No OPD encounters recorded.</p>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Date</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Room</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Queue #</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Status</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Duration</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Notes</th>
                                        <th className="text-left pb-2 text-xs font-medium text-gray-500">Vitals</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {opd_encounters.data.map((q) => {
                                        const arrivedAt  = new Date(q.arrived_at);
                                        const completedAt = q.completed_at ? new Date(q.completed_at) : null;
                                        const durationMins = completedAt
                                            ? Math.floor((completedAt.getTime() - arrivedAt.getTime()) / 60000)
                                            : null;
                                        return (
                                            <tr key={q.id} className="border-b last:border-0 align-top">
                                                <td className="py-2 pr-4">{arrivedAt.toLocaleDateString('en-GB')}</td>
                                                <td className="py-2 pr-4">{q.room?.room_name ?? '—'}</td>
                                                <td className="py-2 pr-4">#{q.queue_number}</td>
                                                <td className="py-2 pr-4">
                                                    <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                        {q.status}
                                                    </span>
                                                </td>
                                                <td className="py-2 pr-4 text-gray-600 text-xs">
                                                    {durationMins !== null ? `${durationMins}m` : '—'}
                                                </td>
                                                <td className="py-2 text-gray-500 text-xs max-w-xs">
                                                    {q.clinical_note ? (
                                                        <div className="space-y-0.5">
                                                            {q.clinical_note.chief_complaint && (
                                                                <p><span className="font-medium text-gray-600">CC:</span> {q.clinical_note.chief_complaint.substring(0, 80)}{q.clinical_note.chief_complaint.length > 80 ? '…' : ''}</p>
                                                            )}
                                                            {q.clinical_note.diagnosis && (
                                                                <p><span className="font-medium text-gray-600">Dx:</span> {q.clinical_note.diagnosis.substring(0, 80)}{q.clinical_note.diagnosis.length > 80 ? '…' : ''}</p>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-gray-300">—</span>
                                                    )}
                                                </td>
                                                <td className="py-2 text-gray-500 text-xs">
                                                    {q.clinical_note && (
                                                        <div className="space-y-0.5">
                                                            {q.clinical_note.temperature != null && <p>🌡 {q.clinical_note.temperature}°C</p>}
                                                            {q.clinical_note.systolic_bp != null && q.clinical_note.diastolic_bp != null && (
                                                                <p>BP {q.clinical_note.systolic_bp}/{q.clinical_note.diastolic_bp} mmHg</p>
                                                            )}
                                                            {q.clinical_note.pulse_rate != null && <p>♥ {q.clinical_note.pulse_rate} bpm</p>}
                                                            {q.clinical_note.spo2 != null && <p>O₂ {q.clinical_note.spo2}%</p>}
                                                            {q.clinical_note.weight != null && <p>{q.clinical_note.weight} kg</p>}
                                                            {q.clinical_note.bmi != null && <p>BMI {q.clinical_note.bmi}</p>}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={opd_encounters.links} />
                    </>
                )}
            </Section>

            {/* ── Laboratory Results ───────────────────────────────────── */}
            <Section title="Laboratory Results" count={lab_results.total}>
                {lab_results.data.length === 0 ? (
                    <p className="text-sm text-gray-400 text-center py-3">No completed laboratory results.</p>
                ) : (
                    <>
                        <div className="space-y-4">
                            {lab_results.data.map((req) => (
                                <div key={req.id} className="border rounded-lg overflow-hidden">
                                    {/* Request header */}
                                    <div className="px-4 py-2 bg-teal-50 border-b flex items-center justify-between flex-wrap gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-semibold text-teal-700">
                                                {req.tests.length} test{req.tests.length !== 1 ? 's' : ''}
                                            </span>
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                                ${req.priority === 'Urgent' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'}`}>
                                                {req.priority}
                                            </span>
                                            {req.requested_by && (
                                                <span className="text-xs text-gray-400">requested by {req.requested_by}</span>
                                            )}
                                        </div>
                                        <span className="text-xs text-gray-500">{req.request_date}</span>
                                    </div>

                                    {/* Results table */}
                                    <table className="w-full text-sm">
                                        <thead className="bg-gray-50 border-b">
                                            <tr>
                                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500 w-1/3">Test</th>
                                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500 w-1/4">Result</th>
                                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500">Remarks</th>
                                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500">Date</th>
                                                <th className="text-left px-4 py-2 text-xs font-medium text-gray-500">Performed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {req.tests.map((t) => (
                                                <tr key={t.id} className={`border-t ${t.result ? '' : 'opacity-50'}`}>
                                                    <td className="px-4 py-2 font-medium text-gray-800">{t.test_name}</td>
                                                    <td className="px-4 py-2 text-gray-700">
                                                        {t.result
                                                            ? <span className="font-semibold text-teal-700">{t.result.result}</span>
                                                            : <span className="text-gray-300 text-xs italic">Pending</span>
                                                        }
                                                    </td>
                                                    <td className="px-4 py-2 text-gray-500 text-xs">{t.result?.remarks ?? '—'}</td>
                                                    <td className="px-4 py-2 text-gray-500 text-xs">{t.result?.result_date ?? '—'}</td>
                                                    <td className="px-4 py-2 text-gray-500 text-xs">{t.result?.performed_by ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ))}
                        </div>
                        <Pagination links={lab_results.links} />
                    </>
                )}
            </Section>

            {/* ── Future sections placeholder ───────────────────────────── */}
            <div className="grid grid-cols-1 gap-4">
                {[{ icon: '💊', label: 'Pharmacy History', note: 'Coming in a future phase' }].map(
                    ({ icon, label, note }) => (
                        <div key={label} className="bg-white rounded-xl border shadow-sm p-5 text-center opacity-60">
                            <div className="text-3xl mb-2">{icon}</div>
                            <p className="text-sm font-semibold text-gray-700">{label}</p>
                            <p className="text-xs text-gray-400 mt-1">{note}</p>
                        </div>
                    )
                )}
            </div>
        </AppLayout>
    );
}
