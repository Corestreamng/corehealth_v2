if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

// ===========================================
// DISPENSE STORE SELECTION & STOCK DISPLAY
// ===========================================

// Fetch product stock by store
function fetchPharmacyProductStock(productId, callback) {
    const url = `/pharmacy-workbench/product/${productId}/stock`;
    console.log('Fetching stock from:', url);

    $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
            console.log('Stock API success for product', productId, ':', response);
            callback(response);
        },
        error: function(xhr, status, error) {
            console.error('Stock API error for product', productId, ':', status, error, xhr.responseText);
            callback({ global_stock: 0, stores: [] });
        }
    });
}
// Override dispensePrescItems to use cart flow instead
window.dispensePrescItems = function() {
    // Redirect to cart flow
    addSelectedToCartAndOpen();
};

// ===========================================
// PHARMACY REPORTS & ANALYTICS MODULE
// ===========================================

// Global report variables
window.pharmacyReportsInitialized = false;
window.pharmDispensingTable = null;
window.pharmRevenueTable = null;
window.pharmStockTable = null;
window.pharmPerformanceTable = null;
window.pharmHmoTable = null;
window.pharmTrendChart = null;
window.pharmRevenuePieChart = null;

// Current filter state
let pharmReportFilters = {
    date_from: null,
    date_to: null,
    status: '',
    store_id: '',
    payment_type: '',
    hmo_id: '',
    doctor_id: '',
    pharmacist_id: '',
    category_id: '',
    patient_search: '',
    min_amount: '',
    max_amount: ''
};

// Open pharmacy reports view
$('#btn-pharmacy-reports').on('click', function() {
    showPharmacyReports();
});

// Close pharmacy reports view
$('#btn-close-pharmacy-reports').on('click', function() {
    hidePharmacyReports();
});

// Initialize filter dropdowns
function initPharmacyReportsFilters() {
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    $('#pharm-report-date-from').val(today);
    $('#pharm-report-date-to').val(today);
    pharmReportFilters.date_from = today;
    pharmReportFilters.date_to = today;

    // Load filter options
    loadPharmReportFilterOptions();
}

// Load filter dropdown options
function loadPharmReportFilterOptions() {
    // Load stores
    $.get('/pharmacy-workbench/stores', function(stores) {
        const $storeSelect = $('#pharm-report-store, #stock-report-store-filter');
        $storeSelect.find('option:not(:first)').remove();
        stores.forEach(store => {
            $storeSelect.append(`<option value="${store.id}">${store.name}</option>`);
        });
    });

    // Load HMOs with optgroups
    $.get('/pharmacy-workbench/filter-hmos', function(hmoGroups) {
        const $hmoSelect = $('#pharm-report-hmo');
        $hmoSelect.find('option:not(:first)').remove();
        Object.keys(hmoGroups).forEach(function(schemeName) {
            let optgroup = `<optgroup label="${schemeName}">`;
            hmoGroups[schemeName].forEach(function(hmo) {
                optgroup += `<option value="${hmo.id}">${hmo.name}</option>`;
            });
            optgroup += '</optgroup>';
            $hmoSelect.append(optgroup);
        });
    });

    // Load doctors
    $.get('/pharmacy-workbench/filter-doctors', function(doctors) {
        const $doctorSelect = $('#pharm-report-doctor');
        $doctorSelect.find('option:not(:first)').remove();
        (doctors || []).forEach(doctor => {
            $doctorSelect.append(`<option value="${doctor.id}">${doctor.name}</option>`);
        });
    });

    // Load pharmacists (staff with pharmacy role)
    $.get('/pharmacy-workbench/pharmacists', function(pharmacists) {
        const $pharmSelect = $('#pharm-report-pharmacist');
        $pharmSelect.find('option:not(:first)').remove();
        (pharmacists || []).forEach(p => {
            $pharmSelect.append(`<option value="${p.id}">${p.name}</option>`);
        });
    });

    // Load product categories
    $.get('/pharmacy-workbench/product-categories', function(categories) {
        const $catSelect = $('#pharm-report-category, #stock-report-category-filter');
        $catSelect.find('option:not(:first)').remove();
        (categories || []).forEach(cat => {
            $catSelect.append(`<option value="${cat.id}">${cat.name}</option>`);
        });
    });
}

// Date preset buttons
$('.date-preset-btn').on('click', function() {
    $('.date-preset-btn').removeClass('active');
    $(this).addClass('active');

    const preset = $(this).data('preset');
    const today = new Date();
    let dateFrom, dateTo;

    switch(preset) {
        case 'today':
            dateFrom = dateTo = today;
            break;
        case 'yesterday':
            dateFrom = dateTo = new Date(today.setDate(today.getDate() - 1));
            break;
        case 'week':
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            dateFrom = weekStart;
            dateTo = new Date();
            break;
        case 'month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = new Date();
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            dateFrom = new Date(today.getFullYear(), quarter * 3, 1);
            dateTo = new Date();
            break;
        case 'year':
            dateFrom = new Date(today.getFullYear(), 0, 1);
            dateTo = new Date();
            break;
        case 'all':
            dateFrom = null;
            dateTo = null;
            break;
    }

    if (dateFrom && dateTo) {
        $('#pharm-report-date-from').val(formatDateInput(dateFrom));
        $('#pharm-report-date-to').val(formatDateInput(dateTo));
        pharmReportFilters.date_from = formatDateInput(dateFrom);
        pharmReportFilters.date_to = formatDateInput(dateTo);
    } else {
        $('#pharm-report-date-from').val('');
        $('#pharm-report-date-to').val('');
        pharmReportFilters.date_from = null;
        pharmReportFilters.date_to = null;
    }

    loadPharmacyReportsData();
});

function formatDateInput(date) {
    if (!date) return '';
    return date.toISOString().split('T')[0];
}

// Filter form submission
$('#pharmacy-reports-filter-form').on('submit', function(e) {
    e.preventDefault();
    collectFilters();
    loadPharmacyReportsData();
});

// Clear filters
$('#clear-pharmacy-report-filters').on('click', function() {
    $('#pharmacy-reports-filter-form')[0].reset();
    const today = new Date().toISOString().split('T')[0];
    $('#pharm-report-date-from').val(today);
    $('#pharm-report-date-to').val(today);
    collectFilters();
    loadPharmacyReportsData();
});

function collectFilters() {
    pharmReportFilters = {
        date_from: $('#pharm-report-date-from').val() || null,
        date_to: $('#pharm-report-date-to').val() || null,
        status: $('#pharm-report-status').val(),
        store_id: $('#pharm-report-store').val(),
        payment_type: $('#pharm-report-payment-type').val(),
        hmo_id: $('#pharm-report-hmo').val(),
        doctor_id: $('#pharm-report-doctor').val(),
        pharmacist_id: $('#pharm-report-pharmacist').val(),
        category_id: $('#pharm-report-category').val(),
        patient_search: $('#pharm-report-patient').val(),
        min_amount: $('#pharm-report-min-amount').val(),
        max_amount: $('#pharm-report-max-amount').val(),
        age_brackets: $('#pharm-report-age-brackets').val()
    };
}

// Main data loader
function loadPharmacyReportsData() {
    loadPharmacyStatistics();
    loadTopProducts();
    loadPaymentMethods();
    refreshPharmacyDataTables();
    loadExecutiveSummaryData();
}

// Load summary statistics
function loadPharmacyStatistics() {
    $.ajax({
        url: wbUrl('/pharmacy-workbench/reports/statistics'),
        method: 'GET',
        data: pharmReportFilters,
        success: function(stats) {
            $('#pharm-stat-dispensed').text(formatNumber(stats.total_dispensed || 0));
            $('#pharm-stat-revenue').text(formatCurrency(stats.total_revenue || 0));
            $('#pharm-stat-cash').text(formatCurrency(stats.cash_sales || 0));
            $('#pharm-stat-hmo').text(formatCurrency(stats.hmo_claims || 0));
            $('#pharm-stat-patients').text(formatNumber(stats.unique_patients || 0));
            $('#pharm-stat-pending').text(formatNumber(stats.pending_count || 0));

            // Update charts
            updateTrendChart(stats.trend_data || []);
            updateRevenuePieChart(stats.revenue_breakdown || {});
        },
        error: function() {
            console.error('Failed to load pharmacy statistics');
        }
    });
}

// Load top products
function loadTopProducts() {
    $.ajax({
        url: wbUrl('/pharmacy-workbench/reports/top-products'),
        method: 'GET',
        data: pharmReportFilters,
        success: function(products) {
            const $tbody = $('#pharm-top-products-tbody');
            $tbody.empty();

            if (!products.length) {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>');
                return;
            }

            products.forEach((p, i) => {
                $tbody.append(`
                    <tr>
                        <td><span class="badge bg-secondary">${i + 1}</span></td>
                        <td>${escapeHtml(p.product_name)}</td>
                        <td class="text-center">${formatNumber(p.quantity)}</td>
                        <td class="text-end">${formatCurrency(p.revenue)}</td>
                    </tr>
                `);
            });
        },
        error: function() {
            $('#pharm-top-products-tbody').html('<tr><td colspan="4" class="text-center text-danger">Failed to load</td></tr>');
        }
    });
}

// Load payment methods breakdown
function loadPaymentMethods() {
    $.ajax({
        url: wbUrl('/pharmacy-workbench/reports/payment-methods'),
        method: 'GET',
        data: pharmReportFilters,
        success: function(methods) {
            const $tbody = $('#pharm-payment-methods-tbody');
            $tbody.empty();

            if (!methods.length) {
                $tbody.html('<tr><td colspan="4" class="text-center text-muted py-3">No data available</td></tr>');
                return;
            }

            const total = methods.reduce((sum, m) => sum + parseFloat(m.amount || 0), 0);

            methods.forEach(m => {
                const percent = total> 0 ? ((parseFloat(m.amount || 0) / total) * 100).toFixed(1) : 0;
                const icon = getPaymentIcon(m.payment_type);
                $tbody.append(`
                    <tr>
                        <td><i class="mdi ${icon} me-1"></i>${m.payment_type || 'Unknown'}</td>
                        <td class="text-center">${formatNumber(m.count)}</td>
                        <td class="text-end">${formatCurrency(m.amount)}</td>
                        <td class="text-end"><span class="badge bg-info">${percent}%</span></td>
                    </tr>
                `);
            });
        },
        error: function() {
            $('#pharm-payment-methods-tbody').html('<tr><td colspan="4" class="text-center text-danger">Failed to load</td></tr>');
        }
    });
}

function getPaymentIcon(type) {
    const icons = {
        'CASH': 'mdi-cash',
        'CARD': 'mdi-credit-card',
        'TRANSFER': 'mdi-bank-transfer',
        'HMO': 'mdi-hospital-building',
        'ACCOUNT': 'mdi-wallet'
    };
    return icons[type] || 'mdi-cash-multiple';
}

// Load Executive Summary Data
function loadExecutiveSummaryData() {
    $('#executive-summary-loader').removeClass('d-none');
    $('#executive-summary-container').addClass('d-none');

    $.ajax({
        url: wbUrl('/pharmacy-workbench/reports/executive-summary'),
        method: 'GET',
        data: pharmReportFilters,
        success: function(data) {
            $('#executive-summary-loader').addClass('d-none');
            $('#executive-summary-container').removeClass('d-none');

            // Top level cards
            $('#exec-stock-value').text(formatCurrency(data.stock_valuation || 0));
            
            let totalCollectionsValue = 0;
            let totalCollectionsCount = 0;
            const $collectionsList = $('#exec-collections-list');
            $collectionsList.empty();
            
            if (data.collections_by_store && data.collections_by_store.length) {
                data.collections_by_store.forEach(c => {
                    totalCollectionsValue += parseFloat(c.value || 0);
                    totalCollectionsCount += parseInt(c.count || 0);
                    $collectionsList.append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="mdi mdi-store text-primary me-2"></i> ${escapeHtml(c.store_name)}
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">${formatCurrency(c.value)}</div>
                                <small class="text-muted">${formatNumber(c.count)} items</small>
                            </div>
                        </li>
                    `);
                });
            } else {
                $collectionsList.html('<li class="list-group-item text-center text-muted">No collections recorded</li>');
            }
            
            $('#exec-collections-value').text(formatCurrency(totalCollectionsValue));
            $('#exec-collections-count').text(formatNumber(totalCollectionsCount) + ' items');

            const totalPatients = (data.patients_attended_to?.walk_in || 0) + 
                                  Object.values(data.patients_attended_to?.by_clinic || {}).reduce((a, b) => a + b, 0);
            $('#exec-patients-count').text(formatNumber(totalPatients));

            // Clinics List
            const $clinicsList = $('#exec-clinics-list');
            $clinicsList.empty();
            if (data.patients_attended_to?.walk_in) {
                $clinicsList.append(`
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><i class="mdi mdi-walk text-secondary me-2"></i> Walk-in</div>
                        <span class="badge bg-secondary rounded-pill">${formatNumber(data.patients_attended_to.walk_in)}</span>
                    </li>
                `);
            }
            if (data.patients_attended_to?.by_clinic && Object.keys(data.patients_attended_to.by_clinic).length) {
                Object.entries(data.patients_attended_to.by_clinic).forEach(([clinicName, count]) => {
                    $clinicsList.append(`
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><i class="mdi mdi-hospital-building text-info me-2"></i> ${escapeHtml(clinicName)}</div>
                            <span class="badge bg-info rounded-pill">${formatNumber(count)}</span>
                        </li>
                    `);
                });
            } else if (!$clinicsList.children().length) {
                $clinicsList.html('<li class="list-group-item text-center text-muted">No clinic visits</li>');
            }

            // Accordions
            renderHmoAccordion('#accordion-exec-gender', data.gender_distribution || {}, 'gender');
            renderHmoAccordion('#accordion-exec-age', data.age_distribution || {}, 'age');
            renderHmoAccordion('#accordion-exec-class', data.patient_classifications || {}, 'class');
        },
        error: function() {
            $('#executive-summary-loader').addClass('d-none');
            // Show error state gracefully
        }
    });
}

function renderHmoAccordion(containerSelector, dataObj, prefix) {
    const $container = $(containerSelector);
    $container.empty();

    if (!Object.keys(dataObj).length) {
        $container.html('<div class="p-3 text-center text-muted">No data available</div>');
        return;
    }

    let index = 0;
    for (const [categoryName, categoryData] of Object.entries(dataObj)) {
        const catId = `heading-${prefix}-${index}`;
        const collapseId = `collapse-${prefix}-${index}`;
        
        let schemesHtml = '';
        if (categoryData.schemes && Object.keys(categoryData.schemes).length) {
            schemesHtml += '<div class="ms-3 mt-2">';
            for (const [schemeName, schemeData] of Object.entries(categoryData.schemes)) {
                schemesHtml += `
                    <div class="card-modern border-0 mb-1">
                        <div class="d-flex justify-content-between p-2 bg-light rounded align-items-center">
                            <span class="fw-bold text-secondary"><i class="mdi mdi-shield-check-outline me-1"></i> ${escapeHtml(schemeName)}</span>
                            <span class="badge bg-secondary">${formatNumber(schemeData.count)}</span>
                        </div>
                `;
                
                if (schemeData.hmos && Object.keys(schemeData.hmos).length) {
                    schemesHtml += '<ul class="list-group list-group-flush ms-4 border-start border-2 border-light mb-2 mt-1">';
                    for (const [hmoName, count] of Object.entries(schemeData.hmos)) {
                        schemesHtml += `
                            <li class="list-group-item border-0 py-1 ps-3 pe-2 bg-transparent d-flex justify-content-between align-items-center" style="font-size: 0.85rem;">
                                <span class="text-muted">${escapeHtml(hmoName)}</span>
                                <span class="badge bg-light text-dark border">${formatNumber(count)}</span>
                            </li>
                        `;
                    }
                    schemesHtml += '</ul>';
                }
                schemesHtml += '</div>';
            }
            schemesHtml += '</div>';
        }

        $container.append(`
            <div class="accordion-item border mb-2 rounded">
                <h2 class="accordion-header" id="${catId}">
                    <button class="accordion-button collapsed py-2 px-3 bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <span class="fw-bold">${escapeHtml(categoryName)}</span>
                            <span class="badge bg-primary rounded-pill">${formatNumber(categoryData.count)}</span>
                        </div>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${catId}" data-bs-parent="${containerSelector}">
                    <div class="accordion-body p-2 pt-0">
                        ${schemesHtml}
                    </div>
                </div>
            </div>
        `);
        index++;
    }
}

// Render deep financial breakdowns
function renderDeepFinancials(containerSelector, collectionsData) {
    const $container = $(containerSelector);
    $container.empty();

    if (!collectionsData || !collectionsData.length) {
        $container.html('<div class="p-4 text-center text-muted">No financial data available</div>');
        return;
    }

    let index = 0;
    collectionsData.forEach(store => {
        const storeId = `heading-fin-store-${index}`;
        const collapseId = `collapse-fin-store-${index}`;

        let schemesHtml = '';
        if (store.schemes && Object.keys(store.schemes).length) {
            schemesHtml += '<div class="ms-3 mt-2">';
            for (const [schemeName, schemeData] of Object.entries(store.schemes)) {
                schemesHtml += `
                    <div class="card-modern border-0 mb-2 shadow-sm">
                        <div class="d-flex justify-content-between p-2 bg-light rounded align-items-center border-start border-4 border-info">
                            <span class="fw-bold text-secondary"><i class="mdi mdi-shield-check-outline me-1"></i> ${escapeHtml(schemeName)}</span>
                            <div class="text-end">
                                <span class="fw-bold text-success me-2">${formatCurrency(schemeData.value)}</span>
                                <span class="badge bg-secondary">${formatNumber(schemeData.count)} items</span>
                            </div>
                        </div>
                `;
                
                if (schemeData.hmos && Object.keys(schemeData.hmos).length) {
                    schemesHtml += '<ul class="list-group list-group-flush ms-4 border-start border-2 border-light mb-2 mt-1">';
                    for (const [hmoName, hmoData] of Object.entries(schemeData.hmos)) {
                        schemesHtml += `
                            <li class="list-group-item border-0 py-2 ps-3 pe-2 bg-transparent d-flex justify-content-between align-items-center" style="font-size: 0.9rem;">
                                <span class="text-muted"><i class="mdi mdi-hospital-building me-1"></i> ${escapeHtml(hmoName)}</span>
                                <div class="text-end">
                                    <span class="fw-bold text-success d-block" style="font-size: 0.85rem;">${formatCurrency(hmoData.value)}</span>
                                    <small class="text-muted d-block">${formatNumber(hmoData.count)} items | Cash: ${formatCurrency(hmoData.cash)} | Claims: ${formatCurrency(hmoData.claims)}</small>
                                </div>
                            </li>
                        `;
                    }
                    schemesHtml += '</ul>';
                }
                schemesHtml += '</div>';
            }
            schemesHtml += '</div>';
        }

        $container.append(`
            <div class="accordion-item border border-primary mb-3 rounded overflow-hidden">
                <h2 class="accordion-header" id="${storeId}">
                    <button class="accordion-button collapsed py-3 px-3 bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <span class="fw-bold fs-5 text-primary"><i class="mdi mdi-store me-2"></i>${escapeHtml(store.store_name)}</span>
                            <div class="text-end">
                                <span class="fw-bold fs-5 text-success me-3">${formatCurrency(store.value)}</span>
                                <span class="badge bg-primary rounded-pill px-3 py-2">${formatNumber(store.count)} items total</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${storeId}">
                    <div class="accordion-body p-3 pt-0 bg-white">
                        ${schemesHtml}
                    </div>
                </div>
            </div>
        `);
        index++;
    });
}

// Hook into existing success handler inside loadExecutiveSummaryData
const originalLoadExecSummaryAjaxSuccess = function(data) {
    // Populate detailed demographic renderers
    renderHmoAccordion('#detailed-gender-container', data.gender_distribution || {}, 'det-gender');
    renderHmoAccordion('#detailed-age-container', data.age_distribution || {}, 'det-age');
    renderHmoAccordion('#detailed-class-container', data.patient_classifications || {}, 'det-class');

    // Populate detailed financial renderer
    renderDeepFinancials('#exec-detailed-financials', data.collections_by_store || []);

    // Populate Financial Performance Summary
    $('#exec-det-opening-stock').text(formatCurrency(data.opening_stock || 0));
    $('#exec-det-purchases').text(formatCurrency(data.total_expenditure || 0));
    $('#exec-det-goods-available').text(formatCurrency((data.opening_stock || 0) + (data.total_expenditure || 0)));
    $('#exec-det-goods-used').text(formatCurrency(data.total_goods_used || 0));
    $('#exec-det-closing-stock').text(formatCurrency(data.stock_valuation || 0));

    // Populate Income by Scheme
    const $incomeList = $('#exec-det-income-scheme-list');
    $incomeList.empty();
    if (data.income_by_scheme && Object.keys(data.income_by_scheme).length) {
        for (const [schemeName, valObj] of Object.entries(data.income_by_scheme)) {
            let total = typeof valObj === 'object' ? (valObj.total || 0) : valObj;
            let cash = typeof valObj === 'object' ? (valObj.cash || 0) : 0;
            let claims = typeof valObj === 'object' ? (valObj.claims || 0) : 0;
            $incomeList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted d-block">${escapeHtml(schemeName)}</span>
                        <small class="text-secondary" style="font-size: 0.75rem;">Cash: ${formatCurrency(cash)} | Claims: ${formatCurrency(claims)}</small>
                    </div>
                    <span class="fw-bold text-success">${formatCurrency(total)}</span>
                </li>
            `);
        }
    } else {
        $incomeList.html('<li class="list-group-item text-center text-muted">No data</li>');
    }

    // Populate Patients by Scheme
    const $patList = $('#exec-det-patients-scheme-list');
    $patList.empty();
    if (data.patients_by_scheme && Object.keys(data.patients_by_scheme).length) {
        for (const [schemeName, val] of Object.entries(data.patients_by_scheme)) {
            $patList.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted">${escapeHtml(schemeName)}</span>
                    <span class="badge bg-secondary rounded-pill">${formatNumber(val)}</span>
                </li>
            `);
        }
    } else {
        $patList.html('<li class="list-group-item text-center text-muted">No data</li>');
    }
};

$(document).on('click', '#btn-refresh-executive-summary', function() {
    collectFilters();
    loadExecutiveSummaryData();
});

// Intercept ajax call globally or we can just append to the end of loadExecutiveSummaryData
// For simplicity, we hook it via ajaxComplete for the specific URL
$(document).ajaxSuccess(function(event, xhr, settings) {
    if (settings.url.indexOf('/pharmacy-workbench/reports/executive-summary') === 0 && !settings.url.includes('print')) {
        originalLoadExecSummaryAjaxSuccess(xhr.responseJSON);
    }
});

// Toggle Print button visibility based on active sub-tab
$(document).on('shown.bs.tab', 'button[data-bs-toggle="pill"]', function (e) {
    if (e.target.id === 'exec-detailed-sub-tab') {
        $('#btn-print-executive-summary').removeClass('d-none');
    } else {
        $('#btn-print-executive-summary').addClass('d-none');
    }
});

// Print functionality
$(document).on('click', '#btn-print-executive-summary', function() {
    collectFilters();
    const printUrl = '/pharmacy-workbench/reports/executive-summary/print?' + $.param(pharmReportFilters);
    const printWindow = window.open(printUrl, '_blank', 'width=1000,height=800');
});


// Initialize DataTables
function initPharmacyReportsDataTables() {
    // Dispensing Report Table
    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        $('#pharm-dispensing-table').DataTable().destroy();
    }

    window.pharmDispensingTable = $('#pharm-dispensing-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl('/pharmacy-workbench/reports/dispensing'),
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            }
        },
        columns: [
            { data: 'dispensed_at', render: data => formatDateTimeShort(data) },
            { data: 'reference_no' },
            { data: 'patient_name' },
            { data: 'product_name' },
            { data: 'quantity', className: 'text-center' },
            { data: 'amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'payment_type', render: data => `<span class="badge bg-secondary">${data || 'N/A'}</span>` },
            { data: 'store_name' },
            { data: 'pharmacist_name' },
            {
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `<button class="btn btn-xs btn-outline-info" onclick="viewDispensingDetail(${data})" title="View Details">
                        <i class="mdi mdi-eye"></i>
                    </button>`;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Stock Report Table
    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        $('#pharm-stock-table').DataTable().destroy();
    }

    window.pharmStockTable = $('#pharm-stock-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: wbUrl('/pharmacy-workbench/reports/stock'),
            data: function(d) {
                return $.extend({}, d, {
                    store_id: $('#stock-report-store-filter').val(),
                    category_id: $('#stock-report-category-filter').val(),
                    low_stock_only: $('#stock-show-low-only').is(':checked') ? 1 : 0,
                    date_from: pharmReportFilters.date_from,
                    date_to: pharmReportFilters.date_to
                });
            }
        },
        columns: [
            { data: 'product_name' },
            { data: 'product_code' },
            { data: 'category_name' },
            { data: 'reorder_level', className: 'text-center' },
            { data: 'global_stock', className: 'text-center fw-bold' },
            {
                data: 'store_breakdown',
                orderable: false,
                render: function(data) {
                    if (!data || !data.length) return '<span class="text-muted">N/A</span>';
                    return '<div class="store-stock-breakdown">' +
                        data.map(s => {
                            const qtyClass = s.quantity <= 0 ? 'qty-out' : (s.quantity <= s.reorder_level ? 'qty-low' : 'qty-ok');
                            return `<span class="store-stock-item">
                                <span class="store-name">${escapeHtml(s.store_name)}:</span>
                                <span class="store-qty ${qtyClass}">${s.quantity}</span>
                            </span>`;
                        }).join('') + '</div>';
                }
            },
            { data: 'dispensed_qty', className: 'text-center' },
            { data: 'unit_price', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'stock_value', className: 'text-end', render: data => formatCurrency(data) },
            {
                data: 'status',
                className: 'text-center',
                render: function(data, type, row) {
                    const globalStock = row.global_stock || 0;
                    const reorder = row.reorder_level || 0;

                    if (globalStock <= 0) {
                        return '<span class="stock-status-badge out-of-stock"><i class="mdi mdi-alert-circle"></i> Out</span>';
                    } else if (globalStock <= reorder * 0.5) {
                        return '<span class="stock-status-badge critical"><i class="mdi mdi-alert"></i> Critical</span>';
                    } else if (globalStock <= reorder) {
                        return '<span class="stock-status-badge low-stock"><i class="mdi mdi-alert-outline"></i> Low</span>';
                    } else {
                        return '<span class="stock-status-badge in-stock"><i class="mdi mdi-check-circle"></i> OK</span>';
                    }
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Performance Report Table
    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        $('#pharm-performance-table').DataTable().destroy();
    }

    window.pharmPerformanceTable = $('#pharm-performance-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: wbUrl('/pharmacy-workbench/reports/performance'),
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            },
            dataSrc: function(json) {
                updatePerformanceTotals(json.totals || {});
                return json.data || [];
            }
        },
        columns: [
            { data: 'pharmacist_name' },
            { data: 'total_dispensed', className: 'text-center' },
            { data: 'total_revenue', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'cash_transactions', className: 'text-center' },
            { data: 'hmo_transactions', className: 'text-center' },
            { data: 'cash_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'hmo_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'avg_tat', className: 'text-center', render: data => data ? `${data} min` : '-' },
            { data: 'unique_patients', className: 'text-center' }
        ],
        order: [[2, 'desc']],
        pageLength: 25,
        footerCallback: function() {}
    });

    // HMO Claims Table
    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        $('#pharm-hmo-table').DataTable().destroy();
    }

    window.pharmHmoTable = $('#pharm-hmo-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: wbUrl('/pharmacy-workbench/reports/hmo-claims'),
            data: function(d) {
                return $.extend({}, d, pharmReportFilters);
            },
            dataSrc: function(json) {
                updateHmoTotals(json.totals || {});
                return json.data || [];
            }
        },
        columns: [
            { data: 'hmo_name' },
            { data: 'total_claims', className: 'text-center' },
            { data: 'total_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'validated_count', className: 'text-center' },
            { data: 'validated_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'pending_count', className: 'text-center' },
            { data: 'pending_amount', className: 'text-end', render: data => formatCurrency(data) },
            { data: 'rejected_count', className: 'text-center' },
            { data: 'rejected_amount', className: 'text-end', render: data => formatCurrency(data) }
        ],
        order: [[2, 'desc']],
        pageLength: 25
    });

    // Revenue Table (custom implementation)
    initRevenueTable();
}

// Revenue table with grouping
function initRevenueTable() {
    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        $('#pharm-revenue-table').DataTable().destroy();
    }

    loadRevenueData('daily');
}

$('input[name="revenue-group"]').on('change', function() {
    loadRevenueData($(this).val());
});

function loadRevenueData(groupBy) {
    $.ajax({
        url: wbUrl('/pharmacy-workbench/reports/revenue'),
        method: 'GET',
        data: $.extend({}, pharmReportFilters, { group_by: groupBy }),
        success: function(response) {
            const $tbody = $('#pharm-revenue-table tbody');
            $tbody.empty();

            if (!response.data || !response.data.length) {
                $tbody.html('<tr><td colspan="8" class="text-center text-muted py-3">No revenue data available</td></tr>');
                updateRevenueTotals({});
                return;
            }

            response.data.forEach(row => {
                $tbody.append(`
                    <tr>
                        <td>${escapeHtml(row.period)}</td>
                        <td class="text-center">${formatNumber(row.transactions)}</td>
                        <td class="text-end">${formatCurrency(row.cash)}</td>
                        <td class="text-end">${formatCurrency(row.card)}</td>
                        <td class="text-end">${formatCurrency(row.transfer)}</td>
                        <td class="text-end">${formatCurrency(row.hmo)}</td>
                        <td class="text-end fw-bold">${formatCurrency(row.total)}</td>
                        <td class="text-end">${formatCurrency(row.avg_transaction)}</td>
                    </tr>
                `);
            });

            updateRevenueTotals(response.totals || {});
        }
    });
}

function updateRevenueTotals(totals) {
    $('#revenue-total-txn').text(formatNumber(totals.transactions || 0));
    $('#revenue-total-cash').text(formatCurrency(totals.cash || 0));
    $('#revenue-total-card').text(formatCurrency(totals.card || 0));
    $('#revenue-total-transfer').text(formatCurrency(totals.transfer || 0));
    $('#revenue-total-hmo').text(formatCurrency(totals.hmo || 0));
    $('#revenue-total-all').text(formatCurrency(totals.total || 0));
    $('#revenue-total-avg').text(formatCurrency(totals.avg_transaction || 0));
}

function updatePerformanceTotals(totals) {
    $('#perf-total-dispensed').text(formatNumber(totals.total_dispensed || 0));
    $('#perf-total-revenue').text(formatCurrency(totals.total_revenue || 0));
    $('#perf-total-cash-txn').text(formatNumber(totals.cash_transactions || 0));
    $('#perf-total-hmo-txn').text(formatNumber(totals.hmo_transactions || 0));
    $('#perf-total-cash-amt').text(formatCurrency(totals.cash_amount || 0));
    $('#perf-total-hmo-amt').text(formatCurrency(totals.hmo_amount || 0));
    $('#perf-avg-tat').text(totals.avg_tat ? `${totals.avg_tat} min` : '-');
    $('#perf-total-patients').text(formatNumber(totals.unique_patients || 0));
}

function updateHmoTotals(totals) {
    $('#hmo-total-claims').text(formatNumber(totals.total_claims || 0));
    $('#hmo-total-amount').text(formatCurrency(totals.total_amount || 0));
    $('#hmo-total-validated').text(formatNumber(totals.validated_count || 0));
    $('#hmo-total-validated-amt').text(formatCurrency(totals.validated_amount || 0));
    $('#hmo-total-pending').text(formatNumber(totals.pending_count || 0));
    $('#hmo-total-pending-amt').text(formatCurrency(totals.pending_amount || 0));
    $('#hmo-total-rejected').text(formatNumber(totals.rejected_count || 0));
    $('#hmo-total-rejected-amt').text(formatCurrency(totals.rejected_amount || 0));
}

// Refresh all DataTables
function refreshPharmacyDataTables() {
    if (window.pharmDispensingTable) window.pharmDispensingTable.ajax.reload();
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
    if (window.pharmPerformanceTable) window.pharmPerformanceTable.ajax.reload();
    if (window.pharmHmoTable) window.pharmHmoTable.ajax.reload();
    loadRevenueData($('input[name="revenue-group"]:checked').val() || 'daily');
}

// Stock report filter handlers
$('#stock-report-store-filter, #stock-report-category-filter').on('change', function() {
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
});

$('#stock-show-low-only').on('change', function() {
    if (window.pharmStockTable) window.pharmStockTable.ajax.reload();
});

// Initialize Charts
function initPharmacyReportsCharts() {
    // Trend Chart
    const trendCtx = document.getElementById('pharm-trend-chart');
    if (trendCtx) {
        window.pharmTrendChart = new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Dispensed Items',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Revenue (₦)',
                        data: [],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return `Revenue: ${formatCurrency(context.raw)}`;
                                }
                                return `${context.dataset.label}: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Items' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue (₦)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    // Revenue Pie Chart
    const pieCtx = document.getElementById('pharm-revenue-pie');
    if (pieCtx) {
        window.pharmRevenuePieChart = new Chart(pieCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Card', 'Transfer', 'HMO', 'Account'],
                datasets: [{
                    data: [0, 0, 0, 0, 0],
                    backgroundColor: [
                        '#28a745',
                        '#17a2b8',
                        '#6f42c1',
                        '#fd7e14',
                        '#e83e8c'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total> 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${formatCurrency(context.raw)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
}

// Update charts with data
function updateTrendChart(data) {
    if (!window.pharmTrendChart || !data.length) return;

    window.pharmTrendChart.data.labels = data.map(d => d.date);
    window.pharmTrendChart.data.datasets[0].data = data.map(d => d.items);
    window.pharmTrendChart.data.datasets[1].data = data.map(d => d.revenue);
    window.pharmTrendChart.update();
}

function updateRevenuePieChart(breakdown) {
    if (!window.pharmRevenuePieChart) return;

    window.pharmRevenuePieChart.data.datasets[0].data = [
        breakdown.cash || 0,
        breakdown.card || 0,
        breakdown.transfer || 0,
        breakdown.hmo || 0,
        breakdown.account || 0
    ];
    window.pharmRevenuePieChart.update();
}

// Export functions
$('#export-reports-excel').on('click', function() {
    exportReportsToExcel();
});

$('#export-reports-pdf').on('click', function() {
    toastr.info('PDF export will be generated. Please use Print> Save as PDF for now.');
    printReports();
});

$('#print-reports').on('click', function() {
    printReports();
});

function exportReportsToExcel() {
    const activeTabId = $('#pharmacy-report-tabs .nav-link.active').attr('id');
    const tabName = $('#pharmacy-report-tabs .nav-link.active').text().trim();

    toastr.info('Preparing Excel export...');

    try {
        const wb = XLSX.utils.book_new();

        // Add summary sheet with statistics
        const summaryData = [
            ['Pharmacy Reports & Analytics'],
            ['Generated:', new Date().toLocaleString()],
            ['Report Type:', tabName],
            ['Date Range:', `${$('#pharm-report-date-from').val() || 'All'} to ${$('#pharm-report-date-to').val() || 'All'}`],
            [],
            ['Key Statistics:'],
            ['Dispensed:', $('#pharm-stat-dispensed').text()],
            ['Total Revenue:', $('#pharm-stat-revenue').text()],
            ['Cash Sales:', $('#pharm-stat-cash').text()],
            ['HMO Claims:', $('#pharm-stat-hmo').text()],
            ['Patients:', $('#pharm-stat-patients').text()],
            ['Pending:', $('#pharm-stat-pending').text()],
        ];

        const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
        XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

        // Export based on active tab
        switch(activeTabId) {
            case 'pharm-overview-tab':
                exportOverviewData(wb);
                break;
            case 'pharm-dispensing-tab':
                exportDispensingData(wb);
                break;
            case 'pharm-revenue-tab':
                exportRevenueData(wb);
                break;
            case 'pharm-stock-tab':
                exportStockData(wb);
                break;
            case 'pharm-performance-tab':
                exportPerformanceData(wb);
                break;
            case 'pharm-hmo-tab':
                exportHmoData(wb);
                break;
        }

        // Download file
        const fileName = `Pharmacy_${tabName.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.xlsx`;
        XLSX.writeFile(wb, fileName);
        toastr.success('Excel file downloaded successfully');

    } catch (error) {
        console.error('Export error:', error);
        toastr.error('Failed to export data');
    }
}

function exportOverviewData(wb) {
    // Top Products
    const topProducts = [];
    $('#pharm-top-products-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            topProducts.push({
                'Rank': $(this).find('td:eq(0)').text(),
                'Product': $(this).find('td:eq(1)').text(),
                'Quantity': $(this).find('td:eq(2)').text(),
                'Revenue': $(this).find('td:eq(3)').text()
            });
        }
    });
    if (topProducts.length> 0) {
        const ws1 = XLSX.utils.json_to_sheet(topProducts);
        XLSX.utils.book_append_sheet(wb, ws1, 'Top Products');
    }

    // Payment Methods
    const paymentMethods = [];
    $('#pharm-payment-methods-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            paymentMethods.push({
                'Method': $(this).find('td:eq(0)').text(),
                'Transactions': $(this).find('td:eq(1)').text(),
                'Amount': $(this).find('td:eq(2)').text(),
                'Percentage': $(this).find('td:eq(3)').text()
            });
        }
    });
    if (paymentMethods.length> 0) {
        const ws2 = XLSX.utils.json_to_sheet(paymentMethods);
        XLSX.utils.book_append_sheet(wb, ws2, 'Payment Methods');
    }
}

function exportDispensingData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        const table = $('#pharm-dispensing-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date/Time': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'Product': row[4],
            'Quantity': row[5],
            'Pharmacist': row[6],
            'Store': row[7],
            'Amount': row[8],
            'Payment': row[9],
            'Status': row[10]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Dispensing Records');
    }
}

function exportRevenueData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        const table = $('#pharm-revenue-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'Services': row[4],
            'Gross Amount': row[5],
            'Discount': row[6],
            'Net Amount': row[7],
            'Payment Method': row[8],
            'HMO': row[9]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Revenue Records');
    }
}

function exportStockData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        const table = $('#pharm-stock-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Product': row[0],
            'Category': row[1],
            'Total Stock': row[2],
            'Available': row[3],
            'Allocated': row[4],
            'Reorder Level': row[5],
            'Status': row[6],
            'Store Breakdown': row[7]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Stock Status');
    }
}

function exportPerformanceData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        const table = $('#pharm-performance-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Pharmacist': row[0],
            'Transactions': row[1],
            'Items Dispensed': row[2],
            'Total Revenue': row[3],
            'Avg Transaction': row[4],
            'Patients Served': row[5],
            'Work Hours': row[6],
            'Efficiency': row[7]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'Performance Metrics');
    }
}

function exportHmoData(wb) {
    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        const table = $('#pharm-hmo-table').DataTable();
        const data = table.rows({ search: 'applied' }).data().toArray();

        const exportData = data.map(row => ({
            'Date': row[0],
            'Ref #': row[1],
            'Patient': row[2],
            'File No': row[3],
            'HMO': row[4],
            'Services': row[5],
            'Amount': row[6],
            'Validation Status': row[7],
            'Validated By': row[8],
            'Remarks': row[9]
        }));

        const ws = XLSX.utils.json_to_sheet(exportData);
        XLSX.utils.book_append_sheet(wb, ws, 'HMO Claims');
    }
}

function printReports() {
    const activeTabId = $('#pharmacy-report-tabs .nav-link.active').attr('id');
    const tabName = $('#pharmacy-report-tabs .nav-link.active').text().trim();
    const dateFrom = $('#pharm-report-date-from').val() || 'All';
    const dateTo = $('#pharm-report-date-to').val() || 'All';

    // Get statistics
    const stats = {
        dispensed: $('#pharm-stat-dispensed').text(),
        revenue: $('#pharm-stat-revenue').text(),
        cash: $('#pharm-stat-cash').text(),
        hmo: $('#pharm-stat-hmo').text(),
        patients: $('#pharm-stat-patients').text(),
        pending: $('#pharm-stat-pending').text()
    };

    let reportContent = '';

    // Build content based on active tab
    switch(activeTabId) {
        case 'pharm-overview-tab':
            reportContent = buildOverviewPrintContent();
            break;
        case 'pharm-dispensing-tab':
            reportContent = buildDispensingPrintContent();
            break;
        case 'pharm-revenue-tab':
            reportContent = buildRevenuePrintContent();
            break;
        case 'pharm-stock-tab':
            reportContent = buildStockPrintContent();
            break;
        case 'pharm-performance-tab':
            reportContent = buildPerformancePrintContent();
            break;
        case 'pharm-hmo-tab':
            reportContent = buildHmoPrintContent();
            break;
    }

    const printWindow = window.open('', '_blank', 'width=1200,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Pharmacy Report - ${tabName}</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    font-size: 11px;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 3px solid #667eea;
                }
                .print-header h2 {
                    color: #667eea;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(6, 1fr);
                    gap: 10px;
                    margin-bottom: 20px;
                }
                .stat-box {
                    text-align: center;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    border-left: 3px solid #667eea;
                }
                .stat-box h5 {
                    margin: 0;
                    font-size: 16px;
                    color: #333;
                    font-weight: bold;
                }
                .stat-box small {
                    color: #666;
                    font-size: 10px;
                    text-transform: uppercase;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 10px;
                }
                th, td {
                    border: 1px solid #ddd;
                    padding: 6px 8px;
                    text-align: left;
                }
                th {
                    background: #667eea;
                    color: white;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                tbody tr:nth-child(even) {
                    background: #f8f9fa;
                }
                .no-print {
                    display: none;
                }
                @media print {
                    body { padding: 10px; }
                    .no-print { display: none !important; }
                    table { page-break-inside: auto; }
                    tr { page-break-inside: avoid; page-break-after: auto; }
                    thead { display: table-header-group; }
                }
                .info-section {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #e7f1ff;
                    border-radius: 5px;
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 2px solid #ddd;
                    text-align: center;
                    font-size: 9px;
                    color: #666;
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2>Pharmacy Reports & Analytics</h2>
                <h4>${tabName}</h4>
                <p style="margin: 5px 0; color: #666;">
                    Date Range: ${dateFrom} to ${dateTo} |
                    Generated: ${new Date().toLocaleString()}
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h5>${stats.dispensed}</h5>
                    <small>Dispensed</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.revenue}</h5>
                    <small>Revenue</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.cash}</h5>
                    <small>Cash</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.hmo}</h5>
                    <small>HMO</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.patients}</h5>
                    <small>Patients</small>
                </div>
                <div class="stat-box">
                    <h5>${stats.pending}</h5>
                    <small>Pending</small>
                </div>
            </div>

            ${reportContent}

            <div class="footer">
                <p><strong>''</strong></p>
                <p>This is a system-generated report</p>
            </div>

            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function buildOverviewPrintContent() {
    let html = '<div class="info-section"><h5>Overview Report</h5></div>';

    // Top Products
    html += '<h6>Top 10 Products</h6><table><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead><tbody>';
    $('#pharm-top-products-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            html += '<tr>';
            $(this).find('td').each(function() {
                html += `<td>${$(this).text()}</td>`;
            });
            html += '</tr>';
        }
    });
    html += '</tbody></table>';

    // Payment Methods
    html += '<h6>Payment Methods</h6><table><thead><tr><th>Method</th><th>Transactions</th><th>Amount</th><th>%</th></tr></thead><tbody>';
    $('#pharm-payment-methods-tbody tr').each(function() {
        if ($(this).find('td').length> 1) {
            html += '<tr>';
            $(this).find('td').each(function() {
                html += `<td>${$(this).text()}</td>`;
            });
            html += '</tr>';
        }
    });
    html += '</tbody></table>';

    return html;
}

function buildDispensingPrintContent() {
    let html = '<div class="info-section"><h5>Dispensing Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-dispensing-table')) {
        const table = $('#pharm-dispensing-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                // Strip HTML tags for clean printing
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildRevenuePrintContent() {
    let html = '<div class="info-section"><h5>Revenue Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-revenue-table')) {
        const table = $('#pharm-revenue-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildStockPrintContent() {
    let html = '<div class="info-section"><h5>Stock Status Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-stock-table')) {
        const table = $('#pharm-stock-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildPerformancePrintContent() {
    let html = '<div class="info-section"><h5>Performance Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-performance-table')) {
        const table = $('#pharm-performance-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

function buildHmoPrintContent() {
    let html = '<div class="info-section"><h5>HMO Claims Report</h5></div><table>';

    if ($.fn.DataTable.isDataTable('#pharm-hmo-table')) {
        const table = $('#pharm-hmo-table').DataTable();
        html += '<thead><tr>';
        table.columns().header().each(function() {
            html += `<th>${$(this).text()}</th>`;
        });
        html += '</tr></thead><tbody>';

        table.rows({ search: 'applied' }).every(function() {
            const data = this.data();
            html += '<tr>';
            data.forEach(cell => {
                const cleanText = $('<div>').html(cell).text();
                html += `<td>${cleanText}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody>';
    }

    html += '</table>';
    return html;
}

// Helper functions
function formatNumber(num) {
    return new Intl.NumberFormat().format(num || 0);
}

function formatCurrency(amount) {
    return '₦' + new Intl.NumberFormat().format(parseFloat(amount || 0).toFixed(2));
}

function formatDateTimeShort(dateString) {
    if (!dateString) return 'N/A';
    const d = new Date(dateString);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function viewDispensingDetail(id) {
    // TODO: Implement detail view modal
    toastr.info('Detail view coming soon');
}

// Tab change handler - lazy load data
$('#pharmacy-report-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
    const tabId = $(e.target).attr('id');
    // Tables are already initialized, just let DataTables handle it
});

// ===========================================
// END PHARMACY REPORTS MODULE
// ===========================================

// ===========================================
// PRODUCT ADAPTATION MODULE
// ===========================================

// Store selected new product data
let selectedNewProduct = null;

// Open product adaptation modal with billing status awareness
function openAdaptationModal(productRequestId, productName, dose, qty, price, status, payable, claims, isPaid, isValidated, coverageMode, productCode) {
    // Reset modal state
    selectedNewProduct = null;
    $('#adapt-product-request-id').val(productRequestId);
    $('#adapt-billing-status').val(status || 'unbilled');
    $('#adapt-coverage-mode').val(coverageMode || 'none');

    // Store original values for calculations
    const originalQty = parseInt(qty) || 1;
    const originalPrice = parseFloat(price) || 0;
    const originalTotal = originalPrice * originalQty;

    $('#adapt-original-price-value').val(originalPrice);
    $('#adapt-original-qty-value').val(originalQty);

    // Set original product info (enhanced layout)
    $('#adapt-original-product').text(productName);
    $('#adapt-original-code').text(productCode || '-');
    $('#adapt-original-dose').text(dose || 'N/A');
    $('#adapt-original-qty').text(originalQty);
    $('#adapt-original-price').text('₦' + formatMoneyPharmacy(originalPrice));
    $('#adapt-original-total').text('₦' + formatMoneyPharmacy(originalTotal));

    // Set status badge
    const statusBadge = status === 'billed' ?
        '<span class="badge bg-success">Billed</span>' :
        '<span class="badge bg-secondary">Unbilled</span>';
    $('#adapt-original-status-badge').html(statusBadge);

    // Set calculation summary original
    $('#adapt-calc-original').text('₦' + formatMoneyPharmacy(originalTotal));
    $('#adapt-calc-new').text('₦0.00');
    $('#adapt-calc-diff').html('<span class="text-muted">₦0.00</span>');
    $('#adapt-calc-note').hide();

    // Reset new product selection
    $('#adapt-new-product').val('').trigger('change');
    $('#adapt-new-qty').val(originalQty);
    $('#adapt-reason').val('');
    $('#adapt-new-product-details').hide();
    $('#adapt-no-product-selected').show();
    $('#adapt-store-stocks').empty();
    $('#adapt-hmo-info').hide();
    $('#confirm-adaptation').prop('disabled', true);
    $('#adapt-summary-text').text('Select a new product to see the changes');

    // Reset step indicators
    $('.adapt-steps .step').removeClass('active');
    $('#adapt-step-1').addClass('active');

    // Show/hide relevant notices and billing info based on status
    const isBilled = status === 'billed';
    $('#adapt-unbilled-notice').toggle(!isBilled);
    $('#adapt-billed-notice').toggle(isBilled);
    $('#adapt-current-billing').toggle(isBilled);
    $('#adapt-billing-impact').hide();

    if (isBilled) {
        $('#adapt-current-payable').text('₦' + formatMoneyPharmacy(payable || 0));
        $('#adapt-current-claims').text('₦' + formatMoneyPharmacy(claims || 0));
        $('#adapt-current-coverage').text((coverageMode || 'none').toUpperCase());
    }

    // Initialize Select2 for product search if not already done
    if (!$('#adapt-new-product').hasClass('select2-hidden-accessible')) {
        $('#adapt-new-product').select2({
            dropdownParent: $('#productAdaptationModal'),
            placeholder: 'Type to search products...',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: wbUrl('/pharmacy-workbench/search-products'),
                dataType: 'json',
                delay: 300,
                data: function(params) {
                    return {
                        term: params.term,
                        patient_id: currentPatient
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(p => ({
                            id: p.id,
                            text: `${p.product_name} (${p.product_code || 'N/A'}) - ₦${formatMoneyPharmacy(p.price || 0)} [Stock: ${p.stock_qty || 0}]`,
                            product: p
                        }))
                    };
                }
            }
        }).on('select2:select', function(e) {
            selectedNewProduct = e.params.data.product;
            updateAdaptationPreview();
        }).on('select2:clear', function() {
            selectedNewProduct = null;
            $('#adapt-new-product-details').hide();
            $('#adapt-no-product-selected').show();
            $('#adapt-billing-impact').hide();
            $('#adapt-hmo-info').hide();
            $('#confirm-adaptation').prop('disabled', true);
            $('#adapt-step-2').removeClass('active');
            $('#adapt-step-3').removeClass('active');
            $('#adapt-calc-new').text('₦0.00');
            $('#adapt-calc-diff').html('<span class="text-muted">₦0.00</span>');
            $('#adapt-calc-note').hide();
            $('#adapt-summary-text').text('Select a new product to see the changes');
        });
    }

    $('#productAdaptationModal').modal('show');
}

// Update adaptation preview when new product or quantity changes
function updateAdaptationPreview() {
    const isBilled = $('#adapt-billing-status').val() === 'billed';
    const newQty = parseInt($('#adapt-new-qty').val()) || 1;
    const originalPrice = parseFloat($('#adapt-original-price-value').val()) || 0;
    const originalQty = parseInt($('#adapt-original-qty-value').val()) || 1;
    const originalTotal = originalPrice * originalQty;

    if (!selectedNewProduct) {
        $('#adapt-new-product-details').hide();
        $('#adapt-no-product-selected').show();
        $('#adapt-billing-impact').hide();
        $('#adapt-hmo-info').hide();
        $('#confirm-adaptation').prop('disabled', true);
        return;
    }

    // Show new product details section
    $('#adapt-no-product-selected').hide();
    $('#adapt-new-product-details').show();

    // Show new product price
    const newPrice = selectedNewProduct.price || 0;
    const newTotal = newPrice * newQty;
    $('#adapt-new-price').text('₦' + formatMoneyPharmacy(newPrice));

    // Update step indicators
    $('#adapt-step-2').addClass('active');

    // ===== STORE STOCKS DISPLAY =====
    const storeStocks = selectedNewProduct.store_stocks || [];
    const globalStock = selectedNewProduct.stock_qty || 0;
    const $stockContainer = $('#adapt-store-stocks');
    $stockContainer.empty();

    // Update stock badge with intuitive thresholds
    // Out (0), Critical (1-5), Low (6-20), OK (>20)
    const $stockBadge = $('#adapt-stock-badge');
    $stockBadge.removeClass('bg-success bg-warning bg-danger badge-stock-ok badge-stock-low badge-stock-critical badge-stock-out');

    if (globalStock <= 0) {
        $stockBadge.addClass('badge-stock-out').html('<i class="mdi mdi-alert-circle"></i> Out of Stock');
    } else if (globalStock <= 5) {
        $stockBadge.addClass('badge-stock-critical').html(`<i class="mdi mdi-alert"></i> ${globalStock} only!`);
    } else if (globalStock <= 20) {
        $stockBadge.addClass('badge-stock-low').html(`<i class="mdi mdi-alert-outline"></i> ${globalStock} left`);
    } else {
        $stockBadge.addClass('badge-stock-ok').text(globalStock + ' in stock');
    }

    // Display store stocks with intuitive thresholds
    if (storeStocks.length> 0) {
        storeStocks.forEach(function(store) {
            let stockClass, stockIcon;
            if (store.quantity <= 0) {
                stockClass = 'text-danger';
                stockIcon = 'mdi-alert-circle';
            } else if (store.quantity <= 5) {
                stockClass = 'text-danger';
                stockIcon = 'mdi-alert';
            } else if (store.quantity <= 20) {
                stockClass = 'text-warning';
                stockIcon = 'mdi-alert-outline';
            } else {
                stockClass = 'text-success';
                stockIcon = 'mdi-check-circle';
            }
            $stockContainer.append(`
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span><i class="mdi mdi-store text-muted"></i> ${store.store_name}</span>
                    <strong class="${stockClass}"><i class="mdi ${stockIcon} small"></i> ${store.quantity}</strong>
                </div>
            `);
        });
    } else {
        $stockContainer.html('<div class="text-center text-muted py-2"><i class="mdi mdi-alert-circle-outline"></i> No stock available</div>');
    }

    // ===== PRICE CALCULATION SUMMARY =====
    $('#adapt-calc-original').text('₦' + formatMoneyPharmacy(originalTotal));
    $('#adapt-calc-new').text('₦' + formatMoneyPharmacy(newTotal));

    const priceDiff = newTotal - originalTotal;
    let diffHtml = '';
    let calcNote = '';

    if (priceDiff> 0) {
        diffHtml = `<span class="text-danger fw-bold">+₦${formatMoneyPharmacy(priceDiff)}</span>`;
        calcNote = '<i class="mdi mdi-arrow-up text-danger"></i> Patient will pay more';
    } else if (priceDiff < 0) {
        diffHtml = `<span class="text-success fw-bold">-₦${formatMoneyPharmacy(Math.abs(priceDiff))}</span>`;
        calcNote = '<i class="mdi mdi-arrow-down text-success"></i> Patient saves money!';
    } else {
        diffHtml = '<span class="text-muted">₦0.00</span>';
        calcNote = '<i class="mdi mdi-equal text-muted"></i> No price change';
    }

    $('#adapt-calc-diff').html(diffHtml);
    if (calcNote) {
        $('#adapt-calc-note').html(calcNote).show();
    } else {
        $('#adapt-calc-note').hide();
    }

    // ===== HMO COVERAGE INFO =====
    const coverageMode = selectedNewProduct.coverage_mode || $('#adapt-coverage-mode').val();
    const newPayable = selectedNewProduct.payable_amount || newTotal;
    const newClaims = selectedNewProduct.claims_amount || 0;

    if (coverageMode && coverageMode !== 'none') {
        $('#adapt-coverage-badge').text(coverageMode.toUpperCase());
        $('#adapt-new-payable').text('₦' + formatMoneyPharmacy(newPayable * newQty));
        $('#adapt-new-claims').text('₦' + formatMoneyPharmacy(newClaims * newQty));
        $('#adapt-hmo-info').show();
    } else {
        $('#adapt-hmo-info').hide();
    }

    // ===== BILLING IMPACT FOR BILLED ITEMS =====
    if (isBilled) {
        const currentPayable = parseFloat($('#adapt-current-payable').text().replace(/[₦,]/g, '')) || 0;
        const currentClaims = parseFloat($('#adapt-current-claims').text().replace(/[₦,]/g, '')) || 0;
        const currentTotal = currentPayable + currentClaims;

        let impactPayable = newTotal;
        let impactClaims = 0;

        // Apply same coverage ratio if HMO coverage exists
        if (coverageMode && coverageMode !== 'none' && currentTotal> 0) {
            const payableRatio = currentPayable / currentTotal;
            const claimsRatio = currentClaims / currentTotal;
            impactPayable = newTotal * payableRatio;
            impactClaims = newTotal * claimsRatio;
        }

        // Update impact table
        $('#adapt-impact-payable-old').text('₦' + formatMoneyPharmacy(currentPayable));
        $('#adapt-impact-payable-new').text('₦' + formatMoneyPharmacy(impactPayable));
        updateDiffBadge('#adapt-impact-payable-diff', impactPayable - currentPayable);

        $('#adapt-impact-claims-old').text('₦' + formatMoneyPharmacy(currentClaims));
        $('#adapt-impact-claims-new').text('₦' + formatMoneyPharmacy(impactClaims));
        updateDiffBadge('#adapt-impact-claims-diff', impactClaims - currentClaims);

        $('#adapt-impact-total-old').text('₦' + formatMoneyPharmacy(currentTotal));
        $('#adapt-impact-total-new').text('₦' + formatMoneyPharmacy(newTotal));
        updateDiffBadge('#adapt-impact-total-diff', newTotal - currentTotal);

        // Add note about billing update
        let note = 'The billing record will be automatically updated with the new amounts.';
        if (newTotal> currentTotal) {
            note += ' The patient/HMO will owe an additional amount.';
        } else if (newTotal < currentTotal) {
            note += ' A credit/refund will be recorded.';
        }
        $('#adapt-impact-note').html('<i class="mdi mdi-information-outline"></i> ' + note);

        $('#adapt-billing-impact').show();
    }

    // ===== UPDATE SUMMARY TEXT =====
    const productName = selectedNewProduct.product_name || 'selected product';
    let summaryText = `Adapting to "${productName}" × ${newQty} = ₦${formatMoneyPharmacy(newTotal)}`;
    if (priceDiff !== 0) {
        summaryText += ` (${priceDiff> 0 ? '+' : ''}₦${formatMoneyPharmacy(priceDiff)})`;
    }
    $('#adapt-summary-text').html(summaryText);

    // Enable confirm button
    $('#confirm-adaptation').prop('disabled', false);
    $('#adapt-step-3').addClass('active');
}

// Helper to update diff badge with color
function updateDiffBadge(selector, diff) {
    const formatted = (diff>= 0 ? '+' : '') + '₦' + formatMoneyPharmacy(Math.abs(diff));
    const badgeClass = diff> 0 ? 'text-danger' : (diff < 0 ? 'text-success' : 'text-muted');
    $(selector).html(`<span class="${badgeClass}">${formatted}</span>`);
}

// Listen for qty change to update preview
$('#adapt-new-qty').on('change input', function() {
    updateAdaptationPreview();
});

// Quantity +/- buttons
$('#adapt-qty-minus').on('click', function() {
    const $input = $('#adapt-new-qty');
    const current = parseInt($input.val()) || 1;
    if (current> 1) {
        $input.val(current - 1);
        updateAdaptationPreview();
    }
});

$('#adapt-qty-plus').on('click', function() {
    const $input = $('#adapt-new-qty');
    const current = parseInt($input.val()) || 1;
    $input.val(current + 1);
    updateAdaptationPreview();
});

// Quick reason buttons
$(document).on('click', '.adapt-quick-reason', function() {
    const reason = $(this).data('reason');
    const $textarea = $('#adapt-reason');
    const currentText = $textarea.val().trim();

    if (currentText) {
        $textarea.val(currentText + '; ' + reason);
    } else {
        $textarea.val(reason);
    }

    // Highlight the button
    $(this).addClass('btn-secondary').removeClass('btn-outline-secondary');
});

// Confirm product adaptation
$('#confirm-adaptation').on('click', function() {
    const productRequestId = $('#adapt-product-request-id').val();
    const newProductId = $('#adapt-new-product').val();
    const newQty = $('#adapt-new-qty').val();
    const reason = $('#adapt-reason').val().trim();

    if (!newProductId) {
        toastr.warning('Please select a new product');
        return;
    }

    if (!reason) {
        toastr.warning('Please enter a reason for adaptation');
        $('#adapt-reason').focus();
        return;
    }

    const $btn = $(this);
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

    $.ajax({
        url: wbUrl(`/pharmacy-workbench/prescription/${productRequestId}/adapt`),
        method: 'POST',
        data: {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
            new_product_id: newProductId,
            new_qty: newQty,
            adaptation_note: reason
        },
        success: function(response) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.success(response.message || 'Prescription adapted successfully');
            $('#productAdaptationModal').modal('hide');

            // Refresh prescription lists
            loadPrescriptionItems(currentStatusFilter);
            refreshAllPrescTables();
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            toastr.error(xhr.responseJSON?.message || 'Failed to adapt prescription');
        }
    });
});

