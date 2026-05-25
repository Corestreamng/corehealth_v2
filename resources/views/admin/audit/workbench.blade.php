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
                                            <th>Actions</th>
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
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-primary settle-btn" 
                                                        data-id="{{ $staffUser->id }}"
                                                        data-name="{{ $staffUser->surname }} {{ $staffUser->firstname }}"
                                                        data-code="{{ $staffUser->staff_profile->employee_id ?? 'N/A' }}"
                                                        data-outstanding="{{ $staffUser->total_outstanding }}"
                                                        data-bills="{{ json_encode($staffUser->staffBills) }}">
                                                        Settle Bills
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
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

<div class="modal fade" id="settleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold" id="settleModalLabel">Settle Outstanding Bills</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="settleForm">
                @csrf
                <input type="hidden" name="staff_id" id="settle_staff_id">
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="bg-light p-3 rounded border">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-muted small font-weight-bold">Staff Member:</div>
                                <div class="font-weight-bold text-dark h6 mb-0" id="settle_staff_name"></div>
                            </div>
                            <div class="col-md-4 border-left">
                                <div class="text-muted small font-weight-bold">Outstanding Balance:</div>
                                <div class="text-warning font-weight-bold h5 mb-0" id="settle_staff_balance"></div>
                            </div>
                            <div class="col-md-4 border-left">
                                <div class="text-muted small font-weight-bold">Remaining Balance:</div>
                                <div class="text-success font-weight-bold h5 mb-0" id="settle_remaining_balance">₦0.00</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-0 small" style="border-left: 4px solid #17a2b8; font-size: 0.82rem;">
                        <i class="mdi mdi-information-outline mr-1 text-info"></i>
                        <strong>Settlement Allocation Note:</strong> The outstanding balance will be cleared by the sum of your <strong>Amount Paid</strong> and any <strong>Discount Allowed</strong>. The remaining balance represents what stays outstanding on the staff ledger. Overpayments are automatically capped.
                    </div>

                    <div>
                        <label class="form-label text-muted small font-weight-bold">Select Patient Bills to Settle:</label>
                        <div id="settle_bills_checkboxes" class="d-flex flex-column gap-2 p-2 border rounded bg-white" style="max-height: 150px; overflow-y: auto;"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small text-muted font-weight-bold">Payment Method</label>
                                <select name="payment_method" id="settle_payment_method" class="form-select">
                                    <option value="CASH">Cash</option>
                                    <option value="POS">POS (Card)</option>
                                    <option value="TRANSFER">Bank Transfer</option>
                                    <option value="MOBILE">Mobile Money</option>
                                </select>
                            </div>
                            <div class="mb-3" id="settle_bank_group" style="display: none;">
                                <label class="form-label small text-muted font-weight-bold">Bank Account</label>
                                <select name="bank_id" id="settle_bank_id" class="form-select">
                                    @foreach($activeBanks as $bank)
                                        <option value="{{ $bank->id }}">{{ $bank->name }} ({{ substr($bank->account_number, -4) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted font-weight-bold">Total Amount to Pay (₦)</label>
                                <input type="number" name="amount_paid" id="settle_amount_paid" class="form-control text-success font-weight-bold" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-danger font-weight-bold">Discount Allowed (₦)</label>
                                <input type="number" name="discount_amount" id="settle_discount_amount" class="form-control font-weight-bold" step="0.01" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            @include('accounting.partials.journal-entry-preview', [
                                'title' => 'Journal Entry Preview',
                                'description' => 'Live preview of the GL journal entry generated upon settlement',
                                'bodyId' => 'settleJePreviewBody',
                                'debitTotalId' => 'settleJeTotalDebit',
                                'creditTotalId' => 'settleJeTotalCredit',
                                'class' => 'shadow-sm border'
                            ])
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-success font-weight-bold">Clear Selected Bills</button>
                </div>
            </form>
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

    // 4. Settle Bills Modal
    $('.settle-btn').on('click', function() {
        var staffId = $(this).data('id');
        var name = $(this).data('name');
        var code = $(this).data('code');
        var outstanding = $(this).data('outstanding');
        var bills = $(this).data('bills');

        $('#settle_staff_id').val(staffId);
        $('#settle_staff_name').text(name + ' (' + code + ')');
        $('#settle_staff_balance').text('₦' + parseFloat(outstanding).toFixed(2));
        $('#settle_amount_paid').val(parseFloat(outstanding).toFixed(2));

        // Add bill checkboxes
        var container = $('#settle_bills_checkboxes');
        container.empty();
        bills.forEach(function(bill) {
            var patientName = 'N/A';
            if (bill.patient) {
                if (bill.patient.user) {
                    var parts = [];
                    if (bill.patient.user.surname) parts.push(bill.patient.user.surname);
                    if (bill.patient.user.firstname) parts.push(bill.patient.user.firstname);
                    if (bill.patient.user.othername) parts.push(bill.patient.user.othername);
                    patientName = parts.join(' ');
                } else if (bill.patient.fullname) {
                    patientName = bill.patient.fullname;
                }
            }
            var labelText = 'Patient: ' + patientName + ' | Outstanding: ₦' + parseFloat(bill.outstanding_amount).toFixed(2);
            var chk = $('<div class="form-check">' +
                '<input class="form-check-input bill-chk" type="checkbox" name="bill_ids[]" value="' + bill.id + '" data-amount="' + bill.outstanding_amount + '" id="bill_chk_' + bill.id + '" checked>' +
                '<label class="form-check-label text-dark small" for="bill_chk_' + bill.id + '">' + labelText + '</label>' +
                '</div>');
            container.append(chk);
        });

        $('#settleModal').data('raw-balance', parseFloat(outstanding));
        $('#settleModal').modal('show');
        $('#settle_discount_amount').val('0.00');
        updateJournalEntryPreview();
    });

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

    // Recalculate amount paid based on selected checkboxes
    $(document).on('change', '.bill-chk', function() {
        var total = 0;
        $('.bill-chk:checked').each(function() {
            total += parseFloat($(this).data('amount'));
        });
        $('#settle_amount_paid').val(total.toFixed(2));
        $('#settle_discount_amount').val('0.00');
        $('#settleModal').data('raw-balance', total);
        updateJournalEntryPreview();
    });

    // Settle payment method change
    $('#settle_payment_method').on('change', function() {
        if ($(this).val() === 'CASH') {
            $('#settle_bank_group').hide();
        } else {
            $('#settle_bank_group').show();
        }
        updateJournalEntryPreview();
    });

    $('#settle_bank_id').on('change', function() {
        updateJournalEntryPreview();
    });

    $('#settle_amount_paid').on('input keyup change', function() {
        updateJournalEntryPreview();
    });

    $('#settle_discount_amount').on('input keyup change', function() {
        updateJournalEntryPreview();
    });

    function updateJournalEntryPreview() {
        var amount = parseFloat($('#settle_amount_paid').val()) || 0;
        var discount = parseFloat($('#settle_discount_amount').val()) || 0;
        var method = $('#settle_payment_method').val();
        var bankText = $('#settle_bank_id option:selected').text();
        var bankId = $('#settle_bank_id').val();

        var $tbody = $('#settleJePreviewBody');
        var $debitTotal = $('#settleJeTotalDebit');
        var $creditTotal = $('#settleJeTotalCredit');

        var rawBalance = parseFloat($('#settleModal').data('raw-balance')) || 0;
        var totalSettled = amount + discount;

        // Calculate remaining balance dynamically
        var remaining = Math.max(0, rawBalance - totalSettled);
        var remainingFormatted = '₦' + remaining.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        $('#settle_remaining_balance').text(remainingFormatted);
        if (remaining <= 0) {
            $('#settle_remaining_balance').removeClass('text-warning').addClass('text-success');
        } else {
            $('#settle_remaining_balance').removeClass('text-success').addClass('text-warning');
        }

        if (totalSettled <= 0) {
            $tbody.html('<tr><td colspan="3" class="text-center text-muted py-2"><i class="mdi mdi-information-outline mr-1"></i>Enter a valid amount or discount to see preview</td></tr>');
            $debitTotal.text('₦0.00');
            $creditTotal.text('₦0.00');
            return;
        }

        var debitAccount = "Cash in Hand (1010)";
        if (method !== 'CASH') {
            if (bankId && bankText && bankText.indexOf('--') === -1) {
                debitAccount = bankText.trim() + " (1020)";
            } else {
                debitAccount = "Bank Account (1020)";
            }
        }
        var creditAccount = "Accounts Receivable - Staff (1130)";
        var discountAccount = "Discount Allowed (6280)";

        var html = '';

        // 1. Cash / Bank Debit
        if (amount > 0) {
            var amountFormatted = '₦' + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            html += '<tr>' +
                '<td class="pl-2 py-1" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + debitAccount + '"><span class="font-weight-bold text-dark">' + debitAccount + '</span></td>' +
                '<td class="text-right text-success font-weight-bold py-1">' + amountFormatted + '</td>' +
                '<td class="text-right text-muted pr-2 py-1">-</td>' +
                '</tr>';
        }

        // 2. Discount Debit
        if (discount > 0) {
            var discountFormatted = '₦' + discount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            html += '<tr>' +
                '<td class="pl-2 py-1" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + discountAccount + '"><span class="font-weight-bold text-dark">' + discountAccount + '</span></td>' +
                '<td class="text-right text-success font-weight-bold py-1">' + discountFormatted + '</td>' +
                '<td class="text-right text-muted pr-2 py-1">-</td>' +
                '</tr>';
        }

        // 3. Accounts Receivable Credit
        if (totalSettled > 0) {
            var totalFormatted = '₦' + totalSettled.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            html += '<tr>' +
                '<td class="pl-2 py-1" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' + creditAccount + '"><span class="font-weight-bold text-dark">' + creditAccount + '</span></td>' +
                '<td class="text-right text-muted py-1">-</td>' +
                '<td class="text-right text-danger pr-2 font-weight-bold py-1">' + totalFormatted + '</td>' +
                '</tr>';
        }

        $tbody.html(html);
        
        var totalFormatted = '₦' + totalSettled.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        $debitTotal.text(totalFormatted);
        $creditTotal.text(totalFormatted);
    }

    // Settle bills AJAX submission
    $('#settleForm').on('submit', function(e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Settling...');

        $.ajax({
            url: "{{ route('audit.settle-bills') }}",
            method: "POST",
            data: $(this).serialize(),
            success: function(res) {
                $('#settleModal').modal('hide');
                alert(res.message);
                window.location.reload();
            },
            error: function(err) {
                btn.prop('disabled', false).text('Clear Selected Bills');
                alert(err.responseJSON ? err.responseJSON.message : 'An error occurred.');
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
