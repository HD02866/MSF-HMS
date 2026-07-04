import { Head, useForm } from '@inertiajs/react';
import { Button, Input } from '@/Layouts/AppLayout';
import BrandLogo from '@/components/BrandLogo';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-green-50 via-white to-yellow-50 px-4">
            <Head title="Login" />
            <div className="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-green-100">
                <div className="flex flex-col items-center mb-8">
                    <BrandLogo size="xl" className="flex-col items-center text-center gap-4" />
                    <p className="text-gray-500 text-sm mt-4">Hospital Management System</p>
                    <p className="text-green-700 text-xs font-medium mt-1">Card Room Module</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <Input
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            autoFocus
                        />
                        {errors.username && <p className="text-red-600 text-xs mt-1">{errors.username}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        {errors.password && <p className="text-red-600 text-xs mt-1">{errors.password}</p>}
                    </div>

                    <Button type="submit" disabled={processing} className="w-full">
                        {processing ? 'Signing in...' : 'Login'}
                    </Button>
                </form>

                <p className="text-center text-xs text-gray-400 mt-6">Default: admin / password</p>
            </div>
        </div>
    );
}
