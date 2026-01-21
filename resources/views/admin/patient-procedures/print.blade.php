<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procedure Report - {{ optional($procedure->service)->service_name ?? 'Procedure' }}</title>
    @php
        // Get settings object
        $sett = appsettings();
        $hosColor = $sett->hos_color ?? '#0066cc';

        // Determine which sections to show based on query parameters
        // Default to showing all sections if no params provided
        $showAll = !request()->hasAny(['patient_info', 'procedure_details', 'team', 'notes', 'labs', 'imaging', 'meds', 'outcome', 'billing']);

        $showPatientInfo = $showAll || request()->has('patient_info');
        $showProcedureDetails = $showAll || request()->has('procedure_details');
        $showTeam = $showAll || request()->has('team');
        $showNotes = $showAll || request()->has('notes');
        $showLabs = $showAll || request()->has('labs');
        $showImaging = $showAll || request()->has('imaging');
        $showMeds = $showAll || request()->has('meds');
        $showOutcome = $showAll || request()->has('outcome');
        $showBilling = $showAll || request()->has('billing');
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
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        /* Branded Header - Same as Imaging Results */
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px;
            border-bottom: 3px solid {{ $hosColor }};
            margin-bottom: 0;
        }
        .result-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .result-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .result-hospital-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: {{ $hosColor }};
            max-width: 280px;
            line-height: 1.3;
        }
        .result-header-right {
            text-align: right;
            font-size: 0.85rem;
            color: #495057;
            line-height: 1.6;
        }
        .result-header-right strong {
            color: #212529;
        }
        .result-title-section {
            background: {{ $hosColor }};
            color: white;
            text-align: center;
            padding: 10px 20px;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .result-footer {
            padding: 15px 20px;
            border-top: 2px solid #dee2e6;
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            background: #f8f9fa;
            margin-top: 20px;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: {{ $hosColor }};
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            min-width: 120px;
        }
        .info-value {
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-requested { background-color: #e2e3e5; color: #383d41; }
        .status-scheduled { background-color: #cce5ff; color: #004085; }
        .status-in_progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        .priority-routine { color: #28a745; }
        .priority-urgent { color: #ffc107; font-weight: bold; }
        .priority-emergency { color: #dc3545; font-weight: bold; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .bundled-yes { color: #1565c0; }
        .bundled-no { color: #c62828; }
        .notes-content {
            background-color: #fafafa;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .notes-meta {
            font-size: 10px;
            color: #888;
            margin-top: 5px;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #888;
            text-align: center;
        }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        .outcome-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .outcome-label {
            font-weight: bold;
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            margin-bottom: 5px;
        }
        .outcome-successful { background-color: #d4edda; color: #155724; }
        .outcome-complications { background-color: #fff3cd; color: #856404; }
        .outcome-aborted { background-color: #f8d7da; color: #721c24; }
        .outcome-converted { background-color: #cce5ff; color: #004085; }

        /* Lab/Imaging/Meds Result Sections */
        .result-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .result-card-header {
            background: #f8f9fa;
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .result-card-header h6 {
            margin: 0;
            font-size: 12px;
            font-weight: 600;
        }
        .result-card-body {
            padding: 12px;
        }
        .result-timeline {
            font-size: 11px;
            color: #666;
            margin-bottom: 10px;
        }
        .result-timeline div {
            margin-bottom: 4px;
        }
        .result-content {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-size: 11px;
            margin-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #cce5ff; color: #004085; }
        .badge-secondary { background: #e9ecef; color: #6c757d; }
        .badge-primary { background: #cce5ff; color: #004085; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .container { padding: 0; max-width: 100%; }
            .section { page-break-inside: avoid; }
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: {{ $hosColor }};
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .print-button:hover {
            opacity: 0.9;
        }
        .billing-table td:last-child {
            text-align: right;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Report</button>

    <div class="container">
        {{-- Branded Hospital Header - Same as Imaging Results --}}
        <div class="result-header">
            <div class="result-header-left">
                <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Hospital Logo" class="result-logo" />
                <div class="result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
            </div>
            <div class="result-header-right">
                <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
            </div>
        </div>

        {{-- Document Title --}}
        <div class="result-title-section">
            PROCEDURE REPORT
        </div>

        {{-- Patient Information --}}
        @if($showPatientInfo)
        <div class="section">
            <div class="section-title">Patient Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Patient Name:</span>
                    <span class="info-value">{{ userfullname($procedure->patient->user_id ?? 0) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">File Number:</span>
                    <span class="info-value">{{ optional($procedure->patient)->file_no ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value">
                        {{ optional($procedure->patient)->dob
                            ? \Carbon\Carbon::parse($procedure->patient->dob)->format('d M Y')
                            : 'N/A' }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender:</span>
                    <span class="info-value">{{ ucfirst(optional($procedure->patient)->gender ?? 'N/A') }}</span>
                </div>
                @if(optional($procedure->patient)->hmo)
                    <div class="info-item">
                        <span class="info-label">HMO:</span>
                        <span class="info-value">{{ $procedure->patient->hmo->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">HMO ID:</span>
                        <span class="info-value">{{ optional($procedure->patient)->hmo_no ?? 'N/A' }}</span>
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Procedure Details --}}
        @if($showProcedureDetails)
        <div class="section">
            <div class="section-title">Procedure Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Procedure:</span>
                    <span class="info-value"><strong>{{ optional($procedure->service)->service_name ?? 'N/A' }}</strong></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Code:</span>
                    <span class="info-value">{{ optional($procedure->service)->service_code ?? 'N/A' }}</span>
                </div>
                @if($procedure->procedureDefinition && $procedure->procedureDefinition->procedureCategory)
                    <div class="info-item">
                        <span class="info-label">Category:</span>
                        <span class="info-value">{{ $procedure->procedureDefinition->procedureCategory->name }}</span>
                    </div>
                @endif
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge status-{{ $procedure->procedure_status }}">
                            {{ \App\Models\Procedure::STATUSES[$procedure->procedure_status] ?? ucfirst($procedure->procedure_status) }}
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Priority:</span>
                    <span class="info-value priority-{{ $procedure->priority }}">{{ ucfirst($procedure->priority) }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Requested By:</span>
                    <span class="info-value">{{ optional($procedure->requestedByUser)->name ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Requested On:</span>
                    <span class="info-value">{{ $procedure->requested_on ? $procedure->requested_on->format('d M Y H:i') : 'N/A' }}</span>
                </div>
                @if($procedure->scheduled_date)
                    <div class="info-item">
                        <span class="info-label">Scheduled Date:</span>
                        <span class="info-value">{{ $procedure->scheduled_date->format('d M Y') }}</span>
                    </div>
                @endif
                @if($procedure->operating_room)
                    <div class="info-item">
                        <span class="info-label">Operating Room:</span>
                        <span class="info-value">{{ $procedure->operating_room }}</span>
                    </div>
                @endif
                @if($procedure->actual_start_time)
                    <div class="info-item">
                        <span class="info-label">Started At:</span>
                        <span class="info-value">{{ $procedure->actual_start_time->format('d M Y H:i') }}</span>
                    </div>
                @endif
                @if($procedure->actual_end_time)
                    <div class="info-item">
                        <span class="info-label">Ended At:</span>
                        <span class="info-value">{{ $procedure->actual_end_time->format('d M Y H:i') }}</span>
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Surgical Team --}}
        @if($showTeam && $procedure->teamMembers->count() > 0)
            <div class="section">
                <div class="section-title">Surgical Team</div>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Lead</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($procedure->teamMembers as $member)
                            <tr>
                                <td>{{ optional($member->user)->name ?? 'Unknown' }}</td>
                                <td>{{ $member->role === 'other' ? $member->custom_role : ucwords(str_replace('_', ' ', $member->role)) }}</td>
                                <td>{{ $member->is_lead ? 'Yes' : '-' }}</td>
                                <td>{{ $member->notes ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Procedure Notes by Type --}}
        @if($showNotes)
            @foreach(['pre_op' => 'Pre-Operative Notes', 'intra_op' => 'Intra-Operative Notes', 'post_op' => 'Post-Operative Notes', 'anesthesia' => 'Anesthesia Notes', 'nursing' => 'Nursing Notes'] as $noteType => $noteTitle)
                @php
                    $typeNotes = $procedure->notes->where('note_type', $noteType);
                @endphp
                @if($typeNotes->count() > 0)
                    <div class="section">
                        <div class="section-title">{{ $noteTitle }}</div>
                        @foreach($typeNotes as $note)
                            <div class="notes-content">
                                <div class="notes-title">{{ $note->title }}</div>
                                {!! $note->content !!}
                                <div class="notes-meta">
                                    By: {{ optional($note->createdBy)->name ?? 'Unknown' }} | {{ $note->created_at->format('d M Y H:i') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Lab Results Section --}}
        @if($showLabs)
            @php
                $labItems = $procedure->items->filter(fn($i) => $i->lab_service_request_id !== null);
            @endphp
            @if($labItems->count() > 0)
                <div class="section">
                    <div class="section-title">Laboratory Results</div>
                    @foreach($labItems as $item)
                        @php
                            $req = $item->labServiceRequest;
                        @endphp
                        @if($req)
                            <div class="result-card">
                                <div class="result-card-header">
                                    <h6>{{ optional($req->service)->service_name ?? 'Lab Test' }}</h6>
                                    <div>
                                        @if($req->result)
                                            <span class="badge badge-success">Result Available</span>
                                        @elseif($req->sample_taken_by)
                                            <span class="badge badge-warning">Sample Taken</span>
                                        @elseif($req->billed_by)
                                            <span class="badge badge-info">Billed</span>
                                        @else
                                            <span class="badge badge-secondary">Pending</span>
                                        @endif
                                        @if($item->is_bundled)
                                            <span class="badge badge-info">Bundled</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="result-card-body">
                                    <div class="result-timeline">
                                        <div><strong>Requested:</strong> {{ $req->doctor_id ? userfullname($req->doctor_id) : 'N/A' }} ({{ $req->created_at ? $req->created_at->format('d M Y H:i') : 'N/A' }})</div>
                                        @if($req->billed_by)
                                            <div><strong>Billed:</strong> {{ userfullname($req->billed_by) }} ({{ $req->billed_date ? date('d M Y H:i', strtotime($req->billed_date)) : 'N/A' }})</div>
                                        @endif
                                        @if($req->sample_taken_by)
                                            <div><strong>Sample Collected:</strong> {{ userfullname($req->sample_taken_by) }} ({{ $req->sample_date ? date('d M Y H:i', strtotime($req->sample_date)) : 'N/A' }})</div>
                                        @endif
                                        @if($req->result_by)
                                            <div><strong>Result By:</strong> {{ userfullname($req->result_by) }} ({{ $req->result_date ? date('d M Y H:i', strtotime($req->result_date)) : 'N/A' }})</div>
                                        @endif
                                    </div>
                                    @if($req->note)
                                        <div><strong>Note:</strong> {{ $req->note }}</div>
                                    @endif
                                    @if($req->result)
                                        <div class="result-content">
                                            <strong>Result:</strong><br>
                                            {!! $req->result !!}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Imaging Results Section --}}
        @if($showImaging)
            @php
                $imagingItems = $procedure->items->filter(fn($i) => $i->imaging_service_request_id !== null);
            @endphp
            @if($imagingItems->count() > 0)
                <div class="section">
                    <div class="section-title">Imaging Results</div>
                    @foreach($imagingItems as $item)
                        @php
                            $req = $item->imagingServiceRequest;
                        @endphp
                        @if($req)
                            <div class="result-card">
                                <div class="result-card-header">
                                    <h6>{{ optional($req->service)->service_name ?? 'Imaging' }}</h6>
                                    <div>
                                        @if($req->result)
                                            <span class="badge badge-success">Result Available</span>
                                        @elseif($req->billed_by)
                                            <span class="badge badge-info">Billed</span>
                                        @else
                                            <span class="badge badge-secondary">Pending</span>
                                        @endif
                                        @if($item->is_bundled)
                                            <span class="badge badge-info">Bundled</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="result-card-body">
                                    <div class="result-timeline">
                                        <div><strong>Requested:</strong> {{ $req->doctor_id ? userfullname($req->doctor_id) : 'N/A' }} ({{ $req->created_at ? $req->created_at->format('d M Y H:i') : 'N/A' }})</div>
                                        @if($req->billed_by)
                                            <div><strong>Billed:</strong> {{ userfullname($req->billed_by) }} ({{ $req->billed_date ? date('d M Y H:i', strtotime($req->billed_date)) : 'N/A' }})</div>
                                        @endif
                                        @if($req->result_by)
                                            <div><strong>Result By:</strong> {{ userfullname($req->result_by) }} ({{ $req->result_date ? date('d M Y H:i', strtotime($req->result_date)) : 'N/A' }})</div>
                                        @endif
                                    </div>
                                    @if($req->note)
                                        <div><strong>Note:</strong> {{ $req->note }}</div>
                                    @endif
                                    @if($req->result)
                                        <div class="result-content">
                                            <strong>Result:</strong><br>
                                            {!! $req->result !!}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Medications Section --}}
        @if($showMeds)
            @php
                $medItems = $procedure->items->filter(fn($i) => $i->product_request_id !== null);
            @endphp
            @if($medItems->count() > 0)
                <div class="section">
                    <div class="section-title">Medications</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Medication</th>
                                <th>Dose</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Billing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($medItems as $item)
                                @php
                                    $req = $item->productRequest;
                                    $statusLabel = 'Pending';
                                    $statusClass = 'badge-secondary';
                                    if($req) {
                                        if ($req->status == 0) {
                                            $statusLabel = 'Dismissed';
                                            $statusClass = 'badge-danger';
                                        } elseif ($req->status == 1) {
                                            $statusLabel = 'Unbilled';
                                            $statusClass = 'badge-warning';
                                        } elseif ($req->status == 2) {
                                            $statusLabel = 'Billed';
                                            $statusClass = 'badge-info';
                                        } elseif ($req->status == 3) {
                                            $statusLabel = 'Dispensed';
                                            $statusClass = 'badge-success';
                                        }
                                    }
                                @endphp
                                @if($req)
                                    <tr>
                                        <td>
                                            {{ optional($req->product)->product_name ?? 'Medication' }}
                                            <div style="font-size:10px;color:#666;">{{ optional($req->product)->product_code ?? '' }}</div>
                                        </td>
                                        <td>{{ $req->dose ?? 'N/A' }}</td>
                                        <td>{{ $req->qty ?? 1 }}</td>
                                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td class="{{ $item->is_bundled ? 'bundled-yes' : 'bundled-no' }}">
                                            {{ $item->is_bundled ? '✓ Bundled' : '✗ Separate' }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- Outcome --}}
        @if($showOutcome && $procedure->outcome)
            <div class="section">
                <div class="section-title">Procedure Outcome</div>
                <div class="outcome-section">
                    <span class="outcome-label outcome-{{ $procedure->outcome }}">
                        {{ \App\Models\Procedure::OUTCOMES[$procedure->outcome] ?? ucfirst($procedure->outcome) }}
                    </span>
                    @if($procedure->outcome_notes)
                        <p style="margin-top: 10px;">{{ $procedure->outcome_notes }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Billing Summary --}}
        @if($showBilling && $procedure->productOrServiceRequest)
            <div class="section">
                <div class="section-title">Billing Summary</div>
                @php
                    $billing = $procedure->productOrServiceRequest;
                @endphp
                <table class="billing-table">
                    <tr>
                        <td>Procedure Fee</td>
                        <td>₦{{ number_format(($billing->amount ?? 0) + ($billing->claims_amount ?? 0), 2) }}</td>
                    </tr>
                    @if(($billing->claims_amount ?? 0) > 0)
                        <tr>
                            <td>Patient Responsibility</td>
                            <td>₦{{ number_format($billing->amount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>HMO Claims</td>
                            <td>₦{{ number_format($billing->claims_amount ?? 0, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td><strong>Payment Status</strong></td>
                        <td><strong>{{ $billing->payment_id ? 'PAID' : 'UNPAID' }}</strong></td>
                    </tr>
                </table>
            </div>
        @endif

        {{-- Cancellation Info --}}
        @if($procedure->procedure_status === 'cancelled')
            <div class="section">
                <div class="section-title" style="color: #dc3545;">Cancellation Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Cancelled By:</span>
                        <span class="info-value">{{ optional($procedure->cancelledByUser)->name ?? 'N/A' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cancelled At:</span>
                        <span class="info-value">{{ $procedure->cancelled_at ? $procedure->cancelled_at->format('d M Y H:i') : 'N/A' }}</span>
                    </div>
                    <div class="info-item" style="grid-column: span 2;">
                        <span class="info-label">Reason:</span>
                        <span class="info-value">{{ $procedure->cancellation_reason ?? 'N/A' }}</span>
                    </div>
                    @if(($procedure->refund_amount ?? 0) > 0)
                        <div class="info-item">
                            <span class="info-label">Refund Amount:</span>
                            <span class="info-value" style="color: #28a745; font-weight: bold;">₦{{ number_format($procedure->refund_amount, 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Signature Section --}}
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Lead Surgeon / Physician</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Hospital Stamp</div>
            </div>
        </div>

        {{-- Branded Footer - Same as Imaging Results --}}
        <div class="result-footer">
            <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
            <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
            <div style="margin-top: 10px; font-size: 11px;">This is a computer-generated document. Report generated on {{ now()->format('d M Y H:i') }} by {{ Auth::user()->name ?? 'System' }}</div>
        </div>
    </div>
</body>
</html>
