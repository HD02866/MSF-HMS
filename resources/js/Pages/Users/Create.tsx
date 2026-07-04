import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import FormError from '@/components/FormError';

interface Props {
    roles: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
}

// Roles that are tied to a specific department automatically
const ROLE_DEPARTMENT_MAP: Record<string, string> = {
    Recorder: 'Card Room',
    'Card Officer': 'Card Room',
};

export default function UsersCreate({ roles, departments }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        full_name: '',
        username: '',
        password: '',
        role_id: '',
        department_id: '',
        phone: '',
    });

    // When a role is selected, auto-set the department if the role has a default
    function handleRoleChange(roleId: string) {
        setData('role_id', roleId);

        const selectedRole = roles.find((r) => String(r.id) === roleId);
        if (selectedRole) {
            const defaultDeptName = ROLE_DEPARTMENT_MAP[selectedRole.name];
            if (defaultDeptName) {
                const matchedDept = departments.find((d) => d.name === defaultDeptName);
                if (matchedDept) {
                    setData('department_id', String(matchedDept.id));
                }
            }
        }
    }

    const selectedRole = roles.find((r) => String(r.id) === data.role_id);
    const autoFilledDept = selectedRole ? ROLE_DEPARTMENT_MAP[selectedRole.name] : null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/users', { preserveScroll: true });
    };

    return (
        <AppLayout title="Create User">
            <Head title="Create User" />

            <form onSubmit={submit} className="max-w-lg space-y-4 bg-white rounded-lg border p-6">
                {Object.keys(errors).length > 0 && (
                    <div className="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                        Please fix the highlighted fields below.
                    </div>
                )}

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <Input value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} />
                    <FormError message={errors.full_name} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                    <Input value={data.username} onChange={(e) => setData('username', e.target.value)} />
                    <FormError message={errors.username} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <Input
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <FormError message={errors.password} />
                    <p className="text-xs text-gray-500 mt-1">Minimum 8 characters.</p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <Select value={data.role_id} onChange={(e) => handleRoleChange(e.target.value)}>
                        <option value="">Select Role</option>
                        {roles.map((role) => (
                            <option key={role.id} value={role.id}>{role.name}</option>
                        ))}
                    </Select>
                    <FormError message={errors.role_id} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <Select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                        <option value="">Select Department</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>{department.name}</option>
                        ))}
                    </Select>
                    {autoFilledDept && (
                        <p className="text-xs text-green-700 mt-1">
                            Auto-set to <span className="font-semibold">{autoFilledDept}</span> — required for the {selectedRole?.name} role.
                        </p>
                    )}
                    <FormError message={errors.department_id} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                    <FormError message={errors.phone} />
                </div>

                <div className="flex gap-3 pt-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Creating...' : 'Create User'}
                    </Button>
                    <Link href="/users"><Button variant="secondary" type="button">Cancel</Button></Link>
                </div>
            </form>
        </AppLayout>
    );
}
