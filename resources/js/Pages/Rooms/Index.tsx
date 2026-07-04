import { Head, useForm, router } from '@inertiajs/react';
import AppLayout, { Button, Input, Select } from '@/Layouts/AppLayout';
import { useState } from 'react';
import FlashAlert from '@/components/FlashAlert';

interface Room {
    id: number;
    room_name: string;
    room_code: string;
    is_active: boolean;
}

interface Props {
    rooms: Room[];
}

// ── Edit Modal ───────────────────────────────────────────────────────────────
function EditModal({ room, onClose }: { room: Room; onClose: () => void }) {
    const form = useForm({
        room_name: room.room_name,
        room_code: room.room_code,
        is_active: room.is_active,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(`/rooms/${room.id}`, {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                <h2 className="text-lg font-semibold text-gray-800 mb-4">Edit Room</h2>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Room Name</label>
                        <Input
                            value={form.data.room_name}
                            onChange={(e) => form.setData('room_name', e.target.value)}
                            placeholder="Room Name"
                        />
                        {form.errors.room_name && (
                            <p className="text-red-500 text-xs mt-1">{form.errors.room_name}</p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Room Code</label>
                        <Input
                            value={form.data.room_code}
                            onChange={(e) => form.setData('room_code', e.target.value)}
                            placeholder="Room Code"
                        />
                        {form.errors.room_code && (
                            <p className="text-red-500 text-xs mt-1">{form.errors.room_code}</p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <Select
                            value={form.data.is_active ? '1' : '0'}
                            onChange={(e) => form.setData('is_active', e.target.value === '1')}
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </Select>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ── Delete Confirm Modal ──────────────────────────────────────────────────────
function DeleteModal({ room, onClose }: { room: Room; onClose: () => void }) {
    const [processing, setProcessing] = useState(false);

    function handleDelete() {
        setProcessing(true);
        router.delete(`/rooms/${room.id}`, {
            onFinish: () => {
                setProcessing(false);
                onClose();
            },
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
                <h2 className="text-lg font-semibold text-gray-800 mb-2">Delete Room</h2>
                <p className="text-sm text-gray-600 mb-6">
                    Are you sure you want to delete{' '}
                    <span className="font-semibold text-gray-800">{room.room_name}</span>{' '}
                    (<span className="font-mono">{room.room_code}</span>)?
                    <br />
                    <span className="text-red-500 text-xs mt-1 block">
                        This action cannot be undone. Rooms with visit records cannot be deleted.
                    </span>
                </p>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="button" variant="danger" disabled={processing} onClick={handleDelete}>
                        {processing ? 'Deleting…' : 'Delete Room'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────
export default function RoomsIndex({ rooms }: Props) {
    const createForm = useForm({ room_name: '', room_code: '' });
    const [editingRoom, setEditingRoom] = useState<Room | null>(null);
    const [deletingRoom, setDeletingRoom] = useState<Room | null>(null);

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/rooms', {
            onSuccess: () => createForm.reset(),
        });
    }

    return (
        <AppLayout title="Room Management">
            <Head title="Rooms" />

            {/* Edit Modal */}
            {editingRoom && (
                <EditModal room={editingRoom} onClose={() => setEditingRoom(null)} />
            )}

            {/* Delete Modal */}
            {deletingRoom && (
                <DeleteModal room={deletingRoom} onClose={() => setDeletingRoom(null)} />
            )}

            {/* Add Room Form */}
            <div className="bg-white rounded-xl border shadow-sm p-5 mb-6">
                <h3 className="text-sm font-semibold text-gray-700 mb-3">Add New Room</h3>
                <form onSubmit={submitCreate} className="flex flex-wrap gap-3 items-end">
                    <div className="flex-1 min-w-[180px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Room Name</label>
                        <Input
                            placeholder="e.g. OPD Room 1"
                            value={createForm.data.room_name}
                            onChange={(e) => createForm.setData('room_name', e.target.value)}
                        />
                        {createForm.errors.room_name && (
                            <p className="text-red-500 text-xs mt-1">{createForm.errors.room_name}</p>
                        )}
                    </div>
                    <div className="flex-1 min-w-[140px]">
                        <label className="block text-xs font-medium text-gray-600 mb-1">Room Code</label>
                        <Input
                            placeholder="e.g. OPD-1"
                            value={createForm.data.room_code}
                            onChange={(e) => createForm.setData('room_code', e.target.value)}
                        />
                        {createForm.errors.room_code && (
                            <p className="text-red-500 text-xs mt-1">{createForm.errors.room_code}</p>
                        )}
                    </div>
                    <Button type="submit" disabled={createForm.processing}>
                        + Add Room
                    </Button>
                </form>
            </div>

            {/* Rooms Table */}
            <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div className="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-700">All Rooms</h3>
                    <span className="text-xs text-gray-500">{rooms.length} room{rooms.length !== 1 ? 's' : ''}</span>
                </div>
                {rooms.length === 0 ? (
                    <div className="px-5 py-10 text-center text-gray-400 text-sm">
                        No rooms added yet. Use the form above to add your first room.
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b">
                            <tr>
                                <th className="text-left px-5 py-3 font-medium text-gray-600">#</th>
                                <th className="text-left px-5 py-3 font-medium text-gray-600">Room Name</th>
                                <th className="text-left px-5 py-3 font-medium text-gray-600">Code</th>
                                <th className="text-left px-5 py-3 font-medium text-gray-600">Status</th>
                                <th className="text-right px-5 py-3 font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rooms.map((room, index) => (
                                <tr key={room.id} className="border-t hover:bg-gray-50 transition-colors">
                                    <td className="px-5 py-3 text-gray-400">{index + 1}</td>
                                    <td className="px-5 py-3 font-medium text-gray-800">{room.room_name}</td>
                                    <td className="px-5 py-3 font-mono text-gray-600">{room.room_code}</td>
                                    <td className="px-5 py-3">
                                        <span
                                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                room.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-600'
                                            }`}
                                        >
                                            {room.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                onClick={() => setEditingRoom(room)}
                                                className="px-3 py-1.5 text-xs font-medium rounded-md bg-yellow-50 text-yellow-700 border border-yellow-200 hover:bg-yellow-100 transition-colors"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => setDeletingRoom(room)}
                                                className="px-3 py-1.5 text-xs font-medium rounded-md bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-colors"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AppLayout>
    );
}
