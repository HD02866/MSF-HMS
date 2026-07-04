<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Register — MSF HMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; padding: 20px; }
        .header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p { font-size: 11px; color: #333; margin-top: 3px; }
        .meta { display: flex; justify-content: space-between; font-size: 10px; color: #555; margin-bottom: 14px; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .summary-box { border: 1px solid #ccc; padding: 6px 12px; border-radius: 4px; font-size: 10px; }
        .summary-box strong { display: block; font-size: 14px; color: #111; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        thead tr { background: #1b5e20; color: #fff; }
        thead th { padding: 6px 8px; text-align: left; font-weight: bold; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        tbody td { padding: 5px 8px; }
        .footer { margin-top: 18px; font-size: 9px; color: #888; text-align: center; }
        @media print {
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Metahara Sugar Factory Hospital</h1>
        <p>Card Room — Daily Register Report</p>
    </div>

    <div class="meta">
        <span>
            @if(!empty($filters['record_date']))Date: {{ $filters['record_date'] }}@else All Dates @endif
            @if(!empty($filters['register_type'])) &nbsp;|&nbsp; Type: {{ $types[$filters['register_type']] ?? $filters['register_type'] }} @endif
        </span>
        <span>Generated: {{ now()->format('Y-m-d H:i') }}</span>
    </div>

    <div class="summary">
        <div class="summary-box"><strong>{{ $summary['total'] }}</strong>Total</div>
        <div class="summary-box"><strong>{{ $summary['family'] }}</strong>Family</div>
        <div class="summary-box"><strong>{{ $summary['employee'] }}</strong>Employee</div>
        <div class="summary-box"><strong>{{ $summary['os'] }}</strong>OS</div>
        <div class="summary-box"><strong>{{ $summary['referral_accident'] }}</strong>Ref. Accident</div>
        <div class="summary-box"><strong>{{ $summary['referral_sick_leave'] }}</strong>Ref. Sick Leave</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Register Type</th>
                <th>ID Number</th>
                <th>Patient Name</th>
                <th>Sex</th>
                <th>Age</th>
                <th>Department</th>
            </tr>
        </thead>
        <tbody>
            @forelse($registers as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->record_date->toDateString() }}</td>
                <td>{{ $types[$row->register_type] ?? $row->register_type }}</td>
                <td>{{ $row->patient?->card_number ?? '—' }}</td>
                <td>{{ $row->patient?->full_name ?? '—' }}</td>
                <td>{{ $row->patient?->gender ?? '—' }}</td>
                <td>{{ $row->patient?->date_of_birth ? $row->patient->date_of_birth->age : '—' }}</td>
                <td>{{ $row->department_name ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;padding:14px;color:#888;">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">MSF HMS — Card Room Module &nbsp;|&nbsp; Confidential &nbsp;|&nbsp; For internal use only</div>
</body>
</html>
