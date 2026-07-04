import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import FormError from '@/components/FormError';

interface Props {
    user: {
        id: number;
        full_name: string;
        username: string;
        role_id: number;
        department_id?: number | null;
        phone?: string | null;
        is_active: boolean;
    };
    roles: Array<{ id: number; name: string }>;
    departments: Array<{ id: number; name: string }>;
}

// Roles that are tied to a specific department automatically
const ROLE_DEPARTMENT_MAP: Record<string, string> = {
    Recorder: 'Card Room',
    'Card Officer': 'Card Room',
};

export default function UsersEdit({ user, roles, departments }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        full_name: user.full_name,
        username: user.username,
        role_id: String(user.role_id),
        department_id: user.department_id ? String(user.department_id) : '',
        phone: user.phone ?? '',
        is_active: user.is_active,
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
        put(`/users/${user.id}`, { preserveScroll: true });
    };

    const deactivate = () => {
        if (!window.confirm(`Deactivate ${user.full_name}?`)) return;
        router.delete(`/users/${user.id}`);
    };

    return (
        <AppLayout title="Edit User">
            <Head title="Edit User" />

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
                    <label className="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                    <Select value={data.role_id} onChange={(e) => handleRoleChange(e.target.value)}>
                        {roles.map((role) => (
                            <option key={role.id} value={role.id}>{role.name}</option>
                        ))}
                    </Select>
                    <FormError message={errors.role_id} />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <Select value={data.department_id} onChange={(e) => setData('department_id', e.target.value)}>
                        <option value="">No Department</option>
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

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Active account
                </label>
                <FormError message={errors.is_active} />

                <div className="flex flex-wrap gap-3 pt-2">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Update User'}
                    </Button>
                    <Link href="/users"><Button variant="secondary" type="button">Cancel</Button></Link>
                    {data.is_active && (
                        <Button type="button" variant="danger" onClick={deactivate}>
                            Deactivate User
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    );
}
