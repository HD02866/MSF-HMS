import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
interface Props {
    patient: any;
    patientTypes: Array<{ id: number; name: string }>;
    relationshipTypes: Array<{ id: number; name: string }>;
}

export default function EditPatient({ patient, patientTypes, relationshipTypes }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        patient_type_id: String(patient.patient_type_id),
        relationship_type_id: patient.relationship_type_id ? String(patient.relationship_type_id) : '',
        employee_no: patient.employee_no ?? '',
        insurance_no: patient.insurance_no ?? '',
        dependent_no: String(patient.dependent_no ?? 0),
        full_name: patient.full_name,
        gender: patient.gender ?? '',
        date_of_birth: patient.date_of_birth?.substring(0, 10) ?? '',
        phone: patient.phone ?? '',
        address: patient.address ?? '',
        woreda: patient.woreda ?? '',
        kebele: patient.kebele ?? '',
        house_no: patient.house_no ?? '',
    });

    const selectedType = patientTypes.find((t) => String(t.id) === data.patient_type_id);
    const showEmployee = selectedType && ['Employee', 'Family'].includes(selectedType.name);
    const showInsurance = selectedType?.name === 'Insurance';

    return (
        <AppLayout title="Edit Patient">
            <Head title={`Edit ${patient.full_name}`} />
            <form onSubmit={(e) => { e.preventDefault(); put(`/patients/${patient.id}`); }} className="max-w-3xl space-y-4">
                <Input value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} placeholder="Full Name" />
                <Select value={data.patient_type_id} onChange={(e) => setData('patient_type_id', e.target.value)}>
                    {patientTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </Select>
                {showEmployee && (
                    <>
                        <Input value={data.employee_no} onChange={(e) => setData('employee_no', e.target.value)} placeholder="Employee No" />
                        <Select value={data.relationship_type_id} onChange={(e) => setData('relationship_type_id', e.target.value)}>
                            <option value="">Relationship</option>
                            {relationshipTypes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                        </Select>
                    </>
                )}
                {showInsurance && (
                    <Input value={data.insurance_no} onChange={(e) => setData('insurance_no', e.target.value)} placeholder="Insurance No" />
                )}
                <Input type="date" value={data.date_of_birth} onChange={(e) => setData('date_of_birth', e.target.value)} />
                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="Phone" />
                {errors.date_of_birth && <p className="text-red-600 text-xs">{errors.date_of_birth}</p>}
                <div className="flex gap-3">
                    <Button type="submit" disabled={processing}>Update</Button>
                    <Link href={`/patients/${patient.id}`}><Button variant="secondary" type="button">Cancel</Button></Link>
                </div>
            </form>
        </AppLayout>
    );
}
