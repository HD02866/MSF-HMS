import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState, useCallback } from 'react';

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

interface Medicine {
    id: number;
    name: string;
    generic_name: string | null;
    form: string | null;
    unit: string;
    unit_price: number;
    quantity_in_stock: number;
    minimum_stock_level: number;
}

interface PrescriptionItem {
    medicine_id: number | null;
    medicine_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    quantity: number;
    notes: string;
    stock_warning: string | null;
}

interface PriorPrescription {
    id: number;
    request_date: string;
    prescriber_name: string | null;
    is_external: boolean;
    clinical_notes: string | null;
    created_at: string;
    items: { id: number; medicine_name: string; dosage: string | null; frequency: string | null; quantity: number }[];
    queue_status: string;
}

interface Props {
    queue_entry: QueueEntry;
    patient: Patient;
    room_name: string | null;
    today: string;
    prescriber_name: string;
    medicines: Medicine[];
    prior_prescriptions: PriorPrescription[];
}

function StockBadge({ medicine, quantity }: { medicine: Medicine; quantity: number }) {
    if (medicine.quantity_in_stock === 0) {
        return <span className="text-xs text-red-600 font-medium">Out of stock</span>;
    }
    if (medicine.quantity_in_stock < quantity) {
        return <span className="text-xs text-orange-600 font-medium">Insufficient (have {medicine.quantity_in_stock})</span>;
    }
    if (medicine.quantity_in_stock <= medicine.minimum_stock_level) {
        return <span className="text-xs text-yellow-600">Low stock ({medicine.quantity_in_stock})</span>;
    }
    return <span className="text-xs text-green-600">In stock ({medicine.quantity_in_stock})</span>;
}

function PriorPrescriptions({ prescriptions }: { prescriptions: PriorPrescription[] }) {
    if (prescriptions.length === 0) return null;

    return (
        <div className="bg-white rounded-xl border shadow-sm overflow-hidden mb-6">
            <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-gray-700">Previous Prescriptions This Encounter</h3>
                <span className="text-xs text-gray-500">{prescriptions.length} prescription{prescriptions.length !== 1 ? 's' : ''}</span>
            </div>
            <div className="divide-y">
                {prescriptions.map((rx) => (
                    <div key={rx.id} className="px-5 py-4">
                        <div className="flex items-start justify-between gap-3 flex-wrap mb-2">
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-gray-500">{rx.request_date}</span>
                                {rx.prescriber_name && <span className="text-xs text-gray-400">by {rx.prescriber_name}</span>}
                                {rx.is_external && <span className="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded">External</span>}
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                    ${rx.queue_status === 'Dispensed' ? 'bg-teal-100 text-teal-700'
                                        : rx.queue_status === 'Cancelled' ? 'bg-red-100 text-red-600'
                                        : 'bg-yellow-100 text-yellow-700'}`}>
                                    {rx.queue_status}
                                </span>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {rx.items.map((item) => (
                                <span key={item.id} className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs border bg-purple-50 text-purple-700 border-purple-200">
                                    {item.medicine_name}
                                    {item.dosage && <span className="text-purple-400">· {item.dosage}</span>}
                                    {item.frequency && <span className="text-purple-400">· {item.frequency}</span>}
                                    <span className="text-purple-400">× {item.quantity}</span>
                                </span>
                            ))}
                        </div>
                        {rx.clinical_notes && (
                            <p className="text-xs text-gray-500 bg-gray-50 rounded-md px-3 py-2 mt-2">{rx.clinical_notes}</p>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Prescription({
    queue_entry, patient, room_name,
    today, prescriber_name: defaultPrescriberName,
    medicines, prior_prescriptions,
}: Props) {
    const [items, setItems] = useState<PrescriptionItem[]>([
        { medicine_id: null, medicine_name: '', dosage: '', frequency: '', duration: '', quantity: 1, notes: '', stock_warning: null },
    ]);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<Medicine[]>([]);
    const [searchingItemIndex, setSearchingItemIndex] = useState<number | null>(null);

    const { data, setData, post, processing, errors } = useForm<{
        prescriber_name: string;
        request_date: string;
        clinical_notes: string;
        is_external: boolean;
        external_notes: string;
        items: { medicine_id: number | null; medicine_name: string; dosage: string; frequency: string; duration: string; quantity: number; notes: string }[];
    }>({
        prescriber_name: defaultPrescriberName,
        request_date:   today,
        clinical_notes: '',
        is_external:    false,
        external_notes: '',
        items:          [{ medicine_id: null, medicine_name: '', dosage: '', frequency: '', duration: '', quantity: 1, notes: '' }],
    });

    const searchMedicines = useCallback(async (query: string, itemIndex: number) => {
        if (query.length < 2) {
            setSearchResults([]);
            setSearchingItemIndex(null);
            return;
        }
        setSearchingItemIndex(itemIndex);
        try {
            const res = await fetch(`/pharmacy/medicines/search?q=${encodeURIComponent(query)}`);
            const data = await res.json();
            setSearchResults(data);
        } catch {
            setSearchResults([]);
        }
    }, []);

    function selectMedicine(medicine: Medicine, itemIndex: number) {
        const next = [...items];
        next[itemIndex] = {
            ...next[itemIndex],
            medicine_id: medicine.id,
            medicine_name: medicine.name,
            dosage: medicine.form ?? '',
            stock_warning: null,
        };
        setItems(next);

        const formData = [...data.items];
        formData[itemIndex] = {
            ...formData[itemIndex],
            medicine_id: medicine.id,
            medicine_name: medicine.name,
            dosage: medicine.form ?? '',
        };
        setData('items', formData);

        setSearchResults([]);
        setSearchingItemIndex(null);
        setSearchQuery('');
    }

    function updateItem(index: number, field: keyof PrescriptionItem, value: string | number) {
        const next = [...items];
        (next[index] as any)[field] = value;

        if (field === 'quantity' && next[index].medicine_id) {
            const med = medicines.find((m) => m.id === next[index].medicine_id);
            if (med && (value as number) > med.quantity_in_stock) {
                next[index].stock_warning = `Only ${med.quantity_in_stock} available`;
            } else {
                next[index].stock_warning = null;
            }
        }

        setItems(next);

        const formData = [...data.items];
        (formData[index] as any)[field] = value;
        setData('items', formData);
    }

    function addItem() {
        const emptyItem: PrescriptionItem = { medicine_id: null, medicine_name: '', dosage: '', frequency: '', duration: '', quantity: 1, notes: '', stock_warning: null };
        setItems([...items, emptyItem]);
        setData('items', [...data.items, { medicine_id: null, medicine_name: '', dosage: '', frequency: '', duration: '', quantity: 1, notes: '' }]);
    }

    function removeItem(index: number) {
        if (items.length <= 1) return;
        const next = items.filter((_, i) => i !== index);
        setItems(next);
        setData('items', data.items.filter((_, i) => i !== index));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/opd/consultation/${queue_entry.id}/prescription`, { preserveScroll: true });
    }

    const hasStockWarnings = items.some((item) => item.stock_warning !== null);
    const totalItems = items.filter((i) => i.medicine_name.trim()).length;

    return (
        <AppLayout title="Prescription">
            <Head title={`Prescription — ${patient.full_name}`} />

            {/* Header */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-6 flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-lg shrink-0">
                        💊
                    </div>
                    <div>
                        <h1 className="text-xl font-bold leading-tight">Prescription</h1>
                        <p className="text-green-100 text-sm mt-0.5">
                            {patient.full_name}
                            <span className="font-mono ml-2 opacity-80">{patient.card_number}</span>
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-green-100">
                        {room_name ?? '—'} · Queue #{queue_entry.queue_number}
                    </span>
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

            {/* Prior prescriptions */}
            <PriorPrescriptions prescriptions={prior_prescriptions} />

            {/* Prescription form */}
            <form onSubmit={submit} className="space-y-6">

                {/* Request meta */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="font-semibold text-gray-800 mb-4">Prescription Details</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">
                                Request Date <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.request_date}
                                onChange={(e) => setData('request_date', e.target.value)}
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                            {errors.request_date && <p className="text-red-500 text-xs mt-1">{errors.request_date}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Prescriber Name</label>
                            <input
                                type="text"
                                value={data.prescriber_name}
                                onChange={(e) => setData('prescriber_name', e.target.value)}
                                placeholder="Doctor / Nurse name"
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-gray-600 mb-1">Prescription Type</label>
                            <label className="flex items-center gap-2 mt-1 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_external}
                                    onChange={(e) => setData('is_external', e.target.checked)}
                                    className="accent-orange-500 w-4 h-4"
                                />
                                <span className="text-sm text-gray-700">External (medicine not in inventory)</span>
                            </label>
                        </div>
                    </div>
                    {data.is_external && (
                        <div className="mt-3">
                            <label className="block text-xs font-medium text-gray-600 mb-1">External Notes</label>
                            <input
                                type="text"
                                value={data.external_notes}
                                onChange={(e) => setData('external_notes', e.target.value)}
                                placeholder="Reason for external prescription (e.g. not available in stock)"
                                className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                            />
                        </div>
                    )}
                </div>

                {/* Medicine items */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h3 className="font-semibold text-gray-800">Medicines</h3>
                            <p className="text-xs text-gray-400 mt-0.5">Search by name or type manually</p>
                        </div>
                        <button
                            type="button"
                            onClick={addItem}
                            className="text-sm text-green-700 font-medium hover:underline"
                        >
                            + Add Medicine
                        </button>
                    </div>

                    {errors.items && (
                        <div className="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-xs text-red-700">
                            {errors.items}
                        </div>
                    )}

                    <div className="space-y-4">
                        {items.map((item, index) => (
                            <div key={index} className="border rounded-lg p-4 relative">
                                {items.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => removeItem(index)}
                                        className="absolute top-2 right-2 text-gray-400 hover:text-red-500 text-sm"
                                    >
                                        ✕
                                    </button>
                                )}

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                    {/* Medicine search */}
                                    <div className="sm:col-span-2 relative">
                                        <label className="block text-xs font-medium text-gray-600 mb-1">
                                            Medicine <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={searchingItemIndex === index ? searchQuery : item.medicine_name}
                                            onChange={(e) => {
                                                setSearchQuery(e.target.value);
                                                updateItem(index, 'medicine_name', e.target.value);
                                                searchMedicines(e.target.value, index);
                                            }}
                                            placeholder="Search medicine name..."
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        />
                                        {searchingItemIndex === index && searchResults.length > 0 && (
                                            <div className="absolute z-10 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                                {searchResults.map((med) => (
                                                    <button
                                                        key={med.id}
                                                        type="button"
                                                        onClick={() => selectMedicine(med, index)}
                                                        className="w-full text-left px-3 py-2 hover:bg-green-50 text-sm border-b last:border-0"
                                                    >
                                                        <span className="font-medium">{med.name}</span>
                                                        {med.generic_name && <span className="text-gray-400 ml-1">({med.generic_name})</span>}
                                                        <span className="text-xs text-gray-400 ml-2">{med.form} · {med.unit}</span>
                                                        <span className={`text-xs ml-2 ${med.quantity_in_stock > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                            Stock: {med.quantity_in_stock}
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    {/* Dosage */}
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Dosage</label>
                                        <input
                                            type="text"
                                            value={item.dosage}
                                            onChange={(e) => updateItem(index, 'dosage', e.target.value)}
                                            placeholder="e.g. 500mg"
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        />
                                    </div>

                                    {/* Quantity */}
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">
                                            Quantity <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            min={1}
                                            value={item.quantity}
                                            onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 1)}
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        />
                                        {item.medicine_id && (
                                            <StockBadge
                                                medicine={medicines.find((m) => m.id === item.medicine_id)!}
                                                quantity={item.quantity}
                                            />
                                        )}
                                        {item.stock_warning && (
                                            <p className="text-xs text-orange-600 mt-0.5">⚠ {item.stock_warning}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                                    {/* Frequency */}
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Frequency</label>
                                        <select
                                            value={item.frequency}
                                            onChange={(e) => updateItem(index, 'frequency', e.target.value)}
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        >
                                            <option value="">Select...</option>
                                            <option value="Once daily">Once daily</option>
                                            <option value="Twice daily">Twice daily</option>
                                            <option value="Three times daily">Three times daily</option>
                                            <option value="Every 8 hours">Every 8 hours</option>
                                            <option value="Every 6 hours">Every 6 hours</option>
                                            <option value="Every 4 hours">Every 4 hours</option>
                                            <option value="As needed">As needed (PRN)</option>
                                            <option value="At bedtime">At bedtime</option>
                                            <option value="Before meals">Before meals</option>
                                            <option value="After meals">After meals</option>
                                        </select>
                                    </div>

                                    {/* Duration */}
                                    <div>
                                        <label className="block text-xs font-medium text-gray-600 mb-1">Duration</label>
                                        <select
                                            value={item.duration}
                                            onChange={(e) => updateItem(index, 'duration', e.target.value)}
                                            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                        >
                                            <option value="">Select...</option>
                                            <option value="1 day">1 day</option>
                                            <option value="3 days">3 days</option>
                                            <option value="5 days">5 days</option>
                                            <option value="7 days">7 days</option>
                                            <option value="10 days">10 days</option>
                                            <option value="14 days">14 days</option>
                                            <option value="21 days">21 days</option>
                                            <option value="1 month">1 month</option>
                                            <option value="2 months">2 months</option>
                                            <option value="3 months">3 months</option>
                                            <option value="Ongoing">Ongoing</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Notes */}
                                <div className="mt-3">
                                    <input
                                        type="text"
                                        value={item.notes}
                                        onChange={(e) => updateItem(index, 'notes', e.target.value)}
                                        placeholder="Additional instructions (optional)"
                                        className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Clinical notes */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <label className="block text-sm font-semibold text-gray-700 mb-2">
                        Clinical Notes
                        <span className="ml-1 text-xs font-normal text-gray-400">(optional)</span>
                    </label>
                    <textarea
                        rows={3}
                        value={data.clinical_notes}
                        onChange={(e) => setData('clinical_notes', e.target.value)}
                        placeholder="Additional clinical context for the pharmacist..."
                        className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-y"
                    />
                    {errors.clinical_notes && <p className="text-red-500 text-xs mt-1">{errors.clinical_notes}</p>}
                </div>

                {/* Submit */}
                <div className="bg-white rounded-xl border shadow-sm p-5 flex items-center justify-between flex-wrap gap-4">
                    <div className="text-xs text-gray-400">
                        {totalItems === 0
                            ? 'Add at least one medicine to submit.'
                            : `${totalItems} medicine${totalItems !== 1 ? 's' : ''} · ${data.request_date}`
                        }
                        {hasStockWarnings && <span className="ml-2 text-orange-500">⚠ Some items have stock warnings</span>}
                    </div>
                    <div className="flex gap-3">
                        <a
                            href={`/opd/consultation/${queue_entry.id}`}
                            className="px-4 py-2 rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                        >
                            Back to Consultation
                        </a>
                        <Button
                            type="submit"
                            disabled={processing || totalItems === 0}
                        >
                            {processing ? 'Submitting…' : '💊 Submit Prescription'}
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
