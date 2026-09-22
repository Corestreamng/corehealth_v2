/**
 * Encounter Procedures Workbench Module
 * CoreHealth v2 — Modular Doctor Encounter Procedure Booking & Management
 */

(function ($) {
    'use strict';

    let currentSelectedProc = null;
    let currentProcedureId = null;
    let noteEditorInstance = null;

    function getEncounterConfig() {
        return window.WORKBENCH_CONFIG || {};
    }

    function formatCurrency(num) {
        return '₦' + (Number(num) || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatNumber(num) {
        return new Intl.NumberFormat('en-NG').format(num || 0);
    }

    // Initialize module on DOM ready
    $(document).ready(function () {
        initProcedureHistoryTable();
        setupProcedureSearch();
        setupConfiguratorEvents();
    });

    function initProcedureHistoryTable() {
        if (!$.fn.DataTable) return;
        const config = getEncounterConfig();
        const patientId = config.patientId || 0;

        if ($.fn.DataTable.isDataTable('#procedure_history_list')) {
            $('#procedure_history_list').DataTable().destroy();
        }

        const historyUrl = (config.routes && config.routes.procedureHistoryList)
            ? config.routes.procedureHistoryList
            : ((config.baseUrl || '') + '/procedureHistoryList/' + patientId);

        $('#procedure_history_list').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: historyUrl,
                type: 'GET',
                error: function (xhr, error, thrown) {
                    console.log('Error loading procedure history:', error);
                }
            },
            columns: [
                { data: 'info', name: 'info', orderable: false, searchable: false }
            ],
            order: [],
            language: {
                emptyTable: "No procedures found for this patient"
            }
        });
    }

    function setupProcedureSearch() {
        let searchTimeout;

        $('#procedure_search').on('keyup', function () {
            const query = $(this).val();
            clearTimeout(searchTimeout);

            if (!query || query.length < 2) {
                $('#procedure_search_results').hide();
                return;
            }

            searchTimeout = setTimeout(function () {
                executeProcedureSearch(query);
            }, 300);
        });

        // Hide results when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#procedure_search, #procedure_search_results').length) {
                $('#procedure_search_results').hide();
            }
        });
    }

    function executeProcedureSearch(query) {
        const config = getEncounterConfig();
        const patientId = config.patientId || 0;
        const procedureCategoryId = config.procedureCategoryId || 8;
        const searchUrl = (config.baseUrl || '') + '/live-search-services';

        $.ajax({
            url: searchUrl,
            type: 'GET',
            data: {
                term: query,
                category_id: procedureCategoryId,
                patient_id: patientId
            },
            success: function (data) {
                const $results = $('#procedure_search_results');
                $results.empty();

                if (!data || data.length === 0) {
                    $results.append('<li class="list-group-item text-muted">No matching procedures found</li>');
                } else {
                    data.forEach(function (item) {
                        const isSelected = window.ClinicalOrdersKit ? window.ClinicalOrdersKit.isAlreadyAdded('procedures', item.id) : false;
                        const category = item.procedure_category
                            || (typeof item.category === 'object' ? item.category?.category_name : item.category)
                            || 'Procedures';
                        const name = item.service_name || 'Unknown';
                        const code = item.service_code || '';
                        const price = item.price && item.price.sale_price !== undefined ? item.price.sale_price : 0;
                        const payable = item.payable_amount !== undefined && item.payable_amount !== null ? item.payable_amount : price;
                        const claims = item.claims_amount !== undefined && item.claims_amount !== null ? item.claims_amount : 0;
                        const mode = item.coverage_mode || null;

                        const isSurgical = Boolean(item.is_surgical);
                        const surgPill = isSurgical
                            ? ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa fa-cut"></i> Surgical</span>'
                            : ' <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fa fa-stethoscope"></i> Bedside</span>';
                        const displayCategory = category + surgPill;

                        const onClick = isSelected ? '' : `window.EncounterProcedures.selectProcedureForBooking(${JSON.stringify(item).replace(/"/g, '&quot;')})`;
                        if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.renderSearchResultItem === 'function') {
                            const mk = window.ClinicalOrdersKit.renderSearchResultItem({
                                id: item.id,
                                category: displayCategory,
                                name: name,
                                code: code,
                                price: price,
                                payable: payable,
                                claims: claims,
                                mode: mode,
                                alreadyAdded: isSelected,
                                alreadyLabel: 'Already Added',
                                onClick: onClick
                            });
                            $results.append(mk);
                        } else {
                            $results.append(`
                                <li class="list-group-item list-group-item-action ${isSelected ? 'disabled' : ''}" style="cursor:pointer;" onclick="${onClick}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${name}</strong> <small class="text-muted">(${code})</small>
                                            <br><small class="badge bg-light text-dark border">${category}</small>${surgPill}
                                        </div>
                                        <div class="text-end">
                                            <span class="fw-bold">${formatCurrency(payable)}</span>
                                            ${claims > 0 ? `<br><small class="text-success">+ ${formatCurrency(claims)} HMO</small>` : ''}
                                        </div>
                                    </div>
                                </li>
                            `);
                        }
                    });
                }

                if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.appendFreeFormLink === 'function') {
                    window.ClinicalOrdersKit.appendFreeFormLink($results, query, 'Add Free-Form Procedure', 'Enter the procedure name:', '#procedure_search', function (val) {
                        selectProcedureForBooking({
                            id: 'FF_' + val,
                            service_name: val + ' [Free-form]',
                            is_free_form: true,
                            price: { sale_price: 0 },
                            payable_amount: 0,
                            claims_amount: 0
                        });
                    });
                }

                $results.show();
            },
            error: function (xhr) {
                console.error('Error searching procedures:', xhr);
                $('#procedure_search_results').html('<li class="list-group-item text-danger">Error loading procedures</li>').show();
            }
        });
    }

    function setupConfiguratorEvents() {
        $('#proc_priority').on('change', function () {
            updateProcSummary();
        });
    }

    function updateProcSummary() {
        if (!currentSelectedProc) {
            $('#proc_summary_text').text('Ready to add procedure to encounter');
            return;
        }
        const isDeferred = ($('#defer_proc_billing').val() === '1') || $('#defer_proc_billing').is(':checked');
        const priority = $('#proc_priority').val() || 'routine';
        const priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);
        const catalogPrice = (currentSelectedProc?.price && currentSelectedProc?.price?.sale_price) ? Number(currentSelectedProc.price.sale_price) : 0;

        let billingText = '';
        if (isDeferred) {
            billingText = '<span class="text-warning fw-semibold"><i class="fa fa-clock"></i> Fee Deferred (Billed in Workbench)</span>';
        } else {
            billingText = `<strong>Fee: ${formatCurrency(catalogPrice)}</strong> <span class="text-muted">(Standard Tariff)</span>`;
        }

        const isSurg = currentSelectedProc ? Boolean(currentSelectedProc.is_surgical) : false;
        const typeBadge = isSurg
            ? '<span class="badge bg-danger me-1"><i class="fa fa-cut"></i> Surgical</span>'
            : '<span class="badge bg-info text-dark me-1"><i class="fa fa-stethoscope"></i> Bedside</span>';

        $('#proc_summary_text').html(`${typeBadge} ${billingText} &bull; <span class="text-secondary">Priority: ${priorityLabel}</span>`);
    }

    function selectProcedureForBooking(procedure) {
        currentSelectedProc = procedure;
        $('#procedure_search_results').hide();
        $('#procedure_search').val('');

        const isFreeForm = String(procedure.id).startsWith('FF_');
        const catalogPrice = (procedure.price && procedure.price.sale_price) ? Number(procedure.price.sale_price) : 0;
        const category = procedure.procedure_category
            || (typeof procedure.category === 'object' ? procedure.category?.category_name : procedure.category)
            || (isFreeForm ? 'Free-form' : 'Procedures');

        // Populate summary card
        $('#proc_config_title').text(procedure.service_name || 'Procedure');
        $('#proc_config_code').text(procedure.service_code || (isFreeForm ? 'Free-form Request' : ''));
        $('#proc_config_category').text(category);

        // Surgical vs Non-Surgical Adaptations
        const isSurgical = Boolean(procedure.is_surgical);
        if (isSurgical) {
            $('#proc_config_surgical_badge').show();
            $('#proc_config_clinical_badge').hide();
            $('#proc_operating_room_label').html('<i class="fa fa-cut text-danger me-1"></i> Operating Theatre / OR Suite (Optional)');
            $('#proc_operating_room').attr('placeholder', 'e.g. Main OR 1 / Theatre 2');
            $('#proc_surgical_prep_box').slideDown(200);
            $('#proc_clinical_prep_box').slideUp(200);
            $('#proc_pre_notes').attr('placeholder', 'Pre-op diagnosis, surgical approach, implant/stapler requirements, theatre prep notes...');
        } else {
            $('#proc_config_surgical_badge').hide();
            $('#proc_config_clinical_badge').show();
            $('#proc_operating_room_label').html('<i class="fa fa-stethoscope text-primary me-1"></i> Procedure Room / Bedside (Optional)');
            $('#proc_operating_room').attr('placeholder', 'e.g. Minor Procedure Room / Ward Bedside');
            $('#proc_surgical_prep_box').slideUp(200);
            $('#proc_clinical_prep_box').slideDown(200);
            $('#proc_pre_notes').attr('placeholder', 'Clinical indications, dressing type / consumables needed, patient instructions...');
        }

        // Set default date to today if empty
        if (!$('#proc_scheduled_date').val()) {
            try {
                $('#proc_scheduled_date').val(new Date().toISOString().split('T')[0]);
            } catch (e) {}
        }

        updateProcSummary();

        // Hide empty state & show configurator
        $('#proc_empty_placeholder').slideUp(150);
        $('#proc_config_card').slideDown(250);
        document.getElementById('proc_config_card').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function cancelProcedureBookingConfig() {
        currentSelectedProc = null;
        $('#proc_config_surgical_badge').hide();
        $('#proc_config_clinical_badge').hide();
        $('#proc_surgical_prep_box').hide();
        $('#proc_clinical_prep_box').hide();
        $('#proc_config_card').slideUp(200);
        $('#proc_empty_placeholder').slideDown(200);
        $('#procedure_search').val('').focus();
    }

    function setProcPreset(type) {
        if (!currentSelectedProc) return;
        const catalogPrice = currentSelectedProc.price?.sale_price || 0;
        const total = parseFloat($('#proc_total_price').val()) || (parseFloat($('#proc_payable_amount').val()) || 0) + (parseFloat($('#proc_claims_amount').val()) || 0) || catalogPrice;

        if (type === 'patient') {
            $('#proc_payable_amount').val(total);
            $('#proc_claims_amount').val(0);
        } else if (type === 'hmo') {
            $('#proc_payable_amount').val(0);
            $('#proc_claims_amount').val(total);
        } else if (type === 'split') {
            const half = Math.round(total / 2);
            $('#proc_payable_amount').val(half);
            $('#proc_claims_amount').val(total - half);
        } else if (type === 'tariff') {
            const payable = currentSelectedProc.payable_amount !== undefined ? currentSelectedProc.payable_amount : catalogPrice;
            const claims = currentSelectedProc.claims_amount !== undefined ? currentSelectedProc.claims_amount : 0;
            const mode = currentSelectedProc.coverage_mode || 'cash';
            $('#proc_total_price').val(payable + claims > 0 ? (payable + claims) : catalogPrice);
            $('#proc_payable_amount').val(payable);
            $('#proc_claims_amount').val(claims);
            $('#proc_coverage_mode').val(mode).trigger('change');
        }
        updateProcSummary();
    }

    function addConfiguredProcedure() {
        if (!currentSelectedProc) {
            if (typeof toastr !== 'undefined') toastr.warning('Please search and select a procedure first.');
            return;
        }

        const procId = currentSelectedProc.id;
        if (window.ClinicalOrdersKit && window.ClinicalOrdersKit.isAlreadyAdded('procedures', procId)) {
            if (typeof toastr !== 'undefined') toastr.warning('This procedure is already added to this encounter.');
            return;
        }

        const config = getEncounterConfig();
        const encounterId = config.encounterId || 0;
        const csrfToken = config.csrf || $('meta[name="csrf-token"]').attr('content');
        const baseUrl = config.baseUrl || '';

        const priority = $('#proc_priority').val() || 'routine';
        const scheduledDate = $('#proc_scheduled_date').val() || null;
        const scheduledTime = $('#proc_scheduled_time').val() || null;
        const operatingRoom = $('#proc_operating_room').val() || null;
        const preNotes = $('#proc_pre_notes').val() || '';
        const deferBilling = ($('#proc_defer_billing').val() === '1') || ($('#defer_proc_billing').val() === '1') || $('#defer_proc_billing').is(':checked') ? 1 : 0;
        const coverageMode = $('#proc_coverage_mode').val() || 'cash';
        const payableAmount = parseFloat($('#proc_payable_amount').val()) || 0;
        const claimsAmount = parseFloat($('#proc_claims_amount').val()) || 0;
        const totalFee = parseFloat($('#proc_total_price').val()) || (payableAmount + claimsAmount);
        const authCode = $('#proc_auth_code').val() || null;

        const isFreeForm = String(procId).startsWith('FF_');
        const categoryName = isFreeForm
            ? 'Free-form Request'
            : (currentSelectedProc.procedure_category
                || (typeof currentSelectedProc.category === 'object' ? currentSelectedProc.category?.category_name : currentSelectedProc.category)
                || 'Procedures');
        const priorityClass = `priority-${priority}`;
        const priorityLabel = priority.charAt(0).toUpperCase() + priority.slice(1);

        let billingBadge = '';
        if (isFreeForm) {
            billingBadge = '<span class="badge bg-secondary">Free-form</span>';
        } else if (deferBilling) {
            billingBadge = '<span class="badge bg-warning text-dark"><i class="fa fa-clock"></i> Base Fee Unbilled</span>';
        } else if (coverageMode !== 'cash') {
            const covClass = coverageMode === 'express' ? 'success' : (coverageMode === 'primary' ? 'primary' : 'secondary');
            billingBadge = `<span class="badge bg-${covClass}"><i class="fa fa-shield-alt"></i> HMO (${coverageMode.toUpperCase()})</span><br><small class="text-muted">${formatCurrency(payableAmount)} Patient / ${formatCurrency(claimsAmount)} HMO</small>`;
        } else {
            billingBadge = `<span class="badge bg-secondary"><i class="fa fa-wallet"></i> Self-Pay</span><br><small class="text-muted">${formatCurrency(payableAmount)}</small>`;
        }

        const isSurgical = Boolean(currentSelectedProc.is_surgical);
        const prepDetails = {
            is_surgical: isSurgical,
            operating_room: operatingRoom,
        };

        if (isSurgical) {
            prepDetails.npo_status = $('#proc_npo_status').val() || 'npo_midnight';
            prepDetails.anesthesia_type = $('#proc_anesthesia_type').val() || 'general';
            prepDetails.consent_req = $('#proc_surgical_consent').val() || 'required';
            prepDetails.blood_required = $('#proc_blood_required').is(':checked');
            prepDetails.prep_notes = $('#proc_surgical_prep_notes').val() || '';
        } else {
            prepDetails.procedure_pack = $('#proc_clinical_pack').val() || 'routine_pack';
            prepDetails.consent_req = $('#proc_clinical_consent').val() || 'routine_explained';
            prepDetails.observation_plan = $('#proc_observation_plan').val() || 'immediate';
            prepDetails.prep_notes = $('#proc_clinical_prep_notes').val() || '';
        }

        const payload = {
            service_id: procId,
            priority: priority,
            scheduled_date: scheduledDate,
            scheduled_time: scheduledTime,
            operating_room: operatingRoom,
            pre_notes: preNotes,
            defer_billing: deferBilling,
            custom_price: totalFee,
            coverage_mode: coverageMode,
            payable_amount: payableAmount,
            claims_amount: claimsAmount,
            auth_code: authCode,
            prep_details: prepDetails
        };

        const postUrl = `${baseUrl}/encounters/${encounterId}/add-procedure`;

        if (window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.addItem === 'function') {
            window.ClinicalOrdersKit.addItem({
                url: postUrl,
                payload: payload,
                csrfToken: csrfToken,
                tableSelector: '#selected-procedures',
                type: 'procedures',
                referenceId: procId,
                buildRowHtml: function (resp) {
                    const rowRecordId = resp.id || resp.item?.id || procId;
                    const workbenchUrl = `${baseUrl}/patient-procedures/${rowRecordId}`;
                    const typeBadge = isSurgical
                        ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1"><i class="fa fa-cut"></i> SURGICAL</span>'
                        : '<span class="badge bg-info-subtle text-info border border-info-subtle ms-1"><i class="fa fa-stethoscope"></i> CLINICAL</span>';

                    let prepPill = '';
                    if (isSurgical) {
                        const npo = prepDetails.npo_status ? prepDetails.npo_status.replace(/_/g, ' ') : '';
                        const anesth = prepDetails.anesthesia_type ? prepDetails.anesthesia_type.replace(/_/g, ' ') : 'GA';
                        prepPill = `<br><small class="text-danger"><i class="fa fa-cut"></i> NPO: ${npo} | Anesth: ${anesth.toUpperCase()}</small>`;
                    } else if (prepDetails.procedure_pack && prepDetails.procedure_pack !== 'routine_pack') {
                        const packLabel = $('#proc_clinical_pack option:selected').text() || prepDetails.procedure_pack.replace(/_/g, ' ');
                        prepPill = `<br><small class="text-info"><i class="fa fa-box-open"></i> ${packLabel}</small>`;
                    }

                    return `<tr data-record-id="${rowRecordId}" data-record-type="procedure" data-service-id="${procId}">
                        <td>
                            ${isFreeForm ? `<h6 class="mb-0"><span class="badge bg-info text-dark">${currentSelectedProc.service_name}</span></h6>` : `<strong><span class="badge bg-success">${currentSelectedProc.service_name || 'Procedure'}</span></strong>`} ${typeBadge}
                            ${!isFreeForm && currentSelectedProc.service_code ? `<br><small class="text-muted">${currentSelectedProc.service_code}</small>` : ''}
                            ${preNotes ? `<br><small class="text-info"><i class="fa fa-sticky-note"></i> ${preNotes.substring(0, 50)}${preNotes.length > 50 ? '...' : ''}</small>` : ''}
                        </td>
                        <td>
                            <small class="text-muted">${categoryName}</small><br>
                            <span class="priority-badge ${priorityClass}">${priorityLabel}</span>
                            ${scheduledDate ? `<br><small class="text-muted"><i class="fa fa-calendar"></i> ${scheduledDate}${scheduledTime ? ' ' + scheduledTime : ''}</small>` : ''}
                            ${operatingRoom ? `<br><small class="text-secondary"><i class="fa fa-door-open"></i> ${operatingRoom}</small>` : ''}
                            ${prepPill}
                        </td>
                        <td>${billingBadge}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${workbenchUrl}" target="_blank" class="btn btn-sm btn-outline-primary" title="Open Procedure Workbench">
                                    <i class="fa fa-external-link-alt"></i> Workbench
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.EncounterProcedures.removeProcedure(this, '${procId}')" title="Remove">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                },
                onSuccess: function () {
                    $('#no_procedures_message').hide();
                    cancelProcedureBookingConfig();
                    $('#proc_empty_placeholder').show();
                    if ($('#procedure_history_list').length && $.fn.DataTable.isDataTable('#procedure_history_list')) {
                        $('#procedure_history_list').DataTable().ajax.reload(null, false);
                    }
                    if (typeof toastr !== 'undefined') toastr.success('Procedure booked successfully.');
                }
            });
        }
    }

    function removeProcedure(btn, serviceId) {
        const $tr = $(btn).closest('tr');
        const recordId = $tr.data('record-id');
        const config = getEncounterConfig();
        const encounterId = config.encounterId || 0;
        const csrfToken = config.csrf || $('meta[name="csrf-token"]').attr('content');
        const baseUrl = config.baseUrl || '';

        if (recordId && window.ClinicalOrdersKit && typeof window.ClinicalOrdersKit.removeItem === 'function') {
            window.ClinicalOrdersKit.removeItem({
                url: `${baseUrl}/encounters/${encounterId}/procedures/${recordId}`,
                csrfToken: csrfToken,
                rowSelector: $tr,
                type: 'procedures',
                referenceId: serviceId ? (isNaN(serviceId) ? serviceId : parseInt(serviceId)) : null,
                tableSelector: '#selected-procedures',
                onSuccess: function () {
                    if ($('#selected-procedures tr[data-record-id]').length === 0) {
                        $('#no_procedures_message').show();
                    }
                    if ($('#procedure_history_list').length && $.fn.DataTable.isDataTable('#procedure_history_list')) {
                        $('#procedure_history_list').DataTable().ajax.reload(null, false);
                    }
                }
            });
        } else {
            $tr.remove();
            if ($('#selected-procedures tr').length === 0) {
                $('#no_procedures_message').show();
            }
        }
    }

    // Modal helpers for procedure management
    function openTeamModal(procedureId) {
        if (!procedureId) return;
        currentProcedureId = procedureId;
        $('#procedure_team_list').html('<tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading team...</td></tr>');
        $('#team_member_user').val('');
        $('#procedureTeamModal').modal('show');
        loadTeamMembers(procedureId);
    }

    function loadTeamMembers(procedureId) {
        const baseUrl = getEncounterConfig().baseUrl || '';
        $.get(`${baseUrl}/patient-procedures/${procedureId}/team`, function (response) {
            if (response && response.team) {
                renderTeamList(response.team);
            } else {
                $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-muted">No team members assigned yet</td></tr>');
            }
        }).fail(function () {
            $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-danger">Failed to load team members</td></tr>');
        });
    }

    function renderTeamList(team) {
        if (!team || team.length === 0) {
            $('#procedure_team_list').html('<tr><td colspan="5" class="text-center text-muted">No team members assigned yet</td></tr>');
            return;
        }

        let html = '';
        team.forEach(function (member) {
            const roleDisplay = member.role ? member.role.replace(/_/g, ' ').toUpperCase() : 'Staff';
            const leadBadge = member.is_lead ? '<span class="badge bg-success ms-1">Lead</span>' : '';
            const staffName = member.user ? (member.user.surname + ' ' + member.user.firstname + (member.user.othername ? ' ' + member.user.othername : '')) : 'Unknown';

            html += `<tr>
                <td><strong>${staffName}</strong></td>
                <td><span class="badge bg-info text-dark">${roleDisplay}</span> ${leadBadge}</td>
                <td><small class="text-muted">${member.notes || '—'}</small></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.EncounterProcedures.removeTeamMember(${member.id})">
                        <i class="fa fa-times"></i>
                    </button>
                </td>
            </tr>`;
        });
        $('#procedure_team_list').html(html);
    }

    function addTeamMember() {
        if (!currentProcedureId) return;
        const config = getEncounterConfig();
        const baseUrl = config.baseUrl || '';
        const csrfToken = config.csrf || $('meta[name="csrf-token"]').attr('content');

        const userId = $('#team_member_user').val();
        const role = $('#team_member_role').val();
        const customRole = $('#team_member_custom_role').val();
        const isLead = $('#team_member_is_lead').is(':checked') ? 1 : 0;
        const notes = $('#team_member_notes').val();

        if (!userId) {
            if (typeof toastr !== 'undefined') toastr.warning('Please select a staff member');
            return;
        }

        $.ajax({
            url: `${baseUrl}/patient-procedures/${currentProcedureId}/team`,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                user_id: userId,
                role: role,
                custom_role: customRole,
                is_lead: isLead,
                notes: notes
            },
            success: function (res) {
                if (res.success) {
                    $('#team_member_user').val('');
                    $('#team_member_notes').val('');
                    $('#team_member_is_lead').prop('checked', false);
                    loadTeamMembers(currentProcedureId);
                } else {
                    if (typeof toastr !== 'undefined') toastr.error(res.message || 'Failed to add team member');
                }
            },
            error: function (xhr) {
                if (typeof toastr !== 'undefined') toastr.error(xhr.responseJSON?.message || 'Error adding team member');
            }
        });
    }

    function removeTeamMember(memberId) {
        if (!currentProcedureId || !memberId) return;
        const config = getEncounterConfig();
        const baseUrl = config.baseUrl || '';
        const csrfToken = config.csrf || $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            url: `${baseUrl}/patient-procedures/${currentProcedureId}/team/${memberId}`,
            type: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function (res) {
                if (res.success) {
                    loadTeamMembers(currentProcedureId);
                }
            }
        });
    }

    function openNotesModal(procedureId) {
        if (!procedureId) return;
        currentProcedureId = procedureId;
        $('#procedure_notes_list').html('<tr><td class="text-center py-3"><i class="fa fa-spinner fa-spin"></i> Loading notes...</td></tr>');
        $('#procedureNotesModal').modal('show');
        loadProcedureNotes(procedureId);
    }

    function loadProcedureNotes(procedureId) {
        const baseUrl = getEncounterConfig().baseUrl || '';
        $.get(`${baseUrl}/patient-procedures/${procedureId}/notes`, function (response) {
            if (response && response.notes) {
                renderNotesList(response.notes);
            } else {
                $('#procedure_notes_list').html('<tr><td class="text-center text-muted py-3">No notes added yet</td></tr>');
            }
        }).fail(function () {
            $('#procedure_notes_list').html('<tr><td class="text-center text-danger py-3">Failed to load notes</td></tr>');
        });
    }

    function renderNotesList(notes) {
        if (!notes || notes.length === 0) {
            $('#procedure_notes_list').html('<tr><td class="text-center text-muted py-3">No notes added yet</td></tr>');
            return;
        }

        let html = '';
        notes.forEach(function (note) {
            const author = note.creator ? (note.creator.surname + ' ' + note.creator.firstname) : 'Staff';
            const dateStr = note.created_at ? new Date(note.created_at).toLocaleString() : '';
            html += `<tr>
                <td class="p-2 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong><span class="badge bg-secondary">${note.note_type ? note.note_type.toUpperCase() : 'NOTE'}</span> ${note.title || 'Untitled'}</strong>
                        <small class="text-muted"><i class="fa fa-user me-1"></i> ${author} | ${dateStr}</small>
                    </div>
                    <div class="p-2 bg-light rounded">${note.content || ''}</div>
                </td>
            </tr>`;
        });
        $('#procedure_notes_list').html(html);
    }

    function addProcedureNote() {
        if (!currentProcedureId) return;
        const config = getEncounterConfig();
        const baseUrl = config.baseUrl || '';
        const csrfToken = config.csrf || $('meta[name="csrf-token"]').attr('content');

        const noteType = $('#proc_note_type').val() || 'general';
        const title = $('#proc_note_title').val() || '';
        const content = $('#proc_note_content').val() || '';

        if (!content.trim()) {
            if (typeof toastr !== 'undefined') toastr.warning('Please enter note content');
            return;
        }

        $.ajax({
            url: `${baseUrl}/patient-procedures/${currentProcedureId}/notes`,
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                note_type: noteType,
                title: title,
                content: content
            },
            success: function (res) {
                if (res.success) {
                    $('#proc_note_title').val('');
                    $('#proc_note_content').val('');
                    loadProcedureNotes(currentProcedureId);
                }
            }
        });
    }

    function printProcedure(procedureId) {
        const baseUrl = getEncounterConfig().baseUrl || '';
        window.open(`${baseUrl}/patient-procedures/${procedureId}/print`, '_blank', 'width=800,height=600');
    }

    // Global namespace export
    window.EncounterProcedures = {
        selectProcedureForBooking: selectProcedureForBooking,
        cancelProcedureBookingConfig: cancelProcedureBookingConfig,
        setProcPreset: setProcPreset,
        addConfiguredProcedure: addConfiguredProcedure,
        removeProcedure: removeProcedure,
        openTeamModal: openTeamModal,
        addTeamMember: addTeamMember,
        removeTeamMember: removeTeamMember,
        openNotesModal: openNotesModal,
        addProcedureNote: addProcedureNote,
        printProcedure: printProcedure,
        initProcedureHistoryTable: initProcedureHistoryTable
    };

    // Backward compatibility aliases for global handlers
    window.addProcedure = selectProcedureForBooking;
    window.removeProcedure = removeProcedure;
    window.openTeamModal = openTeamModal;
    window.addTeamMember = addTeamMember;
    window.removeTeamMember = removeTeamMember;
    window.openNotesModal = openNotesModal;
    window.addProcedureNote = addProcedureNote;
    window.printProcedure = printProcedure;

})(jQuery);
