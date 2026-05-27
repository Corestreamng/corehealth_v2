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
                                                                                        <td>
                                                    @if($bill->status == 'paid' || $bill->status == 'settled' || floatval($bill->outstanding_amount) <= 0)
                                                        <span class="badge bg-success text-white">Fully Settled</span>
                                                    @elseif($bill->payments->isNotEmpty() && floatval($bill->outstanding_amount) > 0)
                                                        <span class="badge bg-warning text-dark">Partially Cleared</span>
                                                    @else
                                                        <span class="badge bg-danger text-white">Pending</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($bill->payments->isNotEmpty())
                                                        <div class="small p-2 bg-light rounded border d-flex flex-column gap-3" style="min-width: 280px; max-width: 400px;">
                                                            @foreach($bill->payments as $payment)
                                                                <div class="p-2 bg-white rounded border shadow-xs @if(!$loop->last) mb-2 @endif">
                                                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-1 border-bottom">
                                                                        <span class="badge bg-info text-white font-weight-black" style="font-size: 0.65rem;">Payment #{{ $loop->iteration }}</span>
                                                                        <span class="small text-muted font-weight-bold" style="font-size: 0.7rem;">{{ $payment->created_at->format('Y-m-d H:i') }}</span>
                                                                    </div>
                                                                    <div class="mb-1"><strong>Amount Cleared:</strong> <span class="text-success font-weight-black">₦{{ number_format(floatval($payment->pivot->amount_allocated), 2) }}</span></div>
                                                                    @if(floatval($payment->pivot->discount_allocated) > 0)
                                                                        <div class="mb-1"><strong>Discount Portion:</strong> <span class="text-danger font-weight-bold">₦{{ number_format(floatval($payment->pivot->discount_allocated), 2) }}</span></div>
                                                                    @endif
                                                                    <div class="mb-1"><strong>Method:</strong> <span class="badge bg-secondary text-white font-weight-bold text-uppercase" style="font-size:0.6rem;">{{ $payment->payment_method }}</span></div>
                                                                    @if($payment->bank)
                                                                        <div class="mb-1 text-truncate"><strong>Bank:</strong> <span class="text-muted" style="font-size: 0.75rem;">{{ $payment->bank->name }}</span></div>
                                                                    @endif
                                                                    @if($payment->reference_no)
                                                                        <div class="mb-2"><strong>Receipt Ref:</strong> <code>{{ $payment->reference_no }}</code></div>
                                                                    @endif

                                                                    <div class="d-flex flex-wrap gap-1 align-items-center mt-2">
                                                                        <button type="button" class="btn btn-xs btn-outline-primary show-breakdown-btn d-flex align-items-center font-weight-bold"
                                                                            data-payment-id="{{ $payment->id }}" style="font-size: 0.65rem; padding: 2px 5px;">
                                                                            <i class="mdi mdi-receipt-text-check mr-1"></i> View Breakdown
                                                                        </button>

                                                                        @if($payment->journalEntry)
                                                                            <button type="button" class="btn btn-xs btn-outline-secondary d-flex align-items-center toggle-journal-btn ml-1"
                                                                                data-target="journal-entry-{{ $bill->id }}-{{ $payment->id }}" style="font-size: 0.65rem; padding: 2px 5px;">
                                                                                <i class="mdi mdi-book-open-page-variant mr-1"></i> GL Journal
                                                                            </button>
                                                                        @endif
                                                                    </div>

                                                                    @if($payment->journalEntry)
                                                                        <div id="journal-entry-{{ $bill->id }}-{{ $payment->id }}" class="mt-2 d-none p-2 bg-light rounded border">
                                                                            <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                                                                <span class="small font-weight-bold text-dark" style="font-size: 0.65rem;">Entry: {{ $payment->journalEntry->entry_number }}</span>
                                                                                <span class="badge bg-{{ $payment->journalEntry->status === 'posted' ? 'success' : 'warning' }} text-white text-uppercase" style="font-size:0.55rem;">
                                                                                    {{ $payment->journalEntry->status }}
                                                                                </span>
                                                                            </div>
                                                                            <table class="table table-xs table-bordered mb-0" style="font-size: 0.6rem; width: 100%;">
                                                                                <thead class="bg-white">
                                                                                    <tr>
                                                                                        <th>Account</th>
                                                                                        <th class="text-right">Dr</th>
                                                                                        <th class="text-right">Cr</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($payment->journalEntry->lines as $line)
                                                                                        <tr>
                                                                                            <td class="text-truncate" style="max-width: 90px;">
                                                                                                <span class="font-weight-bold text-dark">{{ $line->account?->code }}</span><br>
                                                                                                <span class="text-muted" style="font-size:0.55rem;">{{ $line->account?->name }}</span>
                                                                                            </td>
                                                                                            <td class="text-right text-success">{{ $line->debit > 0 ? '₦' . number_format($line->debit, 2) : '-' }}</td>
                                                                                            <td class="text-right text-danger">{{ $line->credit > 0 ? '₦' . number_format($line->credit, 2) : '-' }}</td>
                                                                                        </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                                <tfoot class="font-weight-bold bg-white" style="font-size:0.6rem;">
                                                                                    <tr>
                                                                                        <td>Total</td>
                                                                                        <td class="text-right text-success">₦{{ number_format($payment->journalEntry->lines->sum('debit'), 2) }}</td>
                                                                                        <td class="text-right text-danger">₦{{ number_format($payment->journalEntry->lines->sum('credit'), 2) }}</td>
                                                                                    </tr>
                                                                                </tfoot>
                                                                            </table>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
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

                    {{-- Worksheet: revenue_leakage --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-revenue_leakage" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-chart-line text-info"></i> Daily Invoice Audits & Revenue Leakage</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'revenue_leakage') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="revenue_leakage">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits daily invoices to detect unbilled services and revenue leakage points.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: expense_vouchers --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-expense_vouchers" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-receipt text-danger"></i> Expense Vouchers & Operational Spend</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'expense_vouchers') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="expense_vouchers">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Monitors operational expenses and validates voucher approvals for compliance.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: refund_claims --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-refund_claims" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-cash-refund text-success"></i> Patient Refunds & Adjustments Control</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'refund_claims') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="refund_claims">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks refund requests, credits, and account adjustments for patient reconciliation.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: discount_authorization --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-discount_authorization" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-percent text-warning"></i> Discount Approvals & Special Fee Waivers</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'discount_authorization') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="discount_authorization">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits discount approvals and fee waiver authorizations for policy compliance.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: debt_aging --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-debt_aging" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-history text-secondary"></i> Debt Recovery & Aged Receivables Control</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'debt_aging') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="debt_aging">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Monitors aged receivables and debt collection performance metrics.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: bank_statement_match --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-bank_statement_match" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-bank text-primary"></i> Daily Bank Statement Match & Deposits</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'bank_statement_match') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="bank_statement_match">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Reconciles daily deposits with bank statements for cash flow accuracy.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: petty_cash --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-petty_cash" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-wallet text-teal"></i> Petty Cash Disbursements & Voucher Auditing</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'petty_cash') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="petty_cash">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Validates petty cash transactions and supporting vouchers for authorization.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: statutory_deductions --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-statutory_deductions" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-file-check text-success"></i> Statutory Deductions & Pension Compliance</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'statutory_deductions') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="statutory_deductions">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits statutory payroll deductions and pension contributions for compliance.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
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

                    {{-- Worksheet: clinical_notes_audit --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-clinical_notes_audit" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-note-check-outline text-info"></i> Clinical Notes Completion & Vital Signs Logs</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'clinical_notes_audit') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="clinical_notes_audit">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits clinical notes completeness and vital signs capture frequency.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: maternity_deliveries --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-maternity_deliveries" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-home-heart text-danger"></i> Maternity Admissions & Delivery Outcomes</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'maternity_deliveries') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="maternity_deliveries">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks maternity admissions and delivery outcomes documentation.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: prescription_fills --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-prescription_fills" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-pill text-warning"></i> Pharmacy Prescriptions vs Fills Matching</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'prescription_fills') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="prescription_fills">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Reconciles prescription orders with pharmacy fulfillment records.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: treatment_plans --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-treatment_plans" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-clipboard-check-outline text-teal"></i> Doctor Treatment Plans & Ward Execution</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'treatment_plans') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="treatment_plans">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Verifies doctor-ordered treatment plans execution in ward settings.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: nursing_vitals --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-nursing_vitals" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-heart-pulse text-danger"></i> Nursing Vitals Capture & Frequency Audit</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'nursing_vitals') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="nursing_vitals">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits nursing vital signs capture frequency and documentation completeness.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: discharge_clearance --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-discharge_clearance" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-exit-run text-info"></i> Inpatient Discharge Clearance & Billing Audits</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'discharge_clearance') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="discharge_clearance">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Validates discharge clearances and final billing for inpatient stays.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: emergency_triage --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-emergency_triage" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-ambulance text-warning"></i> Emergency Intake & Triage Level Governance</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'emergency_triage') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="emergency_triage">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits emergency intake workflows and triage level assignments.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
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

                    {{-- Worksheet: stock_variance --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-stock_variance" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-check-circle-outline text-success"></i> Stock Count Variance & Catalog Adjustments</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'stock_variance') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="stock_variance">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits physical stock count variances and inventory adjustments.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: purchase_price_var --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-purchase_price_var" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-currency-usd text-info"></i> PO Purchase Price Variations</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'purchase_price_var') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="purchase_price_var">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks purchase order price variations and cost anomalies.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: dispensing_errors --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-dispensing_errors" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-package-check text-danger"></i> FIFO Dispensing Controls & Expiry Checks</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'dispensing_errors') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="dispensing_errors">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Validates FIFO compliance and expiry date monitoring in dispensing.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: damaged_goods --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-damaged_goods" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-alert-box-outline text-warning"></i> Damaged Goods & Write-off Approvals</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'damaged_goods') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="damaged_goods">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks damaged inventory and authorized write-off requests.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: consignment_audit --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-consignment_audit" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-file-document-check-outline text-info"></i> Consignment Stock Audits & Vendor Logs</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'consignment_audit') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="consignment_audit">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits consignment inventory and vendor reconciliation records.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: min_max_reorder --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-min_max_reorder" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-chart-line-variant text-success"></i> Min-Max Thresholds & Reorder Triggers</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'min_max_reorder') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="min_max_reorder">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Verifies min-max inventory thresholds and reorder point compliance.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: supplier_invoice --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-supplier_invoice" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-invoice-list-outline text-secondary"></i> Supplier Invoices vs PO Delivery Receipts</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'supplier_invoice') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="supplier_invoice">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Reconciles supplier invoices with purchase orders and delivery receipts.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: pharmacy_returns --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-pharmacy_returns" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-package-remove text-danger"></i> Pharmacy Product Returns & Shelf Restocking</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'pharmacy_returns') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="pharmacy_returns">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Tracks pharmacy product returns and restocking processes.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
                        </div>
                    </div>

                    {{-- Worksheet: procurement_contracts --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-procurement_contracts" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-handshake text-primary"></i> Procurement Contracts & Vendor Price Locks</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'procurement_contracts') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="procurement_contracts">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits procurement contracts and vendor price agreement compliance.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Worksheet content loading...</div>
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
