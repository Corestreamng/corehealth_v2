@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-account-cash"></i> Staff Deductions Report</h4>
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
                        <th class="ps-4">Staff Member</th>
                        <th>Department</th>
                        <th>Total Paid (History)</th>
                        <th>Current Outstanding</th>
                        <th>Recommended Deduction</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deductions as $row)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width:32px; height:32px;">
                                        {{ substr(optional($row->staff)->firstname ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fs-14">{{ optional($row->staff)->firstname }} {{ optional($row->staff)->surname }}</h6>
                                        <small class="text-muted">ID: {{ optional($row->staff)->id ?? 'N/A' }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted">{{ optional(optional($row->staff)->staff)->department->name ?? 'N/A' }}</span>
                            </td>
                            <td class="text-success">₦{{ number_format($row->total_paid ?? 0, 2) }}</td>
                            <td class="text-danger fw-bold">₦{{ number_format($row->total_outstanding ?? 0, 2) }}</td>
                            <td>
                                <div class="input-group input-group-sm" style="width: 150px;">
                                    <span class="input-group-text">₦</span>
                                    <input type="number" class="form-control" value="{{ $row->total_outstanding ?? 0 }}" max="{{ $row->total_outstanding ?? 0 }}">
                                </div>
                            </td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-primary">
                                    <i class="mdi mdi-send"></i> Send to Payroll
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="mdi mdi-account-cash mdi-48px text-light mb-2"></i>
                                <h5>No Staff Deductions</h5>
                                <p>There are no outstanding staff bills to deduct.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deductions->hasPages())
        <div class="card-footer bg-white border-top">
            {{ $deductions->links() }}
        </div>
    @endif
</div>
@endsection
