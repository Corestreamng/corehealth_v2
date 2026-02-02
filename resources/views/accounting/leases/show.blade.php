@extends('admin.layouts.app')
@section('title', 'Lease Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Lease Details')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => '#', 'icon' => 'mdi-eye']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Lease Header -->
                <div class="card card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="mdi mdi-file-document-edit mr-2"></i>{{ $lease->lease_number }}
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
                            @endphp
                            <span class="badge {{ $typeBadges[$lease->lease_type] ?? 'badge-secondary' }}">{{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}</span>
                            <span class="badge {{ $statusBadges[$lease->status] ?? 'badge-secondary' }}">{{ ucfirst($lease->status) }}</span>
                        </h5>
                        <div>
                            @if($lease->status === 'active')
                                <a href="{{ route('accounting.leases.edit', $lease->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="mdi mdi-pencil"></i> Edit
                                </a>
                                <a href="{{ route('accounting.leases.modification', $lease->id) }}" class="btn btn-outline-warning btn-sm">
                                    <i class="mdi mdi-file-edit"></i> Modify
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#terminateModal">
                                    <i class="mdi mdi-close-circle"></i> Terminate
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-3">{{ $lease->leased_item }}</h4>
                        @if($lease->description)
                            <p class="text-muted">{{ $lease->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- Lessor & Location Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card card-modern h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="mdi mdi-account-tie mr-2"></i>Lessor Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" width="40%">Lessor Name</td>
                                        <td><strong>{{ $lease->lessor_name ?: $lease->supplier_name ?: '-' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Contact</td>
                                        <td>{{ $lease->lessor_contact ?: '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-modern h-100">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="mdi mdi-map-marker mr-2"></i>Asset Location</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td class="text-muted" width="40%">Location</td>
                                        <td><strong>{{ $lease->asset_location ?: '-' }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Department</td>
                                        <td>{{ $lease->department_name ?: '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lease Terms -->
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-calendar-range mr-2"></i>Lease Terms</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center border-right">
                                <small class="text-muted">Commencement Date</small>
                                <h5>{{ \Carbon\Carbon::parse($lease->commencement_date)->format('M d, Y') }}</h5>
                            </div>
                            <div class="col-md-4 text-center border-right">
                                <small class="text-muted">Lease Term</small>
                                <h5>{{ $lease->lease_term_months }} months</h5>
                            </div>
                            <div class="col-md-4 text-center">
                                <small class="text-muted">End Date</small>
                                <h5>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</h5>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <small class="text-muted">Monthly Payment</small>
                                <h5>₦{{ number_format($lease->monthly_payment, 2) }}</h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <small class="text-muted">Annual Escalation</small>
                                <h5>{{ $lease->annual_rent_increase_rate }}%</h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <small class="text-muted">Borrowing Rate (IBR)</small>
                                <h5>{{ $lease->incremental_borrowing_rate }}%</h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <small class="text-muted">Total Payments</small>
                                <h5>₦{{ number_format($lease->total_lease_payments, 2) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IFRS 16 Values -->
                <div class="card card-modern mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i>IFRS 16 Values</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Right-of-Use Asset</h6>
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <td>Initial Value</td>
                                        <td class="text-right">₦{{ number_format($lease->initial_rou_asset_value, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Accumulated Depreciation</td>
                                        <td class="text-right text-danger">(₦{{ number_format($lease->accumulated_rou_depreciation, 2) }})</td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong>Net Book Value</strong></td>
                                        <td class="text-right"><strong>₦{{ number_format($lease->current_rou_asset_value, 2) }}</strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted">Lease Liability</h6>
                                <table class="table table-sm table-bordered">
                                    <tr>
                                        <td>Initial Liability</td>
                                        <td class="text-right">₦{{ number_format($lease->initial_lease_liability, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Payments Made (Principal)</td>
                                        <td class="text-right text-success">(₦{{ number_format($lease->initial_lease_liability - $lease->current_lease_liability, 2) }})</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td><strong>Current Liability</strong></td>
                                        <td class="text-right"><strong>₦{{ number_format($lease->current_lease_liability, 2) }}</strong></td>
                                    </tr>
                                </table>
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
                    </div>
                </div>

                <!-- Lease Options -->
                @if($lease->has_purchase_option || $lease->has_termination_option || $lease->residual_value_guarantee > 0)
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-cog mr-2"></i>Lease Options</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($lease->has_purchase_option)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6><i class="mdi mdi-cart text-success"></i> Purchase Option</h6>
                                    <p class="mb-1">Amount: <strong>₦{{ number_format($lease->purchase_option_amount, 2) }}</strong></p>
                                    <small class="text-muted">
                                        {{ $lease->purchase_option_reasonably_certain ? '✓ Reasonably certain to exercise' : '○ Not certain to exercise' }}
                                    </small>
                                </div>
                            </div>
                            @endif
                            @if($lease->has_termination_option)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6><i class="mdi mdi-close-circle text-danger"></i> Termination Option</h6>
                                    <p class="mb-1">Earliest: <strong>{{ $lease->earliest_termination_date ? \Carbon\Carbon::parse($lease->earliest_termination_date)->format('M d, Y') : 'N/A' }}</strong></p>
                                    <small class="text-muted">Penalty: ₦{{ number_format($lease->termination_penalty ?? 0, 2) }}</small>
                                </div>
                            </div>
                            @endif
                            @if($lease->residual_value_guarantee > 0)
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100">
                                    <h6><i class="mdi mdi-shield-check text-info"></i> Residual Guarantee</h6>
                                    <p class="mb-0">Amount: <strong>₦{{ number_format($lease->residual_value_guarantee, 2) }}</strong></p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                <!-- Payment Schedule -->
                <div class="card card-modern mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-calendar-check mr-2"></i>Payment Schedule</h6>
                        <a href="{{ route('accounting.leases.schedule', $lease->id) }}" class="btn btn-outline-info btn-sm">
                            <i class="mdi mdi-table"></i> View Full Schedule
                        </a>
                    </div>
                    <div class="card-body">
                        @if($nextPayment && $lease->status === 'active')
                        <div class="alert alert-{{ $nextPayment->due_date < now()->toDateString() ? 'danger' : 'info' }} mb-3">
                            <strong><i class="mdi mdi-bell-ring"></i> Next Payment:</strong>
                            ₦{{ number_format($nextPayment->payment_amount, 2) }} due {{ \Carbon\Carbon::parse($nextPayment->due_date)->format('M d, Y') }}
                            <a href="{{ route('accounting.leases.payment', $lease->id) }}" class="btn btn-sm btn-{{ $nextPayment->due_date < now()->toDateString() ? 'danger' : 'success' }} float-right">
                                <i class="mdi mdi-cash"></i> Record Payment
                            </a>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Payment</th>
                                        <th class="text-right">Principal</th>
                                        <th class="text-right">Interest</th>
                                        <th class="text-right">ROU Depr.</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($schedule as $payment)
                                    <tr class="{{ $payment->payment_date ? 'table-success' : ($payment->due_date < now()->toDateString() ? 'table-danger' : '') }}">
                                        <td>{{ $payment->payment_number }}</td>
                                        <td>{{ \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') }}</td>
                                        <td class="text-right">₦{{ number_format($payment->payment_amount, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->principal_portion, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->interest_portion, 2) }}</td>
                                        <td class="text-right">₦{{ number_format($payment->rou_depreciation, 2) }}</td>
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
                        <p class="text-center text-muted mt-2 mb-0">
                            <a href="{{ route('accounting.leases.schedule', $lease->id) }}">View all {{ $paymentSummary->total_payments }} payments</a>
                        </p>
                        @endif
                    </div>
                </div>

                <!-- Modifications History -->
                @if($modifications->count() > 0)
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-history mr-2"></i>Modification History</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
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

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Payment Summary -->
                <div class="card card-modern mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Payment Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Payments Progress</small>
                            <div class="d-flex justify-content-between">
                                <span>{{ $paymentSummary->paid_count ?? 0 }} of {{ $paymentSummary->total_payments ?? 0 }}</span>
                                <span>{{ $paymentSummary->total_payments > 0 ? round(($paymentSummary->paid_count / $paymentSummary->total_payments) * 100) : 0 }}%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar"
                                     style="width: {{ $paymentSummary->total_payments > 0 ? round(($paymentSummary->paid_count / $paymentSummary->total_payments) * 100) : 0 }}%">
                                </div>
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
                            <tr>
                                <td colspan="2"><hr class="my-2"></td>
                            </tr>
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

                <!-- Accounting Info -->
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-book-open mr-2"></i>Accounting Mapping</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">ROU Asset</td>
                                <td class="text-right">{{ $lease->rou_account_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Lease Liability</td>
                                <td class="text-right">{{ $lease->liability_account_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Depreciation Exp.</td>
                                <td class="text-right">{{ $lease->depreciation_account_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Interest Exp.</td>
                                <td class="text-right">{{ $lease->interest_account_code ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card card-modern mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-lightning-bolt mr-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        @if($lease->status === 'active')
                        <a href="{{ route('accounting.leases.payment', $lease->id) }}" class="btn btn-success btn-block mb-2">
                            <i class="mdi mdi-cash"></i> Record Payment
                        </a>
                        <a href="{{ route('accounting.leases.modification', $lease->id) }}" class="btn btn-warning btn-block mb-2">
                            <i class="mdi mdi-file-edit"></i> Modify Lease
                        </a>
                        @endif
                        <a href="{{ route('accounting.leases.schedule', $lease->id) }}" class="btn btn-info btn-block mb-2">
                            <i class="mdi mdi-table"></i> Full Schedule
                        </a>
                        <a href="{{ route('accounting.leases.index') }}" class="btn btn-outline-secondary btn-block">
                            <i class="mdi mdi-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <!-- Audit Info -->
                <div class="card card-modern">
                    <div class="card-body">
                        <small class="text-muted">
                            Created by {{ $lease->created_by_name ?? 'Unknown' }}<br>
                            {{ \Carbon\Carbon::parse($lease->created_at)->format('M d, Y H:i') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terminate Modal -->
@if($lease->status === 'active')
<div class="modal fade" id="terminateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('accounting.leases.terminate', $lease->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="mdi mdi-alert-circle text-danger mr-2"></i>Terminate Lease</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="mdi mdi-alert"></i> This action will terminate the lease early. Remaining scheduled payments will be cancelled.
                    </div>
                    <div class="form-group">
                        <label for="termination_date">Termination Date <span class="text-danger">*</span></label>
                        <input type="date" name="termination_date" id="termination_date" class="form-control"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="termination_reason">Reason for Termination <span class="text-danger">*</span></label>
                        <textarea name="termination_reason" id="termination_reason" class="form-control" rows="3" required
                                  placeholder="Enter reason for early termination"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-close-circle"></i> Terminate Lease
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
