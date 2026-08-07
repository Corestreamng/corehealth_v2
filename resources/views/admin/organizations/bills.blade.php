@extends('admin.layouts.app')

@section('title', 'Organization Bills')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-receipt"></i> Outstanding Bills for {{ $organization->name }}</h5>
                    <div>
                        <span class="badge bg-light text-dark me-2">Balance: ₦{{ number_format($organization->balance, 2) }}</span>
                        <a href="{{ route('organizations.show', $organization->id) }}" class="btn btn-sm btn-light">Back to Profile</a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ route('organizations.settle', $organization->id) }}" method="POST" id="settlementForm">
                        @csrf
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Total Amount</th>
                                        <th>Discount</th>
                                        <th>Outstanding</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bills as $bill)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="bill_ids[]" value="{{ $bill->id }}" class="form-check-input bill-checkbox" data-outstanding="{{ $bill->outstanding_amount }}">
                                            </td>
                                            <td>{{ $bill->created_at->format('M d, Y H:i') }}</td>
                                            <td>{{ $bill->patient ? $bill->patient->fullname : 'N/A' }}</td>
                                            <td>{{ $bill->service ? $bill->service->service_name : 'N/A' }}</td>
                                            <td>₦{{ number_format($bill->total_amount, 2) }}</td>
                                            <td>₦{{ number_format($bill->discount_amount, 2) }}</td>
                                            <td class="text-danger fw-bold">₦{{ number_format($bill->outstanding_amount, 2) }}</td>
                                            <td>
                                                <span class="badge bg-warning text-dark">{{ ucfirst($bill->status) }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4">No outstanding bills found for this organization.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if($bills->isNotEmpty())
                                    <tfoot>
                                        <tr class="bg-light fw-bold">
                                            <td colspan="6" class="text-end">Selected Total Outstanding:</td>
                                            <td colspan="2" class="text-danger" id="selectedTotalDisplay">₦0.00</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>

                        @if($bills->isNotEmpty())
                            <div class="card-modern bg-light">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Settlement Details</h5>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                            <select name="payment_method" id="payment_method" class="form-select" required>
                                                <option value="TRANSFER">Transfer</option>
                                                <option value="POS">POS</option>
                                                <option value="CASH">Cash</option>
                                                <option value="CHEQUE">Cheque</option>
                                                <option value="WAIVER">Waiver/Write-off</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3" id="bank-group">
                                            <label class="form-label">Bank <span class="text-danger">*</span></label>
                                            <select name="bank_id" id="bank_id" class="form-select" required>
                                                <option value="">Select Bank</option>
                                                @foreach($banks ?? [] as $bank)
                                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Amount Paid (₦) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" min="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Discount / Waiver (₦)</label>
                                            <input type="number" step="0.01" min="0" name="discount_amount" id="discount_amount" class="form-control" value="0">
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Notes / Reference</label>
                                            <input type="text" name="notes" class="form-control" placeholder="Optional transaction reference or note">
                                        </div>
                                    </div>
                                    <div class="mt-2 text-end">
                                        <button type="submit" class="btn btn-success" id="settleBtn" disabled>
                                            <i class="mdi mdi-check-circle"></i> Process Settlement
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.bill-checkbox');
            const selectedTotalDisplay = document.getElementById('selectedTotalDisplay');
            const amountPaidInput = document.getElementById('amount_paid');
            const settleBtn = document.getElementById('settleBtn');
            const paymentMethodSelect = document.getElementById('payment_method');
            const bankGroup = document.getElementById('bank-group');
            const bankSelect = document.getElementById('bank_id');

            function updateTotals() {
                let total = 0;
                let anyChecked = false;
                
                checkboxes.forEach(cb => {
                    if (cb.checked) {
                        total += parseFloat(cb.dataset.outstanding);
                        anyChecked = true;
                    }
                });

                if (selectedTotalDisplay) {
                    selectedTotalDisplay.textContent = '₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                
                if (amountPaidInput && anyChecked && paymentMethodSelect.value !== 'WAIVER') {
                    // Pre-fill amount paid with selected total
                    amountPaidInput.value = total.toFixed(2);
                } else if (amountPaidInput && paymentMethodSelect.value === 'WAIVER') {
                    amountPaidInput.value = '0.00';
                }

                if (settleBtn) {
                    settleBtn.disabled = !anyChecked;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateTotals();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked && selectAll) {
                        selectAll.checked = false;
                    } else if (selectAll && Array.from(checkboxes).every(c => c.checked)) {
                        selectAll.checked = true;
                    }
                    updateTotals();
                });
            });

            if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', function() {
                    if (this.value === 'CASH' || this.value === 'WAIVER') {
                        bankGroup.style.display = 'none';
                        bankSelect.removeAttribute('required');
                        bankSelect.value = '';
                    } else {
                        bankGroup.style.display = 'block';
                        bankSelect.setAttribute('required', 'required');
                    }
                    
                    if (this.value === 'WAIVER') {
                        let total = 0;
                        checkboxes.forEach(cb => {
                            if (cb.checked) total += parseFloat(cb.dataset.outstanding);
                        });
                        amountPaidInput.value = '0.00';
                        amountPaidInput.readOnly = true;
                        document.getElementById('discount_amount').value = total.toFixed(2);
                    } else {
                        amountPaidInput.readOnly = false;
                        updateTotals(); // reset amount paid
                        document.getElementById('discount_amount').value = '0';
                    }
                });
                
                // Trigger change to set initial state
                paymentMethodSelect.dispatchEvent(new Event('change'));
            }

            if (document.getElementById('settlementForm')) {
                document.getElementById('settlementForm').addEventListener('submit', function(e) {
                    const btn = document.getElementById('settleBtn');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Processing...';
                });
            }
        });
    </script>
@endsection
