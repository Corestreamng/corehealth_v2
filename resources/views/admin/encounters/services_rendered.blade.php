@extends('admin.layouts.app')
@section('title', 'Services Rendered')
@section('page_name', 'Services Rendered')
@section('subpage_name', 'Patient History')
@section('style')
    @php
        $primaryColor = appsettings()->hos_color ?? '#011b33';
        $hosName      = appsettings()->site_name ?? appsettings()->header_text ?? 'Hospital Management System';
        $hosAddress   = appsettings()->contact_address ?? '';
        $hosPhone     = appsettings()->contact_phones ?? '';
        $hosLogo      = appsettings()->logo ?? null;
    @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; }

        /* ── Screen styles ─────────────────────────────────────────── */
        .filter-bar { background:#f8f9fa; padding:12px 16px; border-radius:8px; }
        .service-section { margin-bottom:24px; }
        .section-header {
            background: var(--primary-color);
            color:#fff; padding:8px 16px;
            border-radius:6px 6px 0 0;
            font-weight:600;
        }
        /* ── Print branding header (hidden on screen) ─────────────── */
        #print-header { display:none; }

        /* ── Print Styles ─────────────────────────────────────────── */
        .thermal-only { display: none !important; }
        @media print {
            @page { margin: 0; size: auto; }
            .no-print, .filter-bar, .btn { display:none !important; }
            body { background:#fff !important; }
            .card-modern { box-shadow:none !important; border:1px solid #ccc !important; }
            #print-header { display:block !important; }
            body.thermal-mode .a4-only { display: none !important; }
            body.thermal-mode .thermal-only { display: block !important; }
            .con-full-content, .lab-full-content { word-break: break-word !important; overflow-wrap: break-word !important; }
            .con-full-content .bg-light, .lab-full-content .bg-light { background: transparent !important; }

            /* In-page thermal print mode resets */
            body.thermal-mode {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                background: #fff !important;
            }
            body.thermal-mode .container-scroller,
            body.thermal-mode .page-body-wrapper,
            body.thermal-mode .main-panel,
            body.thermal-mode .content-wrapper {
                display: block !important;
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                overflow: visible !important;
                background: transparent !important;
            }
            body.thermal-mode .ch-sidebar,
            body.thermal-mode .sidebar,
            body.thermal-mode .navbar,
            body.thermal-mode footer,
            body.thermal-mode .footer,
            body.thermal-mode .no-print,
            body.thermal-mode .filter-bar,
            body.thermal-mode .btn {
                display: none !important;
            }
            body.thermal-mode .sr-container {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 100% !important;
                margin: 0 !important;
                padding: 2mm 3mm 4mm !important;
                box-sizing: border-box !important;
                font-size: 11px !important;
                font-family: 'Consolas', 'Liberation Mono', monospace, sans-serif !important;
                color: #000 !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode .card-modern {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: transparent !important;
            }
            body.thermal-mode .service-section {
                margin-bottom: 6px !important;
            }
            body.thermal-mode #print-header {
                display: block !important;
                margin-bottom: 6px !important;
                padding-bottom: 4px !important;
                border-bottom: 1px dashed #000 !important;
                text-align: center !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode #print-header .hos-logo {
                max-height: 48px !important;
                max-width: 60px !important;
            }
            body.thermal-mode #print-header h4 {
                font-size: 13px !important;
                font-weight: 700 !important;
                color: #000 !important;
                margin-bottom: 2px !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode #print-header small,
            body.thermal-mode #print-header div {
                font-size: 11px !important;
                color: #000 !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                white-space: normal !important;
            }
            body.thermal-mode .section-header {
                background: transparent !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
                font-size: 12px !important;
                font-weight: 700 !important;
                text-transform: uppercase !important;
                border-radius: 0 !important;
                padding: 3px 0 !important;
                margin-bottom: 3px !important;
                text-align: center !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode .thermal-item {
                border-bottom: 1px dashed #aaa !important;
                padding: 3px 0 !important;
                margin-bottom: 3px !important;
                font-size: 11px !important;
                line-height: 1.35 !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode .thermal-item:last-child {
                border-bottom: none !important;
            }
            body.thermal-mode .thermal-row {
                display: flex !important;
                justify-content: space-between !important;
                align-items: flex-start !important;
                flex-wrap: wrap !important;
                gap: 4px !important;
                font-size: 11px !important;
                line-height: 1.35 !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
            }
            body.thermal-mode .thermal-row > *:first-child {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                white-space: normal !important;
            }
            body.thermal-mode .thermal-row > *:last-child:not(:first-child) {
                flex-shrink: 0 !important;
                text-align: right !important;
                max-width: 48% !important;
                word-break: break-word !important;
                overflow-wrap: break-word !important;
                white-space: normal !important;
            }
            body.thermal-mode .thermal-item,
            body.thermal-mode .thermal-item *,
            body.thermal-mode .thermal-row,
            body.thermal-mode .thermal-row * {
                font-size: 11px !important;
            }
        }
        body.thermal-mode #print-header { display:block; word-break: break-word; overflow-wrap: break-word; }
        body.thermal-mode .sr-container { max-width:{{ $thermalWidth ?? getThermalPrinterWidth() }} !important; margin:0 auto; font-size:11px; font-family: 'Consolas', 'Liberation Mono', monospace, sans-serif; color: #000; word-break: break-word; overflow-wrap: break-word; }
        body.thermal-mode #print-header .hos-logo { max-width:64px; }
        body.thermal-mode #print-header h4 { font-size:13px; text-transform: uppercase; word-break: break-word; overflow-wrap: break-word; }
        body.thermal-mode #print-header small,
        body.thermal-mode #print-header div { font-size:11px; word-break: break-word; overflow-wrap: break-word; white-space: normal; }
        body.thermal-mode .thermal-item { border-bottom: 1px dashed #888; padding: 4px 0; margin-bottom: 4px; font-size:11px; word-break: break-word; overflow-wrap: break-word; }
        body.thermal-mode .thermal-item:last-child { border-bottom: none; }
        body.thermal-mode .thermal-row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 4px; font-size:11px; word-break: break-word; overflow-wrap: break-word; }
        body.thermal-mode .thermal-row > *:first-child { flex: 1 1 auto; min-width: 0; word-break: break-word; overflow-wrap: break-word; white-space: normal; }
        body.thermal-mode .thermal-row > *:last-child:not(:first-child) { flex-shrink: 0; text-align: right; max-width: 48%; word-break: break-word; overflow-wrap: break-word; white-space: normal; }
        body.thermal-mode .thermal-item *, body.thermal-mode .thermal-row * { font-size: 11px; }
        body.thermal-mode .section-header { background: transparent; color: #000; border-bottom: 1px solid #000; font-size: 12px; text-transform: uppercase; border-radius: 0; padding: 4px 0; margin-bottom: 4px; text-align: center; word-break: break-word; overflow-wrap: break-word; }
    </style>
@endsection
@section('content')
    <div class="container-fluid sr-container">

        {{-- ── Print-only branded header ──────────────────────────── --}}
        <div id="print-header" class="text-center mb-3 pb-2" style="border-bottom:2px solid var(--primary-color, #011b33);">
            @if($hosLogo)
                <img src="data:image/gif;base64,{{ $hosLogo }}" alt="Logo" class="hos-logo mb-1" style="max-height:70px;">
            @endif
            <h4 class="mb-0 font-weight-bold" style="color:var(--primary-color)">{{ $hosName }}</h4>
            @if($hosAddress)
                <small class="d-block text-muted">{{ $hosAddress }}</small>
            @endif
            @if($hosPhone)
                <small class="d-block text-muted">Tel: {{ $hosPhone }}</small>
            @endif
            <div class="mt-2 font-weight-bold" style="font-size:1rem;">PATIENT SERVICES RENDERED</div>
            <div class="text-muted" style="font-size:0.85rem;">
                Patient: <strong>{{ userfullname($patient->user_id) }}</strong>
                &nbsp;|&nbsp; File No: <strong>{{ $patient->file_no ?? 'N/A' }}</strong>
                @if(Request::get('start_from') && Request::get('stop_at'))
                    &nbsp;|&nbsp; Period: <strong>{{ Request::get('start_from') }}</strong> – <strong>{{ Request::get('stop_at') }}</strong>
                @endif
            </div>
            <small class="text-muted">Printed: {{ now()->format('d M Y, H:i') }}</small>
        </div>

        {{-- ── Patient Header Card (screen) ────────────────────────── --}}
        <div class="card-modern mb-3 no-print">
            <div class="card-header-modern">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">
                            <i class="mdi mdi-account-circle-outline text-primary"></i>
                            {{ userfullname($patient->user_id) }}
                        </h2>
                        <p class="text-muted mb-0">
                            <span class="mr-3"><i class="mdi mdi-identifier mr-1"></i>File No: <strong>{{ $patient->file_no ?? 'N/A' }}</strong></span>
                            @if($patient->hmo)
                                <span class="mr-3"><i class="mdi mdi-shield-check mr-1"></i>{{ $patient->hmo->name }}</span>
                                @if($patient->hmo_no)
                                    <span class="mr-3"><i class="mdi mdi-card-account-details mr-1"></i>HMO No: {{ $patient->hmo_no }}</span>
                                @endif
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('patient.show', $patient->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="mdi mdi-account-details-outline mr-1"></i> Patient Profile
                    </a>
                </div>
            </div>
        </div>

        {{-- ── Filter & Print Controls ─────────────────────────────── --}}
        <div class="card-modern mb-3 no-print">
            <div class="card-body">
                <form action="{{ route('patient-services-rendered', ['patient_id' => $patient->id]) }}" method="get">
                    <div class="filter-bar d-flex align-items-center flex-wrap gap-2">
                        <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-calendar-range mr-1"></i> Date Range:</label>
                        <input type="date" name="start_from" class="form-control form-control-sm" style="max-width:160px;"
                            value="{{ Request::get('start_from') }}" required>
                        <span class="text-muted">to</span>
                        <input type="date" name="stop_at" class="form-control form-control-sm" style="max-width:160px;"
                            value="{{ Request::get('stop_at') }}" required>
                        <div class="custom-control custom-switch ml-2">
                            <input type="checkbox" class="custom-control-input" id="toggleFullNotes" name="full_notes" value="1" {{ Request::get('full_notes') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="toggleFullNotes">Full Notes</label>
                        </div>
                        <div class="custom-control custom-switch ml-2">
                            <input type="checkbox" class="custom-control-input" id="toggleFullLabs" name="full_labs" value="1" {{ Request::get('full_labs') ? 'checked' : '' }}>
                            <label class="custom-control-label" for="toggleFullLabs">Full Labs</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm ml-2">
                            <i class="mdi mdi-magnify"></i> Fetch
                        </button>
                        @if(Request::get('start_from') && Request::get('stop_at'))
                            <button type="button" class="btn btn-success btn-sm ml-2" id="btnPrintA4">
                                <i class="mdi mdi-printer mr-1"></i> Print A4
                            </button>
                            <button type="button" class="btn btn-info btn-sm" id="btnPrintThermal">
                                <i class="mdi mdi-receipt mr-1"></i> Print Thermal
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        @if(Request::get('start_from') && Request::get('stop_at'))
            <p class="text-muted mb-3 no-print">
                <i class="mdi mdi-calendar-clock mr-1"></i>
                Report period: <strong>{{ Request::get('start_from') }}</strong> to <strong>{{ Request::get('stop_at') }}</strong>
            </p>

            {{-- ── Consultations ────────────────────────────────────── --}}
            @if(isset($consultation) && count($consultation))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-stethoscope mr-2"></i>Consultations ({{ count($consultation) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive a4-only">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Date</th><th>Doctor</th><th>Specialization</th><th>Notes Summary</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($consultation as $i => $con)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $con->created_at?->format('d M Y') }}</td>
                                            <td>{{ $con->doctor && $con->doctor->staff_profile ? userfullname($con->doctor->staff_profile->user_id) : 'N/A' }}</td>
                                            <td>{{ $con->doctor && $con->doctor->staff_profile && $con->doctor->staff_profile->specialization ? $con->doctor->staff_profile->specialization->name : '—' }}</td>
                                            <td class="small">
                                                @if($con->notes)
                                                    <div class="con-short-content">
                                                        {!! \Illuminate\Support\Str::limit(strip_tags($con->notes), 150) !!}
                                                    </div>
                                                    <div class="con-full-content d-none">
                                                        <div class="p-2 border rounded bg-light mb-1" style="word-break:break-word;">
                                                            {!! \Illuminate\Support\Str::contains($con->notes, '<') ? $con->notes : nl2br(e($con->notes)) !!}
                                                        </div>
                                                    </div>
                                                @else
                                                    <em class="text-muted">No notes</em>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="thermal-only">
                            @foreach($consultation as $i => $con)
                                <div class="thermal-item">
                                    <div class="thermal-row">
                                        <strong>{{ $con->created_at?->format('d M y') }}</strong>
                                        <span>{{ $con->doctor && $con->doctor->staff_profile ? userfullname($con->doctor->staff_profile->user_id) : 'N/A' }}</span>
                                    </div>
                                    @if($con->notes)
                                        <div class="con-short-content mt-1" style="font-size:11px; word-break:break-word;">
                                            {!! \Illuminate\Support\Str::limit(strip_tags($con->notes), 120) !!}
                                        </div>
                                        <div class="con-full-content d-none mt-1" style="font-size:11px; word-break:break-word;">
                                            {!! \Illuminate\Support\Str::contains($con->notes, '<') ? $con->notes : nl2br(e($con->notes)) !!}
                                        </div>
                                    @else
                                        <div class="mt-1" style="font-size:11px;"><em>No notes</em></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Prescriptions ─────────────────────────────────────── --}}
            @if(isset($prescription) && count($prescription))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-pill mr-2"></i>Prescriptions ({{ count($prescription) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive a4-only">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Date</th><th>Product</th><th>Dose / Sig</th><th>Qty</th><th>Status</th><th>Prescribed By</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($prescription as $i => $pres)
                                        @php
                                            $statusLabels = [0=>'Pending',1=>'Dispensed',2=>'Partially Dispensed',3=>'Cancelled'];
                                            $statusClasses = [0=>'warning',1=>'success',2=>'info',3=>'danger'];
                                            $st = $pres->status ?? 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $pres->created_at?->format('d M Y') }}</td>
                                            <td>{{ $pres->product ? $pres->product->product_name : 'N/A' }}</td>
                                            <td class="small">{{ $pres->dose ?? '—' }} {{ $pres->sig ?? '' }}</td>
                                            <td>{{ $pres->quantity ?? '—' }}</td>
                                            <td><span class="badge badge-{{ $statusClasses[$st] ?? 'secondary' }}">{{ $statusLabels[$st] ?? 'Unknown' }}</span></td>
                                            <td>{{ $pres->doctor_id ? userfullname($pres->doctor_id) : 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="thermal-only">
                            @foreach($prescription as $i => $pres)
                                @php
                                    $statusLabels = [0=>'Pending',1=>'Dispensed',2=>'Partially Dispensed',3=>'Cancelled'];
                                    $st = $pres->status ?? 0;
                                @endphp
                                <div class="thermal-item">
                                    <div class="thermal-row">
                                        <strong>{{ $pres->product ? $pres->product->product_name : 'N/A' }}</strong>
                                        <span>x{{ $pres->quantity ?? '1' }}</span>
                                    </div>
                                    <div class="thermal-row mt-1" style="font-size:11px;">
                                        <span>{{ $pres->dose ?? '' }} {{ $pres->sig ?? '' }}</span>
                                        <span>[{{ $statusLabels[$st] ?? 'Unk' }}]</span>
                                    </div>
                                    <div class="thermal-row mt-1" style="font-size:11px;">
                                        <span>{{ $pres->created_at?->format('d M y') }}</span>
                                        <span>{{ $pres->doctor_id ? userfullname($pres->doctor_id) : '' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Lab Investigations ────────────────────────────────── --}}
            @if(isset($lab) && count($lab))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-flask-outline mr-2"></i>Lab Investigations ({{ count($lab) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive a4-only">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Date</th><th>Investigation</th><th>Result</th><th>Status</th><th>Requested By</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($lab as $i => $la)
                                        @php
                                            $labSt = $la->status ?? 0;
                                            $labLabels  = [0=>'Pending',1=>'Approved',2=>'Resulted',3=>'Verified'];
                                            $labClasses = [0=>'warning',1=>'info',2=>'success',3=>'primary'];
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $la->created_at?->format('d M Y') }}</td>
                                            <td>{{ $la->service ? $la->service->service_name : 'N/A' }}</td>
                                            <td class="small">
                                                @if($la->result)
                                                    <div class="lab-short-content">
                                                        {!! \Illuminate\Support\Str::limit(strip_tags($la->result), 100) !!}
                                                    </div>
                                                    <div class="lab-full-content d-none">
                                                        <div class="p-2 border rounded bg-light mb-1" style="word-break:break-word;">
                                                            {!! \Illuminate\Support\Str::contains($la->result, '<') ? $la->result : nl2br(e($la->result)) !!}
                                                        </div>
                                                    </div>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td><span class="badge badge-{{ $labClasses[$labSt] ?? 'secondary' }}">{{ $labLabels[$labSt] ?? 'N/A' }}</span></td>
                                            <td>{{ userfullname($la->doctor_id) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="thermal-only">
                            @foreach($lab as $i => $la)
                                @php
                                    $labSt = $la->status ?? 0;
                                    $labLabels  = [0=>'Pending',1=>'Approved',2=>'Resulted',3=>'Verified'];
                                @endphp
                                <div class="thermal-item">
                                    <div class="thermal-row">
                                        <strong>{{ $la->service ? $la->service->service_name : 'N/A' }}</strong>
                                        <span>[{{ $labLabels[$labSt] ?? 'N/A' }}]</span>
                                    </div>
                                    @if($la->result)
                                        <div class="lab-short-content mt-1" style="font-size:11px; word-break:break-word;">
                                            <em>Res:</em> {{ \Illuminate\Support\Str::limit(strip_tags($la->result), 120) }}
                                        </div>
                                        <div class="lab-full-content d-none mt-1" style="font-size:11px; word-break:break-word;">
                                            <em>Res:</em> {!! \Illuminate\Support\Str::contains($la->result, '<') ? $la->result : nl2br(e($la->result)) !!}
                                        </div>
                                    @endif
                                    <div class="thermal-row mt-1" style="font-size:11px;">
                                        <span>{{ $la->created_at?->format('d M y') }}</span>
                                        <span>Req: {{ userfullname($la->doctor_id) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Admissions ──────────────────────────────────────────── --}}
            @if(isset($bed) && count($bed))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-bed-outline mr-2"></i>Admissions ({{ count($bed) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive a4-only">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Admission Date</th><th>Discharge Date</th><th>Days</th><th>Ward / Bed</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($bed as $i => $be)
                                        @php
                                            $admit = $be->created_at;
                                            $disch = $be->discharge_date ? \Carbon\Carbon::parse($be->discharge_date) : null;
                                            $days  = $admit && $disch ? $admit->diffInDays($disch) : null;
                                            $bedLabel = $be->bed ? ($be->bed->name ?? 'N/A') : 'N/A';
                                            $ward = $be->bed && $be->bed->ward ? $be->bed->ward->name : 'N/A';
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $admit?->format('d M Y') }}</td>
                                            <td>{{ $disch ? $disch->format('d M Y') : '—' }}</td>
                                            <td>{{ $days !== null ? $days . 'd' : '—' }}</td>
                                            <td>{{ $ward }} / {{ $bedLabel }}</td>
                                            <td>
                                                @if($disch)
                                                    <span class="badge badge-success">Discharged</span>
                                                @else
                                                    <span class="badge badge-warning">Active</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="thermal-only">
                            @foreach($bed as $i => $be)
                                @php
                                    $admit = $be->created_at;
                                    $disch = $be->discharge_date ? \Carbon\Carbon::parse($be->discharge_date) : null;
                                    $bedLabel = $be->bed ? ($be->bed->name ?? 'N/A') : 'N/A';
                                    $ward = $be->bed && $be->bed->ward ? $be->bed->ward->name : 'N/A';
                                @endphp
                                <div class="thermal-item">
                                    <div class="thermal-row">
                                        <strong>{{ $ward }} / {{ $bedLabel }}</strong>
                                        <span>{{ $disch ? 'Discharged' : 'Active' }}</span>
                                    </div>
                                    <div class="thermal-row mt-1" style="font-size:11px;">
                                        <span>In: {{ $admit?->format('d M y') }}</span>
                                        <span>Out: {{ $disch ? $disch->format('d M y') : '—' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Nursing / Misc Services ─────────────────────────────── --}}
            @if(isset($misc) && count($misc))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-clipboard-pulse-outline mr-2"></i>Nursing Services ({{ count($misc) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive a4-only">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light">
                                    <tr><th>#</th><th>Date</th><th>Service</th><th>Category</th><th>Qty</th><th>Amount</th><th>Performed By</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($misc as $i => $mis)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $mis->created_at?->format('d M Y') }}</td>
                                            <td>{{ $mis->service ? $mis->service->service_name : 'N/A' }}</td>
                                            <td>{{ $mis->service && $mis->service->serviceCategory ? $mis->service->serviceCategory->name : '—' }}</td>
                                            <td>{{ $mis->quantity ?? 1 }}</td>
                                            <td>{{ number_format($mis->amount ?? 0, 2) }}</td>
                                            <td>{{ userfullname($mis->created_by) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="thermal-only">
                            @foreach($misc as $i => $mis)
                                <div class="thermal-item">
                                    <div class="thermal-row">
                                        <strong>{{ $mis->service ? $mis->service->service_name : 'N/A' }}</strong>
                                        <span>x{{ $mis->quantity ?? 1 }}</span>
                                    </div>
                                    <div class="thermal-row mt-1" style="font-size:11px;">
                                        <span>{{ $mis->created_at?->format('d M y') }}</span>
                                        <span>{{ userfullname($mis->created_by) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(empty($consultation) && empty($prescription) && empty($lab) && empty($bed) && empty($misc))
                <div class="alert alert-info"><i class="mdi mdi-information-outline mr-2"></i>No services found for the selected date range.</div>
            @endif
        @endif
    </div>
@endsection

@section('scripts')
<script>
    function printThermalServicesRendered() {
        const $container = $('.sr-container');
        if (!$container.length) return;

        const showFullNotes = $('#toggleFullNotes').is(':checked');
        const showFullLabs = $('#toggleFullLabs').is(':checked');

        const $clone = $container.clone();
        $clone.find('.no-print, .filter-bar, .a4-only, .btn').remove();
        $clone.find('#print-header').show().css('display', 'block');
        $clone.find('.thermal-only').show().css('display', 'block');

        // Apply Full Notes / Full Labs visibility to the cloned thermal document
        if (showFullNotes) {
            $clone.find('.con-short-content').remove();
            $clone.find('.con-full-content').removeClass('d-none').show();
        } else {
            $clone.find('.con-full-content').remove();
            $clone.find('.con-short-content').removeClass('d-none').show();
        }

        if (showFullLabs) {
            $clone.find('.lab-short-content').remove();
            $clone.find('.lab-full-content').removeClass('d-none').show();
        } else {
            $clone.find('.lab-full-content').remove();
            $clone.find('.lab-short-content').removeClass('d-none').show();
        }

        const printWindow = window.open('', '_blank', 'height=700,width=450');
        if (!printWindow) {
            $('body').addClass('thermal-mode');
            window.print();
            $(window).one('afterprint', function () {
                $('body').removeClass('thermal-mode');
            });
            return;
        }

        const thermalContent = $clone.html();
        const doc = `<!DOCTYPE html>
<html>
<head>
    <title>Services Rendered (Thermal)</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { margin: 0; size: auto; }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background: #fff !important;
            font-family: 'Consolas', 'Liberation Mono', monospace, sans-serif;
            font-size: 11px;
            color: #000;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .sr-thermal-print {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 2mm 3mm 4mm !important;
            box-sizing: border-box !important;
            font-size: 11px;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        #print-header { text-align: center; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px dashed #000; word-break: break-word; overflow-wrap: break-word; }
        #print-header .hos-logo { max-height: 48px; max-width: 60px; margin-bottom: 2px; }
        #print-header h4 { font-size: 13px; font-weight: 700; text-transform: uppercase; margin: 0 0 2px; color: #000; word-break: break-word; overflow-wrap: break-word; }
        #print-header small, #print-header div { font-size: 11px !important; color: #000; display: block; line-height: 1.4; word-break: break-word; overflow-wrap: break-word; white-space: normal; }
        .service-section { margin-bottom: 6px; }
        .section-header {
            background: transparent;
            color: #000;
            border-bottom: 1px solid #000;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 3px 0;
            margin: 4px 0 2px;
            text-align: center;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .card-modern { border: none; box-shadow: none; padding: 0; margin: 0; background: transparent; }
        .thermal-item {
            border-bottom: 1px dashed #aaa;
            padding: 3px 0;
            margin-bottom: 2px;
            font-size: 11px;
            line-height: 1.35;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .thermal-item:last-child { border-bottom: none; }
        .thermal-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 4px;
            font-size: 11px;
            line-height: 1.35;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .thermal-row > *:first-child {
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .thermal-row > *:last-child:not(:first-child) {
            flex-shrink: 0;
            text-align: right;
            max-width: 48%;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .con-full-content, .lab-full-content {
            font-size: 11px !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            white-space: normal !important;
        }
        .con-full-content *, .lab-full-content * {
            font-size: 11px !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
        }
        .d-none { display: none !important; }
        .thermal-item, .thermal-item *, .thermal-row, .thermal-row * {
            font-size: 11px !important;
        }
        @media print {
            body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .sr-thermal-print { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 2mm 3mm 4mm !important; }
            .thermal-item, .thermal-item *, .thermal-row, .thermal-row * { font-size: 11px !important; }
        }
    </style>
</head>
<body>
    <div class="sr-thermal-print">
        ${thermalContent}
    </div>
</body>
</html>`;

        printWindow.document.open();
        printWindow.document.write(doc);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
            setTimeout(function() { printWindow.close(); }, 500);
        }, 250);
    }

    $(function () {
        $('#btnPrintA4').on('click', function () {
            $('body').removeClass('thermal-mode');
            window.print();
        });
        $('#btnPrintThermal').on('click', function () {
            printThermalServicesRendered();
        });

        function syncFullContentToggles() {
            const showFullNotes = $('#toggleFullNotes').is(':checked');
            const showFullLabs = $('#toggleFullLabs').is(':checked');

            if (showFullNotes) {
                $('.con-short-content').addClass('d-none');
                $('.con-full-content').removeClass('d-none');
            } else {
                $('.con-full-content').addClass('d-none');
                $('.con-short-content').removeClass('d-none');
            }

            if (showFullLabs) {
                $('.lab-short-content').addClass('d-none');
                $('.lab-full-content').removeClass('d-none');
            } else {
                $('.lab-full-content').addClass('d-none');
                $('.lab-short-content').removeClass('d-none');
            }
        }

        $('#toggleFullNotes, #toggleFullLabs').on('change', syncFullContentToggles);
        syncFullContentToggles();
    });
</script>
@endsection
