@extends('admin.layouts.app')
@section('title', 'Reconcile Fund')
@section('page_name', 'Accounting')
@section('subpage_name', 'Petty Cash Reconciliation')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Petty Cash', 'url' => route('accounting.petty-cash.index'), 'icon' => 'mdi-cash-register'],
    ['label' => 'Funds', 'url' => route('accounting.petty-cash.funds.index'), 'icon' => 'mdi-wallet'],
    ['label' => $fund->fund_name, 'url' => route('accounting.petty-cash.funds.show', $fund), 'icon' => 'mdi-wallet'],
    ['label' => 'Reconcile', 'url' => '#', 'icon' => 'mdi-scale-balance']
]])

<div class="container-fluid">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">
        <!-- Reconciliation Form -->
        <div class="col-lg-6">
            <div class="card-modern mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="mdi mdi-scale-balance mr-2"></i>Fund Reconciliation</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('accounting.petty-cash.reconcile.store', $fund) }}" method="POST" id="reconcileForm">
                        @csrf

                        <!-- Fund Info -->
                        <div class="alert alert-light border mb-4">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Fund</small>
                                    <strong>{{ $fund->fund_name }}</strong>
                                    <span class="text-muted">({{ $fund->fund_code }})</span>
                                </div>
                                <div class="col-6 text-right">
                                    <small class="text-muted d-block">Custodian</small>
                                    <strong>{{ $fund->custodian?->name ?? 'N/A' }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Comparison -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="border rounded p-3 text-center bg-light">
                                    <small class="text-muted d-block">Book Balance (From JE)</small>
                                    <h4 class="text-info mb-0">₦{{ number_format($jeBalance, 2) }}</h4>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 text-center bg-light">
                                    <small class="text-muted d-block">Pending Disbursements</small>
                                    <h4 class="text-warning mb-0">₦{{ number_format($pendingDisbursements, 2) }}</h4>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded p-3 text-center mb-4 bg-primary text-white">
                            <small class="d-block">Expected Physical Count</small>
                            <h3 class="mb-0">₦{{ number_format($jeBalance - $pendingDisbursements, 2) }}</h3>
                            <small>(Book Balance - Pending Disbursements)</small>
                        </div>

                        <!-- Reconciliation Date -->
                        <div class="form-group">
                            <label for="reconciliation_date">Reconciliation Date <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control @error('reconciliation_date') is-invalid @enderror"
                                   id="reconciliation_date"
                                   name="reconciliation_date"
                                   value="{{ old('reconciliation_date', date('Y-m-d')) }}"
                                   required>
                            @error('reconciliation_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Physical Count -->
                        <div class="form-group">
                            <label for="physical_count">Physical Cash Count <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">₦</span>
                                </div>
                                <input type="number"
                                       class="form-control @error('physical_count') is-invalid @enderror"
                                       id="physical_count"
                                       name="physical_count"
                                       value="{{ old('physical_count') }}"
                                       step="0.01"
                                       min="0"
                                       placeholder="Enter actual cash on hand"
                                       required>
                                @error('physical_count')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-muted">Count all cash in the petty cash box</small>
                        </div>

                        <!-- Variance Display -->
                        <div class="form-group">
                            <label>Variance</label>
                            <div class="border rounded p-3 text-center" id="varianceDisplay">
                                <span class="text-muted">Enter physical count to calculate variance</span>
                            </div>
                        </div>

                        <!-- Approval Notice -->
                        <div class="alert alert-info" id="approvalNotice" style="display: none;">
                            <i class="mdi mdi-information"></i>
                            <strong>Note:</strong> Reconciliations with variances require approval before adjustment journal entries are created.
                        </div>

                        <!-- Notes -->
                        <div class="form-group">
                            <label for="notes">Notes <span class="text-danger" id="notesRequired" style="display: none;">*</span></label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes"
                                      name="notes"
                                      rows="3"
                                      placeholder="Explain any discrepancies...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accounting.petty-cash.funds.show', $fund) }}" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-info">
                                <i class="mdi mdi-check"></i> <span id="submitText">Complete Reconciliation</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Pending Transactions -->
        <div class="col-lg-6">
            <div class="card-modern mb-4">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="mdi mdi-clock-outline mr-2"></i>Approved (Awaiting Disbursement)</h6>
                </div>
                <div class="card-body p-0">
                    @if($fund->transactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($fund->transactions as $transaction)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('M d, Y') }}</td>
                                            <td>{{ Str::limit($transaction->description, 30) }}</td>
                                            <td class="text-right text-danger">₦{{ number_format($transaction->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <th colspan="2">Total Pending</th>
                                        <th class="text-right text-danger">₦{{ number_format($pendingDisbursements, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="p-4 text-center text-muted">
                            <i class="mdi mdi-check-circle mdi-48px text-success"></i>
                            <p class="mb-0 mt-2">No pending disbursements</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Reconciliation Tips -->
            <div class="card-modern">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="mdi mdi-information mr-2"></i>Reconciliation Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 pl-3">
                        <li class="mb-2">Count all cash, coins, and any IOUs in the petty cash box</li>
                        <li class="mb-2">Include approved disbursements that haven't been physically handed out yet</li>
                        <li class="mb-2">Document any discrepancies with detailed notes</li>
                        <li class="mb-2">If there's a shortage, investigate before completing reconciliation</li>
                        <li>Reconcile regularly (recommended: weekly or at month-end)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    var expectedBalance = {{ $jeBalance - $pendingDisbursements }};

    $('#physical_count').on('input', function() {
        var physicalCount = parseFloat($(this).val()) || 0;
        var variance = physicalCount - expectedBalance;

        var $display = $('#varianceDisplay');
        $display.empty();

        if (variance === 0) {
            $display.addClass('bg-success text-white').removeClass('bg-danger bg-warning');
            $display.html('<h4 class="mb-0"><i class="mdi mdi-check-circle"></i> Balanced</h4><small>Physical count matches expected balance</small>');
            $('#approvalNotice').hide();
            $('#notesRequired').hide();
            $('#submitText').text('Complete Reconciliation');
        } else if (variance > 0) {
            $display.addClass('bg-warning').removeClass('bg-success bg-danger text-white');
            $display.html('<h4 class="mb-0 text-dark">+₦' + variance.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Overage</h4><small class="text-dark">More cash than expected</small>');
            $('#approvalNotice').show();
            $('#notesRequired').show();
            $('#submitText').text('Submit for Approval');
        } else {
            $display.addClass('bg-danger text-white').removeClass('bg-success bg-warning');
            $display.html('<h4 class="mb-0">₦' + Math.abs(variance).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Shortage</h4><small>Less cash than expected</small>');
            $('#approvalNotice').show();
            $('#notesRequired').show();
            $('#submitText').text('Submit for Approval');
        }
    });

    // Confirm if there's a variance
    $('#reconcileForm').on('submit', function(e) {
        var physicalCount = parseFloat($('#physical_count').val()) || 0;
        var variance = physicalCount - expectedBalance;

        if (variance !== 0 && !$('#notes').val().trim()) {
            e.preventDefault();
            alert('Please provide notes explaining the variance.');
            $('#notes').focus();
            return false;
        }

        if (variance !== 0) {
            var message = variance > 0
                ? 'There is an overage of ₦' + Math.abs(variance).toFixed(2) + '. This will be submitted for approval. Continue?'
                : 'There is a shortage of ₦' + Math.abs(variance).toFixed(2) + '. This will be submitted for approval. Continue?';

            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
@endsection
