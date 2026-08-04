@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-cash-multiple"></i> Receivables Audit</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export</button>
    </div>
</div>

@include('admin.audit_workbench.partials.datetime_filter')

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <ul class="nav nav-tabs" id="receivablesTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button" role="tab" aria-controls="staff" aria-selected="true">
                    Staff Receivables
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="org-tab" data-bs-toggle="tab" data-bs-target="#org" type="button" role="tab" aria-controls="org" aria-selected="false">
                    Organization Receivables
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="tab-content" id="receivablesTabContent">
            <!-- Staff Receivables Tab -->
            <div class="tab-pane fade show active" id="staff" role="tabpanel" aria-labelledby="staff-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Staff Member</th>
                                <th>Patient</th>
                                <th>Total Bill</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staffBills as $bill)
                                <tr class="{{ $bill->is_queried && is_null($bill->query_resolved_at) ? 'table-warning' : '' }}">
                                    <td class="ps-4 text-nowrap">
                                        <small class="text-muted d-block">{{ $bill->created_at->format('d M Y') }}</small>
                                        <small class="text-muted">{{ $bill->created_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 fs-14">{{ optional($bill->staffUser)->firstname ?? 'Unknown' }} {{ optional($bill->staffUser)->surname ?? '' }}</h6>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 fs-14">{{ optional($bill->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($bill->patient)->file_no ?? 'N/A' }}</small>
                                    </td>
                                    <td>₦{{ number_format($bill->total_amount, 2) }}</td>
                                    <td class="text-danger fw-bold">₦{{ number_format($bill->outstanding_amount, 2) }}</td>
                                    <td>
                                        @if($bill->status == 'paid')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Paid</span>
                                        @elseif($bill->status == 'pending')
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2">Pending</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $bill->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(isset($bill->is_audited) && $bill->is_audited)
                                                <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($bill->auditor)->firstname }} on {{ $bill->audited_at }}">
                                                    <i class="mdi mdi-check-decagram"></i> Audited
                                                </button>
                                            @elseif(isset($bill->is_queried) && $bill->is_queried && is_null($bill->query_resolved_at))
                                                <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('StaffBill', {{ $bill->id }})" title="Queried: {{ $bill->query_notes }}">
                                                    <i class="mdi mdi-alert-circle"></i> Resolve Query
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('StaffBill', {{ $bill->id }})" title="Raise Query">
                                                    <i class="mdi mdi-help-circle-outline"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('StaffBill', {{ $bill->id }}, this)">
                                                    <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="mdi mdi-cash mdi-48px text-light mb-2"></i>
                                        <h5>No Staff Receivables</h5>
                                        <p>There are no staff bills available for auditing.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($staffBills->hasPages())
                    <div class="card-footer bg-white border-top">
                        {{ $staffBills->appends(['org_page' => request('org_page')])->links() }}
                    </div>
                @endif
            </div>
            
            <!-- Organization Receivables Tab -->
            <div class="tab-pane fade" id="org" role="tabpanel" aria-labelledby="org-tab">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Organization</th>
                                <th>Patient</th>
                                <th>Total Bill</th>
                                <th>Outstanding</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orgBills as $bill)
                                <tr class="{{ $bill->is_queried && is_null($bill->query_resolved_at) ? 'table-warning' : '' }}">
                                    <td class="ps-4 text-nowrap">
                                        <small class="text-muted d-block">{{ $bill->created_at->format('d M Y') }}</small>
                                        <small class="text-muted">{{ $bill->created_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 fs-14">{{ optional($bill->organization)->name ?? 'Unknown' }}</h6>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 fs-14">{{ optional($bill->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($bill->patient)->file_no ?? 'N/A' }}</small>
                                    </td>
                                    <td>₦{{ number_format($bill->total_amount, 2) }}</td>
                                    <td class="text-danger fw-bold">₦{{ number_format($bill->outstanding_amount, 2) }}</td>
                                    <td>
                                        @if($bill->status == 'paid')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Paid</span>
                                        @elseif($bill->status == 'pending')
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2">Pending</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $bill->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            @if(isset($bill->is_audited) && $bill->is_audited)
                                                <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($bill->auditor)->firstname }} on {{ $bill->audited_at }}">
                                                    <i class="mdi mdi-check-decagram"></i> Audited
                                                </button>
                                            @elseif(isset($bill->is_queried) && $bill->is_queried && is_null($bill->query_resolved_at))
                                                <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('OrganizationBill', {{ $bill->id }})" title="Queried: {{ $bill->query_notes }}">
                                                    <i class="mdi mdi-alert-circle"></i> Resolve Query
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('OrganizationBill', {{ $bill->id }})" title="Raise Query">
                                                    <i class="mdi mdi-help-circle-outline"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('OrganizationBill', {{ $bill->id }}, this)">
                                                    <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="mdi mdi-office-building mdi-48px text-light mb-2"></i>
                                        <h5>No Organization Receivables</h5>
                                        <p>There are no organization bills available for auditing.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orgBills->hasPages())
                    <div class="card-footer bg-white border-top">
                        {{ $orgBills->appends(['staff_page' => request('staff_page')])->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('audit_scripts')
<script>
    // Remember active tab on reload
    $(document).ready(function() {
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeReceivablesTab', $(e.target).attr('data-bs-target'));
        });
        
        var activeTab = localStorage.getItem('activeReceivablesTab');
        if(activeTab){
            $('#receivablesTabs button[data-bs-target="' + activeTab + '"]').tab('show');
        }
    });
</script>
@endpush
