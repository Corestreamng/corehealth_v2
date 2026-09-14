{{-- Encounter Detail Modal Body — rendered server-side and injected via AJAX --}}
@php
    $patient = $encounter->patient;
    $user    = $patient?->user;
    $hmo     = $patient?->hmo;
    $doctor  = $encounter->doctor;
    $clinic  = $encounter->queue?->clinic;

    $patientName = $user
        ? trim($user->surname . ' ' . $user->firstname . ' ' . ($user->othername ?? ''))
        : 'Unknown';

    $doctorName = $doctor
        ? trim($doctor->surname . ' ' . $doctor->firstname . ' ' . ($doctor->othername ?? ''))
        : '—';

    $duration = ($encounter->started_at && $encounter->completed_at)
        ? \Carbon\Carbon::parse($encounter->started_at)->diffInMinutes(\Carbon\Carbon::parse($encounter->completed_at)) . ' min'
        : '—';
@endphp

<div class="p-4">

    {{-- Patient & Encounter Info Row --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3">
                    <h6 class="text-muted small font-weight-bold mb-2 text-uppercase"><i class="mdi mdi-account text-primary"></i> Patient</h6>
                    <div class="font-weight-bold text-dark" style="font-size:1rem;">{{ $patientName }}</div>
                    <small class="text-muted">File No: {{ $patient?->file_no ?? 'N/A' }}</small>
                    @if($hmo)
                        <div class="mt-1"><span class="badge bg-info text-white"><i class="mdi mdi-shield-account"></i> {{ $hmo->name }}</span></div>
                    @else
                        <div class="mt-1"><span class="badge bg-success text-white"><i class="mdi mdi-cash"></i> Private / Self-pay</span></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3">
                    <h6 class="text-muted small font-weight-bold mb-2 text-uppercase"><i class="mdi mdi-stethoscope text-primary"></i> Encounter Info</h6>
                    <div class="d-flex flex-column gap-1">
                        <div><span class="text-muted small">Doctor:</span> <strong>{{ $doctorName }}</strong></div>
                        <div><span class="text-muted small">Clinic:</span> {{ $clinic?->name ?? '—' }}</div>
                        <div><span class="text-muted small">Date:</span> {{ \Carbon\Carbon::parse($encounter->created_at)->format('d M Y, h:i A') }}</div>
                        <div><span class="text-muted small">Duration:</span> {{ $duration }}</div>
                        <div>
                            <span class="text-muted small">Status:</span>
                            @if($encounter->completed)
                                <span class="badge bg-success">Completed</span>
                            @else
                                <span class="badge bg-teal text-white">Active</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Clinical Orders Summary --}}
    <div class="row g-3 mb-3">
        {{-- Lab Requests --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-flask-outline text-primary"></i> Lab Requests ({{ $encounter->labRequests->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @forelse($encounter->labRequests as $lab)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ $lab->service_name ?? 'Lab Test #'.$lab->id }}</small>
                            <span class="badge badge-sm bg-{{ $lab->status >= 4 ? 'success' : ($lab->status >= 2 ? 'info' : 'secondary') }}">
                                {{ ['Requested','Collected','Analysed','Result In','Approved'][$lab->status] ?? 'Pending' }}
                            </span>
                        </div>
                    @empty
                        <small class="text-muted">No lab requests</small>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Imaging Requests --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-radiobox-marked text-warning"></i> Imaging ({{ $encounter->imagingRequests->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @forelse($encounter->imagingRequests as $img)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ $img->service_name ?? 'Scan #'.$img->id }}</small>
                            <span class="badge badge-sm bg-{{ $img->status >= 4 ? 'success' : 'warning' }}">{{ $img->status >= 4 ? 'Done' : 'Pending' }}</span>
                        </div>
                    @empty
                        <small class="text-muted">No imaging requests</small>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Prescriptions --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-pill text-success"></i> Prescriptions ({{ $encounter->productRequests->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @forelse($encounter->productRequests as $rx)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ $rx->item_name ?? 'Drug #'.$rx->id }}</small>
                            <small class="text-muted">Qty: {{ $rx->qty ?? '—' }}</small>
                        </div>
                    @empty
                        <small class="text-muted">No prescriptions</small>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Referrals, Admissions, Procedures row --}}
    <div class="row g-3">
        {{-- Referrals --}}
        @if($encounter->referrals->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-share-variant text-info"></i> Referrals ({{ $encounter->referrals->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @foreach($encounter->referrals as $ref)
                        <div class="py-1 border-bottom">
                            <small><strong>{{ $ref->targetClinic?->name ?? $ref->external_facility_name ?? 'Unknown' }}</strong></small>
                            <span class="badge badge-sm bg-secondary ms-1">{{ ucfirst($ref->status) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Admissions --}}
        @if($encounter->admissionRequests->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-bed text-danger"></i> Admissions ({{ $encounter->admissionRequests->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @foreach($encounter->admissionRequests as $adm)
                        <div class="py-1 border-bottom">
                            <small>Ward: <strong>{{ $adm->ward?->name ?? '—' }}</strong>, Bed: {{ $adm->bed?->bed_number ?? '—' }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Procedures --}}
        @if($encounter->procedures->isNotEmpty())
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-light py-2 px-3">
                    <span class="font-weight-bold small"><i class="mdi mdi-medical-bag text-purple"></i> Procedures ({{ $encounter->procedures->count() }})</span>
                </div>
                <div class="card-body p-2">
                    @foreach($encounter->procedures as $proc)
                        <div class="py-1 border-bottom">
                            <small>{{ $proc->name ?? 'Procedure #'.$proc->id }}</small>
                            <span class="badge badge-sm bg-{{ $proc->procedure_status === 'completed' ? 'success' : 'warning' }} ms-1">{{ ucfirst($proc->procedure_status ?? 'pending') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

</div>
