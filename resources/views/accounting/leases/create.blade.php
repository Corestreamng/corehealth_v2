@extends('admin.layouts.app')
@section('title', 'Create Lease')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Lease Agreement')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => 'New Lease', 'url' => '#', 'icon' => 'mdi-plus']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <form action="{{ route('accounting.leases.store') }}" method="POST" id="lease-form">
            @csrf
            <div class="row">
                <!-- Main Form -->
                <div class="col-lg-8">
                    <!-- Step 1: Lease Details -->
                    <div class="card-modern mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-1-circle mr-2"></i>Lease Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lease_type">Lease Type <span class="text-danger">*</span></label>
                                        <select name="lease_type" id="lease_type" class="form-control" required>
                                            <option value="">Select Type</option>
                                            <option value="finance">Finance Lease</option>
                                            <option value="operating">Operating Lease</option>
                                            <option value="short_term">Short Term (≤12 months)</option>
                                            <option value="low_value">Low Value Asset</option>
                                        </select>
                                        <small class="form-text text-muted">IFRS 16 classification</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="leased_item">Leased Asset <span class="text-danger">*</span></label>
                                        <input type="text" name="leased_item" id="leased_item" class="form-control"
                                               placeholder="e.g., Office Building, Equipment" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="2"
                                          placeholder="Additional details about the leased asset"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_id">Lessor (Supplier)</label>
                                        <select name="lessor_id" id="lessor_id" class="form-control select2">
                                            <option value="">Select Supplier</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_name">Lessor Name</label>
                                        <input type="text" name="lessor_name" id="lessor_name" class="form-control"
                                               placeholder="Or enter name manually">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_contact">Lessor Contact</label>
                                        <input type="text" name="lessor_contact" id="lessor_contact" class="form-control"
                                               placeholder="Phone/Email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="asset_location">Asset Location</label>
                                        <input type="text" name="asset_location" id="asset_location" class="form-control"
                                               placeholder="Physical location of asset">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="department_id">Department</label>
                                <select name="department_id" id="department_id" class="form-control select2">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Payment Terms -->
                    <div class="card-modern mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-2-circle mr-2"></i>Payment Terms</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="commencement_date">Commencement Date <span class="text-danger">*</span></label>
                                        <input type="date" name="commencement_date" id="commencement_date"
                                               class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end_date">End Date <span class="text-danger">*</span></label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="monthly_payment">Monthly Payment <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="monthly_payment" id="monthly_payment"
                                                   class="form-control" step="0.01" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="annual_rent_increase_rate">Annual Escalation Rate</label>
                                        <div class="input-group">
                                            <input type="number" name="annual_rent_increase_rate" id="annual_rent_increase_rate"
                                                   class="form-control" step="0.01" min="0" max="100" value="0">
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Annual rent increase percentage</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="incremental_borrowing_rate">Borrowing Rate (IBR) <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" name="incremental_borrowing_rate" id="incremental_borrowing_rate"
                                                   class="form-control" step="0.01" min="0" max="100" value="10" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">IFRS 16 discount rate</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="initial_direct_costs">Initial Direct Costs</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="initial_direct_costs" id="initial_direct_costs"
                                                   class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                        <small class="form-text text-muted">Costs to obtain the lease</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lease_incentives_received">Lease Incentives Received</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="lease_incentives_received" id="lease_incentives_received"
                                                   class="form-control" step="0.01" min="0" value="0">
                                        </div>
                                        <small class="form-text text-muted">Incentives from lessor</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Options -->
                    <div class="card-modern mb-4">
                        <div class="card-header bg-warning">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-3-circle mr-2"></i>Lease Options</h5>
                        </div>
                        <div class="card-body">
                            <!-- Purchase Option -->
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="has_purchase_option"
                                           id="has_purchase_option" value="1">
                                    <label class="custom-control-label" for="has_purchase_option">
                                        <strong>Has Purchase Option</strong>
                                    </label>
                                </div>
                            </div>
                            <div id="purchase-option-fields" class="ml-4 d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="purchase_option_amount">Purchase Option Amount</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">₦</span>
                                                </div>
                                                <input type="number" name="purchase_option_amount" id="purchase_option_amount"
                                                       class="form-control" step="0.01" min="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox mt-4">
                                                <input type="checkbox" class="custom-control-input"
                                                       name="purchase_option_reasonably_certain"
                                                       id="purchase_option_reasonably_certain" value="1">
                                                <label class="custom-control-label" for="purchase_option_reasonably_certain">
                                                    Reasonably certain to exercise
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Termination Option -->
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" name="has_termination_option"
                                           id="has_termination_option" value="1">
                                    <label class="custom-control-label" for="has_termination_option">
                                        <strong>Has Termination Option</strong>
                                    </label>
                                </div>
                            </div>
                            <div id="termination-option-fields" class="ml-4 d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="earliest_termination_date">Earliest Termination Date</label>
                                            <input type="date" name="earliest_termination_date" id="earliest_termination_date"
                                                   class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="termination_penalty">Termination Penalty</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">₦</span>
                                                </div>
                                                <input type="number" name="termination_penalty" id="termination_penalty"
                                                       class="form-control" step="0.01" min="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Residual Value -->
                            <div class="form-group">
                                <label for="residual_value_guarantee">Residual Value Guarantee</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="residual_value_guarantee" id="residual_value_guarantee"
                                           class="form-control" step="0.01" min="0" value="0">
                                </div>
                                <small class="form-text text-muted">Guaranteed residual value at end of lease</small>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting Mapping -->
                    <div class="card-modern mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0"><i class="mdi mdi-numeric-4-circle mr-2"></i>Accounting Mapping</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="rou_asset_account_id">ROU Asset Account</label>
                                        <select name="rou_asset_account_id" id="rou_asset_account_id" class="form-control select2">
                                            <option value="">Select Account</option>
                                            @foreach($rouAssetAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Right-of-Use Asset account (1xxx)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lease_liability_account_id">Lease Liability Account</label>
                                        <select name="lease_liability_account_id" id="lease_liability_account_id" class="form-control select2">
                                            <option value="">Select Account</option>
                                            @foreach($liabilityAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Lease Liability account (2xxx)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="depreciation_account_id">Depreciation Expense Account</label>
                                        <select name="depreciation_account_id" id="depreciation_account_id" class="form-control select2">
                                            <option value="">Select Account</option>
                                            @foreach($expenseAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">ROU Depreciation expense (5xxx)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="interest_account_id">Interest Expense Account</label>
                                        <select name="interest_account_id" id="interest_account_id" class="form-control select2">
                                            <option value="">Select Account</option>
                                            @foreach($expenseAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Interest expense on lease liability (5xxx)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="card-modern mb-4">
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="notes">Additional Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3"
                                          placeholder="Any additional notes about this lease"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Calculator -->
                <div class="col-lg-4">
                    <div class="card-modern sticky-top" style="top: 80px;">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>IFRS 16 Calculator</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info small">
                                <i class="mdi mdi-information"></i>
                                Fill in payment terms to calculate IFRS 16 values automatically.
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Lease Term</small>
                                <h5 id="calc-term">- months</h5>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <small class="text-muted">Total Lease Payments</small>
                                <h5 id="calc-total-payments">₦0.00</h5>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Present Value (Lease Liability)</small>
                                <h4 class="text-primary" id="calc-pv">₦0.00</h4>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Initial ROU Asset Value</small>
                                <h4 class="text-info" id="calc-rou">₦0.00</h4>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <small class="text-muted">Monthly Depreciation</small>
                                <h5 id="calc-depreciation">₦0.00</h5>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Total Interest Over Term</small>
                                <h5 id="calc-total-interest">₦0.00</h5>
                            </div>

                            <hr>

                            <button type="submit" class="btn btn-success btn-block btn-lg">
                                <i class="mdi mdi-content-save"></i> Create Lease
                            </button>
                            <a href="{{ route('accounting.leases.index') }}" class="btn btn-outline-secondary btn-block">
                                <i class="mdi mdi-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        allowClear: true,
        placeholder: 'Select...',
        width: '100%'
    });

    // Toggle purchase option fields
    $('#has_purchase_option').on('change', function() {
        $('#purchase-option-fields').toggleClass('d-none', !this.checked);
    });

    // Toggle termination option fields
    $('#has_termination_option').on('change', function() {
        $('#termination-option-fields').toggleClass('d-none', !this.checked);
    });

    // Calculate IFRS 16 values
    function calculateIfrs16() {
        var commencement = $('#commencement_date').val();
        var endDate = $('#end_date').val();
        var monthlyPayment = parseFloat($('#monthly_payment').val()) || 0;
        var escalation = parseFloat($('#annual_rent_increase_rate').val()) || 0;
        var ibr = parseFloat($('#incremental_borrowing_rate').val()) || 10;
        var initialCosts = parseFloat($('#initial_direct_costs').val()) || 0;
        var incentives = parseFloat($('#lease_incentives_received').val()) || 0;

        if (!commencement || !endDate || monthlyPayment <= 0) {
            return;
        }

        // Calculate term in months
        var start = new Date(commencement);
        var end = new Date(endDate);
        var months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());

        if (months <= 0) {
            return;
        }

        $('#calc-term').text(months + ' months');

        // Calculate present value
        var monthlyRate = (ibr / 100) / 12;
        var pvPayments = 0;
        var totalPayments = 0;
        var currentPayment = monthlyPayment;

        for (var i = 1; i <= months; i++) {
            // Apply annual escalation at start of each year
            if (escalation > 0 && i > 1 && (i - 1) % 12 === 0) {
                currentPayment *= (1 + escalation / 100);
            }

            var pvFactor = 1 / Math.pow(1 + monthlyRate, i);
            pvPayments += currentPayment * pvFactor;
            totalPayments += currentPayment;
        }

        var initialLiability = pvPayments;
        var initialRouAsset = pvPayments + initialCosts - incentives;
        var monthlyDepreciation = initialRouAsset / months;
        var totalInterest = totalPayments - initialLiability;

        // Update display
        $('#calc-total-payments').text('₦' + totalPayments.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-pv').text('₦' + initialLiability.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-rou').text('₦' + initialRouAsset.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-depreciation').text('₦' + monthlyDepreciation.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-total-interest').text('₦' + totalInterest.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }

    // Trigger calculation on input change
    $('#commencement_date, #end_date, #monthly_payment, #annual_rent_increase_rate, #incremental_borrowing_rate, #initial_direct_costs, #lease_incentives_received')
        .on('change input', calculateIfrs16);
});
</script>
@endpush
