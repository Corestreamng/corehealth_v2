@extends('admin.layouts.app')
@section('title', isset($expense) ? 'Edit Expense' : 'Create Expense')
@section('page_name', 'Inventory Management')
@section('subpage_name', isset($expense) ? 'Edit Expense' : 'Create Expense')

@section('content')
<style>
    .bank-fields { display: none; }
    .cheque-fields { display: none; }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card-modern">
                    <div class="card-header">
                        <h4 class="mb-0">{{ isset($expense) ? 'Edit Expense' : 'New Expense' }}</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST"
                              action="{{ isset($expense) ? route('inventory.expenses.update', $expense) : route('inventory.expenses.store') }}">
                            @csrf
                            @if(isset($expense))
                            @method('PUT')
                            @endif

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="description">Description <span class="text-danger">*</span></label>
                                        <textarea name="description" id="description" rows="2"
                                                  class="form-control @error('description') is-invalid @enderror"
                                                  required>{{ old('description', $expense->description ?? '') }}</textarea>
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
                                            <option value="{{ $key }}" {{ old('category', $expense->category ?? '') == $key ? 'selected' : '' }}>
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
                                                   value="{{ old('amount', $expense->amount ?? '') }}" required>
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
                                               value="{{ old('expense_date', isset($expense) ? $expense->expense_date->format('Y-m-d') : date('Y-m-d')) }}" required>
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
                                            <option value="cash" {{ old('payment_method', $expense->payment_method ?? '') == 'cash' ? 'selected' : '' }}>Cash</option>
                                            <option value="bank_transfer" {{ old('payment_method', $expense->payment_method ?? '') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                            <option value="cheque" {{ old('payment_method', $expense->payment_method ?? '') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                            <option value="card" {{ old('payment_method', $expense->payment_method ?? '') == 'card' ? 'selected' : '' }}>Card</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group bank-fields">
                                    <label for="bank_id">Bank</label>
                                    <select name="bank_id" id="bank_id" class="form-control @error('bank_id') is-invalid @enderror">
                                        <option value="">-- Select Bank --</option>
                                        @foreach($banks ?? [] as $bank)
                                            <option value="{{ $bank->id }}" {{ old('bank_id', $expense->bank_id ?? '') == $bank->id ? 'selected' : '' }}>
                                                {{ $bank->name }} - {{ $bank->account_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('bank_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 form-group cheque-fields">
                                    <label for="cheque_number">Cheque Number</label>
                                    <input type="text" name="cheque_number" id="cheque_number"
                                           class="form-control @error('cheque_number') is-invalid @enderror"
                                           value="{{ old('cheque_number', $expense->cheque_number ?? '') }}"
                                           placeholder="Cheque number">
                                    @error('cheque_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="payment_reference">Payment Reference</label>
                                        <input type="text" name="payment_reference" id="payment_reference" class="form-control"
                                               value="{{ old('payment_reference', $expense->payment_reference ?? '') }}"
                                               placeholder="Transaction reference or receipt number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier_id">Supplier (if applicable)</label>
                                        <select name="supplier_id" id="supplier_id" class="form-control">
                                            <option value="">-- Select Supplier --</option>
                                            @foreach($suppliers ?? [] as $supplier)
                                                <option value="{{ $supplier->id }}" {{ old('supplier_id', $expense->supplier_id ?? '') == $supplier->id ? 'selected' : '' }}>
                                                    {{ $supplier->company_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="store_id">Store</label>
                                        <select name="store_id" id="store_id" class="form-control">
                                            <option value="">-- Select Store --</option>
                                            @foreach($stores ?? [] as $store)
                                                <option value="{{ $store->id }}" {{ old('store_id', $expense->store_id ?? '') == $store->id ? 'selected' : '' }}>
                                                    {{ $store->store_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="title">Title <span class="text-danger">*</span></label>
                                        <input type="text" name="title" id="title"
                                               class="form-control @error('title') is-invalid @enderror"
                                               value="{{ old('title', $expense->title ?? '') }}"
                                               placeholder="Short title for this expense" required>
                                        @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea name="notes" id="notes" rows="2" class="form-control"
                                          placeholder="Additional notes...">{{ old('notes', $expense->notes ?? '') }}</textarea>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('inventory.expenses.index') }}" class="btn btn-secondary">
                                    <i class="mdi mdi-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save"></i> {{ isset($expense) ? 'Update' : 'Create' }} Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Toggle bank/cheque fields based on payment method
    $('#payment_method').on('change', function() {
        const method = $(this).val();

        // Show/hide bank fields for bank_transfer or card
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
});
</script>
@endsection
