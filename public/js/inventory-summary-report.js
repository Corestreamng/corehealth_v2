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

    function getTypeBadgeClass(type) {
        switch (type) {
            case 'Dispense':
                return 'badge-success';
            case 'PO Receipt':
                return 'badge-primary';
            case 'Donation':
                return 'badge-success';
            case 'Manual Batch':
                return 'badge-warning text-dark';
            case 'Requisition':
                return 'badge-info';
            default:
                return 'badge-secondary';
        }
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
                else colHeader.textContent = mode === 'received' ? 'Source Store / Receipt Channel' : 'Destination Unit/Department';
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
            var grandDeficit = 0;
            var grandProfit = 0;

            data.forEach(function(row) {
                var rowQty = Number(row.total_qty) || 0;
                var rowVal = Number(row.total_value) || 0;
                var rowPayable = Number(row.sale_amount_payable != null ? row.sale_amount_payable : row.cash_revenue) || 0;
                var rowClaim = Number(row.sale_amount_claim != null ? row.sale_amount_claim : row.claims_revenue) || 0;
                var totalSales = Number(row.sale_amount_total != null ? row.sale_amount_total : ((Number(row.potential_revenue) || 0) + rowPayable + rowClaim)) || 0;
                var unitSalePrice = Number(row.sale_price_per_unit != null ? row.sale_price_per_unit : row.unit_sale_price) || (rowQty > 0 ? (totalSales / rowQty) : 0);
                var rowDeficit = Number(row.deficit) || 0;
                var rowProfit = Number(row.profit) || 0;

                grandQty += rowQty;
                grandVal += rowVal;
                grandSales += totalSales;
                grandCash += rowPayable;
                grandClaims += rowClaim;
                grandDeficit += rowDeficit;
                grandProfit += rowProfit;

                var profitClass = rowProfit > 0 ? 'text-success' : (rowProfit < 0 ? 'text-danger' : '');

                var channelBadges = '';
                if (row.channels && typeof row.channels === 'object') {
                    var channelPills = [];
                    if (row.channels['PO Receipt']) {
                        channelPills.push('<span class="badge badge-primary mr-1" title="Purchase Order Receipt"><i class="mdi mdi-cart-outline mr-1"></i>PO: ' + Number(row.channels['PO Receipt']).toLocaleString() + '</span>');
                    }
                    if (row.channels['Donation']) {
                        channelPills.push('<span class="badge badge-success mr-1" title="Donated Stock Batch"><i class="mdi mdi-gift-outline mr-1"></i>Donation: ' + Number(row.channels['Donation']).toLocaleString() + '</span>');
                    }
                    if (row.channels['Manual Batch']) {
                        channelPills.push('<span class="badge badge-warning text-dark mr-1" title="Manual Stock Batch"><i class="mdi mdi-pencil-box-outline mr-1"></i>Manual: ' + Number(row.channels['Manual Batch']).toLocaleString() + '</span>');
                    }
                    if (row.channels['Requisition']) {
                        channelPills.push('<span class="badge badge-info mr-1" title="Store Requisition"><i class="mdi mdi-swap-horizontal mr-1"></i>Req: ' + Number(row.channels['Requisition']).toLocaleString() + '</span>');
                    }
                    if (channelPills.length > 0) {
                        channelBadges = '<div class="mt-1" style="line-height: 1.6;">' + channelPills.join('') + '</div>';
                    }
                }

                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td class="font-weight-medium">' +
                        '<i class="mdi mdi-chevron-right mr-2 text-primary toggle-icon"></i> ' +
                        '<span>' + (row.grouping_key || 'Unknown') + '</span>' +
                        channelBadges +
                    '</td>' +
                    '<td class="text-right">' + rowQty.toLocaleString() + '</td>' +
                    '<td class="text-right text-muted">' + formatCurrency(rowVal) + '</td>' +
                    '<td class="text-right font-weight-medium">' + formatCurrency(unitSalePrice) + '</td>' +
                    '<td class="text-right text-info">' + formatCurrency(rowPayable) + '</td>' +
                    '<td class="text-right text-primary">' + formatCurrency(rowClaim) + '</td>' +
                    '<td class="text-right font-weight-bold text-dark">' + formatCurrency(totalSales) + '</td>' +
                    '<td class="text-right font-weight-bold ' + profitClass + '">' + formatCurrency(rowProfit) + '</td>' +
                    '<td class="text-center">' +
                        '<button type="button" class="btn btn-xs btn-outline-primary btn-drilldown">Details</button>' +
                    '</td>';

                var drilldownTr = document.createElement('tr');
                drilldownTr.className = 'drilldown-row d-none';
                drilldownTr.innerHTML =
                    '<td colspan="9" class="p-0">' +
                        '<div class="drilldown-table-container">' +
                            '<div class="text-center py-3 drilldown-loading"><div class="spinner-border spinner-border-sm text-primary"></div></div>' +
                            '<div class="table-responsive drilldown-content d-none">' +
                                '<table class="table table-bordered table-sm mb-0 bg-white" style="font-size: 0.82rem;">' +
                                    '<thead class="bg-primary text-white">' +
                                        '<tr>' +
                                            '<th rowspan="2" class="align-middle">Type</th>' +
                                            '<th rowspan="2" class="align-middle">Date</th>' +
                                            '<th rowspan="2" class="align-middle">Product & Batch</th>' +
                                            '<th rowspan="2" class="text-right align-middle">Qty</th>' +
                                            '<th rowspan="2" class="text-right align-middle">Unit Cost</th>' +
                                            '<th rowspan="2" class="text-right align-middle">Total Cost</th>' +
                                            '<th rowspan="2" class="text-right align-middle">Unit Price</th>' +
                                            '<th colspan="3" class="text-center py-1" style="background: rgba(255,255,255,0.18);">Sale Amount</th>' +
                                            '<th rowspan="2" class="text-right align-middle">Profit</th>' +
                                        '</tr>' +
                                        '<tr>' +
                                            '<th class="text-right py-1" style="background: rgba(255,255,255,0.1); font-size: 0.76rem;">Payable</th>' +
                                            '<th class="text-right py-1" style="background: rgba(255,255,255,0.1); font-size: 0.76rem;">Claim</th>' +
                                            '<th class="text-right py-1 font-weight-bold" style="background: rgba(255,255,255,0.18); font-size: 0.76rem;">Total</th>' +
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
            var grandUnitSaleEl = container.querySelector('.report-grand-unit-sale');
            if (grandUnitSaleEl) grandUnitSaleEl.textContent = formatCurrency(grandQty > 0 ? (grandSales / grandQty) : 0);
            var grandPayableEl = container.querySelector('.report-grand-payable');
            if (grandPayableEl) grandPayableEl.textContent = formatCurrency(grandCash);
            var grandClaimEl = container.querySelector('.report-grand-claim');
            if (grandClaimEl) grandClaimEl.textContent = formatCurrency(grandClaims);
            var grandTotalSaleEl = container.querySelector('.report-grand-total-sale');
            if (grandTotalSaleEl) grandTotalSaleEl.textContent = formatCurrency(grandSales);
            var grandProfitEl = container.querySelector('.report-grand-profit');
            if (grandProfitEl) grandProfitEl.textContent = formatCurrency(grandProfit);

            // Update KPI Cards
            var kpiQtyEl = container.querySelector('.report-kpi-qty');
            if (kpiQtyEl) kpiQtyEl.textContent = grandQty.toLocaleString();

            var kpiCostEl = container.querySelector('.report-kpi-cost');
            if (kpiCostEl) kpiCostEl.textContent = formatCurrency(grandVal);

            var kpiRevEl = container.querySelector('.report-kpi-revenue');
            if (kpiRevEl) kpiRevEl.textContent = formatCurrency(grandSales);

            var kpiPayEl = container.querySelector('.report-kpi-pay');
            if (kpiPayEl) kpiPayEl.textContent = formatCurrency(grandCash);

            var kpiClmEl = container.querySelector('.report-kpi-clm');
            if (kpiClmEl) kpiClmEl.textContent = formatCurrency(grandClaims);

            var kpiProfitEl = container.querySelector('.report-kpi-profit');
            if (kpiProfitEl) {
                kpiProfitEl.textContent = formatCurrency(grandProfit);
                kpiProfitEl.className = 'mb-0 report-kpi-profit ' + (grandProfit > 0 ? 'text-success' : (grandProfit < 0 ? 'text-danger' : 'text-dark'));
            }

            var kpiDeficitEl = container.querySelector('.report-kpi-deficit');
            if (kpiDeficitEl) kpiDeficitEl.textContent = formatCurrency(grandDeficit);
            var kpiDeficitWrap = container.querySelector('.report-kpi-deficit-wrap');
            if (kpiDeficitWrap) kpiDeficitWrap.style.display = grandDeficit > 0 ? '' : 'none';
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
                        var cashPaid = Number(item.sale_amount_payable != null ? item.sale_amount_payable : item.cash_paid) || 0;
                        var claimsPaid = Number(item.sale_amount_claim != null ? item.sale_amount_claim : item.claims_paid) || 0;
                        var totalRev = Number(item.sale_amount_total != null ? item.sale_amount_total : item.total_revenue) || 0;
                        var unitSale = Number(item.sale_price_per_unit != null ? item.sale_price_per_unit : item.unit_sale_price) || (qty > 0 ? (totalRev / qty) : 0);

                        var statusBadgeHtml = '';
                        if (item.status_label) {
                            statusBadgeHtml = '<div class="mt-1"><span class="badge ' + (item.status_badge || 'badge-secondary') + '" style="font-size: 0.68rem; font-weight: 600;">' + item.status_label + '</span></div>';
                        }

                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><span class="badge ' + getTypeBadgeClass(item.type) + '">' + (item.type || 'Item') + '</span>' + statusBadgeHtml + '</td>' +
                            '<td class="small text-nowrap">' + (item.date || 'N/A') + '</td>' +
                            '<td>' +
                                '<div class="font-weight-bold text-dark">' + (item.product_name || 'N/A') + '</div>' +
                                '<div class="small text-muted" style="font-size: 0.75rem;">' +
                                    '<span>Bth: <strong>' + (item.batch_number || 'N/A') + '</strong></span> · ' +
                                    '<span class="' + (isExpiringSoon(item.expiry_date) ? 'text-danger font-weight-bold' : '') + '">Exp: ' + (item.expiry_date || 'N/A') + '</span> · ' +
                                    '<span>Pkg: ' + (item.packaging || 'Unit') + '</span>' +
                                '</div>' +
                            '</td>' +
                            '<td class="text-right font-weight-bold text-nowrap">' + qty.toLocaleString() + '</td>' +
                            '<td class="text-right text-nowrap">' + formatCurrency(costPrice) + '</td>' +
                            '<td class="text-right text-muted text-nowrap">' + formatCurrency(totalVal) + '</td>' +
                            '<td class="text-right font-weight-medium text-nowrap">' + formatCurrency(unitSale) + '</td>' +
                            '<td class="text-right text-info text-nowrap">' + formatCurrency(cashPaid) + '</td>' +
                            '<td class="text-right text-primary text-nowrap">' + formatCurrency(claimsPaid) + '</td>' +
                            '<td class="text-right font-weight-bold text-dark text-nowrap">' + formatCurrency(totalRev) + '</td>' +
                            '<td class="text-right font-weight-bold text-nowrap ' + profitClass + '">' + formatCurrency(profit) + '</td>';
                        drillTbody.appendChild(tr);
                    });
                } else {
                    drillTbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-3">No details available for this group.</td></tr>';
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
