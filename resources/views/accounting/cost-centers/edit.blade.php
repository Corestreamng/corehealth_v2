{{--
    Edit Cost Center
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Edit Cost Center: ' . $costCenter->code)
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Cost Center')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => route('accounting.cost-centers.index'), 'icon' => 'mdi-sitemap'],
        ['label' => $costCenter->code, 'url' => route('accounting.cost-centers.show', $costCenter->id), 'icon' => 'mdi-eye'],
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
.info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f1f1; }
.info-row:last-child { border-bottom: none; }
.info-row .label { color: #6c757d; }
.info-row .value { font-weight: 500; color: #333; }
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

    <form action="{{ route('accounting.cost-centers.update', $costCenter->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <!-- Read-Only Info -->
                <div class="form-section">
                    <h6><i class="mdi mdi-lock mr-2"></i>Fixed Information</h6>
                    <div class="alert alert-info mb-3">
                        <i class="mdi mdi-information"></i> Code and type cannot be changed after creation to maintain data integrity.
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="info-row">
                                <span class="label">Code</span>
                                <span class="value">{{ $costCenter->code }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-row">
                                <span class="label">Type</span>
                                <span class="value">{{ ucfirst($costCenter->center_type) }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-row">
                                <span class="label">Hierarchy Level</span>
                                <span class="value">{{ $costCenter->hierarchy_level }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Editable Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-pencil mr-2"></i>Editable Information</h6>
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $costCenter->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $costCenter->description) }}</textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active"
                                   value="1" {{ old('is_active', $costCenter->is_active) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                        <small class="text-muted">Inactive cost centers won't appear in selection dropdowns</small>
                    </div>
                </div>

                <!-- Organization -->
                <div class="form-section">
                    <h6><i class="mdi mdi-account-group mr-2"></i>Organization</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department_id" class="form-control select2">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $costCenter->department_id) == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manager</label>
                                <select name="manager_user_id" class="form-control select2">
                                    <option value="">Select Manager</option>
                                    @foreach($managers as $user)
                                        <option value="{{ $user->id }}" {{ old('manager_user_id', $costCenter->manager_user_id) == $user->id ? 'selected' : '' }}>
                                            {{ $user->surname }} {{ $user->firstname }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Parent Cost Center</label>
                        <select name="parent_cost_center_id" class="form-control select2">
                            <option value="">None (Top Level)</option>
                            @foreach($parentCenters as $center)
                                @if($center->id != $costCenter->id)
                                    <option value="{{ $center->id }}" {{ old('parent_cost_center_id', $costCenter->parent_cost_center_id) == $center->id ? 'selected' : '' }}>
                                        {{ $center->code }} - {{ $center->name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <small class="text-muted">Changing parent will recalculate hierarchy level</small>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="form-section">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Save Changes
                    </button>
                    <a href="{{ route('accounting.cost-centers.show', $costCenter->id) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <div class="form-section">
                    <h6><i class="mdi mdi-chart-line mr-2"></i>Statistics</h6>
                    <div class="info-row">
                        <span class="label">Created</span>
                        <span class="value">{{ $costCenter->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Last Updated</span>
                        <span class="value">{{ $costCenter->updated_at->format('M d, Y') }}</span>
                    </div>
                    @if($costCenter->children->count() > 0)
                    <div class="info-row">
                        <span class="label">Child Centers</span>
                        <span class="value">{{ $costCenter->children->count() }}</span>
                    </div>
                    @endif
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
