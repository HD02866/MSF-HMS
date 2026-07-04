<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MSF HMS Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #111; }
        h1 { color: #16a34a; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0fdf4; }
        .meta { color: #666; margin-bottom: 24px; }
    </style>
</head>
<body>
    <h1>MSF HMS — {{ ucfirst($report['period']) }} Report</h1>
    <p class="meta">{{ $report['start_date'] }} to {{ $report['end_date'] }}</p>

    <h2>Summary</h2>
    <p><strong>Total Visits:</strong> {{ $report['total_visits'] }}</p>

    <h2>By Patient Type</h2>
    <table>
        <thead><tr><th>Type</th><th>Count</th></tr></thead>
        <tbody>
            @foreach($report['by_patient_type'] as $type => $count)
                <tr><td>{{ $type }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Room Utilization</h2>
    <table>
        <thead><tr><th>Room</th><th>Visits</th></tr></thead>
        <tbody>
            @foreach($report['by_room'] as $room => $count)
                <tr><td>{{ $room }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
