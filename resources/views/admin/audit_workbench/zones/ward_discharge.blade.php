@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-hospital-building"></i> Ward Discharge Audit</h4>
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
                        <th class="ps-4">Discharge Date</th>
                        <th>Patient</th>
                        <th>Ward/Bed</th>
                        <th>Status</th>
                        <th>Consumables Checked</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admissions as $admission)
                        <tr class="{{ $admission->is_queried && is_null($admission->query_resolved_at) ? 'table-warning' : '' }}">
                            <td class="ps-4 text-nowrap">
                                <small class="text-muted d-block">{{ optional($admission->discharge_date)->format('d M Y') ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-purple-subtle text-purple rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                        {{ substr(optional($admission->patient)->user->firstname ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-14">{{ optional($admission->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($admission->patient)->file_no ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <h6 class="mb-0 fs-14">{{ optional($admission->ward)->name ?? 'Unknown Ward' }}</h6>
                                <small class="text-muted">Bed: {{ optional($admission->bed)->name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $admission->status ?? 'Discharged' }}</span>
                            </td>
                            <td>
                                @if(isset($admission->consumables_cleared) && $admission->consumables_cleared)
                                    <span class="text-success"><i class="mdi mdi-check-circle"></i> Cleared</span>
                                @else
                                    <span class="text-warning"><i class="mdi mdi-alert-circle"></i> Pending</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    @if(isset($admission->is_audited) && $admission->is_audited)
                                        <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($admission->auditor)->firstname }} on {{ $admission->audited_at }}">
                                            <i class="mdi mdi-check-decagram"></i> Audited
                                        </button>
                                    @elseif(isset($admission->is_queried) && $admission->is_queried && is_null($admission->query_resolved_at))
                                        <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('AdmissionRequest', {{ $admission->id }})" title="Queried: {{ $admission->query_notes }}">
                                            <i class="mdi mdi-alert-circle"></i> Resolve Query
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('AdmissionRequest', {{ $admission->id }})" title="Raise Query">
                                            <i class="mdi mdi-help-circle-outline"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('AdmissionRequest', {{ $admission->id }}, this)">
                                            <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="mdi mdi-hospital-building mdi-48px text-light mb-2"></i>
                                <h5>No Recent Discharges</h5>
                                <p>There are no recent ward discharges available for auditing.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($admissions->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $admissions->links() }}
        </div>
    @endif
</div>
@endsection
