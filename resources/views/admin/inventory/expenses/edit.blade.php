@extends('admin.layouts.app')
@section('title', 'Edit Expense')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Edit Expense')

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Edit Expense</h4>
                        <span class="badge badge-warning px-3 py-2">{{ ucfirst($expense->status) }}</span>
                    </div>
                    <div class="card-body">
                        @if($expense->status !== 'pending')
                        <div class="alert alert-warning">
                            <i class="mdi mdi-alert"></i> Only pending expenses can be edited.
                        </div>
                        @else
                        <form method="POST" action="{{ route('inventory.expenses.update', $expense) }}">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="description">Description <span class="text-danger">*</span></label>
                                        <textarea name="description" id="description" rows="2"
                                                  class="form-control @error('description') is-invalid @enderror"
                                                  required>{{ old('description', $expense->description) }}</textarea>
                                        @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="category">Category <span class="text-danger">*</span></label>
                                        <select name="category" id="category"
                                                class="form-control @error('category') is-invalid @enderror" required>
                                            <option value="">Select Category</option>
                                            @foreach($categories as $key => $label)
                                            <option value="{{ $key }}" {{ old('category', $expense->category) == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('category')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="amount">Amount <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">₦</span>
                                            </div>
                                            <input type="number" name="amount" id="amount" step="0.01" min="0"
                                                   class="form-control @error('amount') is-invalid @enderror"
                                                   value="{{ old('amount', $expense->amount) }}" required>
                                        </div>
                                        @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="expense_date">Expense Date <span class="text-danger">*</span></label>
                                        <input type="date" name="expense_date" id="expense_date"
                                               class="form-control @error('expense_date') is-invalid @enderror"
                                               value="{{ old('expense_date', $expense->expense_date->format('Y-m-d')) }}" required>
                                        @error('expense_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payment_method">Payment Method</label>
                                        <select name="payment_method" id="payment_method" class="form-control">
                                            <option value="">Select Method</option>
                                            <option value="cash" {{ old('payment_method', $expense->payment_method) == 'cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="bank_transfer" {{ old('payment_method', $expense->payment_method) == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="cheque" {{ old('payment_method', $expense->payment_method) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="card" {{ old('payment_method', $expense->payment_method) == 'card' ? 'selected' : '' }}>Card</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vendor">Vendor/Payee</label>
                                        <input type="text" name="vendor" id="vendor" class="form-control"
                                               value="{{ old('vendor', $expense->vendor) }}"
                                               placeholder="Who was paid?">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="invoice_number">Invoice/Receipt Number</label>
                                        <input type="text" name="invoice_number" id="invoice_number" class="form-control"
                                               value="{{ old('invoice_number', $expense->invoice_number) }}"
                                               placeholder="Reference number">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" rows="2" class="form-control"
                                          placeholder="Additional notes...">{{ old('notes', $expense->notes) }}</textarea>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('inventory.expenses.show', $expense) }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save"></i> Update Expense
                                </button>
                            </div>
                        </form>
                        @endif
                    </div>
                </div>

                <!-- Expense Info Summary -->
                <div class="card-modern mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Expense Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1"><small class="text-muted">Created By</small></p>
                                <p class="mb-3">{{ $expense->createdBy->name ?? 'Unknown' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><small class="text-muted">Created On</small></p>
                                <p class="mb-3">{{ $expense->created_at->format('M d, Y H:i') }}</p>
                            </div>
                        </div>
                        @if($expense->reference)
                        <div class="row">
                            <div class="col-md-12">
                                <p class="mb-1"><small class="text-muted">Reference</small></p>
                                @php
                                    $refType = class_basename($expense->reference_type);
                                @endphp
                                @if($refType === 'PurchaseOrder')
                                <p class="mb-0">
                                    <a href="{{ route('inventory.purchase-orders.show', $expense->reference_id) }}">
                                        {{ $expense->reference->po_number ?? 'PO #' . $expense->reference_id }}
                                    </a>
                                </p>
                                @else
                                <p class="mb-0">{{ $refType }} #{{ $expense->reference_id }}</p>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
