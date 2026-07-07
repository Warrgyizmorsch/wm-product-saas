<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($type) }} Performance Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .report-card {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        @media print {
            body {
                background-color: #ffffff;
            }
            .no-print {
                display: none !important;
            }
            .report-card {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="py-4">
    <div class="container">
        {{-- Controls --}}
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <a href="javascript:window.close();" class="btn btn-secondary">Close Window</a>
            <button onclick="window.print();" class="btn btn-primary">Print Report</button>
        </div>

        {{-- Report Card --}}
        <div class="report-card p-5">
            <div class="row mb-4 pb-3 border-bottom">
                <div class="col-6">
                    <h2 class="fw-bold text-dark mb-1">{{ ucfirst($type) }} Performance Report</h2>
                    <p class="text-muted fs-14">SaaS Enterprise Manufacturing Intelligence</p>
                </div>
                <div class="col-6 text-end">
                    <div class="text-muted fs-12">Period Start: <strong>{{ $reportData['period_start'] }}</strong></div>
                    <div class="text-muted fs-12">Period End: <strong>{{ $reportData['period_end'] }}</strong></div>
                    <div class="text-muted fs-12">Generated At: <strong>{{ now()->toDateTimeString() }}</strong></div>
                </div>
            </div>

            {{-- Table representation of report data --}}
            @if($type === 'machine')
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Machine Code</th>
                            <th>Machine Name</th>
                            <th>OEE %</th>
                            <th>Availability %</th>
                            <th>Performance %</th>
                            <th>Quality %</th>
                            <th>Total Units</th>
                            <th>Downtime (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['data'] as $row)
                            <tr>
                                <td class="fw-bold">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-primary fw-bold">{{ $row['oee'] }}%</td>
                                <td>{{ $row['availability'] }}%</td>
                                <td>{{ $row['performance'] }}%</td>
                                <td class="text-success">{{ $row['quality'] }}%</td>
                                <td>{{ $row['total_produced'] }}</td>
                                <td class="text-danger">{{ $row['downtime_minutes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif($type === 'work-center')
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Work Center Code</th>
                            <th>Work Center Name</th>
                            <th>OEE %</th>
                            <th>Availability %</th>
                            <th>Performance %</th>
                            <th>Quality %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['data'] as $row)
                            <tr>
                                <td class="fw-bold">{{ $row['code'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-primary fw-bold">{{ $row['oee'] }}%</td>
                                <td>{{ $row['availability'] }}%</td>
                                <td>{{ $row['performance'] }}%</td>
                                <td class="text-success">{{ $row['quality'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif($type === 'downtime')
                <h5 class="fw-bold text-dark mb-3">Downtime Events Log</h5>
                <table class="table table-bordered table-striped mb-4">
                    <thead>
                        <tr>
                            <th>Machine</th>
                            <th>Downtime Category</th>
                            <th>Reason</th>
                            <th>Started At</th>
                            <th>Resolved At</th>
                            <th>Duration (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportData['downtimes'] as $d)
                            <tr>
                                <td class="fw-bold">{{ $d->machine->name ?? '—' }}</td>
                                <td><span class="badge bg-soft-danger text-danger">{{ $d->category }}</span></td>
                                <td>{{ $d->reason ?? '—' }}</td>
                                <td>{{ $d->start_time }}</td>
                                <td>{{ $d->end_time ?? 'Unresolved' }}</td>
                                <td class="text-danger fw-bold">{{ $d->duration_minutes ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No downtime events found for the period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <h5 class="fw-bold text-dark mb-3">Category Breakdown Summary</h5>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Downtime Category</th>
                            <th>Total Events Count</th>
                            <th>Total Accumulated Duration (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportData['category_summary'] as $sum)
                            <tr>
                                <td class="fw-bold">{{ $sum->category }}</td>
                                <td>{{ $sum->total_events }}</td>
                                <td class="text-danger fw-bold">{{ $sum->total_duration }} mins</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @if($print)
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    @endif
</body>
</html>
