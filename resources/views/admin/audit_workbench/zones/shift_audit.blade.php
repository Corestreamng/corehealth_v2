@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-clock-outline"></i> Shift Collections Audit</h4>
    <div>
        <button class="btn btn-outline-primary btn-sm"><i class="mdi mdi-filter"></i> Filter</button>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export</button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted">
                    <tr>
                        <th class="ps-4">Shift Start</th>
                        <th>Shift End</th>
                        <th>Cashier</th>
                        <th>Expected Amount</th>
                        <th>Remitted Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shifts as $shift)
                        <tr>
                            <td class="ps-4 text-nowrap">
                                <small class="text-muted d-block">{{ $shift->start_time->format('d M Y') }}</small>
                                <small class="text-muted">{{ $shift->start_time->format('h:i A') }}</small>
                            </td>
                            <td class="text-nowrap">
                                @if($shift->end_time)
                                    <small class="text-muted d-block">{{ $shift->end_time->format('d M Y') }}</small>
                                    <small class="text-muted">{{ $shift->end_time->format('h:i A') }}</small>
                                @else
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2">Active</span>
                                @endif
                            </td>
                            <td>
                                <h6 class="mb-0 fs-14">{{ optional($shift->user)->firstname }} {{ optional($shift->user)->surname }}</h6>
                            </td>
                            <td>₦{{ number_format($shift->expected_amount ?? 0, 2) }}</td>
                            <td>
                                @if($shift->end_time)
                                    ₦{{ number_format($shift->remitted_amount ?? 0, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($shift->end_time)
                                    @if(abs(($shift->expected_amount ?? 0) - ($shift->remitted_amount ?? 0)) < 1)
                                        <span class="text-success"><i class="mdi mdi-check-circle"></i> Balanced</span>
                                    @else
                                        <span class="text-danger"><i class="mdi mdi-alert-circle"></i> Discrepancy</span>
                                    @endif
                                @else
                                    <span class="text-muted">Ongoing</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if($shift->end_time)
                                    @if(isset($shift->is_audited) && $shift->is_audited)
                                        <button class="btn btn-sm btn-success audit-tick-btn" disabled>
                                            <i class="mdi mdi-check-decagram"></i> Audited
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited('NursingShift', {{ $shift->id }}, this)">
                                            <i class="mdi mdi-check-circle-outline"></i> Mark Audited
                                        </button>
                                    @endif
                                @else
                                    <button class="btn btn-sm btn-light" disabled>In Progress</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="mdi mdi-clock-outline mdi-48px text-light mb-2"></i>
                                <h5>No Shifts Found</h5>
                                <p>There are no billing shifts available for auditing.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($shifts->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $shifts->links() }}
        </div>
    @endif
</div>
@endsection
