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

        /* ── A4 print ─────────────────────────────────────────────── */
        @media print {
            .no-print, .filter-bar { display:none !important; }
            body { background:#fff !important; }
            .card-modern { box-shadow:none !important; border:1px solid #ccc !important; }
            #print-header { display:block !important; }
            /* thermal overrides – applied via body class */
        }
        body.thermal-mode #print-header { display:block; }
        body.thermal-mode .sr-container { max-width:320px !important; margin:0 auto; font-size:11px; }
        body.thermal-mode .table th, body.thermal-mode .table td { padding:2px 4px !important; font-size:11px; }
        body.thermal-mode #print-header .hos-logo { max-width:64px; }
        body.thermal-mode #print-header h4 { font-size:13px; }
        body.thermal-mode #print-header small { font-size:10px; }
        @media print {
            body.thermal-mode * { max-width:320px; }
        }
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
                        <div class="table-responsive">
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
                                            <td class="small">{!! $con->notes ? \Illuminate\Support\Str::limit(strip_tags($con->notes), 150) : '<em class="text-muted">No notes</em>' !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Prescriptions ─────────────────────────────────────── --}}
            @if(isset($prescription) && count($prescription))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-pill mr-2"></i>Prescriptions ({{ count($prescription) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive">
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
                    </div>
                </div>
            @endif

            {{-- ── Lab Investigations ────────────────────────────────── --}}
            @if(isset($lab) && count($lab))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-flask-outline mr-2"></i>Lab Investigations ({{ count($lab) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive">
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
                                            <td class="small">{{ $la->result ? \Illuminate\Support\Str::limit(strip_tags($la->result), 100) : '—' }}</td>
                                            <td><span class="badge badge-{{ $labClasses[$labSt] ?? 'secondary' }}">{{ $labLabels[$labSt] ?? 'N/A' }}</span></td>
                                            <td>{{ userfullname($la->doctor_id) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Admissions ──────────────────────────────────────────── --}}
            @if(isset($bed) && count($bed))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-bed-outline mr-2"></i>Admissions ({{ count($bed) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive">
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
                    </div>
                </div>
            @endif

            {{-- ── Nursing / Misc Services ─────────────────────────────── --}}
            @if(isset($misc) && count($misc))
                <div class="service-section">
                    <div class="section-header"><i class="mdi mdi-clipboard-pulse-outline mr-2"></i>Nursing Services ({{ count($misc) }})</div>
                    <div class="card-modern" style="border-radius:0 0 6px 6px;">
                        <div class="table-responsive">
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
    $(function () {
        $('#btnPrintA4').on('click', function () {
            $('body').removeClass('thermal-mode');
            window.print();
        });
        $('#btnPrintThermal').on('click', function () {
            $('body').addClass('thermal-mode');
            window.print();
            // Remove class after printing so page returns to normal
            $(window).one('afterprint', function () {
                $('body').removeClass('thermal-mode');
            });
        });
    });
</script>
@endsection
