@extends('admin.layouts.app')
@section('title', 'Edit Lease')
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Lease')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-view-dashboard'],
    ['label' => 'Leases', 'url' => route('accounting.leases.index'), 'icon' => 'mdi-file-document-edit'],
    ['label' => $lease->lease_number, 'url' => route('accounting.leases.show', $lease->id), 'icon' => 'mdi-eye'],
    ['label' => 'Edit', 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div id="content-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="alert alert-info mb-4">
                    <i class="mdi mdi-information-outline mr-2"></i>
                    <strong>Limited Edit Mode:</strong> Core financial terms (payment amount, lease term, rates) cannot be modified directly.
                    To change these values, use the <a href="{{ route('accounting.leases.modification', $lease->id) }}" class="alert-link">Lease Modification</a> feature
                    which creates a proper IFRS 16 remeasurement record.
                </div>

                <form action="{{ route('accounting.leases.update', $lease->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Lease Summary -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="mdi mdi-file-document-edit mr-2"></i>{{ $lease->lease_number }} - {{ $lease->leased_item }}
                            </h5>
                        </div>
                        <div class="card-body bg-light">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <small class="text-muted">Lease Type</small>
                                    <p class="mb-0"><strong>{{ ucfirst(str_replace('_', ' ', $lease->lease_type)) }}</strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Monthly Payment</small>
                                    <p class="mb-0"><strong>₦{{ number_format($lease->monthly_payment, 2) }}</strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Term</small>
                                    <p class="mb-0"><strong>{{ $lease->lease_term_months }} months</strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">End Date</small>
                                    <p class="mb-0"><strong>{{ \Carbon\Carbon::parse($lease->end_date)->format('M d, Y') }}</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Editable Fields -->
                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-account-edit mr-2"></i>Lessor Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_name">Lessor Name</label>
                                        <input type="text" name="lessor_name" id="lessor_name" class="form-control"
                                               value="{{ old('lessor_name', $lease->lessor_name) }}"
                                               placeholder="Enter lessor name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lessor_contact">Lessor Contact</label>
                                        <input type="text" name="lessor_contact" id="lessor_contact" class="form-control"
                                               value="{{ old('lessor_contact', $lease->lessor_contact) }}"
                                               placeholder="Phone/Email">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-map-marker mr-2"></i>Asset Location</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="asset_location">Asset Location</label>
                                        <input type="text" name="asset_location" id="asset_location" class="form-control"
                                               value="{{ old('asset_location', $lease->asset_location) }}"
                                               placeholder="Physical location of the leased asset">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="department_id">Department</label>
                                        <select name="department_id" id="department_id" class="form-control select2">
                                            <option value="">Select Department</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}" {{ $lease->department_id == $dept->id ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-modern mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="mdi mdi-note-text mr-2"></i>Notes</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <textarea name="notes" id="notes" class="form-control" rows="4"
                                          placeholder="Additional notes about this lease">{{ old('notes', $lease->notes) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('accounting.leases.show', $lease->id) }}" class="btn btn-outline-secondary">
                            <i class="mdi mdi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-content-save"></i> Save Changes
                        </button>
                    </div>
                </form>
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
        allowClear: true,
        placeholder: 'Select...',
        width: '100%'
    });
});
</script>
@endpush
