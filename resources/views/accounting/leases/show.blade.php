@extends('admin.layouts.app')
@section('title', 'Lease Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Lease Details')

@push('styles')
<style>
    .lease-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
    }
    .value-card {
        border-left: 4px solid;
        transition: transform 0.2s;
    }
    .value-card:hover {
        transform: translateX(3px);
    }
    .value-card.asset { border-left-color: #0dcaf0; }
    .value-card.liability { border-left-color: #ffc107; }
    .value-card.expense { border-left-color: #dc3545; }
    .schedule-row-paid { background-color: rgba(25, 135, 84, 0.1) !important; }
    .schedule-row-overdue { background-color: rgba(220, 53, 69, 0.1) !important; }
    .je-preview-code {
        background: #f8f9fa;
        border-left: 3px solid #0d6efd;
        padding: 0.75rem;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => '#', 'icon' => 'mdi-eye']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        {{-- Alert Boxes --}}
        <div id="alert-container">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert mr-2"></i>{{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
            @if(session('info'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-information mr-2"></i>{{ session('info') }}
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            @endif
        </div>

        @php
            $typeBadges = [
                'operating' => 'badge-secondary',
                'finance' => 'badge-primary',
                'short_term' => 'badge-info',
                'low_value' => 'badge-light',
            ];
            $statusBadges = [
                'draft' => 'badge-secondary',
                'active' => 'badge-success',
                'expired' => 'badge-dark',
                'terminated' => 'badge-danger',
                'purchased' => 'badge-info',
            ];
            $isExempt = in_array($lease->lease_type, ['short_term', 'low_value']);
        @endphp

        <div class="row">
            {{-- Main Content --}}
            <div class="col-lg-8">
                {{-- Lease Header Card --}}
                <div class="card-modern mb-4">
                    <div class="lease-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-2">
                                    {{ $lease->lease_number }}
                                    <span class="badge {{ $typeBadges[$lease->lease_type] ?? 'badge-secondary' }} ml-2">
                                        {{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}
                                    </span>
                                    <span class="badge {{ $statusBadges[$lease->status] ?? 'badge-secondary' }}">
                                        {{ ucfirst($lease->status) }}
                                    </span>
                                </h4>
                                <h5 class="mb-1 font-weight-normal">{{ $lease->leased_item }}</h5>
                                @if($lease->description)
                                    <p class="mb-0 small opacity-75">{{ $lease->description }}</p>
                                @endif
                            </div>
                            @if($lease->status === 'active')
                            <div class="btn-group">
                                <a href="{{ route('accounting.leases.edit', $lease->id) }}" class="btn btn-light btn-sm">
                                    <i class="mdi mdi-pencil"></i>
                                </a>
                                <a href="{{ route('accounting.leases.modification', $lease->id) }}" class="btn btn-light btn-sm">
                                    <i class="mdi mdi-file-edit"></i>
                                </a>
                                <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#terminateModal">
                                    <i class="mdi mdi-close-circle text-danger"></i>
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><i class="mdi mdi-account-tie mr-1"></i>Lessor</h6>
                                <p class="mb-0"><strong>{{ $lease->lessor_name ?: $lease->supplier_name ?: '-' }}</strong></p>
                                <small class="text-muted">{{ $lease->lessor_contact ?: '-' }}</small>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2"><i class="mdi mdi-map-marker mr-1"></i>Location</h6>
                                <p class="mb-0"><strong>{{ $lease->asset_location ?: '-' }}</strong></p>
                                <small class="text-muted">{{ $lease->department_name ?: '-' }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Lease Terms --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-calendar-range mr-2"></i>Lease Terms</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 border-right">
                                <small class="text-muted text-uppercase">Commencement</small>
                                <h5 class="mb-0">{{ \Carbon\Carbon::parse($lease->commencement_date)->format('M d, Y') }}</h5>
                            </div>
                            <div class="col-md-4 border-right">
                                <small class="text-muted text-uppercase">Term</small>
                                <h5 class="mb-0">{{ $lease->lease_term_months }} months</h5>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted text-uppercase">End Date</small>
                                <h5 class="mb-0">{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</h5>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <small class="text-muted">Monthly Payment</small>
                                <h5 class="text-primary mb-0">₦{{ number_format($lease->monthly_payment, 2) }}</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Escalation</small>
                                <h5 class="mb-0">{{ $lease->annual_rent_increase_rate }}%</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Discount Rate (IBR)</small>
                                <h5 class="mb-0">{{ $lease->incremental_borrowing_rate }}%</h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted">Total Payments</small>
                                <h5 class="mb-0">₦{{ number_format($lease->total_lease_payments, 2) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- IFRS 16 Values --}}
                <div class="card-modern mb-4">
                    <div class="card-header bg-{{ $isExempt ? 'success' : 'primary' }} text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i>IFRS 16 Recognition</h6>
                        <span class="badge badge-light">
                            @if($isExempt)
                                <i class="mdi mdi-check-circle"></i> Exemption Applied
                            @else
                                <i class="mdi mdi-information-outline"></i> Full Recognition
                            @endif
                        </span>
                    </div>

                    @if($isExempt)
                    <div class="card-body bg-light border-bottom">
                        <div class="alert alert-success mb-0">
                            <h6 class="alert-heading"><i class="mdi mdi-check-circle mr-1"></i>IFRS 16 Exemption</h6>
                            <p class="mb-2">This <strong>{{ $lease->lease_type === 'short_term' ? 'Short-Term' : 'Low-Value' }}</strong> lease is exempt from balance sheet recognition.</p>
                            <hr class="my-2">
                            <p class="mb-0 small">
                                <strong>Treatment:</strong> Payments are expensed as <strong>Rent Expense</strong> on a straight-line basis.
                                No ROU Asset or Lease Liability is recognized.
                            </p>
                        </div>
                    </div>
                    @endif

                    <div class="card-body">
                        @if(!$isExempt)
                        <div class="row">
                            <div class="col-md-6">
                                <div class="value-card asset bg-white p-3 rounded shadow-sm h-100">
                                    <h6 class="text-info mb-3"><i class="mdi mdi-office-building mr-1"></i>Right-of-Use Asset</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td>Initial Value</td>
                                            <td class="text-right">₦{{ number_format($lease->initial_rou_asset_value, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Accumulated Depreciation</td>
                                            <td class="text-right text-danger">(₦{{ number_format($lease->accumulated_rou_depreciation, 2) }})</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td><strong>Net Book Value</strong></td>
                                            <td class="text-right"><strong class="text-info">₦{{ number_format($lease->current_rou_asset_value, 2) }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="value-card liability bg-white p-3 rounded shadow-sm h-100">
                                    <h6 class="text-warning mb-3"><i class="mdi mdi-scale-balance mr-1"></i>Lease Liability</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td>Initial Liability</td>
                                            <td class="text-right">₦{{ number_format($lease->initial_lease_liability, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Principal Paid</td>
                                            <td class="text-right text-success">(₦{{ number_format($lease->initial_lease_liability - $lease->current_lease_liability, 2) }})</td>
                                        </tr>
                                        <tr class="border-top">
                                            <td><strong>Current Balance</strong></td>
                                            <td class="text-right"><strong class="text-warning">₦{{ number_format($lease->current_lease_liability, 2) }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @if($lease->initial_direct_costs > 0 || $lease->lease_incentives_received > 0)
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Initial Direct Costs</small>
                                <p class="mb-0">₦{{ number_format($lease->initial_direct_costs, 2) }}</p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Lease Incentives Received</small>
                                <p class="mb-0">₦{{ number_format($lease->lease_incentives_received, 2) }}</p>
                            </div>
                        </div>
                        @endif
                        @else
                        <div class="row text-center">
                            <div class="col-md-4">
                                <small class="text-muted">Monthly Expense</small>
                                <h5 class="mb-0">₦{{ number_format($lease->monthly_payment, 2) }}</h5>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Total Paid</small>
                                <h5 class="text-success mb-0">₦{{ number_format($paymentSummary->total_paid ?? 0, 2) }}</h5>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Remaining</small>
                                <h5 class="text-warning mb-0">₦{{ number_format(($paymentSummary->total_scheduled ?? 0) - ($paymentSummary->total_paid ?? 0), 2) }}</h5>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Journal Entries --}}
                <div class="card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-book-open-variant mr-2"></i>Journal Entries</h6>
                        @if(isset($journalEntries) && $journalEntries->count() > 0)
                        <a href="{{ route('accounting.journal-entries.index', ['reference_type' => 'lease', 'reference_id' => $lease->id]) }}" class="btn btn-outline-primary btn-sm">
                            View All ({{ $journalEntries->count() }})
                        </a>
                        @endif
                    </div>
                    <div class="card-body">
                        {{-- JE Pattern Explanation --}}
                        @if(!$isExempt)
                        <div class="alert alert-info small mb-3">
                            <strong><i class="mdi mdi-information-outline mr-1"></i>IFRS 16 Journal Entry Pattern:</strong>
                            <div class="row mt-2">
                                <div class="col-md-4">
                                    <p class="mb-1 font-weight-bold">1. Initial Recognition</p>
                                    <div class="je-preview-code">
                                        DR ROU Asset<br>
                                        &nbsp;&nbsp;&nbsp;CR Lease Liability
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1 font-weight-bold">2. Each Payment</p>
                                    <div class="je-preview-code">
                                        DR Lease Liability<br>
                                        DR Interest Expense<br>
                                        &nbsp;&nbsp;&nbsp;CR Bank/Cash
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <p class="mb-1 font-weight-bold">3. Monthly Depreciation</p>
                                    <div class="je-preview-code">
                                        DR Depreciation Exp<br>
                                        &nbsp;&nbsp;&nbsp;CR Accum. Depr (ROU)
                                    </div>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="alert alert-success small mb-3">
                            <strong><i class="mdi mdi-check-circle mr-1"></i>Simplified Accounting (Exempt):</strong>
                            <div class="je-preview-code mt-2">
                                DR Rent Expense ₦{{ number_format($lease->monthly_payment, 2) }}<br>
                                &nbsp;&nbsp;&nbsp;CR Bank/Cash ₦{{ number_format($lease->monthly_payment, 2) }}
                            </div>
                        </div>
                        @endif

                        {{-- Recent JE List --}}
                        @if(isset($journalEntries) && $journalEntries->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Entry #</th>
                                        <th>Description</th>
                                        <th class="text-right">Amount</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($journalEntries->take(5) as $je)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($je->entry_date)->format('M d, Y') }}</td>
                                        <td><span class="badge badge-secondary">{{ $je->entry_number }}</span></td>
                                        <td>{{ \Illuminate\Support\Str::limit($je->description, 35) }}</td>
                                        <td class="text-right">₦{{ number_format($je->total_debit, 2) }}</td>
                                        <td>
                                            <a href="{{ route('accounting.journal-entries.show', $je->id) }}" class="btn btn-sm btn-outline-info" title="View">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center text-muted py-3">
                            <i class="mdi mdi-book-open-blank-variant mdi-36px"></i>
                            <p class="mb-0 mt-2">No journal entries recorded yet.
                                @if($lease->status === 'draft')
                                    <br><small>Activate the lease to create initial recognition.</small>
                                @endif
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Lease Options --}}
                @if($lease->has_purchase_option || $lease->has_termination_option || $lease->residual_value_guarantee > 0)
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-cog mr-2"></i>Lease Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($lease->has_purchase_option)
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-success"><i class="mdi mdi-cart mr-1"></i>Purchase Option</h6>
                                    <p class="mb-1">Amount: <strong>₦{{ number_format($lease->purchase_option_amount, 2) }}</strong></p>
                                    <small class="{{ $lease->purchase_option_reasonably_certain ? 'text-success' : 'text-muted' }}">
                                        <i class="mdi mdi-{{ $lease->purchase_option_reasonably_certain ? 'check-circle' : 'circle-outline' }}"></i>
                                        {{ $lease->purchase_option_reasonably_certain ? 'Reasonably certain' : 'Not certain' }}
                                    </small>
                                </div>
                            </div>
                            @endif
                            @if($lease->has_termination_option)
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-danger"><i class="mdi mdi-close-circle mr-1"></i>Termination Option</h6>
                                    <p class="mb-1">Earliest: <strong>{{ $lease->earliest_termination_date ? \Carbon\Carbon::parse($lease->earliest_termination_date)->format('M d, Y') : 'N/A' }}</strong></p>
                                    <small class="text-muted">Penalty: ₦{{ number_format($lease->termination_penalty ?? 0, 2) }}</small>
                                </div>
                            </div>
                            @endif
                            @if($lease->residual_value_guarantee > 0)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-info"><i class="mdi mdi-shield-check mr-1"></i>Residual Guarantee</h6>
                                    <p class="mb-0">Amount: <strong>₦{{ number_format($lease->residual_value_guarantee, 2) }}</strong></p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Payment Schedule --}}
                <div class="card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-calendar-check mr-2"></i>Payment Schedule</h6>
                        <a href="{{ route('accounting.leases.schedule', $lease->id) }}" class="btn btn-outline-info btn-sm">
                            <i class="mdi mdi-table"></i> Full Schedule
                        </a>
                    </div>
                    <div class="card-body">
                        @if($nextPayment && $lease->status === 'active')
                        <div class="alert alert-{{ $nextPayment->due_date < now()->toDateString() ? 'danger' : 'info' }} d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><i class="mdi mdi-bell-ring mr-1"></i>Next Payment:</strong>
                                ₦{{ number_format($nextPayment->payment_amount, 2) }} due {{ \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') }}
                            </div>
                            <a href="{{ route('accounting.leases.payment', $lease->id) }}" class="btn btn-sm btn-{{ $nextPayment->due_date < now()->toDateString() ? 'danger' : 'success' }}">
                                <i class="mdi mdi-cash"></i> Record Payment
                            </a>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Payment</th>
                                        <th class="text-right">Principal</th>
                                        <th class="text-right">Interest</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schedule as $payment)
                                    <tr class="{{ $payment->payment_date ? 'schedule-row-paid' : ($payment->due_date < now()->toDateString() ? 'schedule-row-overdue' : '') }}">
                                        <td>{{ $payment->payment_number }}</td>
                                        <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                        <td class="text-right">
                                            @if($payment->payment_date && $payment->actual_payment)
                                                ₦{{ number_format($payment->actual_payment, 2) }}
                                                @if($payment->actual_payment != $payment->payment_amount)
                                                    <br><small class="text-muted">(Scheduled: ₦{{ number_format($payment->payment_amount, 2) }})</small>
                                                @endif
                                            @else
                                                ₦{{ number_format($payment->payment_amount, 2) }}
                                            @endif
                                        </td>
                                        <td class="text-right">₦{{ number_format($payment->principal_portion, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->interest_portion, 2) }}</td>
                                        <td class="text-center">
                                            @if($payment->payment_date)
                                                <span class="badge badge-success"><i class="mdi mdi-check"></i> Paid</span>
                                            @elseif($payment->due_date < now()->toDateString())
                                                <span class="badge badge-danger"><i class="mdi mdi-alert"></i> Overdue</span>
                                            @else
                                                <span class="badge badge-warning"><i class="mdi mdi-clock"></i> Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($schedule->count() >= 12)
                        <p class="text-center text-muted mt-2 mb-0 small">
                            <a href="{{ route('accounting.leases.schedule', $lease->id) }}">View all {{ $paymentSummary->total_payments ?? 0 }} payments →</a>
                        </p>
                        @endif
                    </div>
                </div>

                {{-- Modifications --}}
                @if(isset($modifications) && $modifications->count() > 0)
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-history mr-2"></i>Modification History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-right">Adjustment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($modifications as $mod)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($mod->modification_date)->format('M d, Y') }}</td>
                                        <td><span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $mod->modification_type)) }}</span></td>
                                        <td>{{ $mod->description }}</td>
                                        <td class="text-right {{ $mod->adjustment_amount >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $mod->adjustment_amount >= 0 ? '+' : '' }}₦{{ number_format($mod->adjustment_amount, 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- Payment Summary --}}
                <div class="card-modern mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Payment Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Progress</span>
                                <span>{{ $paymentSummary->paid_count ?? 0 }} / {{ $paymentSummary->total_payments ?? 0 }} ({{ $paymentSummary->total_payments > 0 ? round(($paymentSummary->paid_count / $paymentSummary->total_payments) * 100) : 0 }}%)</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: {{ $paymentSummary->total_payments > 0 ? round(($paymentSummary->paid_count / $paymentSummary->total_payments) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Total Scheduled</td>
                                <td class="text-right">₦{{ number_format($paymentSummary->total_scheduled ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Paid</td>
                                <td class="text-right text-success">₦{{ number_format($paymentSummary->total_paid ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Remaining</td>
                                <td class="text-right text-warning">₦{{ number_format(($paymentSummary->total_scheduled ?? 0) - ($paymentSummary->total_paid ?? 0), 2) }}</td>
                            </tr>
                            <tr><td colspan="2"><hr class="my-2"></td></tr>
                            <tr>
                                <td class="text-muted">Total Principal</td>
                                <td class="text-right">₦{{ number_format($paymentSummary->total_principal ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Interest</td>
                                <td class="text-right">₦{{ number_format($paymentSummary->total_interest ?? 0, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                {{-- Accounting Mapping --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-book-open mr-2"></i>GL Account Mapping</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">ROU Asset</td>
                                <td class="text-right"><span class="badge badge-info">{{ $lease->rou_account_code ?? '-' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Lease Liability</td>
                                <td class="text-right"><span class="badge badge-warning text-dark">{{ $lease->liability_account_code ?? '-' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Depreciation</td>
                                <td class="text-right"><span class="badge badge-danger">{{ $lease->depreciation_account_code ?? '-' }}</span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Interest</td>
                                <td class="text-right"><span class="badge badge-danger">{{ $lease->interest_account_code ?? '-' }}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        @if($lease->status === 'active')
                        <a href="{{ route('accounting.leases.payment', $lease->id) }}" class="btn btn-success btn-block mb-2">
                            <i class="mdi mdi-cash mr-1"></i> Record Payment
                        </a>
                        <a href="{{ route('accounting.leases.modification', $lease->id) }}" class="btn btn-warning btn-block mb-2">
                            <i class="mdi mdi-file-edit mr-1"></i> Modify Lease
                        </a>
                        @endif
                        <a href="{{ route('accounting.leases.schedule', $lease->id) }}" class="btn btn-info btn-block mb-2">
                            <i class="mdi mdi-table mr-1"></i> Full Schedule
                        </a>
                        <hr>
                        <p class="text-muted small mb-2"><i class="mdi mdi-download mr-1"></i> Export Options</p>
                        <div class="btn-group btn-group-sm btn-block mb-2">
                            <a href="{{ route('accounting.leases.detail.export.pdf', $lease->id) }}" class="btn btn-outline-danger">
                                <i class="mdi mdi-file-pdf-box mr-1"></i> PDF
                            </a>
                            <a href="{{ route('accounting.leases.detail.export.excel', $lease->id) }}" class="btn btn-outline-success">
                                <i class="mdi mdi-file-excel mr-1"></i> Excel
                            </a>
                        </div>
                        <a href="{{ route('accounting.leases.index') }}" class="btn btn-outline-secondary btn-block">
                            <i class="mdi mdi-arrow-left mr-1"></i> Back to Leases
                        </a>
                    </div>
                </div>

                {{-- Audit Info --}}
                <div class="card-modern card-modern">
                    <div class="card-body py-2">
                        <small class="text-muted">
                            Created by <strong>{{ $lease->created_by_name ?? 'System' }}</strong><br>
                            {{ \Carbon\Carbon::parse($lease->created_at)->format('M d, Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Terminate Modal --}}
@if($lease->status === 'active')
<div class="modal fade" id="terminateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('accounting.leases.terminate', $lease->id) }}" method="POST">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="mdi mdi-alert-circle mr-2"></i>Terminate Lease</h5>
                    <button type="button" class="close text-white" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert mr-1"></i>
                        This will terminate the lease early. Remaining payments will be cancelled.
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Termination Date <span class="text-danger">*</span></label>
                        <input type="date" name="termination_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
                        <textarea name="termination_reason" class="form-control" rows="3" required placeholder="Enter reason for early termination"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="mdi mdi-close-circle mr-1"></i> Terminate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').fadeOut('slow');
    }, 5000);
});
</script>
@endpush
