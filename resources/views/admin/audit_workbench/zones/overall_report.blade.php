@extends('admin.audit_workbench.layout')

@section('audit_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 text-primary"><i class="mdi mdi-chart-bar"></i> Overall Billing Report</h4>
    <div>
        <button class="btn btn-outline-success btn-sm"><i class="mdi mdi-download"></i> Export PDF</button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 rounded-3 bg-success-subtle h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-success text-uppercase fw-bold mb-1">Total Collections</h6>
                        <h3 class="mb-0 text-success">₦{{ number_format($totalCollections ?? 0, 2) }}</h3>
                    </div>
                    <div class="avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="mdi mdi-cash-register mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow-sm border-0 rounded-3 bg-warning-subtle h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-warning text-uppercase fw-bold mb-1">Total Receivables</h6>
                        <h3 class="mb-0 text-warning">₦{{ number_format($totalReceivables ?? 0, 2) }}</h3>
                    </div>
                    <div class="avatar bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width:60px; height:60px;">
                        <i class="mdi mdi-cash-multiple mdi-24px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3 mb-4">
    <div class="card-header bg-white border-bottom pt-3 pb-3">
        <h5 class="mb-0">Revenue Trend (Last 7 Days)</h5>
    </div>
    <div class="card-body" style="height: 300px;">
        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
            <div class="text-center">
                <i class="mdi mdi-chart-line mdi-48px text-light mb-2"></i>
                <p>Chart data will be visualized here.</p>
            </div>
        </div>
    </div>
</div>
@endsection
