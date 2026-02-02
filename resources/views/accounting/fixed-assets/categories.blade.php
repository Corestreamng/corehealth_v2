{{--
    Fixed Asset Categories
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Asset Categories')
@section('page_name', 'Accounting')
@section('subpage_name', 'Asset Categories')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Fixed Assets', 'url' => route('accounting.fixed-assets.index'), 'icon' => 'mdi-domain'],
        ['label' => 'Categories', 'url' => '#', 'icon' => 'mdi-folder-star']
    ]
])

<style>
.category-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #667eea;
}
.category-card.inactive {
    border-left-color: #ccc;
    opacity: 0.7;
}
.category-card h5 {
    margin-bottom: 5px;
}
.category-card .code {
    color: #666;
    font-size: 0.9rem;
}
.category-card .stats {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    margin-top: 15px;
}
.account-badge {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    margin-right: 5px;
    display: inline-block;
    margin-top: 5px;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Asset Categories</h4>
                    <small class="text-muted">Manage fixed asset categories with depreciation settings</small>
                </div>
                <div>
                    <a href="{{ route('accounting.fixed-assets.index') }}" class="btn btn-outline-secondary mr-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Assets
                    </a>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
                        <i class="mdi mdi-plus mr-1"></i> Add Category
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($categories as $category)
            <div class="col-md-6 col-lg-4">
                <div class="category-card {{ !$category->is_active ? 'inactive' : '' }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5>{{ $category->name }}</h5>
                            <span class="code">{{ $category->code }}</span>
                        </div>
                        <div>
                            @if(!$category->is_active)
                                <span class="badge badge-secondary">Inactive</span>
                            @elseif(!$category->is_depreciable)
                                <span class="badge badge-info">Non-Depreciable</span>
                            @else
                                <span class="badge badge-success">Active</span>
                            @endif
                        </div>
                    </div>

                    @if($category->description)
                        <p class="text-muted small mt-2 mb-0">{{ $category->description }}</p>
                    @endif

                    <div class="stats">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h5 mb-0">{{ $category->fixed_assets_count }}</div>
                                <small class="text-muted">Assets</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 mb-0">{{ $category->default_useful_life_years }}y</div>
                                <small class="text-muted">Useful Life</small>
                            </div>
                            <div class="col-4">
                                <div class="h5 mb-0">{{ $category->default_salvage_percentage ?? 0 }}%</div>
                                <small class="text-muted">Salvage</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <span class="account-badge">
                            <i class="mdi mdi-folder-outline mr-1"></i>
                            Asset: {{ $category->assetAccount?->account_code ?? 'N/A' }}
                        </span>
                        <span class="account-badge">
                            <i class="mdi mdi-trending-down mr-1"></i>
                            Accum: {{ $category->depreciationAccount?->account_code ?? 'N/A' }}
                        </span>
                        <span class="account-badge">
                            <i class="mdi mdi-cash-minus mr-1"></i>
                            Exp: {{ $category->expenseAccount?->account_code ?? 'N/A' }}
                        </span>
                    </div>

                    <div class="mt-3 d-flex justify-content-between">
                        <small class="text-muted">
                            {{ ucfirst(str_replace('_', ' ', $category->default_depreciation_method)) }}
                        </small>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="mdi mdi-information-outline mr-2"></i>
                    No asset categories found. Click "Add Category" to create one.
                </div>
            </div>
        @endforelse
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-folder-plus mr-2"></i>Add Asset Category</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('accounting.fixed-assets.categories.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Category Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" required
                                       placeholder="e.g., COMP" maxlength="20">
                                <small class="text-muted">Unique identifier</small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Category Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       placeholder="e.g., Computer Equipment">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional description"></textarea>
                    </div>

                    <hr>
                    <h6>GL Accounts</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Asset Account <span class="text-danger">*</span></label>
                                <select name="asset_account_id" class="form-control select2-modal" required>
                                    <option value="">Select Account</option>
                                    @php
                                        $accounts = \App\Models\Accounting\Account::where('is_active', true)
                                            ->where('account_type', 'Asset')
                                            ->orderBy('account_code')
                                            ->get();
                                    @endphp
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Asset value account</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accum. Depreciation Account <span class="text-danger">*</span></label>
                                <select name="depreciation_account_id" class="form-control select2-modal" required>
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Contra-asset account</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Expense Account <span class="text-danger">*</span></label>
                                <select name="expense_account_id" class="form-control select2-modal" required>
                                    <option value="">Select Account</option>
                                    @php
                                        $expenseAccounts = \App\Models\Accounting\Account::where('is_active', true)
                                            ->where('account_type', 'Expense')
                                            ->orderBy('account_code')
                                            ->get();
                                    @endphp
                                    @foreach($expenseAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Depreciation expense</small>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6>Depreciation Settings</h6>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Default Useful Life (Years) <span class="text-danger">*</span></label>
                                <input type="number" name="default_useful_life_years" class="form-control"
                                       required min="1" max="100" value="5">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Depreciation Method <span class="text-danger">*</span></label>
                                <select name="default_depreciation_method" class="form-control" required>
                                    <option value="straight_line">Straight Line</option>
                                    <option value="declining_balance">Declining Balance</option>
                                    <option value="double_declining">Double Declining Balance</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Default Salvage %</label>
                                <input type="number" name="default_salvage_percentage" class="form-control"
                                       min="0" max="100" step="0.01" value="10">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="is_depreciable"
                                   name="is_depreciable" value="1" checked>
                            <label class="custom-control-label" for="is_depreciable">
                                Assets in this category are depreciable
                            </label>
                        </div>
                        <small class="text-muted">Uncheck for non-depreciable assets like land</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2 in modal
    $('#addCategoryModal').on('shown.bs.modal', function() {
        $('.select2-modal').select2({
            dropdownParent: $('#addCategoryModal'),
            placeholder: 'Select an option',
            width: '100%'
        });
    });
});
</script>
@endpush
