@extends('admin.layouts.app')
@section('title', 'Supplier Reports')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'Supplier Reports')

@push('styles')
<style>
    .report-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 1.5rem;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .report-card .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
    .report-card h5 {
        margin-bottom: 0.5rem;
    }
    .report-card p {
        color: #6c757d;
        margin-bottom: 0;
    }
</style>
@endpush

@section('content')
<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-0">Supplier Reports</h3>
                <p class="text-muted mb-0">Analytics and insights about your suppliers</p>
            </div>
            <div>
                <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">
                    <i class="mdi mdi-arrow-left"></i> Back to Suppliers
                </a>
            </div>
        </div>

        <!-- Report Cards -->
        <div class="row">
            <div class="col-md-4">
                <a href="{{ route('suppliers.reports.performance') }}" class="text-decoration-none">
                    <div class="report-card text-center">
                        <div class="icon text-primary">
                            <i class="mdi mdi-chart-line"></i>
                        </div>
                        <h5>Performance Report</h5>
                        <p>Compare supplier performance, volumes, and values over time</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('suppliers.reports.batches') }}" class="text-decoration-none">
                    <div class="report-card text-center">
                        <div class="icon text-success">
                            <i class="mdi mdi-package-variant-closed"></i>
                        </div>
                        <h5>Batches Report</h5>
                        <p>View all batches received from suppliers with filters</p>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('suppliers.export') }}" class="text-decoration-none">
                    <div class="report-card text-center">
                        <div class="icon text-info">
                            <i class="mdi mdi-download"></i>
                        </div>
                        <h5>Export Suppliers</h5>
                        <p>Download supplier list as CSV for external analysis</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card-modern">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h2 class="text-primary mb-0">{{ \App\Models\Supplier::count() }}</h2>
                                <small class="text-muted">Total Suppliers</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-success mb-0">{{ \App\Models\Supplier::active()->count() }}</h2>
                                <small class="text-muted">Active Suppliers</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-info mb-0">{{ \App\Models\StockBatch::whereNotNull('supplier_id')->count() }}</h2>
                                <small class="text-muted">Supplier Batches</small>
                            </div>
                            <div class="col-md-3">
                                <h2 class="text-warning mb-0">
                                    ₦{{ number_format(\App\Models\StockBatch::whereNotNull('supplier_id')->selectRaw('SUM(initial_qty * cost_price) as total')->value('total') ?? 0, 0) }}
                                </h2>
                                <small class="text-muted">Total Supply Value</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
