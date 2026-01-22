@extends('admin.layout.app')

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
</style>

<div class="import-export-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="mdi mdi-database-import-outline"></i> Data Import / Export</h4>
            <p class="text-muted mb-0">Bulk import and export data using CSV files</p>
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
                                <i class="mdi mdi-file-download-outline"></i>
                                <span>Download the template first to ensure correct format</span>
                                <a href="{{ route('import-export.template.products') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download Template
                                </a>
                            </div>

                            <form action="{{ route('import-export.import.products') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Default Store (optional)</label>
                                    <select name="default_store_id" class="form-select">
                                        <option value="">-- No default store --</option>
                                        @foreach($stores as $store)
                                            <option value="{{ $store->id }}">{{ $store->store_name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Stock will be added to this store if not specified in CSV</small>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('products-file').click()">
                                    <input type="file" id="products-file" name="file" accept=".csv,.txt" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import">
                                        <i class="mdi mdi-database-import"></i> Import Products
                                    </button>
                                </div>
                            </form>
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
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
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
                                <i class="mdi mdi-file-download-outline"></i>
                                <span>Download the template with sample data</span>
                                <a href="{{ route('import-export.template.services') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download Template
                                </a>
                            </div>

                            <form action="{{ route('import-export.import.services') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="file-upload-zone" onclick="document.getElementById('services-file').click()">
                                    <input type="file" id="services-file" name="file" accept=".csv,.txt" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import">
                                        <i class="mdi mdi-database-import"></i> Import Services
                                    </button>
                                </div>
                            </form>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Category Examples:</strong><br>
                                    • Laboratory - Hematology<br>
                                    • Laboratory - Chemistry<br>
                                    • Imaging - Radiology<br>
                                    • Imaging - Ultrasound<br>
                                    • Nursing - Procedures<br>
                                    • Nursing - Monitoring
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
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
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
                                <i class="mdi mdi-file-download-outline"></i>
                                <span>Download the template with required fields</span>
                                <a href="{{ route('import-export.template.staff') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download Template
                                </a>
                            </div>

                            <form action="{{ route('import-export.import.staff') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Default Password</label>
                                    <input type="text" name="default_password" class="form-control" value="password123" placeholder="Default password for new accounts">
                                    <small class="text-muted">Staff should change their password on first login</small>
                                </div>

                                <div class="file-upload-zone" onclick="document.getElementById('staff-file').click()">
                                    <input type="file" id="staff-file" name="file" accept=".csv,.txt" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import">
                                        <i class="mdi mdi-database-import"></i> Import Staff
                                    </button>
                                </div>
                            </form>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Available Roles:</strong><br>
                                    @foreach($roles as $role)
                                        • {{ $role->name }}<br>
                                    @endforeach
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
                                <i class="mdi mdi-file-download-outline"></i>
                                <span>Download the template with patient fields</span>
                                <a href="{{ route('import-export.template.patients') }}" class="btn btn-template btn-sm ms-auto">
                                    <i class="mdi mdi-download"></i> Download Template
                                </a>
                            </div>

                            <form action="{{ route('import-export.import.patients') }}" method="POST" enctype="multipart/form-data">
                                @csrf

                                <div class="file-upload-zone" onclick="document.getElementById('patients-file').click()">
                                    <input type="file" id="patients-file" name="file" accept=".csv,.txt" required>
                                    <i class="mdi mdi-cloud-upload-outline"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="small text-muted">CSV file (max 10MB)</p>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-import">
                                        <i class="mdi mdi-database-import"></i> Import Patients
                                    </button>
                                </div>
                            </form>

                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <strong>Notes:</strong><br>
                                    • File numbers will be auto-generated<br>
                                    • HMO names must match existing HMOs<br>
                                    • Date format: YYYY-MM-DD<br>
                                    • Allergies: comma-separated values
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
                                            <option value="{{ $hmo->id }}">{{ $hmo->hmo_name }}</option>
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
                    <h6>CSV Format Requirements</h6>
                    <ul class="small text-muted">
                        <li>Use comma (,) as delimiter</li>
                        <li>First row must contain column headers</li>
                        <li>Text fields with commas should be quoted</li>
                        <li>Date format: YYYY-MM-DD (e.g., 2026-01-22)</li>
                        <li>Maximum file size: 10MB</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Import Behavior</h6>
                    <ul class="small text-muted">
                        <li>Duplicate entries (by code/email) will be skipped</li>
                        <li>Categories will be auto-created if they don't exist</li>
                        <li>Related records (Price, Stock) are created automatically</li>
                        <li>Errors are logged and reported, valid rows are still imported</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>
@endsection
