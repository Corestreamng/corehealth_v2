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

// =====================================
// NURSING WORKBENCH SPECIFIC FUNCTIONS
// =====================================

// Populate Overview Tab with patient data (called directly with data object)
function populateOverviewTab(data) {
    console.log('Populating overview tab with:', data); // Debug log

    // Populate Patient Information Card
    let patientInfoHtml = `
        <table class="table table-sm mb-0">
            <tr><th width="40%">File No:</th><td>${data.file_no || 'N/A'}</td></tr>
            <tr><th>Name:</th><td>${data.name || 'N/A'}</td></tr>
            <tr><th>Age/Gender:</th><td>${data.age || 'N/A'} / ${data.gender || 'N/A'}</td></tr>
            <tr><th>Phone:</th><td>${data.phone || 'N/A'}</td></tr>
            <tr><th>HMO:</th><td>${data.hmo || 'Private'}</td></tr>
            <tr><th>Blood Group:</th><td>${data.blood_group || 'Unknown'}</td></tr>
        </table>
    `;
    $('#overview-patient-info').html(patientInfoHtml);

    // Populate Admission Status Card
    let admissionHtml = '';
    if (data.admission) {
        admissionHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="40%">Bed/Ward:</th><td><span class="badge badge-info">${data.admission.bed || 'N/A'}</span></td></tr>
                <tr><th>Admitted:</th><td>${data.admission.admitted_date || 'N/A'}</td></tr>
                <tr><th>Duration:</th><td>${data.admission.days_admitted || '0'} day(s)</td></tr>
                <tr><th>Reason:</th><td>${data.admission.reason || 'N/A'}</td></tr>
            </table>
        `;
    } else {
        admissionHtml = '<p class="text-muted text-center py-3">Not currently admitted</p>';
    }
    $('#overview-admission-info').html(admissionHtml);

    // Populate Latest Vitals Card
    let vitalsHtml = '';
    if (data.last_vitals) {
        vitalsHtml = `
            <table class="table table-sm mb-0">
                <tr><th width="50%">BP:</th><td>${data.last_vitals.bp || 'N/A'}</td></tr>
                <tr><th>Heart Rate:</th><td>${data.last_vitals.heart_rate || 'N/A'} bpm</td></tr>
                <tr><th>Temp:</th><td>${data.last_vitals.temp || 'N/A'} ┬░C</td></tr>
                <tr><th>Resp Rate:</th><td>${data.last_vitals.resp_rate || 'N/A'} /min</td></tr>
                <tr><th>Recorded:</th><td><small class="text-muted">${data.last_vitals.time || 'N/A'}</small></td></tr>
            </table>
        `;
    } else {
        vitalsHtml = '<p class="text-muted text-center py-3">No vitals recorded</p>';
    }
    $('#overview-vitals-info').html(vitalsHtml);

    // Populate Latest Nurse Note
    let nurseNoteHtml = '';
    if (data.latest_nurse_note) {
        nurseNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-primary">${data.latest_nurse_note.type}</span>
                    <small class="text-muted">${data.latest_nurse_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_nurse_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_nurse_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        nurseNoteHtml = '<p class="text-muted text-center py-2">No nursing notes</p>';
    }
    $('#overview-nurse-note').html(nurseNoteHtml);

    // Populate Latest Doctor Note
    let doctorNoteHtml = '';
    if (data.latest_doctor_note) {
        doctorNoteHtml = `
            <div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="badge badge-info text-white">Doctor Note</span>
                    <small class="text-muted">${data.latest_doctor_note.time_ago}</small>
                </div>
                <div class="note-preview mb-2" style="font-size: 0.9rem;">
                    ${data.latest_doctor_note.note}
                </div>
                <div class="text-right">
                    <small class="text-muted">By: <strong>${data.latest_doctor_note.created_by}</strong></small>
                </div>
            </div>
        `;
    } else {
        doctorNoteHtml = '<p class="text-muted text-center py-2">No doctor notes</p>';
    }
    $('#overview-doctor-note').html(doctorNoteHtml);

    // Populate Allergies & Alerts Card - handle array, comma-separated string, JSON string, object, or null
    let allergiesHtml = '';
    let allergiesArray = [];
    if (data.allergies) {
        if (Array.isArray(data.allergies)) {
            allergiesArray = data.allergies;
        } else if (typeof data.allergies === 'string') {
            try {
                const parsed = JSON.parse(data.allergies);
                allergiesArray = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                allergiesArray = data.allergies.split(',').map(a => a.trim()).filter(a => a);
            }
        } else if (typeof data.allergies === 'object') {
            allergiesArray = Object.values(data.allergies).filter(a => a);
        }
    }

    if (allergiesArray.length> 0) {
        allergiesHtml = '<div class="d-flex flex-wrap">';
        allergiesArray.forEach(function(allergy) {
            allergiesHtml += `<span class="badge badge-danger m-1 p-2"><i class="mdi mdi-alert"></i> ${allergy}</span>`;
        });
        allergiesHtml += '</div>';
    } else {
        allergiesHtml = '<p class="text-success text-center py-2"><i class="mdi mdi-check-circle"></i> No known allergies</p>';
    }
    $('#overview-allergies').html(allergiesHtml);

    // Load pending medications for overview
    loadOverviewPendingMeds(data.id);

    // Load tasks for overview
    loadOverviewTasks(data.id);
}

// Load Patient Overview (fetches data first - used when switching tabs)
function loadPatientOverview(patientId) {
    if (currentPatientData && currentPatientData.id == patientId) {
        // Use cached data if available
        populateOverviewTab(currentPatientData);
        return;
    }

    $.ajax({
        url: wbUrl(`/nursing-workbench/patient/${patientId}/details`),
        method: 'GET',
        success: function(data) {
            currentPatientData = data;
            populateOverviewTab(data);
        },
        error: function() {
            $('#overview-patient-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-admission-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-vitals-info').html('<p class="text-danger">Failed to load</p>');
            $('#overview-nurse-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-doctor-note').html('<p class="text-danger">Failed to load</p>');
            $('#overview-allergies').html('<p class="text-danger">Failed to load</p>');
        }
    });
}

// Load pending medications for overview card (uses medication chart data)
function loadOverviewPendingMeds(patientId) {
    // Try to get medications from the existing medication chart API
    $.ajax({
        url: wbUrl(`/patients/${patientId}/nurse-chart/medication`),
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            let html = '';
            let pendingMeds = [];

            // Filter for pending/due medications if data is an array
            if (Array.isArray(data)) {
                pendingMeds = data.filter(med => med.status === 'pending' || med.status === 'due');
            } else if (data.medications) {
                pendingMeds = data.medications.filter(med => !med.is_administered);
            }

            if (pendingMeds.length> 0) {
                $('#overview-pending-meds-count').text(pendingMeds.length);
                html = '<ul class="list-group list-group-flush">';
                pendingMeds.slice(0, 5).forEach(function(med) {
                    html += `
                        <li class="list-group-item p-2 d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${med.drug_name || med.name || 'Unknown'}</strong>
                                <small class="d-block text-muted">${med.dose || ''} ${med.route || ''}</small>
                            </div>
                            <span class="badge badge-warning">${med.due_time || med.scheduled_time || 'Pending'}</span>
                        </li>
                    `;
                });
                html += '</ul>';
                if (pendingMeds.length> 5) {
                    html += `<small class="text-muted d-block text-center mt-2">+${pendingMeds.length - 5} more</small>`;
                }
            } else {
                $('#overview-pending-meds-count').text('0');
                html = '<p class="text-muted text-center py-2">No pending medications</p>';
            }
            $('#overview-pending-meds').html(html);
        },
        error: function() {
            // Silently fail - medications can be viewed in medication tab
            $('#overview-pending-meds').html('<p class="text-muted text-center py-2">View in Medication Tab</p>');
            $('#overview-pending-meds-count').text('-');
        }
    });
}

// Load tasks for overview card (placeholder - can be enhanced later)
function loadOverviewTasks(patientId) {
    // For now, just show a placeholder since tasks may not have a dedicated API yet
    $('#overview-tasks').html('<p class="text-muted text-center py-2">No tasks pending</p>');
    $('#overview-tasks-count').text('0');
}

// Load Admitted Patients Queue
function loadAdmittedPatients() {
    $.ajax({
        url: wbRoute('nursing-workbench.admitted-patients', '/nursing-workbench/admitted-patients'),
        method: 'GET',
        success: function(data) {
            if (data.length === 0) {
                showNotification('info', 'No admitted patients found');
                return;
            }
            // Display patients in a list/cards
            displayAdmittedPatientsQueue(data);
        },
        error: function() {
            showNotification('error', 'Failed to load admitted patients');
        }
    });
}

// Load Vitals Queue
function loadVitalsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-heart-pulse"></i> Vitals Queue');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: wbRoute('nursing-workbench.vitals-queue', '/nursing-workbench/vitals-queue'),
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No patients pending vitals"
        },
        drawCallback: function() {
            // Attach click handlers to cards
        }
    });
}

// Load Bed Requests Queue
function loadBedRequestsQueue() {
    $('#empty-state').hide();
    $('#workspace-content').removeClass('active');
    $('.patient-header').removeClass('active');
    $('#queue-view').addClass('active').css('display', 'flex');
    $('#queue-view-title').html('<i class="mdi mdi-bed"></i> Bed Requests');

    if ($.fn.DataTable.isDataTable('#queue-datatable')) {
        $('#queue-datatable').DataTable().destroy();
    }

    $('#queue-datatable').DataTable({
        processing: true,
        serverSide: true,
        ajax: wbRoute('nursing-workbench.bed-requests-queue', '/nursing-workbench/bed-requests-queue'),
        columns: [
            { data: 'info', name: 'info' }
        ],
        language: {
             emptyTable: "No pending bed requests"
        }
    });
}

// Load Medication Due
function loadMedicationDue() {
    showNotification('info', 'Medication due feature coming soon');
    // TODO: Implement medication due loading
}

// ========================================
// INJECTION MODULE - Drug Search & Administration
// ========================================

function setInjectionDrugSource(source) {
    $('#injection-drug-source').val(source);
    $('[data-inj-source]').removeClass('active');
    $(`[data-inj-source="${source}"]`).addClass('active');

    $('.source-section').hide();
    $('#inj-source-' + (source === 'ward_stock' ? 'ward' : source === 'patient_own' ? 'patient' : 'pharmacy')).show();

    // §7.1: Only ward_stock needs the hospital product search (Step 2)
    // Pharmacy uses prescription dropdown, Patient's Own uses free-text fields
    if (source === 'ward_stock') {
        $('.inj-non-pharmacy').show();
    } else {
        $('.inj-non-pharmacy').hide();
    }

    // Clear any previously selected items when switching source to avoid mixed payloads
    $('#injection-selected-body').empty();
    updateInjectionTotals();
    $('#injection-stock-error').remove();
}

function loadInjectionPrescriptions(force = false) {
    if (injectionPrescriptionsLoaded && !force) {
        return $.Deferred().resolve(injectionPrescriptions).promise();
    }
    if (!currentPatient) return $.Deferred().resolve([]).promise();

    const url = medicationChartPrescribedRoute.replace(':patient', currentPatient);
    return $.ajax({ url: url, type: 'GET' })
        .then(function(res) {
            if (res && res.success) {
                injectionPrescriptions = res.prescriptions || [];
                injectionPrescriptionsLoaded = true;
                populateInjectionRxSelect();
            }
            return injectionPrescriptions;
        })
        .catch(function(err) {
            console.error('Failed to load prescriptions for injections', err);
            return [];
        });
}

function populateInjectionRxSelect() {
    const select = $('#injection-rx-select');
    if (!select.length) return;

    select.empty();
    const dispensed = (injectionPrescriptions || []).filter(p => p.is_dispensed);

    if (dispensed.length === 0) {
        select.append('<option value="">No dispensed prescriptions</option>');
        return;
    }

    select.append('<option value="">-- Select dispensed prescription --</option>');
    dispensed.forEach(function(rx) {
        const label = `${rx.product_name || 'Drug'} (${rx.product_code || ''}) from ${rx.dispensed_from_store || 'Pharmacy'}`;
        select.append(`<option value="${rx.id}" data-product-id="${rx.product_id || ''}" data-remaining="${rx.remaining_doses ?? ''}">${label}</option>`);
    });
}

function addInjectionRxToTable() {
    const rxId = $('#injection-rx-select').val();
    if (!rxId) {
        showNotification('warning', 'Select a dispensed prescription first');
        return;
    }
    const rx = (injectionPrescriptions || []).find(p => p.id == rxId);
    if (!rx || !rx.is_dispensed) {
        showNotification('warning', 'Only dispensed prescriptions can be charted');
        return;
    }

    // Prevent duplicates
    if ($(`#injection-selected-body tr[data-product-request-id="${rx.id}"]`).length> 0) {
        showNotification('info', 'Prescription already added');
        return;
    }

    const price = parseFloat(rx.payable_amount || rx.claims_amount || 0) || 0;
    const coverage = rx.coverage_mode || 'cash';
    const coverageInfo = coverage && coverage !== 'cash'
        ? `<span class="badge bg-info">${coverage.toUpperCase()}</span>`
        : '<span class="badge bg-secondary">Cash</span>';

    const remaining = rx.remaining_doses ?? null;
    const remainText = remaining !== null ? `<div class="small text-muted">Remaining: ${remaining}</div>` : '';

    const row = `
        <tr data-product-id="${rx.product_id}" data-product-request-id="${rx.id}" data-price="${price}" data-source="pharmacy_dispensed">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${rx.product_name || 'Drug'}</strong><br>
                <small class="text-muted">[${rx.product_code || ''}]</small>
                ${remainText}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty" value="1" min="1" style="width: 60px;" readonly>
            </td>
            <td class="batch-cell text-muted">N/A</td>
            <td class="stock-cell text-muted">N/A</td>
            <td>₦${price.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm" name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>`;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    $('#injection-rx-summary').text(`${rx.product_name || 'Drug'} added`).show();
}

// Drug source toggle
$(document).on('click', '[data-inj-source]', function() {
    const source = $(this).data('inj-source');
    setInjectionDrugSource(source);
});

$('#injection-add-rx').on('click', function() {
    addInjectionRxToTable();
});

// Default selection
setInjectionDrugSource('pharmacy_dispensed');

// Injection Drug Search (uses same endpoint as prescription form)
let injectionSearchTimeout;
$('#injection-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(injectionSearchTimeout);

    if (query.length < 2) {
        $('#injection-drug-results').hide();
        return;
    }

    injectionSearchTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('live-search-products'),
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#injection-drug-results').html('');

                if (data.length === 0) {
                    $('#injection-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ₦${payable}</span> <span class='text-success ms-1'>Claim: ₦${claims}</span>`
                        : '';

                    const qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addInjectionDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>₦${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#injection-drug-results').append(mk);
                });
                $('#injection-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#injection-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Hide dropdown when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#injection-drug-search, #injection-drug-results').length) {
        $('#injection-drug-results').hide();
    }
    if (!$(e.target).closest('#vaccine-drug-search, #vaccine-drug-results').length) {
        $('#vaccine-drug-results').hide();
    }
});

// Add selected drug to injection table
function addInjectionDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    const drugSource = $('#injection-drug-source').val();

    // Check if store is selected first
    const storeId = $('#injection-store').val();
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('warning', 'Please select a ward store first');
        $('#injection-store').focus();
        return;
    }

    // Check if already added
    if ($(`#injection-selected-body tr[data-product-id="${id}"]`).length> 0) {
        showNotification('warning', 'This drug is already in the list');
        $('#injection-drug-results').hide();
        $('#injection-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">₦${payable}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}" data-source="${drugSource}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="injection_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="1" min="1" style="width: 60px;">
            </td>
            <td class="batch-cell">${drugSource === 'ward_stock' ? '<div class="batch-loading"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div><select class="form-control form-control-sm batch-select-dropdown d-none" name="injection_batch_id[]"><option value="">Auto (FIFO)</option></select><input type="hidden" name="injection_selected_batch_id[]" value="">' : '<span class="text-muted">N/A</span>'}</td>
            <td class="stock-cell">${drugSource === 'ward_stock' ? '<span class="text-muted"><i class="mdi mdi-loading mdi-spin"></i></span>' : '<span class="text-muted">N/A</span>'}</td>
            <td>₦${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Fetch and populate batch dropdown for this product if ward stock
    if (drugSource === 'ward_stock') {
        fetchAndPopulateBatchDropdown(id, storeId, `#injection-selected-body tr[data-product-id="${id}"]`);
    }

    $('#injection-drug-results').hide();
    $('#injection-drug-search').val('');
}

// Remove row from injection table
function removeInjectionRow(btn) {
    $(btn).closest('tr').remove();
    updateInjectionTotals();
}

// §7.2: Insert a virtual row for patient's own drug (no hospital product_id)
function addPatientOwnInjectionRow() {
    const drugName  = $('#inj-external-name').val()?.trim();
    const qty       = $('#inj-external-qty').val();
    const batch     = $('#inj-external-batch').val()?.trim() || '';
    const expiry    = $('#inj-external-expiry').val() || '';
    const note      = $('#inj-external-note').val()?.trim() || '';

    if (!drugName) {
        showNotification('warning', 'Enter the drug name');
        $('#inj-external-name').focus();
        return;
    }
    if (!qty || parseFloat(qty) <= 0) {
        showNotification('warning', 'Enter a valid quantity');
        $('#inj-external-qty').focus();
        return;
    }

    // Prevent duplicate virtual rows with same drug name
    const duplicate = $('#injection-selected-body tr[data-source="patient_own"]').filter(function() {
        return $(this).find('td:eq(1) strong').text().toLowerCase() === drugName.toLowerCase();
    });
    if (duplicate.length> 0) {
        showNotification('warning', 'This drug is already in the list');
        return;
    }

    const uid = 'po_' + Date.now(); // virtual row identifier

    const row = `
        <tr data-source="patient_own" data-virtual-id="${uid}" data-price="0"
            data-ext-name="${drugName}" data-ext-qty="${qty}"
            data-ext-batch="${batch}" data-ext-expiry="${expiry}" data-ext-note="${note}">
            <td><input type="checkbox" class="form-check-input injection-row-check" checked></td>
            <td>
                <strong>${drugName}</strong><br>
                <span class="badge bg-purple text-white" style="background:#9c27b0;">Patient's Own</span>
                ${batch ? `<small class="text-muted ms-1">Batch: ${batch}</small>` : ''}
                ${expiry ? `<small class="text-muted ms-1">Exp: ${expiry}</small>` : ''}
            </td>
            <td>
                <input type="number" class="form-control form-control-sm injection-qty"
                       name="injection_qty[]" value="${qty}" min="0.01" step="0.01" style="width: 60px;">
            </td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">N/A</span></td>
            <td><span class="text-muted">—</span></td>
            <td><span class="badge bg-secondary">No Billing</span></td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="injection_dose[]" placeholder="e.g., 5mg" required>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeInjectionRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#injection-selected-body').append(row);
    updateInjectionTotals();

    // Clear the input fields so nurse can add another if needed
    $('#inj-external-name').val('').focus();
    $('#inj-external-qty').val('');
    $('#inj-external-batch').val('');
    $('#inj-external-expiry').val('');
    $('#inj-external-note').val('');

    showNotification('success', `"${drugName}" added to list`);
}

// Update injection totals
function updateInjectionTotals() {
    let total = 0;
    $('#injection-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.injection-qty').val()) || 1;
        total += price * qty;
    });
    $('#injection-total-price').html(`<strong>₦${total.toFixed(2)}</strong>`);
}

// ===========================================
// BATCH SELECTION HELPER FUNCTIONS
// ===========================================

/**
 * Fetch batches from server and populate dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} rowSelector - Selector for the table row
 */
function fetchAndPopulateBatchDropdown(productId, storeId, rowSelector) {
    const $row = $(rowSelector);
    const $batchCell = $row.find('.batch-cell');
    const $batchLoading = $batchCell.find('.batch-loading');
    const $batchSelect = $batchCell.find('.batch-select-dropdown');
    const $stockCell = $row.find('.stock-cell');

    $.ajax({
        url: wbRoute('nursing-workbench.product-batches', '/nursing-workbench/product-batches'),
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $batchLoading.addClass('d-none');

            if (response.success && response.batches.length> 0) {
                // Build dropdown options
                let options = '<option value="">Auto (FIFO)</option>';
                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' ★ FIFO' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $batchSelect.html(options).removeClass('d-none');

                // Update stock cell
                const totalQty = response.total_available;
                const reqQty = parseInt($row.find('.injection-qty').val()) || 1;
                const stockClass = totalQty>= reqQty ? 'text-success' : 'text-danger';
                const stockIcon = totalQty>= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);

                // Store batches data for later reference
                $row.data('batches', response.batches);
            } else {
                // No batches available
                $batchSelect.html('<option value="">No batches available</option>').removeClass('d-none');
                $stockCell.html('<span class="text-danger"><i class="mdi mdi-alert-circle"></i> 0</span>');
            }
        },
        error: function() {
            $batchLoading.addClass('d-none');
            $batchSelect.html('<option value="">Error loading batches</option>').removeClass('d-none');
            $stockCell.html('<span class="text-warning"><i class="mdi mdi-help-circle"></i> ?</span>');
        }
    });
}

/**
 * Fetch batches for a single product and populate a standalone dropdown
 * @param {int} productId - Product ID
 * @param {int} storeId - Store ID
 * @param {string} selectId - ID of the select element to populate
 * @param {function} callback - Optional callback with batch data
 */
function fetchProductBatchesForSelect(productId, storeId, selectId, callback) {
    const $select = $(selectId);
    $select.html('<option value="">Loading batches...</option>').prop('disabled', true);

    $.ajax({
        url: wbRoute('nursing-workbench.product-batches', '/nursing-workbench/product-batches'),
        method: 'GET',
        data: { product_id: productId, store_id: storeId },
        success: function(response) {
            $select.prop('disabled', false);

            if (response.success && response.batches.length> 0) {
                let options = '<option value="">Auto (FIFO) - Recommended</option>';

                response.batches.forEach((batch, index) => {
                    const isFirst = index === 0;
                    const expiryClass = batch.is_expired ? 'batch-option-expired' :
                                       batch.is_expiring_soon ? 'batch-option-expiring' : '';
                    const expiryText = batch.expiry_formatted ? ` | Exp: ${batch.expiry_formatted}` : '';
                    const fifoLabel = isFirst ? ' ★' : '';

                    options += `<option value="${batch.id}" class="${expiryClass}"
                                data-expiry="${batch.expiry_date || ''}"
                                data-qty="${batch.current_qty}"
                                data-batch-number="${batch.batch_number}">
                        ${batch.batch_number} (${batch.current_qty} avail)${expiryText}${fifoLabel}
                    </option>`;
                });

                $select.html(options);

                if (callback) callback(response);
            } else {
                $select.html('<option value="">No batches in this store</option>');
                if (callback) callback({ success: false, batches: [], total_available: 0 });
            }
        },
        error: function() {
            $select.prop('disabled', false);
            $select.html('<option value="">Error loading batches</option>');
            if (callback) callback({ success: false, error: true });
        }
    });
}

/**
 * Build batch info display for FIFO mode
 */
function buildBatchFifoDisplay(batch) {
    if (!batch) return '<span class="text-muted">No batch available</span>';

    const expiryText = batch.expiry_formatted ? `<span class="batch-expiry">Exp: ${batch.expiry_formatted}</span>` : '';

    return `
        <div class="batch-info-display">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="batch-fifo-badge"><i class="mdi mdi-sort-clock-ascending"></i> FIFO</span>
                    <span class="batch-number ml-2">${batch.batch_number}</span>
                </div>
                <span class="batch-qty">${batch.current_qty}</span>
            </div>
            ${expiryText ? `<div class="mt-1 small">${expiryText}</div>` : ''}
            <button type="button" class="batch-manual-select-btn mt-1" onclick="$(this).closest('.batch-cell').find('.batch-select-dropdown').removeClass('d-none'); $(this).closest('.batch-info-display').addClass('d-none');">
                <i class="mdi mdi-pencil"></i> Change Batch
            </button>
        </div>
    `;
}

/**
 * Handle batch select change - update expiry field if linked
 */
$(document).on('change', '.batch-select-dropdown, #consumable-batch-select, #modal-vaccine-batch-select', function() {
    const $selected = $(this).find(':selected');
    const expiryDate = $selected.data('expiry');
    const batchNumber = $selected.data('batch-number');

    // Update linked expiry field if exists
    const $form = $(this).closest('form, .modal-body, .card-body');
    const $expiryField = $form.find('input[type="date"][id*="expiry"]');
    if ($expiryField.length && expiryDate) {
        $expiryField.val(expiryDate);
    }

    // Store selected batch ID in hidden field if exists
    const $hiddenField = $(this).siblings('input[type="hidden"]');
    if ($hiddenField.length) {
        $hiddenField.val($(this).val());
    }
});

// Recalculate on qty change and clear validation
$(document).on('change', '.injection-qty', function() {
    updateInjectionTotals();

    // Re-fetch batches when quantity changes to show stock status
    const $row = $(this).closest('tr');
    const productId = $row.data('product-id');
    const storeId = $('#injection-store').val();
    const reqQty = parseInt($(this).val()) || 1;

    // Update stock display based on stored batches
    const batches = $row.data('batches');
    if (batches) {
        const totalQty = batches.reduce((sum, b) => sum + b.current_qty, 0);
        const $stockCell = $row.find('.stock-cell');
        const stockClass = totalQty>= reqQty ? 'text-success' : 'text-danger';
        const stockIcon = totalQty>= reqQty ? 'mdi-check-circle' : 'mdi-alert-circle';
        $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${totalQty}</span>`);
    }

    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// Clear validation on dose input change
$(document).on('input', 'input[name="injection_dose[]"]', function() {
    $(this).removeClass('is-invalid');
    $(this).siblings('.validation-error').remove();
});

// ===========================================
// STORE STOCK DISPLAY HELPERS
// ===========================================

// Fetch product stock by store
function fetchProductStockByStore(productId, callback) {
    $.ajax({
        url: wbUrl(`/pharmacy-workbench/product/${productId}/stock`),
        method: 'GET',
        success: function(response) {
            callback(response);
        },
        error: function() {
            callback({ global_stock: 0, stores: [] });
        }
    });
}

// Update injection stock display when store changes
$('#injection-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#injection-store-placeholder').hide();
        $('#injection-store-info').show();
        updateInjectionStockDisplay();
    } else {
        $('#injection-store-info').hide();
        $('#injection-store-placeholder').show();
    }
});

// Update injection table stock display
function updateInjectionStockDisplay() {
    const storeId = $('#injection-store').val();
    if (!storeId) return;

    let stockSummaryHtml = '';
    const rows = $('#injection-selected-body tr');

    if (rows.length === 0) {
        stockSummaryHtml = '<p class="text-muted mb-0">Add drugs to see stock</p>';
    } else {
        rows.each(function() {
            const productId = $(this).data('product-id');
            const productName = $(this).find('td:eq(1) strong').text();
            const qty = parseInt($(this).find('.injection-qty').val()) || 1;
            const $stockCell = $(this).find('.stock-cell');

            fetchProductStockByStore(productId, function(stockData) {
                const storeStock = stockData.stores.find(s => s.store_id == storeId);
                const availableQty = storeStock ? storeStock.quantity : 0;
                const stockClass = availableQty>= qty ? 'text-success' : 'text-danger';
                const stockIcon = availableQty>= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

                $stockCell.html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> ${availableQty}</span>`);
            });
        });
    }

    $('#injection-store-stock-summary').html(stockSummaryHtml || '<p class="text-muted mb-0">Stock shown in table</p>');
}

// Consumable store change handler
$('#consumable-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#consumable-store-placeholder').hide();
        $('#consumable-store-info').show();
        updateConsumableStockDisplay();
    } else {
        $('#consumable-store-info').hide();
        $('#consumable-store-placeholder').show();
    }
});

// Update consumable stock display
function updateConsumableStockDisplay() {
    const storeId = $('#consumable-store').val();
    const productId = $('#consumable-id').val();

    if (!storeId || !productId) {
        $('#consumable-store-stock-summary').html('<p class="text-muted mb-0">Select a product to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const qty = parseInt($('#consumable-quantity').val()) || 1;
        const stockClass = availableQty>= qty ? 'text-success' : 'text-danger';
        const stockIcon = availableQty>= qty ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty < qty) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Insufficient stock!</div>`;
        }

        $('#consumable-store-stock-summary').html(html);
        $('#consumable-stock-info').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock: ${availableQty}</span>`);
    });
}

// Immunization modal store change handler
$('#modal-vaccine-store').on('change', function() {
    const storeId = $(this).val();
    if (storeId) {
        $('#modal-vaccine-store-placeholder').hide();
        $('#modal-vaccine-store-info').show();
        updateImmunizationStockDisplay();
    } else {
        $('#modal-vaccine-store-info').hide();
        $('#modal-vaccine-store-placeholder').show();
    }
});

// Update immunization stock display
function updateImmunizationStockDisplay() {
    const storeId = $('#modal-vaccine-store').val();
    const productId = $('#modal-product-id').val();

    if (!storeId || !productId) {
        $('#modal-vaccine-store-stock').html('<p class="text-muted mb-0">Select a vaccine to see stock</p>');
        return;
    }

    fetchProductStockByStore(productId, function(stockData) {
        const storeStock = stockData.stores.find(s => s.store_id == storeId);
        const availableQty = storeStock ? storeStock.quantity : 0;
        const stockClass = availableQty> 0 ? 'text-success' : 'text-danger';
        const stockIcon = availableQty> 0 ? 'mdi-check-circle' : 'mdi-alert-circle';

        let html = `<div class="${stockClass}"><i class="mdi ${stockIcon}"></i> Available: <strong>${availableQty}</strong></div>`;
        if (availableQty <= 0) {
            html += `<div class="text-danger small"><i class="mdi mdi-alert"></i> Out of stock!</div>`;
        }

        $('#modal-vaccine-store-stock').html(html);
        $('#modal-selected-product-stock').html(`<span class="${stockClass}"><i class="mdi ${stockIcon}"></i> Stock in selected store: ${availableQty}</span>`);
    });
}

// Update consumable quantity change
$('#consumable-quantity').on('change', function() {
    updateConsumableStockDisplay();
});

// ===========================================
// STOCK VALIDATION HELPERS
// ===========================================

// Check stock availability before submission - returns Promise
function validateStockAvailability(storeId, products) {
    return new Promise((resolve, reject) => {
        const stockChecks = products.map(p => {
            return new Promise((res) => {
                fetchProductStockByStore(p.product_id, function(stockData) {
                    const storeStock = stockData.stores.find(s => s.store_id == storeId);
                    const availableQty = storeStock ? storeStock.quantity : 0;
                    res({
                        product_id: p.product_id,
                        product_name: p.product_name || 'Product',
                        requested_qty: parseInt(p.qty) || 1,
                        available_qty: availableQty,
                        sufficient: availableQty>= (parseInt(p.qty) || 1)
                    });
                });
            });
        });

        Promise.all(stockChecks).then(results => {
            const insufficientItems = results.filter(r => !r.sufficient);
            if (insufficientItems.length> 0) {
                reject({
                    type: 'insufficient_stock',
                    items: insufficientItems,
                    message: insufficientItems.map(i =>
                        `${i.product_name}: Need ${i.requested_qty}, only ${i.available_qty} available`
                    ).join('\n')
                });
            } else {
                resolve(results);
            }
        });
    });
}

// Show stock validation error with visual feedback
function showStockValidationError(items, tableSelector) {
    let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    errorHtml += '<strong><i class="mdi mdi-alert-circle"></i> Insufficient Stock!</strong><br>';
    errorHtml += '<ul class="mb-0 pl-3">';

    items.forEach(item => {
        errorHtml += `<li>${item.product_name || 'Item'}: Requested <strong>${item.requested_qty}</strong>, Available <strong>${item.available_qty}</strong></li>`;

        // Highlight the row in the table
        if (tableSelector) {
            const $row = $(`${tableSelector} tr[data-product-id="${item.product_id}"]`);
            $row.addClass('table-danger').find('.stock-cell').addClass('text-danger fw-bold');
            $row.find('.injection-qty, .vaccine-qty').addClass('is-invalid');
        }
    });

    errorHtml += '</ul>';
    errorHtml += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    errorHtml += '</div>';

    return errorHtml;
}

// Injection Form Submit — §7.3 rewrite: 3-path source model
$('#injection-form').on('submit', function(e) {
    e.preventDefault();

    const drugSource = $('#injection-drug-source').val();
    const storeId = $('#injection-store').val();
    const billPatient = drugSource === 'ward_stock' ? ($('#injection-bill-patient').is(':checked') ? 1 : 0) : 0;

    // Ward stock requires store
    if (drugSource === 'ward_stock' && !storeId) {
        showNotification('error', 'Please select a store to dispense from');
        $('#injection-store').focus();
        return;
    }

    // Collect selected products/virtual rows
    const products = [];
    $('#injection-selected-body tr').each(function() {
        if (!$(this).find('.injection-row-check').is(':checked')) return;

        const rowSource = $(this).data('source') || drugSource;
        const batchId = $(this).find('.batch-select-dropdown').val() || null;
        const productRequestId = $(this).data('product-request-id') || null;

        if (rowSource === 'patient_own') {
            // §7.2: Virtual row — read external data from data attributes
            products.push({
                product_id: null, // no hospital product for patient's own
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: null,
                product_request_id: null,
                external_drug_name: $(this).data('ext-name') || $(this).find('td:eq(1) strong').text(),
                external_qty: $(this).data('ext-qty') || $(this).find('.injection-qty').val(),
                external_batch_number: $(this).data('ext-batch') || null,
                external_expiry_date: $(this).data('ext-expiry') || null,
                external_source_note: $(this).data('ext-note') || null,
            });
        } else {
            // pharmacy_dispensed or ward_stock — real hospital product
            products.push({
                product_id: $(this).data('product-id'),
                product_name: $(this).find('td:eq(1) strong').text(),
                qty: $(this).find('.injection-qty').val(),
                dose: $(this).find('input[name="injection_dose[]"]').val(),
                batch_id: batchId,
                product_request_id: productRequestId,
            });
        }
    });

    if (products.length === 0) {
        if (drugSource === 'patient_own') {
            showNotification('error', "Click 'Add Drug to List' first, then submit");
        } else {
            showNotification('error', 'Please select at least one drug');
        }
        return;
    }

    // Pharmacy dispensed must have product_request_id
    if (drugSource === 'pharmacy_dispensed') {
        const missingRx = products.some(p => !p.product_request_id);
        if (missingRx) {
            showNotification('error', 'Select from dispensed prescriptions before administering');
            return;
        }
    }

    // Dose is required for all rows
    const missingDose = products.some(p => !p.dose || !p.dose.trim());
    if (missingDose) {
        showNotification('warning', 'Enter a dose for every drug in the list');
        return;
    }

    // Clear previous validation errors
    $('#injection-selected-body .is-invalid').removeClass('is-invalid');
    $('#injection-selected-body .validation-error').remove();
    $('#injection-selected-body tr').removeClass('table-danger');
    $('#injection-stock-error').remove();

    // Validate stock before submission (ward_stock only)
    const $submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Checking Stock...');

    const stockPromise = drugSource === 'ward_stock'
        ? validateStockAvailability(storeId, products.filter(p => p.product_id))
        : Promise.resolve();

    stockPromise
        .then(() => {
            $submitBtn.html('<i class="mdi mdi-loading mdi-spin"></i> Administering...');

            const data = {
                patient_id: currentPatient,
                drug_source: drugSource,
                bill_patient: billPatient,
                products: products.map(p => ({
                    product_id: p.product_id || null,
                    qty: p.qty,
                    dose: p.dose,
                    batch_id: p.batch_id || null,
                    product_request_id: p.product_request_id || null,
                    external_drug_name: p.external_drug_name || null,
                    external_qty: p.external_qty || null,
                    external_batch_number: p.external_batch_number || null,
                    external_expiry_date: p.external_expiry_date || null,
                    external_source_note: p.external_source_note || null,
                })),
                route: $('#injection-route').val(),
                site: $('#injection-site').val(),
                administered_at: $('#injection-time').val(),
                notes: $('#injection-notes').val(),
                store_id: drugSource === 'ward_stock' ? storeId : null
            };

            $.ajax({
                url: wbRoute('nursing-workbench.injection.administer', '/nursing-workbench/injection/administer'),
                method: 'POST',
                data: data,
                headers: {'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))},
                success: function(response) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    showNotification('success', response.message || 'Injection administered successfully');
                    $('#injection-form')[0].reset();
                    $('#injection-selected-body').empty();
                    setInjectionDrugSource('pharmacy_dispensed');
                    updateInjectionTotals();
                    loadInjectionHistory(currentPatient);
                },
                error: function(xhr) {
                    $submitBtn.prop('disabled', false).html(originalBtnHtml);
                    const response = xhr.responseJSON;
                    handleInjectionSubmitError(response, products);
                }
            });
        })
        .catch(stockError => {
            $submitBtn.prop('disabled', false).html(originalBtnHtml);

            const errorHtml = showStockValidationError(stockError.items, '#injection-selected-body');
            $('#injection-selected-drugs').before(`<div id="injection-stock-error">${errorHtml}</div>`);

            showNotification('error', 'Insufficient stock for one or more items');
        });
});

// Handle injection submit error (separated for cleaner code)
function handleInjectionSubmitError(response, products) {
    const checkedRows = $('#injection-selected-body tr').filter(function() {
        return $(this).find('.injection-row-check').is(':checked');
    });

    if (response?.errors) {
        let errorMessages = [];

        Object.keys(response.errors).forEach(function(field) {
            // Parse field like "products.0.dose" or "products.1.qty"
            const match = field.match(/^products\.(\d+)\.(\w+)$/);
            if (match) {
                const index = parseInt(match[1]);
                const fieldName = match[2];
                const row = checkedRows.eq(index);

                if (row.length) {
                    const productName = row.find('td:eq(1) strong').text();
                    let inputField;

                    if (fieldName === 'dose') {
                        inputField = row.find('input[name="injection_dose[]"]');
                    } else if (fieldName === 'qty') {
                        inputField = row.find('.injection-qty');
                    }

                    if (inputField && inputField.length) {
                        inputField.addClass('is-invalid');
                        const errorMsg = response.errors[field][0].replace(/products\.\d+\./, '');
                        inputField.after(`<div class="validation-error text-danger small">${errorMsg}</div>`);
                    }

                    errorMessages.push(`${productName}: ${response.errors[field][0].replace(/products\.\d+\./, '')}`);
                }
            } else {
                errorMessages.push(response.errors[field][0]);
            }
        });

        if (errorMessages.length> 0) {
            showNotification('error', 'Validation failed: ' + errorMessages.join(', '));
        } else {
            showNotification('error', response.message || 'Validation failed');
        }
    } else {
        showNotification('error', response?.message || 'Failed to administer injection');
    }
}

// Load Injection History with DataTable
function loadInjectionHistory(patientId) {
    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#injection-history-table')) {
        $('#injection-history-table').DataTable().destroy();
    }

    $('#injection-history-table').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: wbUrl(`/nursing-workbench/patient/${patientId}/injections`),
            dataSrc: ''
        },
        columns: [
            { data: 'administered_at' },
            {
                data: 'product_name',
                render: function(data, type, row) {
                    let name = data || 'N/A';
                    if (row.drug_source === 'patient_own') {
                        let tip = 'Patient\'s Own Drug';
                        if (row.external_qty) tip += ' | Qty: ' + row.external_qty;
                        if (row.external_batch_number) tip += ' | Batch: ' + row.external_batch_number;
                        if (row.external_expiry_date) tip += ' | Exp: ' + row.external_expiry_date;
                        if (row.external_source_note) tip += ' | Note: ' + row.external_source_note;
                        name = '<span title="' + tip + '">' + name + '</span> <span class="badge badge-warning badge-sm">Patient\'s Own</span>';
                    } else if (row.drug_source === 'ward_stock') {
                        name += ' <span class="badge badge-info badge-sm">Ward Stock</span>';
                    }
                    return name;
                }
            },
            { data: 'dose' },
            { data: 'route' },
            { data: 'site' },
            { data: 'administered_by' }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            emptyTable: "No injection history found"
        }
    });
}

// ========================================
// IMMUNIZATION MODULE - Vaccine Search & Administration
// ========================================

// Vaccine/Drug Search (uses same endpoint as prescription form)
let vaccineSearchTimeout;
$('#vaccine-drug-search').on('input', function() {
    const query = $(this).val();
    clearTimeout(vaccineSearchTimeout);

    if (query.length < 2) {
        $('#vaccine-drug-results').hide();
        return;
    }

    vaccineSearchTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('live-search-products'),
            method: 'GET',
            dataType: 'json',
            data: { term: query, patient_id: currentPatient },
            success: function(data) {
                $('#vaccine-drug-results').html('');

                if (data.length === 0) {
                    $('#vaccine-drug-results').html('<li class="list-group-item text-muted">No products found</li>').show();
                    return;
                }

                data.forEach(function(item) {
                    const category = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                    const name = item.product_name || 'Unknown';
                    const code = item.product_code || '';
                    const qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                    const price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                    const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                    const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                    const mode = item.coverage_mode || 'cash';

                    const coverageBadge = mode && mode !== 'cash'
                        ? `<span class='badge bg-info ms-1'>${mode.toUpperCase()}</span> <span class='text-danger ms-1'>Pay: ₦${payable}</span> <span class='text-success ms-1'>Claim: ₦${claims}</span>`
                        : '';

                    const qtyClass = qty> 0 ? 'text-success' : 'text-danger';

                    const mk = `<li class='list-group-item list-group-item-action' style="cursor: pointer;"
                               data-id="${item.id}"
                               data-name="${name}"
                               data-code="${code}"
                               data-qty="${qty}"
                               data-price="${price}"
                               data-payable="${payable}"
                               data-claims="${claims}"
                               data-mode="${mode}"
                               data-category="${category}"
                               onclick="addVaccineDrug(this)">
                               <div class="d-flex justify-content-between align-items-start">
                                   <div>
                                       <strong>${name}</strong> <small class="text-muted">[${code}]</small>
                                       <div class="small text-muted">${category}</div>
                                   </div>
                                   <div class="text-end">
                                       <div class="${qtyClass}"><strong>${qty}</strong> avail.</div>
                                       <div>₦${price}</div>
                                   </div>
                               </div>
                               ${coverageBadge ? `<div class="small mt-1">${coverageBadge}</div>` : ''}
                           </li>`;
                    $('#vaccine-drug-results').append(mk);
                });
                $('#vaccine-drug-results').show();
            },
            error: function(xhr) {
                console.error('Product search failed', xhr);
                $('#vaccine-drug-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
            }
        });
    }, 300);
});

// Add selected vaccine to table
function addVaccineDrug(element) {
    const $el = $(element);
    const id = $el.data('id');
    const name = $el.data('name');
    const code = $el.data('code');
    const qty = $el.data('qty');
    const price = parseFloat($el.data('price')) || 0;
    const payable = parseFloat($el.data('payable')) || price;
    const claims = parseFloat($el.data('claims')) || 0;
    const mode = $el.data('mode') || 'cash';

    // Check if already added
    if ($(`#vaccine-selected-body tr[data-product-id="${id}"]`).length> 0) {
        showNotification('warning', 'This vaccine is already in the list');
        $('#vaccine-drug-results').hide();
        $('#vaccine-drug-search').val('');
        return;
    }

    const coverageInfo = mode && mode !== 'cash'
        ? `<span class="badge bg-info">${mode.toUpperCase()}</span><br><small class="text-danger">Pay: ₦${payable}</small><br><small class="text-success">Claim: ₦${claims}</small>`
        : '<span class="badge bg-secondary">Cash</span>';

    const row = `
        <tr data-product-id="${id}" data-price="${payable}">
            <td><input type="checkbox" class="form-check-input vaccine-row-check" checked></td>
            <td>
                <strong>${name}</strong><br>
                <small class="text-muted">[${code}]</small>
                <input type="hidden" name="vaccine_products[]" value="${id}">
            </td>
            <td>
                <input type="number" class="form-control form-control-sm vaccine-qty"
                       name="vaccine_qty[]" value="1" min="1" max="${qty}" style="width: 70px;">
                <small class="text-muted">${qty} avail.</small>
            </td>
            <td>₦${payable.toFixed(2)}</td>
            <td>${coverageInfo}</td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="vaccine_dose[]" placeholder="Dose amount">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeVaccineRow(this)">
                    <i class="mdi mdi-close"></i>
                </button>
            </td>
        </tr>
    `;

    $('#vaccine-selected-body').append(row);
    updateVaccineTotals();
    $('#vaccine-drug-results').hide();
    $('#vaccine-drug-search').val('');
}

// Remove row from vaccine table
function removeVaccineRow(btn) {
    $(btn).closest('tr').remove();
    updateVaccineTotals();
}

// Update vaccine totals
function updateVaccineTotals() {
    let total = 0;
    $('#vaccine-selected-body tr').each(function() {
        const price = parseFloat($(this).data('price')) || 0;
        const qty = parseInt($(this).find('.vaccine-qty').val()) || 1;
        total += price * qty;
    });
    $('#vaccine-total-price').html(`<strong>₦${total.toFixed(2)}</strong>`);
}

// Recalculate on qty change
$(document).on('change', '.vaccine-qty', function() {
    updateVaccineTotals();
});

// Immunization Form Submit
$('#immunization-form').on('submit', function(e) {
    e.preventDefault();

    // Collect selected products
    const products = [];
    $('#vaccine-selected-body tr').each(function() {
        if ($(this).find('.vaccine-row-check').is(':checked')) {
            products.push({
                product_id: $(this).data('product-id'),
                qty: $(this).find('.vaccine-qty').val(),
                dose: $(this).find('input[name="vaccine_dose[]"]').val()
            });
        }
    });

    if (products.length === 0) {
        showNotification('error', 'Please select at least one vaccine');
        return;
    }

    const data = {
        patient_id: currentPatient,
        products: products,
        dose_number: $('#vaccine-dose-number').val(),
        routine: $('#vaccine-routine').val(),
        site: $('#vaccine-site').val(),
        administered_at: $('#vaccine-time').val(),
        batch_number: $('#vaccine-batch').val(),
        expiry_date: $('#vaccine-expiry').val(),
        notes: $('#vaccine-notes').val()
    };

    $.ajax({
        url: wbRoute('nursing-workbench.immunization.administer', '/nursing-workbench/immunization/administer'),
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))},
        success: function(response) {
            showNotification('success', response.message || 'Vaccine administered successfully');
            $('#immunization-form')[0].reset();
            $('#vaccine-selected-body').empty();
            updateVaccineTotals();
            loadImmunizationHistory(currentPatient);
            loadImmunizationSchedule(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to administer vaccine');
        }
    });
});

// Load Immunization Schedule (New Schedule System)
function loadImmunizationSchedule(patientId) {
    ImmunizationModule.configure({
        baseUrl: '/nursing-workbench',
        csrfToken: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        currentPatientId: patientId,
        onScheduleReload: function() { loadImmunizationSchedule(patientId); },
        onHistoryReload: function() { loadImmunizationHistory(patientId); },
        storesHtml: $('#ctx-store-select').html() || '', // Or whatever provides store HTML if needed, but it's handled by modal
        productSearchUrl: wbRoute('nursing-workbench.search-products', '/nursing-workbench/search-products'),
        productBatchesUrl: '/nursing-workbench/product-batches'
    });
    
    ImmunizationModule.initModalEvents();

    ImmunizationModule.loadSchedule(
        patientId,
        '#immunization-schedule-container',
        `/nursing-workbench/patient/${patientId}/schedule`,
        {
            activeSchedulesId: '#active-schedules-badges'
        }
    );
}

// Load immunization timeline view
function loadImmunizationTimeline(patientId) {
    ImmunizationModule.loadTimeline(patientId, '#history-timeline-view', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Load immunization calendar view
function loadImmunizationCalendar(patientId) {
    ImmunizationModule.loadCalendar(patientId, '#history-calendar-view', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Load Immunization History Table View with DataTable
function loadImmunizationHistoryTable(patientId) {
    ImmunizationModule.loadHistoryTable(patientId, '#history-table-view', '#immunization-history-table', `/nursing-workbench/patient/${patientId}/immunization-history`);
}

// Function to load active history view
function loadImmunizationHistory(patientId) {
    if (!patientId) return;
    
    // Determine which view is active
    let activeView = 'timeline';
    if ($('#view-calendar-btn').hasClass('active')) activeView = 'calendar';
    if ($('#view-table-btn').hasClass('active')) activeView = 'table';
    
    if (activeView === 'timeline') {
        loadImmunizationTimeline(patientId);
    } else if (activeView === 'calendar') {
        loadImmunizationCalendar(patientId);
    } else {
        loadImmunizationHistoryTable(patientId);
    }
}

// History view toggles
$(document).on('click', '#view-timeline-btn, #view-calendar-btn, #view-table-btn', function() {
    const view = $(this).data('view');
    
    // Update active state
    $('#view-timeline-btn, #view-calendar-btn, #view-table-btn').removeClass('active');
    $(this).addClass('active');
    
    // Show correct container
    $('#history-timeline-view, #history-calendar-view, #history-table-view').addClass('d-none');
    $(`#history-${view}-view`).removeClass('d-none');
    
    // Load data
    if (currentPatient) {
        if (view === 'timeline') loadImmunizationTimeline(currentPatient);
        else if (view === 'calendar') loadImmunizationCalendar(currentPatient);
        else loadImmunizationHistoryTable(currentPatient);
    }
});

// Load Note Types
// Functions for Notes
// Note Types loading removed as requested
// function loadNoteTypes() { ... }

// Nursing Note Form Submit
// Initialize CKEditor for nursing note
// Initialize CKEditor for nursing note using the shared WorkbenchNotesKit
let nursingNoteEditor;
var nurseNoteAutosaveTimer = null;

function initNursingNoteCKEditor() {
    WorkbenchNotesKit.initEditor({
        prefix: 'nursing',
        editorSelector: '#nursing-note-editor',
        formSelector: '#nursing-note-form',
        statusSelector: '#note-autosave-status',
        noteTypeId: 5,
        csrfToken: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
        getSaveUrl: function(patientId) {
            return wbRoute('nursing-workbench.notes.store', '/nursing-workbench/notes/store');
        },
        getPatientId: function() {
            return currentPatient;
        },
        onSaveSuccess: function() {
            // Switch to history tab to see the new note
            $('#notes-history-tab-link').tab('show');
            loadNotesHistory(currentPatient);
        }
    });
    
    // Keep nursingNoteEditor synced for reference
    nursingNoteEditor = WorkbenchNotesKit.editors['nursing'];
}

// Ensure editor is initialized when tab shown
$('a[data-toggle="tab"][href="#notes-tab"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});
$('a[data-toggle="tab"][href="#notes-add"]').on('shown.bs.tab', function (e) {
    initNursingNoteCKEditor();
});

// Also try to init on page load after a brief delay
setTimeout(initNursingNoteCKEditor, 1000);

// Load Notes History with Cards (DataTable)
function loadNotesHistory(patientId) {
    if (!patientId) return;

    if ($.fn.DataTable.isDataTable('#nursing-notes-table')) {
        $('#nursing-notes-table').DataTable().ajax.url(`/nursing-workbench/patient/${patientId}/nursing-notes`).load();
    } else {
        $('#nursing-notes-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `/nursing-workbench/patient/${patientId}/nursing-notes`,
            columns: [
                { data: 'info', name: 'info', orderable: false, searchable: false }
            ],
            ordering: false,
            lengthChange: false,
            pageLength: 10,
            searching: false,
            dom: "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-5'i><'col-sm-7'p>>",
            language: {
                emptyTable: `<div class="text-center py-5">
                                <i class="mdi mdi-note-outline mdi-48px text-muted"></i>
                                <p class="text-muted mt-2">No nursing notes found</p>
                            </div>`,
                processing: `<div class="text-center">
                                <i class="mdi mdi-loading mdi-spin mdi-24px text-primary"></i>
                             </div>`
            }
        });
    }
}

// Initialize nursing-specific features on tab switch
function switchWorkspaceTab(tab) {
    $('.workspace-tab').removeClass('active');
    $('.workspace-tab-content').removeClass('active');

    $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');
    $(`#${tab}-tab`).addClass('active');

    // Load tab-specific content
    if (!currentPatient) return;

    switch(tab) {
        case 'overview':
            loadPatientOverview(currentPatient);
            break;
        case 'clinical-story':
            if (currentPatient) {
                const $storyWrapper = $('.clinical-story-wrapper');
                $storyWrapper.data('patient-id', currentPatient);
                $storyWrapper.attr('data-patient-id', currentPatient);
                
                const inst = $storyWrapper.data('clinicalStory');
                if (inst) {
                    inst.patientId = currentPatient;
                    inst.resetTimeline();
                    inst.loadTimeline();
                } else {
                    $('.clinical-story-wrapper').each(function () {
                        const $w = $(this);
                        $w.data('clinicalStory', new ClinicalStory(this));
                    });
                }
            }
            break;
        case 'medication':
            // Initialize medication chart with current patient
            if (typeof initMedicationChart === 'function') {
                initMedicationChart(currentPatient);
            }
            break;
        case 'intake-output':
            // Initialize I/O chart with current patient
            if (typeof initIntakeOutputChart === 'function') {
                initIntakeOutputChart(currentPatient);
            }
            break;
        case 'injection':
            loadInjectionHistory(currentPatient);
            // Set current time
            $('#injection-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'immunization':
            loadImmunizationSchedule(currentPatient);
            loadImmunizationHistory(currentPatient);
            $('#vaccine-time').val(new Date().toISOString().slice(0, 16));
            break;
        case 'billing':
            if (window.BillingKit) {
                BillingKit.setPatient(currentPatient);
            }
            break;
        case 'notes':
            // Removed loadNoteTypes call as it is no longer needed
            loadNotesHistory(currentPatient);
            break;
    }
}

// Notification helper
function showNotification(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
    const html = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>`;

    // Create a notification container if it doesn't exist
    if ($('#notification-container').length === 0) {
        $('body').append('<div id="notification-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999; width: 350px;"></div>');
    }

    $('#notification-container').append(html);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('#notification-container .alert').first().alert('close');
    }, 5000);
}

// Workbench-specific wrapper variables for medication and I/O charts
// These will be set dynamically when a patient is selected
var PATIENT_ID = null;
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

// Duplicate route template block removed — single source of truth hoisted at top level

// Edit window from settings
var NOTE_EDIT_WINDOW = '';

// Global variables for medication chart
selectedMedication = null;
let calendarStartDate = new Date();
calendarStartDate.setDate(calendarStartDate.getDate() - 15);
let medications = [];
let medicationStatus = {};
let currentSchedules = [];
let currentAdministrations = [];
let medicationHistory = {};
let patientPrescriptions = [];
let patientPrescriptionsLoaded = false;

// §4.6: Drug source badge helper
function getDrugSourceBadge(drugSource, productRequestId) {
    switch (drugSource) {
        case 'patient_own':
            return '<span class="badge" style="background:#7b1fa2;"><i class="mdi mdi-account-heart"></i> Patient\'s Own</span>';
        case 'ward_stock':
            if (productRequestId) {
                return '<span class="badge bg-primary"><i class="mdi mdi-hospital-building"></i> Ward Stock (Billed)</span>';
            }
            return '<span class="badge bg-info"><i class="mdi mdi-hospital-building"></i> Ward Stock</span>';
        case 'pharmacy_dispensed':
        default:
            return '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispensed</span>';
    }
}

function setDrugSource(source) {
    $('#administer_drug_source').val(source || 'pharmacy_dispensed');
}

// Helper: set datetime-local input to current time
function setCurrentDateTime(inputId) {
    var now = new Date();
    var offset = now.getTimezoneOffset();
    var local = new Date(now.getTime() - offset * 60000);
    document.getElementById(inputId).value = local.toISOString().slice(0, 16);
}

// §6.1: Select2 template for dropdown results (rich format)
function formatRxOption(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle separator
    if ($opt.data('is-separator')) {
        return $('<div class="text-muted fw-bold small py-1 border-top mt-1">' + option.text + '</div>');
    }

    // Handle direct administration entries (ward stock / patient's own)
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var isPatientOwn = directEntry.drug_source === 'patient_own';
        var deLabel = isPatientOwn ? "Patient's Own" : 'Ward Stock';
        var deBadgeClass = isPatientOwn ? 'bg-purple' : 'bg-info';
        var deBadgeHtml = '<span class="badge ' + deBadgeClass + '">' + deLabel + '</span>';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        var deSchedCount = directEntry.times_scheduled || 0;
        var deAdminCount = directEntry.times_administered || 0;

        return $(
            '<div class="d-flex flex-column py-1">' +
                '<div class="d-flex align-items-center gap-2">' +
                    '<span style="font-size:1.1em;">' + deIcon + '</span>' +
                    '<strong>' + deDrugName + '</strong>' +
                    '<small class="text-muted">' + deCodeStr + '</small>' +
                    deBadgeHtml +
                '</div>' +
                '<div class="d-flex gap-3 ms-4">' +
                    '<small class="text-muted">Scheduled: ' + deSchedCount + '</small>' +
                    '<small class="text-info">Administered: ' + deAdminCount + '</small>' +
                    '<small class="text-muted">by ' + directEntry.nurse_name + '</small>' +
                '</div>' +
            '</div>'
        );
    }

    // Handle pharmacy prescriptions
    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var badge = $opt.data('status-badge') || '';
    var adminText = $opt.data('admin-text') || '';
    var doctorText = $opt.data('doctor-text') || '';
    var isDisabled = option.disabled;

    return $(
        '<div class="d-flex flex-column py-1 ' + (isDisabled ? 'opacity-50' : '') + '">' +
            '<div class="d-flex align-items-center gap-2">' +
                '<span style="font-size:1.1em;">' + icon + '</span>' +
                '<strong>' + rx.product_name + '</strong>' +
                '<small class="text-muted">(' + rx.product_code + ')</small>' +
                badge +
            '</div>' +
            '<div class="d-flex gap-3 ms-4">' +
                '<small class="text-muted">Prescribed: ' + rx.qty_prescribed + '</small>' +
                '<small class="text-muted">Administered: ' + (rx.qty_administered || 0) + '</small>' +
                '<small class="text-muted">Remaining: ' + (rx.remaining_doses || 0) + '</small>' +
                '<small class="text-muted">Scheduled: ' + (rx.times_scheduled || 0) + '</small>' +
                (adminText ? '<small class="text-info">' + adminText + '</small>' : '') +
                (doctorText ? '<small class="text-muted">' + doctorText + '</small>' : '') +
                (rx.remaining_doses === 0 && rx.is_dispensed ? '<small class="text-success fw-bold">✓ Fully administered</small>' : '') +
            '</div>' +
            (isDisabled ? '<small class="text-danger ms-4"><i class="mdi mdi-lock"></i> ' + rx.status_label + ' — cannot chart</small>' : '') +
        '</div>'
    );
}

// §6.1: Select2 template for selected item (compact)
function formatRxSelection(option) {
    if (!option.id) return option.text;

    var $opt = $(option.element);

    // Handle direct entry selections
    var directEntry = $opt.data('direct-entry');
    if (directEntry) {
        var deIcon = $opt.data('status-icon') || '';
        var deDrugName = directEntry.product_name || directEntry.external_drug_name || 'Unknown';
        var deCodeStr = directEntry.product_code ? '(' + directEntry.product_code + ')' : '';
        return deIcon + ' ' + deDrugName + ' ' + deCodeStr;
    }

    var rx = $opt.data('rx');
    if (!rx) return option.text;

    var icon = $opt.data('status-icon') || '';
    var remainStr = (rx.remaining_doses !== undefined) ? ' [' + (rx.qty_administered || 0) + '/' + rx.qty_prescribed + ' used]' : '';
    return icon + ' ' + rx.product_name + ' (' + rx.product_code + ')' + remainStr;
}

// =============================================
// OVERVIEW TAB FUNCTIONS
// =============================================
var overviewCurrentStart = null;
var overviewDataCache = null;

function loadMedOverview(startDate) {
    if (!PATIENT_ID) return;

    if (!startDate) {
        // Default to current week start (Monday)
        var d = new Date();
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); // Monday
        startDate = formatDateForApi(d);
    }
    overviewCurrentStart = startDate;

    var endDate = new Date(startDate);
    endDate.setDate(endDate.getDate() + 6);
    var endStr = formatDateForApi(endDate);

    $('#overview-loading').show();
    $('#unified-overview-container').html('');

    var url = medicationChartOverviewRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        data: { start_date: startDate, end_date: endStr },
        success: function(data) {
            $('#overview-loading').hide();
            overviewDataCache = data;

            // Update stats
            var stats = data.stats || {};
            $('#stat-total-meds').text(stats.total_medications || 0);
            $('#stat-given').text(stats.total_given || 0);
            $('#stat-scheduled').text(stats.total_scheduled || 0);
            $('#stat-missed').text(stats.total_missed || 0);

            // Render the 7-day calendar
            renderOverviewCalendar(data, startDate, endStr);
        },
        error: function(xhr) {
            $('#overview-loading').hide();
            $('#unified-overview-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert"></i> Failed to load overview.</div>');
        }
    });
}

function renderOverviewCalendar(data, startStr, endStr) {
    var container = $('#unified-overview-container');
    container.html('');

    var startDate = new Date(startStr);
    var today = new Date();
    today.setHours(0,0,0,0);

    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    // Build day columns header
    var headerHtml = '<div class="calendar-weekday-header d-flex">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var isToday = day.toDateString() === today.toDateString();
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);
        headerHtml += '<div class="weekday-name flex-fill text-center py-1 small fw-bold ' +
            (isToday ? 'bg-primary text-white rounded' : '') +
            (isWeekend ? ' text-muted' : '') + '">' +
            dayNames[day.getDay()] + ' ' + day.getDate() + '/' + (day.getMonth()+1) +
            '</div>';
    }
    headerHtml += '</div>';

    // Group schedules and admins by date
    var schedulesByDay = {};
    var adminsByDay = {};

    (data.schedules || []).forEach(function(s) {
        var dateKey = s.scheduled_time.substring(0, 10);
        if (!schedulesByDay[dateKey]) schedulesByDay[dateKey] = [];
        schedulesByDay[dateKey].push(s);
    });

    (data.unscheduled_admins || []).forEach(function(a) {
        var dateKey = a.administered_at.substring(0, 10);
        if (!adminsByDay[dateKey]) adminsByDay[dateKey] = [];
        adminsByDay[dateKey].push(a);
    });

    // Build day columns
    var gridHtml = '<div class="medication-calendar-grid d-flex" style="min-height:200px;">';
    for (var i = 0; i < 7; i++) {
        var day = new Date(startDate);
        day.setDate(day.getDate() + i);
        var dateKey = formatDateForApi(day);
        var isToday = day.toDateString() === today.toDateString();
        var isPast = day < today && !isToday;
        var isWeekend = (day.getDay() === 0 || day.getDay() === 6);

        var cellClass = 'calendar-day-cell flex-fill border-end p-1';
        if (isToday) cellClass += ' today';
        if (isPast) cellClass += ' past-date';
        if (isWeekend) cellClass += ' weekend';

        gridHtml += '<div class="' + cellClass + '" data-date="' + dateKey + '">';
        gridHtml += '<div class="schedule-items">';

        // Render schedules
        var daySchedules = (schedulesByDay[dateKey] || []).sort(function(a,b) {
            return a.scheduled_time.localeCompare(b.scheduled_time);
        });

        daySchedules.forEach(function(s) {
            var time = new Date(s.scheduled_time);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var statusClass = s.is_administered ? 'status-given' : (isPast ? 'status-missed' : 'status-pending');
            var sourceIcon = s.drug_source === 'ward_stock' ? '🏥' : (s.drug_source === 'patient_own' ? '👤' : '💊');
            var statusIcon = s.is_administered ? '✅' : (isPast ? '❌' : '🕐');

            gridHtml += '<div class="med-item ' + statusClass + '" title="' + s.drug_name + ' - ' + s.dose + ' ' + s.route + '">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(s.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">' + statusIcon + '</span>';
            gridHtml += '</div>';
        });

        // Render unscheduled administrations
        var dayAdmins = adminsByDay[dateKey] || [];
        dayAdmins.forEach(function(a) {
            var time = new Date(a.administered_at);
            var timeStr = time.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true});
            var sourceIcon = a.drug_source === 'ward_stock' ? '🏥' : (a.drug_source === 'patient_own' ? '👤' : '💊');

            gridHtml += '<div class="med-item status-given" title="' + a.drug_name + ' - ' + a.dose + ' (unscheduled)">';
            gridHtml += '<span class="med-time">' + timeStr + '</span> ';
            gridHtml += '<span class="med-name">' + sourceIcon + ' ' + truncate(a.drug_name, 15) + '</span> ';
            gridHtml += '<span class="med-status">✅</span>';
            gridHtml += '</div>';
        });

        if (daySchedules.length === 0 && dayAdmins.length === 0) {
            gridHtml += '<div class="text-muted text-center small py-3"><i class="mdi mdi-calendar-blank"></i><br>No items</div>';
        }

        gridHtml += '</div></div>';
    }
    gridHtml += '</div>';

    container.html(headerHtml + gridHtml);
}

function truncate(str, len) {
    if (!str) return '';
    return str.length> len ? str.substring(0, len) + '…' : str;
}

// Overview nav buttons
$(document).on('click', '#overview-prev-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() - 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-next-btn', function() {
    if (!overviewCurrentStart) return;
    var d = new Date(overviewCurrentStart);
    d.setDate(d.getDate() + 7);
    loadMedOverview(formatDateForApi(d));
});

$(document).on('click', '#overview-today-btn', function() {
    loadMedOverview(null); // null → defaults to current week
});

// =============================================
// PRESCRIPTIONS TAB FUNCTIONS
// =============================================
var rxTabDataCache = null;
var rxCurrentFilter = 'all';

function loadPrescriptionsTab() {
    if (!PATIENT_ID) return;

    $('#rx-loading').show();
    $('#rx-table-wrap').hide();
    $('#rx-empty').hide();

    var url = medicationChartPrescribedRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'GET',
        success: function(data) {
            $('#rx-loading').hide();
            rxTabDataCache = data;

            var rxList = data.prescriptions || [];
            var directList = data.direct_entries || [];

            // Update summary counts
            var dispensed = rxList.filter(function(r) { return r.status === 3; }).length;
            var billed = rxList.filter(function(r) { return r.status === 2; }).length;
            var requested = rxList.filter(function(r) { return r.status === 1; }).length;
            var total = rxList.length + directList.length;

            $('#rx-count-dispensed').text(dispensed);
            $('#rx-count-billed').text(billed);
            $('#rx-count-requested').text(requested);
            $('#rx-count-total').text(total);
            $('#rx-tab-badge').text(total).toggle(total> 0);

            // Render table
            renderPrescriptionsTable(rxList, directList, rxCurrentFilter);
        },
        error: function() {
            $('#rx-loading').hide();
            $('#rx-empty').show().find('p').text('Failed to load prescriptions.');
        }
    });
}

function renderPrescriptionsTable(rxList, directList, filter) {
    var $body = $('#rx-dashboard-body');
    $body.empty();

    var filtered = rxList;
    if (filter && filter !== 'all') {
        filtered = rxList.filter(function(r) { return r.status == filter; });
    }

    if (filtered.length === 0 && (filter !== 'all' || directList.length === 0)) {
        $('#rx-table-wrap').hide();
        $('#rx-empty').show();
        return;
    }

    $('#rx-empty').hide();
    $('#rx-table-wrap').show();

    // Pharmacy prescriptions
    filtered.forEach(function(rx) {
        var statusBadge, statusClass;
        switch (rx.status) {
            case 3:
                statusBadge = '<span class="badge bg-success">Dispensed</span>';
                statusClass = '';
                break;
            case 2:
                statusBadge = rx.is_paid
                    ? '<span class="badge bg-warning text-dark">Awaiting Pharmacy</span>'
                    : '<span class="badge bg-secondary">' + (rx.status_label || 'Awaiting Payment') + '</span>';
                statusClass = '';
                break;
            default:
                statusBadge = '<span class="badge bg-danger">Awaiting Billing</span>';
                statusClass = 'table-danger';
        }

        var qtyInfo = rx.qty_prescribed || 0;
        var adminInfo = (rx.qty_administered || 0) + ' / ' + qtyInfo;
        var remaining = rx.remaining_doses || 0;
        var adminBadge = rx.is_fully_administered
            ? '<span class="badge bg-success">Complete</span>'
            : '<span class="badge bg-' + (remaining <= 0 ? 'danger' : 'secondary') + '">' + adminInfo + '</span>';

        var prescDate = rx.prescribed_at ? formatDate(new Date(rx.prescribed_at)) : '-';

        var actionBtns = '';
        if (rx.can_chart) {
            actionBtns = '<button class="btn btn-sm btn-outline-primary rx-select-btn" data-posr-id="' + rx.posr_id + '" title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>';
        }
        if (rx.status !== 3 && rx.status !== 0) {
            actionBtns += ' <button class="btn btn-sm btn-outline-danger rx-dismiss-btn" data-product-request-id="' + rx.product_request_id + '" data-drug-name="' + (rx.product_name || '') + '" title="Dismiss"><i class="mdi mdi-close-circle"></i></button>';
        }

        $body.append(
            '<tr class="' + statusClass + '">' +
                '<td><strong>' + (rx.product_name || 'Unknown') + '</strong><br><small class="text-muted">' + (rx.product_code || '') + '</small></td>' +
                '<td>' + (rx.dose || '-') + '</td>' +
                '<td><small>' + (rx.doctor_name ? 'Dr. ' + rx.doctor_name : '-') + '</small></td>' +
                '<td><small>' + prescDate + '</small></td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + adminBadge + '<br><small class="text-muted">Remaining: ' + remaining + '</small></td>' +
                '<td class="text-center">' + actionBtns + '</td>' +
            '</tr>'
        );
    });

    // Direct entries (show if filter = 'all')
    if (filter === 'all' && directList.length> 0) {
        $body.append('<tr class="table-light"><td colspan="7" class="fw-bold small text-muted py-1"><i class="mdi mdi-arrow-right"></i> Direct Administrations</td></tr>');

        directList.forEach(function(entry) {
            var isPatientOwn = entry.drug_source === 'patient_own';
            var sourceBadge = isPatientOwn
                ? '<span class="badge" style="background:#7b1fa2;">Patient\'s Own</span>'
                : '<span class="badge bg-info">Ward Stock</span>';
            var drugName = entry.product_name || entry.external_drug_name || 'Unknown';

            $body.append(
                '<tr>' +
                    '<td><strong>' + drugName + '</strong><br><small class="text-muted">' + (entry.product_code || '') + '</small></td>' +
                    '<td>-</td>' +
                    '<td><small>' + (entry.nurse_name || '-') + '</small></td>' +
                    '<td><small>' + (entry.last_administered_at ? formatDate(new Date(entry.last_administered_at)) : '-') + '</small></td>' +
                    '<td>' + sourceBadge + '</td>' +
                    '<td><span class="badge bg-secondary">' + (entry.times_administered || 0) + ' given</span>' +
                        '<br><small class="text-muted">Scheduled: ' + (entry.times_scheduled || 0) + '</small></td>' +
                    '<td class="text-center">' +
                        '<button class="btn btn-sm btn-outline-primary rx-select-direct-btn" ' + 'data-drug-source="' + entry.drug_source + '" ' + 'data-product-id="' + (entry.product_id || '') + '" ' + 'data-external-name="' + (entry.external_drug_name || '') + '" ' + 'title="Select in chart"><i class="mdi mdi-pencil-plus"></i></button>' +
                    '</td>' +
                '</tr>'
            );
        });
    }
}

// Filter buttons for prescriptions tab
$(document).on('click', '#rx-filter-group .btn', function() {
    $('#rx-filter-group .btn').removeClass('active');
    $(this).addClass('active');
    rxCurrentFilter = $(this).data('rx-filter') || 'all';

    if (rxTabDataCache) {
        renderPrescriptionsTable(
            rxTabDataCache.prescriptions || [],
            rxTabDataCache.direct_entries || [],
            rxCurrentFilter
        );
    }
});

// Refresh button
$(document).on('click', '#rx-refresh-btn', function() {
    loadPrescriptionsTab();
});

// Select prescription in chart (switch to Entry tab and pick the drug)
$(document).on('click', '.rx-select-btn', function() {
    var posrId = $(this).data('posr-id');
    if (posrId) {
        // Switch to Entry tab
        $('#med-entry-tab').tab('show');
        // Select the drug in the dropdown
        setTimeout(function() {
            $('#drug-select').val(posrId).trigger('change');
        }, 200);
    }
});

// Select direct entry in chart
$(document).on('click', '.rx-select-direct-btn', function() {
    var drugSource = $(this).data('drug-source');
    var productId = $(this).data('product-id');
    var externalName = $(this).data('external-name');

    // Switch to Entry tab
    $('#med-entry-tab').tab('show');

    // Find matching option in dropdown
    setTimeout(function() {
        var matchVal = null;
        $('#drug-select option').each(function() {
            var $opt = $(this);
            var de = $opt.data('direct-entry');
            if (de && de.drug_source === drugSource) {
                if (drugSource === 'ward_stock' && de.product_id == productId) {
                    matchVal = $opt.val();
                    return false;
                }
                if (drugSource === 'patient_own' && de.external_drug_name === externalName) {
                    matchVal = $opt.val();
                    return false;
                }
            }
        });
        if (matchVal) {
            $('#drug-select').val(matchVal).trigger('change');
        }
    }, 200);
});

// Dismiss prescription from prescriptions tab
$(document).on('click', '.rx-dismiss-btn', function() {
    var productRequestId = $(this).data('product-request-id');
    var drugName = $(this).data('drug-name');
    var reason = prompt('Dismiss "' + drugName + '"? Enter reason:');
    if (!reason) return;

    var url = medicationChartDismissRoute.replace(':patient', PATIENT_ID);

    $.ajax({
        url: url,
        type: 'POST',
        data: {
            _token: CSRF_TOKEN,
            product_request_id: productRequestId,
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Prescription dismissed.');
                loadPrescriptionsTab();
                loadMedicationsList(); // Refresh the dropdown too
            } else {
                toastr.error(response.error || 'Failed to dismiss.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.error || 'Failed to dismiss prescription.');
        }
    });
});

