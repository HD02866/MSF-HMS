import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input } from '@/Layouts/AppLayout';
import FormError from '@/components/FormError';
import { useRef, useState } from 'react';

interface Props {
    user: {
        id: number;
        full_name: string;
        username: string;
        phone: string | null;
        avatar_url: string | null;
        role: { name: string } | null;
        department: { name: string } | null;
    };
}

// ── Avatar initials fallback ──────────────────────────────────────────────────
function AvatarCircle({ url, name, size = 'lg' }: { url: string | null; name: string; size?: 'sm' | 'lg' }) {
    const initials = name
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

    const cls = size === 'lg'
        ? 'w-24 h-24 text-2xl border-4'
        : 'w-16 h-16 text-lg border-2';

    if (url) {
        return (
            <img
                src={url}
                alt={name}
                className={`${cls} rounded-full object-cover border-white shadow-md`}
            />
        );
    }

    return (
        <div className={`${cls} rounded-full bg-green-600 border-white shadow-md flex items-center justify-center`}>
            <span className="text-white font-bold">{initials}</span>
        </div>
    );
}

export default function ProfileEdit({ user }: Props) {
    // ── Avatar preview ────────────────────────────────────────────────────
    const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url);
    const avatarInputRef = useRef<HTMLInputElement>(null);

    // ── Profile form (full_name, username, phone, avatar) ────────────────
    const profileForm = useForm<{
        _method: string;
        full_name: string;
        username: string;
        phone: string;
        avatar: File | null;
    }>({
        _method:   'POST',
        full_name: user.full_name,
        username:  user.username,
        phone:     user.phone ?? '',
        avatar:    null,
    });

    // ── Password form ─────────────────────────────────────────────────────
    const passwordForm = useForm({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    function handleAvatarChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0] ?? null;
        profileForm.setData('avatar', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => setAvatarPreview(ev.target?.result as string);
            reader.readAsDataURL(file);
        }
    }

    function submitProfile(e: React.FormEvent) {
        e.preventDefault();
        profileForm.post('/profile', { forceFormData: true });
    }

    function submitPassword(e: React.FormEvent) {
        e.preventDefault();
        passwordForm.put('/profile/password', {
            onSuccess: () => passwordForm.reset(),
        });
    }

    return (
        <AppLayout title="My Profile">
            <Head title="Profile" />

            <div className="max-w-2xl space-y-6">

                {/* ── Welcome Banner ─────────────────────────────────────── */}
                <div className="bg-green-700 rounded-xl px-6 py-6 flex items-center gap-5">
                    <div className="shrink-0 cursor-pointer" onClick={() => avatarInputRef.current?.click()} title="Click to change photo">
                        <AvatarCircle url={avatarPreview} name={user.full_name} size="lg" />
                    </div>
                    <div>
                        <p className="text-green-200 text-sm font-medium">Welcome back,</p>
                        <h1 className="text-white text-2xl font-bold leading-tight">{user.full_name}</h1>
                        <div className="flex flex-wrap gap-3 mt-1">
                            <span className="text-xs bg-green-600 text-green-100 px-2.5 py-0.5 rounded-full">
                                {user.role?.name ?? '—'}
                            </span>
                            {user.department && (
                                <span className="text-xs bg-green-600 text-green-100 px-2.5 py-0.5 rounded-full">
                                    {user.department.name}
                                </span>
                            )}
                            <span className="text-xs text-green-300">@{user.username}</span>
                        </div>
                    </div>
                </div>

                {/* ── Profile Information ────────────────────────────────── */}
                <form onSubmit={submitProfile} className="bg-white rounded-xl border shadow-sm p-5 space-y-4">
                    <h3 className="font-semibold text-gray-800">Profile Information</h3>

                    {/* Avatar upload */}
                    <div className="flex items-center gap-4">
                        <div
                            className="cursor-pointer rounded-full ring-2 ring-green-300 hover:ring-green-500 transition-all shrink-0"
                            onClick={() => avatarInputRef.current?.click()}
                            title="Click to upload photo"
                        >
                            <AvatarCircle url={avatarPreview} name={profileForm.data.full_name || user.full_name} size="sm" />
                        </div>
                        <div>
                            <button
                                type="button"
                                onClick={() => avatarInputRef.current?.click()}
                                className="text-sm text-green-700 font-medium hover:underline"
                            >
                                {avatarPreview ? 'Change profile photo' : 'Upload profile photo'}
                            </button>
                            <p className="text-xs text-gray-400 mt-0.5">JPG, PNG or GIF — max 2 MB</p>
                            <FormError message={profileForm.errors.avatar} />
                        </div>
                        <input
                            ref={avatarInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={handleAvatarChange}
                        />
                    </div>

                    {/* Full Name */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <Input
                            placeholder="Full Name"
                            value={profileForm.data.full_name}
                            onChange={(e) => profileForm.setData('full_name', e.target.value)}
                        />
                        <FormError message={profileForm.errors.full_name} />
                    </div>

                    {/* Username */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <Input
                            placeholder="Username"
                            value={profileForm.data.username}
                            onChange={(e) => profileForm.setData('username', e.target.value)}
                            autoComplete="username"
                        />
                        <p className="text-xs text-gray-400 mt-1">This is the name you use to log in.</p>
                        <FormError message={profileForm.errors.username} />
                    </div>

                    {/* Phone */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <Input
                            placeholder="Phone (optional)"
                            value={profileForm.data.phone}
                            onChange={(e) => profileForm.setData('phone', e.target.value)}
                        />
                        <FormError message={profileForm.errors.phone} />
                    </div>

                    {/* Read-only info */}
                    <div className="grid grid-cols-2 gap-3 pt-1">
                        <div className="bg-gray-50 rounded-lg px-3 py-2">
                            <p className="text-xs text-gray-400">Role</p>
                            <p className="text-sm font-medium text-gray-700">{user.role?.name ?? '—'}</p>
                        </div>
                        <div className="bg-gray-50 rounded-lg px-3 py-2">
                            <p className="text-xs text-gray-400">Department</p>
                            <p className="text-sm font-medium text-gray-700">{user.department?.name ?? '—'}</p>
                        </div>
                    </div>

                    <Button type="submit" disabled={profileForm.processing}>
                        {profileForm.processing ? 'Saving…' : 'Save Profile'}
                    </Button>
                </form>

                {/* ── Change Password ────────────────────────────────────── */}
                <form onSubmit={submitPassword} className="bg-white rounded-xl border shadow-sm p-5 space-y-4">
                    <h3 className="font-semibold text-gray-800">Change Password</h3>
                    <p className="text-xs text-gray-500">Leave blank if you don't want to change your password.</p>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <Input
                            type="password"
                            placeholder="Enter current password"
                            value={passwordForm.data.current_password}
                            onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                            autoComplete="current-password"
                        />
                        <FormError message={passwordForm.errors.current_password} />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <Input
                            type="password"
                            placeholder="Minimum 8 characters"
                            value={passwordForm.data.password}
                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                        <FormError message={passwordForm.errors.password} />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <Input
                            type="password"
                            placeholder="Repeat new password"
                            value={passwordForm.data.password_confirmation}
                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                        <FormError message={passwordForm.errors.password_confirmation} />
                    </div>

                    <Button type="submit" disabled={passwordForm.processing}>
                        {passwordForm.processing ? 'Updating…' : 'Update Password'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
