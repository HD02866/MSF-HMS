import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import FormError from '@/components/FormError';
import { useMemo, useRef, useState } from 'react';

interface Props {
    patientTypes: Array<{ id: number; name: string }>;
    relationshipTypes: Array<{ id: number; name: string }>;
}

export default function CreatePatient({ patientTypes, relationshipTypes }: Props) {
    // ── Inertia useForm — handles redirect, server errors, and file uploads ──
    const { data, setData, post, processing, errors, reset } = useForm<{
        patient_type_id: string;
        relationship_type_id: string;
        employee_no: string;
        insurance_no: string;
        dependent_no: string;
        full_name: string;
        gender: string;
        date_of_birth: string;
        phone: string;
        address: string;
        woreda: string;
        kebele: string;
        house_no: string;
        assign_room: boolean;
        photo: File | null;
        os_card_number: string;
    }>({
        patient_type_id: '',
        relationship_type_id: '',
        employee_no: '',
        insurance_no: '',
        dependent_no: '0',
        full_name: '',
        gender: '',
        date_of_birth: '',
        phone: '',
        address: '',
        woreda: '',
        kebele: '',
        house_no: '',
        assign_room: false,
        photo: null,
        os_card_number: '',
    });

    // Photo preview state
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);

    function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setData('photo', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => setPhotoPreview(ev.target?.result as string);
            reader.readAsDataURL(file);
        } else {
            setPhotoPreview(null);
        }
    }

    const selectedType = useMemo(
        () => patientTypes.find((t) => String(t.id) === String(data.patient_type_id)),
        [data.patient_type_id, patientTypes],
    );

    const showEmployee  = selectedType && ['Employee', 'Family'].includes(selectedType.name);
    const showInsurance = selectedType?.name === 'Insurance';
    // Show a manual card number field for OS and all other non-employee, non-insurance types
    const showOsCard    = selectedType && !['Employee', 'Family', 'Insurance'].includes(selectedType.name);

    function submit(assignRoom: boolean) {
        // Pass assign_room directly to post() to avoid React state batching race condition
        post('/patients', {
            forceFormData: true,
            data: { ...data, assign_room: assignRoom },
        } as any);
    }

    return (
        <AppLayout title="Create Patient Card">
            <Head title="Create Patient" />

            <div className="max-w-3xl space-y-6">

                {/* Identity */}
                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Identity</h3>

                    {/* Photo upload + preview */}
                    <div className="flex items-start gap-4">
                        <div className="flex flex-col items-center gap-2">
                            <div
                                className="w-24 h-28 rounded border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden cursor-pointer hover:border-green-400 transition-colors"
                                onClick={() => photoInputRef.current?.click()}
                                title="Click to upload photo"
                            >
                                {photoPreview ? (
                                    <img src={photoPreview} alt="Preview" className="w-full h-full object-cover" />
                                ) : (
                                    <div className="text-center px-1">
                                        <div className="text-2xl text-gray-300">📷</div>
                                        <p className="text-xs text-gray-400 mt-1">Photo<br/>(optional)</p>
                                    </div>
                                )}
                            </div>
                            <input
                                ref={photoInputRef}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={handlePhotoChange}
                            />
                            {photoPreview && (
                                <button
                                    type="button"
                                    onClick={() => { setPhotoPreview(null); setData('photo', null); if (photoInputRef.current) photoInputRef.current.value = ''; }}
                                    className="text-xs text-red-500 hover:underline"
                                >
                                    Remove
                                </button>
                            )}
                            <FormError message={errors.photo} />
                        </div>

                        <div className="flex-1 space-y-3">
                            <div>
                                <Input
                                    placeholder="Full Name *"
                                    value={data.full_name}
                                    onChange={(e) => setData('full_name', e.target.value)}
                                />
                                <FormError message={errors.full_name} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Select value={data.gender} onChange={(e) => setData('gender', e.target.value)}>
                                        <option value="">Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </Select>
                                    <FormError message={errors.gender} />
                                </div>
                                <div>
                                    <Input
                                        type="date"
                                        value={data.date_of_birth}
                                        onChange={(e) => setData('date_of_birth', e.target.value)}
                                    />
                                    <FormError message={errors.date_of_birth} />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Patient Category */}
                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Patient Category</h3>
                    <Select value={data.patient_type_id} onChange={(e) => setData('patient_type_id', e.target.value)}>
                        <option value="">Select type *</option>
                        {patientTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </Select>
                    <FormError message={errors.patient_type_id} />
                </section>

                {/* Employee / Family fields */}
                {showEmployee && (
                    <section className="bg-white rounded-lg border p-4 space-y-3">
                        <h3 className="font-semibold text-gray-800">Employee Information</h3>
                        <div>
                            <Input
                                placeholder="Employee Number"
                                value={data.employee_no}
                                onChange={(e) => setData('employee_no', e.target.value)}
                            />
                            <FormError message={errors.employee_no} />
                        </div>
                        <div>
                            <Select value={data.relationship_type_id} onChange={(e) => setData('relationship_type_id', e.target.value)}>
                                <option value="">Relationship</option>
                                {relationshipTypes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </Select>
                            <FormError message={errors.relationship_type_id} />
                        </div>
                        <div>
                            <Input
                                type="number"
                                min={0}
                                placeholder="Dependent No"
                                value={data.dependent_no}
                                onChange={(e) => setData('dependent_no', e.target.value)}
                            />
                            <FormError message={errors.dependent_no} />
                        </div>
                    </section>
                )}

                {/* Insurance fields */}
                {showInsurance && (
                    <section className="bg-white rounded-lg border p-4 space-y-3">
                        <h3 className="font-semibold text-gray-800">Insurance Information</h3>
                        <Input
                            placeholder="Insurance Number *"
                            value={data.insurance_no}
                            onChange={(e) => setData('insurance_no', e.target.value)}
                        />
                        <FormError message={errors.insurance_no} />
                    </section>
                )}

                {/* OS / Other type — manual card number */}
                {showOsCard && (
                    <section className="bg-white rounded-lg border p-4 space-y-3">
                        <h3 className="font-semibold text-gray-800">Card Number</h3>
                        <p className="text-xs text-gray-500">
                            Enter the card number for this patient. If left blank, one will be generated automatically.
                        </p>
                        <div>
                            <Input
                                placeholder="e.g. OS-1234 or any unique number"
                                value={data.os_card_number}
                                onChange={(e) => setData('os_card_number', e.target.value)}
                            />
                            <FormError message={errors.os_card_number} />
                        </div>
                    </section>
                )}

                {/* Address */}
                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Address</h3>
                    <Input
                        placeholder="Address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                    <div className="grid grid-cols-3 gap-3">
                        <Input placeholder="Woreda" value={data.woreda} onChange={(e) => setData('woreda', e.target.value)} />
                        <Input placeholder="Kebele" value={data.kebele} onChange={(e) => setData('kebele', e.target.value)} />
                        <Input placeholder="House No" value={data.house_no} onChange={(e) => setData('house_no', e.target.value)} />
                    </div>
                    <Input placeholder="Phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                </section>

                {/* Submit buttons */}
                <div className="flex gap-3 pb-8">
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={() => submit(false)}
                    >
                        {processing ? 'Saving…' : 'Save & View Card'}
                    </Button>
                    <Button
                        type="button"
                        className="bg-yellow-500 hover:bg-yellow-600 text-gray-900"
                        disabled={processing}
                        onClick={() => submit(true)}
                    >
                        Save & Assign Room
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
