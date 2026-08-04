@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-medical-bag"></i> Procedures & Theatre Verification</h4>
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
                        <th>Procedure</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($procedures as $procedure)
                        <tr class="{{ $procedure->is_queried && is_null($procedure->query_resolved_at) ? 'table-warning' : '' }}">
                            <td class="ps-4 text-nowrap">
                                <small class="text-muted d-block">{{ $procedure->created_at->format('d M Y') }}</small>
                                <small class="text-muted">{{ $procedure->created_at->format('h:i A') }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-danger-subtle text-danger rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                        {{ substr(optional($procedure->patient)->user->firstname ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-14">{{ optional($procedure->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($procedure->patient)->file_no ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <h6 class="mb-0 fs-14">{{ optional($procedure->procedure)->name ?? 'Unknown Procedure' }}</h6>
                                <small class="text-muted">Price: ₦{{ number_format($procedure->price, 2) }}</small>
                            </td>
                            <td>
                                @if($procedure->procedure_status == 'completed')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Completed</span>
                                @elseif($procedure->procedure_status == 'scheduled')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2">Scheduled</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $procedure->status_display }}</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ optional($procedure->requestedByUser)->firstname }} {{ optional($procedure->requestedByUser)->surname }}</small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    @if(isset($procedure->is_audited) && $procedure->is_audited)
                                        <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($procedure->auditor)->firstname }} on {{ $procedure->audited_at }}">
                                            <i class="mdi mdi-check-decagram"></i> Audited
                                        </button>
                                    @elseif(isset($procedure->is_queried) && $procedure->is_queried && is_null($procedure->query_resolved_at))
                                        <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('PatientProcedure', {{ $procedure->id }})" title="Queried: {{ $procedure->query_notes }}">
                                            <i class="mdi mdi-alert-circle"></i> Resolve Query
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('PatientProcedure', {{ $procedure->id }})" title="Raise Query">
                                            <i class="mdi mdi-help-circle-outline"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('PatientProcedure', {{ $procedure->id }}, this)">
                                            <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="mdi mdi-medical-bag mdi-48px text-light mb-2"></i>
                                <h5>No Procedures found</h5>
                                <p>There are no procedures available for auditing at this time.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($procedures->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $procedures->links() }}
        </div>
    @endif
</div>
@endsection
