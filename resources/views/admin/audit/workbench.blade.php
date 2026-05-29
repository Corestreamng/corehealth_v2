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
                    <i class="mdi mdi-cash-multiple"></i> Financial & Revenue (A)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-clinical">
                    <i class="mdi mdi-pulse"></i> Clinical Flow (B)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-diagnostics">
                    <i class="mdi mdi-microscope"></i> Lab, Imaging & Pharm (C)
                </button>
                <button type="button" class="audit-tab-btn" data-target="#tab-module-inventory">
                    <i class="mdi mdi-archive-outline"></i> Inventory & Stores (D)
                </button>
            </div>
        </div>

        {{-- Center Content panels --}}
        <div class="col-lg-9">
            {{-- Panel: Dashboard --}}
            <div class="audit-panel" id="tab-dashboard">
                <div class="glass-panel">
                    <h4 class="text-dark mb-3">Auditor Workspace Overview</h4>
                    <p class="text-muted">Welcome to the Internal Audit workbench. This system aggregates clinical, financial, and operational EMR data points automatically into 13 core auditor worksheets. Toggle active worksheets using the settings drawer and apply period stamps once satisfied.</p>

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

                    {{-- Worksheet: cash_and_billing_audit --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-cash_and_billing_audit" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-cash text-success"></i> Cash Book & Billing Reconciliations</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'cash_and_billing_audit') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="cash_and_billing_audit">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits daily cash intake (Unified Receipts), unbilled services (Leakage), and registration fees.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Unified Receipts and Revenue Leakage.</div>
                        </div>
                    </div>

                    {{-- Worksheet: bank_reconciliation --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-bank_reconciliation" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-bank text-primary"></i> Bank Statements & POS Reconciliations</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'bank_reconciliation') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="bank_reconciliation">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Verifies digital/bank deposits match the General Ledger.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view POS/Bank Deposits and Reconciliations.</div>
                        </div>
                    </div>

                    {{-- Worksheet: hmo_nhis_verification --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-hmo_nhis_verification" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-hospital-building text-purple"></i> HMO/NHIS Claims & Capitation</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'hmo_nhis_verification') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="hmo_nhis_verification">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits services billed to HMOs (claims) and tracks Capitation Remittances.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Billed Claims and Capitation Received.</div>
                        </div>
                    </div>

                    {{-- Worksheet: discounts_refunds_debt --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-discounts_refunds_debt" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-percent text-warning"></i> Discounts, Refunds & Debt Recovery</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'discounts_refunds_debt') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="discounts_refunds_debt">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits money waived (Checkout), refunded, or owed (Staff Debt/Receivables).</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Waivers, Debt, and Adjustments.</div>
                        </div>
                    </div>

                    {{-- Worksheet: payroll_expenses_ledger --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-payroll_expenses_ledger" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-cash-multiple text-danger"></i> Payroll, Deductions & Expenses</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'payroll_expenses_ledger') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="payroll_expenses_ledger">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits money going out: Payroll, Statutory Deductions, Petty Cash, and OpEx.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Payroll Batches and Operational Expenses.</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Panel: Module 2 Clinical --}}
            <div class="audit-panel d-none" id="tab-module-clinical">
                <div class="glass-panel d-flex flex-column gap-4">

                    {{-- Worksheet: consulting_clinics_flow --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-consulting_clinics_flow" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-human-male-female text-info"></i> Consulting Clinics & Patient Flow</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'consulting_clinics_flow') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="consulting_clinics_flow">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits patient flow through specialist clinics (Queue states) and verifies consultation billing.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Queues and Appointments.</div>
                        </div>
                    </div>

                    {{-- Worksheet: inpatient_ward_income --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-inpatient_ward_income" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-bed text-warning"></i> Ward Income & Discharge Clearance</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'inpatient_ward_income') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="inpatient_ward_income">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits admission durations, ward-specific income, and financial clearance for discharges.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Active Admissions and Ward Income.</div>
                        </div>
                    </div>

                    {{-- Worksheet: theatre_bundles_audit --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-theatre_bundles_audit" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-scissors-cutting text-danger"></i> Theatre Bundles & Procedure Revenue</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'theatre_bundles_audit') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="theatre_bundles_audit">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits surgical procedures, bundled consumables usage, and theatre income.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Procedure Register and Bundled Consumption.</div>
                        </div>
                    </div>

                    {{-- Worksheet: maternity_morgue_audit --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-maternity_morgue_audit" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-baby-carriage text-pink"></i> Maternity Enrollments & Mortuary Register</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'maternity_morgue_audit') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="maternity_morgue_audit">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits maternity enrollments, deliveries, and morgue storage fees.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Maternity Deliveries and Mortuary Register.</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Panel: Module 3 Diagnostics --}}
            <div class="audit-panel d-none" id="tab-module-diagnostics">
                <div class="glass-panel d-flex flex-column gap-4">

                    {{-- Worksheet: lab_imaging_register --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-lab_imaging_register" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-microscope text-primary"></i> Lab/Imaging Register & Reagent Usage</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'lab_imaging_register') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="lab_imaging_register">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits diagnostic tests vs billing, and tracks lab reagent consumption via Store Roles.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Diagnostics Register and Reagent Usage.</div>
                        </div>
                    </div>

                    {{-- Worksheet: pharmacy_prescriptions --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-pharmacy_prescriptions" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-pill text-success"></i> Pharmacy Prescriptions, Returns & Damages</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'pharmacy_prescriptions') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="pharmacy_prescriptions">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits drug dispensing workflows, tracks pharmacy returns, and monitors damaged drugs.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Prescriptions, Returns, and Damages.</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Panel: Module 4 Inventory --}}
            <div class="audit-panel d-none" id="tab-module-inventory">
                <div class="glass-panel d-flex flex-column gap-4">

                    {{-- Worksheet: central_store_stock_check --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-central_store_stock_check" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-warehouse text-primary"></i> Central Store Stock & PO Price Variance</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'central_store_stock_check') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="central_store_stock_check">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits Main Store stock (filtered by Drug/Consumable/Utility and Categories), PO variations, Manual Batch variances, and Tally Card Ledgers.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Stock, Price Variances, and Tally Cards.</div>
                        </div>
                    </div>

                    {{-- Worksheet: departmental_ward_stores --}}
                    <div class="responsibility-section card bg-white p-3" id="sheet-departmental_ward_stores" style="border: 1px solid var(--audit-border);">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="text-dark font-weight-bold mb-0"><i class="mdi mdi-store text-info"></i> Departmental/Ward Stock & Requisitions</h5>
                            <div class="d-flex gap-1">
                                <a href="{{ route('audit.reports.show', 'departmental_ward_stores') }}" class="btn btn-xs btn-primary"><i class="mdi mdi-eye"></i> Details</a>
                                <button type="button" class="btn btn-xs btn-outline-primary stamp-sheet-btn" data-key="departmental_ward_stores">Stamp Worksheet</button>
                            </div>
                        </div>
                        <p class="text-muted small">Audits stock in decentralized stores (Wards, Labs, Theatre), requisition fulfillment rates, and damaged items.</p>
                        <div class="bg-light p-3 rounded border mt-2">
                            <div class="text-muted small">Click Details to view Decentralized Stock, Requisitions, and Damages.</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Drawer Overlay --}}

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
    <p class="text-muted small">Toggle which of the 13 worksheets are displayed in your Internal Audit dashboard panels.</p>

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

    // 3. LocalStorage persistence for the 13 worksheets
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
