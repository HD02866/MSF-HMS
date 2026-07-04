import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button, Input } from '@/Layouts/AppLayout';

interface Props {
    user: any;
}

export default function ProfileEdit({ user }: Props) {
    const profileForm = useForm({ full_name: user.full_name, phone: user.phone ?? '' });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AppLayout title="User Profile">
            <Head title="Profile" />

            <div className="max-w-lg space-y-8">
                <form onSubmit={(e) => { e.preventDefault(); profileForm.patch('/profile'); }} className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold">Profile Information</h3>
                    <Input value={profileForm.data.full_name} onChange={(e) => profileForm.setData('full_name', e.target.value)} placeholder="Full Name" />
                    <Input value={profileForm.data.phone} onChange={(e) => profileForm.setData('phone', e.target.value)} placeholder="Phone" />
                    <p className="text-sm text-gray-500">Role: {user.role?.name}</p>
                    <Button type="submit" disabled={profileForm.processing}>Update Profile</Button>
                </form>

                <form onSubmit={(e) => { e.preventDefault(); passwordForm.put('/profile/password'); }} className="bg-white rounded-lg border p-4 space-y-3">
                    <h3 className="font-semibold">Change Password</h3>
                    <Input type="password" placeholder="Current Password" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} />
                    <Input type="password" placeholder="New Password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} />
                    <Input type="password" placeholder="Confirm Password" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} />
                    <Button type="submit" disabled={passwordForm.processing}>Change Password</Button>
                </form>
            </div>
        </AppLayout>
    );
}
