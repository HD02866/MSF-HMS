<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPD Register — MSF HMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: #fff; padding: 15px; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 2px solid #111; padding-bottom: 8px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header p { font-size: 11px; color: #333; margin-top: 2px; }
        .meta { display: flex; justify-content: space-between; font-size: 9px; color: #555; margin-bottom: 10px; }
        .summary { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .summary-box { border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; font-size: 9px; }
        .summary-box strong { display: block; font-size: 14px; color: #111; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        thead tr { background: #1b5e20; color: #fff; }
        thead th { padding: 5px 6px; text-align: left; font-weight: bold; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        tbody td { padding: 4px 6px; vertical-align: top; }
        .status-completed { color: #2e7d32; font-weight: bold; }
        .status-transferred { color: #7b1fa2; font-weight: bold; }
        .cc-cell, .dx-cell { max-width: 120px; overflow: hidden; text-overflow: ellipsis; }
        .footer { margin-top: 14px; font-size: 8px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
        @media print {
            body { padding: 0; font-size: 8px; }
            .header h1 { font-size: 14px; }
            table { font-size: 8px; }
            thead th { padding: 3px 4px; }
            tbody td { padding: 2px 4px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Metahara Sugar Factory Hospital</h1>
        <p>OPD — Consultation Register</p>
    </div>

    <div class="meta">
        <span>
            @if(!empty($filters['period']) && $filters['period'] !== 'all')
                Period: {{ ucfirst($filters['period']) }}
                @if(!empty($filters['date']))
                    — {{ $filters['date'] }}
                @endif
            @else
                All Dates
            @endif
            @if(!empty($filters['status'])) &nbsp;|&nbsp; Status: {{ $filters['status'] }} @endif
        </span>
        <span>Generated: {{ now()->format('Y-m-d H:i') }}</span>
    </div>

    <div class="summary">
        <div class="summary-box"><strong>{{ $summary['total'] }}</strong>Total</div>
        <div class="summary-box"><strong>{{ $summary['completed'] }}</strong>Completed</div>
        <div class="summary-box"><strong>{{ $summary['transferred'] }}</strong>Transferred</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Room</th>
                <th>Queue</th>
                <th>Card No.</th>
                <th>Patient Name</th>
                <th>Sex</th>
                <th>Age</th>
                <th>Type</th>
                <th>Chief Complaint</th>
                <th>Diagnosis</th>
                <th>Doctor/Nurse</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->arrived_at?->toDateString() ?? '—' }}</td>
                <td>{{ $row->room?->room_name ?? '—' }}</td>
                <td>{{ $row->queue_number ?? '—' }}</td>
                <td>{{ $row->patient?->card_number ?? '—' }}</td>
                <td>{{ $row->patient?->full_name ?? '—' }}</td>
                <td>{{ $row->patient?->gender ?? '—' }}</td>
                <td>{{ $row->patient?->date_of_birth ? $row->patient->date_of_birth->age : '—' }}</td>
                <td>{{ $row->patient?->patientType?->name ?? '—' }}</td>
                <td class="cc-cell">{{ $row->clinicalNote?->chief_complaint ?? '—' }}</td>
                <td class="dx-cell">{{ $row->clinicalNote?->diagnosis ?? '—' }}</td>
                <td>{{ $row->clinicalNote?->creator?->full_name ?? '—' }}</td>
                <td class="{{ $row->status === 'Completed' ? 'status-completed' : 'status-transferred' }}">
                    {{ $row->status }}
                </td>
            </tr>
            @empty
            <tr><td colspan="13" style="text-align:center;padding:14px;color:#888;">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">MSF HMS — OPD Module &nbsp;|&nbsp; Confidential &nbsp;|&nbsp; For internal use only</div>
</body>
</html>
