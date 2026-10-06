<div class="summary-report-container" data-store-id="{{ $storeIds ?? '' }}" data-mode="{{ $mode ?? 'given' }}">
    <div class="mb-2">
        <span class="badge badge-info shadow-sm p-2" style="font-size: 0.8rem;">
            <i class="mdi mdi-store mr-1"></i> Context: {{ $storeName ?? 'Global / All Stores' }}
        </span>
    </div>
    <!-- Filters row -->
    <div class="row mb-3 align-items-end bg-light p-3 rounded">
        <div class="col-md-3">
            <label class="small text-muted mb-1 font-weight-bold">Report Type</label>
            <select class="form-control form-control-sm report-group-by">
                <option value="category">Drug/Product Category</option>
                <option value="destination">Unit/Department Collection</option>
                <option value="product">Product (Velocity)</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="small text-muted mb-1 font-weight-bold">Start Date</label>
            <input type="date" class="form-control form-control-sm report-start-date" value="{{ \Carbon\Carbon::now()->subMonths(1)->format('Y-m-d') }}">
        </div>
        <div class="col-md-3">
            <label class="small text-muted mb-1 font-weight-bold">End Date</label>
            <input type="date" class="form-control form-control-sm report-end-date" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
        </div>
        <div class="col-md-3">
            <div class="d-flex" style="gap: 10px;">
                <button type="button" class="btn btn-sm btn-primary flex-grow-1 report-refresh-btn">
                    <i class="mdi mdi-refresh mr-1"></i> Generate
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary report-print-btn" title="Print Report">
                    <i class="mdi mdi-printer"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="text-center report-loading d-none py-4">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted small">Aggregating inventory data...</div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-3 report-kpis-container d-none">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded h-100" style="background-color: #f8f9fa;">
                <div class="card-body py-3">
                    <p class="text-muted mb-1 small text-uppercase font-weight-bold">Total Volume (Qty)</p>
                    <h4 class="mb-0 report-kpi-qty" style="color: #495057;">0</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded h-100" style="background-color: #f8f9fa;">
                <div class="card-body py-3">
                    <p class="text-muted mb-1 small text-uppercase font-weight-bold">Total Cost Value</p>
                    <h4 class="mb-0 text-secondary report-kpi-cost">₦0.00</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded h-100" style="background-color: #e3f2fd;">
                <div class="card-body py-3">
                    <p class="text-muted mb-1 small text-uppercase font-weight-bold">Total Expected Rev</p>
                    <h4 class="mb-0 text-primary report-kpi-revenue">₦0.00</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded h-100" style="background-color: #f8f9fa;">
                <div class="card-body py-3">
                    <p class="text-muted mb-1 small text-uppercase font-weight-bold">Total Profit/Loss</p>
                    <h4 class="mb-0 report-kpi-profit">₦0.00</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="table-responsive report-table-container d-none shadow-sm rounded border bg-white">
        <table class="table table-hover table-sm mb-0 report-main-table">
            <thead class="bg-light">
                <tr>
                    <th><i class="mdi mdi-chevron-down mr-2 invisible"></i> <span class="report-col-header">Category</span></th>
                    <th class="text-right">Volume (Qty)</th>
                    <th class="text-right">Cost (NGN)</th>
                    <th class="text-right">Sales / Potential (NGN)</th>
                    <th class="text-right">Cash Paid</th>
                    <th class="text-right">Claims (HMO)</th>
                    <th class="text-right">Profit/Loss</th>
                    <th class="text-center" style="width: 80px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <!-- Rows injected via JS -->
            </tbody>
            <tfoot class="bg-light font-weight-bold">
                <tr>
                    <td>Grand Total</td>
                    <td class="text-right report-grand-qty">0</td>
                    <td class="text-right report-grand-value">₦0.00</td>
                    <td class="text-right report-grand-sales">₦0.00</td>
                    <td class="text-right report-grand-cash">₦0.00</td>
                    <td class="text-right report-grand-claims">₦0.00</td>
                    <td class="text-right report-grand-profit">₦0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="alert alert-info mt-3 report-empty-state d-none">
        <i class="mdi mdi-information mr-1"></i> No transactions found for the selected period.
    </div>
</div>

@once
<link rel="stylesheet" href="{{ versioned_asset('css/inventory-summary-report.css') }}">
<script src="{{ versioned_asset('js/inventory-summary-report.js') }}"></script>
@endonce
