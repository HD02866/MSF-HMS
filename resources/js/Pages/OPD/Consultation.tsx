import { Head, router, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState, useRef } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface QueueEntry {
    id: number;
    queue_number: number;
    status: string;
    arrived_at: string;
    called_at: string | null;
}

interface Patient {
    id: number;
    full_name: string;
    card_number: string;
    employee_no: string | null;
    insurance_no: string | null;
    gender: string | null;
    date_of_birth: string | null;
    age: number | null;
    phone: string | null;
    photo_url: string | null;
    patient_type: string | null;
    relationship_type: string | null;
}

interface VisitInfo {
    id: number;
    visit_date: string | null;
    visit_time: string | null;
    queue_number: number | null;
    remarks: string | null;
    status: string;
    room_name: string | null;
    assigned_by: string | null;
}

interface ClinicalNote {
    id: number;
    chief_complaint: string | null;
    history: string | null;
    physical_examination: string | null;
    diagnosis: string | null;
    treatment_plan: string | null;
    follow_up_instructions: string | null;
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
}

interface Attachment {
    id: number;
    original_name: string;
    url: string;
    mime_type: string;
    file_size: string;
    type: string;
    uploaded_by: string | null;
    uploaded_at: string;
}

interface Props {
    queue_entry: QueueEntry;
    patient: Patient;
    visit: VisitInfo;
    clinical_note: ClinicalNote | null;
    attachments: Attachment[];
    statuses: Record<string, string>;
    opd_rooms: { id: number; name: string; code: string }[];
}

// ── Info row ───────────────────────────────────────────────────────────────────
function InfoRow({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="flex justify-between items-baseline py-2 border-b last:border-0">
            <dt className="text-sm text-gray-500 shrink-0 w-36">{label}</dt>
            <dd className="text-sm font-medium text-gray-800 text-right">{value ?? '—'}</dd>
        </div>
    );
}

// ── Text area field ────────────────────────────────────────────────────────────
function NoteField({
    label,
    value,
    onChange,
    error,
    placeholder,
    rows = 3,
}: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    placeholder?: string;
    rows?: number;
}) {
    return (
        <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">{label}</label>
            <textarea
                rows={rows}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
            />
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}

// ── Vital signs abnormal ranges ────────────────────────────────────────────────
const VITAL_RANGES: Record<string, { low: number; high: number; unit: string }> = {
    temperature:      { low: 36.1, high: 37.5, unit: '°C' },
    systolic_bp:      { low: 90,   high: 140,  unit: 'mmHg' },
    diastolic_bp:     { low: 60,   high: 90,   unit: 'mmHg' },
    pulse_rate:       { low: 60,   high: 100,  unit: 'bpm' },
    respiratory_rate: { low: 12,   high: 20,   unit: '/min' },
    spo2:             { low: 95,   high: 100,  unit: '%' },
    random_blood_sugar: { low: 3.9, high: 11.1, unit: 'mmol/L' },
};

function isAbnormal(field: string, value: number | null): boolean {
    if (value == null) return false;
    const range = VITAL_RANGES[field];
    return range ? value < range.low || value > range.high : false;
}

// ── Vital signs input field ───────────────────────────────────────────────────
function VitalInput({
    label,
    name,
    value,
    onChange,
    unit,
    step = '1',
    error,
}: {
    label: string;
    name: string;
    value: string;
    onChange: (v: string) => void;
    unit: string;
    step?: string;
    error?: string;
}) {
    const numVal = value === '' ? null : parseFloat(value);
    const abnormal = isAbnormal(name, numVal);
    return (
        <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">{label}</label>
            <div className="relative">
                <input
                    type="number"
                    step={step}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="—"
                    className={`w-full rounded-lg border px-3 py-2 text-sm pr-16 focus:outline-none focus:ring-2 focus:ring-green-500 ${
                        abnormal ? 'border-red-400 bg-red-50 text-red-700 focus:ring-red-400' : 'border-gray-300'
                    }`}
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">{unit}</span>
            </div>
            {abnormal && (
                <p className="text-red-500 text-xs mt-0.5 font-medium">
                    ⚠ Outside normal range ({VITAL_RANGES[name].low}–{VITAL_RANGES[name].high} {unit})
                </p>
            )}
            {error && <p className="text-red-500 text-xs mt-0.5">{error}</p>}
        </div>
    );
}

// ── Clinical Notes Form ────────────────────────────────────────────────────────
function ClinicalNotesForm({
    entryId,
    existing,
    onDataChange,
}: {
    entryId: number;
    existing: ClinicalNote | null;
    onDataChange?: (note: ClinicalNote) => void;
}) {
    const { data, setData, post, processing, errors, wasSuccessful } = useForm({
        chief_complaint:        existing?.chief_complaint        ?? '',
        history:                existing?.history                ?? '',
        physical_examination:   existing?.physical_examination   ?? '',
        diagnosis:              existing?.diagnosis              ?? '',
        treatment_plan:         existing?.treatment_plan         ?? '',
        follow_up_instructions: existing?.follow_up_instructions ?? '',
        temperature:            existing?.temperature?.toString()            ?? '',
        systolic_bp:            existing?.systolic_bp?.toString()            ?? '',
        diastolic_bp:           existing?.diastolic_bp?.toString()           ?? '',
        pulse_rate:             existing?.pulse_rate?.toString()             ?? '',
        respiratory_rate:       existing?.respiratory_rate?.toString()       ?? '',
        spo2:                   existing?.spo2?.toString()                   ?? '',
        weight:                 existing?.weight?.toString()                 ?? '',
        height:                 existing?.height?.toString()                 ?? '',
        bmi:                    existing?.bmi?.toString()                    ?? '',
        random_blood_sugar:     existing?.random_blood_sugar?.toString()     ?? '',
    });

    // Auto-calculate BMI when weight or height changes
    function handleVitalChange(field: string, value: string) {
        setData(field, value);
        if (field === 'weight' || field === 'height') {
            const w = field === 'weight' ? parseFloat(value) : parseFloat(data.weight);
            const h = field === 'height' ? parseFloat(value) : parseFloat(data.height);
            if (w > 0 && h > 0) {
                const heightM = h / 100;
                setData('bmi', (w / (heightM * heightM)).toFixed(1));
            } else {
                setData('bmi', '');
            }
        }
    }

    function handleChange(field: keyof typeof data, value: string) {
        setData(field, value);
        onDataChange?.({
            id:                      existing?.id ?? 0,
            chief_complaint:         field === 'chief_complaint'        ? value : data.chief_complaint,
            history:                 field === 'history'                ? value : data.history,
            physical_examination:    field === 'physical_examination'   ? value : data.physical_examination,
            diagnosis:               field === 'diagnosis'              ? value : data.diagnosis,
            treatment_plan:          field === 'treatment_plan'         ? value : data.treatment_plan,
            follow_up_instructions:  field === 'follow_up_instructions' ? value : data.follow_up_instructions,
            temperature:             field === 'temperature'            ? parseFloat(value) || null : data.temperature ? parseFloat(data.temperature) : null,
            systolic_bp:             field === 'systolic_bp'            ? parseInt(value) || null : data.systolic_bp ? parseInt(data.systolic_bp) : null,
            diastolic_bp:            field === 'diastolic_bp'           ? parseInt(value) || null : data.diastolic_bp ? parseInt(data.diastolic_bp) : null,
            pulse_rate:              field === 'pulse_rate'             ? parseInt(value) || null : data.pulse_rate ? parseInt(data.pulse_rate) : null,
            respiratory_rate:        field === 'respiratory_rate'       ? parseInt(value) || null : data.respiratory_rate ? parseInt(data.respiratory_rate) : null,
            spo2:                    field === 'spo2'                   ? parseFloat(value) || null : data.spo2 ? parseFloat(data.spo2) : null,
            weight:                  field === 'weight'                 ? parseFloat(value) || null : data.weight ? parseFloat(data.weight) : null,
            height:                  field === 'height'                 ? parseFloat(value) || null : data.height ? parseFloat(data.height) : null,
            bmi:                     field === 'bmi'                    ? parseFloat(value) || null : data.bmi ? parseFloat(data.bmi) : null,
            random_blood_sugar:      field === 'random_blood_sugar'     ? parseFloat(value) || null : data.random_blood_sugar ? parseFloat(data.random_blood_sugar) : null,
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${entryId}/notes`, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {existing && (
                <div className="rounded-md bg-blue-50 border border-blue-200 px-4 py-2 text-xs text-blue-700">
                    A note already exists for this encounter. Saving will create a new version — the previous note is preserved.
                </div>
            )}

            {/* ── Vital Signs ───────────────────────────────────────── */}
            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <h4 className="text-sm font-semibold text-gray-700 mb-3">Vital Signs</h4>
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    <VitalInput label="Temperature" name="temperature" value={data.temperature} onChange={(v) => { setData('temperature', v); }} unit="°C" step="0.1" error={errors.temperature} />
                    <VitalInput label="Systolic BP" name="systolic_bp" value={data.systolic_bp} onChange={(v) => setData('systolic_bp', v)} unit="mmHg" error={errors.systolic_bp} />
                    <VitalInput label="Diastolic BP" name="diastolic_bp" value={data.diastolic_bp} onChange={(v) => setData('diastolic_bp', v)} unit="mmHg" error={errors.diastolic_bp} />
                    <VitalInput label="Pulse Rate" name="pulse_rate" value={data.pulse_rate} onChange={(v) => setData('pulse_rate', v)} unit="bpm" error={errors.pulse_rate} />
                    <VitalInput label="Respiratory Rate" name="respiratory_rate" value={data.respiratory_rate} onChange={(v) => setData('respiratory_rate', v)} unit="/min" error={errors.respiratory_rate} />
                    <VitalInput label="SpO₂" name="spo2" value={data.spo2} onChange={(v) => setData('spo2', v)} unit="%" step="0.1" error={errors.spo2} />
                    <VitalInput label="Weight" name="weight" value={data.weight} onChange={(v) => handleVitalChange('weight', v)} unit="kg" step="0.1" error={errors.weight} />
                    <VitalInput label="Height" name="height" value={data.height} onChange={(v) => handleVitalChange('height', v)} unit="cm" step="0.1" error={errors.height} />
                    <VitalInput label="BMI" name="bmi" value={data.bmi} onChange={(v) => setData('bmi', v)} unit="kg/m²" step="0.1" error={errors.bmi} />
                    <VitalInput label="Random Blood Sugar" name="random_blood_sugar" value={data.random_blood_sugar} onChange={(v) => setData('random_blood_sugar', v)} unit="mmol/L" step="0.1" error={errors.random_blood_sugar} />
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <NoteField
                    label="Chief Complaint"
                    value={data.chief_complaint}
                    onChange={(v) => handleChange('chief_complaint', v)}
                    error={errors.chief_complaint}
                    placeholder="Main reason for the visit…"
                    rows={3}
                />
                <NoteField
                    label="History"
                    value={data.history}
                    onChange={(v) => handleChange('history', v)}
                    error={errors.history}
                    placeholder="Medical and relevant history…"
                    rows={3}
                />
                <NoteField
                    label="Physical Examination"
                    value={data.physical_examination}
                    onChange={(v) => handleChange('physical_examination', v)}
                    error={errors.physical_examination}
                    placeholder="Findings from examination…"
                    rows={3}
                />
                <NoteField
                    label="Diagnosis"
                    value={data.diagnosis}
                    onChange={(v) => handleChange('diagnosis', v)}
                    error={errors.diagnosis}
                    placeholder="Provisional or confirmed diagnosis…"
                    rows={3}
                />
                <NoteField
                    label="Treatment Plan"
                    value={data.treatment_plan}
                    onChange={(v) => handleChange('treatment_plan', v)}
                    error={errors.treatment_plan}
                    placeholder="Prescribed medications or interventions…"
                    rows={3}
                />
                <NoteField
                    label="Follow-up Instructions"
                    value={data.follow_up_instructions}
                    onChange={(v) => handleChange('follow_up_instructions', v)}
                    error={errors.follow_up_instructions}
                    placeholder="Return visit, referral instructions…"
                    rows={3}
                />
            </div>

            <div className="flex items-center gap-3 pt-1">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : '💾 Save Clinical Note'}
                </Button>
                {wasSuccessful && (
                    <span className="text-sm text-green-700 font-medium">✓ Saved</span>
                )}
            </div>
        </form>
    );
}

// ── Attachment type icon ───────────────────────────────────────────────────────
function typeIcon(type: string): string {
    switch (type) {
        case 'image':    return '🖼';
        case 'pdf':      return '📄';
        case 'document': return '📝';
        default:         return '📎';
    }
}

// ── Attachments upload + list ──────────────────────────────────────────────────
function AttachmentsSection({
    entryId,
    attachments,
}: {
    entryId: number;
    attachments: Attachment[];
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState('');
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const files = e.target.files;
        if (files) setSelectedFiles(Array.from(files));
    }

    function upload(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedFiles.length) return;
        setUploading(true);
        setUploadError('');

        const formData = new FormData();
        selectedFiles.forEach((f) => formData.append('attachments[]', f));

        router.post(`/opd/consultation/${entryId}/attachments`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setSelectedFiles([]);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
            onError: (e) => setUploadError(Object.values(e).join(' ')),
            onFinish: () => setUploading(false),
        });
    }

    return (
        <div className="space-y-4">
            {/* Upload form */}
            <form onSubmit={upload} className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[240px]">
                    <label className="block text-xs font-medium text-gray-600 mb-1">
                        Select files (images, PDF, documents — max 10 MB each)
                    </label>
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        accept="image/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.csv"
                        onChange={handleFileChange}
                        className="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-gray-300 file:text-sm file:font-medium file:bg-white hover:file:bg-gray-50 cursor-pointer"
                    />
                {uploadError && <p className="text-red-500 text-xs mt-1">{uploadError}</p>}
                </div>
                {selectedFiles.length > 0 && (
                    <div className="text-xs text-gray-500">
                        {selectedFiles.length} file{selectedFiles.length > 1 ? 's' : ''} selected
                    </div>
                )}
                <Button type="submit" disabled={uploading || !selectedFiles.length}>
                    {uploading ? 'Uploading…' : '⬆ Upload'}
                </Button>
            </form>

            {/* Attachment list */}
            {attachments.length === 0 ? (
                <p className="text-sm text-gray-400 text-center py-4">No attachments yet.</p>
            ) : (
                <div className="divide-y rounded-lg border overflow-hidden">
                    {attachments.map((att) => (
                        <div key={att.id} className="flex items-center justify-between gap-3 px-4 py-3 bg-white hover:bg-gray-50">
                            <div className="flex items-center gap-3 min-w-0">
                                <span className="text-xl shrink-0">{typeIcon(att.type)}</span>
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-gray-800 truncate">{att.original_name}</p>
                                    <p className="text-xs text-gray-400">
                                        {att.file_size} · {att.uploaded_by ?? '—'} · {new Date(att.uploaded_at).toLocaleDateString('en-GB')}
                                    </p>
                                </div>
                            </div>
                            <a
                                href={`/opd/consultation/${entryId}/attachments/${att.id}/download`}
                                className="shrink-0 px-3 py-1.5 text-xs font-medium rounded border border-green-200 bg-green-50 text-green-700 hover:bg-green-100"
                            >
                                ⬇ Download
                            </a>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// ── End consultation actions ───────────────────────────────────────────────────
function EndConsultation({
    entryId,
    currentNote,
    opdRooms,
}: {
    entryId: number;
    currentNote: ClinicalNote | null;
    opdRooms: { id: number; name: string; code: string }[];
}) {
    const [processing, setProcessing] = useState(false);
    const [selectedStatus, setSelectedStatus] = useState('');
    const [destinationRoomId, setDestinationRoomId] = useState('');

    function submit(status: string) {
        if (processing) return;
        if (status === 'Completed') {
            if (!confirm('Mark this consultation as Completed? The current note will be saved with the encounter record.')) return;
        } else if (status === 'Cancelled') {
            if (!confirm('Cancel this consultation? This action cannot be undone.')) return;
        } else if (status === 'Transferred') {
            if (!destinationRoomId) {
                alert('Please select a destination room before transferring.');
                return;
            }
            if (!confirm('Transfer this patient to the selected room?')) return;
        }
        setProcessing(true);
        router.post(
            `/opd/consultation/${entryId}/complete`,
            {
                status,
                ...(status === 'Transferred' && destinationRoomId
                    ? { destination_room_id: parseInt(destinationRoomId) }
                    : {}),
                // Pass current note fields so they are saved atomically on Completed
                ...(status === 'Completed' && currentNote
                    ? {
                        chief_complaint:        currentNote.chief_complaint        ?? '',
                        history:                currentNote.history                ?? '',
                        physical_examination:   currentNote.physical_examination   ?? '',
                        diagnosis:              currentNote.diagnosis              ?? '',
                        treatment_plan:         currentNote.treatment_plan         ?? '',
                        follow_up_instructions: currentNote.follow_up_instructions ?? '',
                        temperature:            currentNote.temperature            ?? '',
                        systolic_bp:            currentNote.systolic_bp            ?? '',
                        diastolic_bp:           currentNote.diastolic_bp           ?? '',
                        pulse_rate:             currentNote.pulse_rate             ?? '',
                        respiratory_rate:       currentNote.respiratory_rate       ?? '',
                        spo2:                   currentNote.spo2                   ?? '',
                        weight:                 currentNote.weight                 ?? '',
                        height:                 currentNote.height                 ?? '',
                        bmi:                    currentNote.bmi                    ?? '',
                        random_blood_sugar:     currentNote.random_blood_sugar     ?? '',
                    }
                    : {}),
            },
            { onFinish: () => setProcessing(false) }
        );
    }

    return (
        <div className="space-y-3">
            <p className="text-xs text-gray-500">
                Completing the consultation will save the current clinical note and mark the visit as Completed.
                Previous notes and attachments are preserved.
            </p>
            <div className="flex flex-wrap gap-3">
                <Button type="button" disabled={processing} onClick={() => submit('Completed')}>
                    ✓ Complete Consultation
                </Button>
                <Button type="button" variant="secondary" disabled={processing} onClick={() => {
                    if (!selectedStatus || selectedStatus === 'Completed') {
                        setSelectedStatus('Transferred');
                    }
                    submit('Transferred');
                }}>
                    ↗ Transfer Patient
                </Button>
                <Button type="button" variant="danger" disabled={processing} onClick={() => submit('Cancelled')}>
                    ✕ Cancel
                </Button>
            </div>
            {/* Destination room selector — always visible for Transfer */}
            <div className="mt-3">
                <label className="block text-xs font-medium text-gray-600 mb-1">
                    Destination Room (required for Transfer)
                </label>
                <select
                    value={destinationRoomId}
                    onChange={(e) => {
                        setDestinationRoomId(e.target.value);
                        if (e.target.value) setSelectedStatus('Transferred');
                    }}
                    className="w-full max-w-sm rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    <option value="">— Select destination —</option>
                    {opdRooms.map((room) => (
                        <option key={room.id} value={room.id}>
                            {room.name} ({room.code})
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function OpdConsultation({ queue_entry, patient, visit, clinical_note, attachments, opd_rooms }: Props) {
    const arrivedTime = new Date(queue_entry.arrived_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    const calledTime  = queue_entry.called_at
        ? new Date(queue_entry.called_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
        : '—';

    // Track the live note state so "Complete Consultation" can submit the latest values
    const [liveNote, setLiveNote] = useState<ClinicalNote | null>(clinical_note);

    return (
        <AppLayout title="OPD Consultation">
            <Head title={`Consultation — ${patient.full_name}`} />

            {/* Header banner */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-14 h-14 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-xl shrink-0">
                        #{queue_entry.queue_number}
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">{patient.full_name}</h1>
                        <p className="text-green-100 text-sm font-mono mt-0.5">{patient.card_number}</p>
                    </div>
                </div>
                <span className="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-green-600 border border-green-500">
                    🟢 {queue_entry.status}
                </span>
            </div>

            {/* Patient info + Visit info */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <div className="flex items-start gap-4 mb-4">
                        {patient.photo_url ? (
                            <img src={patient.photo_url} alt={patient.full_name}
                                className="w-16 h-16 rounded-full object-cover border-2 border-green-200 shrink-0" />
                        ) : (
                            <div className="w-16 h-16 rounded-full bg-green-100 border-2 border-green-200 flex items-center justify-center shrink-0">
                                <span className="text-green-700 text-xl font-bold">{patient.full_name[0]?.toUpperCase()}</span>
                            </div>
                        )}
                        <div>
                            <h2 className="font-semibold text-gray-800 text-lg leading-tight">{patient.full_name}</h2>
                            <p className="text-xs text-gray-500 mt-0.5">{patient.patient_type ?? '—'}</p>
                        </div>
                    </div>
                    <dl>
                        <InfoRow label="Card Number"   value={patient.card_number} />
                        <InfoRow label="Employee No."  value={patient.employee_no} />
                        <InfoRow label="Insurance No." value={patient.insurance_no} />
                        <InfoRow label="Patient Type"  value={patient.patient_type} />
                        <InfoRow label="Relationship"  value={patient.relationship_type} />
                        <InfoRow label="Gender"        value={patient.gender} />
                        <InfoRow label="Age"           value={patient.age != null ? `${patient.age} years` : null} />
                        <InfoRow label="Date of Birth" value={patient.date_of_birth} />
                        <InfoRow label="Phone"         value={patient.phone} />
                    </dl>
                </div>

                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h2 className="font-semibold text-gray-800 mb-4">Current Visit</h2>
                    <dl>
                        <InfoRow label="Visit Date"   value={visit.visit_date} />
                        <InfoRow label="Visit Time"   value={visit.visit_time ? visit.visit_time.substring(0, 5) : null} />
                        <InfoRow label="Room"         value={visit.room_name} />
                        <InfoRow label="Queue No."    value={visit.queue_number != null ? `#${visit.queue_number}` : null} />
                        <InfoRow label="Arrived"      value={arrivedTime} />
                        <InfoRow label="Called"       value={calledTime} />
                        <InfoRow label="Assigned By"  value={visit.assigned_by} />
                        <InfoRow label="Visit Status" value={visit.status} />
                    </dl>
                    {visit.remarks && (
                        <div className="mt-3 pt-3 border-t">
                            <p className="text-xs text-gray-500 mb-1">Remarks</p>
                            <p className="text-sm text-gray-700 bg-gray-50 rounded-lg p-2">{visit.remarks}</p>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Clinical Notes ─────────────────────────────────────── */}
            <div className="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <div className="flex items-center justify-between mb-4 flex-wrap gap-3">
                    <h3 className="font-semibold text-gray-800">Clinical Notes</h3>
                    <div className="flex items-center gap-3">
                        <a
                            href={`/opd/consultation/${queue_entry.id}/lab`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100"
                        >
                            🔬 Request Laboratory
                        </a>
                        <a
                            href={`/opd/consultation/${queue_entry.id}/prescription`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100"
                        >
                            💊 Prescribe Medicine
                        </a>
                        <a
                            href={`/opd/consultation/${queue_entry.id}/consultation-request`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-orange-50 text-orange-700 border border-orange-200 hover:bg-orange-100"
                        >
                            📋 Request Consultation
                        </a>
                        <a
                            href={`/opd/consultation/${queue_entry.id}/referral`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100"
                        >
                            🏥 Create Referral
                        </a>
                        <a
                            href={`/opd/consultation/${queue_entry.id}/sick-leave`}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100"
                        >
                            📄 Sick Leave
                        </a>
                        <a
                            href={`/opd/consultation/${queue_entry.id}/history`}
                            className="text-sm text-green-700 font-medium hover:underline"
                        >
                            📋 View Full Patient History →
                        </a>
                    </div>
                </div>
                <ClinicalNotesForm
                    entryId={queue_entry.id}
                    existing={clinical_note}
                    onDataChange={setLiveNote}
                />
            </div>

            {/* ── Attachments ────────────────────────────────────────── */}
            <div className="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <h3 className="font-semibold text-gray-800 mb-4">Attachments</h3>
                <AttachmentsSection entryId={queue_entry.id} attachments={attachments} />
            </div>

            {/* ── End Consultation ────────────────────────────────────── */}
            <div className="bg-white rounded-xl border shadow-sm p-5">
                <h3 className="text-sm font-semibold text-gray-700 mb-4">End Consultation</h3>
                <EndConsultation entryId={queue_entry.id} currentNote={liveNote} opdRooms={opd_rooms} />
            </div>
        </AppLayout>
    );
}
