{{--
    Create Bank Reconciliation
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 3
    Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT
--}}

@extends('admin.layouts.app')

@section('title', 'New Reconciliation')
@section('page_name', 'Accounting')
@section('subpage_name', 'New Reconciliation')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Bank Reconciliation', 'url' => route('accounting.bank-reconciliation.index'), 'icon' => 'mdi-bank-check'],
        ['label' => 'New', 'url' => '#', 'icon' => 'mdi-plus']
    ]
])

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-modern">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="mdi mdi-bank-check mr-2"></i>Start New Bank Reconciliation</h5>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <!-- DEBUG: Selected Bank ID = {{ $selectedBank->id }}, Name = {{ $selectedBank->name }} -->
                    <form action="{{ route('accounting.bank-reconciliation.store', ['bank' => $selectedBank->id]) }}" method="POST">
                        @csrf

                        <div class="alert alert-info">
                            <i class="mdi mdi-information mr-1"></i>
                            Reconciling: <strong>{{ $selectedBank->name }}</strong>. Enter the statement details below.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bank Account <span class="text-danger">*</span></label>
                                    <input type="hidden" name="bank_id" value="{{ $selectedBank->id }}">
                                    <input type="text" class="form-control" value="{{ $selectedBank->name }} - {{ $selectedBank->account_number }}" readonly>
                                    <small class="text-muted">To reconcile a different bank, go back and select from the list.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fiscal Period</label>
                                    <select name="fiscal_period_id" class="form-control select2 @error('fiscal_period_id') is-invalid @enderror">
                                        <option value="">-- Select Period --</option>
                                        @foreach($periods as $period)
                                            <option value="{{ $period->id }}" {{ old('fiscal_period_id') == $period->id ? 'selected' : '' }}>
                                                {{ $period->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fiscal_period_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="text-muted mb-3"><i class="mdi mdi-file-document mr-1"></i> Bank Statement Details</h6>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Statement Date <span class="text-danger">*</span></label>
                                    <input type="date" name="statement_date" class="form-control @error('statement_date') is-invalid @enderror"
                                        value="{{ old('statement_date', date('Y-m-d')) }}" required>
                                    @error('statement_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Period From <span class="text-danger">*</span></label>
                                    <input type="date" name="statement_period_from" class="form-control @error('statement_period_from') is-invalid @enderror"
                                        value="{{ old('statement_period_from', date('Y-m-01')) }}" required>
                                    @error('statement_period_from')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Period To <span class="text-danger">*</span></label>
                                    <input type="date" name="statement_period_to" class="form-control @error('statement_period_to') is-invalid @enderror"
                                        value="{{ old('statement_period_to', date('Y-m-t')) }}" required>
                                    @error('statement_period_to')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Statement Opening Balance <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₦</span>
                                        </div>
                                        <input type="number" name="statement_opening_balance" step="0.01"
                                            class="form-control @error('statement_opening_balance') is-invalid @enderror"
                                            value="{{ old('statement_opening_balance', 0) }}" required placeholder="0.00">
                                    </div>
                                    @error('statement_opening_balance')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Opening balance from bank statement</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Statement Closing Balance <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">₦</span>
                                        </div>
                                        <input type="number" name="statement_closing_balance" step="0.01"
                                            class="form-control @error('statement_closing_balance') is-invalid @enderror"
                                            value="{{ old('statement_closing_balance', 0) }}" required placeholder="0.00">
                                    </div>
                                    @error('statement_closing_balance')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">Closing balance from bank statement</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes...">{{ old('notes') }}</textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accounting.bank-reconciliation.index') }}" class="btn btn-secondary">
                                <i class="mdi mdi-arrow-left mr-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-check mr-1"></i> Create & Start Matching
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
});
</script>
@endpush
