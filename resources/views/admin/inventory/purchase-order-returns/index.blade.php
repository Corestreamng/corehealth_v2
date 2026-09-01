@extends('admin.layouts.app')
@section('title', 'Purchase Order Returns')
@section('page_name', 'Inventory Management')
@section('subpage_name', 'PO Returns')

@section('content')
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/po-returns.css') }}">

<div id="content-wrapper">
    <div class="container-fluid">
        <!-- Header -->
        <div class="po-returns-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h3 font-weight-bold text-white mb-1">
                    <i class="mdi mdi-keyboard-return mr-2"></i> Purchase Order Returns
                </h2>
                <p class="text-white-50 mb-0">Track, manage, and process stock return records to suppliers for received purchase orders</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('inventory.store-workbench.tally-card') }}" class="btn btn-light btn-sm shadow-sm font-weight-bold">
                    <i class="mdi mdi-view-dashboard mr-1"></i> Tally Card
                </a>
                <a href="{{ route('inventory.purchase-orders.index') }}" class="btn btn-outline-light btn-sm font-weight-bold">
                    <i class="mdi mdi-cart mr-1"></i> Purchase Orders
                </a>
            </div>
        </div>

        <!-- KPI Cards Section -->
        <div class="row mb-4">
            <div class="col-12 col-sm-6 col-xl-3 mb-3 mb-xl-0">
                <div class="stat-card-modern">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Return Records</div>
                            <div class="stat-value text-dark">{{ number_format($stats['total_count'] ?? 0) }}</div>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-primary">
                            <i class="mdi mdi-clipboard-text-outline"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 mb-3 mb-xl-0">
                <div class="stat-card-modern">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Pending Approval</div>
                            <div class="stat-value text-warning">{{ number_format($stats['pending_count'] ?? 0) }}</div>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-warning">
                            <i class="mdi mdi-clock-alert-outline"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3 mb-3 mb-sm-0">
                <div class="stat-card-modern">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Approved Returns</div>
                            <div class="stat-value text-success">{{ number_format($stats['approved_count'] ?? 0) }}</div>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-success">
                            <i class="mdi mdi-check-circle-outline"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card-modern">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Value Returned</div>
                            <div class="stat-value text-dark" style="font-size:1.35rem;">₦{{ number_format($stats['total_value'] ?? 0, 2) }}</div>
                        </div>
                        <div class="stat-icon-wrapper stat-icon-danger">
                            <i class="mdi mdi-currency-ngn"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Table Container -->
        <div class="card-returns-container">
            <!-- Filters -->
            <div class="row align-items-center mb-3 pb-3 border-bottom">
                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1"><i class="mdi mdi-store mr-1"></i> Filter by Store</label>
                    <select id="filter-store" class="form-control form-control-sm">
                        <option value="">All Stores</option>
                        @foreach($stores as $st)
                            <option value="{{ $st->id }}">{{ $st->store_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted mb-1"><i class="mdi mdi-filter-variant mr-1"></i> Filter by Status</label>
                    <select id="filter-status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="col-md-4 text-md-right mt-3 mt-md-0">
                    <button type="button" id="btn-reset-filters" class="btn btn-sm btn-outline-secondary">
                        <i class="mdi mdi-refresh mr-1"></i> Clear Filters
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table id="po-returns-table" class="table table-hover table-striped w-100 align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Return & Date</th>
                            <th>Order, Supplier & Store</th>
                            <th>Product & Batch</th>
                            <th>Qty & Packaging</th>
                            <th>Value, Reason & Status</th>
                            <th>Recorded By & Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Financial Review & Approval Modal -->
<div class="modal fade" id="por-review-modal" tabindex="-1" role="dialog" aria-labelledby="porReviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title font-weight-bold text-white mb-0" id="porReviewModalLabel">
                    <i class="mdi mdi-calculator-variant mr-1 text-warning"></i> Return Financial Review & GL Journal Preview
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div id="por-modal-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading financial context...</span>
                    </div>
                    <p class="text-muted mt-2">Loading financial context & GL posting preview...</p>
                </div>
                <div id="por-modal-content" style="display: none;">
                    <!-- Financial Context Banner -->
                    <div id="por-fin-banner" class="alert alert-info border-0 shadow-sm mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="font-weight-bold mb-0 text-dark" id="por-modal-return-num">POR-00000</h6>
                            <span id="por-modal-payment-badge" class="badge badge-success px-3 py-1 font-weight-bold">Fully Paid PO</span>
                        </div>
                        <p id="por-modal-impact-summary" class="mb-0 small text-dark font-weight-500">Approving will create a Supplier Credit Note / Receivable (AR 1200).</p>
                    </div>

                    <!-- Item & Order Details Grid -->
                    <div class="row mb-4">
                        <div class="col-md-6 border-right">
                            <h6 class="text-muted text-uppercase small font-weight-bold mb-2">Item Details</h6>
                            <p class="mb-1"><strong id="por-modal-product-name">Product Name</strong></p>
                            <p class="mb-1 small text-muted">Store: <span id="por-modal-store-name" class="text-dark font-weight-bold">Store</span></p>
                            <p class="mb-1 small text-muted">Batch: <span id="por-modal-batch-num" class="text-dark font-weight-bold">Batch</span></p>
                            <p class="mb-0 small text-muted">Returned Qty: <span id="por-modal-qty" class="text-danger font-weight-bold">0 Units</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase small font-weight-bold mb-2">Financial Breakdown</h6>
                            <p class="mb-1 small text-muted">Purchase Order: <strong id="por-modal-po-num" class="text-primary">PO-000</strong></p>
                            <p class="mb-1 small text-muted">Supplier: <strong id="por-modal-supplier" class="text-dark">Supplier</strong></p>
                            <p class="mb-1 small text-muted">Unit Cost: <strong id="por-modal-unit-cost">₦0.00</strong></p>
                            <p class="mb-0"><span class="small text-muted">Total Return Value:</span> <strong id="por-modal-total-val" class="text-success h5 font-weight-bold mb-0 ml-1">₦0.00</strong></p>
                        </div>
                    </div>

                    <!-- Target GL Journal Entry Preview -->
                    <div class="card border mb-4">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 small font-weight-bold text-dark"><i class="mdi mdi-book-open-outline mr-1"></i> Target General Ledger Posting (Automated JE)</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-striped mb-0 small">
                                <thead>
                                    <tr>
                                        <th>GL Account Code & Name</th>
                                        <th class="text-right">Debit (DR)</th>
                                        <th class="text-right">Credit (CR)</th>
                                    </tr>
                                </thead>
                                <tbody id="por-modal-gl-rows">
                                    <!-- Dynamic GL lines -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Approval Form & Settlement Selector -->
                    <div id="por-modal-approval-box" class="card bg-light border-warning p-3">
                        <h6 class="font-weight-bold text-dark mb-2"><i class="mdi mdi-check-decagram text-warning mr-1"></i> Confirm Return Approval</h6>
                        <div id="por-settlement-selector-box" class="form-group mb-3" style="display: none;">
                            <label class="small font-weight-bold text-dark mb-1">Select Financial Refund Option for Fully Paid PO:</label>
                            <select id="por-settlement-option" class="form-control form-control-sm">
                                <option value="credit_note">Supplier Credit Note (Hold ₦ credit on Vendor Account for future POs)</option>
                                <option value="bank_refund">Direct Bank / Cash Refund from Supplier</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-dark mb-1">Approval Notes (Optional):</label>
                            <textarea id="por-approval-notes" class="form-control form-control-sm" rows="2" placeholder="Add approval or inspection notes..."></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary btn-sm mr-2" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="por-confirm-approve-btn" class="btn btn-success btn-sm font-weight-bold px-3">
                                <i class="mdi mdi-check mr-1"></i> Approve Return & Post GL
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
<script>
    window.WORKBENCH_CONFIG = {
        csrf: '{{ csrf_token() }}',
        baseUrl: '{{ url("/") }}',
        routes: {
            'inventory.po-returns.datatables': '{{ route("inventory.po-returns.datatables") }}'
        }
    };
</script>
<script src="{{ asset('js/workbench-helper.js') }}" defer></script>
<script src="{{ asset('js/po-returns-index.js') }}" defer></script>
@endsection
