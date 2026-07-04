import { Head, Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { DataTable } from '@/components/ui/DataTable';
import { canManagePatients } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

interface Patient {
    id: number;
    card_number: string;
    full_name: string;
    phone: string | null;
    patient_type: { name: string };
    relationship_type: { name: string } | null;
    date_of_birth: string;
}

interface Props {
    patients: { data: Patient[]; links: any[] };
    patientTypes: Array<{ id: number; name: string }>;
    filters: Record<string, string>;
}

export default function PatientSearch({ patients, patientTypes, filters }: Props) {
    const { auth } = usePage().props as any;
    const roleName = auth?.user?.role?.name;

    const search = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
        router.get('/patients/search', Object.fromEntries(form.entries()), { preserveState: true });
    };

    const columns = useMemo<ColumnDef<Patient>[]>(() => [
        { accessorKey: 'card_number', header: 'Card No', cell: ({ row }) => <span className="font-mono">{row.original.card_number}</span> },
        { accessorKey: 'full_name', header: 'Name' },
        { accessorKey: 'patient_type.name', header: 'Type', cell: ({ row }) => row.original.patient_type?.name },
        { accessorKey: 'relationship_type.name', header: 'Relationship', cell: ({ row }) => row.original.relationship_type?.name ?? '—' },
        {
            id: 'age',
            header: 'Age',
            // Stable calculation — does not re-run on every render
            cell: ({ row }) => {
                const dob = row.original.date_of_birth;
                return dob ? Math.floor((Date.now() - new Date(dob).getTime()) / (365.25 * 24 * 3600 * 1000)) : '—';
            },
        },
        { accessorKey: 'phone', header: 'Phone', cell: ({ row }) => row.original.phone ?? '—' },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <div className="space-x-2">
                    <Link href={`/patients/${row.original.id}`} className="text-green-700 hover:underline">View</Link>
                    {canManagePatients(roleName) && (
                        <>
                            <Link href={`/patients/${row.original.id}/edit`} className="text-blue-600 hover:underline">Edit</Link>
                            <Link href={`/visits/assign?patient_id=${row.original.id}`} className="text-yellow-600 hover:underline">Assign</Link>
                        </>
                    )}
                </div>
            ),
        },
    ], [roleName]);

    return (
        <AppLayout title="Patient Search">
            <Head title="Patient Search" />

            <form onSubmit={search} className="bg-white rounded-lg border p-4 mb-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <Input name="card_number" placeholder="Card Number" defaultValue={filters.card_number} />
                <Input name="employee_no" placeholder="Employee No" defaultValue={filters.employee_no} />
                <Input name="insurance_no" placeholder="Insurance No" defaultValue={filters.insurance_no} />
                <Input name="full_name" placeholder="Full Name" defaultValue={filters.full_name} />
                <Input name="phone" placeholder="Phone" defaultValue={filters.phone} />
                <Select name="patient_type_id" defaultValue={filters.patient_type_id ?? ''}>
                    <option value="">All Types</option>
                    {patientTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </Select>
                <div className="col-span-full">
                    <Button type="submit">Search</Button>
                </div>
            </form>

            <div className="bg-white rounded-lg border overflow-hidden">
                <DataTable columns={columns} data={patients.data} emptyMessage="No patients found" />
            </div>
        </AppLayout>
    );
}
