{{--
    Edit Fixed Asset
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.6
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Edit ' . $fixedAsset->asset_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Fixed Asset')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Fixed Assets', 'url' => route('accounting.fixed-assets.index'), 'icon' => 'mdi-domain'],
        ['label' => $fixedAsset->asset_number, 'url' => route('accounting.fixed-assets.show', $fixedAsset), 'icon' => 'mdi-information'],
        ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
    ]
])

<style>
.form-section {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 25px;
    margin-bottom: 20px;
}
.form-section h6 {
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f1f1f1;
}
.read-only-field {
    background-color: #f8f9fa;
    cursor: not-allowed;
}
.book-value-display {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}
.book-value-display .amount {
    font-size: 2rem;
    font-weight: 700;
}
</style>

<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="alert alert-info">
        <i class="mdi mdi-information-outline mr-2"></i>
        <strong>Note:</strong> Financial information (cost, depreciation settings) cannot be edited after asset registration.
        You can only update administrative details.
    </div>

    <form action="{{ route('accounting.fixed-assets.update', $fixedAsset) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Read-Only Financial Info -->
                <div class="form-section">
                    <h6><i class="mdi mdi-currency-ngn mr-2"></i>Financial Information (Read-Only)</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Asset Number</label>
                                <input type="text" class="form-control read-only-field" value="{{ $fixedAsset->asset_number }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Category</label>
                                <input type="text" class="form-control read-only-field" value="{{ $fixedAsset->category?->name }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total Cost</label>
                                <input type="text" class="form-control read-only-field" value="₦{{ number_format($fixedAsset->total_cost, 2) }}" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accumulated Depreciation</label>
                                <input type="text" class="form-control read-only-field" value="₦{{ number_format($fixedAsset->accumulated_depreciation, 2) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Book Value</label>
                                <input type="text" class="form-control read-only-field" value="₦{{ number_format($fixedAsset->book_value, 2) }}" readonly>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Monthly Depreciation</label>
                                <input type="text" class="form-control read-only-field" value="₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Basic Information (Editable) -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Basic Information</h6>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Asset Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $fixedAsset->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control @error('status') is-invalid @enderror" required>
                                    @foreach($statusOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('status', $fixedAsset->status) == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Serial Number</label>
                                <input type="text" name="serial_number" class="form-control"
                                       value="{{ old('serial_number', $fixedAsset->serial_number) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Model Number</label>
                                <input type="text" name="model_number" class="form-control"
                                       value="{{ old('model_number', $fixedAsset->model_number) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control"
                                       value="{{ old('manufacturer', $fixedAsset->manufacturer) }}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $fixedAsset->description) }}</textarea>
                    </div>
                </div>

                <!-- Location & Assignment -->
                <div class="form-section">
                    <h6><i class="mdi mdi-map-marker mr-2"></i>Location & Assignment</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control"
                                       value="{{ old('location', $fixedAsset->location) }}" placeholder="e.g., Building A, Room 101">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department_id" class="form-control select2">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $fixedAsset->department_id) == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Custodian</label>
                                <select name="custodian_user_id" class="form-control select2">
                                    <option value="">Select Custodian</option>
                                    @foreach($custodians as $user)
                                        <option value="{{ $user->id }}" {{ old('custodian_user_id', $fixedAsset->custodian_user_id) == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warranty & Insurance -->
                <div class="form-section">
                    <h6><i class="mdi mdi-shield-check mr-2"></i>Warranty & Insurance</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warranty Expiry</label>
                                <input type="date" name="warranty_expiry_date" class="form-control"
                                       value="{{ old('warranty_expiry_date', $fixedAsset->warranty_expiry_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warranty Provider</label>
                                <input type="text" name="warranty_provider" class="form-control"
                                       value="{{ old('warranty_provider', $fixedAsset->warranty_provider) }}">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Insurance Policy Number</label>
                                <input type="text" name="insurance_policy_number" class="form-control"
                                       value="{{ old('insurance_policy_number', $fixedAsset->insurance_policy_number) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Insurance Expiry</label>
                                <input type="date" name="insurance_expiry_date" class="form-control"
                                       value="{{ old('insurance_expiry_date', $fixedAsset->insurance_expiry_date?->format('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-section">
                    <h6><i class="mdi mdi-note-text mr-2"></i>Notes</h6>
                    <div class="form-group mb-0">
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $fixedAsset->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Book Value Display -->
                <div class="book-value-display mb-4">
                    <div class="opacity-75">Net Book Value</div>
                    <div class="amount">₦{{ number_format($fixedAsset->book_value, 2) }}</div>
                    <div class="opacity-75 mt-2">
                        @php
                            $depPercent = $fixedAsset->depreciable_amount > 0
                                ? min(100, ($fixedAsset->accumulated_depreciation / $fixedAsset->depreciable_amount) * 100)
                                : 0;
                        @endphp
                        {{ number_format($depPercent, 1) }}% depreciated
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-section">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save Changes
                    </button>
                    <a href="{{ route('accounting.fixed-assets.show', $fixedAsset) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <!-- Key Dates (Read-Only) -->
                <div class="form-section">
                    <h6><i class="mdi mdi-calendar mr-2"></i>Key Dates</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Acquisition Date</span>
                        <span>{{ $fixedAsset->acquisition_date?->format('M d, Y') ?? 'N/A' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">In-Service Date</span>
                        <span>{{ $fixedAsset->in_service_date?->format('M d, Y') ?? 'N/A' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Last Depreciation</span>
                        <span>{{ $fixedAsset->last_depreciation_date?->format('M d, Y') ?? 'Never' }}</span>
                    </div>
                </div>

                <!-- Depreciation Info (Read-Only) -->
                <div class="form-section">
                    <h6><i class="mdi mdi-chart-bell-curve mr-2"></i>Depreciation</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Method</span>
                        <span>{{ ucfirst(str_replace('_', ' ', $fixedAsset->depreciation_method)) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Useful Life</span>
                        <span>{{ $fixedAsset->useful_life_years }} years</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Monthly Amount</span>
                        <span>₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });
});
</script>
@endpush
