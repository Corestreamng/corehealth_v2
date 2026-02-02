{{--
    Create Patient Deposit
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 4
    Access: SUPERADMIN|ADMIN|ACCOUNTS|BILLER
--}}

@extends('admin.layouts.app')

@section('title', 'New Patient Deposit')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Deposit')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Patient Deposits', 'url' => route('accounting.patient-deposits.index'), 'icon' => 'mdi-account-cash'],
        ['label' => 'New Deposit', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

<style>
.deposit-form {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    padding: 30px;
}
.patient-info-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}
.patient-info-card .name {
    font-size: 1.3rem;
    font-weight: 600;
}
.patient-info-card .detail {
    opacity: 0.9;
    font-size: 0.9rem;
}
.account-balance-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
.balance-amount {
    font-size: 2rem;
    font-weight: 700;
}
.balance-amount.positive { color: #28a745; }
.balance-amount.negative { color: #dc3545; }
.deposit-type-option {
    cursor: pointer;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.2s;
    text-align: center;
}
.deposit-type-option:hover {
    border-color: #667eea;
    background: #f8f9ff;
}
.deposit-type-option.selected {
    border-color: #667eea;
    background: #f0f3ff;
}
.deposit-type-option i {
    font-size: 2rem;
    margin-bottom: 8px;
    color: #667eea;
}
.section-title {
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
</style>

<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('accounting.patient-deposits.store') }}" method="POST" id="deposit-form">
        @csrf

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <div class="deposit-form">
                    <!-- Patient Selection -->
                    <h5 class="section-title"><i class="mdi mdi-account mr-2"></i>Patient Information</h5>

                    @if($patient)
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <div class="patient-info-card">
                            <div class="name">{{ $patient->user?->name ?? $patient->full_name }}</div>
                            <div class="detail"><i class="mdi mdi-folder-account mr-1"></i> File No: {{ $patient->file_no }}</div>
                            @if($patient->phone_no)
                                <div class="detail"><i class="mdi mdi-phone mr-1"></i> {{ $patient->phone_no }}</div>
                            @endif
                            @if($patient->hmo)
                                <div class="detail"><i class="mdi mdi-hospital-building mr-1"></i> HMO: {{ $patient->hmo->name }}</div>
                            @endif
                        </div>
                    @else
                        <div class="form-group">
                            <label>Select Patient <span class="text-danger">*</span></label>
                            <select name="patient_id" id="patient-select" class="form-control" required>
                                <option value="">Search for patient...</option>
                            </select>
                        </div>
                        <div id="patient-preview" style="display: none;"></div>
                    @endif

                    @if($admission)
                        <input type="hidden" name="admission_id" value="{{ $admission->id }}">
                        <div class="alert alert-info">
                            <i class="mdi mdi-bed mr-2"></i>
                            <strong>Admission:</strong> Ward {{ $admission->ward?->name ?? 'N/A' }} - Admitted {{ $admission->admission_date?->format('M d, Y') ?? 'N/A' }}
                        </div>
                    @else
                        <div class="form-group">
                            <label>Link to Admission (Optional)</label>
                            <select name="admission_id" id="admission-select" class="form-control">
                                <option value="">-- No admission link --</option>
                            </select>
                            <small class="text-muted">Select if this deposit is for a specific admission</small>
                        </div>
                    @endif

                    <!-- Deposit Type -->
                    <h5 class="section-title mt-4"><i class="mdi mdi-tag mr-2"></i>Deposit Type</h5>
                    <input type="hidden" name="deposit_type" id="deposit-type-input" value="{{ old('deposit_type', 'general') }}" required>

                    <div class="row mb-4">
                        @foreach($depositTypes as $value => $label)
                            @php
                                $icons = [
                                    'admission' => 'mdi-bed',
                                    'procedure' => 'mdi-needle',
                                    'surgery' => 'mdi-hospital',
                                    'investigation' => 'mdi-microscope',
                                    'general' => 'mdi-cash-multiple',
                                    'other' => 'mdi-dots-horizontal',
                                ];
                            @endphp
                            <div class="col-md-4 col-6 mb-3">
                                <div class="deposit-type-option {{ old('deposit_type', 'general') === $value ? 'selected' : '' }}"
                                     data-type="{{ $value }}">
                                    <i class="mdi {{ $icons[$value] ?? 'mdi-cash' }}"></i>
                                    <div>{{ $label }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Amount & Payment -->
                    <h5 class="section-title"><i class="mdi mdi-currency-ngn mr-2"></i>Payment Details</h5>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Deposit Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="amount" class="form-control form-control-lg"
                                           step="0.01" min="0.01" value="{{ old('amount') }}" required
                                           placeholder="0.00" id="deposit-amount">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" class="form-control form-control-lg" required id="payment-method">
                                    @foreach($paymentMethods as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="bank-row" style="{{ in_array(old('payment_method'), ['pos', 'transfer', 'cheque']) ? '' : 'display: none;' }}">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank</label>
                                <select name="bank_id" class="form-control" id="bank-select">
                                    <option value="">-- Select Bank --</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->bank_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Reference</label>
                                <input type="text" name="payment_reference" class="form-control"
                                       value="{{ old('payment_reference') }}" placeholder="Transaction ID, Cheque #, etc.">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes about this deposit">{{ old('notes') }}</textarea>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.patient-deposits.index') }}" class="btn btn-secondary">
                            <i class="mdi mdi-arrow-left mr-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="mdi mdi-check mr-1"></i> Create Deposit
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Current Account Balance -->
                <div class="account-balance-card">
                    <h6 class="mb-3"><i class="mdi mdi-wallet mr-2"></i>Current Account Balance</h6>
                    @if($patientAccount)
                        <div class="balance-amount {{ $patientAccount->balance >= 0 ? 'positive' : 'negative' }}">
                            ₦{{ number_format(abs($patientAccount->balance), 2) }}
                        </div>
                        <div class="text-muted">
                            @if($patientAccount->balance > 0)
                                <span class="text-success"><i class="mdi mdi-check-circle mr-1"></i>Credit Balance (Hospital Owes Patient)</span>
                            @elseif($patientAccount->balance < 0)
                                <span class="text-danger"><i class="mdi mdi-alert-circle mr-1"></i>Debt (Patient Owes Hospital)</span>
                            @else
                                <span class="text-muted"><i class="mdi mdi-minus-circle mr-1"></i>Zero Balance</span>
                            @endif
                        </div>
                    @else
                        <div class="text-muted">
                            <i class="mdi mdi-information-outline mr-1"></i>
                            No account exists yet. One will be created automatically.
                        </div>
                    @endif

                    <hr>

                    <div id="new-balance-preview">
                        <small class="text-muted">New Balance After Deposit:</small>
                        <div class="h4 text-success mb-0" id="preview-balance">₦0.00</div>
                    </div>
                </div>

                <!-- Journal Entry Preview -->
                <div class="account-balance-card">
                    <h6 class="mb-3"><i class="mdi mdi-book-open mr-2"></i>Journal Entry Preview</h6>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span><strong>DEBIT:</strong> Cash/Bank</span>
                            <span id="je-debit">₦0.00</span>
                        </div>
                        <div class="d-flex justify-content-between text-success">
                            <span><strong>CREDIT:</strong> Patient Deposits (2350)</span>
                            <span id="je-credit">₦0.00</span>
                        </div>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        This journal entry will be created automatically when the deposit is saved.
                    </small>
                </div>

                <!-- Help -->
                <div class="account-balance-card">
                    <h6 class="mb-3"><i class="mdi mdi-help-circle mr-2"></i>How It Works</h6>
                    <ul class="small text-muted mb-0 pl-3">
                        <li>Deposit creates a liability to the patient</li>
                        <li>Balance appears in Aged Payables report</li>
                        <li>Can be applied to future bills from Billing Workbench</li>
                        <li>Unused balance can be refunded</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var currentBalance = {{ $patientAccount ? $patientAccount->balance : 0 }};

    // Patient select2
    @if(!$patient)
    $('#patient-select').select2({
        ajax: {
            url: '{{ route('accounting.patient-deposits.search-patients') }}',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            }
        },
        placeholder: 'Search by name, file number, or phone...',
        minimumInputLength: 2,
        width: '100%'
    }).on('select2:select', function(e) {
        var data = e.params.data;
        loadPatientDetails(data.id);
    });
    @endif

    // Deposit type selection
    $('.deposit-type-option').on('click', function() {
        $('.deposit-type-option').removeClass('selected');
        $(this).addClass('selected');
        $('#deposit-type-input').val($(this).data('type'));
    });

    // Payment method change
    $('#payment-method').on('change', function() {
        var method = $(this).val();
        if (['pos', 'transfer', 'cheque'].includes(method)) {
            $('#bank-row').slideDown();
        } else {
            $('#bank-row').slideUp();
        }
    });

    // Amount change - update previews
    $('#deposit-amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        var newBalance = currentBalance + amount;

        $('#preview-balance').text('₦' + newBalance.toLocaleString('en-NG', {minimumFractionDigits: 2}));
        $('#je-debit, #je-credit').text('₦' + amount.toLocaleString('en-NG', {minimumFractionDigits: 2}));
    });

    // Load patient details when selected
    function loadPatientDetails(patientId) {
        $.get('/accounting/patient-deposits/patient/' + patientId + '/summary', function(data) {
            currentBalance = data.account.balance;

            var html = '<div class="patient-info-card">';
            html += '<div class="name">' + data.patient.name + '</div>';
            html += '<div class="detail"><i class="mdi mdi-folder-account mr-1"></i> File No: ' + data.patient.file_no + '</div>';
            if (data.patient.hmo) {
                html += '<div class="detail"><i class="mdi mdi-hospital-building mr-1"></i> HMO: ' + data.patient.hmo + '</div>';
            }
            html += '</div>';

            $('#patient-preview').html(html).show();

            // Update balance display
            var balanceClass = currentBalance >= 0 ? 'positive' : 'negative';
            var balanceText = currentBalance > 0 ? 'Credit Balance' : (currentBalance < 0 ? 'Debt' : 'Zero Balance');

            // Trigger amount recalc
            $('#deposit-amount').trigger('input');
        });
    }
});
</script>
@endpush
