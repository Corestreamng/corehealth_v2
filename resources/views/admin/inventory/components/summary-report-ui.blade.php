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
            <button type="button" class="btn btn-sm btn-primary btn-block report-refresh-btn">
                <i class="mdi mdi-refresh mr-1"></i> Generate Report
            </button>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div class="text-center report-loading d-none py-4">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2 text-muted small">Aggregating inventory data...</div>
    </div>

    <!-- Main Table -->
    <div class="table-responsive report-table-container d-none shadow-sm rounded border bg-white">
        <table class="table table-hover table-sm mb-0 report-main-table">
            <thead class="bg-light">
                <tr>
                    <th><i class="mdi mdi-chevron-down mr-2 invisible"></i> <span class="report-col-header">Category</span></th>
                    <th class="text-right">Total Volume (Qty)</th>
                    <th class="text-right">Total Value (NGN)</th>
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
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="alert alert-info mt-3 report-empty-state d-none">
        <i class="mdi mdi-information mr-1"></i> No transactions found for the selected period.
    </div>
</div>

<style>
    .drilldown-row { background-color: #f8f9fa; }
    .drilldown-table-container { padding: 15px 30px; border-left: 3px solid #007bff; }
    .report-main-table tbody tr { cursor: pointer; transition: background-color 0.2s; }
    .report-main-table tbody tr:hover { background-color: #f1f5f9; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const containers = document.querySelectorAll('.summary-report-container');

        containers.forEach(container => {
            const btnRefresh = container.querySelector('.report-refresh-btn');
            const selGroupBy = container.querySelector('.report-group-by');
            const inpStart = container.querySelector('.report-start-date');
            const inpEnd = container.querySelector('.report-end-date');
            
            const tableContainer = container.querySelector('.report-table-container');
            const tbody = container.querySelector('tbody');
            const loading = container.querySelector('.report-loading');
            const emptyState = container.querySelector('.report-empty-state');
            const colHeader = container.querySelector('.report-col-header');
            
            const storeId = container.dataset.storeId;
            const mode = container.dataset.mode;

            const formatCurrency = (val) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val);

            btnRefresh.addEventListener('click', () => loadSummary());

            function loadSummary() {
                loading.classList.remove('d-none');
                tableContainer.classList.add('d-none');
                emptyState.classList.add('d-none');
                tbody.innerHTML = '';

                const groupBy = selGroupBy.value;
                colHeader.textContent = groupBy === 'category' ? 'Drug/Product Category' : 'Destination Unit/Department';

                const url = `/inventory/inventory-reports/summary?store_id=${storeId}&mode=${mode}&group_by=${groupBy}&start_date=${inpStart.value}&end_date=${inpEnd.value}`;

                fetch(url)
                    .then(res => res.json())
                    .then(res => {
                        loading.classList.add('d-none');
                        if (res.status === 'success' && res.data.length > 0) {
                            renderTable(res.data, groupBy);
                            tableContainer.classList.remove('d-none');
                        } else {
                            emptyState.classList.remove('d-none');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        loading.classList.add('d-none');
                    });
            }

            function renderTable(data, groupBy) {
                let grandQty = 0;
                let grandVal = 0;

                data.forEach(row => {
                    grandQty += row.total_qty;
                    grandVal += row.total_value;

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="font-weight-medium">
                            <i class="mdi mdi-chevron-right mr-2 text-primary toggle-icon"></i> 
                            ${row.grouping_key}
                        </td>
                        <td class="text-right">${row.total_qty.toLocaleString()}</td>
                        <td class="text-right">${formatCurrency(row.total_value)}</td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-outline-primary btn-drilldown">Details</button>
                        </td>
                    `;
                    
                    const drilldownTr = document.createElement('tr');
                    drilldownTr.className = 'drilldown-row d-none';
                    drilldownTr.innerHTML = `
                        <td colspan="4" class="p-0">
                            <div class="drilldown-table-container">
                                <div class="text-center py-3 drilldown-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>
                                <div class="table-responsive drilldown-content d-none">
                                    <table class="table table-bordered table-sm mb-0 bg-white">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th>Type</th>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>Batch #</th>
                                                <th>Expiry</th>
                                                <th class="text-right">Qty</th>
                                                <th class="text-right">Unit Cost</th>
                                                <th class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="drilldown-tbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    `;

                    tbody.appendChild(tr);
                    tbody.appendChild(drilldownTr);

                    const toggleHandler = () => {
                        const isHidden = drilldownTr.classList.contains('d-none');
                        const icon = tr.querySelector('.toggle-icon');
                        
                        if (isHidden) {
                            drilldownTr.classList.remove('d-none');
                            icon.classList.remove('mdi-chevron-right');
                            icon.classList.add('mdi-chevron-down');
                            loadDrillDown(row.grouping_key, groupBy, drilldownTr);
                        } else {
                            drilldownTr.classList.add('d-none');
                            icon.classList.add('mdi-chevron-right');
                            icon.classList.remove('mdi-chevron-down');
                        }
                    };

                    tr.addEventListener('click', toggleHandler);
                });

                container.querySelector('.report-grand-qty').textContent = grandQty.toLocaleString();
                container.querySelector('.report-grand-value').textContent = formatCurrency(grandVal);
            }

            function loadDrillDown(groupKey, groupBy, drilldownTr) {
                const contentDiv = drilldownTr.querySelector('.drilldown-content');
                const loadingDiv = drilldownTr.querySelector('.drilldown-loading');
                const drillTbody = drilldownTr.querySelector('.drilldown-tbody');
                
                // If already loaded, skip
                if (drillTbody.children.length > 0) return;

                const url = `/inventory/inventory-reports/drill-down?store_id=${storeId}&mode=${mode}&group_by=${groupBy}&group_key=${encodeURIComponent(groupKey)}&start_date=${inpStart.value}&end_date=${inpEnd.value}`;

                fetch(url)
                    .then(res => res.json())
                    .then(res => {
                        loadingDiv.classList.add('d-none');
                        contentDiv.classList.remove('d-none');

                        if (res.status === 'success' && res.data.length > 0) {
                            res.data.forEach(item => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td><span class="badge badge-${item.type === 'Dispense' ? 'success' : 'info'}">${item.type}</span></td>
                                    <td class="small">${item.date}</td>
                                    <td>
                                        <div class="font-weight-bold">${item.product_name}</div>
                                        <small class="text-muted">Pkg: ${item.packaging}</small>
                                    </td>
                                    <td>${item.batch_number}</td>
                                    <td class="${isExpiringSoon(item.expiry_date) ? 'text-danger font-weight-bold' : ''}">${item.expiry_date}</td>
                                    <td class="text-right font-weight-bold">${item.qty.toLocaleString()}</td>
                                    <td class="text-right">${formatCurrency(item.cost_price)}</td>
                                    <td class="text-right text-primary font-weight-bold">${formatCurrency(item.total_value)}</td>
                                `;
                                drillTbody.appendChild(tr);
                            });
                        } else {
                            drillTbody.innerHTML = `<tr><td colspan="8" class="text-center">No details available.</td></tr>`;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        loadingDiv.innerHTML = '<span class="text-danger">Failed to load details</span>';
                    });
            }

            function isExpiringSoon(dateStr) {
                if (dateStr === 'N/A') return false;
                const exp = new Date(dateStr);
                const now = new Date();
                const diffTime = Math.abs(exp - now);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                return diffDays <= 90; // Highlight if expiring in 90 days
            }

            // Auto-load on init
            loadSummary();
        });
    });
</script>
