@extends('admin.layouts.app')

@section('title', 'Billing Organizations')

@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-office-building"></i> Billing Organizations</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#organizationModal" onclick="resetForm()">
                        <i class="mdi mdi-plus"></i> Add Organization
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="organizations-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Credit Limit</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Organization Modal -->
    <div class="modal fade" id="organizationModal" tabindex="-1" aria-labelledby="organizationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="organizationForm">
                    @csrf
                    <input type="hidden" name="org_id" id="org_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="organizationModalLabel"><i class="mdi mdi-office-building"></i> <span>Add Organization</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Organization Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="credit_limit" class="form-label">Credit Limit (₦)</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="credit_limit" name="credit_limit" value="0">
                                <small class="text-muted">Maximum amount this organization can owe before payments are blocked. Set to 0 for unlimited.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="save-btn">Save Organization</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@include('admin.organizations.partials._scripts')
