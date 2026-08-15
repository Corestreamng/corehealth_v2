@extends('admin.audit_workbench.layout')

@section('page_name', 'Audit Workbench Dashboard')
@section('subpage_name', 'Internal Audit')

@section('audit_content')
<style>
    :root {
        --audit-bg: #f8fafc;
        --audit-card: #ffffff;
        --audit-border: #e2e8f0;
        --audit-text: #1e293b;
        --audit-muted: #64748b;
        --audit-accent: #4f46e5;
        --audit-success: #10b981;
        --audit-warning: #f59e0b;
        --audit-danger: #ef4444;
    }

    .audit-dashboard {
        font-family: 'Outfit', sans-serif;
        color: var(--audit-text);
        background-color: var(--audit-bg);
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid var(--audit-border);
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid var(--audit-border);
        border-radius: 8px;
        padding: 1.25rem;
        transition: all 0.3s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--audit-accent);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
    }
    
    .stamp-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
</style>

<div class="audit-dashboard mt-3">
    {{-- Header section --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom" style="border-color: var(--audit-border) !important;">
        <div>
            <h3 class="mb-1 text-dark d-flex align-items-center gap-2">
                <i class="mdi mdi-shield-check text-indigo" style="color: var(--audit-accent);"></i> Internal Audit Workbench
            </h3>
            <p class="text-muted mb-0">Dynamic EMR Worksheets, Digital Audit Stamps & Financial Clearing</p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            {{-- Date Filters Form --}}
            <form method="GET" action="{{ route('audit.workbench') }}" class="d-flex align-items-center gap-2 bg-white p-2 rounded-lg" style="border: 1px solid var(--audit-border);">
                <div class="d-flex align-items-center gap-1">
                    <span class="text-muted small">From:</span>
                    <input type="date" name="start_date" class="form-control form-control-sm border-secondary" value="{{ $startDate->format('Y-m-d') }}">
                </div>
                <div class="d-flex align-items-center gap-1">
                    <span class="text-muted small">To:</span>
                    <input type="date" name="end_date" class="form-control form-control-sm border-secondary" value="{{ $endDate->format('Y-m-d') }}">
                </div>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="mdi mdi-filter-variant"></i> Apply
                </button>
            </form>
        </div>
    </div>

    {{-- Top level KPIs --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="kpi-card h-100">
                <div class="text-muted small text-uppercase font-weight-bold">Cash Collections</div>
                <div class="h3 font-weight-bold my-1 text-success">₦{{ number_format($reconciliationKPIs['total_cash_collected'], 2) }}</div>
                <div class="text-muted small">CASH payment methods in period</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="kpi-card h-100">
                <div class="text-muted small text-uppercase font-weight-bold">Bank/POS Deposits</div>
                <div class="h3 font-weight-bold my-1 text-info">₦{{ number_format($reconciliationKPIs['total_pos_collected'], 2) }}</div>
                <div class="text-muted small">POS/Transfer payment methods</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="kpi-card h-100">
                <div class="text-muted small text-uppercase font-weight-bold">Staff Outstanding Bills</div>
                <div class="h3 font-weight-bold my-1 text-warning">₦{{ number_format($reconciliationKPIs['unpaid_staff_receivables'], 2) }}</div>
                <div class="text-muted small">Unpaid staff receivables total</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="kpi-card h-100">
                <div class="text-muted small text-uppercase font-weight-bold">Audit Stamps Applied</div>
                <div class="h3 font-weight-bold my-1 text-indigo" style="color: var(--audit-accent);">{{ $reconciliationKPIs['reconciled_stamps_count'] }} Stamps</div>
                <div class="text-muted small">Period stamp approvals locked</div>
            </div>
        </div>
    </div>

    {{-- Center Content Dashboard panels --}}
    <div class="row mt-4">
        <div class="col-md-6 mb-3">
            <div class="card h-100 bg-white border p-3">
                <h6 class="text-dark font-weight-bold"><i class="mdi mdi-information-outline text-info"></i> Period Audit Status</h6>
                <div class="mt-2 text-muted small">
                    Below are the applied stamps for the current period range:
                </div>
                <div class="mt-3 d-flex flex-column gap-2">
                    @php
                        $flatStamps = $stamps->flatten();
                    @endphp
                    @forelse($flatStamps->take(5) as $stamp)
                        <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                            <span class="small text-dark"><code>{{ $stamp->responsibility_key }}</code></span>
                            <span class="badge bg-success stamp-badge text-white"><i class="mdi mdi-check"></i> Stamped</span>
                        </div>
                    @empty
                        <div class="text-muted text-center py-2">No stamps applied yet for this range.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100 bg-white border p-3">
                <h6 class="text-dark font-weight-bold"><i class="mdi mdi-alert-circle-outline text-warning"></i> Key Audit Spotlights</h6>
                <ul class="list-unstyled mt-2 small text-muted d-flex flex-column gap-2">
                    <li><i class="mdi mdi-circle-small text-warning"></i> Outstanding Staff bills total: <strong>₦{{ number_format($reconciliationKPIs['unpaid_staff_receivables'], 2) }}</strong>. Please check the Receivables Tab to settle.</li>
                    <li><i class="mdi mdi-circle-small text-info"></i> Reconciled payroll matches <strong>{{ $payrollBreakdown->count() }} active EMR departments</strong> (excluding midwifery school).</li>
                    <li><i class="mdi mdi-circle-small text-success"></i> HMO claims matches <strong>{{ $hmoClaims->count() }} premium NHIS/NHIA/SHIS schemes</strong>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
