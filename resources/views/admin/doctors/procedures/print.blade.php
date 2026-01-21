<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procedure Report - {{ $procedure->procedureDefinition->name ?? 'Procedure' }}</title>
    @php
        $sett = appsettings();
        $hosColor = appsettings('hos_color') ?? '#0066cc';
    @endphp
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            background: white;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
        }

        /* Result Header & Branding - From Imaging Modal */
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px;
            border-bottom: 3px solid {{ $hosColor }};
            background: #f8f9fa; /* Removed gradient for better print support */
        }
        .result-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .result-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
        }
        .result-hospital-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: {{ $hosColor }};
            max-width: 300px;
            line-height: 1.3;
        }
        .result-header-right {
            text-align: right;
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.6;
        }
        .result-header-right strong {
            color: #212529;
        }

        /* Result Title Section */
        .result-title-section {
            background: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 10px 20px;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0;
        }

        /* Patient Info Section */
        .result-patient-info {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .result-info-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            flex: 1;
        }
        .result-info-row {
            display: flex;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        .result-info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .result-info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 120px;
            font-size: 0.9rem;
        }
        .result-info-value {
            color: #212529;
            font-weight: 500;
            flex: 1;
        }

        /* Result Section */
        .result-section {
            padding: 20px;
        }
        .result-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: {{ $hosColor }};
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid {{ $hosColor }};
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table Styling */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .table th {
            background: {{ $hosColor }};
            color: white;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
        }
        .table td {
            padding: 10px 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        .table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .bg-secondary { background: #e2e3e5; color: #383d41; }
        .bg-primary { background: #cce5ff; color: #004085; }
        .bg-warning { background: #fff3cd; color: #856404; }
        .bg-success { background: #d4edda; color: #155724; }
        .bg-danger { background: #f8d7da; color: #721c24; }
        .bg-info { background: #d1ecf1; color: #0c5460; }

        /* Footer */
        .result-footer {
            padding: 20px;
            border-top: 2px solid #dee2e6;
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            background: #f8f9fa;
            page-break-inside: avoid;
        }

        @media print {
            body { background: white; }
            .no-print { display: none; }
            .container { padding: 0; }
            .result-header, .result-title-section { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <!-- Header -->
        <div class="result-header">
            <div class="result-header-left">
                @if(isset($sett->logo) && $sett->logo)
                    <img src="data:image/jpeg;base64,{{ $sett->logo }}" alt="Hospital Logo" class="result-logo" />
                @endif
                <div class="result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
            </div>
            <div class="result-header-right">
                <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
            </div>
        </div>

        <div class="result-title-section">PROCEDURE REPORT</div>

        <!-- Patient Info -->

        <div class="result-patient-info" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="result-info-box">
                <div class="result-info-row"><div class="result-info-label">Patient Name:</div><div class="result-info-value">{{ userfullname($procedure->patient->user_id) }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Patient ID:</div><div class="result-info-value">{{ $procedure->patient->file_no }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Gender:</div><div class="result-info-value">{{ ucfirst($procedure->patient->gender) }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Age:</div><div class="result-info-value">{{ \Carbon\Carbon::parse($procedure->patient->dob)->age }} Years</div></div>
                <div class="result-info-row"><div class="result-info-label">Requested By:</div><div class="result-info-value">{{ optional($procedure->requestedByUser)->name ?? 'N/A' }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Requested On:</div><div class="result-info-value">{{ $procedure->requested_on ? $procedure->requested_on->format('d M Y H:i') : 'N/A' }}</div></div>
            </div>
            <div class="result-info-box">
                <div class="result-info-row"><div class="result-info-label">Procedure:</div><div class="result-info-value">{{ $procedure->procedureDefinition->name ?? 'N/A' }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Code:</div><div class="result-info-value">{{ $procedure->procedureDefinition->code ?? 'N/A' }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Started At:</div><div class="result-info-value">{{ $procedure->actual_start_time ? $procedure->actual_start_time->format('d M Y H:i') : 'N/A' }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Ended At:</div><div class="result-info-value">{{ $procedure->actual_end_time ? $procedure->actual_end_time->format('d M Y H:i') : 'N/A' }}</div></div>
                <div class="result-info-row"><div class="result-info-label">Type:</div><div class="result-info-value">@if(optional($procedure->procedureDefinition)->is_surgical)<span class="badge bg-danger">SURGICAL</span>@else<span class="badge bg-secondary">NON-SURGICAL</span>@endif</div></div>
                <div class="result-info-row"><div class="result-info-label">Status:</div><div class="result-info-value">
                    <span class="badge bg-{{
                        $procedure->procedure_status === 'completed' ? 'success' :
                        ($procedure->procedure_status === 'in_progress' ? 'warning' :
                        ($procedure->procedure_status === 'cancelled' ? 'danger' : 'secondary'))
                    }}">
                        {{ ucfirst(str_replace('_', ' ', $procedure->procedure_status)) }}
                    </span>
                </div></div>
            </div>
        </div>

        <!-- Procedure Team -->
        <div class="result-section">
            <div class="result-section-title">Procedure Team</div>
            @if($procedure->teamMembers->count() > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th width="40%">Staff Member</th>
                        <th width="30%">Role</th>
                        <th width="30%">Role Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($procedure->teamMembers as $member)
                    <tr>
                        <td>
                            {{ $member->user ? userfullname($member->user->id) : 'Unknown' }}
                            @if($member->is_lead)
                                <span class="badge bg-success" style="font-size: 0.65rem; margin-left: 5px;">Lead</span>
                            @endif
                        </td>
                        <td>{{ $member->custom_role ?: \App\Models\ProcedureTeamMember::ROLES[$member->role] ?? ucfirst($member->role) }}</td>
                        <td>{{ \App\Models\ProcedureTeamMember::ROLES[$member->role] ?? 'Custom' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-muted">No team members recorded.</p>
            @endif
        </div>

        <!-- Outcome (for completed procedures) -->
        @if($procedure->procedure_status === 'completed')
        <div class="result-section">
            <div class="result-section-title">Outcome</div>
            <div style="padding: 15px;">
                <div><strong>Outcome:</strong> {{ \App\Models\Procedure::OUTCOMES[$procedure->outcome] ?? ucfirst($procedure->outcome) }}</div>
                @if($procedure->outcome_notes)
                <div style="margin-top: 8px;"><strong>Notes:</strong><br>{!! nl2br(e($procedure->outcome_notes)) !!}</div>
                @endif
            </div>
        </div>
        @endif

        <!-- Procedure Notes -->
        <div class="result-section">
            <div class="result-section-title">Procedure Notes</div>
            @if($procedure->notes->count() > 0)
                @foreach($procedure->notes as $note)
                <div style="margin-bottom: 20px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                    <div style="padding: 10px 15px; background: #f8f9fa; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            @php
                                $typeLabel = \App\Models\ProcedureNote::NOTE_TYPES[$note->note_type] ?? ucfirst($note->note_type);
                                $badgeClass = 'bg-secondary';
                                if($note->note_type == 'pre_op') $badgeClass = 'bg-info';
                                if($note->note_type == 'intra_op') $badgeClass = 'bg-warning';
                                if($note->note_type == 'post_op') $badgeClass = 'bg-success';
                                if($note->note_type == 'anesthesia') $badgeClass = 'bg-primary';
                            @endphp
                            <span class="badge {{ $badgeClass }}" style="margin-right: 10px;">{{ $typeLabel }}</span>
                            <strong>{{ $note->title }}</strong>
                        </div>
                        <div style="font-size: 0.85rem; color: #666;">
                            {{ $note->created_at->format('d M, Y h:i A') }} | By: {{ $note->createdBy ? (userfullname($note->createdBy->id)) : 'Unknown' }}
                        </div>
                    </div>
                    <div style="padding: 15px;">
                        {!! $note->content !!}
                    </div>
                </div>
                @endforeach
            @else
            <p class="text-muted">No procedure notes recorded.</p>
            @endif
        </div>

        <!-- Footer -->
        <div class="result-footer">
            <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
            <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
            <div style="margin-top: 10px; font-size: 11px;">This is a computer-generated document. Report generated on {{ now()->format('d M, Y h:i A') }}</div>
        </div>
    </div>
</body>
</html>
