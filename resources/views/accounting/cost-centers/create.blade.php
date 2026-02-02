{{--
    Create Cost Center
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
    Access: SUPERADMIN|ADMIN|ACCOUNTS
--}}

@extends('admin.layouts.app')

@section('title', 'Add Cost Center')
@section('page_name', 'Accounting')
@section('subpage_name', 'Add Cost Center')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Cost Centers', 'url' => route('accounting.cost-centers.index'), 'icon' => 'mdi-sitemap'],
        ['label' => 'Add New', 'url' => '#', 'icon' => 'mdi-plus']
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
.type-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.type-card:hover {
    border-color: #667eea;
}
.type-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%);
}
.type-card .icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
    display: block;
}
.type-card .icon i {
    display: inline-block;
}
.type-card.revenue .icon { color: #28a745; }
.type-card.cost .icon { color: #667eea; }
.type-card.service .icon { color: #17a2b8; }
.type-card.project .icon { color: #ffc107; }
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

    <form action="{{ route('accounting.cost-centers.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <!-- Center Type -->
                <div class="form-section">
                    <h6><i class="mdi mdi-shape mr-2"></i>Center Type</h6>
                    <div class="row">
                        @foreach($centerTypes as $key => $label)
                            <div class="col-md-3 mb-3">
                                <div class="type-card {{ $key }} {{ old('center_type') == $key ? 'selected' : '' }}"
                                     data-type="{{ $key }}">
                                    <div class="icon">
                                        @php
                                            $icons = [
                                                'revenue' => 'mdi-cash-multiple',
                                                'cost' => 'mdi-wallet-outline',
                                                'service' => 'mdi-tools',
                                                'project' => 'mdi-briefcase-outline',
                                            ];
                                        @endphp
                                        <i class="mdi {{ $icons[$key] ?? 'mdi-sitemap' }}"></i>
                                    </div>
                                    <div class="font-weight-bold">{{ $label }}</div>
                                    <small class="text-muted">
                                        @switch($key)
                                            @case('revenue')
                                                Tracks income
                                                @break
                                            @case('cost')
                                                Tracks expenses
                                                @break
                                            @case('service')
                                                Internal services
                                                @break
                                            @case('project')
                                                Project-based
                                                @break
                                        @endswitch
                                    </small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="center_type" id="center_type" value="{{ old('center_type') }}" required>
                    @error('center_type')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Basic Information -->
                <div class="form-section">
                    <h6><i class="mdi mdi-information-outline mr-2"></i>Basic Information</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Code <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                       value="{{ old('code') }}" required placeholder="e.g., CC001" maxlength="20">
                                <small class="text-muted">Unique identifier</small>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" required placeholder="e.g., Administration Department">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional description of this cost center">{{ old('description') }}</textarea>
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
                                        <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
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
                                        <option value="{{ $user->id }}" {{ old('manager_user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
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
                                <option value="{{ $center->id }}" {{ old('parent_cost_center_id') == $center->id ? 'selected' : '' }}>
                                    {{ $center->code }} - {{ $center->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Optional - create hierarchy of cost centers</small>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="form-section">
                    <h6><i class="mdi mdi-cog mr-2"></i>Actions</h6>
                    <button type="submit" class="btn btn-primary btn-block mb-2">
                        <i class="mdi mdi-content-save mr-1"></i> Create Cost Center
                    </button>
                    <a href="{{ route('accounting.cost-centers.index') }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-close mr-1"></i> Cancel
                    </a>
                </div>

                <div class="form-section">
                    <h6><i class="mdi mdi-help-circle mr-2"></i>Help</h6>
                    <p class="text-muted small mb-2">
                        <strong>Revenue Center:</strong> Tracks income-generating activities like departments that bill patients.
                    </p>
                    <p class="text-muted small mb-2">
                        <strong>Cost Center:</strong> Tracks expenses for departments like administration or maintenance.
                    </p>
                    <p class="text-muted small mb-2">
                        <strong>Service Center:</strong> Internal services that allocate costs to other departments.
                    </p>
                    <p class="text-muted small mb-0">
                        <strong>Project:</strong> Temporary centers for specific projects with defined budgets.
                    </p>
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

    // Type selection
    $('.type-card').on('click', function() {
        $('.type-card').removeClass('selected');
        $(this).addClass('selected');
        $('#center_type').val($(this).data('type'));
    });
});
</script>
@endpush
