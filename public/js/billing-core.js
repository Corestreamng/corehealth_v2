    // Global state
    let currentPatient = null;
    let currentPatientData = null; // Store full patient data including allergies
    let queueRefreshInterval = null;
    let vitalTooltip = null;

    $(document).ready(function() {
        // Initialize
        loadQueueCounts();
        startQueueRefresh();
        initializeEventListeners();
        loadUserPreferences();
        createVitalTooltip();
        loadBanks(); // Load available banks for payment
        loadStaffList(); // Load active staff members for billing
        loadOrganizationList(); // Load active organizations for billing

        // Initialize Billing Shift Manager
        BillingShiftManager.init();

        // Auto-select patient from URL query parameter (e.g., from Patient list workbench button)
        const urlParams = new URLSearchParams(window.location.search);
        const patientId = urlParams.get('patient_id');
        if (patientId) {
            loadPatient(patientId);
        }

        // Auto-open queue from URL parameter (e.g., from dashboard queue widget click)
        const queueFilter = urlParams.get('queue_filter');
        if (queueFilter && ['all', 'hmo', 'credit'].includes(queueFilter)) {
            setTimeout(function() {
                showQueue(queueFilter);
            }, 500);
        }
    });

    function initializeEventListeners() {
        // Generate initial reference number
        generateReferenceNumber();

        // Patient search (shared module)
        PatientSearch.init();

        // Workspace tabs
        $('.workspace-tab').on('click', function() {
            const tab = $(this).data('tab');
            switchWorkspaceTab(tab);
        });

        // Pending sub-tabs
        $('.pending-subtab').on('click', function() {
            const status = $(this).data('status');
            $('.pending-subtab').removeClass('active');
            $(this).addClass('active');
            renderPendingSubtabContent(status);
        });

        // Navigation buttons
        $('#btn-back-to-search').on('click', function() {
            // Mobile: go back to search pane
            $('#main-workspace').removeClass('active');
            $('#left-panel').removeClass('hidden');
        });

        $('#btn-view-work-pane').on('click', function() {
            // Mobile: switch to work pane without selecting a patient
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        });

        $('#btn-toggle-search').on('click', function() {
            // Desktop/Tablet: toggle search pane visibility
            $('#left-panel').toggleClass('hidden');
        });

        // Queue filter buttons
        $('.queue-item').on('click', function() {
            const filter = $(this).data('filter');
            showQueue(filter);
        });

        // Show all queue button
        $('#show-all-queue-btn, #view-queue-btn').on('click', function() {
            showQueue('all');
        });

        // Close queue button
        $('#btn-close-queue').on('click', function() {
            hideQueue();
        });

        // Refresh billing items button
        $('#refresh-billing-items').on('click', function() {
            if (currentPatient) {
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Refreshing...');

                loadPatient(currentPatient);

                // Re-enable button after a short delay
                setTimeout(() => {
                    $btn.prop('disabled', false).html(originalHtml);
                }, 1000);
            }
        });

        // Payment method change handler is now in payment_scripts.blade.php

        // Filter receipts
        $('#filter-receipts').on('click', function() {
            if (currentPatient) {
                filterReceipts();
            }
        });

        // Export receipts
        $('#export-receipts').on('click', function() {
            if (currentPatient) {
                exportReceipts();
            }
        });

        // Refresh receipts
        $('#refresh-receipts').on('click', function() {
            if (currentPatient) {
                setDefaultReceiptDates();
                filterReceipts();
            }
        });

        // Select all receipts checkbox
        $(document).on('change', '#select-all-receipts', function() {
            $('.receipt-checkbox').prop('checked', $(this).is(':checked'));
            updatePrintSelectedButton();
        });

        // Individual receipt checkbox change
        $(document).on('change', '.receipt-checkbox', function() {
            updatePrintSelectedButton();
        });

        // Reprint individual receipt
        $(document).on('click', '.reprint-receipt', function() {
            const paymentId = $(this).data('id');
            if (paymentId) {
                reprintReceipt([paymentId]);
            }
        });

        // Print individual deposit receipt
        $(document).on('click', '.reprint-deposit-receipt', function() {
            const depositId = $(this).data('deposit-id');
            if (depositId) {
                printDepositReceiptFromList(depositId);
            }
        });

        // Create account button
        $(document).on('click', '#create-account-btn', function() {
            createPatientAccount();
        });

        // View services rendered
        $(document).on('click', '#view-services-rendered', function() {
            if (currentPatient) {
                window.open(`/patient-services-rendered/${currentPatient}`, '_blank');
            }
        });

        // Print and receipt tab logic moved to payment_scripts.blade.php
    }

    // loadBanks and loadStaffList moved to payment_scripts.blade.php

    // generateReferenceNumber moved to payment_scripts.blade.php

    function loadPatient(patientId) {
        console.log('loadPatient called with ID:', patientId);
        currentPatient = patientId;

        // Hide all views to prevent stacking
        hideAllViews();

        // Show patient workspace
        $('#workspace-content').show().addClass('active');
        $('#patient-header').addClass('active');

        // Show loading indicator
        $('#patient-name').html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');
        $('#patient-meta').html('');
        $('#billing-items-tbody').html(`
        <tr>
            <td colspan="8" class="text-center text-muted py-5">
                <i class="mdi mdi-loading mdi-spin" style="font-size: 3rem;"></i>
                <p>Loading billing items...</p>
            </td>
        </tr>
    `);

        // Mobile: Switch to work pane
        $('#left-panel').addClass('hidden');
        $('#main-workspace').addClass('active');

        // Load patient billing data
        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${patientId}/billing-data`),
            method: 'GET',
            success: function(data) {
                console.log('Patient billing data loaded:', data);
                currentPatientData = data.patient;
                displayPatientInfo(data.patient);

                // Initialize Clinical Alerts
                try {
                    $('#btn-manage-alerts').show();
                    if (typeof ClinicalAlerts !== 'undefined') {
                        ClinicalAlerts.init(patientId, 'billing');
                    }
                } catch (e) {
                    console.error('ClinicalAlerts init error:', e);
                }

                // Load billing items for the active Billing tab
                renderBillingItems(data.items);
                updateBillingBadge(data.items.length);

                // Build Family Tabs AFTER items are rendered (so applyBillingFilters runs after tabs are built)
                buildFamilyTabs(data.patient, data.items);

                // Load account balance
                loadAccountBalance(patientId);

                // Switch to billing tab by default
                switchWorkspaceTab('billing');
            },
            error: function(xhr) {
                console.error('Error loading patient:', xhr);
                toastr.error('Failed to load patient data');
            }
        });
    }

    function displayPatientInfo(patient) {
        $('#patient-name').text(patient.name);

        const metaHtml = `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details"></i>
            <span>File: ${patient.file_no}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-calendar"></i>
            <span>Age: ${patient.age}</span>
        </div>
        <div class="patient-meta-item">
            <i class="mdi mdi-gender-${patient.gender === 'Male' ? 'male' : 'female'}"></i>
            <span>${patient.gender}</span>
        </div>
        ${patient.hmo_name ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-hospital-building"></i>
            <span>${patient.hmo_name}</span>
        </div>
        ` : ''}
        ${patient.hmo_no ? `
        <div class="patient-meta-item">
            <i class="mdi mdi-card-account-details-outline"></i>
            <span>HMO No: ${patient.hmo_no}</span>
        </div>
        ` : ''}
    `;

        $('#patient-meta').html(metaHtml);
    }

    function initializeHistoryDataTable(patientId) {
        if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
            $('#investigation_history_list').DataTable().destroy();
        }

        $('#investigation_history_list').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            ajax: {
                url: wbUrl(`/investigationHistoryList/${patientId}`),
                type: 'GET'
            },
            columns: [{
                data: "info",
                name: "info",
                orderable: false,
                searchable: true
            }],
            order: [
                [0, 'desc']
            ],
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],
            language: {
                emptyTable: "No investigation history found for this patient",
                processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
            },
            drawCallback: function() {
                // Add click handler for view result buttons
                $('.view-invest-result-btn').off('click').on('click', function() {
                    const requestId = $(this).data('request-id');
                    viewInvestigationResult(requestId);
                });
            }
        });
    }

    function viewInvestigationResult(requestId) {
        // Open modal to view completed result
        $.ajax({
            url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}`),
            method: 'GET',
            success: function(request) {
                // Show result in a view-only modal or open in new tab
                if (request.result_document) {
                    window.open(request.result_document, '_blank');
                } else {
                    alert('No result document found');
                }
            },
            error: function(xhr) {
                alert('Error loading result: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }

    let currentPendingRequests = null;
    let currentPendingFilter = 'all';

    // ── Selection Preservation State (pharmacy-style) ────────────────────
    let checkedItemsState = {
        billing: new Set(),
        sample: new Set()
    };

    function saveCheckedItemsState(section) {
        checkedItemsState[section] = new Set();
        $(`.request-checkbox[data-section="${section}"]:checked`).each(function() {
            checkedItemsState[section].add($(this).data('request-id'));
        });
    }

    function restoreCheckedItemsState() {
        Object.keys(checkedItemsState).forEach(section => {
            if (checkedItemsState[section].size === 0) return;

            checkedItemsState[section].forEach(id => {
                const $cb = $(`.request-checkbox[data-section="${section}"][data-request-id="${id}"]`);
                if ($cb.length) {
                    $cb.prop('checked', true);
                } else {
                    // Item no longer in DOM — remove from state
                    checkedItemsState[section].delete(id);
                }
            });

            // Sync select-all checkbox
            const total = $(`.request-checkbox[data-section="${section}"]`).length;
            const checked = $(`.request-checkbox[data-section="${section}"]:checked`).length;
            $(`#select-all-${section}`).prop('checked', total > 0 && checked === total);

            // Re-enable action buttons if items are checked
            if (checked > 0) {
                $(`#btn-record-${section}, #btn-collect-${section}, #btn-dismiss-${section}`).prop('disabled', false);
            }
        });
    }

    function clearCheckedItems(section) {
        if (section) {
            checkedItemsState[section] = new Set();
        } else {
            Object.keys(checkedItemsState).forEach(k => checkedItemsState[k] = new Set());
        }
    }

    function hasActiveSelections() {
        return Object.values(checkedItemsState).some(s => s.size > 0);
    }
    // ── End Selection Preservation State ─────────────────────────────────

    function displayPendingRequests(requests) {
        currentPendingRequests = requests;
        const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
        $('#pending-badge').text(totalPending);

        updatePendingSubtabBadges(requests);
        renderPendingSubtabContent(currentPendingFilter);
    }

    function updatePendingSubtabBadges(requests) {
        const totalPending = requests.billing.length + requests.sample.length + requests.results.length;
        $('#all-pending-badge').text(totalPending);
        $('#billing-subtab-badge').text(requests.billing.length);
        $('#sample-subtab-badge').text(requests.sample.length);
        $('#results-subtab-badge').text(requests.results.length);
    }

    function renderPendingSubtabContent(filter) {
        if (!currentPendingRequests) return;

        currentPendingFilter = filter;
        const requests = currentPendingRequests;
        const totalPending = requests.billing.length + requests.sample.length + requests.results.length;

        const $container = $('#pending-subtab-container');
        $container.empty();

        if (totalPending === 0) {
            $container.html('<div class="alert alert-info">No pending lab requests for this patient</div>');
            return;
        }

        // Billing Section (Status 1)
        if ((filter === 'all' || filter === 'billing') && requests.billing.length > 0) {
            const billingHtml = `
            <div class="request-section" data-section="billing">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-cash-register"></i>
                        Awaiting Billing (${requests.billing.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="billing-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-billing" class="select-all-checkbox">
                        <label for="select-all-billing">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-billing" id="btn-record-billing" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Record Billing
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-billing" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
            $container.append(billingHtml);

            requests.billing.forEach(request => {
                $('#billing-cards').append(createRequestCard(request, 'billing'));
            });
        }

        // Sample Section (Status 2)
        if ((filter === 'all' || filter === 'sample') && requests.sample.length > 0) {
            const sampleHtml = `
            <div class="request-section" data-section="sample">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-test-tube"></i>
                        Sample Collection (${requests.sample.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="sample-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <input type="checkbox" id="select-all-sample" class="select-all-checkbox">
                        <label for="select-all-sample">Select All</label>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-sample" id="btn-collect-sample" disabled>
                            <i class="mdi mdi-check-circle"></i>
                            Collect Sample
                        </button>
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-sample" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        `;
            $container.append(sampleHtml);

            requests.sample.forEach(request => {
                $('#sample-cards').append(createRequestCard(request, 'sample'));
            });
        }

        // Results Section (Status 3)
        if ((filter === 'all' || filter === 'results') && requests.results.length > 0) {
            const resultsHtml = `
            <div class="request-section" data-section="results">
                <div class="request-section-header">
                    <h5>
                        <i class="mdi mdi-file-document-edit"></i>
                        Result Entry (${requests.results.length})
                    </h5>
                </div>
                <div class="request-cards-container" id="results-cards"></div>
                <div class="section-actions-footer">
                    <div class="select-all-container">
                        <span class="text-muted"><i class="mdi mdi-information"></i> Results must be entered individually</span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn-action btn-action-dismiss" id="btn-dismiss-results" disabled>
                            <i class="mdi mdi-close-circle"></i>
                            Dismiss Selected
                        </button>
                    </div>
                </div>
            </div>
        `;
            $container.append(resultsHtml);

            requests.results.forEach(request => {
                $('#results-cards').append(createRequestCard(request, 'results'));
            });
        }

        // Initialize event handlers + restore preserved selections
        initializeRequestHandlers();
        restoreCheckedItemsState();
    }

    function createRequestCard(request, section) {
        let serviceName = request.service?.service_name || request.service_name || request.name || 'Unknown Service';
        if (request.treatment_plan_id && request.treatment_plan_name) {
            serviceName += ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${request.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(request.treatment_plan_name)}</a>`;
        }
        const doctorName = request.doctor ? (request.doctor.firstname + ' ' + request.doctor.surname) : 'N/A';
        const requestDate = formatDateTime(request.created_at);
        const note = request.note || '';

        const hasNote = note && note.trim() !== '';
        const noteHtml = hasNote ? `<div class="request-note"><i class="mdi mdi-note-text"></i> ${note}</div>` : '';

        // Check delivery status
        const deliveryCheck = request.delivery_check;
        const canDeliver = deliveryCheck ? deliveryCheck.can_deliver : true;

        // Delivery warning message
        let deliveryWarningHtml = '';
        if (!canDeliver && deliveryCheck) {
            deliveryWarningHtml = `
            <div class="alert alert-warning py-2 px-2 mb-2 mt-2" style="font-size: 0.85rem;">
                <i class="fa fa-exclamation-triangle"></i> <strong>${deliveryCheck.reason}</strong><br>
                <small>${deliveryCheck.hint}</small>
            </div>
        `;
        }

        // Results section has individual action button instead of checkbox
        const checkboxOrAction = section === 'results' ? `
        <button class="btn btn-sm btn-primary enter-result-btn" data-request-id="${request.id}" ${!canDeliver ? 'disabled title="' + (deliveryCheck?.reason || 'Cannot deliver service') + '"' : ''}>
            <i class="mdi mdi-file-document-edit"></i>
            Enter Result
        </button>
    ` : `
        <div class="request-card-checkbox">
            <input type="checkbox" class="request-checkbox" data-request-id="${request.id}" data-section="${section}">
        </div>
    `;

        return `
        <div class="request-card">
            ${checkboxOrAction}
            <div class="request-card-content">
                <div class="request-card-header">
                    <div>
                        <div class="request-service-name">${serviceName}</div>
                        <div class="request-card-meta">
                            <div class="request-meta-item">
                                <i class="mdi mdi-doctor"></i>
                                <span>${doctorName}</span>
                            </div>
                            <div class="request-meta-item">
                                <i class="mdi mdi-clock-outline"></i>
                                <span>${requestDate}</span>
                            </div>
                        </div>
                    </div>
                </div>
                ${noteHtml}
                ${deliveryWarningHtml}
            </div>
        </div>
    `;
    }

    function initializeRequestHandlers() {
        // Unbind previous direct handlers to avoid duplicates (cards are rebuilt each render)
        $('.select-all-checkbox').off('change');
        $('.request-checkbox').off('change');
        $('#btn-record-billing').off('click');
        $('#btn-collect-sample').off('click');
        $('.btn-action-dismiss').off('click');
        $('.enter-result-btn').off('click');

        // Select all checkboxes
        $('.select-all-checkbox').on('change', function() {
            const section = $(this).attr('id').replace('select-all-', '');
            const isChecked = $(this).is(':checked');
            $(`.request-checkbox[data-section="${section}"]`).each(function() {
                $(this).prop('checked', isChecked);
                const rid = $(this).data('request-id');
                if (isChecked) {
                    checkedItemsState[section]?.add(rid);
                } else {
                    checkedItemsState[section]?.delete(rid);
                }
            });
            const checkedCount = $(`.request-checkbox[data-section="${section}"]:checked`).length;
            $(`#btn-record-${section}, #btn-collect-${section}, #btn-dismiss-${section}`).prop('disabled', checkedCount === 0);
        });

        // Individual checkboxes — track in Set
        $('.request-checkbox').on('change', function() {
            const section = $(this).data('section');
            const rid = $(this).data('request-id');
            const checkedCount = $(`.request-checkbox[data-section="${section}"]:checked`).length;

            // Track in state Set
            if ($(this).is(':checked')) {
                checkedItemsState[section]?.add(rid);
            } else {
                checkedItemsState[section]?.delete(rid);
            }

            // Enable/disable action buttons
            $(`#btn-record-${section}, #btn-collect-${section}, #btn-dismiss-${section}`).prop('disabled', checkedCount === 0);

            // Update select all checkbox state
            const totalCount = $(`.request-checkbox[data-section="${section}"]`).length;
            $(`#select-all-${section}`).prop('checked', checkedCount === totalCount);
        });

        // Record Billing button
        $('#btn-record-billing').on('click', function() {
            const selectedIds = $('.request-checkbox[data-section="billing"]:checked').map(function() {
                return $(this).data('request-id');
            }).get();

            if (selectedIds.length > 0) {
                recordBilling(selectedIds);
            }
        });

        // Collect Sample button
        $('#btn-collect-sample').on('click', function() {
            const selectedIds = $('.request-checkbox[data-section="sample"]:checked').map(function() {
                return $(this).data('request-id');
            }).get();

            if (selectedIds.length > 0) {
                collectSample(selectedIds);
            }
        });

        // Dismiss buttons
        $('.btn-action-dismiss').on('click', function() {
            const btnId = $(this).attr('id');
            const section = btnId.replace('btn-dismiss-', '');
            const selectedIds = $(`.request-checkbox[data-section="${section}"]:checked`).map(function() {
                return $(this).data('request-id');
            }).get();

            if (selectedIds.length > 0) {
                dismissRequests(selectedIds, section);
            }
        });

        // Enter Result buttons (individual)
        $('.enter-result-btn').on('click', function() {
            const requestId = $(this).data('request-id');
            enterResult(requestId);
        });
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };
        return date.toLocaleDateString('en-US', options);
    }

    function formatDateTime(dateString) {
        const date = new Date(dateString);
        const dateOptions = {
            month: 'short',
            day: 'numeric'
        };
        const timeOptions = {
            hour: '2-digit',
            minute: '2-digit'
        };
        return date.toLocaleDateString('en-US', dateOptions) + ', ' + date.toLocaleTimeString('en-US', timeOptions);
    }

    function setDefaultReceiptDates() {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        $('#receipts-from-date').val(formatDate(firstDay));
        $('#receipts-to-date').val(formatDate(lastDay));
    }

    function setDefaultReceiptDates() {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        $('#receipts-from-date').val(formatDate(firstDay));
        $('#receipts-to-date').val(formatDate(lastDay));
    }

    function loadQueueCounts() {
        $.get(wbRoute('billing.queue-counts', '/billing/queue-counts'), function(counts) {
            $('#queue-all-count').text(counts.total || 0);
            $('#queue-hmo-count').text(counts.hmo || 0);
            $('#queue-credit-count').text(counts.credit || 0);
            var emergencyCount = counts.emergency || 0;
            $('#queue-emergency-count').text(emergencyCount);
            if (emergencyCount > 0) {
                $('#queue-emergency-count').closest('.queue-item').addClass('emergency-pulse');
            } else {
                $('#queue-emergency-count').closest('.queue-item').removeClass('emergency-pulse');
            }
            updateSyncIndicator();
        });
    }

    function startQueueRefresh() {
        queueRefreshInterval = setInterval(function() {
            loadQueueCounts();

            // Also refresh current patient data if a patient is selected
            if (currentPatient) {
                refreshCurrentPatientData();
            }

            // Refresh queue DataTable if queue view is active
            if ($('#queue-view').hasClass('active') && queueDataTable) {
                queueDataTable.ajax.reload(null, false);
            }
        }, 30000); // 30 seconds
    }

    function refreshCurrentPatientData() {
        if (!currentPatient) return;

        // Skip auto-refresh if user has active checkbox selections (pharmacy-style guard)
        if (hasActiveSelections()) {
            console.log('Skipping auto-refresh: active selections detected');
            return;
        }

        // Silently reload patient requests
        $.get(`/lab-workbench/patient/${currentPatient}/requests`, function(data) {
            displayPendingRequests(data.requests);
            updatePendingSubtabBadges(data.requests);
        }).fail(function() {
            console.error('Failed to refresh patient data');
        });
    }

    let lastSyncTimestamp = null;
    let syncTimeUpdateInterval = null;

    function updateSyncIndicator() {
        lastSyncTimestamp = Date.now();
        updateSyncTimeDisplay();

        // Start interval to update relative time every 10 seconds
        if (syncTimeUpdateInterval) {
            clearInterval(syncTimeUpdateInterval);
        }
        syncTimeUpdateInterval = setInterval(updateSyncTimeDisplay, 10000);
    }

    function updateSyncTimeDisplay() {
        if (!lastSyncTimestamp) {
            $('#last-sync-time').text('Just now');
            return;
        }

        const secondsAgo = Math.floor((Date.now() - lastSyncTimestamp) / 1000);

        if (secondsAgo < 10) {
            $('#last-sync-time').text('Just now');
        } else if (secondsAgo < 60) {
            $('#last-sync-time').text(secondsAgo + 's ago');
        } else {
            const minutesAgo = Math.floor(secondsAgo / 60);
            $('#last-sync-time').text(minutesAgo + 'm ago');
        }
    }

    function switchWorkspaceTab(tab) {
        // Only clear the main workspace nav tabs (those with data-tab), not the family tabs
        $('.workspace-tab[data-tab]').removeClass('active');
        $(`.workspace-tab[data-tab="${tab}"]`).addClass('active');

        $('.workspace-tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');

        // Load tab-specific data
        if (!currentPatient) return;

        switch (tab) {
            case 'billing':
                loadBillingItems();
                break;
            case 'receipts':
                setDefaultReceiptDates();
                loadPatientReceipts();
                break;
            case 'admissions':
                loadAdmissionHistory();
                break;
            case 'account':
                loadAccountSummary();
                break;
        }
    }

    function loadUserPreferences() {
        const clinicalVisible = localStorage.getItem('clinicalPanelVisible') === 'true';
        if (clinicalVisible) {
            $('#right-panel').addClass('active');
            $('#toggle-clinical-btn').html('📊 Clinical Context ×');
        }
    }

    // ========== ACCOUNT BALANCE FUNCTIONS ==========

    let currentAccountBalance = 0;

    function loadAccountBalance(patientId) {
        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${patientId}/account-summary`),
            method: 'GET',
            success: function(data) {
                currentAccountBalance = parseFloat(data.balance) || 0;
                updateAccountBalanceDisplays(data);
            },
            error: function(xhr) {
                console.error('Failed to load account balance', xhr);
            }
        });
    }

    function updateAccountBalanceDisplays(accountData) {
        const balance = parseFloat(accountData.balance) || 0;
        const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

        // Update patient header balance
        $('#header-balance-amount').text(formattedBalance);
        $('#patient-header-balance').show();

        // Update billing tab balance
        $('#billing-balance-amount').text(formattedBalance);

        // Always show billing account balance section and account payment option
        // (Credit facility: allow payments even with zero or negative balance)
        $('#billing-account-balance').show();
        $('#account-payment-option').show();

        // Add visual indicator for negative balance
        if (balance < 0) {
            $('#billing-balance-amount').addClass('text-danger').removeClass('text-success');
            $('#header-balance-amount').addClass('text-danger').removeClass('text-success');
        } else if (balance > 0) {
            $('#billing-balance-amount').addClass('text-success').removeClass('text-danger');
            $('#header-balance-amount').addClass('text-success').removeClass('text-danger');
        } else {
            $('#billing-balance-amount').removeClass('text-success text-danger');
            $('#header-balance-amount').removeClass('text-success text-danger');
        }

        // Update account tab with new modern UI
        if (accountData.account) {
            displayAccountInfo(accountData.account, accountData.unpaid_total);
            // Initialize filters to current month before loading transactions
            initAccountTxFilters();
            loadAccountTransactions();
        } else {
            showNoAccountState();
        }
    }

    function displayAccountInfo(account, pendingBills) {
        const balance = parseFloat(account.balance) || 0;
        const formattedBalance = `₦${Math.abs(balance).toLocaleString()}`;

        // Update hero balance section
        const heroBalance = $('#account-hero-balance');
        heroBalance.removeClass('credit debit');

        $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

        if (balance > 0) {
            heroBalance.addClass('credit');
            $('#hero-balance-status').text('Credit Balance');
        } else if (balance < 0) {
            heroBalance.addClass('debit');
            $('#hero-balance-status').text(`Debit Balance`);
        } else {
            $('#hero-balance-status').text('Balanced');
        }

        // Update pending bills stat
        $('#pending-bills-stat').text(`₦${parseFloat(pendingBills || 0).toLocaleString()}`);

        // Show account UI, hide no-account state
        $('#account-hero-section').show();
        $('#account-transactions-section').show();
        $('#no-account-state').hide();
        $('#account-transaction-panel').hide();
    }

    function showNoAccountState() {
        $('#account-hero-section').hide();
        $('#account-transactions-section').hide();
        $('#no-account-state').show();
    }

    function loadAccountTransactions() {
        if (!currentPatient) return;

        const fromDate = $('#account-tx-from-date').val() || '';
        const toDate = $('#account-tx-to-date').val() || '';
        const txType = $('#account-tx-type-filter').val() || '';

        // Load account-specific transaction history
        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/account-transactions`),
            method: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                tx_type: txType
            },
            success: function(response) {
                console.log('Account transactions response:', response);
                const transactions = response.transactions || [];
                const summary = response.summary || {};
                renderAccountTransactions(transactions);
                updateAccountStats(summary);
            },
            error: function(xhr) {
                console.error('Failed to load account transactions', xhr);
                $('#transaction-timeline').html(`
                <div class="timeline-empty-state">
                    <i class="mdi mdi-alert-circle"></i>
                    <p>Failed to load transactions</p>
                    <small>Please try refreshing</small>
                </div>
            `);
            }
        });
    }

    function updateAccountStats(summary) {
        $('#total-deposits-stat').text(`₦${parseFloat(summary.total_deposits || 0).toLocaleString()}`);
        $('#total-withdrawals-stat').text(`₦${parseFloat(summary.total_withdrawals || 0).toLocaleString()}`);
        $('#tx-count-stat').text(summary.transaction_count || 0);
    }

    function renderAccountTransactions(transactions) {
        console.log('renderAccountTransactions called with:', transactions);
        const timeline = $('#transaction-timeline');
        console.log('Timeline element found:', timeline.length > 0);
        timeline.empty();

        if (!transactions || transactions.length === 0) {
            console.log('No transactions to render');
            timeline.html(`
            <div class="timeline-empty-state">
                <i class="mdi mdi-swap-horizontal"></i>
                <p>No account transactions yet</p>
                <small>Deposits and withdrawals will appear here</small>
            </div>
        `);
            return;
        }

        console.log('Rendering', transactions.length, 'transactions');
        transactions.forEach((tx, index) => {
            const amountClass = parseFloat(tx.amount) >= 0 ? 'positive' : 'negative';
            const amountPrefix = parseFloat(tx.amount) >= 0 ? '+' : '';

            const item = `
            <div class="timeline-item">
                <div class="timeline-icon ${tx.tx_color}">
                    <i class="mdi ${tx.tx_icon}"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="timeline-type">${tx.tx_type}</span>
                        <span class="timeline-amount ${amountClass}">${amountPrefix}₦${Math.abs(parseFloat(tx.amount)).toLocaleString()}</span>
                    </div>
                    <div class="timeline-meta">
                        <span><i class="mdi mdi-calendar"></i> ${tx.created_at}</span>
                        <span><i class="mdi mdi-clock"></i> ${tx.created_time}</span>
                        <span><i class="mdi mdi-account"></i> ${tx.cashier}</span>
                    </div>
                    ${tx.description ? `<div class="timeline-description">${tx.description}</div>` : ''}
                    <span class="timeline-balance">Balance after: ₦${parseFloat(tx.running_balance).toLocaleString()}</span>
                </div>
            </div>
        `;
            console.log('Appending item', index, 'to timeline');
            timeline.append(item);
        });
        console.log('Timeline HTML after render:', timeline.html().substring(0, 200));
    }

    // Account Tab Event Handlers - Transaction Panel
    let currentTransactionType = 'deposit';

    function openTransactionPanel(type) {
        currentTransactionType = type;
        const panel = $('#account-transaction-panel');
        const icon = $('#transaction-panel-icon');
        const title = $('#transaction-panel-title');
        const submitBtn = $('#transaction-submit-btn');
        const submitText = $('#transaction-submit-text');
        const amountHelp = $('#transaction-amount-help');
        const changeLabel = $('#preview-change-label');

        // Reset form
        $('#account-transaction-form')[0].reset();
        $('#transaction-type').val(type);

        // Update panel styling based on type
        panel.removeClass('deposit withdraw adjust');
        panel.addClass(type);

        // Get current balance for preview
        const balanceText = $('#hero-balance-amount').text().replace('₦', '').replace(/,/g, '');
        currentAccountBalance = parseFloat(balanceText) || 0;
        $('#preview-current-balance').text(`₦${currentAccountBalance.toLocaleString()}`);
        updateBalancePreview();

        // Show/hide payment method based on transaction type
        if (type === 'adjust') {
            // Hide payment method for adjustments
            $('#transaction-payment-method-group').hide();
            $('#transaction-bank-group').hide();
        } else {
            // Show payment method for deposits and withdrawals
            $('#transaction-payment-method-group').show();
            // Reset bank visibility based on current payment method
            const payMethod = $('#transaction-payment-method').val();
            if (['POS', 'TRANSFER', 'MOBILE'].includes(payMethod)) {
                $('#transaction-bank-group').show();
            } else {
                $('#transaction-bank-group').hide();
            }
        }

        if (type === 'deposit') {
            icon.attr('class', 'mdi mdi-plus-circle');
            title.text('Make Deposit');
            submitText.text('Confirm Deposit');
            amountHelp.text('Enter amount to add to account');
            changeLabel.text('After Deposit:');
            $('#transaction-description').removeAttr('required');
            $('#transaction-amount').attr('min', '0.01');
        } else if (type === 'withdraw') {
            icon.attr('class', 'mdi mdi-minus-circle');
            title.text('Make Withdrawal');
            submitText.text('Confirm Withdrawal');
            amountHelp.text('Enter amount to withdraw from account');
            changeLabel.text('After Withdrawal:');
            $('#transaction-description').removeAttr('required');
            $('#transaction-amount').attr('min', '0.01');
        } else if (type === 'adjust') {
            icon.attr('class', 'mdi mdi-swap-horizontal');
            title.text('Account Adjustment');
            submitText.text('Confirm Adjustment');
            amountHelp.text('Enter positive to credit, negative to debit');
            changeLabel.text('After Adjustment:');
            $('#transaction-description').attr('required', 'required');
            $('#transaction-amount').removeAttr('min');
        }

        panel.slideDown();
        $('#transaction-amount').focus();
    }

    // Transaction payment method change handler
    $(document).on('change', '#transaction-payment-method', function() {
        const method = $(this).val();
        if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
            $('#transaction-bank-group').show();
        } else {
            $('#transaction-bank-group').hide();
            $('#transaction-bank').val('');
        }
    });

    function updateBalancePreview() {
        const amount = parseFloat($('#transaction-amount').val()) || 0;
        let newBalance = currentAccountBalance;

        if (currentTransactionType === 'deposit') {
            newBalance = currentAccountBalance + amount;
        } else if (currentTransactionType === 'withdraw') {
            newBalance = currentAccountBalance - amount;
        } else if (currentTransactionType === 'adjust') {
            newBalance = currentAccountBalance + amount; // Adjustment can be +/-
        }

        const previewElement = $('#preview-new-balance');
        previewElement.text(`₦${newBalance.toLocaleString()}`);
        previewElement.removeClass('positive negative');

        if (newBalance > 0) {
            previewElement.addClass('positive');
        } else if (newBalance < 0) {
            previewElement.addClass('negative');
        }
    }

    $(document).on('click', '#quick-deposit-btn', function() {
        openTransactionPanel('deposit');
    });

    $(document).on('click', '#quick-withdraw-btn', function() {
        openTransactionPanel('withdraw');
    });

    $(document).on('click', '#quick-adjust-btn', function() {
        openTransactionPanel('adjust');
    });

    $(document).on('click', '#close-transaction-panel', function() {
        $('#account-transaction-panel').slideUp();
    });

    $(document).on('input', '#transaction-amount', function() {
        updateBalancePreview();
    });

    $(document).on('submit', '#account-transaction-form', function(e) {
        e.preventDefault();
        processAccountTransaction();
    });

    function processAccountTransaction() {
        if (!currentPatientData) return;

        const type = $('#transaction-type').val();
        const amountInput = $('#transaction-amount').val();
        const amount = parseFloat(amountInput);
        const description = $('#transaction-description').val();
        const paymentMethod = $('#transaction-payment-method').val();
        const bankId = $('#transaction-bank').val();

        console.log('Processing transaction:', {
            type,
            amountInput,
            amount,
            description,
            paymentMethod,
            bankId
        });

        // For adjustments, allow any non-zero value (positive or negative)
        // For deposit/withdraw, require positive values
        if (type === 'adjust') {
            if (isNaN(amount) || amount === 0) {
                toastr.warning('Please enter a valid non-zero amount (positive to credit, negative to debit)');
                return;
            }
        } else {
            if (isNaN(amount) || amount <= 0) {
                toastr.warning('Please enter a valid positive amount');
                return;
            }
        }

        if (type === 'adjust' && !description) {
            toastr.warning('Description is required for adjustments');
            return;
        }

        // Validate bank selection for non-cash payments (except adjustments)
        if (type !== 'adjust' && ['POS', 'TRANSFER', 'MOBILE'].includes(paymentMethod) && !bankId) {
            toastr.warning('Please select a bank for this payment method');
            return;
        }

        // Check if withdraw amount exceeds balance
        if (type === 'withdraw' && amount > currentAccountBalance) {
            if (!confirm(`Warning: This withdrawal (₦${amount.toLocaleString()}) exceeds the current balance (₦${currentAccountBalance.toLocaleString()}). Continue anyway?`)) {
                return;
            }
        }

        let confirmMsg = '';
        if (type === 'deposit') {
            confirmMsg = `Deposit ₦${amount.toLocaleString()} to this account?`;
        } else if (type === 'withdraw') {
            confirmMsg = `Withdraw ₦${amount.toLocaleString()} from this account?`;
        } else {
            confirmMsg = `Apply adjustment of ₦${amount.toLocaleString()} to this account?`;
        }

        if (!confirm(confirmMsg)) return;

        $.ajax({
            url: wbUrl('/billing-workbench/account-transaction'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                patient_id: currentPatientData.id,
                transaction_type: type,
                amount: amount,
                description: description,
                payment_method: type !== 'adjust' ? paymentMethod : null,
                bank_id: (type !== 'adjust' && bankId) ? bankId : null
            },
            success: function(response) {
                toastr.success(response.message || 'Transaction saved successfully!');

                // Close panel and reset form
                $('#account-transaction-panel').slideUp();
                $('#account-transaction-form')[0].reset();

                // Refresh all account data
                loadAccountBalance(currentPatient);
                loadAccountSummary();
                loadAccountTransactions();

                // If on receipts tab, refresh receipts
                if ($('#receipts-tab').hasClass('active')) {
                    loadPatientReceipts();
                }

                // Show deposit receipt if available
                if (type === 'deposit' && response.receipt_a4 && response.receipt_thermal) {
                    $('#modal-receipt-a4').html(response.receipt_a4);
                    $('#modal-receipt-thermal').html(response.receipt_thermal);

                    // Reset tabs to A4
                    $('.receipt-modal-tab').removeClass('active');
                    $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                    $('#modal-receipt-a4').show();
                    $('#modal-receipt-thermal').hide();

                    // Show modal
                    $('#receiptPreviewModal').modal('show');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to save transaction');
            }
        });
    }

    $(document).on('click', '#filter-account-tx', function() {
        loadAccountTransactions();
    });

    $(document).on('click', '#refresh-account-data', function() {
        loadAccountSummary();
        loadAccountTransactions();
        toastr.info('Refreshing account data...');
    });

    // Set default dates for account transactions filter
    function initAccountTxFilters() {
        const today = new Date().toISOString().split('T')[0];
        const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
        $('#account-tx-from-date').val(firstDay);
        $('#account-tx-to-date').val(today);
    }

    // Initialize when account tab is shown
    $(document).on('shown.bs.tab', 'a[href="#account-tab"]', function() {
        initAccountTxFilters();
        if (currentPatient) {
            loadAccountTransactions();
        }
    });

    // Also call on workspace tab click
    $(document).on('click', '.workspace-tab[data-tab="account-tab"]', function() {
        setTimeout(() => {
            initAccountTxFilters();
            if (currentPatient) {
                loadAccountTransactions();
            }
        }, 100);
    });

    function filterReceipts() {
        if (!currentPatient) return;

        const fromDate = $('#receipts-from-date').val() || '';
        const toDate = $('#receipts-to-date').val() || '';
        const paymentType = $('#receipts-payment-type').val() || '';

        const params = {};
        if (fromDate) params.from_date = fromDate;
        if (toDate) params.to_date = toDate;
        if (paymentType) params.payment_type = paymentType;

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/receipts`),
            method: 'GET',
            data: params,
            success: function(data) {
                renderReceipts(data.receipts);
                updateReceiptsStats(data.stats);
            },
            error: function(xhr) {
                toastr.error('Failed to filter receipts');
            }
        });
    }

    function renderReceipts(receipts) {
        const tbody = $('#receipts-tbody');
        tbody.empty();

        if (receipts.length === 0) {
            tbody.html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="mdi mdi-receipt" style="font-size: 3rem;"></i>
                    <p>No receipts found</p>
                </td>
            </tr>
        `);
            return;
        }

        receipts.forEach(receipt => {
            // Handle different possible field names from backend
            const isDeposit = receipt.source === 'deposit';
            const recordId = receipt.id || (isDeposit ? receipt.deposit_id : receipt.payment_id);
            const referenceNo = receipt.reference_no || receipt.reference_number || 'N/A';
            const dateValue = receipt.created_at || receipt.date || receipt.payment_date;
            const itemCount = receipt.item_count || receipt.items_count || 0;
            const total = parseFloat(receipt.total || 0);
            const discount = parseFloat(receipt.total_discount || receipt.discount || 0);
            const paymentType = receipt.payment_type_label || receipt.payment_type || 'N/A';
            const cashier = receipt.created_by || receipt.cashier || 'N/A';

            // Badge color based on type
            let typeBadge = '';
            if (isDeposit) {
                typeBadge = `<span class="badge badge-success">${paymentType}</span>`;
            } else {
                typeBadge = `<span class="badge badge-primary">${paymentType}</span>`;
            }

            // Reprint button with proper data attributes
            const reprintBtn = isDeposit ?
                `<button class="btn btn-sm btn-success reprint-deposit-receipt" data-deposit-id="${receipt.deposit_id}">
                   <i class="mdi mdi-printer"></i> Print
               </button>` :
                `<button class="btn btn-sm btn-primary reprint-receipt" data-id="${receipt.payment_id}">
                   <i class="mdi mdi-printer"></i> Reprint
               </button>`;

            // For deposits, show items as "Deposit" indicator
            const itemsDisplay = isDeposit ?
                `<span class="text-success"><i class="mdi mdi-arrow-down"></i> Deposit</span>` :
                `${itemCount} item(s)`;

            const row = `
            <tr class="${isDeposit ? 'table-success-light' : ''}">
                <td><input type="checkbox" class="receipt-checkbox" data-id="${recordId}" data-source="${receipt.source || 'payment'}"></td>
                <td>${referenceNo}</td>
                <td>${dateValue}</td>
                <td>${itemsDisplay}</td>
                <td>₦${total.toLocaleString()}</td>
                <td>₦${discount.toLocaleString()}</td>
                <td>${typeBadge}</td>
                <td>${cashier}</td>
                <td>${reprintBtn}</td>
            </tr>
        `;
            tbody.append(row);
        });

        // Reset select all checkbox
        $('#select-all-receipts').prop('checked', false);

        // Update print selected button state
        updatePrintSelectedButton();
    }

    function updateReceiptsStats(stats) {
        if (stats) {
            $('#receipts-total-count').text(stats.count || 0);
            $('#receipts-total-amount').text(`₦${parseFloat(stats.total || 0).toLocaleString()}`);
            $('#receipts-total-discounts').text(`₦${parseFloat(stats.discounts || 0).toLocaleString()}`);
            $('#receipts-summary').show();
        }
    }

    function exportReceipts() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }

        const fromDate = $('#receipts-from-date').val() || '';
        const toDate = $('#receipts-to-date').val() || '';
        const paymentType = $('#receipts-payment-type').val() || '';

        const params = new URLSearchParams();
        if (fromDate) params.append('from_date', fromDate);
        if (toDate) params.append('to_date', toDate);
        if (paymentType) params.append('payment_type', paymentType);

        const url = `/billing-workbench/patient/${currentPatient}/receipts/export?${params.toString()}`;
        window.open(url, '_blank');
    }

    function createPatientAccount() {
        if (!currentPatientData) return;

        if (!confirm('Create a new account for this patient?')) return;

        $.ajax({
            url: wbUrl('/billing-workbench/create-account'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                patient_id: currentPatientData.id
            },
            success: function(response) {
                toastr.success(response.message || 'Account created successfully!');

                // Update patient data with new account info
                if (response.account) {
                    currentPatientData.account_id = response.account.id;
                }

                // Reload account balance and all displays
                loadAccountBalance(currentPatient);

                // Show account UI state
                $('#no-account-state').hide();
                $('#account-hero-section').show();
                $('#account-transactions-section').show();

                // Reload account summary to show full data
                loadAccountSummary();

                // Load account transactions
                loadAccountTransactions();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to create account');
            }
        });
    }

    // ========== ADMISSIONS TAB (now uses AdmissionModule partial) ==========

    function loadAdmissionHistory() {
        if (!currentPatient) return;
        AdmissionModule.init(currentPatient, {
            container: '#admissions-tab',
            printTarget: 'billing',
            onBadgeUpdate: function(count) {
                const badge = $('#admissions-badge');
                badge.text(count);
                if (count > 0) badge.show();
                else badge.hide();
            }
        });
    }

