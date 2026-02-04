@extends('admin.layouts.app')
@section('title', 'Liability Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Liability Details')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Liabilities', 'url' => route('accounting.liabilities.index'), 'icon' => 'mdi-credit-card-clock'],
    ['label' => $liability->liability_number, 'url' => '#', 'icon' => 'mdi-eye']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        {{-- Success/Error Messages --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="mdi mdi-check-circle mr-2"></i>
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="mdi mdi-alert-circle mr-2"></i>
            <strong>Error:</strong> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        @endif

        <div class="row">
            <div class="col-lg-8">
                <!-- Main Info Card -->
                <div class="card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="mdi mdi-credit-card-clock mr-2"></i>{{ $liability->liability_number }}
                                @php
                                    $statusColors = [
                                        'active' => 'success',
                                        'paid_off' => 'info',
                                        'restructured' => 'warning',
                                        'defaulted' => 'danger',
                                        'cancelled' => 'secondary',
                                    ];
                                    $typeColors = [
                                        'loan' => 'primary',
                                        'mortgage' => 'info',
                                        'bond' => 'dark',
                                        'deferred_revenue' => 'warning',
                                        'other' => 'secondary',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $typeColors[$liability->liability_type] ?? 'secondary' }} ml-2">
                                    {{ ucfirst(str_replace('_', ' ', $liability->liability_type)) }}
                                </span>
                                <span class="badge badge-{{ $statusColors[$liability->status] ?? 'secondary' }} ml-1">
                                    {{ ucfirst(str_replace('_', ' ', $liability->status)) }}
                                </span>
                            </h5>
                        </div>
                        <div>
                            @if($liability->status == 'active')
                            <a href="{{ route('accounting.liabilities.payment', $liability->id) }}" class="btn btn-success btn-sm">
                                <i class="mdi mdi-cash-plus"></i> Record Payment
                            </a>
                            @endif
                            <a href="{{ route('accounting.liabilities.edit', $liability->id) }}" class="btn btn-warning btn-sm">
                                <i class="mdi mdi-pencil"></i> Edit
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Creditor Information</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Creditor Name</strong></td>
                                        <td>{{ $liability->creditor_name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Contact</strong></td>
                                        <td>{{ $liability->creditor_contact ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reference</strong></td>
                                        <td>{{ $liability->reference_number ?? '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Loan Terms</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Start Date</strong></td>
                                        <td>{{ \Carbon\Carbon::parse($liability->start_date)->format('M d, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Maturity Date</strong></td>
                                        <td>{{ \Carbon\Carbon::parse($liability->maturity_date)->format('M d, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Term</strong></td>
                                        <td>{{ $liability->term_months }} months</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Payment Frequency</strong></td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $liability->payment_frequency)) }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Financial Details</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Principal Amount</strong></td>
                                        <td class="text-right">₦{{ number_format($liability->principal_amount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Interest Rate</strong></td>
                                        <td class="text-right">{{ $liability->interest_rate }}% ({{ ucfirst($liability->interest_type) }})</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Regular Payment</strong></td>
                                        <td class="text-right">₦{{ number_format($liability->regular_payment_amount, 2) }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Balance Summary</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td width="40%"><strong>Current Balance</strong></td>
                                        <td class="text-right text-danger font-weight-bold">₦{{ number_format($liability->current_balance, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current Portion</strong></td>
                                        <td class="text-right">₦{{ number_format($liability->current_portion, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Non-Current Portion</strong></td>
                                        <td class="text-right">₦{{ number_format($liability->non_current_portion, 2) }}</td>
                                    </tr>
                                    @if($liability->next_payment_date)
                                    <tr>
                                        <td><strong>Next Payment</strong></td>
                                        <td class="text-right">{{ \Carbon\Carbon::parse($liability->next_payment_date)->format('M d, Y') }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                        </div>

                        @if($liability->collateral_description)
                        <hr>
                        <h6 class="text-muted">Collateral</h6>
                        <p class="mb-1">{{ $liability->collateral_description }}</p>
                        @if($liability->collateral_value)
                        <small class="text-muted">Value: ₦{{ number_format($liability->collateral_value, 2) }}</small>
                        @endif
                        @endif

                        @if($liability->notes)
                        <hr>
                        <h6 class="text-muted">Notes</h6>
                        <p class="mb-0">{{ $liability->notes }}</p>
                        @endif
                    </div>
                </div>

                <!-- Payment Schedule -->
                <div class="card-modern card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="mdi mdi-calendar-text mr-2"></i>Payment Schedule</h5>
                        <a href="{{ route('accounting.liabilities.schedule', $liability->id) }}" class="btn btn-outline-primary btn-sm">
                            <i class="mdi mdi-table"></i> Full Amortization
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Payment</th>
                                        <th class="text-right">Principal</th>
                                        <th class="text-right">Interest</th>
                                        <th class="text-right">Balance</th>
                                        <th>Status</th>
                                        <th>JE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($paymentSchedule->take(12) as $payment)
                                    <tr class="{{ $payment->payment_date ? 'table-success' : ($payment->due_date < now()->toDateString() ? 'table-danger' : '') }}">
                                        <td>{{ $payment->payment_number }}</td>
                                        <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                        <td class="text-right">₦{{ number_format($payment->scheduled_payment, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->principal_portion, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->interest_portion, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->closing_balance, 2) }}</td>
                                        <td>
                                            @if($payment->payment_date)
                                                <span class="badge badge-success">
                                                    <i class="mdi mdi-check"></i> Paid {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d') }}
                                                </span>
                                            @elseif($payment->due_date < now()->toDateString())
                                                <span class="badge badge-danger">
                                                    <i class="mdi mdi-alert"></i> Overdue
                                                </span>
                                            @else
                                                <span class="badge badge-warning">
                                                    <i class="mdi mdi-clock"></i> Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($payment->journal_entry_id)
                                                <a href="{{ route('accounting.journal-entries.show', $payment->journal_entry_id) }}"
                                                   class="btn btn-sm btn-link text-primary p-0" title="View Journal Entry">
                                                    <i class="mdi mdi-book-open-page-variant"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No payment schedule found</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($paymentSchedule->count() > 12)
                        <div class="text-center mt-3">
                            <a href="{{ route('accounting.liabilities.schedule', $liability->id) }}" class="btn btn-link">
                                View all {{ $paymentSchedule->count() }} payments →
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Summary Card -->
                <div class="card-modern mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="mdi mdi-chart-donut mr-2"></i>Payment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-success">{{ $paidCount }}</h3>
                                    <small class="text-muted">Paid</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 bg-light rounded">
                                    <h3 class="mb-0 text-warning">{{ $pendingCount }}</h3>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                        @if($overdueCount > 0)
                        <div class="alert alert-danger mb-3">
                            <i class="mdi mdi-alert-circle mr-1"></i>
                            <strong>{{ $overdueCount }}</strong> payment(s) overdue!
                        </div>
                        @endif
                        <div class="mb-2">
                            <small class="text-muted">Total Paid</small>
                            <h5 class="mb-0 text-success">₦{{ number_format($totalPaid, 2) }}</h5>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Remaining Balance</small>
                            <h5 class="mb-0 text-danger">₦{{ number_format($liability->current_balance, 2) }}</h5>
                        </div>
                        @php
                            $progress = $liability->principal_amount > 0
                                ? (($liability->principal_amount - $liability->current_balance) / $liability->principal_amount) * 100
                                : 0;
                        @endphp
                        <div class="progress mt-3" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar"
                                 style="width: {{ $progress }}%">
                                {{ round($progress) }}% Paid
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Accounting Info -->
                <div class="card-modern mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-book-open mr-2"></i>Accounting</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Liability Account</strong></p>
                        <p class="text-muted mb-3">{{ $liability->account_code }} - {{ $liability->account_name }}</p>

                        <p class="mb-1"><strong>Interest Expense Account</strong></p>
                        <p class="text-muted mb-3">{{ $liability->interest_account_code }} - {{ $liability->interest_account_name }}</p>

                        @if($liability->journal_entry_id)
                        <hr>
                        <p class="mb-1"><strong>Initial Booking JE</strong></p>
                        <a href="{{ route('accounting.journal-entries.show', $liability->journal_entry_id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-book-open-page-variant mr-1"></i>
                            View Journal Entry
                        </a>
                        <small class="text-muted d-block mt-1">
                            Created when liability was recorded
                        </small>
                        @endif
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card-modern card-modern">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            @if($liability->status == 'active')
                            <a href="{{ route('accounting.liabilities.payment', $liability->id) }}" class="btn btn-success btn-block mb-2">
                                <i class="mdi mdi-cash-plus"></i> Record Payment
                            </a>
                            @endif
                            <a href="{{ route('accounting.liabilities.schedule', $liability->id) }}" class="btn btn-info btn-block mb-2">
                                <i class="mdi mdi-table"></i> View Full Schedule
                            </a>
                            <a href="{{ route('accounting.liabilities.edit', $liability->id) }}" class="btn btn-warning btn-block mb-2">
                                <i class="mdi mdi-pencil"></i> Edit Details
                            </a>
                            <a href="{{ route('accounting.liabilities.index') }}" class="btn btn-outline-secondary btn-block">
                                <i class="mdi mdi-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
