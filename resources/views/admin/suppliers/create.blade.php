@extends('admin.layouts.app')
@section('title', 'Add Supplier')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Add Supplier')

@section('content')
<style>
    .form-section {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .form-section h5 {
        border-bottom: 2px solid #007bff;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <form method="POST" action="{{ route('suppliers.store') }}">
                    @csrf

                    <!-- Basic Information -->
                    <div class="form-section">
                        <h5><i class="mdi mdi-account-box"></i> Basic Information</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="company_name">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" name="company_name" id="company_name"
                                           class="form-control @error('company_name') is-invalid @enderror"
                                           value="{{ old('company_name') }}" required>
                                    @error('company_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact_person">Contact Person</label>
                                    <input type="text" name="contact_person" id="contact_person"
                                           class="form-control @error('contact_person') is-invalid @enderror"
                                           value="{{ old('contact_person') }}">
                                    @error('contact_person')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="phone">Phone <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" id="phone"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone') }}" required>
                                    @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="alt_phone">Alternative Phone</label>
                                    <input type="text" name="alt_phone" id="alt_phone"
                                           class="form-control @error('alt_phone') is-invalid @enderror"
                                           value="{{ old('alt_phone') }}">
                                    @error('alt_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" name="email" id="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email') }}">
                                    @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea name="address" id="address" rows="2"
                                      class="form-control @error('address') is-invalid @enderror">{{ old('address') }}</textarea>
                            @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="tax_number">Tax/VAT Number</label>
                                    <input type="text" name="tax_number" id="tax_number"
                                           class="form-control @error('tax_number') is-invalid @enderror"
                                           value="{{ old('tax_number') }}">
                                    @error('tax_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="payment_terms">Payment Terms</label>
                                    <select name="payment_terms" id="payment_terms" class="form-control">
                                        <option value="">Select...</option>
                                        <option value="cash" {{ old('payment_terms') == 'cash' ? 'selected' : '' }}>Cash on Delivery</option>
                                        <option value="net_7" {{ old('payment_terms') == 'net_7' ? 'selected' : '' }}>Net 7 Days</option>
                                        <option value="net_15" {{ old('payment_terms') == 'net_15' ? 'selected' : '' }}>Net 15 Days</option>
                                        <option value="net_30" {{ old('payment_terms') == 'net_30' ? 'selected' : '' }}>Net 30 Days</option>
                                        <option value="net_60" {{ old('payment_terms') == 'net_60' ? 'selected' : '' }}>Net 60 Days</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="credit_limit">Credit Limit (₦)</label>
                                    <input type="number" name="credit_limit" id="credit_limit"
                                           class="form-control @error('credit_limit') is-invalid @enderror"
                                           value="{{ old('credit_limit', 0) }}" step="0.01" min="0">
                                    @error('credit_limit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Information -->
                    <div class="form-section">
                        <h5><i class="mdi mdi-bank"></i> Bank Information</h5>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bank_name">Bank Name</label>
                                    <input type="text" name="bank_name" id="bank_name"
                                           class="form-control @error('bank_name') is-invalid @enderror"
                                           value="{{ old('bank_name') }}">
                                    @error('bank_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bank_account_number">Account Number</label>
                                    <input type="text" name="bank_account_number" id="bank_account_number"
                                           class="form-control @error('bank_account_number') is-invalid @enderror"
                                           value="{{ old('bank_account_number') }}">
                                    @error('bank_account_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="bank_account_name">Account Name</label>
                                    <input type="text" name="bank_account_name" id="bank_account_name"
                                           class="form-control @error('bank_account_name') is-invalid @enderror"
                                           value="{{ old('bank_account_name') }}">
                                    @error('bank_account_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-section">
                        <h5><i class="mdi mdi-note-text"></i> Additional Information</h5>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="form-control @error('notes') is-invalid @enderror"
                                      placeholder="Any additional notes about this supplier...">{{ old('notes') }}</textarea>
                            @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="status" name="status" value="1" checked>
                                <label class="custom-control-label" for="status">Active Supplier</label>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check"></i> Create Supplier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
