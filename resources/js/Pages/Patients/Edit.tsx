import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import FormError from '@/components/FormError';
import { useMemo, useRef, useState } from 'react';

interface Props {
    patient: {
        id: number;
        card_number: string;
        full_name: string;
        gender: string | null;
        date_of_birth: string | null;
        phone: string | null;
        address: string | null;
        woreda: string | null;
        kebele: string | null;
        house_no: string | null;
        employee_no: string | null;
        insurance_no: string | null;
        dependent_no: number | null;
        patient_type_id: number;
        relationship_type_id: number | null;
        photo_url: string | null;
        photo_path: string | null;
    };
    patientTypes: Array<{ id: number; name: string }>;
    relationshipTypes: Array<{ id: number; name: string }>;
}

export default function EditPatient({ patient, patientTypes, relationshipTypes }: Props) {
    // Photo preview — starts with the existing photo if present
    const [photoPreview, setPhotoPreview] = useState<string | null>(patient.photo_url ?? null);
    const [photoCleared, setPhotoCleared] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);

    // Use post + _method spoofing so multipart/form-data works for file upload
    const { data, setData, post, processing, errors } = useForm<{
        _method: string;
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
        card_number: string;
        photo: File | null;
    }>({
        _method:              'PUT',
        patient_type_id:      String(patient.patient_type_id),
        relationship_type_id: patient.relationship_type_id ? String(patient.relationship_type_id) : '',
        employee_no:          patient.employee_no ?? '',
        insurance_no:         patient.insurance_no ?? '',
        dependent_no:         String(patient.dependent_no ?? 0),
        full_name:            patient.full_name,
        gender:               patient.gender ?? '',
        date_of_birth:        patient.date_of_birth?.substring(0, 10) ?? '',
        phone:                patient.phone ?? '',
        address:              patient.address ?? '',
        woreda:               patient.woreda ?? '',
        kebele:               patient.kebele ?? '',
        house_no:             patient.house_no ?? '',
        card_number:          patient.card_number,
        photo:                null,
    });

    function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        setData('photo', file);
        setPhotoCleared(false);
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => setPhotoPreview(ev.target?.result as string);
            reader.readAsDataURL(file);
        }
    }

    function clearPhoto() {
        setPhotoPreview(null);
        setPhotoCleared(true);
        setData('photo', null);
        if (photoInputRef.current) photoInputRef.current.value = '';
    }

    const selectedType = useMemo(
        () => patientTypes.find((t) => String(t.id) === data.patient_type_id),
        [data.patient_type_id, patientTypes],
    );

    const showEmployee  = selectedType && ['Employee', 'Family'].includes(selectedType.name);
    const showInsurance = selectedType?.name === 'Insurance';

    function submit(e: React.FormEvent) {
        e.preventDefault();
        // POST with _method: PUT so multipart file upload works through Laravel's method spoofing
        post(`/patients/${patient.id}`, { forceFormData: true });
    }

    return (
        <AppLayout title="Edit Patient">
            <Head title={`Edit — ${patient.full_name}`} />

            <form onSubmit={submit} className="max-w-3xl space-y-6">

                {/* Identity */}
                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Identity</h3>

                    {/* Card Number — editable for all patient types */}
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Card Number</label>
                        <Input
                            placeholder="Card Number"
                            value={data.card_number}
                            onChange={(e) => setData('card_number', e.target.value)}
                        />
                        <FormError message={errors.card_number} />
                    </div>

                    <div className="flex items-start gap-4">
                        {/* Photo upload */}
                        <div className="flex flex-col items-center gap-2 shrink-0">
                            <div
                                className="w-24 h-28 rounded border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden cursor-pointer hover:border-green-400 transition-colors"
                                onClick={() => photoInputRef.current?.click()}
                                title="Click to change photo"
                            >
                                {photoPreview ? (
                                    <img src={photoPreview} alt="Patient photo" className="w-full h-full object-cover" />
                                ) : (
                                    <div className="text-center px-1">
                                        <div className="text-2xl text-gray-300">📷</div>
                                        <p className="text-xs text-gray-400 mt-1">
                                            {photoCleared ? 'Upload photo' : 'Change photo'}
                                        </p>
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
                                    onClick={clearPhoto}
                                    className="text-xs text-red-500 hover:underline"
                                >
                                    Remove
                                </button>
                            )}
                            <FormError message={errors.photo} />
                        </div>

                        {/* Name / Gender / DOB */}
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

                {/* Address */}
                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Address</h3>
                    <div>
                        <Input
                            placeholder="Address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                        <FormError message={errors.address} />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <Input placeholder="Woreda" value={data.woreda} onChange={(e) => setData('woreda', e.target.value)} />
                            <FormError message={errors.woreda} />
                        </div>
                        <div>
                            <Input placeholder="Kebele" value={data.kebele} onChange={(e) => setData('kebele', e.target.value)} />
                            <FormError message={errors.kebele} />
                        </div>
                        <div>
                            <Input placeholder="House No" value={data.house_no} onChange={(e) => setData('house_no', e.target.value)} />
                            <FormError message={errors.house_no} />
                        </div>
                    </div>
                    <div>
                        <Input placeholder="Phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        <FormError message={errors.phone} />
                    </div>
                </section>

                {/* Buttons */}
                <div className="flex gap-3 pb-8">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save Changes'}
                    </Button>
                    <Link href={`/patients/${patient.id}`}>
                        <Button variant="secondary" type="button">Cancel</Button>
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
