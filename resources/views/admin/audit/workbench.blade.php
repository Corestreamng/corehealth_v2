@extends('admin.layouts.app')

@section('page_name', 'Audit Workbench')
@section('subpage_name', 'Internal Audit')

@section('content')
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

    .audit-tab-btn {
        background: transparent;
        color: var(--audit-muted);
        border: 1px solid transparent;
        padding: 0.75rem 1.25rem;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s;
        text-align: left;
        width: 100%;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .audit-tab-btn.active {
        background: rgba(79, 70, 229, 0.08);
        color: var(--audit-accent);
        border-color: var(--audit-accent);
    }

    .audit-tab-btn:hover:not(.active) {
        background: rgba(0, 0, 0, 0.02);
        color: var(--audit-text);
    }

    /* Right drawer */
    .audit-drawer {
        position: fixed;
        right: -360px;
        top: 0;
        height: 100vh;
        width: 360px;
        background: #ffffff;
        border-left: 1px solid var(--audit-border);
        z-index: 1050;
        transition: right 0.3s ease-in-out;
        box-shadow: -4px 0 20px rgba(0,0,0,0.1);
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
    }

    .audit-drawer.open {
        right: 0;
    }

    .drawer-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0,0,0,0.3);
        z-index: 1040;
        display: none;
    }

    .drawer-overlay.show {
        display: block;
    }

    .responsibility-toggle-card {
        border-bottom: 1px solid var(--audit-border);
        padding: 0.75rem 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .glass-panel {
        background: #ffffff;
        border: 1px solid var(--audit-border);
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
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
            <p class="text-muted mb-0">Dynamic EMR Worksheets, Digital Audit Stamps & Staff Receivables clearing</p>
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

            <button type="button" class="btn btn-sm btn-outline-primary" id="openDrawerBtn">
                <i class="mdi mdi-cog-outline"></i> Worksheet Settings
            </button>
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

    {{-- Layout Grid --}}
    <div class="row">
        {{-- Side navigation tabs --}}
        <div class="col-lg-3 mb-4">
            <div class="glass-panel d-flex flex-column gap-2" style="background: #ffffff;">
                <div class="font-weight-bold text-dark mb-2 small text-uppercase tracking-wider">Workbench Modules</div>
                <button type="button" class="audit-tab-btn active" data-target="#tab-dashboard">
                    <i class="mdi mdi-view-dashboard-outline"></i> Dashboard Overview
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-staff-receivables">
                    <i class="mdi mdi-account-cash-outline"></i> Staff Bills Ledger
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-financials">
                    <i class="mdi mdi-cash-multiple"></i> Financial & Accounts
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-clinical">
                    <i class="mdi mdi-pulse"></i> Clinical & Operations
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-inventory">
                    <i class="mdi mdi-archive-outline"></i> Inventory & Store
                </button>
            </div>
        </div>

        {{-- Center Content panels --}}
        <div class="col-lg-9">
            {{-- Panel: Dashboard --}}
            <div class="audit-panel" id="tab-dashboard">
                <div class="glass-panel">
                    <h4 class="text-dark mb-3">Auditor Workspace Overview</h4>
                    <p class="text-muted">Welcome to the Internal Audit workbench. This system aggregates clinical, financial, and operational EMR data points automatically into 33 core auditor worksheets. Toggle active worksheets using the settings drawer and apply period stamps once satisfied.</p>
                    
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
                                    <li><i class="mdi mdi-circle-small text-warning"></i> Outstanding Staff bills total: <strong>₦{{ number_format($reconciliationKPIs['unpaid_staff_receivables'], 2) }}</strong>. Please check the Staff Bills Tab to settle.</li>
                                    <li><i class="mdi mdi-circle-small text-info"></i> Reconciled payroll matches <strong>{{ $payrollBreakdown->count() }} active EMR departments</strong> (excluding midwifery school).</li>
                                    <li><i class="mdi mdi-circle-small text-success"></i> HMO claims matches <strong>{{ $hmoClaims->count() }} premium NHIS/NHIA/SHIS schemes</strong>.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel: Staff Receivables Workbench --}}
            <div class="audit-panel d-none" id="tab-staff-receivables">
                <div class="glass-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                        <h4 class="text-dark mb-0"><i class="mdi mdi-account-cash text-primary"></i> Staff Receivables Ledger</h4>
                        <span class="badge bg-warning text-dark font-weight-bold">Clearing & Audit History</span>
                    </div>

                    <!-- Sub-tabs Nav -->
                    <ul class="nav nav-tabs mb-3" id="staffLedgerTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="outstanding-tab" data-bs-toggle="tab" href="#tab-outstanding" role="tab">
                                <i class="mdi mdi-alert-circle-outline"></i> Outstanding Balances
                                <span class="badge bg-warning text-dark ms-1">{{ $staffWithBills->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="history-tab" data-bs-toggle="tab" href="#tab-history" role="tab">
                                <i class="mdi mdi-history"></i> All Bills History
                                <span class="badge bg-secondary text-white ms-1">{{ $allStaffBills->count() }}</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Tabs Content -->
                    <div class="tab-content">
                        <!-- Sub-tab 1: Outstanding -->
                        <div class="tab-pane fade show active" id="tab-outstanding" role="tabpanel">
                            <div class="table-responsive mt-2">
                                <table class="table table-striped table-bordered" id="staffReceivablesTable" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Employee ID</th>
                                            <th>Unpaid Bills</th>
                                            <th class="text-right">Total Outstanding</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($staffWithBills as $staffUser)
                                            <tr>
                                                <td>
                                                    <strong>{{ $staffUser->surname }} {{ $staffUser->firstname }}</strong>
                                                </td>
                                                <td><code>{{ $staffUser->staff_profile->employee_id ?? 'N/A' }}</code></td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $staffUser->staffBills->count() }} Patient Bills</span>
                                                </td>
                                                <td class="text-right font-weight-bold text-warning">
                                                    ₦{{ number_format($staffUser->total_outstanding, 2) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="mdi mdi-checkbox-marked-circle-outline text-success mdi-36px"></i>
                                                    <p class="mb-0 mt-2">All staff accounts are fully cleared!</p>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Sub-tab 2: History -->
                        <div class="tab-pane fade" id="tab-history" role="tabpanel">
                            <div class="table-responsive mt-2">
                                <table class="table table-hover table-bordered table-striped" id="staffBillsHistoryTable" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Incurred Date</th>
                                            <th>Staff Member</th>
                                            <th>Patient Name</th>
                                            <th>Ref Details</th>
                                            <th class="text-right">Original Amt</th>
                                            <th class="text-right">Outstanding</th>
                                            <th>Status</th>
                                            <th>Settlement Details / Breakdown</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($allStaffBills as $bill)
                                            @php
                                                $staffName = $bill->staffUser 
                                                    ? trim($bill->staffUser->surname . ' ' . $bill->staffUser->firstname . ' ' . $bill->staffUser->othername) 
                                                    : 'Unknown Staff';
                                                $empCode = $bill->staffUser?->staff_profile?->employee_id ?? 'N/A';
                                                
                                                $patientName = $bill->patient && $bill->patient->user 
                                                    ? trim($bill->patient->user->surname . ' ' . $bill->patient->user->firstname . ' ' . $bill->patient->user->othername) 
                                                    : ($bill->patient?->fullname ?? 'N/A');
                                                $fileNo = $bill->patient?->file_no ?? 'N/A';
                                            @endphp
                                            <tr>
                                                <td><span class="small text-muted">{{ $bill->created_at->format('Y-m-d H:i') }}</span></td>
                                                <td>
                                                    <strong>{{ $staffName }}</strong><br>
                                                    <code class="small">{{ $empCode }}</code>
                                                </td>
                                                <td>
                                                    <strong>{{ $patientName }}</strong><br>
                                                    <code class="small">File: {{ $fileNo }}</code>
                                                </td>
                                                <td>
                                                    <span class="small text-muted">Ref: {{ $bill->checkoutPayment?->reference_no ?? 'N/A' }}</span>
                                                </td>
                                                <td class="text-right font-weight-bold">₦{{ number_format($bill->total_amount, 2) }}</td>
                                                <td class="text-right font-weight-bold @if($bill->outstanding_amount > 0) text-warning @else text-success @endif">
                                                    ₦{{ number_format($bill->outstanding_amount, 2) }}
                                                </td>
                                                <td>
                                                    @if($bill->status == 'settled')
                                                        <span class="badge bg-success text-white">Fully Settled</span>
                                                    @elseif($bill->status == 'partial')
                                                        <span class="badge bg-warning text-dark">Partially Cleared</span>
                                                    @else
                                                        <span class="badge bg-danger text-white">Pending</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($bill->status == 'settled' || $bill->status == 'partial')
                                                        <div class="small p-2 bg-light rounded border" style="min-width: 250px;">
                                                            <div><strong>Cleared At:</strong> {{ $bill->settled_at ? $bill->settled_at->format('Y-m-d H:i') : ($bill->updated_at ? $bill->updated_at->format('Y-m-d H:i') : 'N/A') }}</div>
                                                            <div><strong>Cleared Amount:</strong> ₦{{ number_format(floatval($bill->total_amount) - floatval($bill->outstanding_amount), 2) }}</div>
                                                            <div><strong>Method:</strong> <span class="badge bg-info text-white">{{ $bill->settlementPayment?->payment_method ?? 'N/A' }}</span></div>
                                                            @if($bill->settlementPayment?->bank)
                                                                <div><strong>Bank:</strong> {{ $bill->settlementPayment->bank->name }}</div>
                                                            @endif
                                                            @if($bill->settlementPayment?->reference_no)
                                                                <div><strong>Receipt Ref:</strong> <code>{{ $bill->settlementPayment->reference_no }}</code></div>
                                                            @endif

                                                            @if($bill->settlement_payment_id)
                                                                <button type="button" class="btn btn-xs btn-outline-primary show-breakdown-btn d-flex align-items-center gap-1 mt-2 font-weight-bold" 
                                                                    data-payment-id="{{ $bill->settlement_payment_id }}">
                                                                    <i class="mdi mdi-receipt-text-check mr-1"></i> View Breakdown
                                                                </button>
                                                            @endif

                                                            @if($bill->settlementPayment?->journalEntry)
                                                                <div class="mt-2 pt-2 border-top">
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary d-flex align-items-center gap-1 toggle-journal-btn" data-target="journal-entry-{{ $bill->id }}" style="font-size: 0.7rem; padding: 2px 5px;">
                                                                        <i class="mdi mdi-book-open-page-variant mr-1"></i> Show Journal Entry
                                                                    </button>
                                                                    <div id="journal-entry-{{ $bill->id }}" class="mt-2 d-none p-2 bg-white rounded border">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                                                            <span class="small font-weight-bold text-dark" style="font-size: 0.7rem;">Entry #: {{ $bill->settlementPayment->journalEntry->entry_number }}</span>
                                                                            <span class="badge bg-{{ $bill->settlementPayment->journalEntry->status === 'posted' ? 'success' : 'warning' }} text-white text-uppercase" style="font-size:0.6rem;">
                                                                                {{ $bill->settlementPayment->journalEntry->status }}
                                                                            </span>
                                                                        </div>
                                                                        <table class="table table-xs table-bordered mb-0" style="font-size: 0.65rem; width: 100%;">
                                                                            <thead class="bg-light">
                                                                                <tr>
                                                                                    <th>Account</th>
                                                                                    <th class="text-right">Dr</th>
                                                                                    <th class="text-right">Cr</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach($bill->settlementPayment->journalEntry->lines as $line)
                                                                                    <tr>
                                                                                        <td>
                                                                                            <span class="font-weight-bold text-dark">{{ $line->account?->code }}</span><br>
                                                                                            <span class="text-muted" style="font-size:0.6rem;">{{ $line->account?->name }}</span>
                                                                                        </td>
                                                                                        <td class="text-right text-success">{{ $line->debit > 0 ? '₦' . number_format($line->debit, 2) : '-' }}</td>
                                                                                        <td class="text-right text-danger">{{ $line->credit > 0 ? '₦' . number_format($line->credit, 2) : '-' }}</td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                            <tfoot class="font-weight-bold bg-light" style="font-size:0.65rem;">
                                                                                <tr>
                                                                                    <td>Total</td>
                                                                                    <td class="text-right text-success">₦{{ number_format($bill->settlementPayment->journalEntry->lines->sum('debit'), 2) }}</td>
                                                                                    <td class="text-right text-danger">₦{{ number_format($bill->settlementPayment->journalEntry->lines->sum('credit'), 2) }}</td>
                                                                                </tr>
                                                                            </tfoot>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">No settlement breakdown available.</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">No staff bills recorded.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Panel: Module 1 Financials --}}
            <div class="audit-panel d-none" id="tab-module-financials">
                <div class="glass-panel d-flex flex-column gap-4">
                    
                    {{-- Worksheet: cash_reconciliation --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-cash_reconciliation" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-cash text-success"></i> Cash Book & Daily Cashier Collections</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'cash_reconciliation') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="cash_reconciliation">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Monitors daily cashier collections, txn volume, and payment method distribution across CASH and Bank/POS.</p>
                        
                        <div class="table-responsive mt-2">
                            <table class="table table-striped table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Cashier Name</th>
                                        <th class="text-center">Txn Count</th>
                                        <th class="text-right">Cash Collected</th>
                                        <th class="text-right">Bank/POS Deposits</th>
                                        <th class="text-right">Staff Receivables</th>
                                        <th class="text-right">Total sum</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cashierSummary as $cashierRow)
                                        <tr>
                                            <td>{{ $cashierRow->cashier_name }}</td>
                                            <td class="text-center"><span class="badge bg-secondary">{{ $cashierRow->txn_count }}</span></td>
                                            <td class="text-right text-success">₦{{ number_format($cashierRow->cash_collected, 2) }}</td>
                                            <td class="text-right text-info">₦{{ number_format($cashierRow->bank_collected, 2) }}</td>
                                            <td class="text-right text-warning">₦{{ number_format($cashierRow->staff_receivable, 2) }}</td>
                                            <td class="text-right font-weight-bold text-dark">₦{{ number_format($cashierRow->total_collected, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No cashier collections in period</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Worksheet: hmo_claims_nhis --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-hmo_claims_nhis" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-hospital-building text-purple" style="color: #a855f7;"></i> HMO Claims NHIS/NHIA Matching</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'hmo_claims_nhis') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="hmo_claims_nhis">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks claims matching and payable variance values for NHIS, NHIA, SHIS, and PLASCHEMA schemes.</p>
                        
                        <div class="table-responsive mt-2">
                            <table class="table table-striped table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Scheme / HMO Name</th>
                                        <th class="text-center">Claims Count</th>
                                        <th class="text-right">Patient Payable Total</th>
                                        <th class="text-right">HMO Claim Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($hmoClaims as $hmoRow)
                                        <tr>
                                            <td><strong>{{ $hmoRow->hmo_name }}</strong></td>
                                            <td class="text-center"><span class="badge bg-secondary">{{ $hmoRow->claim_count }} claims</span></td>
                                            <td class="text-right text-muted">₦{{ number_format($hmoRow->total_payable, 2) }}</td>
                                            <td class="text-right text-purple font-weight-bold" style="color: #8b5cf6;">₦{{ number_format($hmoRow->total_claim, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No active HMO claims matched in period</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Worksheet: payroll_dept --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-payroll_dept" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-cash-multiple text-indigo" style="color: var(--audit-accent);"></i> Payroll Department Reconciliations</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'payroll_dept') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="payroll_dept">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Verifies active EMR staff salaries grouped by Department and Category (midwifery school excluded).</p>
                        
                        <div class="table-responsive mt-2">
                            <table class="table table-striped table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff Department</th>
                                        <th class="text-center">Staff Count</th>
                                        <th class="text-right">Basic Salary Total</th>
                                        <th class="text-right">Gross Salary Total</th>
                                        <th class="text-right">Net Salary Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($payrollBreakdown as $deptName => $deptData)
                                        <tr>
                                            <td><strong>{{ $deptName }}</strong></td>
                                            <td class="text-center"><span class="badge bg-secondary">{{ $deptData['count'] }} active</span></td>
                                            <td class="text-right text-muted">₦{{ number_format($deptData['basic_salary'], 2) }}</td>
                                            <td class="text-right text-info">₦{{ number_format($deptData['gross_salary'], 2) }}</td>
                                            <td class="text-right text-success font-weight-bold">₦{{ number_format($deptData['net_salary'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No department payroll profiles linked</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Panel: Module 2 Clinical --}}
            <div class="audit-panel d-none" id="tab-module-clinical">
                <div class="glass-panel d-flex flex-column gap-4">
                    
                    {{-- Worksheet: consulting_queues --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-consulting_queues" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-human-male-female text-info"></i> Consulting Queues & Bookings</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'consulting_queues') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="consulting_queues">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks active doctor bookings, consulting list wait-times, and encounter completion statuses.</p>
                        
                        <div class="row mt-2">
                            @forelse($consultingQueues as $qRow)
                                <div class="col-md-3 mb-2">
                                    <div class="bg-light p-2 rounded text-center border">
                                        <div class="text-muted small text-uppercase">{{ $qRow->status }}</div>
                                        <div class="h5 font-weight-bold text-dark mb-0">{{ $qRow->count }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-center text-muted">No appointments found in range</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Worksheet: inpatient_stays --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-inpatient_stays" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-bed text-warning"></i> Inpatient Stays & Bed Occupancy</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'inpatient_stays') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="inpatient_stays">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Calculates active EMR ward bed occupancies and inpatient admission listings.</p>
                        
                        <div class="row mt-2">
                            <div class="col-md-6 mb-2">
                                <div class="bg-light p-2 rounded text-center border">
                                    <div class="text-muted small">Active Admitted Inpatients</div>
                                    <div class="h4 font-weight-bold text-warning mb-0">{{ $inpatientCount }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="bg-light p-2 rounded text-center border">
                                    <div class="text-muted small">Bed Occupancy Rate</div>
                                    <div class="h4 font-weight-bold text-dark mb-0">
                                        {{ round(($occupiedBedsCount / $totalBedsCount) * 100, 1) }}%
                                        <small class="text-muted" style="font-size: 0.8rem;">({{ $occupiedBedsCount }}/{{ $totalBedsCount }})</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Worksheet: theatre_bundles --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-theatre_bundles" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-scissors-cutting text-danger"></i> Theatre Consumable Bundles</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'theatre_bundles') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="theatre_bundles">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Verifies theatre procedure items marked as bundled to prevent separate billing leakages.</p>
                        
                        <div class="bg-light p-3 rounded border d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted">Total active procedure items configured with <code>is_bundled = true</code></span>
                            <span class="h4 font-weight-bold text-danger mb-0">{{ $theatreBundles }} Items</span>
                        </div>
                    </div>

                    {{-- Worksheet: morgue_releases --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-morgue_releases" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-coffin text-muted"></i> Morgue collections & Decedents</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'morgue_releases') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="morgue_releases">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits morgue entry counts and release flags linked to the morgue ledger.</p>
                        
                        <div class="bg-light p-3 rounded border d-flex justify-content-between align-items-center mt-2">
                            <span class="text-muted">Total decedent profiles currently inside morgue records</span>
                            <span class="h4 font-weight-bold text-dark mb-0">{{ $morgueCount }} Decedents</span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Panel: Module 3 Inventory --}}
            <div class="audit-panel d-none" id="tab-module-inventory">
                <div class="glass-panel d-flex flex-column gap-4">
                    
                    {{-- Worksheet: store_governance --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-store_governance" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-lan text-info"></i> Store Role Governance & Lane Policies</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'store_governance') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="store_governance">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audit and verify store catalog lane permissions to enforce strict distribution rules.</p>
                        
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-dark small">EMR inventory resolution uses localized role defaults, department overrides, and fallback actions. Check details in the Store Governance page.</div>
                        </div>
                    </div>

                    {{-- Worksheet: requisition_fulfill --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-requisition_fulfill" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-clipboard-text-outline text-warning"></i> Lab Requisition Balances vs Services</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'requisition_fulfill') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="requisition_fulfill">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Cross-checks store requisitions to lab/radiology stores against billed items and completed results.</p>
                        
                        <div class="row mt-2">
                            <div class="col-md-4 mb-2">
                                <div class="bg-light p-2 rounded text-center border">
                                    <div class="text-muted small">Lab/Radiology Requisitions</div>
                                    <div class="h5 font-weight-bold text-warning mb-0">{{ $labStoresRequisitions->req_count ?? 0 }} Reqs</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="bg-light p-2 rounded text-center border">
                                    <div class="text-muted small">Lab Requests Billed</div>
                                    <div class="h5 font-weight-bold text-dark mb-0">{{ $labServiceCount }} Tests</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="bg-light p-2 rounded text-center border">
                                    <div class="text-muted small">Imaging Requests Billed</div>
                                    <div class="h5 font-weight-bold text-info mb-0">{{ $imagingServiceCount }} Scans</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

{{-- Settlement Breakdown Modal --}}
<div class="modal fade" id="settlementBreakdownModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-gradient-info text-white py-3">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="mdi mdi-receipt-text-check mdi-24px mr-2"></i>
                    <span>Settlement Transaction Breakdown</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Receipt Master Info Card -->
                <div class="card border-0 shadow-sm mb-4 rounded">
                    <div class="card-body p-3">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-muted small font-weight-bold text-uppercase">Receipt Reference</div>
                                <div class="h5 font-weight-black text-primary mb-0" id="breakdown_ref">N/A</div>
                            </div>
                            <div class="col-md-3 border-left-dashed">
                                <div class="text-muted small font-weight-bold text-uppercase">Payment Method</div>
                                <div class="h6 font-weight-bold text-dark mb-0">
                                    <span id="breakdown_method" class="badge bg-info text-white">CASH</span>
                                    <span id="breakdown_bank" class="text-muted small ml-1"></span>
                                </div>
                            </div>
                            <div class="col-md-3 border-left-dashed">
                                <div class="text-muted small font-weight-bold text-uppercase">Total Cash Cleared</div>
                                <div class="h5 font-weight-bold text-success mb-0" id="breakdown_total_paid">₦0.00</div>
                            </div>
                            <div class="col-md-3 border-left-dashed">
                                <div class="text-muted small font-weight-bold text-uppercase">Total Discount Allowed</div>
                                <div class="h5 font-weight-bold text-danger mb-0" id="breakdown_total_discount">₦0.00</div>
                            </div>
                        </div>
                        <div class="row mt-3 pt-3 border-top small text-muted">
                            <div class="col-md-6">
                                <i class="mdi mdi-calendar-clock mr-1"></i> <strong>Settlement Date:</strong> <span id="breakdown_date">N/A</span>
                            </div>
                            <div class="col-md-6 text-md-right">
                                <i class="mdi mdi-account-tie mr-1"></i> <strong>Processed By:</strong> <span id="breakdown_processed_by">N/A</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Allocation Details Table -->
                <div class="card border-0 shadow-sm rounded">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 text-dark font-weight-bold">
                            <i class="mdi mdi-format-list-bulleted mr-1 text-info"></i>
                            Allocated Staff Receivables Breakdown
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="breakdown_bills_table">
                            <thead class="bg-light">
                                <tr>
                                    <th class="py-3 px-4">Incurred Date</th>
                                    <th class="py-3">Patient Details</th>
                                    <th class="py-3">Invoice Ref</th>
                                    <th class="py-3 text-right">Original Bill</th>
                                    <th class="py-3 text-right text-success">Cash Allocated</th>
                                    <th class="py-3 text-right text-danger">Discount Allocated</th>
                                    <th class="py-3 text-right">Remaining Balance</th>
                                    <th class="py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="breakdown_bills_tbody">
                                <!-- Dynamic Content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white py-3 border-top-0">
                <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Stamp Modal --}}
<div class="modal fade" id="stampModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Apply Period Audit Stamp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="stampForm">
                @csrf
                <input type="hidden" name="responsibility_key" id="stamp_responsibility_key">
                <input type="hidden" name="start_date" value="{{ $startDate->format('Y-m-d') }}">
                <input type="hidden" name="end_date" value="{{ $endDate->format('Y-m-d') }}">
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="bg-light p-2 rounded border">
                        <div class="text-muted small">Worksheet Responsibility:</div>
                        <div class="font-weight-bold text-info" id="stamp_responsibility_label"></div>
                        <div class="text-muted small mt-1">Audit Period: <code>{{ $startDate->format('Y-m-d') }}</code> to <code>{{ $endDate->format('Y-m-d') }}</code></div>
                    </div>
                    <div>
                        <label class="form-label small text-muted font-weight-bold">Auditing Notes / Review Comments</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Verify reconciliations are complete and correct..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-info text-white">Apply Approval Stamp</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Drawer Settings panel --}}
<div class="drawer-overlay" id="drawerOverlay"></div>
<div class="audit-drawer" id="auditDrawer">
    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
        <h5 class="text-dark mb-0 font-weight-bold"><i class="mdi mdi-cog-outline text-info"></i> Worksheet Settings</h5>
        <button type="button" class="btn-close" id="closeDrawerBtn"></button>
    </div>
    <p class="text-muted small">Toggle which of the 33 worksheets are displayed in your Internal Audit dashboard panels.</p>
    
    <div style="flex: 1; overflow-y: auto;" class="d-flex flex-column gap-2 mt-2">
        @foreach($responsibilities as $moduleKey => $list)
            <div class="mt-2">
                <div class="small font-weight-bold text-uppercase text-info tracking-wider mb-1">{{ ucfirst($moduleKey) }} Responsibilities</div>
                @foreach($list as $rKey => $rLabel)
                    <div class="responsibility-toggle-card">
                        <span class="small text-dark text-truncate mr-2" title="{{ $rLabel }}">{{ $rLabel }}</span>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('audit.reports.show', $rKey) }}" class="btn btn-xs btn-outline-info p-1 py-0 mr-1" title="View Full Report"><i class="mdi mdi-eye"></i></a>
                            <div class="form-check form-switch">
                                <input class="form-check-input responsibility-checkbox" type="checkbox" data-key="{{ $rKey }}" checked>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // 1. Sidebar tab switching
    $('.audit-tab-btn').on('click', function() {
        $('.audit-tab-btn').removeClass('active');
        $(this).addClass('active');

        var target = $(this).data('target');
        $('.audit-panel').addClass('d-none');
        $(target).removeClass('d-none');
    });

    // 2. Settings Drawer Toggle
    $('#openDrawerBtn').on('click', function() {
        $('#auditDrawer').addClass('open');
        $('#drawerOverlay').addClass('show');
    });

    $('#closeDrawerBtn, #drawerOverlay').on('click', function() {
        $('#auditDrawer').removeClass('open');
        $('#drawerOverlay').removeClass('show');
    });

    // 3. LocalStorage persistence for the 33 worksheets
    function loadWorksheetSettings() {
        var activeSheets = localStorage.getItem('active_worksheets');
        if (activeSheets) {
            var sheets = JSON.parse(activeSheets);
            $('.responsibility-checkbox').each(function() {
                var key = $(this).data('key');
                if (sheets[key] === false) {
                    $(this).prop('checked', false);
                    $('#sheet-' + key).addClass('d-none');
                } else {
                    $(this).prop('checked', true);
                    $('#sheet-' + key).removeClass('d-none');
                }
            });
        }
    }

    $('.responsibility-checkbox').on('change', function() {
        var sheets = {};
        $('.responsibility-checkbox').each(function() {
            var key = $(this).data('key');
            sheets[key] = $(this).is(':checked');
            if ($(this).is(':checked')) {
                $('#sheet-' + key).removeClass('d-none');
            } else {
                $('#sheet-' + key).addClass('d-none');
            }
        });
        localStorage.setItem('active_worksheets', JSON.stringify(sheets));
    });

    loadWorksheetSettings();

    // Initialize history table DataTable
    if ($('#staffBillsHistoryTable tbody tr').length > 1) {
        $('#staffBillsHistoryTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                emptyTable: "No staff bills history found"
            }
        });
    }

    // Fetch and display breakdown details in #settlementBreakdownModal
    $(document).on('click', '.show-breakdown-btn', function() {
        var paymentId = $(this).data('payment-id');
        if (!paymentId) return;

        var $tbody = $('#breakdown_bills_tbody');
        $tbody.html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="mdi mdi-loading mdi-spin mr-1"></i>Loading settlement breakdown...</td></tr>');
        $('#settlementBreakdownModal').modal('show');

        $.ajax({
            url: "/audit-workbench/settlement-breakdown/" + paymentId,
            method: "GET",
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    var p = response.payment;
                    $('#breakdown_ref').text(p.reference_no || ('Payment #' + p.id));
                    $('#breakdown_method').text(p.payment_method);
                    $('#breakdown_bank').text(p.payment_method !== 'CASH' ? (' - ' + p.bank_name) : '');
                    $('#breakdown_total_paid').text('₦' + p.total_paid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    $('#breakdown_total_discount').text('₦' + p.total_discount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    $('#breakdown_date').text(p.settled_at);
                    $('#breakdown_processed_by').text(p.settled_by);

                    $tbody.empty();
                    response.bills.forEach(function(bill) {
                        var statusBadge = '';
                        if (bill.status === 'paid' || bill.status === 'settled') {
                            statusBadge = '<span class="badge bg-success text-white">Fully Settled</span>';
                        } else if (bill.status === 'partial') {
                            statusBadge = '<span class="badge bg-warning text-dark">Partially Cleared</span>';
                        } else {
                            statusBadge = '<span class="badge bg-danger text-white">Pending</span>';
                        }

                        var row = $('<tr>' +
                            '<td class="py-3 px-4"><span class="small text-muted">' + bill.incurred_date + '</span></td>' +
                            '<td><strong>' + bill.patient_name + '</strong><br><code class="small text-muted">File: ' + bill.file_no + '</code></td>' +
                            '<td><code class="small text-dark">' + bill.reference + '</code></td>' +
                            '<td class="text-right font-weight-bold">₦' + bill.original_amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td class="text-right text-success font-weight-bold">₦' + bill.allocated_paid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td class="text-right text-danger font-weight-bold">₦' + bill.allocated_discount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td class="text-right font-weight-bold text-secondary">₦' + bill.remaining_balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '<td class="text-center">' + statusBadge + '</td>' +
                            '</tr>');
                        $tbody.append(row);
                    });
                } else {
                    $tbody.html('<tr><td colspan="8" class="text-center text-danger py-4"><i class="mdi mdi-alert-circle mr-1"></i>Failed to load breakdown details.</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="8" class="text-center text-danger py-4"><i class="mdi mdi-alert-circle mr-1"></i>Error connecting to server.</td></tr>');
            }
        });
    });

    // 5. Stamp Modal Trigger
    $('.stamp-sheet-btn').on('click', function() {
        var key = $(this).data('key');
        var label = $(this).closest('.responsibility-section').find('h5').text().trim();

        $('#stamp_responsibility_key').val(key);
        $('#stamp_responsibility_label').text(label);
        $('#stampModal').modal('show');
    });

    // Stamp Form AJAX submission
    $('#stampForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Applying Stamp...');

        $.ajax({
            url: "{{ route('audit.stamps.approve') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function(res) {
                $('#stampModal').modal('hide');
                alert(res.message);
                window.location.reload();
            },
            error: function(err) {
                btn.prop('disabled', false).text('Apply Approval Stamp');
                alert('An error occurred.');
            }
        });
    });
    // Toggle journal entry preview
    $(document).on('click', '.toggle-journal-btn', function() {
        var targetId = $(this).data('target');
        var target = $('#' + targetId);
        if (target.hasClass('d-none')) {
            target.removeClass('d-none');
            $(this).html('<i class="mdi mdi-book-open-page-variant mr-1"></i> Hide Journal Entry').removeClass('btn-outline-secondary').addClass('btn-secondary');
        } else {
            target.addClass('d-none');
            $(this).html('<i class="mdi mdi-book-open-page-variant mr-1"></i> Show Journal Entry').removeClass('btn-secondary').addClass('btn-outline-secondary');
        }
    });
});
</script>
@endpush
@endsection
