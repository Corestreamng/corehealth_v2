@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-file-chart"></i> Custom Audit Report</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export CSV</button>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-body">
        <form action="{{ route('audit.custom-report') }}" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ request('end_date', now()->format('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
                <label for="department" class="form-label">Department / Unit</label>
                <select id="department" name="department" class="form-select">
                    <option value="">All Departments</option>
                    <option value="Pharmacy" {{ request('department') == 'Pharmacy' ? 'selected' : '' }}>Pharmacy</option>
                    <option value="Laboratory" {{ request('department') == 'Laboratory' ? 'selected' : '' }}>Laboratory</option>
                    <option value="Radiology" {{ request('department') == 'Radiology' ? 'selected' : '' }}>Radiology</option>
                    <option value="Ward" {{ request('department') == 'Ward' ? 'selected' : '' }}>Ward</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-magnify"></i> Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="mdi mdi-chart-box-outline mdi-48px text-light mb-2"></i>
                            <h5>Report Data Will Appear Here</h5>
                            <p>Select your filters and click Generate Report to see data.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
