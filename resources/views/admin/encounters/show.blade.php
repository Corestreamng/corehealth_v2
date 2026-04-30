@extends('admin.layouts.app')
@section('title', 'Encounter Details')
@section('page_name', 'Encounters')
@section('subpage_name', 'Details')

@section('content')
    <div class="row mb-3">
        <div class="col d-flex gap-2">
            <a href="{{ url()->previous() }}" class="btn btn-sm btn-secondary">
                <i class="mdi mdi-arrow-left"></i> Back
            </a>
            <a href="{{ route('patient-services-rendered', ['patient_id' => $encounter->patient_id]) }}"
                class="btn btn-sm btn-outline-primary">
                <i class="mdi mdi-clipboard-list-outline"></i> Services Rendered
            </a>
            <a href="{{ route('patient.show', $encounter->patient_id) }}" class="btn btn-sm btn-outline-secondary">
                <i class="mdi mdi-account-details-outline"></i> Patient Profile
            </a>
        </div>
    </div>

    {{-- Patient & Encounter Header --}}
    <div class="card-modern mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="font-weight-bold mb-1">
                        {{ userfullname($encounter->patient->user_id) }}
                        <small class="text-muted">[{{ $encounter->patient->file_no ?? 'N/A' }}]</small>
                    </h5>
                    <div class="text-muted small">
                        <span class="mr-3">
                            <i class="mdi mdi-account"></i>
                            {{ ucfirst($encounter->patient->gender ?? 'N/A') }}
                            @if ($encounter->patient->dob)
                                &bull; {{ $encounter->patient->dob->age }} yrs
                            @endif
                        </span>
                        <span>
                            <i class="mdi mdi-shield-account"></i>
                            {{ $encounter->patient->hmo->name ?? 'Self / Private' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-6 text-md-right">
                    <div class="small text-muted">
                        <strong>Encounter #{{ $encounter->id }}</strong><br>
                        <i class="mdi mdi-doctor"></i>
                        Dr. {{ $encounter->doctor ? userfullname($encounter->doctor->id) : 'N/A' }}<br>
                        <i class="mdi mdi-clock-outline"></i>
                        {{ $encounter->created_at->format('D, d M Y \a\t H:i') }}
                    </div>
                    <span class="badge mt-1 {{ $encounter->completed ? 'badge-success' : 'badge-warning' }}">
                        {{ $encounter->completed ? 'Completed' : 'In Progress' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Diagnosis / Notes --}}
    <div class="card-modern mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0"><i class="mdi mdi-stethoscope mr-1"></i> Diagnosis / Reasons for Encounter</h6>
        </div>
        <div class="card-body">
            @if (count($diagnosisItems))
                <div class="mb-2">
                    @foreach ($diagnosisItems as $d)
                        <span class="badge badge-info mr-1 mb-1" style="font-size:0.85rem;">{{ $d }}</span>
                    @endforeach
                </div>
            @else
                <p class="text-muted mb-1">No diagnosis recorded.</p>
            @endif

            @if ($encounter->notes)
                <hr class="my-2">
                <p class="mb-0 small"><strong>Doctor's Notes:</strong></p>
                <div class="mt-1">{!! $encounter->notes !!}</div>
            @endif
        </div>
    </div>

    {{-- Labs --}}
    <div class="card-modern mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0">
                <i class="mdi mdi-flask-outline mr-1"></i>
                Lab Requests
                <span class="badge badge-secondary ml-1">{{ $encounter->labRequests->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($encounter->labRequests->isEmpty())
                <p class="text-muted p-3 mb-0">No lab requests for this encounter.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Test</th>
                                <th>Code</th>
                                <th>Priority</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($encounter->labRequests as $i => $lab)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $lab->service->service_name ?? 'N/A' }}</td>
                                    <td><small>{{ $lab->service->service_code ?? '' }}</small></td>
                                    <td>
                                        @if ($lab->priority === 'urgent')
                                            <span class="badge badge-danger">Urgent</span>
                                        @else
                                            <span class="badge badge-secondary">Routine</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $lab->note ?? '—' }}</small></td>
                                    <td>
                                        @php
                                            $labStatusMap = [
                                                1 => ['label' => 'Requested', 'class' => 'badge-secondary'],
                                                2 => ['label' => 'Sample Taken', 'class' => 'badge-info'],
                                                3 => ['label' => 'Processing', 'class' => 'badge-warning'],
                                                4 => ['label' => 'Resulted', 'class' => 'badge-primary'],
                                                5 => ['label' => 'Pending Approval', 'class' => 'badge-warning'],
                                                6 => ['label' => 'Rejected', 'class' => 'badge-danger'],
                                                7 => ['label' => 'Approved', 'class' => 'badge-success'],
                                            ];
                                            $ls = $labStatusMap[$lab->status] ?? [
                                                'label' => 'Unknown',
                                                'class' => 'badge-light',
                                            ];
                                        @endphp
                                        <span class="badge {{ $ls['class'] }}">{{ $ls['label'] }}</span>
                                    </td>
                                    <td>
                                        @if ($lab->result)
                                            <small>{{ $lab->result }}</small>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $lab->created_at->format('d M Y') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Imaging --}}
    <div class="card-modern mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0">
                <i class="mdi mdi-radiology-box-outline mr-1"></i>
                Imaging Requests
                <span class="badge badge-secondary ml-1">{{ $encounter->imagingRequests->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($encounter->imagingRequests->isEmpty())
                <p class="text-muted p-3 mb-0">No imaging requests for this encounter.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Study</th>
                                <th>Code</th>
                                <th>Priority</th>
                                <th>Note</th>
                                <th>Status</th>
                                <th>Report</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($encounter->imagingRequests as $i => $img)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $img->service->service_name ?? 'N/A' }}</td>
                                    <td><small>{{ $img->service->service_code ?? '' }}</small></td>
                                    <td>
                                        @if ($img->priority === 'urgent')
                                            <span class="badge badge-danger">Urgent</span>
                                        @else
                                            <span class="badge badge-secondary">Routine</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $img->note ?? '—' }}</small></td>
                                    <td>
                                        @php
                                            $imgStatusMap = [
                                                1 => ['label' => 'Requested', 'class' => 'badge-secondary'],
                                                2 => ['label' => 'In Progress', 'class' => 'badge-warning'],
                                                3 => ['label' => 'Resulted', 'class' => 'badge-primary'],
                                                4 => ['label' => 'Completed', 'class' => 'badge-success'],
                                                5 => ['label' => 'Pending Approval', 'class' => 'badge-warning'],
                                                6 => ['label' => 'Rejected', 'class' => 'badge-danger'],
                                                7 => ['label' => 'Approved', 'class' => 'badge-success'],
                                            ];
                                            $is = $imgStatusMap[$img->status] ?? [
                                                'label' => 'Unknown',
                                                'class' => 'badge-light',
                                            ];
                                        @endphp
                                        <span class="badge {{ $is['class'] }}">{{ $is['label'] }}</span>
                                    </td>
                                    <td>
                                        @if ($img->result)
                                            <small>{{ $img->result }}</small>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $img->created_at->format('d M Y') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Procedures --}}
    <div class="card-modern mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0">
                <i class="mdi mdi-needle mr-1"></i>
                Procedures
                <span class="badge badge-secondary ml-1">{{ $procedures->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($procedures->isEmpty())
                <p class="text-muted p-3 mb-0">No procedures for this encounter.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Procedure</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Scheduled</th>
                                <th>Outcome</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($procedures as $i => $proc)
                                @php
                                    $procStatusClasses = [
                                        'requested' => 'badge-secondary',
                                        'scheduled' => 'badge-info',
                                        'in_progress' => 'badge-warning',
                                        'completed' => 'badge-success',
                                        'cancelled' => 'badge-danger',
                                    ];
                                    $psc = $procStatusClasses[$proc->procedure_status] ?? 'badge-light';
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $proc->procedureDefinition->name ?? 'N/A' }}</td>
                                    <td>
                                        @if ($proc->priority === 'urgent')
                                            <span class="badge badge-danger">Urgent</span>
                                        @else
                                            <span
                                                class="badge badge-secondary">{{ ucfirst($proc->priority ?? 'routine') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $psc }}">
                                            {{ ucfirst(str_replace('_', ' ', $proc->procedure_status ?? 'N/A')) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            {{ $proc->scheduled_date ? $proc->scheduled_date->format('d M Y') : '—' }}
                                            {{ $proc->scheduled_time ? ' ' . \Carbon\Carbon::parse($proc->scheduled_time)->format('H:i') : '' }}
                                        </small>
                                    </td>
                                    <td><small>{{ $proc->outcome ?? '—' }}</small></td>
                                    <td><small>{{ $proc->created_at->format('d M Y') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Prescriptions --}}
    <div class="card-modern mb-3">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0">
                <i class="mdi mdi-pill mr-1"></i>
                Prescriptions
                <span class="badge badge-secondary ml-1">{{ $encounter->productRequests->count() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            @if ($encounter->productRequests->isEmpty())
                <p class="text-muted p-3 mb-0">No prescriptions for this encounter.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Drug / Product</th>
                                <th>Dose</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Prescribed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($encounter->productRequests as $i => $rx)
                                @php
                                    $rxStatus = match ((int) $rx->status) {
                                        0 => ['label' => 'Pending', 'class' => 'badge-secondary'],
                                        1 => ['label' => 'Requested', 'class' => 'badge-info'],
                                        2 => ['label' => 'Dispensed', 'class' => 'badge-success'],
                                        3 => ['label' => 'Cancelled', 'class' => 'badge-danger'],
                                        default => ['label' => 'Unknown', 'class' => 'badge-light'],
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $rx->product->product_name ?? 'N/A' }}</td>
                                    <td><small>{{ $rx->dose ?? '—' }}</small></td>
                                    <td>{{ $rx->qty ?? '—' }}</td>
                                    <td><span class="badge {{ $rxStatus['class'] }}">{{ $rxStatus['label'] }}</span></td>
                                    <td><small>{{ $rx->created_at->format('d M Y') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

@endsection
