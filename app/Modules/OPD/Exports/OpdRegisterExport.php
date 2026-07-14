<?php

namespace App\Modules\OPD\Exports;

use App\Models\OpdQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OpdRegisterExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithEvents
{
    private const STATUS_COLORS = [
        'Completed'   => 'C8E6C9', // green
        'Transferred' => 'E1BEE7', // purple
    ];

    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Date',
            'Room',
            'Queue #',
            'Card No.',
            'Patient Name',
            'Sex',
            'Age',
            'Patient Type',
            'Chief Complaint',
            'Diagnosis',
            'Doctor/Nurse',
            'Status',
        ];
    }

    /** @param OpdQueue $row */
    public function map($row): array
    {
        $patient     = $row->patient;
        $clinical    = $row->clinicalNote;
        $patientType = $patient?->patientType;

        return [
            '', // # — filled by row index in AfterSheet
            $row->arrived_at?->toDateString() ?? '',
            $row->room?->room_name ?? '',
            $row->queue_number ?? '',
            $patient?->card_number ?? '',
            $patient?->full_name ?? '',
            $patient?->gender ?? '',
            $patient?->date_of_birth ? $patient->date_of_birth->age : '',
            $patientType?->name ?? '',
            $clinical?->chief_complaint ?? '',
            $clinical?->diagnosis ?? '',
            $clinical->creator?->full_name ?? '',
            $row->status ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1B5E20'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowIndex = 2;

                foreach ($this->rows as $row) {
                    // Row number
                    $sheet->setCellValue("A{$rowIndex}", $rowIndex - 1);

                    $color = self::STATUS_COLORS[$row->status] ?? 'FFFFFF';
                    $sheet->getStyle("A{$rowIndex}:M{$rowIndex}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF'.$color],
                        ],
                    ]);
                    $rowIndex++;
                }
            },
        ];
    }
}
