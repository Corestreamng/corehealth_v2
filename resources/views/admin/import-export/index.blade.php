@extends('admin.layouts.app')

@section('content')
<style>
    .import-export-container {
        padding: 1.5rem;
    }

    .section-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .section-card-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .section-card-header i {
        font-size: 1.5rem;
    }

    .section-card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .section-card-body {
        padding: 1.5rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        padding: 1.25rem;
        text-align: center;
    }

    .stat-card.products {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .stat-card.services {
        background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
    }

    .stat-card.staff {
        background: linear-gradient(135deg, #4776E6 0%, #8E54E9 100%);
    }

    .stat-card.patients {
        background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
    }

    .stat-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
    }

    .stat-card p {
        margin: 0.5rem 0 0;
        opacity: 0.9;
    }

    .nav-tabs-custom {
        border-bottom: 2px solid #e9ecef;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        color: #6c757d;
        position: relative;
    }

    .nav-tabs-custom .nav-link:hover {
        color: #495057;
    }

    .nav-tabs-custom .nav-link.active {
        color: #667eea;
        background: transparent;
    }

    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: #667eea;
    }

    .import-section, .export-section {
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .import-section h6, .export-section h6 {
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .template-download {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        background: #e7f3ff;
        border-radius: 6px;
        margin-bottom: 1rem;
    }

    .template-download i {
        color: #0d6efd;
    }

    .file-upload-zone {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }

    .file-upload-zone:hover {
        border-color: #667eea;
        background: #f8f9ff;
    }

    .file-upload-zone.dragover {
        border-color: #667eea;
        background: #f0f4ff;
    }

    .file-upload-zone input[type="file"] {
        display: none;
    }

    .file-upload-zone i {
        font-size: 3rem;
        color: #adb5bd;
        margin-bottom: 1rem;
    }

    .file-upload-zone p {
        margin: 0;
        color: #6c757d;
    }

    .file-selected {
        background: #d4edda;
        border-color: #28a745;
    }

    .file-selected i {
        color: #28a745;
    }

    .error-list {
        max-height: 200px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #f5c6cb;
        border-radius: 6px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .error-list li {
        color: #721c24;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .btn-import {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 6px;
        font-weight: 500;
    }

    .btn-import:hover {
        opacity: 0.9;
        color: white;
    }

    .btn-export {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 6px;
        font-weight: 500;
    }

    .btn-export:hover {
        opacity: 0.9;
        color: white;
    }

    .btn-template {
        background: #e7f3ff;
        border: 1px solid #0d6efd;
        color: #0d6efd;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-weight: 500;
    }

    .btn-template:hover {
        background: #0d6efd;
        color: white;
    }

    .alert-import-errors {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
    }

    .filter-row {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .filter-row .form-group {
        margin-bottom: 0;
        min-width: 200px;
    }

    /* Progress Bar Styles */
    .import-progress-container {
        display: none;
        margin-top: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .import-progress-container.active {
        display: block;
    }

    .progress-wrapper {
        margin-bottom: 0.75rem;
    }

    .progress {
        height: 24px;
        border-radius: 12px;
        background-color: #e9ecef;
        overflow: hidden;
    }

    .progress-bar {
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .progress-bar.bg-success {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;
    }

    .progress-bar.bg-danger {
        background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%) !important;
    }

    .progress-bar.bg-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    }

    .progress-stats {
        display: flex;
        justify-content: space-between;
        font-size: 0.875rem;
        color: #6c757d;
    }

    .progress-stats .stat-item {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .import-result-panel {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 8px;
        display: none;
    }

    .import-result-panel.success {
        display: block;
        background: #d4edda;
        border: 1px solid #c3e6cb;
    }

    .import-result-panel.error {
        display: block;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
    }

    .btn-cancel-import {
        background: #dc3545;
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.875rem;
    }

    .btn-cancel-import:hover {
        background: #c82333;
        color: white;
    }

    .btn-cancel-import:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
</style>

<div class="import-export-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-database-import-outline"></i> Data Import / Export</h4>
            <p class="text-muted mb-0">Bulk import and export data using Excel (XLSX) files with dropdown validations</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card products">
            <h2>{{ number_format($stats['products']) }}</h2>
            <p><i class="mdi mdi-pill"></i> Products</p>
        </div>
        <div class="stat-card services">
            <h2>{{ number_format($stats['services']) }}</h2>
            <p><i class="mdi mdi-medical-bag"></i> Services</p>
        </div>
        <div class="stat-card staff">
            <h2>{{ number_format($stats['staff']) }}</h2>
            <p><i class="mdi mdi-account-group"></i> Staff</p>
        </div>
        <div class="stat-card patients">
            <h2>{{ number_format($stats['patients']) }}</h2>
            <p><i class="mdi mdi-account-multiple"></i> Patients</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('import_report'))
        @php $report = session('import_report'); @endphp
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="alert-heading mb-2">
                        <i class="mdi mdi-clipboard-check"></i> {{ $report['type'] }} Import Report
                    </h5>
                    <div class="row g-3">
                        <div class="col-auto">
                            <span class="badge bg-success fs-6 py-2 px-3">
                                <i class="mdi mdi-plus-circle"></i> {{ $report['created'] }} Created
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-warning text-dark fs-6 py-2 px-3">
                                <i class="mdi mdi-pencil-circle"></i> {{ $report['updated'] }} Updated
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-secondary fs-6 py-2 px-3">
                                <i class="mdi mdi-skip-next-circle"></i> {{ $report['skipped'] }} Skipped
                            </span>
                        </div>
                    </div>
                    <div class="mt-2 text-muted small">
                        <i class="mdi mdi-timer"></i> Processed {{ $report['total_rows'] }} rows in {{ $report['batches_processed'] }} batches
                        ({{ $report['duration'] }} seconds)
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @if(!empty($report['errors']))
                <hr class="my-2">
                <details class="mt-2">
                    <summary class="text-warning cursor-pointer" style="cursor:pointer">
                        <i class="mdi mdi-alert"></i> {{ count($report['errors']) }} Issue(s) - Click to expand
                    </summary>
                    <ul class="mt-2 mb-0 ps-3 small" style="max-height: 200px; overflow-y: auto;">
                        @foreach($report['errors'] as $error)
                            <li class="text-danger">{{ $error }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="alert-import-errors">
            <h6><i class="mdi mdi-alert"></i> Import Warnings ({{ count(session('import_errors')) }} issues)</h6>
            <ul class="error-list mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Main Content Tabs -->
    <div class="section-card">
        <ul class="nav nav-tabs nav-tabs-custom" id="importExportTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="products-tab" data-bs-toggle="tab" href="#products" role="tab">
                    <i class="mdi mdi-pill"></i> Products/Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="services-tab" data-bs-toggle="tab" href="#services" role="tab">
                    <i class="mdi mdi-medical-bag"></i> Services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="staff-tab" data-bs-toggle="tab" href="#staff" role="tab">
                    <i class="mdi mdi-account-group"></i> Staff
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="patients-tab" data-bs-toggle="tab" href="#patients" role="tab">
                    <i class="mdi mdi-account-multiple"></i> Patients
                </a>
            </li>
        </ul>

        <div class="tab-content p-4" id="importExportTabsContent">
            <!-- Products Tab -->
            <div class="tab-pane fade show active" id="products" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="import-section">
                            <h6><i class="mdi mdi-upload"></i> Import Products</h6>

                            <div class="template-download">
                                <i class="mdi mdi-file-excel text-success"></i>
                                <span>Download Excel template with dropdown lists for valid values</span>
                                <a href="{{ route('import-export.template.products') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download XLSX Template
                                </a>
                            </div>

                            <form id="products-import-form" data-type="products" class="async-import-form">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Default Store (optional)</label>
                                    <select name="default_store_id" class="form-select">
                                        <option value="">-- No default store --</option>
                                        @foreach($stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Stock will be added to this store if not specified in file</small>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('products-file').click()">
                                    <input type="file" id="products-file" name="file" accept=".xlsx,.xls,.csv" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">Excel (.xlsx) or CSV file (max 10MB)</p>
                                </div>

                                <div class="mb-3 mt-3">
                                    <label class="form-label">Duplicate Handling</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="products-update" value="update" checked>
                                        <label class="form-check-label" for="products-update">
                                            <i class="mdi mdi-pencil text-warning"></i> Update existing records
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="products-skip" value="skip">
                                        <label class="form-check-label" for="products-skip">
                                            <i class="mdi mdi-skip-next text-secondary"></i> Skip existing records
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import btn-start-import">
                                        <i class="mdi mdi-database-import"></i> Import Products
                                    </button>
                                </div>
                            </form>

                            <!-- Progress Container for Products -->
                            <div class="import-progress-container" id="products-progress-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold"><i class="mdi mdi-loading mdi-spin"></i> Importing Products...</span>
                                    <button type="button" class="btn btn-cancel-import btn-sm" data-type="products">
                                        <i class="mdi mdi-close"></i> Cancel
                                    </button>
                                </div>
                                <div class="progress-wrapper">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="progress-stats">
                                    <span class="stat-item"><i class="mdi mdi-check-circle text-success"></i> Created: <strong class="created-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-pencil-circle text-warning"></i> Updated: <strong class="updated-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-skip-next-circle text-secondary"></i> Skipped: <strong class="skipped-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-counter"></i> <strong class="processed-count">0</strong> / <strong class="total-count">0</strong></span>
                                </div>
                            </div>

                            <!-- Result Panel for Products -->
                            <div class="import-result-panel" id="products-result-panel">
                                <div class="result-content"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="export-section">
                            <h6><i class="mdi mdi-download"></i> Export Products</h6>

                            <form action="{{ route('import-export.export.products') }}" method="GET">
                                <div class="mb-3">
                                    <label class="form-label">Filter by Category (optional)</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">-- All Categories --</option>
                                        @foreach($categories['products'] as $category)
                                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Filter by Store (optional)</label>
                                    <select name="store_id" class="form-select">
                                        <option value="">-- All Stores --</option>
                                        @foreach($stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-export">
                                    <i class="mdi mdi-file-export"></i> Export Products
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Tab -->
            <div class="tab-pane fade" id="services" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="import-section">
                            <h6><i class="mdi mdi-upload"></i> Import Services</h6>

                            <div class="template-download">
                                <i class="mdi mdi-file-excel text-success"></i>
                                <span>Download Excel template with category dropdown</span>
                                <a href="{{ route('import-export.template.services') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download XLSX Template
                                </a>
                            </div>

                            <form id="services-import-form" data-type="services" class="async-import-form">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Duplicate Handling</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="services-duplicate-update" value="update" checked>
                                        <label class="form-check-label" for="services-duplicate-update">
                                            <i class="mdi mdi-pencil text-warning"></i> Update existing records
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="services-duplicate-skip" value="skip">
                                        <label class="form-check-label" for="services-duplicate-skip">
                                            <i class="mdi mdi-skip-next text-secondary"></i> Skip existing records
                                        </label>
                                    </div>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('services-file').click()">
                                    <input type="file" id="services-file" name="file" accept=".xlsx,.xls,.csv" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">Excel (.xlsx) or CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import btn-start-import">
                                        <i class="mdi mdi-database-import"></i> Import Services
                                    </button>
                                </div>
                            </form>

                            <!-- Progress Container for Services -->
                            <div class="import-progress-container" id="services-progress-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold"><i class="mdi mdi-loading mdi-spin"></i> Importing Services...</span>
                                    <button type="button" class="btn btn-cancel-import btn-sm" data-type="services">
                                        <i class="mdi mdi-close"></i> Cancel
                                    </button>
                                </div>
                                <div class="progress-wrapper">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="progress-stats">
                                    <span class="stat-item"><i class="mdi mdi-check-circle text-success"></i> Created: <strong class="created-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-pencil-circle text-warning"></i> Updated: <strong class="updated-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-skip-next-circle text-secondary"></i> Skipped: <strong class="skipped-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-counter"></i> <strong class="processed-count">0</strong> / <strong class="total-count">0</strong></span>
                                </div>
                            </div>

                            <!-- Result Panel for Services -->
                            <div class="import-result-panel" id="services-result-panel">
                                <div class="result-content"></div>
                            </div>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Note:</strong> The Excel template includes a dropdown list for service categories.<br>
                                    Select from existing categories or new ones will be auto-created.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="export-section">
                            <h6><i class="mdi mdi-download"></i> Export Services</h6>

                            <form action="{{ route('import-export.export.services') }}" method="GET">
                                <div class="mb-3">
                                    <label class="form-label">Filter by Category (optional)</label>
                                    <select name="category_id" class="form-select">
                                        <option value="">-- All Categories --</option>
                                        @foreach($categories['services'] as $category)
                                            <option value="{{ $category->id }}">{{ $category->category_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-export">
                                    <i class="mdi mdi-file-export"></i> Export Services
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Tab -->
            <div class="tab-pane fade" id="staff" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="import-section">
                            <h6><i class="mdi mdi-upload"></i> Import Staff</h6>

                            <div class="template-download">
                                <i class="mdi mdi-file-excel text-success"></i>
                                <span>Download Excel template with role, clinic & specialization dropdowns</span>
                                <a href="{{ route('import-export.template.staff') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download XLSX Template
                                </a>
                            </div>

                            <form id="staff-import-form" data-type="staff" class="async-import-form">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Default Password</label>
                                    <input type="text" name="default_password" class="form-control" value="password123" placeholder="Default password for new accounts">
                                    <small class="text-muted">Staff should change their password on first login</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Duplicate Handling</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="staff-duplicate-update" value="update" checked>
                                        <label class="form-check-label" for="staff-duplicate-update">
                                            <i class="mdi mdi-pencil text-warning"></i> Update existing records
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="staff-duplicate-skip" value="skip">
                                        <label class="form-check-label" for="staff-duplicate-skip">
                                            <i class="mdi mdi-skip-next text-secondary"></i> Skip existing records
                                        </label>
                                    </div>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('staff-file').click()">
                                    <input type="file" id="staff-file" name="file" accept=".xlsx,.xls,.csv" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">Excel (.xlsx) or CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import btn-start-import">
                                        <i class="mdi mdi-database-import"></i> Import Staff
                                    </button>
                                </div>
                            </form>

                            <!-- Progress Container for Staff -->
                            <div class="import-progress-container" id="staff-progress-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold"><i class="mdi mdi-loading mdi-spin"></i> Importing Staff...</span>
                                    <button type="button" class="btn btn-cancel-import btn-sm" data-type="staff">
                                        <i class="mdi mdi-close"></i> Cancel
                                    </button>
                                </div>
                                <div class="progress-wrapper">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="progress-stats">
                                    <span class="stat-item"><i class="mdi mdi-check-circle text-success"></i> Created: <strong class="created-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-pencil-circle text-warning"></i> Updated: <strong class="updated-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-skip-next-circle text-secondary"></i> Skipped: <strong class="skipped-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-counter"></i> <strong class="processed-count">0</strong> / <strong class="total-count">0</strong></span>
                                </div>
                            </div>

                            <!-- Result Panel for Staff -->
                            <div class="import-result-panel" id="staff-result-panel">
                                <div class="result-content"></div>
                            </div>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Note:</strong> The Excel template includes dropdown lists for:<br>
                                    • Role (select from available roles)<br>
                                    • Gender (Male/Female)<br>
                                    • Specialization (select from list)<br>
                                    • Clinic (select from list)
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="export-section">
                            <h6><i class="mdi mdi-download"></i> Export Staff</h6>

                            <form action="{{ route('import-export.export.staff') }}" method="GET">
                                <div class="mb-3">
                                    <label class="form-label">Filter by Role (optional)</label>
                                    <select name="role" class="form-select">
                                        <option value="">-- All Roles --</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-export">
                                    <i class="mdi mdi-file-export"></i> Export Staff
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patients Tab -->
            <div class="tab-pane fade" id="patients" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="import-section">
                            <h6><i class="mdi mdi-upload"></i> Import Patients</h6>

                            <div class="template-download">
                                <i class="mdi mdi-file-excel text-success"></i>
                                <span>Download Excel template with HMO, gender, blood group & genotype dropdowns</span>
                                <a href="{{ route('import-export.template.patients') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download XLSX Template
                                </a>
                            </div>

                            <form id="patients-import-form" data-type="patients" class="async-import-form">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Duplicate Handling</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="patients-duplicate-update" value="update" checked>
                                        <label class="form-check-label" for="patients-duplicate-update">
                                            <i class="mdi mdi-pencil text-warning"></i> Update existing records
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="duplicate_action" id="patients-duplicate-skip" value="skip">
                                        <label class="form-check-label" for="patients-duplicate-skip">
                                            <i class="mdi mdi-skip-next text-secondary"></i> Skip existing records
                                        </label>
                                    </div>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('patients-file').click()">
                                    <input type="file" id="patients-file" name="file" accept=".xlsx,.xls,.csv" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">Excel (.xlsx) or CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import btn-start-import">
                                        <i class="mdi mdi-database-import"></i> Import Patients
                                    </button>
                                </div>
                            </form>

                            <!-- Progress Container for Patients -->
                            <div class="import-progress-container" id="patients-progress-container">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold"><i class="mdi mdi-loading mdi-spin"></i> Importing Patients...</span>
                                    <button type="button" class="btn btn-cancel-import btn-sm" data-type="patients">
                                        <i class="mdi mdi-close"></i> Cancel
                                    </button>
                                </div>
                                <div class="progress-wrapper">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: 0%">0%</div>
                                    </div>
                                </div>
                                <div class="progress-stats">
                                    <span class="stat-item"><i class="mdi mdi-check-circle text-success"></i> Created: <strong class="created-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-pencil-circle text-warning"></i> Updated: <strong class="updated-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-skip-next-circle text-secondary"></i> Skipped: <strong class="skipped-count">0</strong></span>
                                    <span class="stat-item"><i class="mdi mdi-counter"></i> <strong class="processed-count">0</strong> / <strong class="total-count">0</strong></span>
                                </div>
                            </div>

                            <!-- Result Panel for Patients -->
                            <div class="import-result-panel" id="patients-result-panel">
                                <div class="result-content"></div>
                            </div>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Notes:</strong><br>
                                    • File numbers will be auto-generated if empty<br>
                                    • Use the dropdown lists in Excel for HMO, gender, blood group, genotype<br>
                                    • Date format: YYYY-MM-DD<br>
                                    • Allergies: comma-separated values<br>
                                    • Blank patients (file_no only) supported with default values
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="export-section">
                            <h6><i class="mdi mdi-download"></i> Export Patients</h6>

                            <form action="{{ route('import-export.export.patients') }}" method="GET">
                                <div class="mb-3">
                                    <label class="form-label">Filter by HMO (optional)</label>
                                    <select name="hmo_id" class="form-select">
                                        <option value="">-- All Patients --</option>
                                        @foreach($hmos as $hmo)
                                            <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-export">
                                    <i class="mdi mdi-file-export"></i> Export Patients
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="section-card">
        <div class="section-card-header">
            <i class="mdi mdi-help-circle-outline text-info"></i>
            <h5>Import Guidelines</h5>
        </div>
        <div class="section-card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="mdi mdi-file-excel text-success"></i> Excel Template Features</h6>
                    <ul class="small text-muted">
                        <li><strong>Dropdown Lists:</strong> Select valid values from dropdowns (no typing errors!)</li>
                        <li>Categories, Stores, Roles, HMOs are pre-populated</li>
                        <li>Gender, Blood Group, Genotype have fixed valid options</li>
                        <li>Date format: YYYY-MM-DD (e.g., 2026-01-22)</li>
                        <li>Maximum file size: 10MB</li>
                        <li>Also supports CSV format for backwards compatibility</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6><i class="mdi mdi-cog"></i> Import Behavior</h6>
                    <ul class="small text-muted">
                        <li><strong>Duplicate detection:</strong> Updates existing records instead of skipping</li>
                        <li><strong>Batch processing:</strong> Records committed in batches of 50</li>
                        <li><strong>Real-time progress:</strong> See live updates during import</li>
                        <li><strong>Cancellation:</strong> Long imports can be cancelled mid-way</li>
                        <li>Categories will be auto-created if they don't exist</li>
                        <li>Related records (Price, Stock, HMO Tariffs) are created automatically</li>
                        <li>Blank patients (file_no only) supported with default values</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Import state tracking
    const importState = {
        products: { importId: null, polling: null },
        services: { importId: null, polling: null },
        staff: { importId: null, polling: null },
        patients: { importId: null, polling: null }
    };

    // CSRF Token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                      document.querySelector('input[name="_token"]')?.value;

    // File upload zones
    document.querySelectorAll('.file-upload-zone').forEach(zone => {
        const input = zone.querySelector('input[type="file"]');

        // Drag and drop
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');

            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileDisplay(zone, input);
            }
        });

        // File selected
        input.addEventListener('change', () => {
            updateFileDisplay(zone, input);
        });
    });

    function updateFileDisplay(zone, input) {
        if (input.files.length > 0) {
            zone.classList.add('file-selected');
            const icon = zone.querySelector('i');
            icon.className = 'mdi mdi-check-circle';

            const text = zone.querySelector('p:first-of-type');
            text.innerHTML = `<strong>${input.files[0].name}</strong>`;

            const subtext = zone.querySelector('p:last-of-type');
            subtext.textContent = `${(input.files[0].size / 1024).toFixed(1)} KB`;
        }
    }

    // AJAX Import Forms
    document.querySelectorAll('.async-import-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const type = this.dataset.type;
            const fileInput = this.querySelector('input[type="file"]');

            if (!fileInput.files.length) {
                showToast('Please select a file to import.', 'warning');
                return;
            }

            // Prepare form data
            const formData = new FormData(this);

            // Disable form and show progress
            const submitBtn = this.querySelector('.btn-start-import');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Uploading...';

            const progressContainer = document.getElementById(`${type}-progress-container`);
            const resultPanel = document.getElementById(`${type}-result-panel`);

            // Reset and show progress container
            progressContainer.classList.add('active');
            resultPanel.classList.remove('success', 'error');
            resultPanel.style.display = 'none';
            resetProgress(progressContainer);

            try {
                // Upload file and queue import
                const response = await fetch(`/import-export/async-import/${type}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Upload failed');
                }

                // Store import ID and start polling
                importState[type].importId = data.import_id;
                updateProgress(progressContainer, {
                    total: data.total_rows,
                    processed: 0,
                    percentage: 0,
                    created: 0,
                    updated: 0,
                    skipped: 0
                });

                // Start polling for status
                importState[type].polling = setInterval(() => {
                    pollImportStatus(type);
                }, 1500);

            } catch (error) {
                console.error('Import upload error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i class="mdi mdi-database-import"></i> Import ${capitalizeFirst(type)}`;
                progressContainer.classList.remove('active');

                showResult(resultPanel, 'error', error.message);
            }
        });
    });

    // Poll import status
    async function pollImportStatus(type) {
        const importId = importState[type].importId;
        if (!importId) return;

        try {
            const response = await fetch(`/import-export/import-status/${importId}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to get status');
            }

            const progress = data.progress;
            const progressContainer = document.getElementById(`${type}-progress-container`);
            const resultPanel = document.getElementById(`${type}-result-panel`);
            const form = document.getElementById(`${type}-import-form`);
            const submitBtn = form.querySelector('.btn-start-import');

            // Update progress display
            updateProgress(progressContainer, progress);

            // Check if completed or failed
            if (progress.status === 'completed' || progress.status === 'failed' || progress.status === 'cancelled') {
                clearInterval(importState[type].polling);
                importState[type].polling = null;
                importState[type].importId = null;

                // Re-enable form
                submitBtn.disabled = false;
                submitBtn.innerHTML = `<i class="mdi mdi-database-import"></i> Import ${capitalizeFirst(type)}`;

                // Update progress bar to final state
                const progressBar = progressContainer.querySelector('.progress-bar');
                if (progress.status === 'completed') {
                    progressBar.classList.remove('bg-primary');
                    progressBar.classList.add('bg-success');
                    showResult(resultPanel, 'success', buildSuccessMessage(progress));
                } else if (progress.status === 'cancelled') {
                    progressBar.classList.remove('bg-primary');
                    progressBar.classList.add('bg-warning');
                    showResult(resultPanel, 'error', 'Import was cancelled.');
                } else {
                    progressBar.classList.remove('bg-primary');
                    progressBar.classList.add('bg-danger');
                    showResult(resultPanel, 'error', progress.error || 'Import failed.');
                }

                // Hide cancel button
                progressContainer.querySelector('.btn-cancel-import').disabled = true;

                // Reset file input
                resetFileInput(type);
            }

        } catch (error) {
            console.error('Status polling error:', error);
        }
    }

    // Cancel import buttons
    document.querySelectorAll('.btn-cancel-import').forEach(btn => {
        btn.addEventListener('click', async function() {
            const type = this.dataset.type;
            const importId = importState[type].importId;

            if (!importId) return;

            this.disabled = true;
            this.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Cancelling...';

            try {
                const response = await fetch(`/import-export/cancel-import/${importId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!data.success) {
                    this.disabled = false;
                    this.innerHTML = '<i class="mdi mdi-close"></i> Cancel';
                    showToast(data.error || 'Failed to cancel', 'error');
                }
                // If successful, the polling will detect the cancelled status

            } catch (error) {
                console.error('Cancel error:', error);
                this.disabled = false;
                this.innerHTML = '<i class="mdi mdi-close"></i> Cancel';
            }
        });
    });

    // Helper functions
    function updateProgress(container, progress) {
        const progressBar = container.querySelector('.progress-bar');
        const percentage = Math.round(progress.percentage || 0);

        progressBar.style.width = `${percentage}%`;
        progressBar.textContent = `${percentage}%`;

        container.querySelector('.created-count').textContent = progress.created || 0;
        container.querySelector('.updated-count').textContent = progress.updated || 0;
        container.querySelector('.skipped-count').textContent = progress.skipped || 0;
        container.querySelector('.processed-count').textContent = progress.processed || 0;
        container.querySelector('.total-count').textContent = progress.total || 0;
    }

    function resetProgress(container) {
        const progressBar = container.querySelector('.progress-bar');
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        progressBar.classList.remove('bg-success', 'bg-danger', 'bg-warning');
        progressBar.classList.add('bg-primary');

        container.querySelector('.created-count').textContent = '0';
        container.querySelector('.updated-count').textContent = '0';
        container.querySelector('.skipped-count').textContent = '0';
        container.querySelector('.processed-count').textContent = '0';
        container.querySelector('.total-count').textContent = '0';

        container.querySelector('.btn-cancel-import').disabled = false;
        container.querySelector('.btn-cancel-import').innerHTML = '<i class="mdi mdi-close"></i> Cancel';
    }

    function showResult(panel, type, message) {
        panel.classList.remove('success', 'error');
        panel.classList.add(type);
        panel.style.display = 'block';
        panel.querySelector('.result-content').innerHTML = message;
    }

    function buildSuccessMessage(progress) {
        let html = `
            <div class="d-flex align-items-start">
                <i class="mdi mdi-check-circle-outline text-success me-2" style="font-size: 1.5rem;"></i>
                <div>
                    <h6 class="mb-1">Import Completed Successfully</h6>
                    <div class="mb-2">
                        <span class="badge bg-success me-1">${progress.created} Created</span>
                        <span class="badge bg-warning text-dark me-1">${progress.updated} Updated</span>
                        <span class="badge bg-secondary">${progress.skipped} Skipped</span>
                    </div>
                    <small class="text-muted">
                        Processed ${progress.total} rows in ${progress.duration ? progress.duration + 's' : 'N/A'}
                    </small>
        `;

        if (progress.errors && progress.errors.length > 0) {
            html += `
                    <details class="mt-2">
                        <summary class="text-warning" style="cursor:pointer;">
                            <i class="mdi mdi-alert"></i> ${progress.errors.length} Warning(s)
                        </summary>
                        <ul class="mt-1 mb-0 ps-3 small" style="max-height: 150px; overflow-y: auto;">
                            ${progress.errors.map(err => `<li class="text-danger">${escapeHtml(err)}</li>`).join('')}
                        </ul>
                    </details>
            `;
        }

        html += `
                </div>
            </div>
        `;

        return html;
    }

    function resetFileInput(type) {
        const fileInput = document.getElementById(`${type}-file`);
        const zone = fileInput.closest('.file-upload-zone');

        fileInput.value = '';
        zone.classList.remove('file-selected');

        const icon = zone.querySelector('i');
        icon.className = 'mdi mdi-cloud-upload-outline';

        const text = zone.querySelector('p:first-of-type');
        text.innerHTML = '<strong>Click to upload</strong> or drag and drop';

        const subtext = zone.querySelector('p:last-of-type');
        subtext.textContent = 'Excel (.xlsx) or CSV file (max 10MB)';
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        // Simple toast notification - you can integrate with your toast library
        alert(message);
    }
});
</script>
@endsection
