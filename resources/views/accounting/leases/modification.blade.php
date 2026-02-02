@extends('admin.layouts.app')
@section('title', 'Lease Modification')
@section('page_name', 'Accounting')
@section('subpage_name', 'Lease Modification')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Modification', 'url' => '#', 'icon' => 'mdi-file-edit']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Info Alert -->
                <div class="alert alert-info mb-4">
                    <i class="mdi mdi-information-outline mr-2"></i>
                    <strong>IFRS 16 Lease Modification:</strong> This feature creates a proper remeasurement record.
                    The ROU asset and lease liability will be recalculated based on the remaining lease term and new payment terms.
                </div>

                <!-- Current Lease Summary -->
                <div class="card card-modern mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="mdi mdi-file-document mr-2"></i>Current Lease Terms</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <small class="text-muted">Lease Number</small>
                                <p class="mb-0"><strong>{{ $lease->lease_number }}</strong></p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Monthly Payment</small>
                                <p class="mb-0"><strong>₦{{ number_format($lease->monthly_payment, 2) }}</strong></p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">End Date</small>
                                <p class="mb-0"><strong>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</strong></p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Remaining Term</small>
                                <p class="mb-0"><strong>{{ max(0, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }} months</strong></p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Current Liability</small>
                                <p class="mb-0"><strong>₦{{ number_format($lease->current_lease_liability, 2) }}</strong></p>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted">Current ROU Asset</small>
                                <p class="mb-0"><strong>₦{{ number_format($lease->current_rou_asset_value, 2) }}</strong></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modification Form -->
                <form action="{{ route('accounting.leases.modification.store', $lease->id) }}" method="POST" id="modification-form">
                    @csrf

                    <div class="card card-modern mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="mdi mdi-file-edit mr-2"></i>Modification Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modification_date">Modification Effective Date <span class="text-danger">*</span></label>
                                        <input type="date" name="modification_date" id="modification_date" class="form-control"
                                               value="{{ date('Y-m-d') }}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modification_type">Modification Type <span class="text-danger">*</span></label>
                                        <select name="modification_type" id="modification_type" class="form-control" required>
                                            <option value="">Select Type</option>
                                            <option value="term_extension">Term Extension</option>
                                            <option value="term_reduction">Term Reduction</option>
                                            <option value="payment_change">Payment Amount Change</option>
                                            <option value="scope_change">Scope Change</option>
                                            <option value="rate_change">Rate Change</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Description <span class="text-danger">*</span></label>
                                <textarea name="description" id="description" class="form-control" rows="3" required
                                          placeholder="Describe the reason for this modification and any relevant details"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Term Modification -->
                    <div class="card card-modern mb-4" id="term-modification-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-calendar-range mr-2"></i>New Term Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new_remaining_term_months">New Remaining Term (Months)</label>
                                        <input type="number" name="new_remaining_term_months" id="new_remaining_term_months"
                                               class="form-control" min="1"
                                               value="{{ max(1, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }}"
                                               placeholder="Enter new remaining term in months">
                                        <small class="text-muted">Current remaining: {{ max(0, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }} months</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>New End Date (calculated)</label>
                                        <input type="text" class="form-control bg-light" id="calculated_end_date" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Modification -->
                    <div class="card card-modern mb-4" id="payment-modification-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-cash mr-2"></i>New Payment Terms</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new_monthly_payment">New Monthly Payment</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="new_monthly_payment" id="new_monthly_payment"
                                                   class="form-control" step="0.01" min="0"
                                                   value="{{ $lease->monthly_payment }}"
                                                   placeholder="Enter new monthly payment">
                                        </div>
                                        <small class="text-muted">Current: ₦{{ number_format($lease->monthly_payment, 2) }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Impact Preview -->
                    <div class="card card-modern mb-4" id="impact-preview">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>Estimated Impact</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-secondary mb-3">
                                <i class="mdi mdi-information"></i>
                                The values below are estimates. Final calculations will be performed upon submission.
                            </div>
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <small class="text-muted">New Lease Liability</small>
                                    <h4 id="estimated_liability">-</h4>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Adjustment Amount</small>
                                    <h4 id="estimated_adjustment">-</h4>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">New ROU Asset</small>
                                    <h4 id="estimated_rou">-</h4>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">New Monthly Depreciation</small>
                                    <h4 id="estimated_depreciation">-</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card card-modern mb-4">
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="notes">Additional Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                          placeholder="Optional notes about this modification"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="mdi mdi-file-edit"></i> Submit Modification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var currentLiability = {{ $lease->current_lease_liability }};
    var currentRouAsset = {{ $lease->current_rou_asset_value }};
    var ibr = {{ $lease->incremental_borrowing_rate }};
    var escalation = {{ $lease->annual_rent_increase_rate }};

    // Toggle fields based on modification type
    $('#modification_type').on('change', function() {
        var type = $(this).val();

        // Show/hide relevant sections
        if (type === 'term_extension' || type === 'term_reduction') {
            $('#term-modification-card').show();
            $('#new_remaining_term_months').attr('required', true);
        } else {
            $('#term-modification-card').hide();
            $('#new_remaining_term_months').attr('required', false);
        }

        if (type === 'payment_change') {
            $('#payment-modification-card').show();
            $('#new_monthly_payment').attr('required', true);
        } else {
            $('#payment-modification-card').hide();
            $('#new_monthly_payment').attr('required', false);
        }

        calculateImpact();
    });

    // Calculate end date
    $('#new_remaining_term_months').on('input', function() {
        var months = parseInt($(this).val()) || 0;
        var modDate = new Date($('#modification_date').val());
        modDate.setMonth(modDate.getMonth() + months);
        $('#calculated_end_date').val(modDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }));
        calculateImpact();
    });

    $('#new_monthly_payment, #modification_date').on('input change', function() {
        calculateImpact();
    });

    function calculateImpact() {
        var months = parseInt($('#new_remaining_term_months').val()) || {{ max(1, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }};
        var payment = parseFloat($('#new_monthly_payment').val()) || {{ $lease->monthly_payment }};

        if (months <= 0) return;

        // Calculate PV of remaining payments
        var monthlyRate = (ibr / 100) / 12;
        var pvPayments = 0;
        var currentPayment = payment;

        for (var i = 1; i <= months; i++) {
            if (escalation > 0 && i > 1 && (i - 1) % 12 === 0) {
                currentPayment *= (1 + escalation / 100);
            }
            var pvFactor = 1 / Math.pow(1 + monthlyRate, i);
            pvPayments += currentPayment * pvFactor;
        }

        var newLiability = pvPayments;
        var adjustment = newLiability - currentLiability;
        var newRouAsset = currentRouAsset + adjustment;
        var monthlyDepreciation = newRouAsset / months;

        $('#estimated_liability').text('₦' + newLiability.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#estimated_adjustment').text((adjustment >= 0 ? '+' : '') + '₦' + adjustment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#estimated_adjustment').removeClass('text-success text-danger').addClass(adjustment >= 0 ? 'text-danger' : 'text-success');
        $('#estimated_rou').text('₦' + newRouAsset.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#estimated_depreciation').text('₦' + monthlyDepreciation.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }

    // Initial calculation
    $('#new_remaining_term_months').trigger('input');
    calculateImpact();
});
</script>
@endpush
