@extends('admin.layouts.app')
@section('title', 'Lease Modification')
@section('page_name', 'Accounting')
@section('subpage_name', 'Lease Modification')

@push('styles')
<style>
    /* Select2 Consistent Styling */
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
        padding: 0.375rem 0.75rem;
        line-height: 1.5;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 0;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
    .select2-container { width: 100% !important; }

    /* Cards */
    .card-modern { border-radius: 0.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: none; margin-bottom: 1rem; }
    .card-modern .card-header { border-radius: 0.5rem 0.5rem 0 0; padding: 1rem 1.25rem; font-weight: 600; }
    .card-modern .card-body { padding: 1.25rem; }

    /* Header gradient */
    .modification-header-gradient {
        background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
        border-radius: 0.5rem;
    }

    /* Alert styling */
    .alert-container .alert { border-radius: 0.5rem; border-left: 4px solid; }
    .alert-container .alert-success { border-left-color: #28a745; }
    .alert-container .alert-danger { border-left-color: #dc3545; }
    .alert-container .alert-warning { border-left-color: #ffc107; }
    .alert-container .alert-info { border-left-color: #17a2b8; }

    /* Section headers */
    .section-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }
    .section-header .section-number {
        background: #e67e22;
        color: white;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
        margin-right: 0.75rem;
    }
    .section-header h5 { margin: 0; font-size: 1rem; }

    /* Current value cards */
    .current-value-card {
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        border: 1px solid #e9ecef;
    }
    .current-value-card.highlighted {
        background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
        border-color: #ffc107;
    }

    /* Impact preview */
    .impact-card {
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        border: 2px solid;
        transition: all 0.3s ease;
    }
    .impact-card.liability { border-color: #ffc107; background: rgba(255, 193, 7, 0.05); }
    .impact-card.adjustment { border-color: #17a2b8; background: rgba(23, 162, 184, 0.05); }
    .impact-card.rou { border-color: #28a745; background: rgba(40, 167, 69, 0.05); }
    .impact-card.depreciation { border-color: #6f42c1; background: rgba(111, 66, 193, 0.05); }

    /* Modification type cards */
    .mod-type-card {
        border: 2px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
    }
    .mod-type-card:hover { border-color: #e67e22; background: rgba(230, 126, 34, 0.05); }
    .mod-type-card.selected { border-color: #e67e22; background: rgba(230, 126, 34, 0.1); }
    .mod-type-card i { font-size: 1.5rem; margin-bottom: 0.5rem; }

    /* Required field indicator */
    .required-indicator { color: #dc3545; font-weight: bold; }
</style>
@endpush

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Modification', 'url' => '#', 'icon' => 'mdi-file-edit']
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
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="mdi mdi-alert-circle mr-2"></i><strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Header -->
                <div class="card-modern modification-header-gradient mb-4">
                    <div class="card-body text-white py-3">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <i class="mdi mdi-file-edit" style="font-size: 2.5rem; opacity: 0.8;"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-1">Lease Modification</h4>
                                        <p class="mb-0 opacity-75">{{ $lease->lease_number }} - {{ $lease->leased_item }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <small class="d-block opacity-75">IFRS 16 Compliant Remeasurement</small>
                                <span class="badge badge-light">{{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }} Lease</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IFRS 16 Info Alert -->
                <div class="alert alert-info mb-4">
                    <div class="d-flex">
                        <div class="mr-3">
                            <i class="mdi mdi-book-open-outline" style="font-size: 1.5rem;"></i>
                        </div>
                        <div>
                            <strong>IFRS 16 Lease Modification:</strong>
                            <p class="mb-0 mt-1">This creates a proper remeasurement record. The ROU asset and lease liability will be recalculated based on the remaining lease term and new payment terms. Most modifications do not create P&L impact - the adjustment is recognized directly against the ROU asset.</p>
                        </div>
                    </div>
                </div>

                <!-- Current Lease Terms Summary -->
                <div class="card-modern mb-4">
                    <div class="card-body">
                        <div class="section-header">
                            <span class="section-number"><i class="mdi mdi-eye"></i></span>
                            <h5>Current Lease Terms</h5>
                        </div>

                        <div class="row">
                            <div class="col-md-2">
                                <div class="current-value-card">
                                    <small class="text-muted d-block">Monthly Payment</small>
                                    <strong class="text-primary">₦{{ number_format($lease->monthly_payment, 2) }}</strong>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="current-value-card">
                                    <small class="text-muted d-block">End Date</small>
                                    <strong>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</strong>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="current-value-card">
                                    <small class="text-muted d-block">Remaining Term</small>
                                    <strong>{{ max(0, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }} months</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="current-value-card highlighted">
                                    <small class="text-muted d-block">Current Liability</small>
                                    <strong class="text-warning">₦{{ number_format($lease->current_lease_liability, 2) }}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="current-value-card highlighted">
                                    <small class="text-muted d-block">Current ROU Asset</small>
                                    <strong class="text-success">₦{{ number_format($lease->current_rou_asset_value, 2) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modification Form -->
                <form action="{{ route('accounting.leases.modification.store', $lease->id) }}" method="POST" id="modification-form">
                    @csrf

                    <!-- Section 1: Modification Details -->
                    <div class="card-modern mb-4">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">1</span>
                                <h5><i class="mdi mdi-calendar-edit mr-2 text-warning"></i>Modification Details</h5>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modification_date">Effective Date <span class="required-indicator">*</span></label>
                                        <input type="date" name="modification_date" id="modification_date" class="form-control"
                                               value="{{ old('modification_date', date('Y-m-d')) }}" required>
                                        <small class="text-muted">Date when modification takes effect</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modification_type">Modification Type <span class="required-indicator">*</span></label>
                                        <select name="modification_type" id="modification_type" class="form-control select2" required>
                                            <option value="">Select Modification Type</option>
                                            <option value="term_extension" {{ old('modification_type') == 'term_extension' ? 'selected' : '' }}>
                                                Term Extension
                                            </option>
                                            <option value="term_reduction" {{ old('modification_type') == 'term_reduction' ? 'selected' : '' }}>
                                                Term Reduction
                                            </option>
                                            <option value="payment_change" {{ old('modification_type') == 'payment_change' ? 'selected' : '' }}>
                                                Payment Amount Change
                                            </option>
                                            <option value="scope_change" {{ old('modification_type') == 'scope_change' ? 'selected' : '' }}>
                                                Scope Change
                                            </option>
                                            <option value="rate_change" {{ old('modification_type') == 'rate_change' ? 'selected' : '' }}>
                                                Rate Change (IBR)
                                            </option>
                                        </select>
                                        <small class="text-muted">Type of modification being made</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">Description <span class="required-indicator">*</span></label>
                                <textarea name="description" id="description" class="form-control" rows="3" required
                                          placeholder="Describe the reason for this modification and any relevant details...">{{ old('description') }}</textarea>
                                <small class="text-muted">Provide a clear explanation for audit trail purposes</small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Term Modification -->
                    <div class="card-modern mb-4" id="term-modification-card" style="display: none;">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">2</span>
                                <h5><i class="mdi mdi-calendar-range mr-2 text-info"></i>New Term Details</h5>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="new_remaining_term_months">New Remaining Term (Months) <span class="required-indicator">*</span></label>
                                        <input type="number" name="new_remaining_term_months" id="new_remaining_term_months"
                                               class="form-control" min="1"
                                               value="{{ old('new_remaining_term_months', max(1, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date)))) }}"
                                               placeholder="Enter new remaining term">
                                        <small class="text-muted">
                                            Current remaining: <strong>{{ max(0, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }} months</strong>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>New End Date (calculated)</label>
                                        <input type="text" class="form-control bg-light" id="calculated_end_date" readonly>
                                        <small class="text-muted">Automatically calculated from remaining term</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Payment Modification -->
                    <div class="card-modern mb-4" id="payment-modification-card" style="display: none;">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">2</span>
                                <h5><i class="mdi mdi-cash mr-2 text-success"></i>New Payment Terms</h5>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label for="new_monthly_payment">New Monthly Payment <span class="required-indicator">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="new_monthly_payment" id="new_monthly_payment"
                                                   class="form-control" step="0.01" min="0"
                                                   value="{{ old('new_monthly_payment', $lease->monthly_payment) }}"
                                                   placeholder="Enter new monthly payment">
                                        </div>
                                        <small class="text-muted">
                                            Current: <strong>₦{{ number_format($lease->monthly_payment, 2) }}</strong>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Impact Preview -->
                    <div class="card-modern mb-4" id="impact-preview">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">3</span>
                                <h5><i class="mdi mdi-calculator mr-2 text-primary"></i>Estimated Impact</h5>
                            </div>

                            <div class="alert alert-secondary small mb-4">
                                <i class="mdi mdi-information mr-1"></i>
                                These are estimated values. Final calculations will be performed upon submission using IFRS 16 methodology.
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="impact-card liability">
                                        <small class="text-muted d-block mb-2">New Lease Liability</small>
                                        <h4 id="estimated_liability" class="text-warning mb-0">-</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="impact-card adjustment">
                                        <small class="text-muted d-block mb-2">Adjustment Amount</small>
                                        <h4 id="estimated_adjustment" class="mb-0">-</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="impact-card rou">
                                        <small class="text-muted d-block mb-2">New ROU Asset</small>
                                        <h4 id="estimated_rou" class="text-success mb-0">-</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="impact-card depreciation">
                                        <small class="text-muted d-block mb-2">New Monthly Depr.</small>
                                        <h4 id="estimated_depreciation" class="text-purple mb-0">-</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 5: Additional Notes -->
                    <div class="card-modern mb-4">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">4</span>
                                <h5><i class="mdi mdi-note-text mr-2 text-secondary"></i>Additional Notes</h5>
                            </div>

                            <div class="form-group mb-0">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                          placeholder="Any additional notes about this modification (optional)">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 6: Journal Entry Preview -->
                    <div class="card-modern mb-4 border-warning">
                        <div class="card-body">
                            <div class="section-header">
                                <span class="section-number">5</span>
                                <h5><i class="mdi mdi-book-open-variant mr-2 text-warning"></i>Journal Entry Preview</h5>
                            </div>

                            <div class="alert alert-info small mb-3">
                                <i class="mdi mdi-information-outline mr-1"></i>
                                <strong>IFRS 16 Modification Accounting:</strong> The adjustment is recognized by modifying both the ROU Asset and Lease Liability. No P&L impact for most modifications.
                            </div>

                            <table class="table table-sm table-bordered mb-3">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Account</th>
                                        <th class="text-right" style="width:130px">Debit</th>
                                        <th class="text-right" style="width:130px">Credit</th>
                                    </tr>
                                </thead>
                                <tbody id="je-preview-body">
                                    <!-- Increase scenario (Debit ROU, Credit Liability) -->
                                    <tr id="je-increase-rou" class="d-none">
                                        <td>
                                            <span class="badge badge-success">ROU Asset</span>
                                            <small class="text-muted d-block">{{ $lease->rou_account_code ?? '1460' }} - Right-of-Use Asset</small>
                                        </td>
                                        <td class="text-right font-weight-bold" id="je-rou-debit">-</td>
                                        <td></td>
                                    </tr>
                                    <tr id="je-increase-liability" class="d-none">
                                        <td>
                                            <span class="badge badge-warning">Lease Liability</span>
                                            <small class="text-muted d-block">{{ $lease->liability_account_code ?? '2310' }} - Lease Obligations</small>
                                        </td>
                                        <td></td>
                                        <td class="text-right font-weight-bold" id="je-liability-credit">-</td>
                                    </tr>
                                    <!-- Decrease scenario (Debit Liability, Credit ROU) -->
                                    <tr id="je-decrease-liability" class="d-none">
                                        <td>
                                            <span class="badge badge-warning">Lease Liability</span>
                                            <small class="text-muted d-block">{{ $lease->liability_account_code ?? '2310' }} - Lease Obligations</small>
                                        </td>
                                        <td class="text-right font-weight-bold" id="je-liability-debit">-</td>
                                        <td></td>
                                    </tr>
                                    <tr id="je-decrease-rou" class="d-none">
                                        <td>
                                            <span class="badge badge-success">ROU Asset</span>
                                            <small class="text-muted d-block">{{ $lease->rou_account_code ?? '1460' }} - Right-of-Use Asset</small>
                                        </td>
                                        <td></td>
                                        <td class="text-right font-weight-bold" id="je-rou-credit">-</td>
                                    </tr>
                                    <!-- No change message -->
                                    <tr id="je-no-change">
                                        <td colspan="3" class="text-center text-muted py-3">
                                            <i class="mdi mdi-information-outline mr-1"></i>
                                            Enter modification details to see journal entry preview
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-right" id="je-total-debit">-</th>
                                        <th class="text-right" id="je-total-credit">-</th>
                                    </tr>
                                </tfoot>
                            </table>

                            <small class="text-muted">
                                <i class="mdi mdi-alert-outline mr-1"></i>
                                Final journal entry will be created automatically upon submission.
                            </small>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between mb-4">
                        <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-secondary">
                            <i class="mdi mdi-arrow-left mr-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-warning btn-lg px-4">
                            <i class="mdi mdi-file-edit mr-1"></i> Submit Modification
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
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: 'Select...',
        width: '100%'
    });

    // Variables from PHP
    var currentLiability = {{ $lease->current_lease_liability }};
    var currentRouAsset = {{ $lease->current_rou_asset_value }};
    var ibr = {{ $lease->incremental_borrowing_rate }};
    var escalation = {{ $lease->annual_rent_increase_rate ?? 0 }};
    var currentMonthlyPayment = {{ $lease->monthly_payment }};
    var currentRemainingMonths = {{ max(1, \Carbon\Carbon::now()->diffInMonths(\Carbon\Carbon::parse($lease->end_date))) }};

    // Toggle fields based on modification type
    $('#modification_type').on('change', function() {
        var type = $(this).val();

        // Hide all optional sections first
        $('#term-modification-card, #payment-modification-card').hide();
        $('#new_remaining_term_months, #new_monthly_payment').attr('required', false);

        // Show relevant sections
        if (type === 'term_extension' || type === 'term_reduction') {
            $('#term-modification-card').show();
            $('#new_remaining_term_months').attr('required', true);
        }

        if (type === 'payment_change') {
            $('#payment-modification-card').show();
            $('#new_monthly_payment').attr('required', true);
        }

        calculateImpact();
    });

    // Calculate end date when term changes
    $('#new_remaining_term_months').on('input', function() {
        var months = parseInt($(this).val()) || 0;
        var modDate = new Date($('#modification_date').val());
        modDate.setMonth(modDate.getMonth() + months);
        $('#calculated_end_date').val(modDate.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }));
        calculateImpact();
    });

    // Recalculate on any input change
    $('#new_monthly_payment, #modification_date').on('input change', function() {
        calculateImpact();
    });

    function calculateImpact() {
        var type = $('#modification_type').val();
        var months = parseInt($('#new_remaining_term_months').val()) || currentRemainingMonths;
        var payment = parseFloat($('#new_monthly_payment').val()) || currentMonthlyPayment;

        if (months <= 0) return;

        // Calculate PV of remaining payments using effective interest method
        var monthlyRate = (ibr / 100) / 12;
        var pvPayments = 0;
        var currentPayment = payment;

        for (var i = 1; i <= months; i++) {
            // Apply escalation annually
            if (escalation > 0 && i > 1 && (i - 1) % 12 === 0) {
                currentPayment *= (1 + escalation / 100);
            }
            var pvFactor = 1 / Math.pow(1 + monthlyRate, i);
            pvPayments += currentPayment * pvFactor;
        }

        var newLiability = pvPayments;
        var adjustment = newLiability - currentLiability;
        var newRouAsset = currentRouAsset + adjustment;
        var monthlyDepreciation = months > 0 ? newRouAsset / months : 0;

        // Update display
        $('#estimated_liability').text('₦' + newLiability.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        var adjustmentText = (adjustment >= 0 ? '+' : '') + '₦' + adjustment.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        $('#estimated_adjustment')
            .text(adjustmentText)
            .removeClass('text-success text-danger')
            .addClass(adjustment >= 0 ? 'text-danger' : 'text-success');

        $('#estimated_rou').text('₦' + newRouAsset.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        $('#estimated_depreciation').text('₦' + monthlyDepreciation.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }));

        // Update JE Preview
        updateJEPreview(adjustment);
    }

    function updateJEPreview(adjustment) {
        var absAdjustment = Math.abs(adjustment);
        var formattedAdjustment = '₦' + absAdjustment.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        // Hide all JE rows first
        $('#je-increase-rou, #je-increase-liability, #je-decrease-liability, #je-decrease-rou').addClass('d-none');
        $('#je-no-change').addClass('d-none');

        if (Math.abs(adjustment) < 0.01) {
            // No significant change
            $('#je-no-change').removeClass('d-none').find('td').html(
                '<i class="mdi mdi-check-circle text-success mr-1"></i> No adjustment needed - values unchanged'
            );
            $('#je-total-debit, #je-total-credit').text('₦0.00');
        } else if (adjustment > 0) {
            // Increase in liability - DEBIT ROU, CREDIT Liability
            $('#je-increase-rou, #je-increase-liability').removeClass('d-none');
            $('#je-rou-debit').text(formattedAdjustment);
            $('#je-liability-credit').text(formattedAdjustment);
            $('#je-total-debit, #je-total-credit').text(formattedAdjustment);
        } else {
            // Decrease in liability - DEBIT Liability, CREDIT ROU
            $('#je-decrease-liability, #je-decrease-rou').removeClass('d-none');
            $('#je-liability-debit').text(formattedAdjustment);
            $('#je-rou-credit').text(formattedAdjustment);
            $('#je-total-debit, #je-total-credit').text(formattedAdjustment);
        }
    }

    // Initial trigger
    $('#new_remaining_term_months').trigger('input');
    calculateImpact();

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-container .alert').fadeOut('slow');
    }, 5000);
});
</script>
@endpush
