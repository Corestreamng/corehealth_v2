{{--
    Reusable Partial for Injection & Immunization History

    Required variables:
    - $patient: The patient model instance

    Usage:
    @include('admin.patients.partials.injection_immunization_history', ['patient' => $patient])
--}}

@php
    $uniqueId = 'inj_imm_' . uniqid();
@endphp

<div class="injection-immunization-history-container" id="{{ $uniqueId }}-container" data-patient-id="{{ $patient->id }}">
    <!-- Sub-tabs for Injection & Immunization History -->
    <ul class="nav nav-tabs mb-3" id="{{ $uniqueId }}-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="{{ $uniqueId }}-injection-tab" data-bs-toggle="tab" href="#{{ $uniqueId }}-injection" role="tab">
                <i class="mdi mdi-needle me-1"></i> Injection History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="{{ $uniqueId }}-immunization-tab" data-bs-toggle="tab" href="#{{ $uniqueId }}-immunization" role="tab">
                <i class="mdi mdi-medical-bag me-1"></i> Immunization History
            </a>
        </li>
    </ul>

    <div class="tab-content" id="{{ $uniqueId }}-content">
        <!-- Injection History Tab -->
        <div class="tab-pane fade show active" id="{{ $uniqueId }}-injection" role="tabpanel">
            <div class="card-modern">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-history"></i> Injection History</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary refresh-injection-btn" data-target="{{ $uniqueId }}">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="{{ $uniqueId }}-injection-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Drug</th>
                                    <th>Dose</th>
                                    <th>Route</th>
                                    <th>Site</th>
                                    <th>Nurse</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Immunization History Tab -->
        <div class="tab-pane fade" id="{{ $uniqueId }}-immunization" role="tabpanel">
            <div class="card-modern">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="mdi mdi-history"></i> Immunization History & Timeline</h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active imm-view-btn" data-target="{{ $uniqueId }}" data-view="timeline">
                                <i class="mdi mdi-chart-timeline-variant"></i> Timeline
                            </button>
                            <button type="button" class="btn btn-outline-primary imm-view-btn" data-target="{{ $uniqueId }}" data-view="calendar">
                                <i class="mdi mdi-calendar-month"></i> Calendar
                            </button>
                            <button type="button" class="btn btn-outline-primary imm-view-btn" data-target="{{ $uniqueId }}" data-view="table">
                                <i class="mdi mdi-table"></i> Table
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- History Views Container -->
                    <div class="immunization-views-container">
                        <!-- Timeline View (Default) -->
                        <div class="imm-history-view" id="{{ $uniqueId }}-timeline-view">
                            <div class="text-center py-4">
                                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                                <p class="text-muted mt-2">Loading timeline...</p>
                            </div>
                        </div>

                        <!-- Calendar View -->
                        <div class="imm-history-view d-none" id="{{ $uniqueId }}-calendar-view">
                            <div class="text-center py-4">
                                <i class="mdi mdi-calendar-month mdi-48px text-muted"></i>
                                <p class="text-muted mt-2">Loading calendar...</p>
                            </div>
                        </div>

                        <!-- Table View -->
                        <div class="imm-history-view d-none" id="{{ $uniqueId }}-table-view">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" id="{{ $uniqueId }}-immunization-table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Vaccine</th>
                                            <th>Dose #</th>
                                            <th>Dose Amount</th>
                                            <th>Batch</th>
                                            <th>Site</th>
                                            <th>Nurse</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Inline script that waits for jQuery to be available --}}
<script>
(function initInjImmHistory() {
    // Wait for jQuery to be available
    if (typeof jQuery === 'undefined') {
        setTimeout(initInjImmHistory, 100);
        return;
    }

    const $ = jQuery;
    const uniqueId = '{{ $uniqueId }}';
    const patientId = '{{ $patient->id }}';
    let injectionTableInitialized = false;
    let immunizationTableInitialized = false;

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Load injection history by default
        loadInjectionHistoryPartial();

        // Tab shown event - load data when tab is shown
        $(`#${uniqueId}-tabs a[data-bs-toggle="tab"]`).on('shown.bs.tab', function (e) {
            const target = $(e.target).attr('href');
            if (target === `#${uniqueId}-injection`) {
                if (!injectionTableInitialized) {
                    loadInjectionHistoryPartial();
                }
            } else if (target === `#${uniqueId}-immunization`) {
                loadImmunizationTimelinePartial();
            }
        });

        // Refresh injection button
        $(`#${uniqueId}-container`).on('click', '.refresh-injection-btn', function() {
            loadInjectionHistoryPartial();
        });

        // View toggle for immunization
        $(`#${uniqueId}-container`).on('click', '.imm-view-btn', function() {
            const view = $(this).data('view');
            const target = $(this).data('target');

            // Update button states
            $(`#${target}-container .imm-view-btn`).removeClass('active');
            $(this).addClass('active');

            // Show/hide views
            $(`#${target}-container .imm-history-view`).addClass('d-none');
            $(`#${target}-${view}-view`).removeClass('d-none');

            // Load the appropriate view
            if (view === 'timeline') {
                loadImmunizationTimelinePartial();
            } else if (view === 'calendar') {
                loadImmunizationCalendarPartial();
            } else if (view === 'table') {
                loadImmunizationTablePartial();
            }
        });
    });

    // Load Injection History
    function loadInjectionHistoryPartial() {
        const tableId = `#${uniqueId}-injection-table`;

        // Wait for DataTable to be available
        if (typeof $.fn.DataTable === 'undefined') {
            setTimeout(loadInjectionHistoryPartial, 100);
            return;
        }

        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        $(tableId).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: `/nursing-workbench/patient/${patientId}/injections`,
                dataSrc: ''
            },
            columns: [
                {
                    data: 'administered_at',
                    render: function(data) {
                        return data || 'N/A';
                    }
                },
                {
                    data: 'product_name',
                    render: function(data) {
                        return data || 'N/A';
                    }
                },
                {
                    data: 'dose',
                    defaultContent: 'N/A'
                },
                {
                    data: 'route',
                    defaultContent: 'N/A'
                },
                {
                    data: 'site',
                    defaultContent: 'N/A'
                },
                {
                    data: 'administered_by',
                    defaultContent: 'N/A'
                }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                emptyTable: "No injection history found",
                processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
            }
        });

        injectionTableInitialized = true;
    }

    // Load Immunization Timeline
    function loadImmunizationTimelinePartial() {
        const container = $(`#${uniqueId}-timeline-view`);
        container.html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading timeline...</p>
            </div>
        `);

        $.ajax({
            url: `/nursing-workbench/patient/${patientId}/immunization-history`,
            method: 'GET',
            success: function(response) {
                if (!response.records || response.records.length === 0) {
                    container.html(`
                        <div class="text-center py-4">
                            <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                            <p class="text-muted mt-2">No immunization records found</p>
                        </div>
                    `);
                    return;
                }

                let html = '<div class="timeline-container" style="position: relative; padding-left: 30px; border-left: 3px solid #dee2e6;">';

                response.records.forEach((record, index) => {
                    const statusClass = 'success';
                    html += `
                        <div class="timeline-item mb-3" style="position: relative;">
                            <div class="timeline-marker" style="position: absolute; left: -40px; width: 20px; height: 20px; border-radius: 50%; background: var(--${statusClass}); border: 3px solid white; box-shadow: 0 0 0 3px var(--${statusClass});"></div>
                            <div class="card-modern">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>${record.vaccine_name}</strong>
                                            <span class="badge bg-success ms-2">${record.dose_number || 'N/A'}</span>
                                            <br>
                                            <small class="text-muted">
                                                <i class="mdi mdi-calendar"></i> ${record.administered_date}
                                                ${record.site ? `| <i class="mdi mdi-map-marker"></i> ${record.site}` : ''}
                                                ${record.batch_number ? `| <i class="mdi mdi-barcode"></i> ${record.batch_number}` : ''}
                                            </small>
                                        </div>
                                        <small class="text-muted">${record.administered_by || ''}</small>
                                    </div>
                                    ${record.notes ? `<small class="text-muted d-block mt-1"><i class="mdi mdi-note"></i> ${record.notes}</small>` : ''}
                                </div>
                            </div>
                        </div>`;
                });

                html += '</div>';
                container.html(html);
            },
            error: function() {
                container.html(`
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle"></i> Failed to load timeline
                    </div>
                `);
            }
        });
    }

    // Load Immunization Calendar
    function loadImmunizationCalendarPartial() {
        const container = $(`#${uniqueId}-calendar-view`);
        container.html(`
            <div class="text-center py-4">
                <i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i>
                <p class="text-muted mt-2">Loading calendar...</p>
            </div>
        `);

        $.ajax({
            url: `/nursing-workbench/patient/${patientId}/immunization-history`,
            method: 'GET',
            success: function(response) {
                if (!response.records || response.records.length === 0) {
                    container.html(`
                        <div class="text-center py-4">
                            <i class="mdi mdi-calendar-blank mdi-48px text-muted"></i>
                            <p class="text-muted mt-2">No immunization records found</p>
                        </div>
                    `);
                    return;
                }

                // Group by month/year
                const grouped = {};
                response.records.forEach(record => {
                    const date = new Date(record.administered_date);
                    const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                    if (!grouped[key]) {
                        grouped[key] = {
                            label: date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
                            records: []
                        };
                    }
                    grouped[key].records.push(record);
                });

                let html = '<div class="row">';
                Object.keys(grouped).sort().reverse().forEach(key => {
                    const group = grouped[key];
                    html += `
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card-modern h-100">
                                <div class="card-header bg-primary text-white py-2">
                                    <i class="mdi mdi-calendar-month"></i> ${group.label}
                                </div>
                                <div class="card-body py-2">
                                    <ul class="list-unstyled mb-0">`;
                    group.records.forEach(record => {
                        const day = new Date(record.administered_date).getDate();
                        html += `
                            <li class="mb-1">
                                <span class="badge bg-secondary me-1">${day}</span>
                                <span>${record.vaccine_name}</span>
                                <small class="text-muted">(${record.dose_number || 'N/A'})</small>
                            </li>`;
                    });
                    html += `
                                    </ul>
                                </div>
                            </div>
                        </div>`;
                });
                html += '</div>';

                container.html(html);
            },
            error: function() {
                container.html(`
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle"></i> Failed to load calendar
                    </div>
                `);
            }
        });
    }

    // Load Immunization Table
    function loadImmunizationTablePartial() {
        const tableId = `#${uniqueId}-immunization-table`;

        // Wait for DataTable to be available
        if (typeof $.fn.DataTable === 'undefined') {
            setTimeout(loadImmunizationTablePartial, 100);
            return;
        }

        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        $(tableId).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: `/nursing-workbench/patient/${patientId}/immunization-history`,
                dataSrc: 'records'
            },
            columns: [
                {
                    data: 'administered_date',
                    render: function(data) {
                        return data || 'N/A';
                    }
                },
                {
                    data: 'vaccine_name',
                    render: function(data) {
                        return data || 'N/A';
                    }
                },
                {
                    data: 'dose_number',
                    render: function(data) {
                        return data ? `Dose ${data}` : 'N/A';
                    }
                },
                {
                    data: 'dose',
                    defaultContent: 'N/A'
                },
                {
                    data: 'batch_number',
                    defaultContent: 'N/A'
                },
                {
                    data: 'site',
                    defaultContent: 'N/A'
                },
                {
                    data: 'administered_by',
                    defaultContent: 'N/A'
                }
            ],
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            language: {
                emptyTable: "No immunization records found",
                processing: '<i class="mdi mdi-loading mdi-spin"></i> Loading...'
            }
        });

        immunizationTableInitialized = true;
    }
})();
</script>
