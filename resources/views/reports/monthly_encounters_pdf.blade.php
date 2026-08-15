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

    <div class="header">
        <h1>Monthly Encounters Report - {{ $year }}</h1>
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
                        @php
                            $patientName = $encounter->patient->user->name 
                                ?? trim(($encounter->patient->user->firstname ?? '') . ' ' . ($encounter->patient->user->surname ?? ''))
                                ?? 'Unknown Patient';
                                
                            $receptionistName = 'N/A';
                            if (method_exists($encounter, 'queue') && $encounter->queue && $encounter->queue->receptionist) {
                                $receptionistName = $encounter->queue->receptionist->user->name 
                                    ?? trim(($encounter->queue->receptionist->user->firstname ?? '') . ' ' . ($encounter->queue->receptionist->user->surname ?? ''));
                            } elseif ($encounter->productOrServiceRequest && $encounter->productOrServiceRequest->user) {
                                $receptionistName = $encounter->productOrServiceRequest->user->name 
                                    ?? trim(($encounter->productOrServiceRequest->user->firstname ?? '') . ' ' . ($encounter->productOrServiceRequest->user->surname ?? ''));
                            }
                                
                            $clinicName = 'N/A';
                            if (method_exists($encounter, 'queue') && $encounter->queue && $encounter->queue->clinic) {
                                $clinicName = $encounter->queue->clinic->name ?? 'N/A';
                            } elseif ($encounter->doctor && $encounter->doctor->staff_profile && $encounter->doctor->staff_profile->clinic) {
                                $clinicName = $encounter->doctor->staff_profile->clinic->name ?? 'N/A';
                            }

                            $doctorName = $encounter->doctor->name 
                                ?? trim(($encounter->doctor->firstname ?? '') . ' ' . ($encounter->doctor->surname ?? ''))
                                ?? 'N/A';
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($encounter->created_at)->format('M d, Y h:i A') }}</td>
                            <td>{{ $patientName ?: 'Unknown Patient' }}</td>
                            <td>{{ $clinicName ?: 'N/A' }}</td>
                            <td>{{ $receptionistName ?: 'N/A' }}</td>
                            <td>{{ $doctorName ?: 'N/A' }}</td>
                            <td>
                                @if($encounter->completed)
                                    <span class="status-completed">Completed</span>
                                @else
                                    <span class="status-ongoing">Ongoing</span>
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
