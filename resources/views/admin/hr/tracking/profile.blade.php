@extends('admin.layouts.app')
@section('title', 'Staff Tracking Profile')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Tracking Profile')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .hr-table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; background: #f9fafb; }
        .hr-table td { vertical-align: middle !important; }
        .profile-header { background: linear-gradient(135deg, var(--primary-color), {{ $primaryColor }}cc); border-radius: 12px; color: #fff; padding: 1.5rem; margin-bottom: 1rem; }
        .profile-header .staff-name { font-size: 1.4rem; font-weight: 700; margin-bottom: 0.25rem; }
        .profile-header .staff-meta { opacity: 0.85; font-size: 0.88rem; }
        .profile-header .staff-meta span { margin-right: 1.2rem; }
        .tracking-tabs .nav-link { font-weight: 600; font-size: 0.85rem; color: #6b7280; border: none; padding: 0.6rem 1rem; }
        .tracking-tabs .nav-link.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); background: transparent; }
        .tracking-tabs .nav-link .badge { font-size: 0.7rem; }
        .tab-section-empty { text-align: center; color: #9ca3af; padding: 2rem; font-size: 0.9rem; }
        .key-dates-bar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .key-date-chip { display: inline-flex; align-items: center; padding: 0.35rem 0.7rem; border-radius: 20px; font-size: 0.78rem; font-weight: 500; }
        .key-date-chip i { margin-right: 4px; }
        .key-date-chip.alert-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .key-date-chip.alert-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .key-date-chip.alert-info { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .key-date-chip.alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .bio-label { font-weight: 600; color: #6b7280; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .bio-value { font-size: 0.92rem; color: #1f2937; margin-bottom: 0.75rem; }
        .bio-section-title { font-weight: 700; font-size: 0.9rem; color: var(--primary-color); border-bottom: 2px solid var(--primary-light); padding-bottom: 0.4rem; margin-bottom: 1rem; margin-top: 1rem; }
        .summary-card { border-radius: 10px; border: 1px solid #e5e7eb; padding: 1rem; text-align: center; transition: transform 0.15s; }
        .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .summary-card .sc-value { font-size: 1.5rem; font-weight: 700; }
        .summary-card .sc-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
        .timeline-item { position: relative; padding-left: 2rem; padding-bottom: 1rem; border-left: 2px solid #e5e7eb; }
        .timeline-item:last-child { border-left: 2px solid transparent; }
        .timeline-item::before { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--primary-color); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--primary-color); }
        .timeline-item.entry::before { background: #6b7280; box-shadow: 0 0 0 2px #6b7280; }
        @media print {
            .no-print { display: none !important; }
            .profile-header { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .tab-pane { display: block !important; opacity: 1 !important; }
            .nav-tabs { display: none !important; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    {{-- Top bar --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
            <i class="mdi mdi-arrow-left mr-1"></i> Back
        </a>
        <div class="d-flex" style="gap:0.5rem;">
            <a href="{{ route('staff.edit', $staff->user_id) }}" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                <i class="mdi mdi-pencil mr-1"></i> Edit Staff
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm" style="border-radius: 8px;">
                <i class="mdi mdi-printer mr-1"></i> Print Profile
            </button>
        </div>
    </div>

    {{-- Profile Header --}}
    <div class="profile-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <div class="mr-3">
                    <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;">
                        {{ strtoupper(substr($staff->user?->surname ?? '?', 0, 1)) }}{{ strtoupper(substr($staff->user?->firstname ?? '', 0, 1)) }}
                    </div>
                </div>
                <div>
                    <div class="staff-name">{{ $staff->user?->surname }} {{ $staff->user?->firstname }} {{ $staff->user?->othername }}</div>
                    <div class="staff-meta">
                        @if($staff->employee_id)<span><i class="mdi mdi-identifier mr-1"></i>{{ $staff->employee_id }}</span>@endif
                        <span><i class="mdi mdi-domain mr-1"></i>{{ $staff->department?->name ?? '—' }}</span>
                        <span><i class="mdi mdi-briefcase mr-1"></i>{{ $staff->cadre?->name ?? '—' }} {{ $staff->job_title ? '· '.$staff->job_title : '' }}</span>
                        <span><i class="mdi mdi-stairs mr-1"></i>{{ $staff->gradeLevel?->name ?? '—' }}</span>
                        @if($staff->unit)<span><i class="mdi mdi-account-group mr-1"></i>{{ $staff->unit->name }}</span>@endif
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-md-0">
                @if($staff->employment_status)
                    @php $empColors = ['active'=>'success','suspended'=>'warning','resigned'=>'secondary','terminated'=>'danger','retired'=>'dark']; @endphp
                    <span class="badge badge-{{ $empColors[$staff->employment_status] ?? 'secondary' }}" style="font-size:0.8rem;padding:0.4rem 0.8rem;border-radius:20px;">
                        {{ ucfirst($staff->employment_status) }}
                    </span>
                @endif
                @if($staff->date_hired)
                    <span style="opacity:0.8;font-size:0.82rem;margin-left:0.5rem;">
                        <i class="mdi mdi-calendar mr-1"></i>{{ \Carbon\Carbon::parse($staff->date_hired)->diffForHumans(null, true) }} of service
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Key Dates Alert Bar --}}
    @php
        $now = \Carbon\Carbon::now();
        $alerts = [];
        if ($staff->next_promotion_due_date && \Carbon\Carbon::parse($staff->next_promotion_due_date)->lte($now))
            $alerts[] = ['Promotion overdue: ' . \Carbon\Carbon::parse($staff->next_promotion_due_date)->format('d M Y'), 'alert-danger', 'mdi-arrow-up-bold-circle'];
        elseif ($staff->next_promotion_due_date && \Carbon\Carbon::parse($staff->next_promotion_due_date)->lte($now->copy()->addMonths(3)))
            $alerts[] = ['Promotion due: ' . \Carbon\Carbon::parse($staff->next_promotion_due_date)->format('d M Y'), 'alert-warning', 'mdi-arrow-up-bold-circle'];

        if ($staff->license_expiry_date && \Carbon\Carbon::parse($staff->license_expiry_date)->lte($now))
            $alerts[] = ['License expired: ' . \Carbon\Carbon::parse($staff->license_expiry_date)->format('d M Y'), 'alert-danger', 'mdi-card-account-details'];
        elseif ($staff->license_expiry_date && \Carbon\Carbon::parse($staff->license_expiry_date)->lte($now->copy()->addMonths(3)))
            $alerts[] = ['License expiring: ' . \Carbon\Carbon::parse($staff->license_expiry_date)->format('d M Y'), 'alert-warning', 'mdi-card-account-details'];

        if ($staff->confirmation_due_date && !$staff->date_confirmed && \Carbon\Carbon::parse($staff->confirmation_due_date)->lte($now))
            $alerts[] = ['Confirmation overdue: ' . \Carbon\Carbon::parse($staff->confirmation_due_date)->format('d M Y'), 'alert-danger', 'mdi-account-check'];

        if ($staff->next_medical_exam_due && \Carbon\Carbon::parse($staff->next_medical_exam_due)->lte($now))
            $alerts[] = ['Medical exam overdue: ' . \Carbon\Carbon::parse($staff->next_medical_exam_due)->format('d M Y'), 'alert-danger', 'mdi-stethoscope'];
        elseif ($staff->next_medical_exam_due && \Carbon\Carbon::parse($staff->next_medical_exam_due)->lte($now->copy()->addMonths(3)))
            $alerts[] = ['Medical exam due: ' . \Carbon\Carbon::parse($staff->next_medical_exam_due)->format('d M Y'), 'alert-warning', 'mdi-stethoscope'];

        if ($staff->retirement_date && \Carbon\Carbon::parse($staff->retirement_date)->lte($now->copy()->addYear()))
            $alerts[] = ['Retiring: ' . \Carbon\Carbon::parse($staff->retirement_date)->format('d M Y'), 'alert-info', 'mdi-account-clock'];

        if ($staff->date_confirmed)
            $alerts[] = ['Confirmed: ' . \Carbon\Carbon::parse($staff->date_confirmed)->format('d M Y'), 'alert-success', 'mdi-account-check'];
    @endphp
    @if(count($alerts))
    <div class="key-dates-bar">
        @foreach($alerts as $a)
            <span class="key-date-chip {{ $a[1] }}"><i class="mdi {{ $a[2] }}"></i> {{ $a[0] }}</span>
        @endforeach
    </div>
    @endif

    {{-- Summary Overview Cards --}}
    <div class="row mb-3">
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                <div class="sc-value text-primary">{{ $staff->date_hired ? round(\Carbon\Carbon::parse($staff->date_hired)->diffInYears($now), 1) : '—' }}</div>
                <div class="sc-label">Years of Service</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                <div class="sc-value text-success">{{ $staff->currentSalaryProfile?->gross_salary ? '₦' . number_format($staff->currentSalaryProfile->gross_salary, 0) : '—' }}</div>
                <div class="sc-label">Monthly Salary</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                <div class="sc-value text-info">{{ $staff->qualifications->count() }}</div>
                <div class="sc-label">Qualifications</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                <div class="sc-value" style="color:#7c3aed;">{{ $staff->promotions->count() }}</div>
                <div class="sc-label">Promotions</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                <div class="sc-value" style="color:#059669;">{{ $staff->trainings->where('status', 'completed')->count() }}/{{ $staff->trainings->count() }}</div>
                <div class="sc-label">Trainings Done</div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-2">
            <div class="summary-card">
                @php $openFu = $staff->followUps->where('status', '!=', 'resolved')->count(); @endphp
                <div class="sc-value {{ $openFu> 0 ? 'text-danger' : 'text-muted' }}">{{ $openFu }}</div>
                <div class="sc-label">Open Follow-ups</div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-body" style="padding: 0;">
            <ul class="nav nav-tabs tracking-tabs px-3 pt-2 no-print" id="trackingTabs" role="tablist" style="overflow-x:auto;flex-wrap:nowrap;">
                <li class="nav-item">
                    <a class="nav-link active" id="biodata-tab" data-bs-toggle="tab" href="#biodata" role="tab">
                        <i class="mdi mdi-account-details mr-1"></i> Bio Data
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="promotions-tab" data-bs-toggle="tab" href="#promotions" role="tab">
                        <i class="mdi mdi-arrow-up-bold-circle mr-1"></i> Promotions <span class="badge badge-primary ml-1">{{ $staff->promotions->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="qualifications-tab" data-bs-toggle="tab" href="#qualifications" role="tab">
                        <i class="mdi mdi-school mr-1"></i> Qualifications <span class="badge badge-info ml-1">{{ $staff->qualifications->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="trainings-tab" data-bs-toggle="tab" href="#trainings" role="tab">
                        <i class="mdi mdi-certificate mr-1"></i> Trainings <span class="badge badge-success ml-1">{{ $staff->trainings->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="exams-tab" data-bs-toggle="tab" href="#exams" role="tab">
                        <i class="mdi mdi-stethoscope mr-1"></i> Medical Exams <span class="badge badge-warning ml-1">{{ $staff->medicalExams->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="followups-tab" data-bs-toggle="tab" href="#followups" role="tab">
                        <i class="mdi mdi-clipboard-check-outline mr-1"></i> Follow-ups <span class="badge badge-danger ml-1">{{ $staff->followUps->where('status', '!=', 'resolved')->count() }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="salary-tab" data-bs-toggle="tab" href="#salary" role="tab">
                        <i class="mdi mdi-cash-multiple mr-1"></i> Salary
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="leave-disc-tab" data-bs-toggle="tab" href="#leave-disc" role="tab">
                        <i class="mdi mdi-calendar-account mr-1"></i> Leave & Disciplinary
                    </a>
                </li>
            </ul>

            {{-- Quick Actions --}}
            <div class="d-flex align-items-center px-3 pt-2 pb-0 no-print" style="gap:0.5rem;flex-wrap:wrap;">
                <small class="text-muted mr-2"><i class="mdi mdi-lightning-bolt"></i> Quick:</small>
                <a href="{{ route('hr.promotions.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-primary btn-xs" style="border-radius:6px;font-size:0.75rem;padding:2px 8px;">+ Promotion</a>
                <a href="{{ route('hr.qualifications.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-info btn-xs" style="border-radius:6px;font-size:0.75rem;padding:2px 8px;">+ Qualification</a>
                <a href="{{ route('hr.trainings.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-success btn-xs" style="border-radius:6px;font-size:0.75rem;padding:2px 8px;">+ Training</a>
                <a href="{{ route('hr.medical-exams.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-warning btn-xs" style="border-radius:6px;font-size:0.75rem;padding:2px 8px;">+ Medical Exam</a>
                <a href="{{ route('hr.follow-ups.index', ['staff_id' => $staff->id]) }}" class="btn btn-outline-danger btn-xs" style="border-radius:6px;font-size:0.75rem;padding:2px 8px;">+ Follow-up</a>
            </div>

            <div class="tab-content p-3">
                {{-- ========== BIO DATA TAB ========== --}}
                <div class="tab-pane fade show active" id="biodata" role="tabpanel">
                    <div class="row">
                        {{-- Col 1: Personal --}}
                        <div class="col-md-4">
                            <div class="bio-section-title"><i class="mdi mdi-account mr-1"></i> Personal Information</div>
                            <div class="bio-label">Full Name</div>
                            <div class="bio-value">{{ $staff->user?->surname }} {{ $staff->user?->firstname }} {{ $staff->user?->othername }}</div>
                            <div class="bio-label">Gender</div>
                            <div class="bio-value">{{ ucfirst($staff->gender ?? '—') }}</div>
                            <div class="bio-label">Date of Birth</div>
                            <div class="bio-value">{{ $staff->date_of_birth ? \Carbon\Carbon::parse($staff->date_of_birth)->format('d M Y') . ' (Age: ' . \Carbon\Carbon::parse($staff->date_of_birth)->age . ')' : '—' }}</div>
                            <div class="bio-label">Marital Status</div>
                            <div class="bio-value">{{ ucfirst($staff->marital_status ?? '—') }}</div>
                            <div class="bio-label">No. of Children</div>
                            <div class="bio-value">{{ $staff->number_of_children ?? '—' }}</div>
                            <div class="bio-label">Other Talents</div>
                            <div class="bio-value">{{ $staff->other_talents ?? '—' }}</div>
                        </div>

                        {{-- Col 2: Contact & NOK --}}
                        <div class="col-md-4">
                            <div class="bio-section-title"><i class="mdi mdi-map-marker mr-1"></i> Contact & Address</div>
                            <div class="bio-label">Phone</div>
                            <div class="bio-value">{{ $staff->phone_number ?? '—' }}</div>
                            <div class="bio-label">Email</div>
                            <div class="bio-value">{{ $staff->user?->email ?? '—' }}</div>
                            <div class="bio-label">Residential Address</div>
                            <div class="bio-value">{{ $staff->home_address ?? '—' }}</div>
                            <div class="bio-label">Permanent Home Address</div>
                            <div class="bio-value">{{ $staff->permanent_home_address ?? '—' }}</div>

                            <div class="bio-section-title"><i class="mdi mdi-account-heart mr-1"></i> Next of Kin</div>
                            @if($staff->nextOfKin && $staff->nextOfKin->count())
                                @foreach($staff->nextOfKin as $nok)
                                <div class="mb-2 p-2" style="background:#f9fafb;border-radius:8px;">
                                    <strong>{{ $nok->full_name }}</strong> ({{ $nok->relationship }})
                                    @if($nok->is_primary) <span class="badge badge-primary ml-1">Primary</span> @endif
                                    <br><small class="text-muted">{{ $nok->phone ?? '' }} {{ $nok->email ? '· '.$nok->email : '' }}</small>
                                    @if($nok->address)<br><small class="text-muted">{{ $nok->address }}</small>@endif
                                </div>
                                @endforeach
                            @else
                                <div class="bio-value text-muted">No next of kin recorded</div>
                            @endif
                        </div>

                        {{-- Col 3: Employment + IDs + Key Dates --}}
                        <div class="col-md-4">
                            <div class="bio-section-title"><i class="mdi mdi-briefcase mr-1"></i> Employment Details</div>
                            <div class="bio-label">Employee Number</div>
                            <div class="bio-value">{{ $staff->employee_id ?? '—' }}</div>
                            <div class="bio-label">Date of Hire</div>
                            <div class="bio-value">{{ $staff->date_hired ? \Carbon\Carbon::parse($staff->date_hired)->format('d M Y') : '—' }}
                                @if($staff->date_hired) <small class="text-muted">({{ \Carbon\Carbon::parse($staff->date_hired)->diffForHumans(null, true) }})</small> @endif
                            </div>
                            <div class="bio-label">Employment Type / Status</div>
                            <div class="bio-value">{{ ucfirst(str_replace('_', ' ', $staff->employment_type ?? '—')) }} · {{ ucfirst($staff->employment_status ?? '—') }}</div>
                            <div class="bio-label">Job Location</div>
                            <div class="bio-value">{{ $staff->job_location ?? '—' }}</div>
                            <div class="bio-label">Responsibility</div>
                            <div class="bio-value">{{ $staff->responsibility ?? '—' }}</div>

                            <div class="bio-section-title"><i class="mdi mdi-stairs mr-1"></i> Grade Progression</div>
                            <div class="d-flex align-items-center mb-2">
                                <div class="p-2 text-center" style="background:#f3f4f6;border-radius:8px;flex:1;">
                                    <div style="font-size:0.7rem;color:#6b7280;">ENTRY LEVEL</div>
                                    <div class="font-weight-bold">{{ $staff->entryGradeLevel?->name ?? '—' }}</div>
                                </div>
                                <div class="mx-2 text-success"><i class="mdi mdi-arrow-right-bold" style="font-size:1.5rem;"></i></div>
                                <div class="p-2 text-center" style="background:var(--primary-light);border-radius:8px;flex:1;border:1px solid var(--primary-color);">
                                    <div style="font-size:0.7rem;color:#6b7280;">CURRENT LEVEL</div>
                                    <div class="font-weight-bold" style="color:var(--primary-color);">{{ $staff->gradeLevel?->name ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="bio-section-title"><i class="mdi mdi-card-account-details mr-1"></i> Licensing & IDs</div>
                            <div class="bio-label">MDCN / License Number</div>
                            <div class="bio-value">{{ $staff->license_number ?? '—' }}</div>
                            <div class="bio-label">License Due Date</div>
                            <div class="bio-value">
                                @if($staff->license_expiry_date)
                                    {{ \Carbon\Carbon::parse($staff->license_expiry_date)->format('d M Y') }}
                                    @if(\Carbon\Carbon::parse($staff->license_expiry_date)->isPast()) <span class="badge badge-danger">Expired</span> @endif
                                @else — @endif
                            </div>
                            <div class="bio-label">National Identity Number</div>
                            <div class="bio-value">{{ $staff->national_id_number ?? '—' }}</div>

                            <div class="bio-section-title"><i class="mdi mdi-calendar-clock mr-1"></i> Key Dates</div>
                            <div class="bio-label">Date Confirmed</div>
                            <div class="bio-value">{{ $staff->date_confirmed ? \Carbon\Carbon::parse($staff->date_confirmed)->format('d M Y') : ($staff->confirmation_due_date ? 'Due: '.\Carbon\Carbon::parse($staff->confirmation_due_date)->format('d M Y') : '—') }}</div>
                            <div class="bio-label">Last Promotion</div>
                            <div class="bio-value">{{ $staff->last_promotion_date ? \Carbon\Carbon::parse($staff->last_promotion_date)->format('d M Y') : '—' }}</div>
                            <div class="bio-label">Next Promotion Due</div>
                            <div class="bio-value">{{ $staff->next_promotion_due_date ? \Carbon\Carbon::parse($staff->next_promotion_due_date)->format('d M Y') : '—' }}</div>
                            <div class="bio-label">Expected Exit (Age)</div>
                            <div class="bio-value">{{ $staff->retirement_date ? \Carbon\Carbon::parse($staff->retirement_date)->format('d M Y') : '—' }}</div>
                            <div class="bio-label">Expected Exit (Service)</div>
                            <div class="bio-value">{{ $staff->max_service_date ? \Carbon\Carbon::parse($staff->max_service_date)->format('d M Y') : '—' }}</div>
                        </div>
                    </div>
                </div>

                {{-- ========== PROMOTIONS TAB ========== --}}
                <div class="tab-pane fade" id="promotions" role="tabpanel">
                    @if($staff->promotions->count())
                    {{-- Career Timeline --}}
                    <div class="mb-4">
                        <h6 class="font-weight-bold mb-3" style="color:var(--primary-color);"><i class="mdi mdi-timeline mr-1"></i> Career Progression Timeline</h6>
                        <div style="padding-left: 0.5rem;">
                            <div class="timeline-item entry">
                                <div style="font-size:0.75rem;color:#6b7280;">{{ $staff->date_hired ? $staff->date_hired->format('d M Y') : 'Start' }}</div>
                                <div class="font-weight-bold">Hired at {{ $staff->entryGradeLevel?->name ?? 'Entry Level' }}</div>
                                @if($staff->job_title)<div style="font-size:0.82rem;color:#6b7280;">{{ $staff->job_title }}</div>@endif
                            </div>
                            @foreach($staff->promotions->sortBy('promotion_date') as $p)
                            <div class="timeline-item">
                                <div style="font-size:0.75rem;color:#6b7280;">{{ $p->promotion_date?->format('d M Y') }}</div>
                                <div class="font-weight-bold">
                                    {{ $p->fromGradeLevel?->name ?? '—' }} <i class="mdi mdi-arrow-right text-success"></i> {{ $p->toGradeLevel?->name ?? '—' }}
                                </div>
                                @if($p->to_job_title)<div style="font-size:0.82rem;color:#6b7280;">{{ $p->to_job_title }}</div>@endif
                                @if($p->authority)<div style="font-size:0.75rem;color:#9ca3af;">Authority: {{ $p->authority }}</div>@endif
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Detailed Table --}}
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr><th>Date</th><th>Grade Change</th><th>New Title</th><th>Authority</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                @foreach($staff->promotions as $p)
                                <tr>
                                    <td>{{ $p->promotion_date?->format('d M Y') }}<br><small class="text-muted">Effective: {{ $p->effective_date?->format('d M Y') ?? '—' }}</small></td>
                                    <td>{{ $p->fromGradeLevel?->name ?? '—' }} <i class="mdi mdi-arrow-right text-success"></i> <span class="badge badge-success">{{ $p->toGradeLevel?->name ?? '—' }}</span></td>
                                    <td>{{ $p->to_job_title ?? '—' }}</td>
                                    <td>{{ $p->authority ?? '—' }}</td>
                                    <td>{{ $p->remarks ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($staff->next_promotion_due_date)
                        <div class="mt-2">
                            <small class="text-muted"><i class="mdi mdi-calendar-clock mr-1"></i>Next promotion due: <strong>{{ \Carbon\Carbon::parse($staff->next_promotion_due_date)->format('d M Y') }}</strong>
                            @if(\Carbon\Carbon::parse($staff->next_promotion_due_date)->isPast()) <span class="badge badge-danger ml-1">Overdue</span> @endif
                            </small>
                        </div>
                    @endif
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No promotions recorded</div>
                    @endif
                </div>

                {{-- ========== QUALIFICATIONS TAB ========== --}}
                <div class="tab-pane fade" id="qualifications" role="tabpanel">
                    @if($staff->qualifications->count())
                    {{-- Entry vs Additional summary --}}
                    <div class="d-flex align-items-center mb-3" style="gap:1rem;">
                        <span class="badge badge-primary" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Entry: {{ $staff->qualifications->where('type', 'entry')->count() }}</span>
                        <span class="badge badge-info" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Additional: {{ $staff->qualifications->where('type', 'additional')->count() }}</span>
                        <span class="badge badge-success" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Verified: {{ $staff->qualifications->where('result_seen', true)->count() }}</span>
                        <span class="badge badge-warning" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Pending: {{ $staff->qualifications->where('result_seen', false)->count() }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr><th>Qualification</th><th>Field</th><th>Institution</th><th>Year</th><th>Date Obtained</th><th>Type</th><th>Verified</th></tr>
                            </thead>
                            <tbody>
                                @foreach($staff->qualifications as $q)
                                <tr>
                                    <td>{{ $q->qualification_name }}@if($q->document_path) <i class="mdi mdi-paperclip text-info" title="Document attached"></i>@endif</td>
                                    <td>{{ $q->field_of_study ?? '—' }}</td>
                                    <td>{{ $q->institution ?? '—' }}</td>
                                    <td>{{ $q->year_of_graduation ?? '—' }}</td>
                                    <td>{{ $q->date_obtained?->format('d M Y') ?? '—' }}</td>
                                    <td><span class="badge badge-{{ $q->type == 'entry' ? 'primary' : 'info' }}">{{ ucfirst($q->type) }}</span></td>
                                    <td>
                                        @if($q->result_seen)
                                            <span class="badge badge-success"><i class="mdi mdi-check-circle"></i> Verified</span>
                                        @else
                                            <span class="badge badge-warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No qualifications recorded</div>
                    @endif
                </div>

                {{-- ========== TRAININGS TAB ========== --}}
                <div class="tab-pane fade" id="trainings" role="tabpanel">
                    @if($staff->trainings->count())
                    {{-- Training Gap Analysis --}}
                    @php
                        $identified = $staff->trainings->where('type', 'identified');
                        $careerPlan = $staff->trainings->where('type', 'career_plan');
                        $attended = $staff->trainings->where('type', 'attended');
                        $completedCount = $staff->trainings->where('status', 'completed')->count();
                        $pendingCount = $staff->trainings->where('status', '!=', 'completed')->where('status', '!=', 'cancelled')->count();
                    @endphp
                    <div class="d-flex align-items-center mb-3" style="gap:1rem;flex-wrap:wrap;">
                        <span class="badge badge-success" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Attended: {{ $attended->count() }}</span>
                        <span class="badge badge-warning" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Identified: {{ $identified->count() }}</span>
                        <span class="badge badge-info" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Career Plan: {{ $careerPlan->count() }}</span>
                        <span class="badge badge-secondary" style="font-size:0.82rem;padding:0.4rem 0.8rem;">Completed: {{ $completedCount }}/{{ $staff->trainings->count() }}</span>
                        @if($pendingCount> 0)
                        <span class="badge badge-danger" style="font-size:0.82rem;padding:0.4rem 0.8rem;">{{ $pendingCount }} Pending</span>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr><th>Title</th><th>Type</th><th>Institution</th><th>Dates</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                @php $typeColors = ['attended'=>'success','identified'=>'warning','career_plan'=>'info']; $statusColors = ['planned'=>'secondary','in_progress'=>'warning','completed'=>'success','cancelled'=>'danger']; @endphp
                                @foreach($staff->trainings as $t)
                                <tr>
                                    <td>{{ $t->title }}@if($t->certificate_path) <i class="mdi mdi-certificate text-success" title="Certificate attached"></i>@endif</td>
                                    <td><span class="badge badge-{{ $typeColors[$t->type] ?? 'secondary' }}">{{ str_replace('_', ' ', ucfirst($t->type)) }}</span></td>
                                    <td>{{ $t->institution ?? '—' }}</td>
                                    <td>{{ $t->start_date?->format('d M Y') ?? '—' }}@if($t->end_date) – {{ $t->end_date->format('d M Y') }}@endif</td>
                                    <td><span class="badge badge-{{ $statusColors[$t->status] ?? 'secondary' }}">{{ str_replace('_', ' ', ucfirst($t->status)) }}</span></td>
                                    <td>@if($t->notes)<i class="mdi mdi-comment-text-outline text-muted" title="{{ $t->notes }}"></i>@endif</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No training records</div>
                    @endif
                </div>

                {{-- ========== MEDICAL EXAMS TAB ========== --}}
                <div class="tab-pane fade" id="exams" role="tabpanel">
                    @if($staff->medicalExams->count())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr><th>Date</th><th>Type</th><th>Result</th><th>Conducted By</th><th>Next Due</th><th></th></tr>
                            </thead>
                            <tbody>
                                @php $resultColors = ['fit'=>'success','unfit'=>'danger','conditional'=>'warning']; @endphp
                                @foreach($staff->medicalExams as $exam)
                                <tr>
                                    <td>{{ $exam->exam_date?->format('d M Y') }}</td>
                                    <td>@php $typeLabels = ['pre_employment'=>'Pre-Employment','periodic'=>'Periodic','exit'=>'Exit']; @endphp<span class="badge badge-info">{{ $typeLabels[$exam->exam_type] ?? ucfirst($exam->exam_type) }}</span></td>
                                    <td><span class="badge badge-{{ $resultColors[$exam->result] ?? 'secondary' }}">{{ ucfirst($exam->result) }}</span></td>
                                    <td>{{ $exam->conducted_by ?? '—' }}</td>
                                    <td>
                                        @if($exam->next_exam_due)
                                            @if($exam->next_exam_due->isPast())
                                                <span class="text-danger font-weight-bold">{{ $exam->next_exam_due->format('d M Y') }} <i class="mdi mdi-alert"></i></span>
                                            @else
                                                {{ $exam->next_exam_due->format('d M Y') }}
                                            @endif
                                        @else — @endif
                                    </td>
                                    <td>@if($exam->document_path)<i class="mdi mdi-paperclip text-info" title="Document attached"></i>@endif</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($staff->next_medical_exam_due)
                    <div class="mt-2">
                        <small class="text-muted"><i class="mdi mdi-calendar-clock mr-1"></i>Next medical exam due: <strong>{{ \Carbon\Carbon::parse($staff->next_medical_exam_due)->format('d M Y') }}</strong>
                        @if(\Carbon\Carbon::parse($staff->next_medical_exam_due)->isPast()) <span class="badge badge-danger ml-1">Overdue</span> @endif
                        </small>
                    </div>
                    @endif
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No medical exams recorded</div>
                    @endif
                </div>

                {{-- ========== FOLLOW-UPS TAB ========== --}}
                <div class="tab-pane fade" id="followups" role="tabpanel">
                    @if($staff->followUps->count())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr><th>Subject</th><th>Priority</th><th>Due Date</th><th>Status</th><th>Created By</th></tr>
                            </thead>
                            <tbody>
                                @php $prioColors = ['low'=>'secondary','medium'=>'warning','high'=>'danger']; $statusColors2 = ['open'=>'warning','in_progress'=>'info','resolved'=>'success']; @endphp
                                @foreach($staff->followUps as $fu)
                                <tr>
                                    <td>{{ $fu->subject }}@if($fu->details)<br><small class="text-muted">{{ Str::limit($fu->details, 80) }}</small>@endif</td>
                                    <td><span class="badge badge-{{ $prioColors[$fu->priority] ?? 'secondary' }}">{{ ucfirst($fu->priority) }}</span></td>
                                    <td>
                                        @if($fu->due_date)
                                            @if($fu->due_date->isPast() && $fu->status !== 'resolved')
                                                <span class="text-danger font-weight-bold">{{ $fu->due_date->format('d M Y') }} <i class="mdi mdi-alert"></i></span>
                                            @else {{ $fu->due_date->format('d M Y') }} @endif
                                        @else — @endif
                                    </td>
                                    <td><span class="badge badge-{{ $statusColors2[$fu->status] ?? 'secondary' }}">{{ str_replace('_', ' ', ucfirst($fu->status)) }}</span></td>
                                    <td>{{ $fu->createdByUser?->surname ?? '—' }} {{ $fu->createdByUser?->firstname ?? '' }} {{ $fu->createdByUser?->othername ?? '' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No follow-ups recorded</div>
                    @endif
                </div>

                {{-- ========== SALARY TAB ========== --}}
                <div class="tab-pane fade" id="salary" role="tabpanel">
                    @if($staff->currentSalaryProfile)
                    @php $sp = $staff->currentSalaryProfile; @endphp
                    <div class="row mb-4">
                        <div class="col-md-3 mb-2">
                            <div class="summary-card" style="border-color:#059669;">
                                <div class="sc-value text-success">₦{{ number_format($sp->gross_salary, 0) }}</div>
                                <div class="sc-label">Gross Monthly</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="summary-card" style="border-color:#dc2626;">
                                <div class="sc-value text-danger">₦{{ number_format($sp->total_deductions, 0) }}</div>
                                <div class="sc-label">Total Deductions</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="summary-card" style="border-color:#2563eb;">
                                <div class="sc-value text-primary">₦{{ number_format($sp->net_salary, 0) }}</div>
                                <div class="sc-label">Net Monthly</div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <div class="summary-card" style="border-color:#7c3aed;">
                                <div class="sc-value" style="color:#7c3aed;">₦{{ number_format($sp->gross_salary * 12, 0) }}</div>
                                <div class="sc-label">Gross Annual</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="bio-label">Effective From</div>
                            <div class="bio-value">{{ $sp->effective_from?->format('d M Y') ?? '—' }}</div>
                            <div class="bio-label">Pay Frequency</div>
                            <div class="bio-value">{{ ucfirst(str_replace('_', ' ', $sp->pay_frequency ?? 'monthly')) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="bio-label">Salary Increment Date</div>
                            <div class="bio-value">{{ $staff->salary_increment_date ? $staff->salary_increment_date->format('d M Y') : '—' }}</div>
                        </div>
                    </div>

                    @if($staff->salaryProfiles->count()> 1)
                    <div class="mt-4">
                        <h6 class="font-weight-bold mb-2" style="color:var(--primary-color);"><i class="mdi mdi-history mr-1"></i> Salary History</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-striped hr-table">
                                <thead>
                                    <tr><th>Effective From</th><th>Effective To</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($staff->salaryProfiles->sortByDesc('effective_from') as $profile)
                                    <tr class="{{ $profile->is_active ? 'table-success' : '' }}">
                                        <td>{{ $profile->effective_from?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ $profile->effective_to?->format('d M Y') ?? 'Current' }}</td>
                                        <td>₦{{ number_format($profile->gross_salary, 0) }}</td>
                                        <td>₦{{ number_format($profile->total_deductions, 0) }}</td>
                                        <td>₦{{ number_format($profile->net_salary, 0) }}</td>
                                        <td>@if($profile->is_active)<span class="badge badge-success">Active</span>@else<span class="badge badge-secondary">Ended</span>@endif</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    @else
                    <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No salary profile configured</div>
                    @endif

                    @if($staff->bank_name || $staff->bank_account_number)
                    <div class="mt-3">
                        <div class="bio-section-title"><i class="mdi mdi-bank mr-1"></i> Bank Details</div>
                        <div class="bio-label">Bank Name</div>
                        <div class="bio-value">{{ $staff->bank_name ?? '—' }}</div>
                        <div class="bio-label">Account Number</div>
                        <div class="bio-value">{{ $staff->bank_account_number ?? '—' }}</div>
                        <div class="bio-label">Account Name</div>
                        <div class="bio-value">{{ $staff->bank_account_name ?? '—' }}</div>
                    </div>
                    @endif

                    @if($staff->tax_id || $staff->pension_id)
                    <div class="mt-3">
                        <div class="bio-section-title"><i class="mdi mdi-file-document mr-1"></i> Tax & Pension</div>
                        <div class="bio-label">Tax ID</div>
                        <div class="bio-value">{{ $staff->tax_id ?? '—' }}</div>
                        <div class="bio-label">Pension ID</div>
                        <div class="bio-value">{{ $staff->pension_id ?? '—' }}</div>
                    </div>
                    @endif
                </div>

                {{-- ========== LEAVE & DISCIPLINARY TAB ========== --}}
                <div class="tab-pane fade" id="leave-disc" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="font-weight-bold mb-3" style="color:var(--primary-color);"><i class="mdi mdi-calendar-account mr-1"></i> Leave Requests</h6>
                            @if($staff->leaveRequests && $staff->leaveRequests->count())
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped hr-table">
                                    <thead>
                                        <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        @php $lvStatColors = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','cancelled'=>'secondary','recalled'=>'dark']; @endphp
                                        @foreach($staff->leaveRequests->sortByDesc('start_date')->take(10) as $lr)
                                        <tr>
                                            <td>{{ $lr->leaveType?->name ?? '—' }}</td>
                                            <td>{{ $lr->start_date?->format('d M Y') ?? '—' }}</td>
                                            <td>{{ $lr->end_date?->format('d M Y') ?? '—' }}</td>
                                            <td>{{ $lr->days_requested ?? '—' }}</td>
                                            <td><span class="badge badge-{{ $lvStatColors[$lr->status] ?? 'secondary' }}">{{ ucfirst($lr->status ?? '') }}</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($staff->leaveRequests->count()> 10)
                            <small class="text-muted">Showing latest 10 of {{ $staff->leaveRequests->count() }}. <a href="{{ route('hr.leave-requests.index', ['staff_id' => $staff->id]) }}">View all</a></small>
                            @endif
                            @else
                            <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No leave requests found</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6 class="font-weight-bold mb-3" style="color:var(--primary-color);"><i class="mdi mdi-gavel mr-1"></i> Disciplinary Records</h6>
                            @if($staff->disciplinaryQueries && $staff->disciplinaryQueries->count())
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped hr-table">
                                    <thead>
                                        <tr><th>Date</th><th>Subject</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($staff->disciplinaryQueries->sortByDesc('issued_date') as $dq)
                                        <tr>
                                            <td>{{ $dq->issued_date?->format('d M Y') ?? '—' }}</td>
                                            <td>{{ Str::limit($dq->subject ?? $dq->query_details ?? '—', 60) }}</td>
                                            <td>
                                                @php $dqColors = ['pending'=>'warning','responded'=>'info','decided'=>'success','appealed'=>'danger']; @endphp
                                                <span class="badge badge-{{ $dqColors[$dq->status] ?? 'secondary' }}">{{ ucfirst($dq->status ?? '') }}</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="tab-section-empty"><i class="mdi mdi-information-outline mr-1"></i>No disciplinary records</div>
                            @endif

                            @if($staff->hr_notes)
                            <div class="mt-3">
                                <div class="bio-section-title"><i class="mdi mdi-note-text mr-1"></i> HR Notes</div>
                                <div class="bio-value">{{ $staff->hr_notes }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
