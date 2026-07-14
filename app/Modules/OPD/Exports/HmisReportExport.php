<?php

namespace App\Modules\OPD\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HmisReportExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data) {}

    public function sheets(): array
    {
        return [
            'Overview'          => new OverviewSheet($this->data['overview']),
            'Demographics'      => new DemographicsSheet($this->data['demographics']),
            'Disease'           => new DiseaseSheet($this->data['disease']),
            'Laboratory'        => new LaboratorySheet($this->data['laboratory']),
            'Pharmacy'          => new PharmacySheet($this->data['pharmacy']),
            'Referrals'         => new ReferralSheet($this->data['referrals']),
            'Sick Leave'        => new SickLeaveSheet($this->data['sickLeave']),
            'Completed Visits'  => new VisitsSheet($this->data['visits']),
        ];
    }
}

class OverviewSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        $rows = collect();
        $rows->push(['Total Encounters', $this->data['total_encounters']]);
        $rows->push(['Unique Patients', $this->data['unique_patients']]);
        $rows->push(['Lab Requests', $this->data['lab_requests']]);
        $rows->push(['Prescriptions', $this->data['prescriptions']]);
        $rows->push(['Referrals', $this->data['referrals']]);
        $rows->push(['Sick Leaves', $this->data['sick_leaves']]);
        $rows->push(['Completion Rate', $this->data['completion_rate'] . '%']);
        $rows->push(['Avg Wait Time', $this->data['avg_wait_minutes'] . 'm']);
        return $rows;
    }

    public function headings(): array { return ['Metric', 'Value']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class DemographicsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        $rows = collect();
        foreach ($this->data['by_type'] as $item) {
            $rows->push(['Patient Type', $item['label'], $item['count']]);
        }
        foreach ($this->data['by_gender'] as $item) {
            $rows->push(['Gender', $item['label'], $item['count']]);
        }
        foreach ($this->data['by_age'] as $item) {
            $rows->push(['Age Group', $item['label'], $item['count']]);
        }
        return $rows;
    }

    public function headings(): array { return ['Category', 'Label', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class DiseaseSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        $rows = collect();
        foreach ($this->data['by_diagnosis'] as $item) {
            $rows->push(['Diagnosis', $item['label'], $item['count']]);
        }
        foreach ($this->data['by_complaint'] as $item) {
            $rows->push(['Chief Complaint', $item['label'], $item['count']]);
        }
        return $rows;
    }

    public function headings(): array { return ['Type', 'Label', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class LaboratorySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        return $this->data['by_test']->map(fn ($item) => [$item['label'], $item['count']]);
    }

    public function headings(): array { return ['Test Name', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class PharmacySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        return $this->data['by_medicine']->map(fn ($item) => [$item['label'], $item['count']]);
    }

    public function headings(): array { return ['Medicine', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class ReferralSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        return $this->data['by_destination']->map(fn ($item) => [$item['label'], $item['count']]);
    }

    public function headings(): array { return ['Destination', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class SickLeaveSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        return $this->data['by_employee']->map(fn ($item) => [$item['label'], $item['count']]);
    }

    public function headings(): array { return ['Employee', 'Count']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}

class VisitsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private readonly array $data) {}

    public function collection()
    {
        return $this->data['by_room']->map(fn ($item) => [$item['label'], $item['count'], $item['completed'], $item['transferred']]);
    }

    public function headings(): array { return ['Room', 'Total', 'Completed', 'Transferred']; }
    public function map($row): array { return $row; }
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1B5E20']]]];
    }
}
