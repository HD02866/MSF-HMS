import { Head, Link, router } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';

interface PaginatedUsers {
    data: Array<{
        id: number;
        username: string;
        full_name: string;
        is_active: boolean;
        role?: { name: string };
        department?: { name: string } | null;
    }>;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    users: PaginatedUsers;
}

export default function UsersIndex({ users }: Props) {
    const deactivateUser = (userId: number, fullName: string) => {
        if (!window.confirm(`Deactivate ${fullName}? They will no longer be able to sign in.`)) {
            return;
        }

        router.delete(`/users/${userId}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout title="User Management">
            <Head title="Users" />

            <div className="mb-4 flex items-center justify-between gap-3">
                <p className="text-sm text-gray-500">Manage hospital staff accounts and roles.</p>
                <Link href="/users/create"><Button>Create User</Button></Link>
            </div>

            <div className="bg-white rounded-lg border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="text-left px-4 py-3">Username</th>
                            <th className="text-left px-4 py-3">Full Name</th>
                            <th className="text-left px-4 py-3">Role</th>
                            <th className="text-left px-4 py-3">Department</th>
                            <th className="text-left px-4 py-3">Status</th>
                            <th className="text-left px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr key={user.id} className="border-t">
                                <td className="px-4 py-3 font-mono">{user.username}</td>
                                <td className="px-4 py-3">{user.full_name}</td>
                                <td className="px-4 py-3">{user.role?.name ?? '—'}</td>
                                <td className="px-4 py-3">{user.department?.name ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <span className={user.is_active ? 'text-green-700' : 'text-red-600'}>
                                        {user.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <Link href={`/users/${user.id}/edit`} className="text-green-700 hover:underline">
                                            Edit
                                        </Link>
                                        {user.is_active && (
                                            <button
                                                type="button"
                                                onClick={() => deactivateUser(user.id, user.full_name)}
                                                className="text-red-600 hover:underline"
                                            >
                                                Deactivate
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                    No users found. Create the first staff account.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {users.links.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {users.links.map((link, index) => (
                        link.url ? (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                className={`px-3 py-1 rounded border text-sm ${
                                    link.active
                                        ? 'bg-green-600 text-white border-green-600'
                                        : 'bg-white text-gray-700 hover:bg-gray-50'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className="px-3 py-1 rounded border text-sm bg-gray-100 text-gray-400"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        )
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
