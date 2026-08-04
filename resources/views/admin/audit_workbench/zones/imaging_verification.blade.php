@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-radiology"></i> Imaging Price Verification</h4>
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
                        <th>Imaging Test</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($imagingRequests as $request)
                        <tr class="{{ $request->is_queried && is_null($request->query_resolved_at) ? 'table-warning' : '' }}">
                            <td class="ps-4 text-nowrap">
                                <small class="text-muted d-block">{{ $request->created_at->format('d M Y') }}</small>
                                <small class="text-muted">{{ $request->created_at->format('h:i A') }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                        {{ substr(optional($request->patient)->user->firstname ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-14">{{ optional($request->patient)->fullname ?? 'Unknown' }}</h6>
                                        <small class="text-muted">{{ optional($request->patient)->file_no ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <h6 class="mb-0 fs-14">{{ optional($request->service)->service_name ?? 'Unknown Service' }}</h6>
                                <small class="text-muted">Price: ₦{{ number_format(optional(optional($request->service)->price)->sale_price ?? 0, 2) }}</small>
                            </td>
                            <td>
                                @if($request->status == 'Completed' || $request->status == 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2">Paid / Completed</span>
                                @elseif($request->status == 'Pending')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2">Pending</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2">{{ $request->status ?? 'Requested' }}</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ optional($request->doctor)->firstname }} {{ optional($request->doctor)->surname }}</small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    @if(isset($request->is_audited) && $request->is_audited)
                                        <button class="btn btn-sm btn-success audit-tick-btn" disabled title="Audited by {{ optional($request->auditor)->firstname }} on {{ $request->audited_at }}">
                                            <i class="mdi mdi-check-decagram"></i> Audited
                                        </button>
                                    @elseif(isset($request->is_queried) && $request->is_queried && is_null($request->query_resolved_at))
                                        <button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal('ImagingServiceRequest', {{ $request->id }})" title="Queried: {{ $request->query_notes }}">
                                            <i class="mdi mdi-alert-circle"></i> Resolve Query
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal('ImagingServiceRequest', {{ $request->id }})" title="Raise Query">
                                            <i class="mdi mdi-help-circle-outline"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('ImagingServiceRequest', {{ $request->id }}, this)">
                                            <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="mdi mdi-radiology mdi-48px text-light mb-2"></i>
                                <h5>No Imaging Requests found</h5>
                                <p>There are no imaging requests available for auditing at this time.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($imagingRequests->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $imagingRequests->links() }}
        </div>
    @endif
</div>
@endsection
