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
