<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMIS Report — MSF HMS</title>
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
        h2 { font-size: 13px; font-weight: bold; color: #1b5e20; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 11px; font-weight: bold; color: #333; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 10px; }
        thead tr { background: #1b5e20; color: #fff; }
        thead th { padding: 5px 6px; text-align: left; font-weight: bold; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid #ddd; }
        tbody tr:nth-child(even) { background: #f9f9f9; }
        tbody td { padding: 4px 6px; vertical-align: top; }
        .footer { margin-top: 14px; font-size: 8px; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
        .section { margin-bottom: 16px; }
        @media print {
            body { padding: 0; font-size: 8px; }
            .header h1 { font-size: 14px; }
            table { font-size: 8px; }
            thead th { padding: 3px 4px; }
            tbody td { padding: 2px 4px; }
            .section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Metahara Sugar Factory Hospital</h1>
        <p>OPD — HMIS Report</p>
    </div>

    <div class="meta">
        <span>Period: {{ ucfirst($period) }} &nbsp;|&nbsp; {{ $overview['start_date'] }} to {{ $overview['end_date'] }}</span>
        <span>Generated: {{ now()->format('Y-m-d H:i') }}</span>
    </div>

    {{-- ═══ Overview ═══ --}}
    <div class="section">
        <h2>1. Overview</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $overview['total_encounters'] }}</strong>Encounters</div>
            <div class="summary-box"><strong>{{ $overview['unique_patients'] }}</strong>Patients</div>
            <div class="summary-box"><strong>{{ $overview['lab_requests'] }}</strong>Lab Requests</div>
            <div class="summary-box"><strong>{{ $overview['prescriptions'] }}</strong>Prescriptions</div>
            <div class="summary-box"><strong>{{ $overview['referrals'] }}</strong>Referrals</div>
            <div class="summary-box"><strong>{{ $overview['sick_leaves'] }}</strong>Sick Leave</div>
            <div class="summary-box"><strong>{{ $overview['completion_rate'] }}%</strong>Completion</div>
            <div class="summary-box"><strong>{{ $overview['avg_wait_minutes'] }}m</strong>Avg Wait</div>
        </div>
    </div>

    {{-- ═══ Demographics ═══ --}}
    <div class="section">
        <h2>2. Patient Demographics</h2>

        @if($demographics['by_type']->count())
        <h3>By Patient Type</h3>
        <table>
            <thead><tr><th>Type</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                @foreach($demographics['by_type'] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $item['count'] }}</td>
                    <td>{{ $demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($demographics['by_gender']->count())
        <h3>By Gender</h3>
        <table>
            <thead><tr><th>Gender</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                @foreach($demographics['by_gender'] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $item['count'] }}</td>
                    <td>{{ $demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($demographics['by_age']->count())
        <h3>By Age Group</h3>
        <table>
            <thead><tr><th>Age Group</th><th>Count</th><th>%</th></tr></thead>
            <tbody>
                @foreach($demographics['by_age'] as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td>{{ $item['count'] }}</td>
                    <td>{{ $demographics['total_patients'] > 0 ? round(($item['count'] / $demographics['total_patients']) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Disease Statistics ═══ --}}
    <div class="section">
        <h2>3. Disease Statistics</h2>

        @if($disease['by_diagnosis']->count())
        <h3>Top Diagnoses</h3>
        <table>
            <thead><tr><th>#</th><th>Diagnosis</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($disease['by_diagnosis'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($disease['by_complaint']->count())
        <h3>Top Chief Complaints</h3>
        <table>
            <thead><tr><th>#</th><th>Chief Complaint</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($disease['by_complaint'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Laboratory ═══ --}}
    <div class="section">
        <h2>4. Laboratory</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $laboratory['total_requests'] }}</strong>Total Requests</div>
            <div class="summary-box"><strong>{{ $laboratory['completed'] }}</strong>Completed</div>
            <div class="summary-box"><strong>{{ $laboratory['pending'] }}</strong>Pending</div>
            <div class="summary-box"><strong>{{ $laboratory['urgent'] }}</strong>Urgent</div>
        </div>

        @if($laboratory['by_test']->count())
        <h3>Most Requested Tests</h3>
        <table>
            <thead><tr><th>#</th><th>Test</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($laboratory['by_test'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Pharmacy ═══ --}}
    <div class="section">
        <h2>5. Pharmacy</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $pharmacy['total_prescriptions'] }}</strong>Prescriptions</div>
            <div class="summary-box"><strong>{{ $pharmacy['total_items'] }}</strong>Total Items</div>
            <div class="summary-box"><strong>{{ $pharmacy['internal'] }}</strong>Internal</div>
            <div class="summary-box"><strong>{{ $pharmacy['external'] }}</strong>External</div>
        </div>

        @if($pharmacy['by_medicine']->count())
        <h3>Most Prescribed Medicines</h3>
        <table>
            <thead><tr><th>#</th><th>Medicine</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($pharmacy['by_medicine'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Referrals ═══ --}}
    <div class="section">
        <h2>6. Referrals</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $referrals['total_referrals'] }}</strong>Total Referrals</div>
        </div>

        @if($referrals['by_destination']->count())
        <h3>By Destination</h3>
        <table>
            <thead><tr><th>#</th><th>Destination</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($referrals['by_destination'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Sick Leave ═══ --}}
    <div class="section">
        <h2>7. Sick Leave</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $sickLeave['total_sick_leaves'] }}</strong>Total</div>
            <div class="summary-box"><strong>{{ $sickLeave['total_days'] }}</strong>Total Days</div>
            <div class="summary-box"><strong>{{ $sickLeave['avg_days'] }}</strong>Avg Days</div>
        </div>

        @if($sickLeave['by_employee']->count())
        <h3>By Employee</h3>
        <table>
            <thead><tr><th>#</th><th>Employee</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($sickLeave['by_employee'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ═══ Completed Visits ═══ --}}
    <div class="section">
        <h2>8. Completed Visits</h2>
        <div class="summary">
            <div class="summary-box"><strong>{{ $visits['total'] }}</strong>Total</div>
            <div class="summary-box"><strong>{{ $visits['completed'] }}</strong>Completed</div>
            <div class="summary-box"><strong>{{ $visits['transferred'] }}</strong>Transferred</div>
            <div class="summary-box"><strong>{{ $visits['avg_duration_mins'] }}m</strong>Avg Duration</div>
            <div class="summary-box"><strong>{{ $visits['min_duration_mins'] }}m</strong>Min</div>
            <div class="summary-box"><strong>{{ $visits['max_duration_mins'] }}m</strong>Max</div>
        </div>

        @if($visits['by_room']->count())
        <h3>By Room</h3>
        <table>
            <thead><tr><th>Room</th><th>Total</th><th>Completed</th><th>Transferred</th></tr></thead>
            <tbody>
                @foreach($visits['by_room'] as $item)
                <tr><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td><td>{{ $item['completed'] }}</td><td>{{ $item['transferred'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if($visits['by_doctor']->count())
        <h3>By Doctor/Nurse</h3>
        <table>
            <thead><tr><th>#</th><th>Doctor/Nurse</th><th>Count</th></tr></thead>
            <tbody>
                @foreach($visits['by_doctor'] as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item['label'] }}</td><td>{{ $item['count'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="footer">MSF HMS — OPD Module &nbsp;|&nbsp; Confidential &nbsp;|&nbsp; For internal use only</div>
</body>
</html>
