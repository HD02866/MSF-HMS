<?php

namespace App\Modules\CardRoom\Exports;

use App\Models\DailyRegister;
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

class DailyRegisterExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize,
    WithEvents
{
    /**
     * Row background colors per register type (hex, no #).
     */
    private const TYPE_COLORS = [
        'family'              => 'FFF9C4', // yellow
        'employee'            => 'C8E6C9', // green
        'os'                  => 'BBDEFB', // blue
        'referral_accident'   => 'FFCDD2', // light red
        'referral_sick_leave' => 'EF9A9A', // red
    ];

    public function __construct(private readonly Collection $registers) {}

    public function collection(): Collection
    {
        return $this->registers;
    }

    public function headings(): array
    {
        return ['Date', 'Register Type', 'ID Number', 'Patient Name', 'Sex', 'Age', 'Department'];
    }

    /** @param DailyRegister $row */
    public function map($row): array
    {
        $patient = $row->patient;

        return [
            $row->record_date->toDateString(),
            DailyRegister::TYPES[$row->register_type] ?? $row->register_type,
            $patient?->card_number ?? '',
            $patient?->full_name ?? '',
            $patient?->gender ?? '',
            $patient?->date_of_birth ? $patient->date_of_birth->age : '',
            $row->department_name ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Style header row
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
                $rowIndex = 2; // data starts at row 2 (row 1 is header)

                foreach ($this->registers as $register) {
                    $color = self::TYPE_COLORS[$register->register_type] ?? 'FFFFFF';
                    $sheet->getStyle("A{$rowIndex}:G{$rowIndex}")->applyFromArray([
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
