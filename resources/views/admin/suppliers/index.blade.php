@extends('admin.layouts.app')
@section('title', 'Suppliers')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Suppliers')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<style>
    .supplier-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .stat-card {
        flex: 1;
        background: #fff;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
    }
    .stat-card h3 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 600;
    }
    .stat-card small {
        color: #6c757d;
    }
</style>
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Suppliers</h3>
                <p class="text-muted mb-0">Manage your product suppliers</p>
            </div>
            <div>
                <a href="{{ route('suppliers.reports.index') }}" class="btn btn-outline-info mr-2">
                    <i class="mdi mdi-chart-bar"></i> Reports
                </a>
                <a href="{{ route('suppliers.export') }}" class="btn btn-outline-secondary mr-2">
                    <i class="mdi mdi-download"></i> Export
                </a>
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary">
                    <i class="mdi mdi-plus"></i> Add Supplier
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="supplier-stats">
            <div class="stat-card">
                <h3 class="text-primary" id="total-suppliers">-</h3>
                <small>Total Suppliers</small>
            </div>
            <div class="stat-card">
                <h3 class="text-success" id="active-suppliers">-</h3>
                <small>Active Suppliers</small>
            </div>
            <div class="stat-card">
                <h3 class="text-info" id="total-batches">-</h3>
                <small>Total Batches</small>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="card-modern">
            <div class="card-body">
                <table id="suppliers-table" class="table table-sm table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Batches</th>
                            <th>POs</th>
                            <th>Outstanding</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/dataT/datatables.min.js') }}"></script>
@include('admin.suppliers.partials._scripts')
