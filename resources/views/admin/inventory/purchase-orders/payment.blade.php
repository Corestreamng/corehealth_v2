@extends('admin.layouts.app')
@section('title', 'Record Payment - ' . $purchaseOrder->po_number)
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Record Payment')

@section('content')
<style>
    .po-header {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 2rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    .po-header h2 {
        margin: 0;
        font-weight: 600;
    }
    .po-header .po-number {
        font-size: 1.25rem;
        opacity: 0.9;
    }
    .detail-card {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .detail-card h5 {
        border-bottom: 2px solid #28a745;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        color: #333;
    }
    .detail-label {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.85rem;
    }
    .detail-value {
        font-weight: 600;
        color: #212529;
    }
    .summary-box {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
    }
    .summary-box .amount {
        font-size: 1.5rem;
        font-weight: bold;
    }
    .summary-box.total { border-left: 4px solid #007bff; }
    .summary-box.paid { border-left: 4px solid #28a745; }
    .summary-box.balance { border-left: 4px solid #dc3545; }
    .payment-table {
        width: 100%;
    }
    .payment-table th {
        background: #f8f9fa;
    }
    .payment-status-badge {
        font-size: 0.85rem;
        padding: 0.4em 0.8em;
        border-radius: 20px;
    }
    .bank-fields { display: none; }
    .cheque-fields { display: none; }
</style>

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="po-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="po-number">{{ $purchaseOrder->po_number }}</div>
                    <h2>Record Payment</h2>
                    <div class="mt-2">
                        <span class="badge badge-light">{{ $purchaseOrder->supplier->company_name }}</span>
                        <span class="badge {{ $purchaseOrder->getPaymentStatusBadgeClass() }} ml-2">
                            {{ ucfirst($purchaseOrder->payment_status) }}
                        </span>
                    </div>
                </div>
                <div class="col-md-4 text-right">
                    <h3 class="mb-0">₦{{ number_format($purchaseOrder->balance_due, 2) }}</h3>
                    <small>Balance Due</small>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="{{ route('inventory.purchase-orders.show', $purchaseOrder) }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left"></i> Back to PO
            </a>
            <a href="{{ route('inventory.purchase-orders.accounts-payable') }}" class="btn btn-outline-secondary btn-sm">
                <i class="mdi mdi-format-list-bulleted"></i> Accounts Payable
            </a>
        </div>

        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-7">
                <div class="detail-card">
                    <h5><i class="mdi mdi-cash-multiple"></i> Payment Details</h5>

                    <form id="payment-form" method="POST" action="{{ route('inventory.purchase-orders.payment.process', $purchaseOrder) }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="payment_date"
                                       class="form-control @error('payment_date') is-invalid @enderror"
                                       value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" name="amount" id="amount"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           value="{{ old('amount', $purchaseOrder->balance_due) }}"
                                           min="0.01" max="{{ $purchaseOrder->balance_due }}" step="0.01" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <small class="text-muted">Max: ₦{{ number_format($purchaseOrder->balance_due, 2) }}</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select name="payment_method" id="payment_method"
                                        class="form-control @error('payment_method') is-invalid @enderror" required>
                                    @foreach($paymentMethods as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_method') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group bank-fields">
                                <label for="bank_id">Bank</label>
                                <select name="bank_id" id="bank_id"
                                        class="form-control @error('bank_id') is-invalid @enderror">
                                    <option value="">-- Select Bank --</option>
                                    @foreach($banks as $bank)
                                        <option value="{{ $bank->id }}" {{ old('bank_id') == $bank->id ? 'selected' : '' }}>
                                            {{ $bank->name }} - {{ $bank->account_number }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('bank_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group bank-fields">
                                <label for="reference_number">Reference Number</label>
                                <input type="text" name="reference_number" id="reference_number"
                                       class="form-control @error('reference_number') is-invalid @enderror"
                                       value="{{ old('reference_number') }}" placeholder="Transaction reference">
                                @error('reference_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6 form-group cheque-fields">
                                <label for="cheque_number">Cheque Number</label>
                                <input type="text" name="cheque_number" id="cheque_number"
                                       class="form-control @error('cheque_number') is-invalid @enderror"
                                       value="{{ old('cheque_number') }}" placeholder="Cheque number">
                                @error('cheque_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="form-control @error('notes') is-invalid @enderror"
                                      placeholder="Optional notes about this payment">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="text-right">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                            <button type="submit" class="btn btn-success" id="btn-submit">
                                <i class="mdi mdi-check"></i> Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary & History -->
            <div class="col-lg-5">
                <!-- Payment Summary -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-chart-bar"></i> Payment Summary</h5>

                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="summary-box total">
                                <div class="detail-label">Total Amount</div>
                                <div class="amount text-primary">₦{{ number_format($purchaseOrder->total_amount, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="summary-box paid">
                                <div class="detail-label">Amount Paid</div>
                                <div class="amount text-success">₦{{ number_format($purchaseOrder->amount_paid, 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="summary-box balance">
                                <div class="detail-label">Balance Due</div>
                                <div class="amount text-danger" id="balance-display">₦{{ number_format($purchaseOrder->balance_due, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Info -->
                <div class="detail-card">
                    <h5><i class="mdi mdi-information"></i> Order Information</h5>

                    <div class="mb-2">
                        <div class="detail-label">Supplier</div>
                        <div class="detail-value">{{ $purchaseOrder->supplier->company_name }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="detail-label">Destination Store</div>
                        <div class="detail-value">{{ $purchaseOrder->targetStore->store_name }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="detail-label">Order Date</div>
                        <div class="detail-value">{{ $purchaseOrder->created_at->format('M d, Y') }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="detail-label">Items Count</div>
                        <div class="detail-value">{{ $purchaseOrder->items->count() }} items</div>
                    </div>
                </div>

                <!-- Payment History -->
                @if($purchaseOrder->payments->count() > 0)
                <div class="detail-card">
                    <h5><i class="mdi mdi-history"></i> Payment History</h5>

                    <div class="table-responsive">
                        <table class="table table-sm payment-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseOrder->payments->sortByDesc('payment_date') as $payment)
                                <tr>
                                    <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                                    <td class="text-success">₦{{ number_format($payment->amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-secondary">{{ $payment->payment_method_label }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle bank/cheque fields based on payment method
    $('#payment_method').on('change', function() {
        const method = $(this).val();

        // Show/hide bank fields
        if (method === 'bank_transfer' || method === 'card') {
            $('.bank-fields').slideDown();
        } else {
            $('.bank-fields').slideUp();
        }

        // Show/hide cheque fields
        if (method === 'cheque') {
            $('.cheque-fields').slideDown();
            $('.bank-fields').slideDown(); // Cheques also need bank
        } else {
            $('.cheque-fields').slideUp();
        }
    }).trigger('change');

    // AJAX form submission
    $('#payment-form').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btn-submit');
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);

                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = '{{ route('inventory.purchase-orders.show', $purchaseOrder) }}';
                    }, 1500);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Record Payment');

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    toastr.error(xhr.responseJSON.message);
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    Object.values(xhr.responseJSON.errors).forEach(function(errors) {
                        errors.forEach(function(error) {
                            toastr.error(error);
                        });
                    });
                } else {
                    toastr.error('An error occurred. Please try again.');
                }
            }
        });
    });
});
</script>
@endpush
