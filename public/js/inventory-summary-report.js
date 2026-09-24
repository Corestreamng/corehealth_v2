/**
 * CoreHealth v2 - Inventory Summary Reports Component JS
 * Handles asynchronous aggregation, KPI computation, and interactive drill-down
 * for store, pharmacy, and department inventory workbenches.
 */
(function() {
    'use strict';

    if (typeof window.wbUrl !== 'function') {
        window.wbUrl = function(path) {
            var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
            base = base.replace(/\/$/, '');
            var cleanPath = (path || '').replace(/^\//, '');
            return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
        };
    }

    function formatCurrency(val) {
        return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(val || 0);
    }

    function isExpiringSoon(dateStr) {
        if (!dateStr || dateStr === 'N/A') return false;
        var exp = new Date(dateStr);
        if (isNaN(exp.getTime())) return false;
        var now = new Date();
        var diffTime = exp.getTime() - now.getTime();
        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays >= 0 && diffDays <= 90;
    }

    function resolveContainerStoreId(container) {
        var storeId = container.dataset.storeId;
        if (!storeId || storeId.trim() === '' || storeId.trim() === '0') {
            if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.resolvedStoreId) {
                storeId = String(window.WORKBENCH_CONFIG.resolvedStoreId);
            } else {
                var input = document.querySelector('#current-store-id') || document.querySelector('[name="store_id"]');
                if (input && input.value) {
                    storeId = input.value;
                } else {
                    storeId = '2'; // Default pharmacy / central store fallback
                }
            }
            container.dataset.storeId = storeId;
        }
        return storeId;
    }

    function initContainer(container) {
        if (!container || container.dataset.initialized === 'true') return;
        container.dataset.initialized = 'true';

        var btnRefresh = container.querySelector('.report-refresh-btn');
        var btnPrint = container.querySelector('.report-print-btn');
        var selGroupBy = container.querySelector('.report-group-by');
        var inpStart = container.querySelector('.report-start-date');
        var inpEnd = container.querySelector('.report-end-date');

        var tableContainer = container.querySelector('.report-table-container');
        var tbody = container.querySelector('.report-main-table tbody');
        var loading = container.querySelector('.report-loading');
        var emptyState = container.querySelector('.report-empty-state');
        var colHeader = container.querySelector('.report-col-header');
        var kpisContainer = container.querySelector('.report-kpis-container');

        var mode = container.dataset.mode || 'given';

        function loadSummary() {
            var storeId = resolveContainerStoreId(container);
            var groupBy = selGroupBy ? selGroupBy.value : 'category';

            if (loading) loading.classList.remove('d-none');
            if (tableContainer) tableContainer.classList.add('d-none');
            if (kpisContainer) kpisContainer.classList.add('d-none');
            if (emptyState) emptyState.classList.add('d-none');
            if (tbody) tbody.innerHTML = '';

            if (colHeader) {
                if (groupBy === 'category') colHeader.textContent = 'Drug/Product Category';
                else if (groupBy === 'product') colHeader.textContent = 'Product Name';
                else colHeader.textContent = 'Destination Unit/Department';
            }

            var params = new URLSearchParams({
                store_id: storeId,
                mode: mode,
                group_by: groupBy,
                start_date: inpStart ? inpStart.value : '',
                end_date: inpEnd ? inpEnd.value : ''
            });

            var url = window.wbUrl('/inventory/inventory-reports/summary') + '?' + params.toString();

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('HTTP error ' + res.status);
                }
                return res.json();
            })
            .then(function(res) {
                if (loading) loading.classList.add('d-none');
                if (res && res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                    renderTable(res.data, groupBy);
                    if (tableContainer) tableContainer.classList.remove('d-none');
                    if (kpisContainer) kpisContainer.classList.remove('d-none');
                } else {
                    if (emptyState) emptyState.classList.remove('d-none');
                }
            })
            .catch(function(err) {
                console.error('Inventory Summary Report load failed:', err);
                if (loading) loading.classList.add('d-none');
                if (emptyState) {
                    emptyState.textContent = 'Failed to load report data. Please check date range or store filters.';
                    emptyState.classList.remove('d-none');
                }
            });
        }

        function renderTable(data, groupBy) {
            if (!tbody) return;
            var grandQty = 0;
            var grandVal = 0;
            var grandSales = 0;
            var grandCash = 0;
            var grandClaims = 0;
            var grandProfit = 0;

            data.forEach(function(row) {
                var totalSales = (Number(row.potential_revenue) || 0) + (Number(row.cash_revenue) || 0) + (Number(row.claims_revenue) || 0);
                var rowProfit = Number(row.profit) || 0;
                var rowQty = Number(row.total_qty) || 0;
                var rowVal = Number(row.total_value) || 0;
                var rowCash = Number(row.cash_revenue) || 0;
                var rowClaims = Number(row.claims_revenue) || 0;

                grandQty += rowQty;
                grandVal += rowVal;
                grandSales += totalSales;
                grandCash += rowCash;
                grandClaims += rowClaims;
                grandProfit += rowProfit;

                var profitClass = rowProfit > 0 ? 'text-success' : (rowProfit < 0 ? 'text-danger' : '');

                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="font-weight-medium">' +
                        '<i class="mdi mdi-chevron-right mr-2 text-primary toggle-icon"></i> ' +
                        (row.grouping_key || 'Unknown') +
                    '</td>' +
                    '<td class="text-right">' + rowQty.toLocaleString() + '</td>' +
                    '<td class="text-right">' + formatCurrency(rowVal) + '</td>' +
                    '<td class="text-right">' + formatCurrency(totalSales) + '</td>' +
                    '<td class="text-right text-info">' + formatCurrency(rowCash) + '</td>' +
                    '<td class="text-right text-primary">' + formatCurrency(rowClaims) + '</td>' +
                    '<td class="text-right font-weight-bold ' + profitClass + '">' + formatCurrency(rowProfit) + '</td>' +
                    '<td class="text-center">' +
                        '<button type="button" class="btn btn-xs btn-outline-primary btn-drilldown">Details</button>' +
                    '</td>';

                var drilldownTr = document.createElement('tr');
                drilldownTr.className = 'drilldown-row d-none';
                drilldownTr.innerHTML =
                    '<td colspan="8" class="p-0">' +
                        '<div class="drilldown-table-container">' +
                            '<div class="text-center py-3 drilldown-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>' +
                            '<div class="table-responsive drilldown-content d-none">' +
                                '<table class="table table-bordered table-sm mb-0 bg-white" style="font-size: 0.85rem;">' +
                                    '<thead class="bg-primary text-white">' +
                                        '<tr>' +
                                            '<th>Type</th>' +
                                            '<th>Date</th>' +
                                            '<th>Product</th>' +
                                            '<th>Batch #</th>' +
                                            '<th>Expiry</th>' +
                                            '<th class="text-right">Qty</th>' +
                                            '<th class="text-right">Unit Cost</th>' +
                                            '<th class="text-right">Total Cost</th>' +
                                            '<th class="text-right">Cash</th>' +
                                            '<th class="text-right">Claims</th>' +
                                            '<th class="text-right">Rev</th>' +
                                            '<th class="text-right">Profit</th>' +
                                        '</tr>' +
                                    '</thead>' +
                                    '<tbody class="drilldown-tbody"></tbody>' +
                                '</table>' +
                            '</div>' +
                        '</div>' +
                    '</td>';

                tbody.appendChild(tr);
                tbody.appendChild(drilldownTr);

                var toggleHandler = function(e) {
                    var isHidden = drilldownTr.classList.contains('d-none');
                    var icon = tr.querySelector('.toggle-icon');

                    if (isHidden) {
                        drilldownTr.classList.remove('d-none');
                        if (icon) {
                            icon.classList.remove('mdi-chevron-right');
                            icon.classList.add('mdi-chevron-down');
                        }
                        loadDrillDown(row.grouping_key, groupBy, drilldownTr);
                    } else {
                        drilldownTr.classList.add('d-none');
                        if (icon) {
                            icon.classList.add('mdi-chevron-right');
                            icon.classList.remove('mdi-chevron-down');
                        }
                    }
                };

                tr.addEventListener('click', toggleHandler);
            });

            var grandQtyEl = container.querySelector('.report-grand-qty');
            if (grandQtyEl) grandQtyEl.textContent = grandQty.toLocaleString();
            var grandValEl = container.querySelector('.report-grand-value');
            if (grandValEl) grandValEl.textContent = formatCurrency(grandVal);
            var grandSalesEl = container.querySelector('.report-grand-sales');
            if (grandSalesEl) grandSalesEl.textContent = formatCurrency(grandSales);
            var grandCashEl = container.querySelector('.report-grand-cash');
            if (grandCashEl) grandCashEl.textContent = formatCurrency(grandCash);
            var grandClaimsEl = container.querySelector('.report-grand-claims');
            if (grandClaimsEl) grandClaimsEl.textContent = formatCurrency(grandClaims);
            var grandProfitEl = container.querySelector('.report-grand-profit');
            if (grandProfitEl) grandProfitEl.textContent = formatCurrency(grandProfit);

            // Update KPI Cards
            var kpiQtyEl = container.querySelector('.report-kpi-qty');
            if (kpiQtyEl) kpiQtyEl.textContent = grandQty.toLocaleString();

            var kpiCostEl = container.querySelector('.report-kpi-cost');
            if (kpiCostEl) kpiCostEl.textContent = formatCurrency(grandVal);

            var kpiRevEl = container.querySelector('.report-kpi-revenue');
            if (kpiRevEl) kpiRevEl.textContent = formatCurrency(grandSales);

            var kpiProfitEl = container.querySelector('.report-kpi-profit');
            if (kpiProfitEl) {
                kpiProfitEl.textContent = formatCurrency(grandProfit);
                kpiProfitEl.className = 'mb-0 report-kpi-profit ' + (grandProfit > 0 ? 'text-success' : (grandProfit < 0 ? 'text-danger' : 'text-dark'));
            }
        }

        function loadDrillDown(groupKey, groupBy, drilldownTr) {
            var contentDiv = drilldownTr.querySelector('.drilldown-content');
            var loadingDiv = drilldownTr.querySelector('.drilldown-loading');
            var drillTbody = drilldownTr.querySelector('.drilldown-tbody');

            if (!drillTbody || drillTbody.children.length > 0) return;

            var storeId = resolveContainerStoreId(container);
            var params = new URLSearchParams({
                store_id: storeId,
                mode: mode,
                group_by: groupBy,
                group_key: groupKey,
                start_date: inpStart ? inpStart.value : '',
                end_date: inpEnd ? inpEnd.value : ''
            });

            var url = window.wbUrl('/inventory/inventory-reports/drill-down') + '?' + params.toString();

            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('HTTP error ' + res.status);
                }
                return res.json();
            })
            .then(function(res) {
                if (loadingDiv) loadingDiv.classList.add('d-none');
                if (contentDiv) contentDiv.classList.remove('d-none');

                if (res && res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                    res.data.forEach(function(item) {
                        var profit = Number(item.profit) || 0;
                        var profitClass = profit > 0 ? 'text-success' : (profit < 0 ? 'text-danger' : '');
                        var qty = Number(item.qty) || 0;
                        var costPrice = Number(item.cost_price) || 0;
                        var totalVal = Number(item.total_value) || 0;
                        var cashPaid = Number(item.cash_paid) || 0;
                        var claimsPaid = Number(item.claims_paid) || 0;
                        var totalRev = Number(item.total_revenue) || 0;

                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><span class="badge badge-' + (item.type === 'Dispense' ? 'success' : 'info') + '">' + (item.type || 'Item') + '</span></td>' +
                            '<td class="small">' + (item.date || 'N/A') + '</td>' +
                            '<td>' +
                                '<div class="font-weight-bold">' + (item.product_name || 'N/A') + '</div>' +
                                '<small class="text-muted">Pkg: ' + (item.packaging || 'Unit') + '</small>' +
                            '</td>' +
                            '<td>' + (item.batch_number || 'N/A') + '</td>' +
                            '<td class="' + (isExpiringSoon(item.expiry_date) ? 'text-danger font-weight-bold' : '') + '">' + (item.expiry_date || 'N/A') + '</td>' +
                            '<td class="text-right font-weight-bold">' + qty.toLocaleString() + '</td>' +
                            '<td class="text-right">' + formatCurrency(costPrice) + '</td>' +
                            '<td class="text-right text-muted">' + formatCurrency(totalVal) + '</td>' +
                            '<td class="text-right">' + formatCurrency(cashPaid) + '</td>' +
                            '<td class="text-right">' + formatCurrency(claimsPaid) + '</td>' +
                            '<td class="text-right font-weight-bold text-primary">' + formatCurrency(totalRev) + '</td>' +
                            '<td class="text-right font-weight-bold ' + profitClass + '">' + formatCurrency(profit) + '</td>';
                        drillTbody.appendChild(tr);
                    });
                } else {
                    drillTbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-3">No details available for this group.</td></tr>';
                }
            })
            .catch(function(err) {
                console.error('Inventory drill-down load failed:', err);
                if (loadingDiv) {
                    loadingDiv.innerHTML = '<span class="text-danger small">Failed to load details</span>';
                }
            });
        }

        if (btnRefresh) {
            btnRefresh.addEventListener('click', function() {
                loadSummary();
            });
        }

        if (selGroupBy) {
            selGroupBy.addEventListener('change', function() {
                loadSummary();
            });
        }

        if (btnPrint) {
            btnPrint.addEventListener('click', function() {
                var storeId = resolveContainerStoreId(container);
                var groupBy = selGroupBy ? selGroupBy.value : 'category';
                var params = new URLSearchParams({
                    store_id: storeId,
                    mode: mode,
                    group_by: groupBy,
                    start_date: inpStart ? inpStart.value : '',
                    end_date: inpEnd ? inpEnd.value : ''
                });
                var printUrl = window.wbUrl('/inventory/inventory-reports/summary/print') + '?' + params.toString();
                window.open(printUrl, '_blank', 'width=1000,height=800,scrollbars=yes');
            });
        }

        // Initial fetch
        loadSummary();
    }

    window.initInventorySummaryReports = function(scope) {
        var root = scope || document;
        var containers = root.querySelectorAll ? root.querySelectorAll('.summary-report-container') : [];
        for (var i = 0; i < containers.length; i++) {
            initContainer(containers[i]);
        }
    };

    if (document.readyState !== 'loading') {
        window.initInventorySummaryReports();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            window.initInventorySummaryReports();
        });
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('shown.bs.tab shown.bs.modal', function(e) {
            window.initInventorySummaryReports();
        });
    }
})();
