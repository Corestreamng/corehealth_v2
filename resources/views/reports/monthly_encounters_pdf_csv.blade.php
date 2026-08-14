<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Encounters Report - {{ $year }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #2c3e50; font-size: 24px; }
        .header p { color: #7f8c8d; margin-top: 5px; }
        
        .stats-container { width: 100%; margin-bottom: 30px; border-collapse: separate; border-spacing: 15px; }
        .stats-container td { 
            padding: 20px; 
            text-align: center; 
            border: 1px solid #ecf0f1; 
            background-color: #f8f9fa; 
            border-radius: 8px; 
            width: 25%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stats-container .stat-value { font-size: 28px; font-weight: bold; color: #3498db; margin-bottom: 5px; }
        .stats-container .stat-label { font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: bold; }
        
        .month-section { margin-bottom: 40px; }
        .month-title { 
            font-size: 18px; 
            color: #2c3e50; 
            border-bottom: 2px solid #3498db; 
            padding-bottom: 8px; 
            margin-bottom: 15px; 
            font-weight: 600;
        }
        .month-title span { float: right; font-size: 14px; color: #7f8c8d; font-weight: normal; margin-top: 4px; }
        
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.details th, table.details td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; }
        table.details th { background-color: #f4f6f7; color: #2c3e50; font-weight: bold; font-size: 11px; text-transform: uppercase; }
        table.details tr:nth-child(even) { background-color: #fcfcfc; }
        
        .status-completed { color: #27ae60; font-weight: bold; }
        .status-ongoing { color: #e67e22; font-weight: bold; }
    </style>
</head>
<body>

    @if($show_summary)
    <div class="header">
        <h1>Monthly Encounters Report - {{ $year }} (Prod Data)</h1>
        <p>Generated on {{ \Carbon\Carbon::now()->format('F d, Y h:i A') }}</p>
    </div>

    <table class="stats-container">
        <tr>
            <td>
                <div class="stat-value">{{ number_format($stats['total_encounters']) }}</div>
                <div class="stat-label">Total Encounters</div>
            </td>
            <td>
                <div class="stat-value">{{ number_format($stats['total_patients']) }}</div>
                <div class="stat-label">Unique Patients</div>
            </td>
            <td>
                <div class="stat-value">{{ number_format($stats['total_doctors']) }}</div>
                <div class="stat-label">Doctors Seen</div>
            </td>
            <td>
                <div class="stat-value">{{ number_format($stats['total_clinics']) }}</div>
                <div class="stat-label">Clinics Active</div>
            </td>
        </tr>
    </table>
    @endif

    @foreach($grouped as $month => $encounters)
        <div class="month-section">
            <h2 class="month-title">
                {{ $month }} 
                <span>{{ $encounters->count() }} Encounters</span>
            </h2>
            <table class="details">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Clinic</th>
                        <th>Booked By (Receptionist)</th>
                        <th>Seen By (Doctor)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($encounters as $encounter)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($encounter->created_at)->format('M d, Y h:i A') }}</td>
                            <td>{{ $encounter->patient_name }}</td>
                            <td>{{ $encounter->clinic_name }}</td>
                            <td>{{ $encounter->receptionist_name }}</td>
                            <td>{{ $encounter->doctor_name }}</td>
                            <td>
                                @if(strtolower($encounter->status) === 'completed')
                                    <span class="status-completed">Completed</span>
                                @else
                                    <span class="status-ongoing">{{ $encounter->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

</body>
</html>
