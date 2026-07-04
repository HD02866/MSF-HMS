import { Head, Link } from '@inertiajs/react';
import AppLayout, { Button } from '@/Layouts/AppLayout';
import TreatmentCard from '@/components/TreatmentCard';
import { formatTime } from '@/lib/utils';

interface Props {
    patient: any;
}

export default function ShowPatient({ patient }: Props) {
    const printCard = () => {
        window.print();
    };

    return (
        <AppLayout title="Patient Details">
            <Head title={patient.full_name} />

            <div className="flex flex-wrap gap-3 mb-4 print:hidden">
                <Link href={`/patients/${patient.id}/edit`}><Button>Edit</Button></Link>
                <Link href={`/visits/assign?patient_id=${patient.id}`}><Button className="bg-yellow-500 hover:bg-yellow-600">Assign Room</Button></Link>
                <Button variant="secondary" onClick={printCard}>Print Treatment Card</Button>
                <Link href="/patients/search"><Button variant="secondary">Back to Search</Button></Link>
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6 print:hidden">
                <div className="bg-white rounded-lg border p-4">
                    <h3 className="font-semibold mb-4">Patient Information</h3>
                    <dl className="space-y-2 text-sm">
                        {[
                            ['Card Number', patient.card_number],
                            ['Full Name', patient.full_name],
                            ['Type', patient.patient_type?.name],
                            ['Relationship', patient.relationship_type?.name ?? '—'],
                            ['Gender', patient.gender ?? '—'],
                            ['Date of Birth', patient.date_of_birth],
                            ['Phone', patient.phone ?? '—'],
                            ['Address', patient.address ?? '—'],
                            ['Status', patient.status],
                        ].map(([label, value]) => (
                            <div key={label as string} className="flex justify-between border-b pb-2">
                                <dt className="text-gray-500">{label}</dt>
                                <dd className="font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </div>

                <div className="bg-white rounded-lg border overflow-hidden">
                    <h3 className="font-semibold px-4 py-3 border-b">Visit History</h3>
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="text-left px-4 py-2">Date</th>
                                <th className="text-left px-4 py-2">Time</th>
                                <th className="text-left px-4 py-2">Room</th>
                                <th className="text-left px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(patient.visits ?? []).map((v: any) => (
                                <tr key={v.id} className="border-t">
                                    <td className="px-4 py-2">{v.visit_date}</td>
                                    <td className="px-4 py-2">{formatTime(v.visit_time)}</td>
                                    <td className="px-4 py-2">{v.room?.room_name}</td>
                                    <td className="px-4 py-2">{v.status}</td>
                                </tr>
                            ))}
                            {(patient.visits ?? []).length === 0 && (
                                <tr><td colSpan={4} className="px-4 py-6 text-center text-gray-400">No visits yet</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <section className="mt-8 print:hidden">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h3 className="text-lg font-semibold text-gray-800">Treatment Card Preview</h3>
                        <p className="text-sm text-gray-500">Digital replica of the official 4-page patient card.</p>
                    </div>
                    <Button variant="secondary" onClick={printCard}>Print Card</Button>
                </div>
            </section>

            <div className="print-card-area mt-6 overflow-x-auto">
                <TreatmentCard patient={patient} />
            </div>
        </AppLayout>
    );
}
