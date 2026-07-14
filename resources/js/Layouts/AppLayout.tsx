import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { cn, canManagePatients } from '@/lib/utils';
import BrandLogo from '@/components/BrandLogo';
import FlashAlert from '@/components/FlashAlert';

interface NavItem {
    name: string;
    href: string;
    roles?: string[];
    sub?: boolean;   // indented sub-item under a parent group
}

// Role → home dashboard path
const ROLE_DASHBOARD: Record<string, string> = {
    Recorder:           '/recorder/dashboard',
    'Card Officer':     '/card-officer/dashboard',
    'OPD Nurse':        '/opd/dashboard',
    'Lab Technician':   '/lab/queue',
    Pharmacist:         '/pharmacy/dashboard',
    Admin:              '/dashboard',
    'Department Head':  '/dashboard',
    'General Manager':  '/dashboard',
};

// Full navigation — each item is shown only to its allowed roles (undefined = everyone)
const navigation: NavItem[] = [
    // ── Dashboard (role-specific) ──────────────────────────────────────────
    { name: 'Dashboard', href: '/recorder/dashboard',      roles: ['Recorder'] },
    { name: 'Dashboard', href: '/card-officer/dashboard',  roles: ['Card Officer'] },
    { name: 'Dashboard', href: '/opd/dashboard',           roles: ['OPD Nurse'] },
    { name: 'Dashboard', href: '/dashboard',               roles: ['Admin', 'Department Head', 'General Manager'] },

    // ── Patient ────────────────────────────────────────────────────────────
    { name: 'Patient Search', href: '/patients/search', roles: ['Admin', 'Card Officer', 'Department Head', 'General Manager', 'Recorder'] },
    { name: 'New Patient',    href: '/patients/create',  roles: ['Admin', 'Card Officer', 'Department Head'] },
    { name: 'Assign Room',    href: '/visits/assign',    roles: ['Admin', 'Card Officer', 'Department Head', 'Recorder'] },
    { name: 'Visit Register', href: '/visits/register',  roles: ['Admin', 'Card Officer', 'Department Head'] },

    // ── OPD Nurse menu ──────────────────────────────────────────────────────
    { name: 'OPD Queue',      href: '/opd/dashboard',    roles: ['OPD Nurse'] },
    { name: 'OPD Register',   href: '/opd/register',     roles: ['OPD Nurse', 'Admin', 'Department Head'] },
    { name: 'OPD Reports',    href: '/opd/reports',      roles: ['OPD Nurse', 'Admin', 'Department Head'] },
    { name: 'HMIS Reports',   href: '/opd/hmis-reports',  roles: ['OPD Nurse', 'Admin', 'Department Head', 'General Manager'] },

    // ── Consultation Request Queue ─────────────────────────────────────────
    { name: 'Consultation Requests', href: '/consultation-requests/queue', roles: ['Admin', 'Department Head', 'General Manager'] },

    // ── Lab Technician menu ─────────────────────────────────────────────────
    { name: 'Lab Queue',      href: '/lab/queue',         roles: ['Lab Technician'] },

    // ── Pharmacist menu ────────────────────────────────────────────────────
    { name: 'Pharmacy Dashboard', href: '/pharmacy/dashboard', roles: ['Pharmacist'] },
    { name: 'Pharmacy Queue',     href: '/pharmacy/queue',     roles: ['Pharmacist'] },

    // ── Daily Register (admin/officer view — single entry) ─────────────────
    { name: 'Daily Register', href: '/daily-register',   roles: ['Admin', 'Card Officer', 'Department Head'] },

    // ── Recorder Daily Register menu (expanded per-type links) ────────────
    { name: 'Daily Register',        href: '/daily-register',                                   roles: ['Recorder'] },
    { name: 'Family Register',       href: '/daily-register?register_type=family',              roles: ['Recorder'], sub: true },
    { name: 'Employee Register',     href: '/daily-register?register_type=employee',            roles: ['Recorder'], sub: true },
    { name: 'OS Register',           href: '/daily-register?register_type=os',                  roles: ['Recorder'], sub: true },
    { name: 'Referral Accident',     href: '/daily-register?register_type=referral_accident',   roles: ['Recorder'], sub: true },
    { name: 'Referral Sick Leave',   href: '/daily-register?register_type=referral_sick_leave', roles: ['Recorder'], sub: true },
    { name: 'Exports',               href: '/daily-register/export/excel',                      roles: ['Recorder'] },

    // ── Referrals & Sick Leaves (OPD-created, Recorder/Admin can view) ────
    { name: 'Referrals & Sick Leaves', href: '/recorder/referrals-sick-leaves', roles: ['Recorder', 'Admin', 'Department Head'] },

    // ── Reports / Admin ────────────────────────────────────────────────────
    { name: 'Reports', href: '/reports', roles: ['Admin', 'Card Officer', 'Department Head', 'General Manager'] },
    { name: 'Users',   href: '/users',   roles: ['Admin'] },
    { name: 'Rooms',   href: '/rooms',   roles: ['Admin'] },

    // ── Profile (everyone) ─────────────────────────────────────────────────
    { name: 'Profile', href: '/profile' },
];

export default function AppLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
    const { auth } = usePage().props as any;
    const roleName: string = auth?.user?.role?.name ?? '';

    const visibleNav = navigation.filter((item) => {
        if (!item.roles) return true;
        return item.roles.includes(roleName);
    });

    // Sidebar subtitle
    const sidebarSubtitle = roleName === 'Recorder'
        ? 'Recorder — Daily Register'
        : roleName === 'OPD Nurse'
            ? 'OPD — Queue Management'
            : roleName === 'Lab Technician'
                ? 'Laboratory — Test Queue'
                : roleName === 'Pharmacist'
                    ? 'Pharmacy — Medicine Dispensing'
                    : 'Card Room Module';

    return (
        <div className="min-h-screen flex bg-gray-50">
            <aside className="w-64 bg-green-700 text-white flex flex-col shrink-0 print:hidden">
                <div className="p-5 border-b border-green-600">
                    <BrandLogo size="md" variant="light" />
                    <p className="text-green-100 text-xs mt-3 leading-relaxed">{sidebarSubtitle}</p>
                </div>
                <nav className="flex-1 p-4 space-y-1">
                    {visibleNav.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'block rounded-md hover:bg-green-600 text-sm font-medium',
                                item.sub
                                    ? 'pl-6 pr-3 py-1.5 text-green-100 text-xs font-normal hover:text-white'
                                    : 'px-3 py-2',
                            )}
                        >
                            {item.sub && <span className="mr-1.5 opacity-50">›</span>}
                            {item.name}
                        </Link>
                    ))}
                </nav>
                <div className="p-4 border-t border-green-600 text-sm">
                    <div className="flex items-center gap-3 mb-2">
                        {auth?.user?.avatar_url ? (
                            <img
                                src={auth.user.avatar_url}
                                alt={auth.user.full_name}
                                className="w-9 h-9 rounded-full object-cover border-2 border-green-500 shrink-0"
                            />
                        ) : (
                            <div className="w-9 h-9 rounded-full bg-green-600 border-2 border-green-500 flex items-center justify-center shrink-0">
                                <span className="text-white text-sm font-bold">
                                    {(auth?.user?.full_name ?? '?')[0].toUpperCase()}
                                </span>
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="font-medium truncate">{auth?.user?.full_name}</p>
                            <p className="text-green-100 text-xs truncate">{roleName}</p>
                        </div>
                    </div>
                    <Link href="/logout" method="post" as="button" className="text-yellow-300 hover:underline text-xs">
                        Logout
                    </Link>
                </div>
            </aside>

            <div className="flex-1 flex flex-col min-w-0">
                <header className="bg-white border-b px-6 py-3 flex items-center justify-between gap-4 print:hidden">
                    {/* Left — page title */}
                    <div className="flex items-center gap-4 min-w-0">
                        <BrandLogo size="sm" showText={false} className="lg:hidden" />
                        <div className="min-w-0">
                            <h2 className="text-lg font-semibold text-gray-800 truncate">{title}</h2>
                            <p className="text-xs text-gray-500 hidden sm:block">Metahara Sugar Factory Hospital</p>
                        </div>
                    </div>

                    {/* Right — New Patient button + user avatar / greeting */}
                    <div className="flex items-center gap-3 shrink-0">
                        {canManagePatients(roleName) && (
                            <Link
                                href="/patients/create"
                                className="bg-yellow-400 hover:bg-yellow-500 text-gray-900 px-4 py-2 rounded-md text-sm font-medium"
                            >
                                + New Patient
                            </Link>
                        )}

                        {/* Profile avatar + greeting — links to /profile */}
                        <Link
                            href="/profile"
                            className="flex items-center gap-2.5 group rounded-lg px-2 py-1.5 hover:bg-gray-50 transition-colors"
                        >
                            {/* Avatar circle */}
                            {auth?.user?.avatar_url ? (
                                <img
                                    src={auth.user.avatar_url}
                                    alt={auth.user.full_name}
                                    className="w-9 h-9 rounded-full object-cover ring-2 ring-green-500 group-hover:ring-green-600 shrink-0"
                                />
                            ) : (
                                <div className="w-9 h-9 rounded-full bg-green-600 ring-2 ring-green-500 group-hover:ring-green-600 flex items-center justify-center shrink-0">
                                    <span className="text-white text-sm font-bold select-none">
                                        {(auth?.user?.full_name ?? '?')[0].toUpperCase()}
                                    </span>
                                </div>
                            )}
                            {/* Greeting text */}
                            <div className="hidden sm:block text-left">
                                <p className="text-xs text-gray-400 leading-tight">Welcome back,</p>
                                <p className="text-sm font-semibold text-gray-800 leading-tight max-w-[140px] truncate">
                                    {auth?.user?.full_name}
                                </p>
                            </div>
                        </Link>
                    </div>
                </header>

                <main className="flex-1 p-6 print:p-0">
                    <FlashAlert />
                    {children}
                </main>
            </div>
        </div>
    );
}

export function StatCard({ label, value, className }: { label: string; value: number | string; className?: string }) {
    return (
        <div className={cn('bg-white rounded-lg border p-4 shadow-sm', className)}>
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-bold text-green-700 mt-1">{value}</p>
        </div>
    );
}

export function Button({ className, variant = 'primary', ...props }: React.ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' }) {
    const variants = {
        primary: 'bg-green-600 hover:bg-green-700 text-white',
        secondary: 'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700',
        danger: 'bg-red-600 hover:bg-red-700 text-white',
    };
    return (
        <button
            className={cn('px-4 py-2 rounded-md text-sm font-medium disabled:opacity-50', variants[variant], className)}
            {...props}
        />
    );
}

export function Input({ className, ...props }: React.InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            className={cn('w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500', className)}
            {...props}
        />
    );
}

export function Select({ className, children, ...props }: React.SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn('w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500', className)}
            {...props}
        >
            {children}
        </select>
    );
}
