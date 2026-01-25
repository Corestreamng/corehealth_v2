@extends('admin.layouts.app')
@section('title', 'Create Department')
@section('page_name', 'Administration')
@section('subpage_name', 'Create Department')

@section('style')
@php
    $primaryColor = appsettings()->hos_color ?? '#011b33';
@endphp
<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-light: {{ $primaryColor }}15;
    }
</style>
<link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8 col-md-10 mx-auto">
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">Create Department</h2>
                        <p class="text-muted mb-0">Add a new organizational department</p>
                    </div>
                    <a href="{{ route('departments.index') }}" class="btn btn-light border">
                        <i class="mdi mdi-arrow-left"></i> Back
                    </a>
                </div>
                <div class="card-body p-4">
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <form action="{{ route('departments.store') }}" method="POST">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label-modern">Department Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-modern" name="name" value="{{ old('name') }}" placeholder="e.g. Nursing Department" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern">Code</label>
                                <input type="text" class="form-control form-control-modern" name="code" value="{{ old('code') }}" placeholder="e.g. NURS" maxlength="20">
                                <small class="text-muted">Short code for reference</small>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <label class="form-label-modern">Description</label>
                                <textarea class="form-control form-control-modern" name="description" rows="3" placeholder="Brief description of the department...">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-8">
                                <label class="form-label-modern">Head of Department</label>
                                <select class="form-control form-control-modern select2" name="head_of_department_id">
                                    <option value="">Select Head of Department</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('head_of_department_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-modern">&nbsp;</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_active">
                                        <span class="font-weight-bold">Active</span>
                                        <small class="d-block text-muted">Department is available for assignment</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('departments.index') }}" class="btn btn-light border px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary-modern px-4">
                                <i class="mdi mdi-check mr-1"></i> Create Department
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: 'Select Head of Department',
            allowClear: true
        });
    });
</script>
@endsection
