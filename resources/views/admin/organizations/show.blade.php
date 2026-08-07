@extends('admin.layouts.app')

@section('title', 'Organization Details')

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="card-modern">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="mdi mdi-office-building"></i> {{ $organization->name }}</h5>
                </div>
                <div class="card-body">
                    <p><strong>Status:</strong> 
                        @if($organization->status)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </p>
                    <p><strong>Email:</strong> {{ $organization->email ?? 'N/A' }}</p>
                    <p><strong>Phone:</strong> {{ $organization->phone ?? 'N/A' }}</p>
                    <p><strong>Address:</strong> {{ $organization->address ?? 'N/A' }}</p>
                    <hr>
                    <p><strong>Credit Limit:</strong> ₦{{ number_format($organization->credit_limit, 2) }}</p>
                    <p><strong>Current Balance:</strong> ₦{{ number_format($organization->balance, 2) }}</p>
                    
                    <a href="{{ route('organizations.index') }}" class="btn btn-secondary mt-3">Back to List</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card-modern">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-receipt"></i> Recent Bills</h5>
                    <a href="{{ route('organizations.bills', $organization->id) }}" class="btn btn-sm btn-light">View All Bills</a>
                </div>
                <div class="card-body">
                    <p class="text-muted">Billing logic is currently being implemented. You will see organization bills here soon.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
