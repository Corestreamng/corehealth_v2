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

    // ========== BILLING WORKBENCH FUNCTIONS ==========

    function loadBillingItems() {
        if (!currentPatient) return;

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/billing-data`),
            method: 'GET',
            success: function(response) {
                renderBillingItems(response.items);
                updateBillingBadge(response.items.length);
            },
            error: function(xhr) {
                console.error('Failed to load billing items', xhr);
                toastr.error('Failed to load billing items');
            }
        });
    }

    function renderBillingItems(items) {
        console.log('renderBillingItems called with:', items);

        const tbody = $('#billing-items-tbody');
        tbody.empty();

        // Default to 'all' or currently selected tab if any
        let activeFamilyUserId = $('#billing-family-tabs .workspace-tab.active').data('user-id') || 'all';

        if (items.length === 0) {
            tbody.html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No unpaid items for this patient</p>
                </td>
            </tr>
        `);
            return;
        }

        items.forEach(item => {
            // Use pre-formatted datetime or format it client-side
            const datetime = item.created_at_formatted || (item.created_at ? formatDateTime(item.created_at) : 'N/A');
            let itemName = item.name;
            if (item.treatment_plan_id && item.treatment_plan_name) {
                itemName += ` <br><a href="#" class="tp-view-link badge mt-1" style="background-color: #e0f2f1; color: #00796b; border: 1px solid #00897b; text-decoration: none;" onclick="ClinicalOrdersKit.viewTreatmentPlan(${item.treatment_plan_id}); event.stopPropagation(); return false;"><i class="fa fa-clipboard-list"></i> ${escapeHtml(item.treatment_plan_name)}</a>`;
            }

            const row = `
            <tr data-item-id="${item.id}" data-user-id="${item.user_id}">
                <td><input type="checkbox" class="billing-item-checkbox" data-id="${item.id}"></td>
                <td class="text-nowrap" style="font-size: 0.85rem;"><i class="mdi mdi-clock-outline text-muted"></i> ${datetime}</td>
                <td>${itemName}</td>
                <td>${item.category || 'N/A'}</td>
                <td>₦${parseFloat(item.price).toLocaleString()}</td>
                <td><input type="number" class="form-control item-qty-input" value="${item.qty}" min="1" data-id="${item.id}"></td>
                <td><input type="number" class="form-control item-discount-input" value="${item.discount || 0}" min="0" max="100" data-id="${item.id}"></td>
                <td>${item.claims_amount> 0 ? `<span class="hmo-badge">₦${parseFloat(item.claims_amount).toLocaleString()}</span>` : '-'}</td>
                <td class="item-total" data-id="${item.id}">₦${calculateItemTotal(item).toLocaleString()}</td>
            </tr>
        `;
            tbody.append(row);
        });

        // Attach event listeners
        $('.billing-item-checkbox').on('change', updatePaymentSummary);
        $('#select-all-billing-items').on('change', function() {
            const selectVisibleOnly = $('#select-visible-only').is(':checked');
            if (selectVisibleOnly) {
                // Only select/deselect visible (not filtered out) items
                $('.billing-item-checkbox').each(function() {
                    const row = $(this).closest('tr');
                    if (!row.hasClass('filtered-out')) {
                        $(this).prop('checked', $('#select-all-billing-items').is(':checked'));
                    }
                });
            } else {
                // Select all items regardless of filter
                $('.billing-item-checkbox').prop('checked', $(this).is(':checked'));
            }
            updatePaymentSummary();
        });
        $('.item-qty-input, .item-discount-input').on('input', function() {
            const id = $(this).data('id');
            recalculateItemTotal(id);
            updatePaymentSummary();
        });

        // Populate category filter dropdown and update stats
        populateCategoryFilter(items);
        applyBillingFilters();
        updateBillingFilterStats();

        console.log('Rendered', items.length, 'billing items');
    }

    // ========== BILLING FILTER FUNCTIONS ==========

    // Populate category filter dropdown with unique categories from items
    function populateCategoryFilter(items) {
        const select = $('#billing-category-filter');
        const categories = [...new Set(items.map(item => item.category).filter(c => c))].sort();

        select.empty();
        select.append('<option value="">All Categories</option>');
        categories.forEach(cat => {
            select.append(`<option value="${cat}">${cat}</option>`);
        });
    }

    // Apply all billing filters
    function applyBillingFilters() {
        const searchTerm = $('#billing-search-input').val().toLowerCase().trim();
        const categoryFilter = $('#billing-category-filter').val();
        const dateFrom = $('#billing-date-from').val();
        const dateTo = $('#billing-date-to').val();

        $('#billing-items-tbody tr').each(function() {
            const row = $(this);
            if (row.find('td').length < 3) return; // Skip empty rows

            const itemName = row.find('td:eq(2)').text().toLowerCase();
            const itemCategory = row.find('td:eq(3)').text();
            const itemDateText = row.find('td:eq(1)').text().trim();

            let visible = true;
            const activeFamilyUserId = $('#billing-family-tabs .workspace-tab.active').data('user-id') || 'all';

            // Family Tab Filter
            if (activeFamilyUserId !== 'all' && row.data('user-id') != activeFamilyUserId) {
                visible = false;
            }

            // Search filter
            if (searchTerm && !itemName.includes(searchTerm)) {
                visible = false;
            }

            // Category filter
            if (categoryFilter && itemCategory !== categoryFilter) {
                visible = false;
            }

            // Date filters
            if (dateFrom || dateTo) {
                const itemDate = parseDateFromDisplay(itemDateText);
                if (itemDate) {
                    if (dateFrom && itemDate < new Date(dateFrom)) {
                        visible = false;
                    }
                    if (dateTo) {
                        const toDate = new Date(dateTo);
                        toDate.setHours(23, 59, 59, 999);
                        if (itemDate > toDate) {
                            visible = false;
                        }
                    }
                }
            }

            if (visible) {
                row.removeClass('filtered-out');
                // Highlight search match
                if (searchTerm) {
                    row.addClass('highlighted-match');
                } else {
                    row.removeClass('highlighted-match');
                }
            } else {
                row.addClass('filtered-out');
                row.removeClass('highlighted-match');
                // Uncheck filtered out items if select-visible-only is checked
                if ($('#select-visible-only').is(':checked')) {
                    row.find('.billing-item-checkbox').prop('checked', false);
                }
            }
        });

        updateBillingFilterStats();
        updatePaymentSummary();
    }

    // Parse date from display format (DD/MM/YYYY HH:MM)
    function parseDateFromDisplay(dateText) {
        if (!dateText || dateText === 'N/A') return null;
        try {
            // Handle DD/MM/YYYY HH:MM format
            const parts = dateText.split(' ');
            const dateParts = parts[0].split('/');
            if (dateParts.length === 3) {
                return new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
            }
            return new Date(dateText);
        } catch (e) {
            return null;
        }
    }

    // Update filter stats display
    function updateBillingFilterStats() {
        const totalRows = $('#billing-items-tbody tr').filter(function() {
            return $(this).find('td').length >= 3;
        }).length;

        const visibleRows = $('#billing-items-tbody tr').filter(function() {
            return $(this).find('td').length >= 3 && !$(this).hasClass('filtered-out');
        }).length;

        let visibleTotal = 0;
        $('#billing-items-tbody tr').each(function() {
            const row = $(this);
            if (row.find('td').length >= 3 && !row.hasClass('filtered-out')) {
                const totalText = row.find('.item-total').text().replace('₦', '').replace(/,/g, '');
                visibleTotal += parseFloat(totalText) || 0;
            }
        });

        $('#billing-items-total').text(totalRows);
        $('#billing-items-visible').text(visibleRows);
        $('#billing-visible-total').text(`₦${visibleTotal.toLocaleString()}`);
    }

    // Clear all billing filters
    function clearBillingFilters() {
        $('#billing-search-input').val('');
        $('#billing-category-filter').val('');
        $('#billing-date-from').val('');
        $('#billing-date-to').val('');
        applyBillingFilters();
    }

    // Initialize filter event listeners
    $(document).on('input', '#billing-search-input', debounce(applyBillingFilters, 300));
    $(document).on('change', '#billing-category-filter', applyBillingFilters);
    $(document).on('change', '#billing-date-from, #billing-date-to', applyBillingFilters);
    $(document).on('click', '#clear-billing-filters', clearBillingFilters);

    // Debounce utility
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Format datetime for display
    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        } catch (e) {
            return dateString;
        }
    }

    function calculateItemTotal(item) {
        const qty = parseFloat(item.qty) || 1;
        const price = parseFloat(item.price) || 0;
        const discount = parseFloat(item.discount) || 0;
        const subtotal = price * qty;
        const discountAmount = subtotal * (discount / 100);
        return subtotal - discountAmount;
    }

    function recalculateItemTotal(itemId) {
        const row = $(`tr[data-item-id="${itemId}"]`);
        const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
        const price = parseFloat(row.find('td:eq(4)').text().replace('₦', '').replace(/,/g, ''));
        const discount = parseFloat(row.find('.item-discount-input').val()) || 0;

        const subtotal = price * qty;
        const discountAmount = subtotal * (discount / 100);
        const total = subtotal - discountAmount;

        row.find('.item-total').text(`₦${total.toLocaleString()}`);
    }

    function updatePaymentSummary() {
        const selectedItems = $('.billing-item-checkbox:checked');

        if (selectedItems.length === 0) {
            // Hide floating cart
            $('#floating-cart').fadeOut(200);
            $('#process-payment-btn').prop('disabled', true);
            $('#print-invoice-btn').prop('disabled', true);
            return;
        }

        let subtotal = 0;
        let totalDiscount = 0;

        selectedItems.each(function() {
            const row = $(this).closest('tr');
            const qty = parseFloat(row.find('.item-qty-input').val()) || 1;
            const price = parseFloat(row.find('td:eq(4)').text().replace('₦', '').replace(/,/g, ''));
            const discountPercent = parseFloat(row.find('.item-discount-input').val()) || 0;

            const itemSubtotal = price * qty;
            const itemDiscount = itemSubtotal * (discountPercent / 100);

            subtotal += itemSubtotal;
            totalDiscount += itemDiscount;
        });

        const total = subtotal - totalDiscount;

        // Update summary in modal
        $('#summary-subtotal').text(`₦${subtotal.toLocaleString()}`);
        $('#summary-discount').text(`₦${totalDiscount.toLocaleString()}`);
        $('#summary-total').text(`₦${total.toLocaleString()}`);

        // Update floating cart
        $('#cart-item-count').text(selectedItems.length);
        $('#modal-item-count').text(selectedItems.length);
        $('#cart-total-display').text(`₦${total.toLocaleString()}`);

        // Show floating cart with pulse animation
        const floatingCart = $('#floating-cart');
        if (!floatingCart.is(':visible')) {
            floatingCart.fadeIn(300);
        }
        // Pulse animation on update
        $('.floating-cart-btn').addClass('pulse');
        setTimeout(() => $('.floating-cart-btn').removeClass('pulse'), 300);

        $('#process-payment-btn').prop('disabled', false);
        $('#print-invoice-btn').prop('disabled', false);
    }

    // Process payment button click (toolbar) - opens the modal
    $(document).on('click', '#process-payment-btn', function() {
        const selectedItems = $('.billing-item-checkbox:checked');
        if (selectedItems.length === 0) {
            toastr.warning('Please select items to process payment');
            return;
        }
        // Auto-select patient default billing preference if set
        if (currentPatientData && currentPatientData.default_billing_mode) {
            $('#payment-method').val(currentPatientData.default_billing_mode).trigger('change');
            if (currentPatientData.default_billing_mode === 'BILL_TO_STAFF') {
                $('#payment-staff-id').val(currentPatientData.default_billing_id).trigger('change');
            } else if (currentPatientData.default_billing_mode === 'BILL_TO_ORGANIZATION') {
                $('#payment-organization-id').val(currentPatientData.default_billing_id).trigger('change');
            }

            // Add a small visual cue
            if ($('#default-billing-cue').length === 0) {
                $('.payment-method-section label').append(' <span id="default-billing-cue" class="badge bg-info text-white ms-2" style="font-size: 0.7rem;">Default</span>');
            }
        } else {
            // Reset to default
            $('#payment-method').val('CASH').trigger('change');
            $('#default-billing-cue').remove();
        }

        // Open the payment modal using Bootstrap 5 API
        const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        paymentModal.show();
    });

    // Payment scripts are now included via partial at the bottom of the file

    // Print invoice button click handler
    $(document).on('click', '#print-invoice-btn', function() {
        printInvoice();
    });

    function printInvoice() {
        const selectedItems = $('.billing-item-checkbox:checked');

        if (selectedItems.length === 0) {
            toastr.warning('Please select items to print invoice');
            return;
        }

        // Collect selected item IDs
        const itemIds = [];
        selectedItems.each(function() {
            itemIds.push($(this).data('id'));
        });

        // Show loading
        toastr.info('Generating invoice...');

        $.ajax({
            url: wbUrl('/billing-workbench/print-invoice'),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                patient_id: currentPatient,
                item_ids: itemIds
            },
            success: function(response) {
                // Show in modal (reusing receipt modal)
                $('#modal-receipt-a4').html(response.invoice_a4);
                $('#modal-receipt-thermal').html(response.invoice_thermal);

                // Reset tabs to A4
                $('.receipt-modal-tab').removeClass('active');
                $('.receipt-modal-tab[data-format="a4"]').addClass('active');
                $('#modal-receipt-a4').show();
                $('#modal-receipt-thermal').hide();

                // Update modal title temporarily
                $('#receiptPreviewModal .modal-title').text('Invoice Preview - ' + response.invoice_no);

                // Show modal
                $('#receiptPreviewModal').modal('show');
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate invoice');
            }
        });
    }

    // Receipt Modal Tab Switching
    $(document).on('click', '.receipt-modal-tab', function() {
        const format = $(this).data('format');

        $('.receipt-modal-tab').removeClass('active');
        $(this).addClass('active');

        if (format === 'a4') {
            $('#modal-receipt-a4').show();
            $('#modal-receipt-thermal').hide();
        } else {
            $('#modal-receipt-a4').hide();
            $('#modal-receipt-thermal').show();
        }
    });

    // Modal Print Buttons
    $(document).on('click', '#modal-print-a4', function() {
        printReceiptContent('modal-receipt-a4');
    });

    $(document).on('click', '#modal-print-thermal', function() {
        printReceiptContent('modal-receipt-thermal');
    });

    function printReceiptContent(elementId) {
        const content = $(`#${elementId}`).html();
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Receipt</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; margin: 0; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }');
        printWindow.document.write('.text-center { text-align: center; }');
        printWindow.document.write('.text-right { text-align: right; }');
        printWindow.document.write('.font-weight-bold { font-weight: bold; }');
        printWindow.document.write('@media print { body { padding: 0; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 250);
    }

    // ==========================================
    // ACCOUNT STATEMENT FUNCTIONALITY
    // ==========================================

    // Store generated statement content
    let generatedStatementA4 = null;
    let generatedStatementThermal = null;

    // Print Statement Button Click
    $(document).on('click', '#print-statement-btn', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }

        // Reset the modal to config view
        resetStatementModal();

        // Set default dates (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));

        $('#statement-date-to').val(today.toISOString().split('T')[0]);
        $('#statement-date-from').val(thirtyDaysAgo.toISOString().split('T')[0]);

        // Show modal
        $('#accountStatementModal').modal('show');
    });

    // Reset modal to initial state
    function resetStatementModal() {
        $('#statement-config-panel').show();
        $('#statement-preview-panel').hide();
        $('#statement-modal-footer').hide();
        generatedStatementA4 = null;
        generatedStatementThermal = null;

        // Reset checkboxes to checked
        $('#include-deposits').prop('checked', true);
        $('#include-payments').prop('checked', true);
        $('#include-withdrawals').prop('checked', true);
        $('#include-services').prop('checked', true);
    }

    // Date preset buttons
    $(document).on('click', '.statement-date-presets button', function() {
        const preset = $(this).data('preset');
        const today = new Date();
        let fromDate = new Date();

        switch (preset) {
            case '7days':
                fromDate.setDate(today.getDate() - 7);
                break;
            case '30days':
                fromDate.setDate(today.getDate() - 30);
                break;
            case 'thisMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                break;
            case 'lastMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                today.setDate(0); // Last day of previous month
                break;
            case 'thisYear':
                fromDate = new Date(today.getFullYear(), 0, 1);
                break;
            case 'all':
                fromDate = new Date(2000, 0, 1);
                break;
        }

        $('#statement-date-from').val(fromDate.toISOString().split('T')[0]);
        $('#statement-date-to').val(preset === 'lastMonth' ?
            new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0] :
            new Date().toISOString().split('T')[0]);

        // Highlight active preset
        $('.statement-date-presets button').removeClass('btn-secondary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-secondary');
    });

    // Generate Statement Button
    $(document).on('click', '#generate-statement-btn', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }

        const dateFrom = $('#statement-date-from').val();
        const dateTo = $('#statement-date-to').val();

        if (!dateFrom || !dateTo) {
            toastr.warning('Please select a date range');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Generating...');

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/generate-statement`),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                date_from: dateFrom,
                date_to: dateTo,
                include_deposits: $('#include-deposits').is(':checked'),
                include_payments: $('#include-payments').is(':checked'),
                include_withdrawals: $('#include-withdrawals').is(':checked'),
                include_services: $('#include-services').is(':checked')
            },
            success: function(response) {
                if (response.success) {
                    // Store generated content
                    generatedStatementA4 = response.statement_a4;
                    generatedStatementThermal = response.statement_thermal;

                    // Update preview panes
                    $('#statement-pane-a4').html(response.statement_a4);
                    $('#statement-pane-thermal').html(response.statement_thermal);

                    // Switch to preview panel
                    $('#statement-config-panel').hide();
                    $('#statement-preview-panel').show();
                    $('#statement-modal-footer').show();

                    // Reset tabs to A4
                    $('.statement-modal-tab').removeClass('active');
                    $('.statement-modal-tab[data-format="a4"]').addClass('active');
                    $('#statement-pane-a4').addClass('active').show();
                    $('#statement-pane-thermal').removeClass('active').hide();

                    toastr.success(`Statement generated with ${response.transaction_count} transactions`);
                } else {
                    toastr.error(response.message || 'Failed to generate statement');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to generate statement');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-file-document"></i> Generate Statement');
            }
        });
    });

    // Statement Modal Tab Switching
    $(document).on('click', '.statement-modal-tab', function() {
        const format = $(this).data('format');

        // Handle config tab
        if (format === 'config') {
            $('#statement-config-panel').show();
            $('#statement-preview-panel').hide();
            $('#statement-modal-footer').hide();
            return;
        }

        $('.statement-modal-tab').removeClass('active');
        $(this).addClass('active');

        $('.statement-modal-pane').removeClass('active').hide();
        $(`#statement-pane-${format}`).addClass('active').show();
    });

    // Back to Options Button
    $(document).on('click', '#statement-back-btn', function() {
        $('#statement-config-panel').show();
        $('#statement-preview-panel').hide();
        $('#statement-modal-footer').hide();
    });

    // Statement Print Buttons
    $(document).on('click', '#statement-print-a4', function() {
        printStatementContent('statement-pane-a4');
    });

    $(document).on('click', '#statement-print-thermal', function() {
        printStatementContent('statement-pane-thermal');
    });

    function printStatementContent(elementId) {
        const content = $(`#${elementId}`).html();
        const printWindow = window.open('', '', 'height=700,width=900');
        printWindow.document.write('<html><head><title>Account Statement</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 15px; margin: 0; font-size: 12px; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
        printWindow.document.write('th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #ddd; }');
        printWindow.document.write('th { background: #f5f5f5; font-weight: bold; }');
        printWindow.document.write('.text-center { text-align: center; }');
        printWindow.document.write('.text-right { text-align: right; }');
        printWindow.document.write('.font-weight-bold { font-weight: bold; }');
        printWindow.document.write('.summary-card { display: inline-block; padding: 10px; margin: 5px; border: 1px solid #ddd; border-radius: 5px; }');
        printWindow.document.write('.type-badge { padding: 2px 6px; border-radius: 3px; font-size: 10px; }');
        printWindow.document.write('.credit { color: #28a745; }');
        printWindow.document.write('.debit { color: #dc3545; }');
        printWindow.document.write('@media print { body { padding: 5px; } @page { margin: 0.5cm; } }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 300);
    }

    // Account Tab
    function loadAccountSummary() {
        if (!currentPatient) return;

        $.ajax({
            url: wbUrl(`/billing-workbench/patient/${currentPatient}/account-summary`),
            method: 'GET',
            success: function(response) {
                renderAccountSummary(response);
            },
            error: function(xhr) {
                toastr.error('Failed to load account summary');
            }
        });
    }

    function renderAccountSummary(data) {
        const balance = parseFloat(data.balance);

        // Update hero balance in new UI
        const heroBalance = $('#account-hero-balance');
        heroBalance.removeClass('credit debit');

        $('#hero-balance-amount').text(`₦${balance.toLocaleString()}`);

        if (balance > 0) {
            heroBalance.addClass('credit');
            $('#hero-balance-status').text('Credit Balance');
        } else if (balance < 0) {
            heroBalance.addClass('debit');
            $('#hero-balance-status').text('Debit Balance');
        } else {
            $('#hero-balance-status').text('Balanced');
        }

        // Update pending bills stat
        $('#pending-bills-stat').text(`₦${parseFloat(data.unpaid_total || 0).toLocaleString()}`);

        // Also update the account tab cards with new modern UI
        if (data.account) {
            displayAccountInfo(data.account, data.unpaid_total);
        } else {
            showNoAccountState();
        }
    }

    // My Transactions Modal
    $(document).on('click', '#btn-my-transactions', function() {
        $('#myTransactionsModal').modal('show');
        // Set default dates to today
        const today = new Date().toISOString().split('T')[0];
        $('#my-trans-from-date').val(today);
        $('#my-trans-to-date').val(today);

        // Populate bank dropdown
        populateMyTransactionsBankDropdown();
    });

    function populateMyTransactionsBankDropdown() {
        const $bankSelect = $('#my-trans-bank');
        $bankSelect.find('option:not(:first)').remove();

        if (availableBanks.length > 0) {
            availableBanks.forEach(bank => {
                $bankSelect.append(`<option value="${bank.id}">${bank.name}</option>`);
            });
        }
    }

    $(document).on('click', '#load-my-transactions', function() {
        const fromDate = $('#my-trans-from-date').val();
        const toDate = $('#my-trans-to-date').val();
        const paymentType = $('#my-trans-payment-type').val();
        const bankId = $('#my-trans-bank').val();

        loadMyTransactions(fromDate, toDate, paymentType, bankId);
    });

    // Print My Transactions
    $(document).on('click', '#print-my-transactions', function() {
        const printContent = document.getElementById('my-transactions-modal-body').innerHTML;
        const fromDate = $('#my-trans-from-date').val();
        const toDate = $('#my-trans-to-date').val();

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>My Transactions Report</title>
            <link rel="stylesheet" href="${window.location.origin}/assets/css/bootstrap.min.css">
            <link rel="stylesheet" href="${window.location.origin}/assets/css/style.css">
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background: #fff;
                }
                .print-header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #dee2e6;
                }
                .print-header h2 {
                    margin-bottom: 5px;
                    color: #333;
                }
                .date-range {
                    color: #666;
                    margin-bottom: 0;
                    font-size: 0.9rem;
                }
                .print-date {
                    font-size: 0.8rem;
                    color: #888;
                }
                .table {
                    width: 100%;
                    margin-top: 15px;
                }
                .table th {
                    background-color: #f8f9fa;
                    font-weight: 600;
                    border-top: 2px solid #dee2e6;
                }
                .table td, .table th {
                    padding: 0.5rem;
                    font-size: 0.85rem;
                }
                .my-transactions-filter { display: none !important; }
                .summary-section {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .summary-stat-card {
                    display: inline-block;
                    padding: 12px 20px;
                    margin: 5px;
                    background: #fff;
                    border-radius: 8px;
                    text-align: center;
                    min-width: 140px;
                    border: 1px solid #dee2e6;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                .stat-value {
                    font-size: 1.25rem;
                    font-weight: bold;
                    color: #333;
                    display: block;
                }
                .stat-label {
                    font-size: 0.75rem;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .payment-type-breakdown {
                    margin-top: 15px;
                }
                .card {
                    border: 1px solid #dee2e6;
                    box-shadow: none;
                }
                .card-body {
                    padding: 0.75rem;
                }
                .btn { display: none !important; }
                @media print {
                    body {
                        padding: 0;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print { display: none !important; }
                    .summary-stat-card {
                        background: #f8f9fa !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .table th {
                        background-color: #e9ecef !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid">
                <div class="print-header">
                    <h2>My Transactions Report</h2>
                    <p class="date-range">Period: ${fromDate} to ${toDate}</p>
                    <p class="print-date">Printed on: ${new Date().toLocaleString()}</p>
                </div>
                ${printContent}
            </div>
            <script>
                // Wait for Bootstrap CSS to load before printing
                setTimeout(function() {
                    window.print();
                }, 500);
            <\/script>
        </body>
        </html>
    `);
        printWindow.document.close();
    });

    function loadMyTransactions(fromDate, toDate, paymentType, bankId) {
        $.ajax({
            url: wbUrl('/billing-workbench/my-transactions'),
            method: 'GET',
            data: {
                from: fromDate,
                to: toDate,
                payment_type: paymentType,
                bank_id: bankId
            },
            success: function(response) {
                renderMyTransactions(response.transactions);
                renderMyTransactionsSummary(response.summary);
            },
            error: function(xhr) {
                toastr.error('Failed to load transactions');
            }
        });
    }

    function renderMyTransactions(transactions) {
        const tbody = $('#my-transactions-tbody');
        tbody.empty();

        if (transactions.length === 0) {
            tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="mdi mdi-information-outline" style="font-size: 3rem;"></i>
                    <p>No transactions found for the selected period</p>
                </td>
            </tr>
        `);
            return;
        }

        transactions.forEach(tx => {
            const row = `
            <tr>
                <td>${tx.created_at}</td>
                <td>${tx.patient_name}</td>
                <td>${tx.file_no}</td>
                <td>${tx.reference_no || 'N/A'}</td>
                <td>${tx.payment_type}</td>
                <td>${tx.bank_name || '-'}</td>
                <td>₦${parseFloat(tx.total).toLocaleString()}</td>
                <td>₦${parseFloat(tx.total_discount).toLocaleString()}</td>
            </tr>
        `;
            tbody.append(row);
        });
    }

    function renderMyTransactionsSummary(summary) {
        $('#my-total-transactions').text(summary.count);
        $('#my-total-amount').text(`₦${parseFloat(summary.total_amount).toLocaleString()}`);
        $('#my-total-discounts').text(`₦${parseFloat(summary.total_discount).toLocaleString()}`);

        // Render breakdown by payment type
        const breakdown = $('#payment-type-breakdown');
        breakdown.empty();

        if (summary.by_type) {
            let html = '<h6 class="mt-3 mb-2">Breakdown by Payment Type</h6><div class="row">';
            Object.keys(summary.by_type).forEach(type => {
                const data = summary.by_type[type];
                html += `
                <div class="col-md-3 mb-2">
                    <div style="padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #dee2e6;">
                        <strong>${type}</strong><br>
                        <small>${data.count} transactions</small><br>
                        <span style="font-size: 1.1rem; color: var(--hospital-primary);">₦${parseFloat(data.amount).toLocaleString()}</span>
                    </div>
                </div>
            `;
            });
            html += '</div>';
            breakdown.html(html);
        }

        $('#my-transactions-summary').show();
    }

    function updateBillingBadge(count) {
        $('#billing-badge').text(count);
    }

    // Old lab-specific functions removed, keeping legacy compatibility stubs

    function recordBilling(requestIds) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }

    function collectSample(requestIds) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }

    function dismissRequests(requestIds, section) {
        console.warn('Legacy function called - no longer applicable in billing workbench');
    }

    function setResTempInModal(request) {
        $('#investResModal').find('form').trigger('reset');
        $('#invest_res_service_name').text(request.service ? request.service.name : '');
        $('#invest_res_entry_id').val(request.id);
        $('#invest_res_is_edit').val(0);
        $('#deleted_attachments').val('[]');
        $('#existing_attachments_container').hide();
        $('#existing_attachments_list').html('');

        // Check template version
        const isV2 = request.service && request.service.template_version == 2;

        if (isV2) {
            let structure = request.service.template_structure;
            if (typeof structure === 'string') {
                try {
                    structure = JSON.parse(structure);
                } catch (e) {
                    console.error('Error parsing V2 template structure:', e);
                    structure = null;
                }
            }

            if (structure) {
                // Parse result_data if available (for edit mode)
                let existingData = null;
                if (request.result_data) {
                    try {
                        existingData = typeof request.result_data === 'string' ? JSON.parse(request.result_data) : request.result_data;
                    } catch (e) {
                        console.error('Error parsing result_data:', e);
                    }
                }
                loadV2Template(structure, existingData);
            } else {
                console.error('Invalid V2 template structure');
                // Fallback or error handling
            }
        } else {
            // Use request.result if available (for edit), otherwise template body
            let content = request.result || (request.service ? request.service.template_body : '');
            loadV1Template(content);
        }

        // Load existing attachments if editing (logic to be added if edit mode is supported)
        loadExistingAttachments(request.id);
    }

    function loadV1Template(template) {
        $('#invest_res_template_version').val('1');
        $('#v1_template_container').show();
        $('#v2_template_container').hide();

        // Re-enable content editing if it was disabled upon save
        if (template) {
            template = template.replace(/contenteditable="false"/g, 'contenteditable="true"');
            template = template.replace(/contenteditable='false'/g, "contenteditable='true'");
        }

        // Initialize CKEditor if not already initialized
        if (!window.investResEditor) {
            ClassicEditor
                .create(document.querySelector('#invest_res_template_editor'), {
                    toolbar: {
                        items: [
                            'undo', 'redo',
                            '|', 'heading',
                            '|', 'bold', 'italic',
                            '|', 'link', 'insertTable',
                            '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                        ]
                    }
                })
                .then(editor => {
                    window.investResEditor = editor;
                    editor.setData(template || '');
                })
                .catch(err => {
                    console.error(err);
                });
        } else {
            window.investResEditor.setData(template || '');
        }
    }

    function loadV2Template(template, existingData) {
        $('#invest_res_template_version').val('2');
        $('#v1_template_container').hide();
        $('#v2_template_container').show();

        let formHtml = '<div class="v2-result-form">';
        formHtml += '<h6 class="mb-3">' + (template.template_name || 'Result Entry') + '</h6>';

        // Sort parameters by order
        let parameters = template.parameters ? template.parameters.sort((a, b) => a.order - b.order) : [];

        parameters.forEach(param => {
            if (param.show_in_report === false) {
                return; // Skip hidden parameters
            }

            formHtml += '<div class="form-group row">';
            formHtml += '<label class="col-md-4 col-form-label">';
            formHtml += param.name;
            if (param.unit) {
                formHtml += ' <small class="text-muted">(' + param.unit + ')</small>';
            }
            if (param.required) {
                formHtml += ' <span class="text-danger">*</span>';
            }
            formHtml += '</label>';
            formHtml += '<div class="col-md-8">';

            let fieldId = 'param_' + param.id;
            let value = '';
            if (existingData && existingData[param.id]) {
                // Handle both direct value and object with value property
                if (typeof existingData[param.id] === 'object' && existingData[param.id] !== null && existingData[param.id].hasOwnProperty('value')) {
                    value = existingData[param.id].value;
                } else {
                    value = existingData[param.id];
                }
            }
            if (value === null || value === undefined) value = '';

            // Generate form field based on type
            if (param.type === 'string') {
                formHtml += '<input type="text" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                formHtml += 'id="' + fieldId + '" ';
                formHtml += 'value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'integer') {
                formHtml += '<input type="number" step="1" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                if (param.reference_range) {
                    formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                    formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                formHtml += 'value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'float') {
                formHtml += '<input type="number" step="0.01" class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                if (param.reference_range) {
                    formHtml += 'data-ref-min="' + (param.reference_range.min || '') + '" ';
                    formHtml += 'data-ref-max="' + (param.reference_range.max || '') + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                formHtml += 'value="' + value + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">';

            } else if (param.type === 'boolean') {
                formHtml += '<select class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                if (param.reference_range && param.reference_range.reference_value !== undefined) {
                    formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += '>';
                formHtml += '<option value="">Select</option>';
                formHtml += '<option value="true" ' + (value === true || value === 'true' ? 'selected' : '') + '>Yes/Positive</option>';
                formHtml += '<option value="false" ' + (value === false || value === 'false' ? 'selected' : '') + '>No/Negative</option>';
                formHtml += '</select>';

            } else if (param.type === 'enum') {
                formHtml += '<select class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                if (param.reference_range && param.reference_range.reference_value) {
                    formHtml += 'data-ref-value="' + param.reference_range.reference_value + '" ';
                }
                formHtml += 'id="' + fieldId + '" ';
                if (param.required) formHtml += 'required ';
                formHtml += '>';
                formHtml += '<option value="">Select</option>';
                if (param.options) {
                    param.options.forEach(opt => {
                        let optVal = typeof opt === 'object' ? opt.value : opt;
                        let optLabel = typeof opt === 'object' ? opt.label : opt;
                        formHtml += '<option value="' + optVal + '" ' + (value === optVal ? 'selected' : '') + '>' + optLabel + '</option>';
                    });
                }
                formHtml += '</select>';

            } else if (param.type === 'long_text') {
                formHtml += '<textarea class="form-control v2-param-field" ';
                formHtml += 'data-param-id="' + param.id + '" ';
                formHtml += 'data-param-type="' + param.type + '" ';
                formHtml += 'id="' + fieldId + '" ';
                formHtml += 'rows="3" ';
                if (param.required) formHtml += 'required ';
                formHtml += 'placeholder="Enter ' + param.name + '">' + value + '</textarea>';
            }

            // Add reference range info if available
            if (param.reference_range) {
                formHtml += '<small class="form-text text-muted">';
                if (param.type === 'integer' || param.type === 'float') {
                    if (param.reference_range.min !== null && param.reference_range.max !== null) {
                        formHtml += 'Normal range: ' + param.reference_range.min + ' - ' + param.reference_range.max;
                    }
                } else if (param.type === 'boolean' && param.reference_range.reference_value !== undefined) {
                    formHtml += 'Normal: ' + (param.reference_range.reference_value ? 'Yes/Positive' : 'No/Negative');
                } else if (param.type === 'enum' && param.reference_range.reference_value) {
                    formHtml += 'Normal: ' + param.reference_range.reference_value;
                } else if (param.reference_range.text) {
                    formHtml += param.reference_range.text;
                }
                formHtml += '</small>';
            }

            // Status indicator (will be updated on blur)
            formHtml += '<div class="mt-1"><span class="param-status" id="status_' + param.id + '"></span></div>';

            formHtml += '</div>';
            formHtml += '</div>';
        });

        formHtml += '</div>';

        $('#v2_form_fields').html(formHtml);

        // Add event listeners for value changes to show status
        $('.v2-param-field').on('blur change', function() {
            updateParameterStatus($(this));
        });

        // Trigger status update for pre-filled values
        $('.v2-param-field').each(function() {
            if ($(this).val()) {
                updateParameterStatus($(this));
            }
        });
    }

    function updateParameterStatus($field) {
        let paramId = $field.data('param-id');
        let paramType = $field.data('param-type');
        let value = $field.val();
        let $statusSpan = $('#status_' + paramId);

        if (!value || value === '') {
            $statusSpan.html('');
            return;
        }

        let status = '';
        let statusClass = '';

        if (paramType === 'integer' || paramType === 'float') {
            let numValue = parseFloat(value);
            let min = $field.data('ref-min');
            let max = $field.data('ref-max');

            if (min !== undefined && max !== undefined && min !== '' && max !== '') {
                if (numValue < min) {
                    status = 'Low';
                    statusClass = 'badge-warning';
                } else if (numValue > max) {
                    status = 'High';
                    statusClass = 'badge-danger';
                } else {
                    status = 'Normal';
                    statusClass = 'badge-success';
                }
            }
        } else if (paramType === 'boolean') {
            let refValue = $field.data('ref-value');
            if (refValue !== undefined) {
                let boolValue = value === 'true';
                let refBool = refValue === true || refValue === 'true';

                if (boolValue === refBool) {
                    status = 'Normal';
                    statusClass = 'badge-success';
                } else {
                    status = 'Abnormal';
                    statusClass = 'badge-warning';
                }
            }
        } else if (paramType === 'enum') {
            let refValue = $field.data('ref-value');
            if (refValue) {
                if (value === refValue) {
                    status = 'Normal';
                    statusClass = 'badge-success';
                } else {
                    status = 'Abnormal';
                    statusClass = 'badge-warning';
                }
            }
        }

        if (status) {
            $statusSpan.html('<span class="badge ' + statusClass + '">' + status + '</span>');
        } else {
            $statusSpan.html('');
        }
    }

    function loadExistingAttachments(requestId) {
        const container = $('#existing_attachments_list');
        const wrapper = $('#existing_attachments_container');
        container.empty();
        wrapper.hide();

        $.ajax({
            url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}/attachments`),
            method: 'GET',
            success: function(attachments) {
                if (attachments && attachments.length > 0) {
                    wrapper.show();
                    attachments.forEach(att => {
                        const attDiv = $('<div>').addClass('attachment-item mb-2 d-flex justify-content-between align-items-center');
                        const link = $('<a>').attr('href', att.url).attr('target', '_blank').text(att.filename);
                        const deleteBtn = $('<button>')
                            .addClass('btn btn-sm btn-danger')
                            .html('<i class="fa fa-trash"></i>')
                            .on('click', function() {
                                markAttachmentForDeletion(att.id);
                                attDiv.remove();
                                if (container.children().length === 0) {
                                    wrapper.hide();
                                }
                            });
                        attDiv.append(link).append(deleteBtn);
                        container.append(attDiv);
                    });
                }
            }
        });
    }

    function markAttachmentForDeletion(attachmentId) {
        const current = $('#deleted_attachments').val();
        const deleted = current ? JSON.parse(current) : [];
        deleted.push(attachmentId);
        $('#deleted_attachments').val(JSON.stringify(deleted));
    }

    // Handle result form submission
    $('#investResForm').on('submit', function(e) {
        e.preventDefault();

        // Copy data from editors/inputs to hidden fields
        copyResTemplateToField();

        const formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                alert('Result saved successfully!');
                $('#investResModal').modal('hide');
                if (currentPatient) {
                    loadPatient(currentPatient);
                }
            },
            error: function(xhr) {
                alert('Error saving result: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    });

    function copyResTemplateToField() {
        let version = $('#invest_res_template_version').val();

        if (version === '2') {
            // Collect V2 structured data
            let data = {};
            $('.v2-param-field').each(function() {
                let paramId = $(this).data('param-id');
                let paramType = $(this).data('param-type');
                let value = $(this).val();

                // Convert values to appropriate types
                if (paramType === 'integer') {
                    data[paramId] = value ? parseInt(value) : null;
                } else if (paramType === 'float') {
                    data[paramId] = value ? parseFloat(value) : null;
                } else if (paramType === 'boolean') {
                    data[paramId] = value === 'true' ? true : (value === 'false' ? false : null);
                } else {
                    data[paramId] = value || null;
                }
            });

            $('#invest_res_template_data').val(JSON.stringify(data));
            // For V2, we still save a simple HTML representation to result column for backward compat
            $('#invest_res_template_submited').val('<p>Structured result data (V2 template)</p>');
        } else {
            // V1: Copy from CKEditor
            if (window.investResEditor) {
                $('#invest_res_template_submited').val(window.investResEditor.getData());
            }
        }
        return true;
    }

    function editLabResult(obj) {
        const requestId = $(obj).data('id');

        $.ajax({
            url: wbUrl(`/lab-workbench/lab-service-requests/${requestId}`),
            method: 'GET',
            success: function(request) {
                // Populate the form with template structure AND existing result data
                setResTempInModal(request);

                // Set Edit Mode UI
                $('#invest_res_is_edit').val(1);
                $('#investResModalLabel').text('Edit Result: ' + (request.service ? request.service.name : ''));
                $('#invest_res_submit_btn').html('<i class="mdi mdi-content-save"></i> Update Result');

                $('#investResModal').modal('show');
            },
            error: function(xhr) {
                alert('Error loading request: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }

    // setResViewInModal, PrintElem, getFileIcon now provided by invest_res_view_js partial

    // Delete Lab Request with Reason
    let deleteRequestId = null;
    let deleteEncounterId = null;

    function deleteLabRequest(requestId, encounterId, serviceName) {
        deleteRequestId = requestId;
        deleteEncounterId = encounterId;
        $('#delete_service_name').text(serviceName);
        $('#delete_request_id').text(requestId);
        $('#delete_reason').val('');
        $('#deleteReasonModal').modal('show');
    }

    $('#deleteRequestForm').on('submit', function(e) {
        e.preventDefault();

        const reason = $('#delete_reason').val();

        if (reason.length < 10) {
            alert('Please provide a detailed reason (minimum 10 characters)');
            return;
        }

        $.ajax({
            url: wbUrl(`/lab-workbench/lab-service-requests/${deleteRequestId}`),
            method: 'DELETE',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                reason: reason
            },
            success: function(response) {
                $('#deleteReasonModal').modal('hide');
                alert(response.message);

                // Reload patient data if we're on a patient
                if (currentPatient) {
                    loadPatient(currentPatient);
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete request'));
            }
        });
    });

    // Dismiss Lab Request
    let dismissRequestId = null;

    function dismissSingleRequest(requestId, serviceName) {
        dismissRequestId = requestId;
        $('#dismiss_service_name').text(serviceName);
        $('#dismiss_request_id').text(requestId);
        $('#dismiss_reason').val('');
        $('#dismissReasonModal').modal('show');
    }

    $('#dismissRequestForm').on('submit', function(e) {
        e.preventDefault();

        const reason = $('#dismiss_reason').val();

        if (reason.length < 10) {
            alert('Please provide a detailed reason (minimum 10 characters)');
            return;
        }

        $.ajax({
            url: wbUrl(`/lab-workbench/lab-service-requests/${dismissRequestId}/dismiss`),
            method: 'POST',
            data: {
                _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')),
                reason: reason
            },
            success: function(response) {
                $('#dismissReasonModal').modal('hide');
                alert(response.message);

                // Reload patient data
                if (currentPatient) {
                    loadPatient(currentPatient);
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to dismiss request'));
            }
        });
    });

    // ============================================
    // ENHANCEMENT FUNCTIONS
    // ============================================

    // Create vital tooltip element
    function createVitalTooltip() {
        vitalTooltip = $('<div class="vital-tooltip"></div>').appendTo('body');

        // Hide on mouse leave
        $(document).on('mouseleave', '.vital-item', function() {
            vitalTooltip.removeClass('active');
        });
    }

    // Show vital tooltip with details
    function showVitalTooltip(event, vitalType, value, normalRange) {
        const tooltip = vitalTooltip;
        const $target = $(event.currentTarget);
        const offset = $target.offset();

        let deviation = '';
        let status = 'Normal';

        // Calculate deviation based on vital type
        if (vitalType === 'temperature' && value !== 'N/A') {
            const temp = parseFloat(value);
            const idealTemp = 37.0;
            const diff = Math.abs(temp - idealTemp);
            deviation = temp > idealTemp ? `+${diff.toFixed(1)}°C above ideal` : `-${diff.toFixed(1)}°C below ideal`;
            status = (temp >= 36.1 && temp <= 38.0) ? 'Normal' : 'Abnormal';
        } else if (vitalType === 'pulse' && value !== 'N/A') {
            const pulse = parseInt(value);
            const idealPulse = 80;
            const diff = Math.abs(pulse - idealPulse);
            deviation = pulse > idealPulse ? `+${diff} bpm above ideal` : `-${diff} bpm below ideal`;
            status = (pulse >= 60 && pulse <= 100) ? 'Normal' : 'Abnormal';
        } else if (vitalType === 'bp' && value !== 'N/A' && value.includes('/')) {
            const [sys, dia] = value.split('/').map(v => parseInt(v));
            status = (sys >= 90 && sys <= 140 && dia >= 60 && dia <= 90) ? 'Normal' : 'Abnormal';
            deviation = sys > 140 ? 'High BP' : sys < 90 ? 'Low BP' : 'Optimal';
        }

        const content = `
        <div style="font-weight: 600; margin-bottom: 0.5rem;">${vitalType.toUpperCase()}</div>
        <div><strong>Value:</strong> ${value}</div>
        <div><strong>Normal Range:</strong> ${normalRange}</div>
        <div><strong>Status:</strong> <span style="color: ${status === 'Normal' ? '#28a745' : '#dc3545'}">${status}</span></div>
        ${deviation ? `<div><strong>Deviation:</strong> ${deviation}</div>` : ''}
    `;

        tooltip.html(content);
        tooltip.css({
            top: offset.top - tooltip.outerHeight() - 10,
            left: offset.left + ($target.outerWidth() / 2) - (tooltip.outerWidth() / 2)
        });
        tooltip.addClass('active');
    }

    // Check for drug allergies
    function checkForAllergies(medications, patientAllergies) {
        if (!patientAllergies) {
            return [];
        }

        // Normalize allergies to array format
        let allergiesArray = [];

        if (typeof patientAllergies === 'string') {
            // Handle comma-separated string
            allergiesArray = patientAllergies.split(',').map(a => a.trim()).filter(a => a.length > 0);
        } else if (Array.isArray(patientAllergies)) {
            // Handle array (could be array of strings or array of objects)
            allergiesArray = patientAllergies.map(a => {
                if (typeof a === 'string') return a.trim();
                if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                return '';
            }).filter(a => a.length > 0);
        } else if (typeof patientAllergies === 'object' && patientAllergies !== null) {
            // Handle single object or object with values
            if (patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen) {
                allergiesArray = [(patientAllergies.name || patientAllergies.allergy || patientAllergies.allergen).trim()];
            } else {
                // Try to extract values from object
                allergiesArray = Object.values(patientAllergies).map(a => {
                    if (typeof a === 'string') return a.trim();
                    if (typeof a === 'object' && a !== null) return (a.name || a.allergy || a.allergen || '').trim();
                    return '';
                }).filter(a => a.length > 0);
            }
        }

        if (allergiesArray.length === 0) {
            return [];
        }

        const alerts = [];
        medications.forEach(med => {
            const drugName = (med.drug_name || med.product_name || '').toLowerCase();
            allergiesArray.forEach(allergy => {
                if (drugName.includes(allergy.toLowerCase())) {
                    alerts.push({
                        medication: med.drug_name || med.product_name,
                        allergy: allergy
                    });
                }
            });
        });

        return alerts;
    }

    // Display allergy alert banner
    function displayAllergyAlert(alerts) {
        if (alerts.length === 0) return '';

        const allergyList = alerts.map(alert =>
            `<strong>${alert.medication}</strong> (Allergic to: ${alert.allergy})`
        ).join('<br>');

        return `
        <div class="allergy-alert">
            <div class="allergy-alert-icon">⚠️</div>
            <div>
                <strong>ALLERGY WARNING!</strong><br>
                ${allergyList}
            </div>
        </div>
    `;
    }

    // Animate refresh button
    function animateRefresh(buttonElement) {
        const $btn = $(buttonElement);
        $btn.addClass('refreshing');
        setTimeout(() => $btn.removeClass('refreshing'), 600);
    }

    // =============================================
    // VIEW MANAGEMENT HELPERS
    // =============================================

    // Hide all overlapping views - call this before showing any new view
    function hideAllViews() {
        $('#empty-state').hide();
        $('#queue-view').removeClass('active').hide();
        $('.queue-item').removeClass('active');
        $('#reports-view').removeClass('active').hide();
        $('#patient-header').removeClass('active');
        $('#workspace-content').removeClass('active').hide();
    }

    // =============================================
    // QUEUE FUNCTIONALITY
    // =============================================

    let queueDataTable = null;
    let currentQueueFilter = 'all';

    function showQueue(filter) {
        currentQueueFilter = filter;

        // Update queue title
        const titles = {
            'all': '🟡 All Unpaid Items',
            'hmo': '🟢 HMO Items',
            'credit': '🟠 Credit Accounts',
        };
        $('#queue-view-title').html(`<i class="mdi mdi-format-list-bulleted"></i> ${titles[filter] || titles['all']}`);

        // Update active state on queue buttons
        $('.queue-item').removeClass('active');
        if (filter !== 'all') {
            $(`.queue-item[data-filter="${filter}"]`).addClass('active');
        }

        // Hide other views, show queue view
        hideAllViews();
        $('#queue-view').show().addClass('active');

        // On mobile, hide search pane and show main workspace
        if (window.innerWidth < 768) {
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        }

        // Initialize or reload DataTable
        initializeQueueDataTable(filter);
    }

    function hideQueue() {
        $('#queue-view').removeClass('active');
        $('.queue-item').removeClass('active');

        if (currentPatient) {
            // If patient was selected, show their workspace
            $('#patient-header').addClass('active');
            $('#workspace-content').addClass('active');
        } else {
            // Otherwise show empty state
            $('#empty-state').show();
        }

        // On mobile, go back to search pane
        if (window.innerWidth < 768) {
            $('#main-workspace').removeClass('active');
            $('#left-panel').removeClass('hidden');
        }
    }

    function initializeQueueDataTable(filter) {
        // Destroy existing DataTable if it exists
        if (queueDataTable) {
            queueDataTable.destroy();
        }

        // Initialize DataTable for payment queue
        queueDataTable = $('#queue-datatable').DataTable({
            ajax: {
                url: wbUrl('/billing-workbench/payment-queue'),
                data: {
                    filter: filter
                },
                dataSrc: ''
            },
            columns: [{
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    var emergencyBadge = row.is_emergency ?
                        '<span class=\"badge bg-danger me-2\"><i class=\"fa fa-bolt\"></i> EMERGENCY</span>' :
                        '';
                    var borderStyle = row.is_emergency ?
                        'border-left: 4px solid #dc3545; background: #fff8f8;' :
                        '';
                    return `
                        <div class="queue-patient-item" data-patient-id="${row.patient_id}" style="cursor: pointer; padding: 1rem; border-bottom: 1px solid #e9ecef; ${borderStyle}">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                ${emergencyBadge}
                                <div style="font-weight: 600; font-size: 1rem; color: #212529;">${row.patient_name}</div>
                                <span class="badge badge-primary">${row.file_no}</span>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                                <i class="mdi mdi-file-document-outline"></i> ${row.unpaid_count} unpaid item(s)
                                ${row.hmo_items> 0 ? `<span class="hmo-badge ml-2"><i class="mdi mdi-shield-check"></i> ${row.hmo_items} HMO</span>` : ''}
                                ${row.hmo ? `<br><small><i class="mdi mdi-hospital-building"></i> ${row.hmo}</small>` : ''}
                            </div>
                        </div>
                    `;
                }
            }],
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: false,
            info: true,
            responsive: true,
            language: {
                emptyTable: "No patients in this queue",
                zeroRecords: "No patients found",
                info: "Showing _START_ to _END_ of _TOTAL_ patients",
                infoEmpty: "No patients to show",
                infoFiltered: "(filtered from _MAX_ total patients)"
            }
        });

        // Click handler for patient selection from queue
        $('#queue-datatable').on('click', '.queue-patient-item', function() {
            const patientId = $(this).data('patient-id');
            hideQueue();
            loadPatient(patientId);
        });
    }

    // ==========================================
    // REPORTS VIEW FUNCTIONS
    // ==========================================

    function showReports() {
        // Hide all views to prevent stacking
        hideAllViews();

        // Show reports view
        $('#reports-view').show().addClass('active');

        // On mobile, switch to main workspace
        if (window.innerWidth < 768) {
            $('#left-panel').addClass('hidden');
            $('#main-workspace').addClass('active');
        }

        // Load statistics and initialize if not already done
        if (!window.reportsInitialized) {
            // Don't set default dates - load all records initially
            // setDefaultDateFilters();
            loadFilterOptions();
            loadReportsStatistics();
            initializeReportsDataTable();
            // initializeReportsCharts(); // TODO: Implement when Chart.js is added
            window.reportsInitialized = true;
        } else {
            // Refresh statistics
            loadReportsStatistics();
            if (window.reportsDataTable) {
                window.reportsDataTable.ajax.reload();
            }
        }
    }

    function hideReports() {
        $('#reports-view').removeClass('active');
        $('#empty-state').show();

        // On mobile, go back to search pane
        if (window.innerWidth < 768) {
            $('#main-workspace').removeClass('active');
            $('#left-panel').removeClass('hidden');
        }
    }

    function setDefaultDateFilters() {
        // Set date filters to current month
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        // Format as YYYY-MM-DD
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        $('#report-date-from').val(formatDate(firstDay));
        $('#report-date-to').val(formatDate(lastDay));
    }

    function loadFilterOptions() {
        // Load doctors
        $.ajax({
            url: wbRoute('lab.filterDoctors', '/lab/filterDoctors'),
            method: 'GET',
            success: function(doctors) {
                let options = '<option value="">All Doctors</option>';
                doctors.forEach(function(doctor) {
                    options += `<option value="${doctor.id}">${doctor.name}</option>`;
                });
                $('#report-doctor-filter').html(options);
            },
            error: function(xhr) {
                console.error('Failed to load doctors:', xhr);
            }
        });

        // Load HMOs with optgroups
        $.ajax({
            url: wbRoute('lab.filterHmos', '/lab/filterHmos'),
            method: 'GET',
            success: function(hmoGroups) {
                let options = '<option value="">All HMOs</option>';
                Object.keys(hmoGroups).forEach(function(schemeName) {
                    options += `<optgroup label="${schemeName}">`;
                    hmoGroups[schemeName].forEach(function(hmo) {
                        options += `<option value="${hmo.id}">${hmo.name}</option>`;
                    });
                    options += '</optgroup>';
                });
                $('#report-hmo-filter').html(options);
            },
            error: function(xhr) {
                console.error('Failed to load HMOs:', xhr);
            }
        });

        // Load services
        $.ajax({
            url: wbRoute('lab.filterServices', '/lab/filterServices'),
            method: 'GET',
            success: function(services) {
                let options = '<option value="">All Services</option>';
                services.forEach(function(service) {
                    options += `<option value="${service.id}">${service.name}</option>`;
                });
                $('#report-service-filter').html(options);
            },
            error: function(xhr) {
                console.error('Failed to load services:', xhr);
            }
        });
    }

    /* REPLACED BY NEW IMPLEMENTATION
    function loadReportsStatistics(filters = {}) {
        // If no filters provided, use current form values
        if (Object.keys(filters).length === 0) {
            filters = {
                date_from: $('#report-date-from').val(),
                date_to: $('#report-date-to').val(),
                status: $('#report-status-filter').val(),
                service_id: $('#report-service-filter').val(),
                doctor_id: $('#report-doctor-filter').val(),
                hmo_id: $('#report-hmo-filter').val(),
                patient_search: $('#report-patient-search').val()
            };
        }

        $.ajax({
            url: wbRoute('lab.statistics', '/lab/statistics'),
            method: 'GET',
            data: filters,
            success: function(data) {
                // Update summary cards
                $('#stat-total-requests').text(data.summary.total_requests);
                $('#stat-completed').text(data.summary.completed);
                $('#stat-pending').text(data.summary.pending);
                $('#stat-avg-tat').text(data.summary.avg_tat + 'h');

                // Store data for charts
                window.reportsData = data;

                // Update charts if they exist
                if (window.statusChart) {
                    updateStatusChart(data.by_status);
                }
                if (window.trendsChart) {
                    updateTrendsChart(data.monthly_trends);
                }

                // Update top services
                if (data.top_services && data.top_services.length> 0) {
                    let servicesHtml = '<ul class="list-group list-group-flush">';
                    data.top_services.forEach(function(service, index) {
                        const percentage = data.summary.total_requests> 0 ? Math.round((service.count / data.summary.total_requests) * 100) : 0;
                        servicesHtml += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div style="flex: 1; min-width: 0; margin-right: 15px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-truncate font-weight-bold" title="${service.service}">${index + 1}. ${service.service}</span>
                                    <span class="badge badge-primary badge-pill">${service.count}</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
                        </li>`;
                    });
                    servicesHtml += '</ul>';
                    $('#top-services-list').html(servicesHtml);
                } else {
                    $('#top-services-list').html('<p class="text-muted">No data available</p>');
                }
            },
            error: function(xhr) {
                console.error('Failed to load statistics:', xhr);
                toastr.error('Failed to load statistics');
            }
        });
    }
    */

    function initializeReportsDataTable() {
        if (window.reportsDataTable) {
            window.reportsDataTable.destroy();
        }

        window.reportsDataTable = $('#reports-datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: wbRoute('lab.reports', '/lab/reports'),
                data: function(d) {
                    // Add filter values to request
                    d.date_from = $('#report-date-from').val();
                    d.date_to = $('#report-date-to').val();
                    d.status = $('#report-status-filter').val();
                    d.service_id = $('#report-service-filter').val();
                    d.doctor_id = $('#report-doctor-filter').val();
                    d.hmo_id = $('#report-hmo-filter').val();
                    d.patient_search = $('#report-patient-search').val();

                    // Debug: Log what we're sending
                    console.log('DataTable AJAX params:', {
                        date_from: d.date_from,
                        date_to: d.date_to,
                        status: d.status,
                        service_id: d.service_id,
                        doctor_id: d.doctor_id,
                        hmo_id: d.hmo_id,
                        patient_search: d.patient_search
                    });
                }
            },
            columns: [{
                    data: 'created_at',
                    name: 'created_at'
                },
                {
                    data: 'file_no',
                    name: 'patient.file_no'
                },
                {
                    data: 'patient_name',
                    name: 'patient_name',
                    orderable: false
                },
                {
                    data: 'service_name',
                    name: 'service.service_name'
                },
                {
                    data: 'doctor_name',
                    name: 'doctor_name',
                    orderable: false
                },
                {
                    data: 'hmo_name',
                    name: 'hmo_name',
                    orderable: false
                },
                {
                    data: 'status_badge',
                    name: 'status',
                    orderable: false
                },
                {
                    data: 'tat',
                    name: 'tat',
                    orderable: false
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false
                }
            ],
            order: [
                [0, 'desc']
            ],
            pageLength: 25,
            responsive: false,
            scrollX: true
        });
    }

    function initializeReportsCharts() {
        // TODO: Implement Chart.js charts
        // - Status breakdown (bar chart)
        // - Monthly trends (line chart)
        console.log('Charts will be implemented with Chart.js');
    }

    function updateStatusChart(data) {
        // TODO: Update status chart with new data
    }

    function updateTrendsChart(data) {
        // TODO: Update trends chart with new data
    }

    function renderQueueCard(data) {
        // Status badges
        const statusBadges = getStatusBadges(data);

        // Patient meta
        const patientMeta = `
        <div class="queue-card-patient-meta">
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-account"></i>
                <span>${data.age} • ${data.gender}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-card-account-details"></i>
                <span>${data.file_no}</span>
            </div>
            <div class="queue-card-patient-meta-item">
                <i class="mdi mdi-hospital-building"></i>
                <span>${data.hmo}</span>
            </div>
        </div>
    `;

        // Note section
        const noteSection = data.note ? `
        <div class="queue-card-note">
            <div class="queue-card-note-label"><i class="mdi mdi-note-text"></i> Request Note</div>
            <div>${data.note}</div>
        </div>
    ` : '';

        // Attachments
        let attachmentsSection = '';
        if (data.attachments && data.attachments.length > 0) {
            const attachmentLinks = data.attachments.map(att => {
                const icon = getFileIcon(att.type);
                return `<a href="/storage/${att.path}" target="_blank" class="queue-card-attachment">
                ${icon} ${att.name}
            </a>`;
            }).join('');
            attachmentsSection = `
            <div class="queue-card-attachments">
                <strong><i class="mdi mdi-paperclip"></i> Attachments:</strong>
                ${attachmentLinks}
            </div>
        `;
        }

        // Result section
        const resultSection = data.result ? `
        <div class="queue-card-note" style="background: #f0f9ff; border-color: #0ea5e9;">
            <div class="queue-card-note-label" style="color: #0ea5e9;"><i class="mdi mdi-flask"></i> Result</div>
            <div>${data.result}</div>
        </div>
    ` : '';

        const emergencyBadge = data.is_emergency ?
            '<span class="badge bg-danger mb-1"><i class="fa fa-bolt"></i> EMERGENCY</span> ' :
            '';
        const emergencyBorderStyle = data.is_emergency ? 'border-left: 4px solid #dc3545;' : '';

        return `
        <div class="queue-card" data-patient-id="${data.patient_id}" style="${emergencyBorderStyle}">
            <div class="queue-card-header">
                <div class="queue-card-patient">
                    <div class="queue-card-patient-name">${emergencyBadge}${data.patient_name}</div>
                    ${patientMeta}
                </div>
                <div class="queue-card-service">${data.service_name}</div>
            </div>
            <div class="queue-card-body">
                <div class="queue-card-status-row">
                    ${statusBadges}
                </div>
                ${noteSection}
                ${resultSection}
                ${attachmentsSection}
            </div>
            <div class="queue-card-actions">
                <button class="btn btn-primary btn-select-patient-from-queue" data-patient-id="${data.patient_id}">
                    <i class="mdi mdi-account-arrow-right"></i> Select Patient
                </button>
            </div>
        </div>
    `;
    }

    function getStatusBadges(data) {
        let badges = '';

        // Requested
        badges += `
        <div class="queue-card-status-item completed">
            <div class="queue-card-status-label"><i class="mdi mdi-calendar-check"></i> Requested</div>
            <div class="queue-card-status-value">${data.requested_by}<br><small>${data.requested_at}</small></div>
        </div>
    `;

        // Billing
        if (data.billed_by && data.billed_at) {
            badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billed</div>
                <div class="queue-card-status-value">${data.billed_by}<br><small>${data.billed_at}</small></div>
            </div>
        `;
        } else {
            badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-cash-register"></i> Billing</div>
                <div class="queue-card-status-value">Awaiting billing</div>
            </div>
        `;
        }

        // Sample
        if (data.sample_taken_by && data.sample_taken_at) {
            badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample Taken</div>
                <div class="queue-card-status-value">${data.sample_taken_by}<br><small>${data.sample_taken_at}</small></div>
            </div>
        `;
        } else if (data.status >= 2) {
            badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-test-tube"></i> Sample</div>
                <div class="queue-card-status-value">Awaiting sample collection</div>
            </div>
        `;
        }

        // Result
        if (data.result_by && data.result_at) {
            badges += `
            <div class="queue-card-status-item completed">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">${data.result_by}<br><small>${data.result_at}</small></div>
            </div>
        `;
        } else if (data.status >= 3) {
            badges += `
            <div class="queue-card-status-item pending">
                <div class="queue-card-status-label"><i class="mdi mdi-flask"></i> Result</div>
                <div class="queue-card-status-value">Awaiting result entry</div>
            </div>
        `;
        }

        return badges;
    }

    function getFileIcon(extension) {
        const icons = {
            'pdf': '<i class="mdi mdi-file-pdf"></i>',
            'doc': '<i class="mdi mdi-file-word"></i>',
            'docx': '<i class="mdi mdi-file-word"></i>',
            'jpg': '<i class="mdi mdi-file-image"></i>',
            'jpeg': '<i class="mdi mdi-file-image"></i>',
            'png': '<i class="mdi mdi-file-image"></i>',
        };
        return icons[extension] || '<i class="mdi mdi-file"></i>';
    }

    // Handle patient selection from queue
    $(document).on('click', '.btn-select-patient-from-queue', function() {
        const patientId = $(this).data('patient-id');
        loadPatient(patientId);
        hideQueue();
    });

    // ==========================================
    // REPORTS VIEW EVENT HANDLERS
    // ==========================================

    // Open reports view
    $('#btn-view-reports').on('click', function() {
        showReports();
    });

    // Close reports view
    $('#btn-close-reports').on('click', function() {
        hideReports();
    });

    // Reports filter form submission
    $('#reports-filter-form').on('submit', function(e) {
        e.preventDefault();

        // Reload statistics with filters
        const filters = {
            date_from: $('#report-date-from').val(),
            date_to: $('#report-date-to').val(),
            status: $('#report-status-filter').val(),
            service_id: $('#report-service-filter').val(),
            doctor_id: $('#report-doctor-filter').val(),
            hmo_id: $('#report-hmo-filter').val(),
            patient_search: $('#report-patient-search').val()
        };

        loadReportsStatistics(filters);

        // Reload DataTable
        if (window.reportsDataTable) {
            window.reportsDataTable.ajax.reload();
        }
    });

    // Clear reports filters
    $('#clear-report-filters').on('click', function() {
        $('#reports-filter-form')[0].reset();
        loadReportsStatistics();
        if (window.reportsDataTable) {
            window.reportsDataTable.ajax.reload();
        }
    });

    // Export buttons (TODO: Implement with DataTables buttons extension)
    $('#export-excel').on('click', function() {
        toastr.info('Excel export will be implemented with DataTables buttons extension');
    });

    $('#export-pdf').on('click', function() {
        toastr.info('PDF export will be implemented with DataTables buttons extension');
    });

    $('#print-report').on('click', function() {
        toastr.info('Print functionality will be implemented with DataTables buttons extension');
    });

    // Show new request button when patient is selected
    function updateQuickActions() {
        if (currentPatient) {
            $('#btn-new-request').show();
        } else {
            $('#btn-new-request').hide();
        }
    }

    // New request button handler
    $('#btn-new-request').on('click', function() {
        if (!currentPatient) {
            toastr.warning('Please select a patient first');
            return;
        }
        switchWorkspaceTab('new-request');
        $('#new-request-patient-name').text(currentPatient.name);
    });

    // ==========================================
    // REPORTS & ANALYTICS HELPER FUNCTIONS
    // ==========================================

    function loadReportsStatistics(filters = {}) {
        // Show loading state
        $('#stat-total-requests').text('Loading...');
        $('#stat-completed').text('Loading...');
        $('#stat-pending').text('Loading...');
        $('#stat-avg-tat').text('Loading...');

        $.ajax({
            url: wbRoute('lab.statistics', '/lab/statistics'),
            method: 'GET',
            data: filters,
            success: function(response) {
                // Update Summary Cards
                updateSummaryCards(response.summary);

                // Render Top Services
                renderTopServices(response.top_services, response.summary ? response.summary.total_requests : 0);

                // Render Top Doctors
                renderTopDoctors(response.top_doctors);

                // Initialize/Update Charts
                initializeReportsCharts(response.by_status, response.monthly_trends);
            },
            error: function(xhr) {
                console.error('Error loading statistics:', xhr);
                toastr.error('Failed to load report statistics');
            }
        });
    }

    function updateSummaryCards(summary) {
        if (!summary) return;

        $('#stat-total-requests').text(summary.total_requests || 0);
        $('#stat-completed').text(summary.completed_requests || 0);
        $('#stat-pending').text(summary.pending_requests || 0);

        // Update Avg TAT
        $('#stat-avg-tat').text((summary.avg_tat || 0) + ' hrs');
    }

    function renderTopServices(services, totalRequests = 0) {
        const container = $('#top-services-list');

        if (!services || services.length === 0) {
            container.html('<p class="text-muted text-center p-3">No data available for the selected period.</p>');
            return;
        }

        let html = '<ul class="list-group list-group-flush">';

        services.forEach((service, index) => {
            const percentage = totalRequests > 0 ? Math.round((service.count / totalRequests) * 100) : 0;

            html += `<li class="list-group-item d-flex justify-content-between align-items-center px-0">
            <div style="flex: 1; min-width: 0; margin-right: 15px;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-truncate font-weight-bold" title="${service.name}">${index + 1}. ${service.name}</span>
                    <span class="badge badge-primary badge-pill">${service.count}</span>
                </div>
                <div class="progress" style="height: 6px;">
                    <div class="progress-bar bg-info" role="progressbar" style="width: ${percentage}%" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
            <small class="text-muted" style="min-width: 35px; text-align: right;">${percentage}%</small>
        </li>`;
        });

        html += '</ul>';
        container.html(html);
    }

    function renderTopDoctors(doctors) {
        const container = $('#top-doctors-list');

        if (!doctors || doctors.length === 0) {
            container.html('<p class="text-muted text-center p-3">No data available.</p>');
            return;
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-sm"><thead><tr><th>Doctor Name</th><th class="text-center">Requests</th><th class="text-right">Total Revenue</th></tr></thead><tbody>';

        doctors.forEach(doc => {
            const docName = doc.doctor ? (doc.doctor.firstname + ' ' + doc.doctor.surname) : 'Unknown';
            html += `
            <tr>
                <td><i class="mdi mdi-doctor mr-1"></i> ${docName}</td>
                <td class="text-center"><span class="badge badge-pill badge-info">${doc.count}</span></td>
                <td class="text-right">₦${parseFloat(doc.revenue).toLocaleString()}</td>
            </tr>
        `;
        });

        html += '</tbody></table></div>';
        container.html(html);
    }

    let statusChartInstance = null;
    let trendsChartInstance = null;

    function initializeReportsCharts(byStatus, monthlyTrends) {
        // 1. Status Chart (Doughnut)
        const statusCtx = document.getElementById('status-chart').getContext('2d');

        // Destroy existing chart if it exists
        if (statusChartInstance) {
            statusChartInstance.destroy();
        }

        const statusLabels = [];
        const statusData = [];
        const statusColors = [];

        // Map status IDs to names and colors
        const statusMap = {
            1: {
                name: 'Awaiting Billing',
                color: '#ffc107'
            },
            2: {
                name: 'Awaiting Sample',
                color: '#17a2b8'
            },
            3: {
                name: 'Awaiting Results',
                color: '#007bff'
            },
            4: {
                name: 'Completed',
                color: '#28a745'
            }
        };

        if (byStatus && byStatus.length > 0) {
            byStatus.forEach(item => {
                const info = statusMap[item.status] || {
                    name: 'Unknown',
                    color: '#6c757d'
                };
                statusLabels.push(info.name);
                statusData.push(item.count);
                statusColors.push(info.color);
            });
        } else {
            // Empty state
            statusLabels.push('No Data');
            statusData.push(0);
            statusColors.push('#e9ecef');
        }

        statusChartInstance = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'right'
                }
            }
        });

        // 2. Monthly Trends Chart (Line)
        const trendsCtx = document.getElementById('trends-chart').getContext('2d');

        if (trendsChartInstance) {
            trendsChartInstance.destroy();
        }

        const trendLabels = [];
        const trendData = [];

        if (monthlyTrends && monthlyTrends.length > 0) {
            monthlyTrends.forEach(item => {
                trendLabels.push(item.month);
                trendData.push(item.count);
            });
        }

        trendsChartInstance = new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Requests',
                    data: trendData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            precision: 0
                        }
                    }]
                }
            }
        });
    }

    // ==========================================
    // MODAL CLEANUP - Fix backdrop/body scroll issues
    // ==========================================
    $(document).on('hidden.bs.modal', '.modal', function() {
        // Remove any lingering backdrops
        $('.modal-backdrop').remove();

        // Remove modal-open class if no modals are visible
        if ($('.modal:visible').length === 0) {
            $('body').removeClass('modal-open');
            $('body').css({
                'overflow': '',
                'padding-right': ''
            });
        }
    });

    // Ensure body scroll is restored when modal closes
    $('#receiptPreviewModal, #accountStatementModal').on('hidden.bs.modal', function() {
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
        $('.modal-backdrop').remove();

        // Reset receipt modal title back to default
        if ($(this).attr('id') === 'receiptPreviewModal') {
            $(this).find('.modal-title').text('Receipt Preview');
        }
    });

    function buildFamilyTabs(patient, items) {
        const tabsContainer = $('#billing-family-tabs');
        tabsContainer.empty();

        if (!patient.family_members || patient.family_members.length <= 1) {
            tabsContainer.hide();
            return;
        }

        tabsContainer.show();

        // Add "Me" tab (active by default)
        tabsContainer.append(`
        <button class="workspace-tab active" data-user-id="${patient.user_id}">
            <i class="mdi mdi-account"></i>
            <span>Me</span>
        </button>
    `);

        // Add "All Family Members" tab
        tabsContainer.append(`
        <button class="workspace-tab" data-user-id="all">
            <i class="mdi mdi-format-list-bulleted"></i>
            <span>All Family Members</span>
        </button>
    `);

        // Add individual family members (excluding the current patient)
        patient.family_members.forEach(member => {
            if (member.user_id === patient.user_id) return;

            const hasItems = items.some(item => item.user_id == member.user_id);
            const icon = member.is_principal ? 'mdi-account-star' : 'mdi-account-outline';
            const badge = hasItems ? '<span class="workspace-tab-badge" style="display:inline-block; margin-left: 5px; background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem;">!</span>' : '';

            tabsContainer.append(`
            <button class="workspace-tab" data-user-id="${member.user_id}">
                <i class="mdi ${icon}"></i>
                <span>${member.name}</span>
                ${badge}
            </button>
        `);
        });

        // Handle tab clicks
        tabsContainer.find('.workspace-tab').on('click', function() {
            tabsContainer.find('.workspace-tab').removeClass('active');
            $(this).addClass('active');
            applyBillingFilters();
            updatePaymentSummary();
        });
    }

    // =============================================
    // BILLING SHIFT MANAGEMENT MODULE
    // =============================================

    const BillingShiftManager = {
        // State
        activeShift: null,
        shiftTimer: null,

        // Routes
        routes: {
            check: '/billing-workbench/active-shift',
            start: '/billing-workbench/start-shift',
            end: '/billing-workbench/end-shift'
        },

        // Initialize
        init: function() {
            this.bindEvents();
            this.checkShiftStatus();
            this.makeFabDraggable();
        },

        // Bind event handlers
        bindEvents: function() {
            const self = this;

            // Start shift button (on lock overlay)
            $('#start-shift-btn').on('click', function() {
                self.showStartShiftModal();
            });

            // Confirm start shift
            $('#confirm-start-shift-btn').on('click', function() {
                self.startShift();
            });

            // End shift button (FAB)
            $('#shift-fab-btn').on('click', function() {
                self.showEndShiftModal();
            });

            // Confirm end shift
            $('#confirm-end-shift-btn').on('click', function() {
                self.endShift();
            });
        },

        // Check shift status on load
        checkShiftStatus: function() {
            const self = this;

            $.ajax({
                url: this.routes.check,
                type: 'GET',
                success: function(response) {
                    if (response.active) {
                        self.activeShift = response.shift;
                        self.showWorkbench();
                        self.startShiftTimer();
                    } else {
                        self.showLockOverlay();
                    }
                },
                error: function() {
                    // If check fails, show workbench anyway to prevent locking out on error
                    self.showWorkbench();
                }
            });
        },

        // Show lock overlay
        showLockOverlay: function() {
            $('#shift-lock-overlay').show();
            $('#shift-control-fab').hide();
        },

        // Show workbench (unlock)
        showWorkbench: function() {
            $('#shift-lock-overlay').hide();
            $('#shift-control-fab').show();
        },

        // Show start shift modal
        showStartShiftModal: function() {
            // Temporarily hide overlay so modal is visible clearly
            $('#shift-lock-overlay').addClass('modal-open-hidden');
            $('#startShiftModal').modal('show');
        },

        // Start shift action
        startShift: function() {
            const self = this;
            const shiftType = $('#shift-type-select').val();

            $('#confirm-start-shift-btn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Starting...');

            $.ajax({
                url: this.routes.start,
                type: 'POST',
                data: {
                    shift_type: shiftType,
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
                },
                success: function(response) {
                    $('#confirm-start-shift-btn').prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');

                    if (response.success) {
                        toastr.success('Billing shift started successfully');
                        $('#startShiftModal').modal('hide');
                        $('#shift-lock-overlay').removeClass('modal-open-hidden');

                        self.activeShift = response.shift;
                        self.showWorkbench();
                        self.startShiftTimer();
                    } else {
                        toastr.error(response.message || 'Failed to start shift');
                        $('#shift-lock-overlay').removeClass('modal-open-hidden');
                    }
                },
                error: function(xhr) {
                    $('#confirm-start-shift-btn').prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                    toastr.error(xhr.responseJSON?.message || 'Failed to start shift');
                    $('#shift-lock-overlay').removeClass('modal-open-hidden');
                }
            });
        },

        // Show end shift modal
        showEndShiftModal: function() {
            $('#endShiftModal').modal('show');
        },

        // End shift action
        endShift: function() {
            const self = this;

            $('#confirm-end-shift-btn').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Ending...');

            $.ajax({
                url: this.routes.end,
                type: 'POST',
                data: {
                    _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content'))
                },
                success: function(response) {
                    $('#confirm-end-shift-btn').prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');

                    if (response.success) {
                        toastr.success('Billing shift ended successfully');
                        $('#endShiftModal').modal('hide');

                        self.stopShiftTimer();
                        self.activeShift = null;
                        self.showLockOverlay();

                        // Show a summary toast
                        toastr.info(`Shift ended. Collected: ₦${response.shift.total_collected} from ${response.shift.payments_count} payments.`, 'Shift Summary', {
                            timeOut: 10000
                        });
                    } else {
                        toastr.error(response.message || 'Failed to end shift');
                    }
                },
                error: function(xhr) {
                    $('#confirm-end-shift-btn').prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
                    toastr.error(xhr.responseJSON?.message || 'Failed to end shift');
                }
            });
        },

        // Shift Timer
        startShiftTimer: function() {
            const self = this;
            this.stopShiftTimer(); // Clear any existing timer

            this.updateTimerDisplay(); // Initial update

            this.shiftTimer = setInterval(function() {
                if (self.activeShift) {
                    // Increment elapsed seconds
                    if (typeof self.activeShift.elapsed_seconds !== 'undefined') {
                        self.activeShift.elapsed_seconds++;
                    } else {
                        self.activeShift.elapsed_seconds = 1;
                    }
                    self.updateTimerDisplay();
                }
            }, 1000);
        },

        stopShiftTimer: function() {
            if (this.shiftTimer) {
                clearInterval(this.shiftTimer);
                this.shiftTimer = null;
            }
        },

        updateTimerDisplay: function() {
            if (!this.activeShift || typeof this.activeShift.elapsed_seconds === 'undefined') return;

            const totalSeconds = this.activeShift.elapsed_seconds;
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            const formatted = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            $('#shift-elapsed-time').text(formatted);

            // Highlight if overdue (> 12 hours)
            if (hours >= 12) {
                $('.shift-fab-timer').addClass('overdue');
            } else {
                $('.shift-fab-timer').removeClass('overdue');
            }
        },

        // Make FAB draggable
        makeFabDraggable: function() {
            const fab = document.getElementById('shift-control-fab');
            if (!fab) return;

            let isDragging = false;
            let currentX;
            let currentY;
            let initialX;
            let initialY;
            let xOffset = 0;
            let yOffset = 0;
            let isClick = false;
            let dragStartTime;

            fab.addEventListener("touchstart", dragStart, {
                passive: false
            });
            fab.addEventListener("touchend", dragEnd, {
                passive: false
            });
            fab.addEventListener("touchmove", drag, {
                passive: false
            });

            fab.addEventListener("mousedown", dragStart, false);
            document.addEventListener("mouseup", dragEnd, false);
            document.addEventListener("mousemove", drag, false);

            function dragStart(e) {
                if (e.type === "touchstart") {
                    initialX = e.touches[0].clientX - xOffset;
                    initialY = e.touches[0].clientY - yOffset;
                } else {
                    initialX = e.clientX - xOffset;
                    initialY = e.clientY - yOffset;
                }

                // Only start drag if not clicking a button directly
                if (!e.target.closest('button')) {
                    isDragging = true;
                    isClick = true;
                    dragStartTime = new Date().getTime();
                }
            }

            function dragEnd(e) {
                initialX = currentX;
                initialY = currentY;
                isDragging = false;

                // If dragging occurred for more than 200ms or moved significantly, prevent click
                const dragDuration = new Date().getTime() - dragStartTime;
                if (dragDuration > 200) {
                    isClick = false;
                }
            }

            function drag(e) {
                if (isDragging) {
                    e.preventDefault();

                    isClick = false; // Moved, so it's not a click

                    if (e.type === "touchmove") {
                        currentX = e.touches[0].clientX - initialX;
                        currentY = e.touches[0].clientY - initialY;
                    } else {
                        currentX = e.clientX - initialX;
                        currentY = e.clientY - initialY;
                    }

                    xOffset = currentX;
                    yOffset = currentY;

                    setTranslate(currentX, currentY, fab);
                }
            }

            function setTranslate(xPos, yPos, el) {
                el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
            }
        }
    };
