@extends('admin.layouts.app')
@section('title', $fund->fund_name)
@section('page_name', 'Accounting')
@section('subpage_name', 'Fund Details')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Funds', 'url' => route('accounting.petty-cash.funds.index'), 'icon' => 'mdi-wallet'],
    ['label' => $fund->fund_name, 'url' => '#', 'icon' => 'mdi-information']
]])

<div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        @endif

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">
                    <i class="mdi mdi-wallet mr-2"></i>{{ $fund->fund_name }}
                    @if($fund->status === 'active')
                        <span class="badge badge-success">Active</span>
                    @elseif($fund->status === 'suspended')
                        <span class="badge badge-warning">Suspended</span>
                    @else
                        <span class="badge badge-secondary">Closed</span>
                    @endif
                </h4>
                <p class="text-muted mb-0">
                    Code: <code>{{ $fund->fund_code }}</code> |
                    Custodian: {{ $fund->custodian?->name ?? 'N/A' }}
                </p>
            </div>
            <div class="btn-group">
                <a href="{{ route('accounting.petty-cash.disbursement.create', $fund) }}" class="btn btn-danger">
                    <i class="mdi mdi-cash-minus"></i> Disburse
                </a>
                <a href="{{ route('accounting.petty-cash.replenishment.create', $fund) }}" class="btn btn-success">
                    <i class="mdi mdi-cash-plus"></i> Replenish
                </a>
                <a href="{{ route('accounting.petty-cash.reconcile', $fund) }}" class="btn btn-info">
                    <i class="mdi mdi-scale-balance"></i> Reconcile
                </a>
                <a href="{{ route('accounting.petty-cash.funds.edit', $fund) }}" class="btn btn-outline-primary">
                    <i class="mdi mdi-pencil"></i> Edit
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-success" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash mr-1"></i> Current Balance</h5>
                    <div class="value text-success">₦{{ number_format($fund->current_balance, 2) }}</div>
                    <small class="text-muted">Fund balance</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-info" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-book-open mr-1"></i> JE Balance</h5>
                    <div class="value text-info">₦{{ number_format($stats['je_balance'], 2) }}</div>
                    <small class="text-muted">From journal entries</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-danger" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-cash-minus mr-1"></i> Total Disbursed</h5>
                    <div class="value text-danger">₦{{ number_format($stats['total_disbursements'], 2) }}</div>
                    <small class="text-muted">All time</small>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="stat-card border-left border-warning" style="border-left-width: 4px !important;">
                    <h5><i class="mdi mdi-clock-outline mr-1"></i> Pending</h5>
                    <div class="value text-warning">{{ $stats['pending_count'] }}</div>
                    <small class="text-muted">Awaiting approval</small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Fund Details -->
            <div class="col-lg-4">
                <div class="card-modern mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="mdi mdi-information mr-2"></i>Fund Details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">GL Account:</td>
                                <td><strong>{{ $fund->account?->account_number }}</strong> - {{ $fund->account?->account_name }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Department:</td>
                                <td>{{ $fund->department?->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Fund Limit:</td>
                                <td>₦{{ number_format($fund->fund_limit, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Transaction Limit:</td>
                                <td>₦{{ number_format($fund->transaction_limit, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Requires Approval:</td>
                                <td>
                                    @if($fund->requires_approval)
                                        <span class="badge badge-info">Yes</span>
                                    @else
                                        <span class="badge badge-secondary">No</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Auto-Approve Below:</td>
                                <td>₦{{ number_format($fund->approval_threshold, 2) }}</td>
                            </tr>
                        </table>

                        @if($fund->notes)
                            <hr>
                            <p class="small text-muted mb-0">{{ $fund->notes }}</p>
                        @endif
                    </div>
                </div>

                <!-- Utilization Chart -->
                <div class="card-modern mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="mdi mdi-chart-pie mr-2"></i>Fund Utilization</h6>
                    </div>
                    <div class="card-body text-center">
                        @php
                            $utilizationPct = $fund->fund_limit > 0 ? (($fund->fund_limit - $fund->current_balance) / $fund->fund_limit) * 100 : 0;
                            $availablePct = 100 - $utilizationPct;
                        @endphp
                        <div class="mb-3">
                            <canvas id="utilizationChart" height="200"></canvas>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="mb-0 text-success">₦{{ number_format($fund->current_balance, 2) }}</h5>
                                <small class="text-muted">Available ({{ number_format($availablePct, 1) }}%)</small>
                            </div>
                            <div class="col-6">
                                <h5 class="mb-0 text-danger">₦{{ number_format($fund->fund_limit - $fund->current_balance, 2) }}</h5>
                                <small class="text-muted">Used ({{ number_format($utilizationPct, 1) }}%)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Export Actions -->
                <div class="card-modern card-modern">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="mdi mdi-download mr-2"></i>Export</h6>
                    </div>
                    <div class="card-body">
                        <a href="{{ route('accounting.petty-cash.export.pdf', $fund) }}" class="btn btn-danger btn-block mb-2">
                            <i class="mdi mdi-file-pdf"></i> Export PDF
                        </a>
                        <a href="{{ route('accounting.petty-cash.export.excel', $fund) }}" class="btn btn-success btn-block">
                            <i class="mdi mdi-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Transactions -->
            <div class="col-lg-8">
                <div class="card-modern card-modern">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-history mr-2"></i>Recent Transactions</h6>
                        <a href="{{ route('accounting.petty-cash.transactions.index', $fund) }}" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body">
                        @if($recentTransactions->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Voucher #</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th class="text-right">Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentTransactions as $transaction)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}</td>
                                                <td><code>{{ $transaction->voucher_number }}</code></td>
                                                <td>
                                                    @if($transaction->transaction_type === 'disbursement')
                                                        <span class="badge badge-danger">Disbursement</span>
                                                    @elseif($transaction->transaction_type === 'replenishment')
                                                        <span class="badge badge-success">Replenishment</span>
                                                    @else
                                                        <span class="badge badge-warning">Adjustment</span>
                                                    @endif
                                                </td>
                                                <td>{{ Str::limit($transaction->description, 30) }}</td>
                                                <td class="text-right">₦{{ number_format($transaction->amount, 2) }}</td>
                                                <td>
                                                    @if($transaction->status === 'pending')
                                                        <span class="badge badge-warning">Pending</span>
                                                    @elseif($transaction->status === 'approved')
                                                        <span class="badge badge-success">Approved</span>
                                                    @elseif($transaction->status === 'rejected')
                                                        <span class="badge badge-danger">Rejected</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ ucfirst($transaction->status) }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($transaction->status === 'pending')
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-success approve-btn" data-id="{{ $transaction->id }}" title="Approve">
                                                                <i class="mdi mdi-check"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger reject-btn" data-id="{{ $transaction->id }}" title="Reject">
                                                                <i class="mdi mdi-close"></i>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <i class="mdi mdi-cash-remove mdi-48px text-muted"></i>
                                <p class="text-muted mt-2">No transactions yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Transaction</h5>
                <button type="button" class="close"  data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" id="reject-transaction-id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection-reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.stat-card h5 {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 8px;
}
.stat-card .value {
    font-size: 1.75rem;
    font-weight: 600;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Utilization Chart
    var ctx = document.getElementById('utilizationChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Used'],
            datasets: [{
                data: [{{ $availablePct }}, {{ $utilizationPct }}],
                backgroundColor: ['#28a745', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '60%'
        }
    });

    // Approve transaction
    $('.approve-btn').click(function() {
        var id = $(this).data('id');
        if (confirm('Are you sure you want to approve this transaction?')) {
            $.ajax({
                url: "{{ route('accounting.petty-cash.transactions.approve', '') }}/" + id,
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        location.reload();
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'An error occurred');
                }
            });
        }
    });

    // Reject transaction
    var rejectId = null;
    $('.reject-btn').click(function() {
        rejectId = $(this).data('id');
        $('#rejection-reason').val('');
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: "{{ route('accounting.petty-cash.transactions.reject', '') }}/" + rejectId,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                rejection_reason: $('#rejection-reason').val()
            },
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#rejectModal').modal('hide');
                    location.reload();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });
});
</script>
@endpush
