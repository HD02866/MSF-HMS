import { Head, router, usePage } from '@inertiajs/react';
import { useForm as useHookForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { useMemo } from 'react';
import { z } from 'zod';

interface Props {
    patientTypes: Array<{ id: number; name: string }>;
    relationshipTypes: Array<{ id: number; name: string }>;
}

const createPatientSchema = z.object({
    patient_type_id: z.string().min(1, 'Patient type is required'),
    relationship_type_id: z.string().optional(),
    employee_no: z.string().optional(),
    insurance_no: z.string().optional(),
    dependent_no: z.string().optional(),
    full_name: z.string().min(1, 'Full name is required'),
    gender: z.string().optional(),
    date_of_birth: z.string().min(1, 'Date of birth is required'),
    phone: z.string().optional(),
    address: z.string().optional(),
    woreda: z.string().optional(),
    kebele: z.string().optional(),
    house_no: z.string().optional(),
});

type CreatePatientForm = z.infer<typeof createPatientSchema>;

export default function CreatePatient({ patientTypes, relationshipTypes }: Props) {
    const { errors: serverErrors } = usePage().props as { errors: Record<string, string> };
    const { register, handleSubmit, watch, formState: { errors: clientErrors } } = useHookForm<CreatePatientForm>({
        resolver: zodResolver(createPatientSchema),
        defaultValues: {
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
        },
    });

    const patientTypeId = watch('patient_type_id');
    const selectedType = useMemo(
        () => patientTypes.find((t) => String(t.id) === String(patientTypeId)),
        [patientTypeId, patientTypes],
    );

    const showEmployee = selectedType && ['Employee', 'Family'].includes(selectedType.name);
    const showInsurance = selectedType?.name === 'Insurance';

    const onSubmit = (data: CreatePatientForm, assignRoom = false) => {
        router.post('/patients', { ...data, assign_room: assignRoom });
    };

    return (
        <AppLayout title="Create Patient Card">
            <Head title="Create Patient" />

            <form className="max-w-3xl space-y-6" onSubmit={handleSubmit((data) => onSubmit(data, false))}>
                {(serverErrors.employee_no || serverErrors.dependent_no) && (
                    <div className="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm space-y-1">
                        {serverErrors.employee_no && <p>{serverErrors.employee_no}</p>}
                        {serverErrors.dependent_no && <p>{serverErrors.dependent_no}</p>}
                    </div>
                )}

                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Identity</h3>
                    <Input placeholder="Full Name *" {...register('full_name')} />
                    {(clientErrors.full_name) && (
                        <p className="text-red-600 text-xs">{clientErrors.full_name?.message}</p>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        <Select {...register('gender')}>
                            <option value="">Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </Select>
                        <Input type="date" {...register('date_of_birth')} />
                    </div>
                    {(clientErrors.date_of_birth) && (
                        <p className="text-red-600 text-xs">{clientErrors.date_of_birth?.message}</p>
                    )}
                </section>

                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Patient Category</h3>
                    <Select {...register('patient_type_id')}>
                        <option value="">Select type *</option>
                        {patientTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </Select>
                </section>

                {showEmployee && (
                    <section className="bg-white rounded-lg border p-4 space-y-3">
                        <h3 className="font-semibold text-gray-800">Employee Information</h3>
                        <Input placeholder="Employee Number" {...register('employee_no')} />
                        {serverErrors.employee_no && (
                            <p className="text-red-600 text-xs">{serverErrors.employee_no}</p>
                        )}
                        <Select {...register('relationship_type_id')}>
                            <option value="">Relationship</option>
                            {relationshipTypes.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                        </Select>
                        <Input type="number" min={0} placeholder="Dependent No" {...register('dependent_no')} />
                        {serverErrors.dependent_no && (
                            <p className="text-red-600 text-xs">{serverErrors.dependent_no}</p>
                        )}
                    </section>
                )}

                {showInsurance && (
                    <section className="bg-white rounded-lg border p-4 space-y-3">
                        <h3 className="font-semibold text-gray-800">Insurance Information</h3>
                        <Input placeholder="Insurance Number *" {...register('insurance_no')} />
                        {(clientErrors.insurance_no) && (
                            <p className="text-red-600 text-xs">{clientErrors.insurance_no?.message}</p>
                        )}
                    </section>
                )}

                <section className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold text-gray-800">Address</h3>
                    <Input placeholder="Address" {...register('address')} />
                    <div className="grid grid-cols-3 gap-3">
                        <Input placeholder="Woreda" {...register('woreda')} />
                        <Input placeholder="Kebele" {...register('kebele')} />
                        <Input placeholder="House No" {...register('house_no')} />
                    </div>
                    <Input placeholder="Phone" {...register('phone')} />
                </section>

                <div className="flex gap-3">
                    <Button type="submit">Save</Button>
                    <Button type="button" className="bg-yellow-500 hover:bg-yellow-600" onClick={handleSubmit((data) => onSubmit(data, true))}>
                        Save & Assign Room
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
