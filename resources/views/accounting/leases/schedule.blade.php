@extends('admin.layouts.app')
@section('title', 'Lease Payment Schedule')
@section('page_name', 'Accounting')
@section('subpage_name', 'Payment Schedule')

@push('styles')
<style>
    /* Cards */
    .card-modern { border-radius: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; margin-bottom: 1rem; }
    .card-modern .card-header { border-radius: 0.5rem 0.5rem 0 0; padding: 1rem 1.25rem; font-weight: 600; }
    .card-modern .card-body { padding: 1.25rem; }

    /* Header gradient */
    .schedule-header-gradient {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        border-radius: 0.5rem;
    }

    /* Alert styling */
    .alert-container .alert { border-radius: 0.5rem; border-left: 4px solid; }
    .alert-container .alert-success { border-left-color: #28a745; }
    .alert-container .alert-danger { border-left-color: #dc3545; }
    .alert-container .alert-warning { border-left-color: #ffc107; }
    .alert-container .alert-info { border-left-color: #17a2b8; }

    /* Summary cards */
    .summary-card {
        border-radius: 0.5rem;
        padding: 1.25rem;
        text-align: center;
        transition: all 0.2s ease;
    }
    .summary-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
    .summary-card.bg-primary { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%) !important; }
    .summary-card.bg-success { background: linear-gradient(135deg, #27ae60 0%, #219a52 100%) !important; }
    .summary-card.bg-warning { background: linear-gradient(135deg, #f39c12 0%, #d68910 100%) !important; }
    .summary-card.bg-info { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important; }
    .summary-card.bg-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important; }

    /* Schedule table */
    .schedule-table th { font-size: 0.85rem; white-space: nowrap; }
    .schedule-table td { font-size: 0.9rem; }
    .schedule-table .table-success { background-color: rgba(40, 167, 69, 0.1) !important; }
    .schedule-table .table-danger { background-color: rgba(220, 53, 69, 0.1) !important; }
    .schedule-table .table-warning { background-color: rgba(255, 193, 7, 0.1) !important; }

    /* IFRS info cards */
    .ifrs-card {
        border-radius: 0.5rem;
        padding: 1rem;
        border-left: 4px solid;
    }
    .ifrs-card.principal { border-left-color: #17a2b8; background: rgba(23, 162, 184, 0.05); }
    .ifrs-card.interest { border-left-color: #dc3545; background: rgba(220, 53, 69, 0.05); }
    .ifrs-card.depreciation { border-left-color: #6f42c1; background: rgba(111, 66, 193, 0.05); }

    /* Print styles */
    @media print {
        .btn, .breadcrumb, nav, .sidebar, .navbar, footer, .no-print { display: none !important; }
        .card-modern { border: 1px solid #ddd !important; box-shadow: none !important; }
        .table-responsive { overflow: visible !important; }
        .schedule-header-gradient { background: #f8f9fa !important; color: #000 !important; }
        .summary-card { background: #f8f9fa !important; color: #000 !important; border: 1px solid #ddd !important; }
    }

    /* Status badges */
    .status-badge { font-size: 0.75rem; padding: 0.35em 0.65em; }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Payment Schedule', 'url' => '#', 'icon' => 'mdi-table']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Alert Container -->
        <div class="alert-container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert mr-2"></i>{{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
        </div>

        <!-- Lease Summary Header -->
        <div class="card-modern schedule-header-gradient mb-4">
            <div class="card-body text-white py-3">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center">
                            <div class="mr-3">
                                <i class="mdi mdi-table-clock" style="font-size: 2.5rem; opacity: 0.8;"></i>
                            </div>
                            <div>
                                <h4 class="mb-1">{{ $lease->lease_number }}</h4>
                                <p class="mb-0 opacity-75">{{ $lease->leased_item }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row text-center">
                            <div class="col-md-3 border-left border-white-25">
                                <small class="d-block opacity-75">Monthly Payment</small>
                                <strong>₦{{ number_format($lease->monthly_payment, 2) }}</strong>
                            </div>
                            <div class="col-md-3 border-left border-white-25">
                                <small class="d-block opacity-75">IBR</small>
                                <strong>{{ $lease->incremental_borrowing_rate }}% p.a.</strong>
                            </div>
                            <div class="col-md-3 border-left border-white-25">
                                <small class="d-block opacity-75">Initial Liability</small>
                                <strong>₦{{ number_format($lease->initial_lease_liability, 2) }}</strong>
                            </div>
                            <div class="col-md-3 border-left border-white-25">
                                <small class="d-block opacity-75">Initial ROU Asset</small>
                                <strong>₦{{ number_format($lease->initial_rou_asset_value, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <span class="badge badge-{{ $lease->status === 'active' ? 'success' : 'secondary' }} px-3 py-2">
                    <i class="mdi mdi-{{ $lease->status === 'active' ? 'check-circle' : 'pause-circle' }} mr-1"></i>
                    {{ ucfirst($lease->status) }}
                </span>
                <span class="badge badge-{{ in_array($lease->lease_type, ['short_term', 'low_value']) ? 'light text-secondary' : 'warning' }} px-3 py-2 ml-2">
                    {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }} Lease
                </span>
            </div>
            <div>
                <div class="btn-group mr-2">
                    <a href="{{ route('accounting.leases.schedule.export.pdf', $lease->id) }}" class="btn btn-outline-danger" title="Export to PDF">
                        <i class="mdi mdi-file-pdf-box mr-1"></i> PDF
                    </a>
                    <a href="{{ route('accounting.leases.schedule.export.excel', $lease->id) }}" class="btn btn-outline-success" title="Export to Excel">
                        <i class="mdi mdi-file-excel mr-1"></i> Excel
                    </a>
                </div>
                <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-primary ml-2">
                    <i class="mdi mdi-arrow-left mr-1"></i> Back to Lease
                </a>
            </div>
        </div>

        <!-- IFRS 16 Explanation -->
        @if(!in_array($lease->lease_type, ['short_term', 'low_value']))
        <div class="card-modern mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="mdi mdi-book-open-outline mr-2 text-primary"></i>IFRS 16 Schedule Components</h6>
                    <button class="btn btn-link btn-sm" type="button" data-toggle="collapse" data-target="#ifrsExplanation">
                        <i class="mdi mdi-chevron-down"></i>
                    </button>
                </div>
                <div class="collapse show" id="ifrsExplanation">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="ifrs-card principal">
                                <h6 class="text-info mb-2"><i class="mdi mdi-bank mr-1"></i>Principal Portion</h6>
                                <p class="small mb-0">Reduces the <strong>Lease Liability</strong> on the balance sheet. Calculated as payment minus interest.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ifrs-card interest">
                                <h6 class="text-danger mb-2"><i class="mdi mdi-trending-up mr-1"></i>Interest Portion</h6>
                                <p class="small mb-0">Recognized as <strong>Interest Expense</strong> in P&L. Effective interest method at {{ $lease->incremental_borrowing_rate }}% IBR.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="ifrs-card depreciation">
                                <h6 class="text-purple mb-2"><i class="mdi mdi-chart-line mr-1"></i>ROU Depreciation</h6>
                                <p class="small mb-0">Monthly <strong>Depreciation Expense</strong>. Straight-line over {{ $lease->lease_term_months }} months.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @else
        <div class="alert alert-success mb-4">
            <i class="mdi mdi-check-circle mr-2"></i>
            <strong>IFRS 16 Exemption:</strong> This is a {{ $lease->lease_type === 'short_term' ? 'short-term (≤12 months)' : 'low-value' }} lease.
            Payments are recognized as rent expense on a straight-line basis. No ROU asset or liability recognition required.
        </div>
        @endif

        <!-- Summary Cards -->
        @php
            $totalScheduledPayment = $schedule->sum('payment_amount');
            $totalActualPaid = $schedule->whereNotNull('payment_date')->sum(function($p) {
                return $p->actual_payment ?? $p->payment_amount;
            });
            $totalPrincipal = $schedule->sum('principal_portion');
            $totalInterest = $schedule->sum('interest_portion');
            $totalDepreciation = $schedule->sum('rou_depreciation');
            $paidCount = $schedule->whereNotNull('payment_date')->count();
            $overdueCount = $schedule->whereNull('payment_date')->where('due_date', '<', now()->toDateString())->count();
        @endphp

        <div class="row mb-4">
            <div class="col-md-2">
                <div class="summary-card bg-primary text-white">
                    <i class="mdi mdi-calendar-multiple" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h3 class="mb-0 mt-2">{{ $schedule->count() }}</h3>
                    <small>Total Payments</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-card bg-success text-white">
                    <i class="mdi mdi-check-all" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h3 class="mb-0 mt-2">{{ $paidCount }}</h3>
                    <small>Paid</small>
                </div>
            </div>
            @if($overdueCount > 0)
            <div class="col-md-2">
                <div class="summary-card bg-danger text-white">
                    <i class="mdi mdi-alert-circle" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h3 class="mb-0 mt-2">{{ $overdueCount }}</h3>
                    <small>Overdue</small>
                </div>
            </div>
            @endif
            <div class="col-md-{{ $overdueCount > 0 ? '2' : '3' }}">
                <div class="summary-card bg-warning text-dark">
                    <i class="mdi mdi-cash" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h4 class="mb-0 mt-2">₦{{ number_format($totalScheduledPayment / 1000, 0) }}K</h4>
                    <small>Total Scheduled</small>
                    @if($totalActualPaid > 0)
                    <small class="d-block text-success">Paid: ₦{{ number_format($totalActualPaid / 1000, 0) }}K</small>
                    @endif
                </div>
            </div>
            <div class="col-md-{{ $overdueCount > 0 ? '2' : '2' }}">
                <div class="summary-card bg-info text-white">
                    <i class="mdi mdi-percent" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h4 class="mb-0 mt-2">₦{{ number_format($totalInterest / 1000, 0) }}K</h4>
                    <small>Total Interest</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="summary-card" style="background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); color: white;">
                    <i class="mdi mdi-chart-line" style="font-size: 1.5rem; opacity: 0.8;"></i>
                    <h4 class="mb-0 mt-2">₦{{ number_format($totalDepreciation / 1000, 0) }}K</h4>
                    <small>Total Depreciation</small>
                </div>
            </div>
        </div>

        <!-- Payment Schedule Table -->
        <div class="card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="mdi mdi-table mr-2"></i>Full Payment Schedule</h5>
                <small class="text-muted">{{ $schedule->count() }} periods over {{ $lease->lease_term_months }} months</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover schedule-table" id="schedule-table">
                        <thead class="thead-dark">
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Due Date</th>
                                <th class="text-right">Payment</th>
                                @if(!in_array($lease->lease_type, ['short_term', 'low_value']))
                                <th class="text-right">Principal</th>
                                <th class="text-right">Interest</th>
                                <th class="text-right">Liability After</th>
                                <th class="text-right">ROU Depr.</th>
                                <th class="text-right">ROU After</th>
                                @endif
                                <th class="text-center" style="width: 100px;">Status</th>
                                <th style="width: 100px;">Paid Date</th>
                                <th class="text-center no-print" style="width: 60px;">JE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedule as $payment)
                            @php
                                $isPaid = !is_null($payment->payment_date);
                                $isOverdue = !$isPaid && $payment->due_date < now()->toDateString() && $lease->status === 'active';
                                $rowClass = $isPaid ? 'table-success' : ($isOverdue ? 'table-danger' : '');
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-center font-weight-bold">{{ $payment->payment_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                <td class="text-right">
                                    @if($isPaid && $payment->actual_payment)
                                        ₦{{ number_format($payment->actual_payment, 2) }}
                                        @if($payment->actual_payment != $payment->payment_amount)
                                            <br><small class="text-muted">(Sched: ₦{{ number_format($payment->payment_amount, 2) }})</small>
                                        @endif
                                    @else
                                        ₦{{ number_format($payment->payment_amount, 2) }}
                                    @endif
                                </td>
                                @if(!in_array($lease->lease_type, ['short_term', 'low_value']))
                                <td class="text-right text-info">₦{{ number_format($payment->principal_portion, 2) }}</td>
                                <td class="text-right text-danger">₦{{ number_format($payment->interest_portion, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->closing_liability, 2) }}</td>
                                <td class="text-right text-purple">₦{{ number_format($payment->rou_depreciation, 2) }}</td>
                                <td class="text-right">₦{{ number_format($payment->closing_rou_value, 2) }}</td>
                                @endif
                                <td class="text-center">
                                    @if($isPaid)
                                        <span class="badge badge-success status-badge"><i class="mdi mdi-check"></i> Paid</span>
                                    @elseif($isOverdue)
                                        <span class="badge badge-danger status-badge"><i class="mdi mdi-alert"></i> Overdue</span>
                                    @elseif($lease->status !== 'active')
                                        <span class="badge badge-secondary status-badge"><i class="mdi mdi-minus"></i> Cancelled</span>
                                    @else
                                        <span class="badge badge-warning status-badge"><i class="mdi mdi-clock"></i> Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($isPaid)
                                        <small>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</small>
                                    @else
                                        <small class="text-muted">-</small>
                                    @endif
                                </td>
                                <td class="text-center no-print">
                                    @if($payment->journal_entry_id)
                                        <a href="{{ route('accounting.journal-entries.show', $payment->journal_entry_id) }}"
                                           class="btn btn-sm btn-outline-primary" title="View Journal Entry">
                                            <i class="mdi mdi-book-open"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-dark">
                            <tr>
                                <th colspan="2" class="text-right">TOTALS:</th>
                                <th class="text-right">
                                    ₦{{ number_format($totalScheduledPayment, 2) }}
                                    @if($totalActualPaid > 0 && $totalActualPaid != $totalScheduledPayment)
                                    <br><small>(Paid: ₦{{ number_format($totalActualPaid, 2) }})</small>
                                    @endif
                                </th>
                                @if(!in_array($lease->lease_type, ['short_term', 'low_value']))
                                <th class="text-right">₦{{ number_format($totalPrincipal, 2) }}</th>
                                <th class="text-right">₦{{ number_format($totalInterest, 2) }}</th>
                                <th></th>
                                <th class="text-right">₦{{ number_format($totalDepreciation, 2) }}</th>
                                <th colspan="4"></th>
                                @else
                                <th colspan="3"></th>
                                @endif
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="card-modern mt-4 no-print">
            <div class="card-body py-3">
                <div class="d-flex justify-content-around align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="badge badge-success mr-2">&nbsp;&nbsp;</span>
                        <small>Paid</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-danger mr-2">&nbsp;&nbsp;</span>
                        <small>Overdue</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-warning mr-2">&nbsp;&nbsp;</span>
                        <small>Pending</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-secondary mr-2">&nbsp;&nbsp;</span>
                        <small>Cancelled</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-container .alert').fadeOut('slow');
    }, 5000);
});
</script>
@endpush
