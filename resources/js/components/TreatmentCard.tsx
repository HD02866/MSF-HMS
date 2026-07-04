export interface TreatmentCardPatient {
    card_number: string;
    full_name: string;
    gender?: string | null;
    date_of_birth?: string;
    phone?: string | null;
    address?: string | null;
    woreda?: string | null;
    kebele?: string | null;
    house_no?: string | null;
    visits?: Array<{
        visit_date: string;
        visit_time?: string;
        remarks?: string | null;
        room?: { room_name?: string } | null;
    }>;
}

interface TreatmentRow {
    date: string;
    treatment: string;
}

const PAGE1_ROWS = 24;
const CONTINUATION_ROWS = 36;
const TOTAL_PAGES = 4;

function calculateAge(dateOfBirth?: string): string {
    if (!dateOfBirth) return '';
    const dob = new Date(dateOfBirth);
    if (Number.isNaN(dob.getTime())) return '';
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
    }
    return String(age);
}

function formatCardDate(date?: Date): string {
    const value = date ?? new Date();
    return value.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

function buildTreatmentRows(patient: TreatmentCardPatient): TreatmentRow[] {
    const visits = [...(patient.visits ?? [])].sort((a, b) => a.visit_date.localeCompare(b.visit_date));

    return visits.map((visit) => {
        const room = visit.room?.room_name ? `Room: ${visit.room.room_name}` : '';
        const remarks = visit.remarks?.trim() ?? '';
        const treatment = [room, remarks].filter(Boolean).join(' — ') || '';

        return {
            date: visit.visit_date,
            treatment,
        };
    });
}

function padRows(rows: TreatmentRow[], count: number): TreatmentRow[] {
    const padded = [...rows];
    while (padded.length < count) {
        padded.push({ date: '', treatment: '' });
    }
    return padded.slice(0, count);
}

function splitPages(rows: TreatmentRow[]): TreatmentRow[][] {
    const page1 = padRows(rows.slice(0, PAGE1_ROWS), PAGE1_ROWS);
    const remaining = rows.slice(PAGE1_ROWS);
    const pages: TreatmentRow[][] = [page1];

    for (let page = 1; page < TOTAL_PAGES; page += 1) {
        const start = (page - 1) * CONTINUATION_ROWS;
        pages.push(padRows(remaining.slice(start, start + CONTINUATION_ROWS), CONTINUATION_ROWS));
    }

    return pages;
}

function CardLine({
    label,
    value,
    className,
}: {
    label: string;
    value?: string | null;
    className?: string;
}) {
    return (
        <span className={`treatment-card-line-field ${className ?? ''}`}>
            <span className="treatment-card-line-label">{label}</span>
            <span className="treatment-card-line-value">{value || '\u00A0'}</span>
        </span>
    );
}

function CardStackedField({
    amharic,
    english,
    value,
}: {
    amharic: string;
    english: string;
    value?: string | null;
}) {
    return (
        <div className="treatment-card-stacked-field">
            <div className="treatment-card-stacked-label">
                <span className="treatment-card-amharic">{amharic}</span>
                <span>{english}</span>
            </div>
            <div className="treatment-card-stacked-value">{value || '\u00A0'}</div>
        </div>
    );
}

function PageLabel({ page }: { page: number }) {
    return (
        <div className="treatment-card-page-label">
            Page {page} of {TOTAL_PAGES}
        </div>
    );
}

function TreatmentTable({ rows }: { rows: TreatmentRow[] }) {
    return (
        <table className="treatment-card-table">
            <thead>
                <tr>
                    <th className="treatment-card-date-col">
                        <span className="treatment-card-amharic">ቀን</span>
                        <span>Date</span>
                    </th>
                    <th>
                        <span className="treatment-card-amharic">የሕክምና</span>
                        <span>Treatment</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row, index) => (
                    <tr key={`${row.date}-${index}`}>
                        <td>{row.date}</td>
                        <td>{row.treatment}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function CardPageOne({
    patient,
    rows,
    issueDate,
}: {
    patient: TreatmentCardPatient;
    rows: TreatmentRow[];
    issueDate: string;
}) {
    return (
        <section className="treatment-card-page">
            <PageLabel page={1} />

            <table className="treatment-card-header-table">
                <tbody>
                    <tr>
                        <td rowSpan={2} className="treatment-card-logo-cell">
                            <img
                                src="/images/Logo.jpg"
                                alt="Metahara Sugar Factory"
                                className="treatment-card-logo"
                            />
                        </td>
                        <td className="treatment-card-header-main">
                            Company Name: <strong>METAHARA SUGER FACTORY</strong>
                        </td>
                        <td className="treatment-card-header-side">OF/MSF/HRSM/097</td>
                    </tr>
                    <tr>
                        <td className="treatment-card-header-main">
                            From Title: <strong>የሕክምና ካርድ / TREATMENT CARD</strong>
                        </td>
                        <td className="treatment-card-header-side">
                            Issue No.2
                            <br />
                            Page 1 of 4
                        </td>
                    </tr>
                </tbody>
            </table>

            <div className="treatment-card-meta-grid">
                <div className="treatment-card-meta-block">
                    <div><span className="treatment-card-amharic">መርቲ</span>- MERTI</div>
                    <div>Tel. 002 455 00 12/13</div>
                    <div>P.O.bOX 5664</div>
                </div>
                <div className="treatment-card-meta-block treatment-card-meta-right">
                    <CardStackedField amharic="ቀን" english="(Date)" value={issueDate} />
                    <CardStackedField amharic="" english="Date" value={issueDate} />
                    <CardStackedField amharic="ካርድ ቁጥር" english="(Card No.)" value={patient.card_number} />
                    <CardStackedField amharic="" english="Card No." value={patient.card_number} />
                </div>
            </div>

            <div className="treatment-card-patient-row">
                <CardLine label="Name" value={patient.full_name} className="treatment-card-line-wide" />
                <CardLine label="Age" value={calculateAge(patient.date_of_birth)} className="treatment-card-line-short" />
                <CardLine label="Sex" value={patient.gender ?? ''} className="treatment-card-line-short" />
            </div>

            <div className="treatment-card-patient-row">
                <CardLine label="Address" value={patient.address ?? ''} className="treatment-card-line-medium" />
                <CardLine label="Woreda" value={patient.woreda ?? ''} className="treatment-card-line-medium" />
                <CardLine label="Kebele" value={patient.kebele ?? ''} className="treatment-card-line-medium" />
                <CardLine label="House No." value={patient.house_no ?? ''} className="treatment-card-line-medium" />
                <CardLine label="Tel." value={patient.phone ?? ''} className="treatment-card-line-medium" />
            </div>

            <TreatmentTable rows={rows} />
        </section>
    );
}

function CardContinuationPage({
    page,
    rows,
    showFooter,
}: {
    page: number;
    rows: TreatmentRow[];
    showFooter?: boolean;
}) {
    return (
        <section className="treatment-card-page">
            <PageLabel page={page} />
            <TreatmentTable rows={rows} />
            {showFooter && <div className="treatment-card-footer">Samket plc</div>}
        </section>
    );
}

export default function TreatmentCard({ patient }: { patient: TreatmentCardPatient }) {
    const issueDate = formatCardDate();
    const pages = splitPages(buildTreatmentRows(patient));

    return (
        <div className="treatment-card-stack">
            <CardPageOne patient={patient} rows={pages[0]} issueDate={issueDate} />
            <CardContinuationPage page={2} rows={pages[1]} />
            <CardContinuationPage page={3} rows={pages[2]} />
            <CardContinuationPage page={4} rows={pages[3]} showFooter />
        </div>
    );
}
