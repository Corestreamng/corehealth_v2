@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-pill"></i> Prescription Audit</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export</button>
    </div>
</div>

@include('admin.audit_workbench.partials.datetime_filter')

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Patient</th>
                        <th>Medication</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Prescriber</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prescriptions as $prescription)
                        <tr class="{{ $prescription->is_queried && is_null($prescription->query_resolved_at) ? 'table-warning' : '' }}">
                            <td class="ps-4 text-nowrap">
                                <small class="text-muted d-block">{{ $prescription->created_at->format('d M Y') }}</small>
                                <small class="text-muted">{{ $prescription->created_at->format('h:i A') }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                        {{ substr(optional($prescription->patient)->user->firstname ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-14">{{ optional($prescription->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($prescription->patient)->file_no ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <h6 class="mb-0 fs-14">{{ optional($prescription->product)->name ?? 'Unknown Medication' }}</h6>
                                <small class="text-muted">Dosage: {{ $prescription->dose }}</small>
                            </td>
                            <td>{{ $prescription->qty }}</td>
                            <td>
                                @if($prescription->status == 'Paid' || $prescription->status == 'dispensed')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Paid / Dispensed</span>
                                @elseif($prescription->status == 'Unpaid')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2">Unpaid</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $prescription->status ?? 'Pending' }}</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ optional($prescription->doctor)->firstname }} {{ optional($prescription->doctor)->surname }}</small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    @if(isset($prescription->is_audited) && $prescription->is_audited)
                                        <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($prescription->auditor)->firstname }} on {{ $prescription->audited_at }}">
                                            <i class="mdi mdi-check-decagram"></i> Audited
                                        </button>
                                    @elseif(isset($prescription->is_queried) && $prescription->is_queried && is_null($prescription->query_resolved_at))
                                        <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('ProductRequest', {{ $prescription->id }})" title="Queried: {{ $prescription->query_notes }}">
                                            <i class="mdi mdi-alert-circle"></i> Resolve Query
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('ProductRequest', {{ $prescription->id }})" title="Raise Query">
                                            <i class="mdi mdi-help-circle-outline"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('ProductRequest', {{ $prescription->id }}, this)">
                                            <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="mdi mdi-pill mdi-48px text-light mb-2"></i>
                                <h5>No prescriptions found</h5>
                                <p>There are no prescriptions available for auditing at this time.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($prescriptions->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $prescriptions->links() }}
        </div>
    @endif
</div>
@endsection
