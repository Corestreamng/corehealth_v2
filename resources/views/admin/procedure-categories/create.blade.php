@extends('admin.layouts.app')
@section('title', 'Create Procedure Category')
@section('page_name', 'Settings')
@section('subpage_name', 'Create Procedure Category')
@section('content')
<section class="container">
    <div class="card-modern mb-3">
        <form method="POST" action="{{ route('procedure-categories.store') }}" class="form-horizontal">
            @csrf
            <div class="card-header bg-transparent">
                <h5 class="mb-0">
                    <i class="mdi mdi-hospital-box mr-2"></i>{{ __('Create Procedure Category') }}
                </h5>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group row">
                    <label for="name" class="col-md-2 col-form-label">
                        {{ __('Name') }} <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-10">
                        <input type="text" id="name" class="form-control @error('name') is-invalid @enderror"
                            name="name" value="{{ old('name') }}" placeholder="e.g., General Surgery" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group row">
                    <label for="code" class="col-md-2 col-form-label">
                        {{ __('Code') }} <span class="text-danger">*</span>
                    </label>
                    <div class="col-md-10">
                        <input type="text" id="code" class="form-control @error('code') is-invalid @enderror"
                            name="code" value="{{ old('code') }}" placeholder="e.g., GS"
                            maxlength="20" required style="text-transform: uppercase; max-width: 200px;">
                        <small class="text-muted">Short code for the category (max 20 characters)</small>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group row">
                    <label for="description" class="col-md-2 col-form-label">{{ __('Description') }}</label>
                    <div class="col-md-10">
                        <textarea id="description" class="form-control @error('description') is-invalid @enderror"
                            name="description" rows="3"
                            placeholder="Brief description of this procedure category...">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="form-group row">
                    <label for="status" class="col-md-2 col-form-label">{{ __('Status') }}</label>
                    <div class="col-md-10">
                        <div class="custom-control custom-switch mt-2">
                            <input type="checkbox" class="custom-control-input" id="status" name="status" value="1"
                                {{ old('status', true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="status">Active</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <div class="form-group row mb-0">
                    <div class="col-md-6">
                        <a href="{{ route('procedure-categories.index') }}" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                    </div>
                    <div class="col-md-6 text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Save Category
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Auto-uppercase code field
    $('#code').on('input', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>
@endsection
