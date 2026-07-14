import { Head, router, useForm } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import { useState, useCallback } from 'react';

// ── Types ──────────────────────────────────────────────────────────────────────
interface Patient {
    id: number;
    full_name: string;
    card_number: string;
    gender: string | null;
    date_of_birth: string | null;
}

interface QueueEntry {
    id: number;
    queue_number: number;
    status: string;
    arrived_at: string;
    called_at: string | null;
    patient: Patient;
}

interface Notification {
    id: string;
    type: string;
    patient_name: string;
    card_number: string;
    event_label: string;
    test_names: string[];
    notified_at: string;
    is_read: boolean;
}

interface Stats {
    waiting: number;
    total_today: number;
    completed: number;
    transferred: number;
    unread: number;
    current: QueueEntry | null;
}

interface Room {
    id: number;
    room_name: string;
    room_code: string;
}

interface Props {
    stats: Stats | null;
    queue: QueueEntry[];
    notifications: Notification[];
    opd_rooms: Room[];
    current_room: Room | null;
    statuses: Record<string, string>;
}

// ── Status badge colours ───────────────────────────────────────────────────────
const STATUS_COLORS: Record<string, string> = {
    'Waiting':          'bg-yellow-100 text-yellow-800',
    'Called':           'bg-blue-100 text-blue-800',
    'In Consultation':  'bg-green-100 text-green-800',
    'Completed':        'bg-gray-100 text-gray-600',
    'Transferred':      'bg-purple-100 text-purple-800',
    'Cancelled':        'bg-red-100 text-red-600',
};

// ── Waiting time display ───────────────────────────────────────────────────────
function waitingTime(arrivedAt: string): string {
    const mins = Math.floor((Date.now() - new Date(arrivedAt).getTime()) / 60000);
    if (mins < 60) return `${mins}m`;
    return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}

// ── Stat card ──────────────────────────────────────────────────────────────────
function StatTile({ label, value, color }: { label: string; value: number | string; color: string }) {
    return (
        <div className={`rounded-xl border-2 p-4 text-center ${color}`}>
            <p className="text-3xl font-bold">{value}</p>
            <p className="text-xs font-medium mt-1 opacity-80">{label}</p>
        </div>
    );
}

// ── Status update buttons ──────────────────────────────────────────────────────
function StatusButton({
    entryId,
    status,
    label,
    className,
}: {
    entryId: number;
    status: string;
    label: string;
    className: string;
}) {
    const form = useForm({ status });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/opd/queue/${entryId}/status`, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="inline">
            <button
                type="submit"
                disabled={form.processing}
                className={`px-2.5 py-1 text-xs font-medium rounded border transition-colors ${className}`}
            >
                {label}
            </button>
        </form>
    );
}

// ── Notification panel ────────────────────────────────────────────────────────
function NotificationPanel({
    notifications,
    roomId,
    unreadCount,
    onMarkRead,
}: {
    notifications: Notification[];
    roomId: number;
    unreadCount: number;
    onMarkRead: () => void;
}) {
    const [open, setOpen] = useState(false);

    function markRead() {
        router.post('/opd/notifications/mark-read', { room_id: roomId }, {
            preserveScroll: true,
            onSuccess: () => { onMarkRead(); setOpen(false); },
        });
    }

    return (
        <div className="relative">
            <button
                onClick={() => setOpen((v) => !v)}
                className="relative flex items-center gap-2 px-3 py-2 bg-white border rounded-lg hover:bg-gray-50 text-sm font-medium"
            >
                🔔 Notifications
                {unreadCount > 0 && (
                    <span className="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-10 z-40 w-80 bg-white border rounded-xl shadow-xl overflow-hidden">
                    <div className="flex items-center justify-between px-4 py-2 border-b bg-gray-50">
                        <span className="text-sm font-semibold text-gray-700">Notifications</span>
                        {unreadCount > 0 && (
                            <button onClick={markRead} className="text-xs text-green-700 hover:underline">
                                Mark all read
                            </button>
                        )}
                    </div>
                    <div className="max-h-72 overflow-y-auto divide-y">
                        {notifications.length === 0 ? (
                            <p className="px-4 py-6 text-center text-gray-400 text-xs">No notifications</p>
                        ) : notifications.map((n) => (
                            <div key={n.id} className={`px-4 py-3 text-sm ${n.is_read ? '' : 'bg-green-50'}`}>
                                <div className="flex items-center justify-between gap-2">
                                    <span className="font-medium text-gray-800 truncate">{n.patient_name}</span>
                                    <span className={`shrink-0 text-xs font-medium px-1.5 py-0.5 rounded-full
                                        ${n.type === 'lab_completed' ? 'bg-teal-100 text-teal-700'
                                        : n.type === 'lab_received'  ? 'bg-blue-100 text-blue-700'
                                        : 'bg-green-100 text-green-800'}`}>
                                        {n.event_label}
                                    </span>
                                </div>
                                <p className="text-xs text-gray-500 font-mono mt-0.5">{n.card_number}</p>
                                {n.test_names.length > 0 && (
                                    <p className="text-xs text-gray-400 mt-0.5 truncate">
                                        {n.test_names.slice(0, 3).join(', ')}{n.test_names.length > 3 ? ` +${n.test_names.length - 3} more` : ''}
                                    </p>
                                )}
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {new Date(n.notified_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────
export default function OpdDashboard({
    stats, queue, notifications, opd_rooms, current_room, statuses,
}: Props) {
    const [unread, setUnread] = useState(stats?.unread ?? 0);

    const today = new Date().toLocaleDateString('en-GB', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    });

    function switchRoom(roomId: string) {
        router.get('/opd/dashboard', { room_id: roomId }, { preserveState: false });
    }

    function callNext() {
        if (!current_room) return;
        router.post('/opd/queue/call-next', { room_id: current_room.id }, { preserveScroll: true });
    }

    return (
        <AppLayout title="OPD Dashboard">
            <Head title="OPD Dashboard" />

            {/* Banner */}
            <div className="bg-green-700 text-white rounded-xl px-6 py-5 mb-5 flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 className="text-xl font-bold">OPD Queue — {current_room?.room_name ?? 'No Room Selected'}</h1>
                    <p className="text-green-100 text-sm mt-0.5">{today}</p>
                </div>
                <div className="flex items-center gap-3 flex-wrap">
                    {/* Room selector */}
                    <select
                        value={current_room?.id ?? ''}
                        onChange={(e) => switchRoom(e.target.value)}
                        className="rounded-md border border-green-500 bg-green-600 text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-400"
                    >
                        {opd_rooms.map((r) => (
                            <option key={r.id} value={r.id}>{r.room_name}</option>
                        ))}
                    </select>

                    {/* Notifications bell */}
                    {current_room && (
                        <NotificationPanel
                            notifications={notifications}
                            roomId={current_room.id}
                            unreadCount={unread}
                            onMarkRead={() => setUnread(0)}
                        />
                    )}
                </div>
            </div>

            {/* Stats */}
            {stats && (
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
                    <StatTile label="Waiting"        value={stats.waiting}     color="border-yellow-300 bg-yellow-50 text-yellow-800" />
                    <StatTile label="Total Today"    value={stats.total_today} color="border-green-300 bg-green-50 text-green-800" />
                    <StatTile label="Completed"      value={stats.completed}   color="border-gray-300 bg-gray-50 text-gray-700" />
                    <StatTile label="Transferred"    value={stats.transferred} color="border-purple-300 bg-purple-50 text-purple-800" />
                    <StatTile label="Notifications"  value={unread}            color="border-red-300 bg-red-50 text-red-700" />
                </div>
            )}

            {/* Current patient + Quick actions */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                {/* Current patient */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="text-sm font-semibold text-gray-700 mb-3">Current Patient</h3>
                    {stats?.current ? (
                        <div className="space-y-1.5">
                            <div className="flex items-center gap-3">
                                <span className="text-2xl font-bold text-green-700">
                                    #{stats.current.queue_number}
                                </span>
                                <div>
                                    <p className="font-semibold text-gray-800">{stats.current.patient.full_name}</p>
                                    <p className="text-xs font-mono text-gray-500">{stats.current.patient.card_number}</p>
                                </div>
                            </div>
                            <span className="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                In Consultation
                            </span>
                            <div className="flex gap-2 mt-2">
                                <StatusButton entryId={stats.current.id} status="Completed"   label="Complete"   className="bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100" />
                                <StatusButton entryId={stats.current.id} status="Transferred" label="Transfer"   className="bg-purple-50 text-purple-700 border-purple-200 hover:bg-purple-100" />
                            </div>
                        </div>
                    ) : (
                        <p className="text-gray-400 text-sm">No patient in consultation.</p>
                    )}
                </div>

                {/* Quick actions */}
                <div className="bg-white rounded-xl border shadow-sm p-5">
                    <h3 className="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <Button
                            onClick={callNext}
                            disabled={!current_room || (stats?.waiting ?? 0) === 0}
                            className="flex flex-col items-center py-4 h-auto"
                        >
                            <span className="text-xl mb-1">📣</span>
                            <span className="text-xs">Call Next Patient</span>
                        </Button>
                        <a
                            href={`/opd/dashboard?room_id=${current_room?.id ?? ''}`}
                            className="flex flex-col items-center py-4 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-center"
                        >
                            <span className="text-xl mb-1">🔄</span>
                            <span className="text-xs font-medium">Refresh Queue</span>
                        </a>
                        <a
                            href="/patients/search"
                            className="flex flex-col items-center py-4 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-center"
                        >
                            <span className="text-xl mb-1">🔍</span>
                            <span className="text-xs font-medium">Search Patient</span>
                        </a>
                        <a
                            href="/visits/register"
                            className="flex flex-col items-center py-4 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-center"
                        >
                            <span className="text-xl mb-1">📋</span>
                            <span className="text-xs font-medium">Visit History</span>
                        </a>
                    </div>
                </div>
            </div>

            {/* Queue table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-700">Today's Queue</h3>
                    <span className="text-xs text-gray-500">{queue.length} patient{queue.length !== 1 ? 's' : ''}</span>
                </div>

                {queue.length === 0 ? (
                    <div className="px-5 py-10 text-center text-gray-400 text-sm">
                        No patients in the queue yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 border-b">
                                <tr>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">#</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Patient</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Card No</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Arrived</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Wait</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                                    <th className="text-right px-4 py-3 font-medium text-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {queue.map((entry) => (
                                    <tr key={entry.id} className="border-t hover:bg-gray-50">
                                        <td className="px-4 py-3 font-bold text-green-700">
                                            {entry.queue_number}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-gray-800">
                                            {entry.patient.full_name}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-gray-600 text-xs">
                                            {entry.patient.card_number}
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 text-xs">
                                            {new Date(entry.arrived_at).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}
                                        </td>
                                        <td className="px-4 py-3 text-gray-600 text-xs">
                                            {entry.status === 'Waiting' ? waitingTime(entry.arrived_at) : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[entry.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {entry.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1.5 flex-wrap">
                                                {entry.status === 'Waiting' && (
                                                    <StatusButton entryId={entry.id} status="Called" label="Call" className="bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100" />
                                                )}
                                                {entry.status === 'Called' && (
                                                    <a
                                                        href={`/opd/consultation/${entry.id}`}
                                                        className="px-2.5 py-1 text-xs font-medium rounded border bg-green-50 text-green-700 border-green-200 hover:bg-green-100"
                                                    >
                                                        Start
                                                    </a>
                                                )}
                                                {['Waiting', 'Called'].includes(entry.status) && (
                                                    <StatusButton entryId={entry.id} status="Cancelled" label="Cancel" className="bg-red-50 text-red-700 border-red-200 hover:bg-red-100" />
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
