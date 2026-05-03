@extends('admin.layouts.app')
@section('title', 'Create Clinic')
@section('page_name', 'Clinic')
@section('subpage_name', 'Create Clinic')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card-modern shadow-sm border-0">
                    <div class="card-header-modern">
                        <h4 class="mb-1 fw-bold text-dark">
                            <i class="mdi mdi-hospital-building text-primary"></i> Create New Clinic
                        </h4>
                        <p class="text-muted mb-0 small">Enter the name of the new clinic or department.</p>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('clinics.store') }}" method="POST">
                            @csrf
                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">Clinic Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-modern" id="name" name="name" required placeholder="e.g. Ophthalmology, Pediatrics">
                                <small class="text-muted">Specialized vitals metrics can be configured after creation.</small>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="{{ route('clinics.index') }}" class="btn btn-light me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    <i class="mdi mdi-check"></i> Create Clinic
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
