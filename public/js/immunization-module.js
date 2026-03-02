/**
 * Shared Immunization Module
 * Used by both Nursing Workbench and Maternity Workbench.
 *
 * All functions accept a config object with:
 *   - baseUrl: route prefix (e.g. '/nursing-workbench' or '/maternity-workbench')
 *   - csrfToken: CSRF token string
 *   - currentPatientId: the patient_id currently being managed
 *   - onScheduleReload: callback to reload schedule after administer/skip
 *   - onHistoryReload: callback to reload history after administer
 */
(function(window) {
    'use strict';

    const ImmunizationModule = {};

    // ─── Internal state ──────────────────────────────────────
    let _currentScheduleItem = null;
    let _modalSelectedProduct = null;
    let _config = {};
    let _modalSearchTimeout = null;
    let _eventsBound = false;

    /**
     * Configure the module for the current context
     */
    ImmunizationModule.configure = function(config) {
        _config = Object.assign({
            baseUrl: '/nursing-workbench',
            csrfToken: '',
            currentPatientId: null,
            onScheduleReload: null,
            onHistoryReload: null,
            storesHtml: '',           // <option> HTML for store selects
            productSearchUrl: '/live-search-products',
            productStockUrl: '/pharmacy-workbench/product/{id}/stock',
            productBatchesUrl: null,  // e.g. '/nursing-workbench/product-batches' or '/maternity-workbench/product-batches'
        }, config);
    };

    /**
     * Get current config
     */
    ImmunizationModule.getConfig = function() {
        return _config;
    };

    // ═══════════════════════════════════════════════════════════
    // SCHEDULE RENDERING
    // ═══════════════════════════════════════════════════════════

    /**
     * Load immunization schedule for a patient
     * @param {number} patientId
     * @param {string} containerId - CSS selector for container
     * @param {string} scheduleUrl - full URL to GET schedule
     * @param {object} opts - { templateSelectId, addBtnId, activeSchedulesId }
     */
    ImmunizationModule.loadSchedule = function(patientId, containerId, scheduleUrl, opts) {
        opts = opts || {};
        const $container = $(containerId);
        $container.html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading immunization schedule...</p>
            </div>
        `);

        $.ajax({
            url: scheduleUrl,
            method: 'GET',
            success: function(response) {
                if (!response.success) {
                    $container.html(`<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> ${response.message || 'Failed to load schedule'}</div>`);
                    return;
                }
                if (!response.has_schedule) {
                    ImmunizationModule._showNoSchedule(containerId, patientId, response.patient, opts);
                    return;
                }
                ImmunizationModule.renderScheduleTimeline(response, containerId, opts);
            },
            error: function(xhr) {
                if (xhr.status === 404) {
                    ImmunizationModule._showNoSchedule(containerId, patientId, null, opts);
                } else {
                    $container.html(`<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load immunization schedule</div>`);
                }
            }
        });
    };

    /**
     * Show "no schedule" message with option to generate
     */
    ImmunizationModule._showNoSchedule = function(containerId, patientId, patient, opts) {
        const patientInfo = patient ? `<p class="mb-2">Patient: <strong>${patient.name}</strong> (Age: ${patient.age})</p>` : '';

        if (opts.activeSchedulesId) {
            $(opts.activeSchedulesId).html(`
                <div class="mb-2">
                    <strong>Active Schedules:</strong> <span class="text-muted">None — Add a schedule using the selector above</span>
                </div>
            `);
        }

        $(containerId).html(`
            <div class="text-center py-4">
                <i class="mdi mdi-calendar-plus mdi-48px text-muted"></i>
                <p class="text-muted mt-2">No immunization schedule found for this patient.</p>
                ${patientInfo}
                <p class="text-muted">Select a schedule template above and click "Add Schedule" to generate a vaccination schedule.</p>
            </div>
        `);
    };

    /**
     * Render schedule timeline (age-grouped cards with vaccine cards)
     */
    ImmunizationModule.renderScheduleTimeline = function(response, containerId, opts) {
        const { patient, schedule, stats, active_templates } = response;
        opts = opts || {};

        // Update active schedules display
        if (opts.activeSchedulesId) {
            let templateBadges = '';
            if (active_templates && active_templates.length > 0) {
                templateBadges = active_templates.map(function(t) {
                    return '<span class="badge badge-primary mr-1"><i class="mdi mdi-calendar-check"></i> ' + t.name + '</span>';
                }).join('');
            }
            $(opts.activeSchedulesId).html(`
                <div class="mb-2">
                    <strong>Active Schedules:</strong> ${templateBadges || '<span class="text-muted">None</span>'}
                </div>
            `);
        }

        let html = '';

        // Patient info & stats bar
        if (patient) {
            html += `
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="mr-3"><i class="mdi mdi-account-circle mdi-36px text-primary"></i></div>
                        <div>
                            <h6 class="mb-0">${patient.name}</h6>
                            <small class="text-muted">DOB: ${patient.dob || 'N/A'} | Age: ${patient.age}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end gap-2">
                        <span class="badge badge-success" title="Administered"><i class="mdi mdi-check"></i> ${stats.administered}</span>
                        <span class="badge badge-warning" title="Pending"><i class="mdi mdi-clock"></i> ${stats.pending}</span>
                        <span class="badge badge-danger" title="Overdue"><i class="mdi mdi-alert"></i> ${stats.overdue}</span>
                        <span class="badge badge-info" title="Skipped"><i class="mdi mdi-skip-next"></i> ${stats.skipped}</span>
                    </div>
                </div>
            </div>`;
        }

        html += '<div class="schedule-timeline">';

        schedule.forEach(function(ageGroup, index) {
            const isFirst = index === 0;
            const allAdministered = ageGroup.vaccines.every(function(v) { return v.status === 'administered'; });
            const hasOverdue = ageGroup.vaccines.some(function(v) { return v.status === 'overdue'; });
            const hasDue = ageGroup.vaccines.some(function(v) { return v.status === 'due'; });

            let ageHeaderClass = 'bg-light';
            let ageIcon = 'mdi-clock-outline';
            if (allAdministered) { ageHeaderClass = 'bg-success text-white'; ageIcon = 'mdi-check-all'; }
            else if (hasOverdue) { ageHeaderClass = 'bg-danger text-white'; ageIcon = 'mdi-alert-circle'; }
            else if (hasDue) { ageHeaderClass = 'bg-warning text-dark'; ageIcon = 'mdi-bell'; }

            html += `
            <div class="card-modern mb-2 schedule-age-group" data-age="${ageGroup.age_days}">
                <div class="card-header py-2 ${ageHeaderClass}" style="cursor: pointer;" onclick="$(this).next('.card-body').slideToggle(200)">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="mdi ${ageIcon} mr-1"></i> <strong>${ageGroup.age_display}</strong></span>
                        <span>${ageGroup.vaccines.length} vaccine${ageGroup.vaccines.length > 1 ? 's' : ''} <i class="mdi mdi-chevron-down ml-1"></i></span>
                    </div>
                </div>
                <div class="card-body py-2" ${!isFirst && allAdministered ? 'style="display:none;"' : ''}>
                    <div class="row">`;

            ageGroup.vaccines.forEach(function(vaccine) {
                let statusBadge = '';
                let actionButton = '';

                switch (vaccine.status) {
                    case 'pending':
                        statusBadge = '<span class="badge badge-secondary"><i class="mdi mdi-clock-outline"></i> Pending</span>';
                        break;
                    case 'due':
                        statusBadge = '<span class="badge badge-warning"><i class="mdi mdi-bell"></i> Due Now</span>';
                        actionButton = '<button class="btn btn-sm btn-success mt-1" onclick="ImmunizationModule.openAdministerModal(' + vaccine.id + ')"><i class="mdi mdi-needle"></i> Administer</button>';
                        break;
                    case 'overdue':
                        statusBadge = '<span class="badge badge-danger"><i class="mdi mdi-alert-circle"></i> Overdue</span>';
                        actionButton = '<button class="btn btn-sm btn-danger mt-1" onclick="ImmunizationModule.openAdministerModal(' + vaccine.id + ')"><i class="mdi mdi-needle"></i> Administer Now</button>';
                        break;
                    case 'administered':
                        statusBadge = '<span class="badge badge-success"><i class="mdi mdi-check"></i> Done</span>';
                        break;
                    case 'skipped':
                        statusBadge = '<span class="badge badge-info"><i class="mdi mdi-skip-next"></i> Skipped</span>';
                        break;
                    case 'contraindicated':
                        statusBadge = '<span class="badge badge-dark"><i class="mdi mdi-cancel"></i> Contraindicated</span>';
                        break;
                }

                // Options menu for non-administered vaccines
                let optionsMenu = '';
                if (!['administered', 'skipped', 'contraindicated'].includes(vaccine.status)) {
                    optionsMenu = `
                        <div class="dropdown d-inline">
                            <button class="btn btn-sm btn-link text-muted p-0 ml-1" type="button" data-toggle="dropdown">
                                <i class="mdi mdi-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="#" onclick="ImmunizationModule.skipVaccine(${vaccine.id}); return false;">
                                    <i class="mdi mdi-skip-next"></i> Skip
                                </a>
                                <a class="dropdown-item" href="#" onclick="ImmunizationModule.contraindicateVaccine(${vaccine.id}); return false;">
                                    <i class="mdi mdi-cancel"></i> Contraindicated
                                </a>
                            </div>
                        </div>`;
                }

                html += `
                    <div class="col-md-4 col-sm-6 mb-2">
                        <div class="card-modern h-100 ${vaccine.status === 'overdue' ? 'border-danger' : vaccine.status === 'due' ? 'border-warning' : ''}">
                            <div class="card-body py-2 px-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>${vaccine.dose_label || vaccine.vaccine_name}</strong>
                                        ${optionsMenu}
                                        <br><small class="text-muted">${vaccine.vaccine_code || ''}</small>
                                    </div>
                                    ${statusBadge}
                                </div>
                                <hr class="my-1">
                                <small class="text-muted d-block"><i class="mdi mdi-calendar"></i> Due: ${vaccine.due_date_formatted}</small>
                                ${vaccine.route ? '<small class="text-muted d-block"><i class="mdi mdi-needle"></i> ' + vaccine.route + ' - ' + (vaccine.site || 'N/A') + '</small>' : ''}
                                ${vaccine.administered_date ? '<small class="text-success d-block"><i class="mdi mdi-check"></i> Given: ' + vaccine.administered_date + '</small>' : ''}
                                ${vaccine.skip_reason ? '<small class="text-info d-block"><i class="mdi mdi-information"></i> ' + vaccine.skip_reason + '</small>' : ''}
                                ${actionButton}
                            </div>
                        </div>
                    </div>`;
            });

            html += '</div></div></div>';
        });

        html += '</div>';
        $(containerId).html(html);
    };

    // ═══════════════════════════════════════════════════════════
    // SCHEDULE ACTIONS
    // ═══════════════════════════════════════════════════════════

    /**
     * Skip a scheduled vaccine
     */
    ImmunizationModule.skipVaccine = function(scheduleId) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Skip Vaccine',
                input: 'textarea',
                inputLabel: 'Please provide a reason for skipping this vaccine:',
                inputPlaceholder: 'Enter reason...',
                showCancelButton: true,
                confirmButtonText: 'Skip',
                confirmButtonColor: '#17a2b8',
                inputValidator: function(value) { if (!value) return 'Please provide a reason'; }
            }).then(function(result) {
                if (result.isConfirmed) {
                    ImmunizationModule._updateScheduleStatus(scheduleId, 'skipped', result.value);
                }
            });
        } else {
            var reason = prompt('Reason for skipping this vaccine:');
            if (reason) ImmunizationModule._updateScheduleStatus(scheduleId, 'skipped', reason);
        }
    };

    /**
     * Mark vaccine as contraindicated
     */
    ImmunizationModule.contraindicateVaccine = function(scheduleId) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Contraindication',
                input: 'textarea',
                inputLabel: 'Please provide the contraindication reason:',
                inputPlaceholder: 'e.g., Allergic reaction, Medical condition...',
                showCancelButton: true,
                confirmButtonText: 'Mark Contraindicated',
                confirmButtonColor: '#343a40',
                inputValidator: function(value) { if (!value) return 'Please provide a reason'; }
            }).then(function(result) {
                if (result.isConfirmed) {
                    ImmunizationModule._updateScheduleStatus(scheduleId, 'contraindicated', result.value);
                }
            });
        } else {
            var reason = prompt('Contraindication reason:');
            if (reason) ImmunizationModule._updateScheduleStatus(scheduleId, 'contraindicated', reason);
        }
    };

    ImmunizationModule._updateScheduleStatus = function(scheduleId, status, reason) {
        $.ajax({
            url: _config.baseUrl + '/schedule/' + scheduleId + '/status',
            method: 'PUT',
            headers: { 'X-CSRF-TOKEN': _config.csrfToken },
            data: { status: status, reason: reason },
            success: function(response) {
                if (response.success) {
                    toastr.success('Vaccine marked as ' + status);
                    if (_config.onScheduleReload) _config.onScheduleReload();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to update status');
            }
        });
    };

    /**
     * Generate schedule for patient
     */
    ImmunizationModule.generateSchedule = function(patientId, generateUrl, templateId, onSuccess) {
        var data = {};
        if (templateId) data.template_id = templateId;

        $.ajax({
            url: generateUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': _config.csrfToken },
            data: data,
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    if (onSuccess) onSuccess(response);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate schedule');
            }
        });
    };

    /**
     * Load schedule templates into a select element
     */
    ImmunizationModule.loadTemplates = function(selectId) {
        $.ajax({
            url: _config.baseUrl + '/schedule-templates',
            method: 'GET',
            success: function(response) {
                var options = '<option value="">Select Schedule Template...</option>';
                if (response.templates) {
                    response.templates.forEach(function(t) {
                        var defaultBadge = t.is_default ? ' (Default)' : '';
                        options += '<option value="' + t.id + '">' + t.name + defaultBadge + '</option>';
                    });
                }
                $(selectId).html(options);
            }
        });
    };

    // ═══════════════════════════════════════════════════════════
    // ADMINISTER MODAL
    // ═══════════════════════════════════════════════════════════

    /**
     * Open the administer vaccine modal for a schedule item
     */
    ImmunizationModule.openAdministerModal = function(scheduleId) {
        ImmunizationModule.resetAdministerModal();

        // Find the schedule item from cached data
        // We need to fetch the schedule to find it
        var patientId = _config.currentPatientId;

        $.ajax({
            url: _config.baseUrl + '/patient/' + patientId + '/schedule',
            method: 'GET',
            success: function(response) {
                var found = null;
                if (response.schedule) {
                    response.schedule.forEach(function(ageGroup) {
                        ageGroup.vaccines.forEach(function(vaccine) {
                            if (vaccine.id === scheduleId) found = vaccine;
                        });
                    });
                }

                if (!found) {
                    toastr.error('Schedule item not found');
                    return;
                }

                _currentScheduleItem = found;
                $('#imm-modal-vaccine-name').text(found.vaccine_name);
                $('#imm-modal-dose-label').text(found.dose_label || 'Dose ' + found.dose_number);
                $('#imm-modal-due-date').text(found.due_date_formatted);
                $('#imm-modal-schedule-id').val(scheduleId);

                if (found.route) $('#imm-modal-vaccine-route').val(found.route);
                if (found.site) $('#imm-modal-vaccine-site').val(found.site);

                // Set current date/time
                var now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                $('#imm-modal-vaccine-time').val(now.toISOString().slice(0, 16));

                $('#administerVaccineModalShared').modal('show');
            },
            error: function() {
                toastr.error('Failed to load schedule item details');
            }
        });
    };

    /**
     * Reset the administer modal
     */
    ImmunizationModule.resetAdministerModal = function() {
        _currentScheduleItem = null;
        _modalSelectedProduct = null;
        var form = document.getElementById('imm-modal-immunization-form');
        if (form) form.reset();
        $('#imm-modal-schedule-id').val('');
        $('#imm-modal-product-id').val('');
        $('#imm-modal-vaccine-name').text('-');
        $('#imm-modal-dose-label').text('-');
        $('#imm-modal-due-date').text('-');
        $('#imm-modal-vaccine-search').val('');
        $('#imm-modal-selected-product-card').addClass('d-none');
        $('#imm-modal-stock-error').remove();
    };

    /**
     * Select product in modal
     */
    ImmunizationModule.selectModalProduct = function(element) {
        var storeId = $('#imm-modal-vaccine-store').val();
        if (!storeId) {
            toastr.warning('Please select a store first');
            $('#imm-modal-vaccine-store').focus();
            return;
        }

        var product = {
            id: $(element).data('id'),
            name: $(element).data('name'),
            code: $(element).data('code'),
            qty: $(element).data('qty'),
            price: $(element).data('price'),
            payable: $(element).data('payable'),
            claims: $(element).data('claims'),
            mode: $(element).data('mode'),
            category: $(element).data('category')
        };

        _modalSelectedProduct = product;
        $('#imm-modal-product-id').val(product.id);
        $('#imm-modal-selected-product-name').text(product.name);
        $('#imm-modal-selected-product-details').html(
            '<span class="mr-2">[' + product.code + ']</span><span class="mr-2">' + product.category + '</span>'
        );

        // Price display
        var priceHtml = '₦' + parseFloat(product.price).toLocaleString();
        if (product.mode && product.mode !== 'cash') {
            priceHtml = '<span class="badge badge-info mr-1">' + product.mode.toUpperCase() + '</span>' +
                '<span class="text-danger">Pay: ₦' + parseFloat(product.payable).toLocaleString() + '</span>' +
                '<span class="text-success ml-1">Claim: ₦' + parseFloat(product.claims).toLocaleString() + '</span>';
        }
        $('#imm-modal-selected-product-price').html(priceHtml);
        $('#imm-modal-selected-product-card').removeClass('d-none');
        $('#imm-modal-vaccine-search').val('');
        $('#imm-modal-vaccine-results').hide();

        // Update stock display
        ImmunizationModule._updateStockDisplay();

        // Fetch batches
        ImmunizationModule._fetchBatches(product.id, storeId);
    };

    ImmunizationModule._updateStockDisplay = function() {
        var storeId = $('#imm-modal-vaccine-store').val();
        var productId = $('#imm-modal-product-id').val();
        if (!storeId || !productId) return;

        $.ajax({
            url: _config.productStockUrl.replace('{id}', productId),
            method: 'GET',
            success: function(stockData) {
                var storeStock = (stockData.stores || []).find(function(s) { return s.store_id == storeId; });
                var qty = storeStock ? storeStock.quantity : 0;
                var cls = qty > 0 ? 'text-success' : 'text-danger';
                var icon = qty > 0 ? 'mdi-check-circle' : 'mdi-alert-circle';
                $('#imm-modal-vaccine-store-stock').html('<div class="' + cls + '"><i class="mdi ' + icon + '"></i> Available: <strong>' + qty + '</strong></div>');
                $('#imm-modal-selected-product-stock').html('<span class="' + cls + '"><i class="mdi ' + icon + '"></i> Stock: ' + qty + '</span>');
            },
            error: function() {
                $('#imm-modal-vaccine-store-stock').html('<p class="text-muted mb-0">Could not check stock</p>');
            }
        });
    };

    ImmunizationModule._fetchBatches = function(productId, storeId) {
        var $select = $('#imm-modal-vaccine-batch-select');
        $select.html('<option value="">Loading batches...</option>').prop('disabled', true);

        var batchUrl = _config.productBatchesUrl || (_config.baseUrl + '/product-batches');

        $.ajax({
            url: batchUrl,
            method: 'GET',
            data: { product_id: productId, store_id: storeId },
            success: function(response) {
                $select.prop('disabled', false);
                if (response.success && response.batches && response.batches.length > 0) {
                    var options = '<option value="">Auto (FIFO) - Recommended</option>';
                    response.batches.forEach(function(batch, i) {
                        var expiryText = batch.expiry_formatted ? ' | Exp: ' + batch.expiry_formatted : '';
                        var fifoLabel = i === 0 ? ' ★' : '';
                        options += '<option value="' + batch.id + '" data-expiry="' + (batch.expiry_date || '') +
                            '" data-qty="' + batch.current_qty + '" data-batch-number="' + batch.batch_number + '">' +
                            batch.batch_number + ' (' + batch.current_qty + ' avail)' + expiryText + fifoLabel + '</option>';
                    });
                    $select.html(options);
                    $('#imm-modal-vaccine-batch-help').html(
                        '<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + response.total_available + ' available in ' + response.batches.length + ' batch(es)</span>'
                    );
                    // Auto-fill expiry from FIFO batch
                    if (response.batches[0] && response.batches[0].expiry_date) {
                        $('#imm-modal-vaccine-expiry').val(response.batches[0].expiry_date);
                    }
                } else {
                    $select.html('<option value="">No batches in this store</option>');
                    $('#imm-modal-vaccine-batch-help').html('<span class="text-danger"><i class="mdi mdi-alert"></i> No batches available</span>');
                }
            },
            error: function() {
                $select.prop('disabled', false).html('<option value="">Error loading batches</option>');
            }
        });
    };

    /**
     * Submit immunization from shared modal
     */
    ImmunizationModule.submitAdministration = function() {
        if (!$('#imm-modal-product-id').val()) { toastr.error('Please select a vaccine product'); return; }
        if (!$('#imm-modal-vaccine-site').val()) { toastr.error('Please select administration site'); return; }
        if (!$('#imm-modal-vaccine-route').val()) { toastr.error('Please select administration route'); return; }
        if (!$('#imm-modal-vaccine-time').val()) { toastr.error('Please enter administration time'); return; }
        if (!$('#imm-modal-vaccine-store').val()) { toastr.error('Please select a store'); return; }

        var $btn = $('#imm-modal-submit-immunization');
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Administering...');

        var $batchSelect = $('#imm-modal-vaccine-batch-select');
        var selectedOption = $batchSelect.find(':selected');

        var data = {
            schedule_id: $('#imm-modal-schedule-id').val(),
            product_id: $('#imm-modal-product-id').val(),
            site: $('#imm-modal-vaccine-site').val(),
            route: $('#imm-modal-vaccine-route').val(),
            batch_id: $batchSelect.val(),
            batch_number: selectedOption.data('batch-number') || selectedOption.text().split(' ')[0],
            expiry_date: $('#imm-modal-vaccine-expiry').val(),
            administered_at: $('#imm-modal-vaccine-time').val(),
            manufacturer: $('#imm-modal-vaccine-manufacturer').val(),
            vis_date: $('#imm-modal-vaccine-vis').val(),
            notes: $('#imm-modal-vaccine-notes').val(),
            store_id: $('#imm-modal-vaccine-store').val()
        };

        $.ajax({
            url: _config.baseUrl + '/administer-from-schedule',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': _config.csrfToken },
            data: data,
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);
                if (response.success) {
                    toastr.success(response.message || 'Vaccine administered successfully');
                    $('#administerVaccineModalShared').modal('hide');
                    if (_config.onScheduleReload) _config.onScheduleReload();
                    if (_config.onHistoryReload) _config.onHistoryReload();
                } else {
                    toastr.error(response.message || 'Failed to administer vaccine');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                toastr.error(xhr.responseJSON?.message || 'Failed to administer vaccine');
            }
        });
    };

    // ═══════════════════════════════════════════════════════════
    // HISTORY VIEWS
    // ═══════════════════════════════════════════════════════════

    /**
     * Load immunization timeline view
     */
    ImmunizationModule.loadTimeline = function(patientId, containerId, historyUrl) {
        var $container = $(containerId);
        $container.html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i><p class="text-muted mt-2">Loading timeline...</p></div>');

        $.ajax({
            url: historyUrl,
            method: 'GET',
            success: function(response) {
                var records = response.records || response.history || [];
                if (records.length === 0) {
                    $container.html('<div class="text-center py-4"><i class="mdi mdi-calendar-blank mdi-48px text-muted"></i><p class="text-muted mt-2">No immunization records found</p></div>');
                    return;
                }

                var html = '<div class="timeline-container" style="position: relative; padding-left: 30px; border-left: 3px solid #dee2e6;">';
                records.forEach(function(record) {
                    var dateDisplay = record.administered_date || record.administered_at || '';
                    html += '<div class="timeline-item mb-3" style="position: relative;">' +
                        '<div class="timeline-marker" style="position: absolute; left: -40px; width: 20px; height: 20px; border-radius: 50%; background: var(--success); border: 3px solid white; box-shadow: 0 0 0 3px var(--success);"></div>' +
                        '<div class="card-modern"><div class="card-body py-2">' +
                        '<div class="d-flex justify-content-between align-items-start"><div>' +
                        '<strong>' + record.vaccine_name + '</strong>' +
                        '<span class="badge badge-success ml-2">' + (record.dose_number || 'N/A') + '</span><br>' +
                        '<small class="text-muted"><i class="mdi mdi-calendar"></i> ' + dateDisplay +
                        (record.site ? ' | <i class="mdi mdi-map-marker"></i> ' + record.site : '') +
                        (record.batch_number ? ' | <i class="mdi mdi-barcode"></i> ' + record.batch_number : '') +
                        '</small></div>' +
                        '<small class="text-muted">' + (record.administered_by || '') + '</small>' +
                        '</div>' +
                        (record.notes ? '<small class="text-muted d-block mt-1"><i class="mdi mdi-note"></i> ' + record.notes + '</small>' : '') +
                        '</div></div></div>';
                });
                html += '</div>';
                $container.html(html);
            },
            error: function() {
                $container.html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load timeline</div>');
            }
        });
    };

    /**
     * Load immunization calendar view (grouped by month)
     */
    ImmunizationModule.loadCalendar = function(patientId, containerId, historyUrl) {
        var $container = $(containerId);
        $container.html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i><p class="text-muted mt-2">Loading calendar...</p></div>');

        $.ajax({
            url: historyUrl,
            method: 'GET',
            success: function(response) {
                var records = response.records || response.history || [];
                if (records.length === 0) {
                    $container.html('<div class="text-center py-4"><i class="mdi mdi-calendar-blank mdi-48px text-muted"></i><p class="text-muted mt-2">No immunization records found</p></div>');
                    return;
                }

                var grouped = {};
                records.forEach(function(record) {
                    var dateStr = record.administered_date || record.administered_at;
                    var date = new Date(dateStr);
                    var key = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
                    if (!grouped[key]) {
                        grouped[key] = { label: date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }), records: [] };
                    }
                    grouped[key].records.push(record);
                });

                var html = '<div class="row">';
                Object.keys(grouped).sort().reverse().forEach(function(key) {
                    var group = grouped[key];
                    html += '<div class="col-md-6 col-lg-4 mb-3"><div class="card-modern h-100">' +
                        '<div class="card-header bg-primary text-white py-2"><i class="mdi mdi-calendar-month"></i> ' + group.label + '</div>' +
                        '<div class="card-body py-2"><ul class="list-unstyled mb-0">';
                    group.records.forEach(function(record) {
                        var dateStr = record.administered_date || record.administered_at;
                        var day = new Date(dateStr).getDate();
                        html += '<li class="mb-1"><span class="badge badge-secondary mr-1">' + day + '</span>' +
                            '<span>' + record.vaccine_name + '</span>' +
                            '<small class="text-muted"> (' + (record.dose_number || 'N/A') + ')</small></li>';
                    });
                    html += '</ul></div></div></div>';
                });
                html += '</div>';
                $container.html(html);
            },
            error: function() {
                $container.html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load calendar</div>');
            }
        });
    };

    /**
     * Load immunization history DataTable
     */
    ImmunizationModule.loadHistoryTable = function(patientId, containerId, tableId, historyUrl) {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        $(tableId).DataTable({
            processing: true,
            serverSide: false,
            ajax: { url: historyUrl, dataSrc: function(json) { return json.records || json.history || []; } },
            columns: [
                { data: 'administered_date', render: function(d) { return d || 'N/A'; } },
                { data: 'vaccine_name', render: function(d) { return d || 'N/A'; } },
                { data: 'dose_number', render: function(d) { return d ? 'Dose ' + d : 'N/A'; } },
                { data: 'dose', defaultContent: 'N/A' },
                { data: 'batch_number', defaultContent: 'N/A' },
                { data: 'site', defaultContent: 'N/A' },
                { data: 'administered_by', defaultContent: 'N/A' }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            language: { emptyTable: 'No immunization records found', processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...' }
        });
    };

    // ═══════════════════════════════════════════════════════════
    // MODAL EVENT BINDINGS (call once after DOM ready)
    // ═══════════════════════════════════════════════════════════

    ImmunizationModule.initModalEvents = function() {
        if (_eventsBound) return;
        _eventsBound = true;

        // Product search
        $(document).on('input', '#imm-modal-vaccine-search', function() {
            var term = $(this).val();
            clearTimeout(_modalSearchTimeout);
            if (term.length < 2) { $('#imm-modal-vaccine-results').hide(); return; }

            _modalSearchTimeout = setTimeout(function() {
                $.ajax({
                    url: _config.productSearchUrl,
                    method: 'GET',
                    data: { term: term, patient_id: _config.currentPatientId },
                    success: function(data) {
                        var $results = $('#imm-modal-vaccine-results').html('');
                        if (!data || data.length === 0) {
                            $results.html('<li class="list-group-item text-muted">No products found</li>').show();
                            return;
                        }
                        data.forEach(function(item) {
                            var cat = (item.category && item.category.category_name) ? item.category.category_name : 'N/A';
                            var name = item.product_name || 'Unknown';
                            var code = item.product_code || '';
                            var qty = item.stock && item.stock.current_quantity !== undefined ? item.stock.current_quantity : 0;
                            var price = item.price && item.price.initial_sale_price !== undefined ? item.price.initial_sale_price : 0;
                            var payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                            var claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                            var mode = item.coverage_mode || 'cash';
                            var qtyClass = qty > 0 ? 'text-success' : 'text-danger';

                            var mk = '<li class="list-group-item list-group-item-action" style="cursor:pointer;"' +
                                ' data-id="' + item.id + '" data-name="' + name + '" data-code="' + code + '"' +
                                ' data-qty="' + qty + '" data-price="' + price + '" data-payable="' + payable + '"' +
                                ' data-claims="' + claims + '" data-mode="' + mode + '" data-category="' + cat + '"' +
                                ' onclick="ImmunizationModule.selectModalProduct(this)">' +
                                '<div class="d-flex justify-content-between align-items-start"><div>' +
                                '<strong>' + name + '</strong> <small class="text-muted">[' + code + ']</small>' +
                                '<div class="small text-muted">' + cat + '</div></div>' +
                                '<div class="text-end"><div class="' + qtyClass + '"><strong>' + qty + '</strong> avail.</div>' +
                                '<div>₦' + price + '</div></div></div></li>';
                            $results.append(mk);
                        });
                        $results.show();
                    },
                    error: function() {
                        $('#imm-modal-vaccine-results').html('<li class="list-group-item text-danger">Search failed</li>').show();
                    }
                });
            }, 300);
        });

        // Hide search results on click outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#imm-modal-vaccine-search, #imm-modal-vaccine-results').length) {
                $('#imm-modal-vaccine-results').hide();
            }
        });

        // Remove selected product
        $(document).on('click', '#imm-modal-remove-product', function() {
            _modalSelectedProduct = null;
            $('#imm-modal-product-id').val('');
            $('#imm-modal-selected-product-card').addClass('d-none');
        });

        // Store change
        $(document).on('change', '#imm-modal-vaccine-store', function() {
            var storeId = $(this).val();
            if (storeId) {
                $('#imm-modal-vaccine-store-placeholder').hide();
                $('#imm-modal-vaccine-store-info').show();
                ImmunizationModule._updateStockDisplay();
            } else {
                $('#imm-modal-vaccine-store-info').hide();
                $('#imm-modal-vaccine-store-placeholder').show();
            }
        });

        // Submit button
        $(document).on('click', '#imm-modal-submit-immunization', function() {
            ImmunizationModule.submitAdministration();
        });
    };

    // Expose globally
    window.ImmunizationModule = ImmunizationModule;

})(window);
