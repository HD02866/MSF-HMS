import { Head, useForm } from '@inertiajs/react';
import AppLayout, { Button, Select } from '@/Layouts/AppLayout';

interface Props {
    patient: any | null;
    rooms: Array<{ id: number; room_name: string; room_code: string }>;
}

export default function AssignRoom({ patient, rooms }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        patient_id: patient?.id ?? '',
        room_id: '',
        remarks: '',
    });

    if (!patient) {
        return (
            <AppLayout title="Assign Room">
                <Head title="Assign Room" />
                <p className="text-gray-500">Select a patient from Patient Search first.</p>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Assign Room">
            <Head title="Assign Room" />

            <div className="max-w-lg space-y-6">
                <div className="bg-white rounded-lg border p-4">
                    <h3 className="font-semibold mb-3">Selected Patient</h3>
                    <dl className="text-sm space-y-1">
                        <div className="flex justify-between"><dt className="text-gray-500">Card</dt><dd className="font-mono">{patient.card_number}</dd></div>
                        <div className="flex justify-between"><dt className="text-gray-500">Name</dt><dd>{patient.full_name}</dd></div>
                        <div className="flex justify-between"><dt className="text-gray-500">Type</dt><dd>{patient.patient_type?.name}</dd></div>
                    </dl>
                </div>

                <form onSubmit={(e) => { e.preventDefault(); post('/visits', { preserveScroll: true }); }} className="bg-white rounded-lg border p-4 space-y-4">
                    {Object.keys(errors).length > 0 && (
                        <div className="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                            {errors.room_id || errors.patient_id || 'Patient assignment failed. Please check the form and try again.'}
                        </div>
                    )}

                    <div>
                        <label className="block text-sm font-medium mb-2">Select Room *</label>
                        <div className="grid grid-cols-2 gap-2">
                            {rooms.map((room) => (
                                <label
                                    key={room.id}
                                    className={`border rounded-md p-3 cursor-pointer text-sm text-center ${String(data.room_id) === String(room.id) ? 'border-green-600 bg-green-50 ring-2 ring-green-500' : 'hover:border-green-300'}`}
                                >
                                    <input
                                        type="radio"
                                        name="room_id"
                                        value={room.id}
                                        className="sr-only"
                                        checked={String(data.room_id) === String(room.id)}
                                        onChange={() => setData('room_id', String(room.id))}
                                    />
                                    {room.room_name}
                                </label>
                            ))}
                        </div>
                        {errors.room_id && <p className="text-red-600 text-xs mt-1">{errors.room_id}</p>}
                        {errors.patient_id && <p className="text-red-600 text-xs mt-1">{errors.patient_id}</p>}
                    </div>
                    <Button type="submit" disabled={processing || !data.room_id} className="w-full">
                        Assign Room
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
