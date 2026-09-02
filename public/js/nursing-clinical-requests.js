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

// =============================================
// INTAKE & OUTPUT CHART FUNCTIONS
// =============================================

function loadFluidPeriods() {
    console.log('loadFluidPeriods called, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        console.log('No PATIENT_ID, returning');
        return;
    }

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#fluid_start_date').val();
    const endDate = $('#fluid_end_date').val();
    console.log('Fetching from:', url, 'with dates:', startDate, endDate);

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'fluid', start_date: startDate, end_date: endDate },
        success: function(data) {
            console.log('loadFluidPeriods response:', data);
            fluidPeriods = data.fluidPeriods || [];
            console.log('fluidPeriods array:', fluidPeriods);
            renderFluidPeriods();
        },
        error: function(xhr) {
            console.error('loadFluidPeriods error:', xhr);
            $('#fluid-periods-list').html('<p class="text-danger">Failed to load fluid data.</p>');
        }
    });
}

function loadSolidPeriods() {
    if (!PATIENT_ID) return;

    const url = intakeOutputChartIndexRoute.replace(':patient', PATIENT_ID);
    const startDate = $('#solid_start_date').val();
    const endDate = $('#solid_end_date').val();

    $.ajax({
        url: url,
        type: 'GET',
        data: { type: 'solid', start_date: startDate, end_date: endDate },
        success: function(data) {
            solidPeriods = data.solidPeriods || [];
            renderSolidPeriods();
        },
        error: function() {
            $('#solid-periods-list').html('<p class="text-danger">Failed to load solid data.</p>');
        }
    });
}

function renderFluidPeriods() {
    console.log('renderFluidPeriods called, fluidPeriods:', fluidPeriods);
    if (fluidPeriods.length === 0) {
        console.log('No periods, showing empty message');
        $('#fluid-periods-list').html('<p class="text-muted">No fluid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    fluidPeriods.forEach(period => {
        console.log('Rendering period:', period);
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length> 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-primary">Intake</span>'
                    : '<span class="badge bg-warning text-dark">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="fluid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} ml</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-success' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-primary add-fluid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-fluid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-primary mb-0"><i class="mdi mdi-water"></i> Intake: ${totalIntake} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-warning mb-0"><i class="mdi mdi-water-off"></i> Output: ${totalOutput} ml</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance>= 0 ? 'text-success' : 'text-danger'}">${balance} ml</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#fluid-periods-list').html(html);
}

function renderSolidPeriods() {
    if (solidPeriods.length === 0) {
        $('#solid-periods-list').html('<p class="text-muted">No solid intake/output periods found. Click "Start New Period" to begin.</p>');
        return;
    }

    let html = '';
    solidPeriods.forEach(period => {
        const isActive = !period.ended_at;
        const statusBadge = isActive ? '<span class="badge bg-info">Active</span>' : '<span class="badge bg-secondary">Ended</span>';
        const totalIntake = period.total_intake || 0;
        const totalOutput = period.total_output || 0;
        const balance = totalIntake - totalOutput;

        // Build records table
        let recordsHtml = '';
        if (period.records && period.records.length> 0) {
            recordsHtml = `
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Nurse</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>`;
            period.records.forEach(record => {
                const recordTime = new Date(record.recorded_at);
                const typeBadge = record.type === 'intake'
                    ? '<span class="badge bg-success">Intake</span>'
                    : '<span class="badge bg-danger">Output</span>';
                const deleteBtn = record.can_delete
                    ? `<button class="btn btn-sm btn-outline-danger delete-io-record-btn" data-record-id="${record.id}" data-type="solid" title="Delete record"><i class="mdi mdi-delete"></i></button>`
                    : '';
                recordsHtml += `
                    <tr>
                        <td><small>${formatDateTime(recordTime)}</small></td>
                        <td>${typeBadge}</td>
                        <td>${record.amount} g</td>
                        <td>${record.description || '-'}</td>
                        <td><small>${record.nurse_name || 'Unknown'}</small></td>
                        <td class="text-end">${deleteBtn}</td>
                    </tr>`;
            });
            recordsHtml += '</tbody></table></div>';
        } else {
            recordsHtml = '<div class="text-muted small mt-2"><em>No records yet. Click "Add Record" to add intake/output.</em></div>';
        }

        html += `
            <div class="card-modern period-card mb-3 ${isActive ? 'border-info' : ''}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><strong>Period:</strong> ${formatDateTime(new Date(period.started_at))} ${statusBadge}</span>
                    <div>
                        ${isActive ? `<button class="btn btn-sm btn-success add-solid-record-btn" data-period-id="${period.id}"><i class="mdi mdi-plus"></i> Add Record</button>
                        <button class="btn btn-sm btn-warning end-solid-period-btn" data-period-id="${period.id}"><i class="mdi mdi-stop"></i> End Period</button>` : ''}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="text-success mb-0"><i class="mdi mdi-food-apple"></i> Intake: ${totalIntake} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="text-danger mb-0"><i class="mdi mdi-delete-empty"></i> Output: ${totalOutput} g</h6>
                        </div>
                        <div class="col-md-4">
                            <h6 class="mb-0"><strong>Balance:</strong> <span class="${balance>= 0 ? 'text-success' : 'text-danger'}">${balance} g</span></h6>
                        </div>
                    </div>
                    ${recordsHtml}
                </div>
            </div>`;
    });

    $('#solid-periods-list').html(html);
}

// Fluid filter buttons
$(document).on('click', '#fluid_apply_filter_btn', function() {
    loadFluidPeriods();
});

$(document).on('click', '#fluid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#fluid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#fluid_end_date').val(today.toISOString().split('T')[0]);
    loadFluidPeriods();
});

// Solid filter buttons
$(document).on('click', '#solid_apply_filter_btn', function() {
    loadSolidPeriods();
});

$(document).on('click', '#solid_reset_filter_btn', function() {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    $('#solid_start_date').val(weekAgo.toISOString().split('T')[0]);
    $('#solid_end_date').val(today.toISOString().split('T')[0]);
    loadSolidPeriods();
});

// Start fluid period
$(document).on('click', '#startFluidPeriodBtn', function() {
    console.log('startFluidPeriodBtn clicked, PATIENT_ID:', PATIENT_ID);
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'fluid', _token: CSRF_TOKEN },
        success: function(response) {
            console.log('Start period response:', response);
            if (response.success) {
                toastr.success('Fluid period started.');
                console.log('Calling loadFluidPeriods...');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            console.error('Start period error:', xhr);
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// Start solid period
$(document).on('click', '#startSolidPeriodBtn', function() {
    if (!PATIENT_ID) {
        toastr.warning('Please select a patient first.');
        return;
    }

    $.ajax({
        url: intakeOutputChartStartRoute,
        type: 'POST',
        data: { patient_id: PATIENT_ID, type: 'solid', _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period started.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to start period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to start period.');
        }
    });
});

// End fluid period
$(document).on('click', '.end-fluid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this fluid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid period ended.');
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// End solid period
$(document).on('click', '.end-solid-period-btn', function() {
    const periodId = $(this).data('period-id');
    if (!confirm('End this solid period?')) return;

    $.ajax({
        url: intakeOutputChartEndRoute,
        type: 'POST',
        data: { period_id: periodId, _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Solid period ended.');
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to end period.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to end period.');
        }
    });
});

// Add fluid record
$(document).on('click', '.add-fluid-record-btn', function() {
    currentFluidPeriodId = $(this).data('period-id');
    $('#fluid_period_id').val(currentFluidPeriodId);
    $('#fluidRecordModal').modal('show');
});

// Add solid record
$(document).on('click', '.add-solid-record-btn', function() {
    currentSolidPeriodId = $(this).data('period-id');
    $('#solid_period_id').val(currentSolidPeriodId);
    $('#solidRecordModal').modal('show');
});

// Delete I/O record handler
$(document).on('click', '.delete-io-record-btn', function() {
    const recordId = $(this).data('record-id');
    const type = $(this).data('type');
    if (!recordId) return;
    if (!confirm('Delete this record? This cannot be undone.')) return;
    const url = intakeOutputChartDeleteRecordRoute.replace(':record', recordId);
    $.ajax({
        url: url,
        type: 'DELETE',
        data: { _token: CSRF_TOKEN },
        success: function(response) {
            if (response.success) {
                toastr.success('Record deleted.');
                if (type === 'fluid') {
                    loadFluidPeriods();
                } else {
                    loadSolidPeriods();
                }
            } else {
                toastr.error(response.message || 'Failed to delete record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to delete record.');
        }
    });
});

// Fluid record form submit
$(document).on('submit', '#fluidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Fluid record added.');
                $('#fluidRecordModal').modal('hide');
                $('#fluidRecordForm')[0].reset();
                loadFluidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Solid record form submit
$(document).on('submit', '#solidRecordForm', function(e) {
    e.preventDefault();

    $.ajax({
        url: intakeOutputChartRecordRoute,
        type: 'POST',
        data: $(this).serialize() + '&_token=' + CSRF_TOKEN,
        success: function(response) {
            if (response.success) {
                toastr.success('Solid record added.');
                $('#solidRecordModal').modal('hide');
                $('#solidRecordForm')[0].reset();
                loadSolidPeriods();
            } else {
                toastr.error(response.message || 'Failed to add record.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to add record.');
        }
    });
});

// Edit Note Logic
let editNoteEditor;

function openEditNoteModal(btn) {
    const noteId = $(btn).data('id');
    // Get content from data attribute on the button (set by server)
    const content = $(btn).data('content') || $(btn).closest('.nursing-note-card').find('.note-content').html() || '';

    $('#edit-note-id').val(noteId);

    // Initialize Editor if not exists
    if (!editNoteEditor) {
        ClassicEditor
            .create(document.querySelector('#edit-note-editor'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'bulletedList', 'numberedList', 'blockQuote', 'undo', 'redo']
            })
            .then(editor => {
                editNoteEditor = editor;
                editNoteEditor.setData(content);
                $('#editNoteModal').modal('show');
            })
            .catch(error => {
                console.error(error);
            });
    } else {
        editNoteEditor.setData(content);
        $('#editNoteModal').modal('show');
    }
}

function updatedNote() {
    const noteId = $('#edit-note-id').val();
    const content = editNoteEditor.getData();

    if (!content.trim()) {
        showNotification('error', 'Note content cannot be empty');
        return;
    }

    $.ajax({
        url: wbUrl(`/nursing-workbench/nursing-note/${noteId}`),
        type: 'PUT',
        data: {
            note: content,
            _token: CSRF_TOKEN
        },
        success: function(response) {
            showNotification('success', 'Note updated successfully');
            $('#editNoteModal').modal('hide');
            loadNotesHistory(currentPatient); // Reload table
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update note');
        }
    });
}

// Edit Vital Logic
function openEditVitalModal(btn) {
    const vitalData = JSON.parse($(btn).attr('data-vital'));

    $('#edit-vital-id').val(vitalData.id);
    $('#edit-blood-pressure').val(vitalData.blood_pressure || '');
    $('#edit-temp').val(vitalData.temp || '');
    $('#edit-heart-rate').val(vitalData.heart_rate || '');
    $('#edit-resp-rate').val(vitalData.resp_rate || '');
    $('#edit-weight').val(vitalData.weight || '');
    $('#edit-height').val(vitalData.height || '');
    $('#edit-spo2').val(vitalData.spo2 || '');
    $('#edit-blood-sugar').val(vitalData.blood_sugar || '');
    $('#edit-other-notes').val(vitalData.other_notes || '');

    $('#editVitalModal').modal('show');
}

function updateVital() {
    const vitalId = $('#edit-vital-id').val();

    const data = {
        blood_pressure: $('#edit-blood-pressure').val(),
        temp: $('#edit-temp').val(),
        heart_rate: $('#edit-heart-rate').val(),
        resp_rate: $('#edit-resp-rate').val(),
        weight: $('#edit-weight').val(),
        height: $('#edit-height').val(),
        spo2: $('#edit-spo2').val(),
        blood_sugar: $('#edit-blood-sugar').val(),
        other_notes: $('#edit-other-notes').val(),
        _token: CSRF_TOKEN
    };

    $.ajax({
        url: wbUrl(`/nursing-workbench/vitals/${vitalId}`),
        type: 'PUT',
        data: data,
        success: function(response) {
            if (response.success) {
                showNotification('success', response.message || 'Vitals updated successfully');
                $('#editVitalModal').modal('hide');
                // Reload unified vitals history DataTable (if present)
                $('.unified-vitals-history-table').each(function() {
                    if ($.fn.DataTable.isDataTable(this)) {
                        $(this).DataTable().ajax.reload(null, false);
                    }
                });
                // Reload nursing workbench vitals table (if present)
                if ($.fn.DataTable.isDataTable('#vitals-table')) {
                    $('#vitals-table').DataTable().ajax.reload(null, false);
                }
            } else {
                showNotification('error', response.message || 'Failed to update vitals');
            }
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to update vitals');
        }
    });
}

// =============================================
// SHIFT MANAGEMENT MODULE
// =============================================

const ShiftManager = {
    // State
    activeShift: null,
    shiftTimer: null,
    acknowledgedHandovers: [],
    currentHandoverDetail: null,
    forceEndShift: false,

    // Handover cards state
    handoverCurrentPage: 1,
    handoverPerPage: 12,
    handoverViewMode: 'cards', // 'cards' or 'list'

    // Routes
    routes: {
        check: '/nursing-workbench/shift/check',
        start: '/nursing-workbench/shift/start',
        end: '/nursing-workbench/shift/end',
        preview: '/nursing-workbench/shift/preview',
        pendingHandovers: '/nursing-workbench/shift/pending-handovers',
        wards: '/nursing-workbench/shift/wards',
        actions: '/nursing-workbench/shift/actions',
        handovers: '/nursing-workbench/handovers',
        handoverDetail: '/nursing-workbench/handover',
        acknowledge: '/nursing-workbench/handover/{id}/acknowledge',
        acknowledgeMultiple: '/nursing-workbench/handovers/acknowledge-multiple'
    },

    // Initialize
    init: function() {
        this.bindEvents();
        this.checkShiftStatus();
        this.loadWards();
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

        // Ward select change - load handovers for that ward
        $('#shift-ward-select').on('change', function() {
            // Reset handovers when ward changes
            $('#shift-handovers-step').hide();
            self.acknowledgedHandovers = [];
        });

        // Check for handovers button
        $('#load-ward-handovers-btn').on('click', function() {
            self.loadHandoversForWard();
        });

        // FAB main button toggle
        $('#shift-fab-btn').on('click', function() {
            self.toggleFabActions();
        });

        // End shift button
        $('#end-shift-btn').on('click', function() {
            self.showEndShiftModal();
        });

        // Confirm end shift
        $('#confirm-end-shift-btn').on('click', function() {
            self.endShift();
        });

        // Load shift preview
        $('#load-shift-preview-btn').on('click', function() {
            self.loadShiftPreview();
        });

        // View shift summary
        $('#view-shift-summary').on('click', function() {
            self.showShiftSummary();
        });

        // View handovers button
        $('#view-handovers-btn').on('click', function() {
            self.showHandoversList();
        });

        // Quick action button for handovers
        $(document).on('click', '#quick-shift-handover', function() {
            self.showHandoversList();
        });

        // Add pending task
        $('#add-pending-task-btn').on('click', function() {
            self.addPendingTaskRow();
        });

        // Remove pending task
        $(document).on('click', '.remove-pending-task', function() {
            $(this).closest('.pending-task-row').remove();
        });

        // Apply handover filters
        $('#apply-handover-filters').on('click', function() {
            self.reloadHandoversCards();
        });

        // Clear handover filters
        $('#clear-handover-filters').on('click', function() {
            self.clearHandoverFilters();
        });

        // Per page change
        $('#handover-per-page').on('change', function() {
            self.handoverPerPage = parseInt($(this).val());
            self.handoverCurrentPage = 1;
            self.loadHandoversCards();
        });

        // Pagination click
        $(document).on('click', '#handover-pagination-list .page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && !$(this).parent().hasClass('disabled') && !$(this).parent().hasClass('active')) {
                self.handoverCurrentPage = page;
                self.loadHandoversCards();
            }
        });

        // View toggle - Cards
        $('#handover-view-cards').on('click', function() {
            self.handoverViewMode = 'cards';
            $(this).addClass('active');
            $('#handover-view-list').removeClass('active');
            self.loadHandoversCards();
        });

        // View toggle - List
        $('#handover-view-list').on('click', function() {
            self.handoverViewMode = 'list';
            $(this).addClass('active');
            $('#handover-view-cards').removeClass('active');
            self.loadHandoversCards();
        });

        // Search input enter key
        $('#handover-filter-search').on('keypress', function(e) {
            if (e.which === 13) {
                self.reloadHandoversCards();
            }
        });

        // View handover detail
        $(document).on('click', '.view-handover', function() {
            const id = $(this).data('id');
            self.showHandoverDetail(id);
        });

        // Acknowledge handover from cards/list
        $(document).on('click', '.acknowledge-handover', function() {
            const id = $(this).data('id');
            self.acknowledgeHandover(id);
        });

        // Acknowledge handover from detail modal
        $('#acknowledge-handover-detail-btn').on('click', function() {
            if (self.currentHandoverDetail) {
                self.acknowledgeHandover(self.currentHandoverDetail.id, true);
            }
        });

        // Load more handovers in start shift modal
        $('#load-more-handovers-btn').on('click', function() {
            self.loadPendingHandovers(48); // Load 48 hours
        });

        // Handover acknowledgment checkboxes
        $(document).on('change', '.handover-ack-checkbox', function() {
            const id = $(this).data('id');
            if ($(this).is(':checked')) {
                if (!self.acknowledgedHandovers.includes(id)) {
                    self.acknowledgedHandovers.push(id);
                }
            } else {
                self.acknowledgedHandovers = self.acknowledgedHandovers.filter(h => h !== id);
            }
        });

        // Restore overlay when start shift modal is closed without starting
        $('#startShiftModal').on('hidden.bs.modal', function() {
            if (!self.activeShift) {
                $('#shift-lock-overlay').removeClass('modal-open-hidden');
            }
        });
    },

    // Check shift status on load
    checkShiftStatus: function() {
        const self = this;

        $.ajax({
            url: this.routes.check,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    if (response.has_active_shift) {
                        self.activeShift = response.shift;

                        // Check if shift is older than 12 hours (43200 seconds)
                        const elapsedSeconds = response.shift.elapsed_seconds || 0;
                        const twelveHoursInSeconds = 12 * 60 * 60; // 43200

                        if (elapsedSeconds> twelveHoursInSeconds) {
                            // Shift is overdue - force end shift
                            self.showWorkbench();
                            self.startShiftTimer();
                            self.forceEndShiftModal(elapsedSeconds);
                        } else {
                            self.showWorkbench();
                            self.startShiftTimer();
                        }
                    } else {
                        self.showLockOverlay();
                    }
                }
            },
            error: function() {
                // If check fails, show workbench anyway (graceful degradation)
                self.showWorkbench();
            }
        });
    },

    // Force end shift modal for overdue shifts (>12 hours)
    forceEndShiftModal: function(elapsedSeconds) {
        const self = this;
        const hours = Math.floor(elapsedSeconds / 3600);
        const minutes = Math.floor((elapsedSeconds % 3600) / 60);
        const overdueHours = hours - 12;

        // Set flag to prevent modal dismissal
        this.forceEndShift = true;

        // Show the end shift modal with forced mode
        this.showEndShiftModal(true);

        // Add overdue warning to the modal
        const warningHtml = `
            <div id="overdue-shift-warning" class="alert alert-danger mb-4">
                <h5 class="alert-heading">
                    <i class="mdi mdi-alert-octagon"></i> Shift Overdue - Action Required
                </h5>
                <hr>
                <p class="mb-2">
                    <strong>Your shift has been running for ${hours} hours and ${minutes} minutes.</strong>
                </p>
                <p class="mb-2">
                    Standard shifts are 8-12 hours. Your shift is now <strong class="text-danger">${overdueHours> 0 ? overdueHours + ' hour(s)' : 'more than 12 hours'}</strong> overdue.
                </p>
                <p class="mb-0">
                    <i class="mdi mdi-information-outline"></i>
                    <strong>You must end this shift to continue.</strong> This ensures proper handover documentation and accurate shift records.
                    Please review your activities, add any critical notes, and end your shift.
                </p>
            </div>
        `;

        // Insert warning at the top of modal body if not already there
        if ($('#overdue-shift-warning').length === 0) {
            $('#endShiftModal .modal-body').prepend(warningHtml);
        }

        // Change modal header to indicate forced mode
        $('#endShiftModalLabel').html('<i class="mdi mdi-alert-octagon"></i> End Overdue Shift (Required)');

        // Hide cancel button and prevent modal dismiss
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').hide();
        $('#endShiftModal .btn-close').hide();

        // Make modal static (cannot dismiss by clicking outside)
        $('#endShiftModal').attr('data-bs-backdrop', 'static');
        $('#endShiftModal').attr('data-bs-keyboard', 'false');

        // Update end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Overdue Shift');

        toastr.warning('Your shift is overdue. Please end your shift and create a handover document.', 'Shift Overdue', {
            timeOut: 10000,
            closeButton: true
        });
    },

    // Reset end shift modal to normal mode
    resetEndShiftModal: function() {
        this.forceEndShift = false;

        // Remove overdue warning
        $('#overdue-shift-warning').remove();

        // Restore modal header
        $('#endShiftModalLabel').html('<i class="mdi mdi-stop-circle"></i> End Your Shift');

        // Show cancel button and close button
        $('#endShiftModal .btn-secondary[data-bs-dismiss="modal"]').show();
        $('#endShiftModal .btn-close').show();

        // Remove static backdrop
        $('#endShiftModal').removeAttr('data-bs-backdrop');
        $('#endShiftModal').removeAttr('data-bs-keyboard');

        // Reset end shift button text
        $('#confirm-end-shift-btn').html('<i class="mdi mdi-stop-circle"></i> End Shift');
    },

    // Show lock overlay
    showLockOverlay: function() {
        $('#shift-lock-overlay').show();
        $('#shift-control-fab').hide();
        // Show a simple count of handovers (user will see details after selecting ward in modal)
        this.loadPendingHandoversCount();
    },

    // Show workbench (unlock)
    showWorkbench: function() {
        $('#shift-lock-overlay').hide();
        $('#shift-control-fab').show();
        this.updateFabDisplay();
    },

    // Load pending handovers count for lock overlay (just shows count, not details)
    loadPendingHandoversCount: function() {
        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending> 0) {
                    $('#pending-handovers-count').text(response.total_pending);
                    $('#pending-handovers-preview').show();
                    // Show simplified list
                    let html = '';
                    response.handovers.slice(0, 3).forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">· ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    if (response.total_pending> 3) {
                        html += `<p class="text-center text-muted mt-2 mb-0">+${response.total_pending - 3} more</p>`;
                    }
                    $('#pending-handovers-list').html(html);
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load pending handovers preview for lock overlay (DEPRECATED - use loadPendingHandoversCount)
    loadPendingHandoversPreview: function() {
        const self = this;

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: { hours: 24 },
            success: function(response) {
                if (response.success && response.total_pending> 0) {
                    $('#pending-handovers-count').text(response.total_pending);

                    let html = '';
                    response.handovers.forEach(function(h) {
                        html += `
                            <div class="pending-handover-item ${h.has_critical_notes ? 'has-critical' : ''}">
                                <div>
                                    <strong>${h.created_by_name}</strong>
                                    <span class="text-muted">· ${h.created_at_ago}</span>
                                    ${h.has_critical_notes ? '<span class="badge badge-danger ml-2">Critical</span>' : ''}
                                </div>
                                <span>${h.shift_type_badge}</span>
                            </div>
                        `;
                    });
                    $('#pending-handovers-list').html(html);
                    $('#pending-handovers-preview').show();
                } else {
                    $('#pending-handovers-preview').hide();
                }
            }
        });
    },

    // Load wards for select
    loadWards: function() {
        $.ajax({
            url: this.routes.wards,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">All Wards (Floating)</option>';
                    response.wards.forEach(function(ward) {
                        options += `<option value="${ward.id}">${ward.name}</option>`;
                    });
                    $('#shift-ward-select, #handover-filter-ward').html(options);
                }
            }
        });
    },

    // Show start shift modal
    showStartShiftModal: function() {
        const self = this;
        this.acknowledgedHandovers = [];

        // Show modal with config step first, hide handovers until ward is selected
        $('#shift-config-step').show();
        $('#shift-handovers-step').hide();
        $('#start-shift-handovers-list').html('');

        // Temporarily hide overlay so modal is visible
        $('#shift-lock-overlay').addClass('modal-open-hidden');
        $('#startShiftModal').modal('show');
    },

    // Load handovers for selected ward (called when ward changes)
    loadHandoversForWard: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId || null,
                hours: 24
            },
            success: function(response) {
                if (response.success && response.handovers.length> 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                    $('#start-shift-handovers-list').html('<p class="text-muted text-center py-3">No pending handovers for this ward</p>');
                }
            },
            error: function() {
                $('#shift-handovers-step').hide();
            }
        });
    },

    // Render pending handovers in modal
    renderPendingHandovers: function(handovers) {
        const self = this;
        let html = '';
        handovers.forEach(function(h) {
            const isAcked = self.acknowledgedHandovers.includes(h.id);
            html += `
                <div class="handover-ack-item ${h.has_critical_notes ? 'critical' : ''}">
                    <div class="handover-ack-header">
                        <div>
                            ${h.shift_type_badge}
                            <span class="ml-2 text-muted">${h.ward_name}</span>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input handover-ack-checkbox"
                                data-id="${h.id}" ${isAcked ? 'checked' : ''}>
                            <label class="form-check-label">Acknowledged</label>
                        </div>
                    </div>
                    <div class="handover-ack-meta">
                        <strong>${h.created_by_name}</strong> · ${h.created_at_ago}
                    </div>
                    <div class="handover-ack-content mt-2">
                        ${h.summary_preview}
                        ${h.has_critical_notes ? '<div class="text-danger mt-1"><i class="mdi mdi-alert"></i> Contains critical notes</div>' : ''}
                    </div>
                    <div class="handover-ack-footer">
                        <span class="text-muted">${h.pending_tasks_count} pending task(s)</span>
                        <button class="btn btn-sm btn-outline-info view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i> View Full
                        </button>
                    </div>
                </div>
            `;
        });
        $('#start-shift-handovers-list').html(html);
    },

    // Load pending handovers for acknowledgment
    loadPendingHandovers: function(hours = 24) {
        const self = this;
        const wardId = $('#shift-ward-select').val();

        $.ajax({
            url: this.routes.pendingHandovers,
            type: 'GET',
            data: {
                ward_id: wardId,
                hours: hours
            },
            success: function(response) {
                if (response.success && response.handovers.length> 0) {
                    self.renderPendingHandovers(response.handovers);
                    $('#shift-handovers-step').show();
                } else {
                    $('#shift-handovers-step').hide();
                }
            }
        });
    },

    // Start shift
    startShift: function() {
        const self = this;
        const wardId = $('#shift-ward-select').val();
        const shiftType = $('#shift-type-select').val();

        // Check if there are critical handovers that need acknowledgment
        const criticalHandovers = $('.handover-ack-item.critical');
        const unacknowledgedCritical = criticalHandovers.filter(function() {
            return !$(this).find('.handover-ack-checkbox').is(':checked');
        });

        if (unacknowledgedCritical.length> 0) {
            // Highlight unacknowledged critical handovers
            unacknowledgedCritical.addClass('shake-highlight');
            setTimeout(() => unacknowledgedCritical.removeClass('shake-highlight'), 500);

            toastr.warning('Please acknowledge all critical handovers (marked in red) before starting your shift');

            // Scroll to first unacknowledged
            const firstUnacked = unacknowledgedCritical.first();
            if (firstUnacked.length) {
                firstUnacked[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        const btn = $('#confirm-start-shift-btn');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Starting...');

        $.ajax({
            url: this.routes.start,
            type: 'POST',
            data: {
                ward_id: wardId || null,
                shift_type: shiftType || null,
                acknowledged_handovers: this.acknowledgedHandovers,
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    self.activeShift = response.shift;
                    $('#startShiftModal').modal('hide');
                    self.showWorkbench();
                    self.startShiftTimer();
                    toastr.success(response.message || 'Shift started successfully');
                } else if (response.requires_acknowledgment) {
                    toastr.warning(response.message);
                    // Highlight unacknowledged critical handovers
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                } else {
                    toastr.error(response.message || 'Failed to start shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
                }
            },
            error: function(xhr) {
                const resp = xhr.responseJSON || {};
                if (resp.requires_acknowledgment) {
                    toastr.warning(resp.message || 'Please acknowledge critical handovers first');
                    // Highlight unacknowledged critical handovers
                    $('.handover-ack-item.critical').each(function() {
                        if (!$(this).find('.handover-ack-checkbox').is(':checked')) {
                            $(this).addClass('shake-highlight');
                            setTimeout(() => $(this).removeClass('shake-highlight'), 500);
                        }
                    });
                } else {
                    toastr.error(resp.message || 'Failed to start shift');
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-play-circle"></i> Start Shift');
            }
        });
    },

    // Show end shift modal
    showEndShiftModal: function(forced = false) {
        if (!this.activeShift) return;

        // Reset modal to normal mode if not forced
        if (!forced) {
            this.resetEndShiftModal();
        }

        // Populate summary
        $('#end-shift-duration').text(this.formatElapsedTime(this.activeShift.elapsed_seconds || 0));
        $('#end-shift-vitals').text(this.activeShift.counters?.vitals || 0);
        $('#end-shift-medications').text(this.activeShift.counters?.medications || 0);
        $('#end-shift-notes').text(this.activeShift.counters?.notes || 0);
        $('#end-shift-total').text(this.activeShift.total_actions || 0);

        // Clear form
        $('#end-shift-critical-notes').val('');
        $('#end-shift-concluding-notes').val('');
        $('#pending-tasks-container').html(`
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `);
        $('#create-handover-checkbox').prop('checked', true);

        // Reset preview section and show loading state
        $('#shift-activity-preview').html(`
            <div class="text-center text-muted py-3">
                <i class="mdi mdi-loading mdi-spin"></i> Loading activity preview...
            </div>
        `);

        $('#endShiftModal').modal('show');

        // Auto-load preview after modal is shown
        this.loadShiftPreview();
    },

    // Load shift preview with audit-based activities
    loadShiftPreview: function() {
        const self = this;
        const btn = $('#load-shift-preview-btn');
        const container = $('#shift-activity-preview');

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Loading...');

        $.ajax({
            url: this.routes.preview,
            type: 'GET',
            success: function(response) {
                if (response.success && response.preview) {
                    self.renderShiftPreview(response.preview);
                } else {
                    container.html(`
                        <div class="alert alert-warning mb-0">
                            <i class="mdi mdi-alert"></i> No activity data found for this shift
                        </div>
                    `);
                }
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Refresh Preview');
            },
            error: function(xhr) {
                container.html(`
                    <div class="alert alert-danger mb-0">
                        <i class="mdi mdi-alert-circle"></i> Failed to load preview
                    </div>
                `);
                btn.prop('disabled', false).html('<i class="mdi mdi-refresh"></i> Load Preview');
            }
        });
    },

    // Render shift preview HTML
    renderShiftPreview: function(preview) {
        let html = '';

        // Summary stats
        html += `
            <div class="d-flex justify-content-around text-center mb-3 pb-3 border-bottom">
                <div>
                    <div class="h5 mb-0 text-primary">${preview.total_events || 0}</div>
                    <small class="text-muted">Total Events</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-info">${preview.total_patients || 0}</div>
                    <small class="text-muted">Patients</small>
                </div>
                <div>
                    <div class="h5 mb-0 text-secondary">${preview.elapsed_time || '--'}</div>
                    <small class="text-muted">Duration</small>
                </div>
            </div>
        `;

        // Activity breakdown
        if (preview.activity_summary && preview.activity_summary.length> 0) {
            html += '<h6 class="mb-2"><i class="mdi mdi-chart-bar"></i> Activity Breakdown</h6>';
            html += '<div class="row">';
            preview.activity_summary.forEach(function(activity) {
                html += `
                    <div class="col-6 col-md-4 mb-2">
                        <div class="d-flex align-items-center p-2 border rounded bg-white">
                            <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-2" style="font-size: 1.5rem;"></i>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold">${activity.count}</div>
                                <small class="text-muted">${activity.label}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Patient highlights (collapsible)
        if (preview.patient_highlights && preview.patient_highlights.length> 0) {
            html += `
                <h6 class="mb-2 mt-3"><i class="mdi mdi-account-group"></i> Patient Highlights</h6>
                <div class="patient-highlights-preview">
            `;
            preview.patient_highlights.slice(0, 5).forEach(function(patient, idx) {
                html += `
                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom bg-white">
                        <div>
                            <i class="mdi mdi-account text-primary mr-1"></i>
                            <strong>${patient.patient_name}</strong>
                            <small class="text-muted ml-1">(${patient.patient_no || 'N/A'})</small>
                        </div>
                        <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                    </div>
                `;
            });
            if (preview.patient_highlights.length> 5) {
                html += `<div class="text-center py-2 text-muted small">... and ${preview.patient_highlights.length - 5} more patients</div>`;
            }
            html += '</div>';
        }

        // Auto-generated summary preview
        if (preview.detailed_summary) {
            html += `
                <div class="mt-3 pt-3 border-top">
                    <h6 class="mb-2"><i class="mdi mdi-clipboard-text"></i> Auto-Generated Summary</h6>
                    <div class="bg-white p-2 border rounded small" style="max-height: 150px; overflow-y: auto;">
                        ${preview.detailed_summary.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        }

        $('#shift-activity-preview').html(html || '<div class="text-center text-muted">No activities recorded yet</div>');
    },

    // End shift
    endShift: function() {
        const self = this;
        const btn = $('#confirm-end-shift-btn');
        const wasForced = this.forceEndShift;

        // Gather pending tasks
        const pendingTasks = [];
        $('.pending-task-row').each(function() {
            const desc = $(this).find('.pending-task-desc').val().trim();
            if (desc) {
                pendingTasks.push({
                    description: desc,
                    priority: $(this).find('.pending-task-priority').val()
                });
            }
        });

        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Ending...');

        $.ajax({
            url: this.routes.end,
            type: 'POST',
            data: {
                critical_notes: $('#end-shift-critical-notes').val(),
                concluding_notes: $('#end-shift-concluding-notes').val(),
                pending_tasks: pendingTasks,
                create_handover: $('#create-handover-checkbox').is(':checked'),
                _token: CSRF_TOKEN
            },
            success: function(response) {
                if (response.success) {
                    // Reset forced mode before hiding modal
                    self.resetEndShiftModal();

                    $('#endShiftModal').modal('hide');
                    self.activeShift = null;
                    self.forceEndShift = false;
                    self.stopShiftTimer();

                    if (wasForced) {
                        toastr.success('Overdue shift ended successfully. Thank you for completing the handover.', 'Shift Ended');
                    } else {
                        toastr.success(response.message || 'Shift ended successfully');
                    }

                    if (response.handover_created) {
                        toastr.info('Handover document created for incoming nurse');
                    }

                    // Show summary
                    setTimeout(function() {
                        self.showLockOverlay();
                    }, 1000);
                } else {
                    toastr.error(response.message || 'Failed to end shift');
                    btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to end shift');
                btn.prop('disabled', false).html('<i class="mdi mdi-stop-circle"></i> End Shift');
            }
        });
    },

    // Add pending task row
    addPendingTaskRow: function() {
        const html = `
            <div class="pending-task-row mb-2">
                <div class="input-group">
                    <select class="form-control form-control-sm pending-task-priority" style="max-width: 100px;">
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                    <input type="text" class="form-control pending-task-desc" placeholder="Describe pending task...">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-pending-task" type="button">
                            <i class="mdi mdi-close"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#pending-tasks-container').append(html);
    },

    // Show handovers list (Cards-based modal)
    showHandoversList: function() {
        const self = this;
        // Reset pagination and load first page
        this.handoverCurrentPage = 1;
        this.handoverPerPage = parseInt($('#handover-per-page').val()) || 12;
        this.handoverViewMode = 'cards';

        // Set default view toggle
        $('#handover-view-cards').addClass('active');
        $('#handover-view-list').removeClass('active');

        // Set default dates (last 2 days)
        const today = new Date();
        const twoDaysAgo = new Date(today);
        twoDaysAgo.setDate(today.getDate() - 2);

        const formatDate = (date) => date.toISOString().split('T')[0];
        $('#handover-filter-from').val(formatDate(twoDaysAgo));
        $('#handover-filter-to').val(formatDate(today));

        // Populate wards if not already done
        if ($('#handover-filter-ward option').length <= 1) {
            this.populateHandoverWards();
        }

        this.loadHandoversCards();
        $('#handoversListModal').modal('show');
    },

    // Populate ward filter options
    populateHandoverWards: function() {
        const select = $('#handover-filter-ward');
        $.ajax({
            url: this.routes.wards || '/wards',
            type: 'GET',
            success: function(response) {
                const wards = response.wards || response.data || response;
                if (Array.isArray(wards)) {
                    wards.forEach(function(ward) {
                        select.append(`<option value="${ward.id}">${ward.name}</option>`);
                    });
                }
            }
        });
    },

    // Load handovers cards with backend processing
    loadHandoversCards: function() {
        const self = this;
        const container = $('#handover-cards-grid');
        const loadingEl = $('#handover-loading');
        const emptyEl = $('#handover-empty');

        // Show loading state
        container.html('');
        loadingEl.show();
        emptyEl.hide();

        // Build filter params
        const params = {
            page: this.handoverCurrentPage,
            per_page: this.handoverPerPage,
            ward_id: $('#handover-filter-ward').val(),
            shift_type: $('#handover-filter-shift').val(),
            status: $('#handover-filter-status').val(),
            priority: $('#handover-filter-priority').val(),
            date_from: $('#handover-filter-from').val(),
            date_to: $('#handover-filter-to').val(),
            search: $('#handover-filter-search').val(),
            sort: $('#handover-filter-sort').val(),
            format: 'cards'
        };

        $.ajax({
            url: this.routes.handovers,
            type: 'GET',
            data: params,
            success: function(response) {
                loadingEl.hide();

                if (response.success && response.data && response.data.length> 0) {
                    self.renderHandoversCards(response.data);
                    self.updateHandoverStats(response.stats);
                    self.renderHandoverPagination(response.pagination);
                } else {
                    emptyEl.show();
                    self.updateHandoverStats({ total: 0, pending: 0, critical: 0 });
                    self.renderHandoverPagination(null);
                }
            },
            error: function() {
                loadingEl.hide();
                container.html(`
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="mdi mdi-alert-circle"></i> Failed to load handovers. Please try again.
                        </div>
                    </div>
                `);
            }
        });
    },

    // Render handover cards
    renderHandoversCards: function(handovers) {
        const self = this;
        const container = $('#handover-cards-grid');
        const viewMode = this.handoverViewMode;

        if (viewMode === 'list') {
            this.renderHandoversList(handovers);
            return;
        }

        let html = '';
        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;
            const cardClasses = [
                'handover-card',
                isCritical ? 'critical' : '',
                isPending ? 'pending' : 'acknowledged'
            ].filter(Boolean).join(' ');

            const shiftBadgeClass = {
                'morning': 'morning',
                'afternoon': 'afternoon',
                'night': 'night'
            }[h.shift_type] || 'secondary';

            const shiftIcon = {
                'morning': '🌅',
                'afternoon': '☀️',
                'night': '🌙'
            }[h.shift_type] || '🔲';

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="${cardClasses}" data-handover-id="${h.id}">
                        <div class="handover-card-header">
                            <span class="handover-card-shift-badge ${shiftBadgeClass}">
                                ${shiftIcon} ${h.shift_type_label || h.shift_type}
                            </span>
                            <span class="handover-card-time" title="${h.created_at_full}">
                                ${h.created_at_ago}
                            </span>
                        </div>
                        <div class="handover-card-body">
                            <div class="handover-card-meta">
                                <span class="handover-card-nurse">
                                    <i class="mdi mdi-account-nurse"></i> ${h.created_by_name}
                                </span>
                            </div>
                            <div class="handover-card-ward">
                                <i class="mdi mdi-hospital-building"></i> ${h.ward_name}
                            </div>
                            <div class="handover-card-summary">
                                ${h.summary_preview || '<span class="text-muted">No summary provided</span>'}
                            </div>
                            ${isCritical ? `
                                <div class="handover-card-critical-preview">
                                    <i class="mdi mdi-alert-circle"></i>
                                    <strong>Critical:</strong> ${h.critical_notes_preview || 'See details'}
                                </div>
                            ` : ''}
                            <div class="handover-card-stats">
                                ${isCritical ? '<span class="handover-card-stat danger"><i class="mdi mdi-alert"></i> Critical</span>' : ''}
                                ${h.pending_tasks_count> 0 ? `<span class="handover-card-stat warning"><i class="mdi mdi-clipboard-list"></i> ${h.pending_tasks_count} tasks</span>` : ''}
                                ${h.action_count ? `<span class="handover-card-stat"><i class="mdi mdi-chart-bar"></i> ${h.action_count} actions</span>` : ''}
                            </div>
                        </div>
                        <div class="handover-card-footer">
                            <span class="handover-card-status ${isPending ? 'pending' : 'acknowledged'}">
                                ${isPending ? '<i class="mdi mdi-clock-alert"></i> Pending' : '<i class="mdi mdi-check-circle"></i> Acknowledged'}
                            </span>
                            <div class="handover-card-actions">
                                <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}" title="View Details">
                                    <i class="mdi mdi-eye"></i>
                                </button>
                                ${isPending ? `
                                    <button class="btn btn-sm btn-success acknowledge-handover" data-id="${h.id}" title="Acknowledge">
                                        <i class="mdi mdi-check"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.html(html);
    },

    // Render handovers as list
    renderHandoversList: function(handovers) {
        const container = $('#handover-cards-grid');
        let html = '<div class="col-12">';

        handovers.forEach(function(h) {
            const isCritical = h.has_critical_notes;
            const isPending = !h.is_acknowledged;

            html += `
                <div class="handover-list-item ${isCritical ? 'critical' : ''}" data-handover-id="${h.id}">
                    <div class="handover-list-shift">
                        <span class="badge badge-${h.shift_type === 'morning' ? 'warning' : h.shift_type === 'afternoon' ? 'info' : 'dark'}">
                            ${h.shift_type_label || h.shift_type}
                        </span>
                    </div>
                    <div class="handover-list-info">
                        <div class="handover-list-meta">
                            <strong>${h.created_by_name}</strong>
                            <span class="text-muted">•</span>
                            <span class="text-muted">${h.ward_name}</span>
                            <span class="text-muted">•</span>
                            <small class="text-muted">${h.created_at_ago}</small>
                            ${isCritical ? '<span class="badge badge-danger ms-2">Critical</span>' : ''}
                        </div>
                        <div class="handover-list-summary">
                            ${h.summary_preview || 'No summary'}
                        </div>
                    </div>
                    <div class="handover-list-status">
                        ${isPending
                            ? '<span class="badge badge-warning">Pending</span>'
                            : '<span class="badge badge-success">Acknowledged</span>'}
                    </div>
                    <div class="handover-list-actions">
                        <button class="btn btn-sm btn-outline-primary view-handover" data-id="${h.id}">
                            <i class="mdi mdi-eye"></i>
                        </button>
                        ${isPending ? `
                            <button class="btn btn-sm btn-success acknowledge-handover ms-1" data-id="${h.id}">
                                <i class="mdi mdi-check"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    },

    // Update handover stats display
    updateHandoverStats: function(stats) {
        $('#handover-total-count span').text(stats.total || 0);
        $('#handover-pending-count span').text(stats.pending || 0);
        $('#handover-critical-count span').text(stats.critical || 0);
    },

    // Render pagination
    renderHandoverPagination: function(pagination) {
        const self = this;
        const container = $('#handover-pagination-list');
        const pageInfo = $('#handover-page-info');

        if (!pagination || pagination.total_pages <= 1) {
            container.html('');
            pageInfo.text('');
            return;
        }

        pageInfo.text(`Page ${pagination.current_page} of ${pagination.total_pages}`);

        let html = '';

        // Previous button
        html += `
            <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="mdi mdi-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        if (startPage> 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage> 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.total_pages}">${pagination.total_pages}</a></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="mdi mdi-chevron-right"></i>
                </a>
            </li>
        `;

        container.html(html);
    },

    // Reload handovers with current filters
    reloadHandoversCards: function() {
        this.handoverCurrentPage = 1;
        this.loadHandoversCards();
    },

    // Clear all handover filters
    clearHandoverFilters: function() {
        $('#handover-filter-ward').val('');
        $('#handover-filter-shift').val('');
        $('#handover-filter-status').val('');
        $('#handover-filter-priority').val('');
        $('#handover-filter-from').val('');
        $('#handover-filter-to').val('');
        $('#handover-filter-search').val('');
        $('#handover-filter-sort').val('newest');
        this.reloadHandoversCards();
    },

    // Show handover detail
    showHandoverDetail: function(id) {
        const self = this;

        $.ajax({
            url: this.routes.handoverDetail + '/' + id,
            type: 'GET',
            success: function(response) {
                if (response.success) {
                    self.currentHandoverDetail = response.handover;
                    self.renderHandoverDetail(response.handover);

                    // Show/hide acknowledge button
                    if (!response.handover.is_acknowledged) {
                        $('#acknowledge-handover-detail-btn').show();
                    } else {
                        $('#acknowledge-handover-detail-btn').hide();
                    }

                    $('#handoverDetailModal').modal('show');
                } else {
                    toastr.error('Failed to load handover details');
                }
            },
            error: function() {
                toastr.error('Failed to load handover details');
            }
        });
    },

    // Render handover detail content
    renderHandoverDetail: function(h) {
        let pendingTasksHtml = '';
        if (h.pending_tasks && h.pending_tasks.length> 0) {
            pendingTasksHtml = '<ul class="list-group list-group-flush">';
            h.pending_tasks.forEach(function(task) {
                const priorityColors = { low: 'secondary', normal: 'primary', high: 'warning', urgent: 'danger' };
                pendingTasksHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${task.description}
                        <span class="badge badge-${priorityColors[task.priority] || 'secondary'}">${task.priority || 'normal'}</span>
                    </li>
                `;
            });
            pendingTasksHtml += '</ul>';
        } else {
            pendingTasksHtml = '<p class="text-muted">No pending tasks</p>';
        }

        // Build action summary HTML with icons and colors
        let actionSummaryHtml = '';
        if (h.action_summary && Object.keys(h.action_summary).length> 0) {
            actionSummaryHtml = '<div class="row text-center mt-3">';
            for (const [key, value] of Object.entries(h.action_summary)) {
                const icon = value.icon || 'mdi-checkbox-blank-circle';
                const color = value.color || 'secondary';
                const count = value.count || 0;
                const label = value.label || key;
                actionSummaryHtml += `
                    <div class="col-4 col-md-3 mb-2">
                        <div class="stat-box p-2 border rounded">
                            <i class="mdi ${icon} text-${color}" style="font-size: 1.5rem;"></i>
                            <div class="stat-value h5 mb-0">${count}</div>
                            <div class="stat-label small text-muted">${label}</div>
                        </div>
                    </div>
                `;
            }
            actionSummaryHtml += '</div>';
        }

        // Build patient highlights HTML
        let patientHighlightsHtml = '';
        if (h.patient_highlights && h.patient_highlights.length> 0) {
            patientHighlightsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-account-group"></i> Patient Activity Summary</h6>
                    <div class="accordion" id="patientHighlightsAccordion">
            `;

            h.patient_highlights.forEach(function(patient, idx) {
                const collapseId = `patientCollapse${idx}`;
                patientHighlightsHtml += `
                    <div class="card-modern mb-2">
                        <div class="card-header p-2" id="heading${idx}">
                            <h6 class="mb-0">
                                <button class="btn btn-link btn-sm w-100 text-left d-flex justify-content-between align-items-center" type="button" data-toggle="collapse" data-target="#${collapseId}">
                                    <span>
                                        <i class="mdi mdi-account"></i> ${patient.patient_name}
                                        <span class="text-muted ml-2">(${patient.patient_no || 'N/A'})</span>
                                    </span>
                                    <span class="badge badge-primary badge-pill">${patient.total_events} events</span>
                                </button>
                            </h6>
                        </div>
                        <div id="${collapseId}" class="collapse${idx === 0 ? ' show' : ''}" data-parent="#patientHighlightsAccordion">
                            <div class="card-body p-2">
                                <ul class="list-unstyled mb-0">
                `;

                if (patient.activities && patient.activities.length> 0) {
                    patient.activities.forEach(function(activity) {
                        patientHighlightsHtml += `
                            <li class="mb-1">
                                <i class="mdi ${activity.icon || 'mdi-circle'} text-${activity.color || 'secondary'} mr-1"></i>
                                <span class="text-muted">${activity.label}:</span>
                                <strong>${activity.count}</strong>
                                ${activity.events && activity.events.length> 0 ?
                                    `<span class="text-muted small">(${activity.events.slice(0, 3).join(', ')}${activity.events.length> 3 ? '...' : ''})</span>`
                                    : ''
                                }
                            </li>
                        `;
                    });
                }

                patientHighlightsHtml += `
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
            });

            patientHighlightsHtml += '</div></div>';
        }

        // Build audit details HTML (detailed changes)
        let auditDetailsHtml = '';
        if (h.audit_details && h.audit_details.length> 0) {
            auditDetailsHtml = `
                <div class="mt-4">
                    <h6><i class="mdi mdi-history"></i> Detailed Activity Log <small class="text-muted">(${h.audit_details.length} changes)</small></h6>
                    <div class="audit-details-list" style="max-height: 400px; overflow-y: auto;">
            `;

            // Group by patient
            const byPatient = {};
            h.audit_details.forEach(function(detail) {
                const patientKey = detail.patient_name || 'General';
                if (!byPatient[patientKey]) {
                    byPatient[patientKey] = [];
                }
                byPatient[patientKey].push(detail);
            });

            for (const [patient, details] of Object.entries(byPatient)) {
                auditDetailsHtml += `
                    <div class="audit-patient-group mb-3">
                        <h6 class="text-primary mb-2">
                            <i class="mdi mdi-account"></i> ${patient}
                        </h6>
                        <div class="audit-items pl-3 border-left">
                `;

                details.forEach(function(detail) {
                    const eventBadge = detail.event === 'created'
                        ? '<span class="badge badge-success badge-sm">New</span>'
                        : detail.event === 'updated'
                        ? '<span class="badge badge-warning badge-sm">Updated</span>'
                        : '<span class="badge badge-danger badge-sm">Deleted</span>';

                    let changesHtml = '<ul class="list-unstyled mb-0 pl-3 small">';
                    if (detail.changes && detail.changes.length> 0) {
                        detail.changes.forEach(function(change) {
                            if (change.type === 'created') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <strong>' + change.value + '</strong></li>';
                            } else if (change.type === 'changed') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.old + '</del> → <strong class="text-success">' + change.new + '</strong></li>';
                            } else if (change.type === 'deleted') {
                                changesHtml += '<li><span class="text-muted">' + change.label + ':</span> <del class="text-danger">' + change.value + '</del></li>';
                            }
                        });
                    }
                    changesHtml += '</ul>';

                    auditDetailsHtml += `
                        <div class="audit-item mb-2 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <i class="mdi ${detail.icon} text-${detail.color}"></i>
                                    <strong class="ml-1">${detail.category}</strong>
                                    ${eventBadge}
                                </div>
                                <small class="text-muted">${detail.time}</small>
                            </div>
                            ${changesHtml}
                        </div>
                    `;
                });

                auditDetailsHtml += '</div></div>';
            }

            auditDetailsHtml += '</div></div>';
        }

        const html = `
            <div class="handover-detail">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        ${h.shift_type_badge}
                        <span class="ml-2">${h.ward_name}</span>
                    </div>
                    ${h.status_badge}
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted">Created By</small>
                        <div><strong>${h.created_by.name}</strong></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Date/Time</small>
                        <div>${h.created_at} <span class="text-muted">(${h.created_at_ago})</span></div>
                    </div>
                </div>

                ${h.shift_duration ? `<div class="mb-3"><small class="text-muted">Shift Duration</small><div>${h.shift_duration}</div></div>` : ''}

                ${actionSummaryHtml ? `
                    <div class="mt-3">
                        <h6><i class="mdi mdi-chart-bar"></i> Activity Summary</h6>
                        ${actionSummaryHtml}
                    </div>
                ` : ''}

                ${h.critical_notes ? `
                    <div class="alert alert-danger mt-4">
                        <h6 class="alert-heading"><i class="mdi mdi-alert"></i> Critical Notes</h6>
                        <div>${h.critical_notes}</div>
                    </div>
                ` : ''}

                <div class="mt-4">
                    <h6><i class="mdi mdi-clipboard-text"></i> Summary</h6>
                    <div class="bg-light p-3 rounded">${h.summary || '<em>No summary provided</em>'}</div>
                </div>

                ${h.concluding_notes ? `
                    <div class="mt-4">
                        <h6><i class="mdi mdi-note-text"></i> Concluding Notes</h6>
                        <div class="bg-light p-3 rounded">${h.concluding_notes}</div>
                    </div>
                ` : ''}

                ${patientHighlightsHtml}

                ${auditDetailsHtml}

                <div class="mt-4">
                    <h6><i class="mdi mdi-format-list-checks"></i> Pending Tasks</h6>
                    ${pendingTasksHtml}
                </div>

                ${h.is_acknowledged ? `
                    <div class="mt-4 alert alert-success">
                        <i class="mdi mdi-check-circle"></i> Acknowledged by <strong>${h.acknowledged_by_name}</strong> on ${h.acknowledged_at}
                    </div>
                ` : ''}
            </div>
        `;

        $('#handover-detail-content').html(html);
    },

    // Acknowledge handover
    acknowledgeHandover: function(id, fromDetail = false) {
        const self = this;

        $.ajax({
            url: this.routes.acknowledge.replace('{id}', id),
            type: 'POST',
            data: { _token: CSRF_TOKEN },
            success: function(response) {
                if (response.success) {
                    toastr.success('Handover acknowledged');

                    if (fromDetail) {
                        $('#acknowledge-handover-detail-btn').hide();
                        self.currentHandoverDetail.is_acknowledged = true;
                        self.currentHandoverDetail.acknowledged_at = response.acknowledged_at;
                    }

                    // Reload cards list
                    self.loadHandoversCards();
                } else {
                    toastr.error(response.message || 'Failed to acknowledge');
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to acknowledge');
            }
        });
    },

    // Show shift summary
    showShiftSummary: function() {
        if (!this.activeShift) return;

        const self = this;

        // Load actions for current shift
        $.ajax({
            url: this.routes.actions,
            type: 'GET',
            success: function(response) {
                self.renderShiftSummary(response);
                $('#shiftSummaryModal').modal('show');
            },
            error: function() {
                // Still show basic summary
                self.renderShiftSummary({ actions: {}, total: 0 });
                $('#shiftSummaryModal').modal('show');
            }
        });
    },

    // Render shift summary
    renderShiftSummary: function(data) {
        const shift = this.activeShift;
        const counters = shift.counters || {};

        let actionsHtml = '';
        if (data.actions && Object.keys(data.actions).length> 0) {
            actionsHtml = '<div class="mt-4"><h6>Actions by Type</h6><div class="list-group">';
            for (const [type, info] of Object.entries(data.actions)) {
                actionsHtml += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="mdi ${info.config.icon} text-${info.config.color}"></i> ${info.config.label}</span>
                        <span class="badge badge-${info.config.color}">${info.count}</span>
                    </div>
                `;
            }
            actionsHtml += '</div></div>';
        }

        const html = `
            <div class="shift-summary-content">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-clock-outline text-primary mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Shift Started</div>
                                <strong>${shift.started_at_full}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <i class="mdi mdi-timer-outline text-info mr-2" style="font-size: 2rem;"></i>
                            <div>
                                <div class="text-muted small">Elapsed Time</div>
                                <strong id="summary-elapsed-time">${shift.elapsed_time}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="text-muted small">Shift Type</div>
                        <span class="badge badge-info">${shift.shift_type_label}</span>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Ward</div>
                        <strong>${shift.ward_name}</strong>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Scheduled End</div>
                        <strong>${shift.scheduled_end || 'N/A'}</strong>
                    </div>
                </div>

                <h6 class="text-muted mb-3">Activity Summary</h6>
                <div class="row text-center">
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-danger">${counters.vitals || 0}</div>
                            <div class="stat-label">Vitals</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-warning">${counters.medications || 0}</div>
                            <div class="stat-label">Medications</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-info">${counters.injections || 0}</div>
                            <div class="stat-label">Injections</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-success">${counters.immunizations || 0}</div>
                            <div class="stat-label">Immunizations</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-box">
                            <div class="stat-value text-primary">${counters.notes || 0}</div>
                            <div class="stat-label">Notes</div>
                        </div>
                    </div>
                </div>

                ${actionsHtml}

                <div class="mt-4 text-center">
                    <div class="h4 text-primary">${shift.total_actions || 0}</div>
                    <div class="text-muted">Total Actions This Shift</div>
                </div>
            </div>
        `;

        $('#shift-summary-content').html(html);
    },

    // Start shift timer
    startShiftTimer: function() {
        const self = this;

        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
        }

        this.updateFabDisplay();

        this.shiftTimer = setInterval(function() {
            if (self.activeShift) {
                self.activeShift.elapsed_seconds = (self.activeShift.elapsed_seconds || 0) + 1;
                self.updateFabDisplay();

                // Check for overdue
                if (self.activeShift.remaining_seconds !== null) {
                    self.activeShift.remaining_seconds = Math.max(0, (self.activeShift.remaining_seconds || 0) - 1);
                }
            }
        }, 1000);
    },

    // Stop shift timer
    stopShiftTimer: function() {
        if (this.shiftTimer) {
            clearInterval(this.shiftTimer);
            this.shiftTimer = null;
        }
    },

    // Update FAB display
    updateFabDisplay: function() {
        if (!this.activeShift) return;

        const elapsed = this.activeShift.elapsed_seconds || 0;
        $('#shift-elapsed-time').text(this.formatElapsedTime(elapsed));

        // Check if overdue (past max shift duration)
        if (this.activeShift.is_overdue || elapsed> 12 * 3600) {
            $('.shift-fab-timer').addClass('overdue');
        } else {
            $('.shift-fab-timer').removeClass('overdue');
        }

        // Update FAB button state
        $('#shift-fab-btn').addClass('active');
    },

    // Format elapsed time
    formatElapsedTime: function(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours> 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    },

    // Toggle FAB actions
    toggleFabActions: function() {
        const actions = $('.shift-fab-actions');
        if (actions.is(':visible')) {
            actions.slideUp(200);
        } else {
            actions.slideDown(200);
        }
    },

    // Make FAB draggable
    makeFabDraggable: function() {
        const fab = document.getElementById('shift-control-fab');
        if (!fab) return;

        let isDragging = false;
        let startX, startY, startLeft, startBottom;

        fab.addEventListener('mousedown', startDrag);
        fab.addEventListener('touchstart', startDrag, { passive: false });

        function startDrag(e) {
            if (e.target.tagName === 'BUTTON') return; // Don't drag when clicking buttons

            isDragging = true;
            const rect = fab.getBoundingClientRect();

            if (e.type === 'touchstart') {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            } else {
                startX = e.clientX;
                startY = e.clientY;
            }

            startLeft = rect.left;
            startBottom = window.innerHeight - rect.bottom;

            document.addEventListener('mousemove', drag);
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchend', stopDrag);
        }

        function drag(e) {
            if (!isDragging) return;
            e.preventDefault();

            let clientX, clientY;
            if (e.type === 'touchmove') {
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                clientX = e.clientX;
                clientY = e.clientY;
            }

            const deltaX = clientX - startX;
            const deltaY = startY - clientY;

            const newRight = window.innerWidth - (startLeft + fab.offsetWidth + deltaX);
            const newBottom = startBottom + deltaY;

            // Keep within bounds
            fab.style.right = Math.max(10, Math.min(window.innerWidth - fab.offsetWidth - 10, newRight)) + 'px';
            fab.style.bottom = Math.max(10, Math.min(window.innerHeight - fab.offsetHeight - 10, newBottom)) + 'px';
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchend', stopDrag);
        }
    }
};

// Initialize Shift Manager on document ready
$(document).ready(function() {
    ShiftManager.init();
});
(function() {
    'use strict';

    var crCurrentDeleteItem = null;

    // Toggle "Other" textarea when "Other" is selected
    $('#crDeletionReasonSelect').on('change', function() {
        if ($(this).val() === 'Other') {
            $('#crDeletionReasonOther').removeClass('d-none');
        } else {
            $('#crDeletionReasonOther').addClass('d-none').val('');
        }
    });

    /**
     * Delete a nurse-created clinical request from history.
     * Uses the nursing-workbench DELETE routes.
     */
    window.deleteNurseClinicalRequest = function(type, id, name) {
        var pathMap = {
            lab:          'labs',
            imaging:      'imaging',
            prescription: 'prescriptions',
            procedure:    'procedures'
        };
        ClinicalOrdersKit.showDeleteConfirmation({
            type: type,
            itemName: name,
            onConfirm: function (reason, callback) {
                $.ajax({
        url: wbUrl('/nursing-workbench/clinical-requests/' + pathMap[type] + '/' + id),
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { reason: reason },
                    success: function(response) {
                        callback(true);
                        if (response.success) {
                            toastr.success(response.message || 'Request deleted successfully');
                            var tableMap = {
                                lab:          '#cr_lab_history_list',
                                imaging:      '#cr_imaging_history_list',
                                prescription: '#cr_presc_history_list',
                                procedure:    '#cr_proc_history_list'
                            };
                            var tableId = tableMap[type];
                            if (tableId && $.fn.DataTable.isDataTable(tableId)) {
                                $(tableId).DataTable().ajax.reload();
                            }
                        }
                    },
                    error: function(xhr) {
                        callback(false);
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to delete request';
                        toastr.error(msg);
                    }
                });
            }
        });
    };
})();
function openStoreContextOverride() {
    $('#storeContextOverrideModal').modal('show');
}
function confirmStoreContextOverride() {
    const storeId = $('#ctx-store-select').val();
    if (!storeId) {
        $('#ctx-override-error').text('Please select a store.').removeClass('d-none');
        return;
    }
    $('#ctx-override-error').addClass('d-none');
    $('#ctx-override-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Applying...');
    $.ajax({
        url: wbRoute('store-context.set', '/store-context/set'),
        method: 'POST',
        data: { store_id: storeId, context: 'ward', _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content')) },
        success: function () { window.location.reload(); },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message ?? 'Failed to update store context.';
            $('#ctx-override-error').text(msg).removeClass('d-none');
            $('#ctx-override-btn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Apply');
        }
    });
}
function showBundleRemove(btn) {
    var parentId = btn.dataset.parentId;
    var bundleName = btn.dataset.bundleName;
    var items = JSON.parse(btn.dataset.items || '[]');
    var removeUrl = btn.dataset.removeUrl;
    BundleRemoveModal.show({
        bundleId: parentId,
        bundleName: bundleName,
        items: items,
        onConfirm: function(callback) {
            $.ajax({
                url: removeUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ parent_request_id: parentId }),
                success: function(r) {
                    if (r.success) {
                        toastr.success(r.message || 'Combo removed');
                        callback(false);
                        if ($.fn.DataTable.isDataTable('#investigation_history_list')) { $('#investigation_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#cr_presc_history_list')) { $('#cr_presc_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#cr_lab_history_list')) { $('#cr_lab_history_list').DataTable().ajax.reload(null, false); }
                        if ($.fn.DataTable.isDataTable('#cr_imaging_history_list')) { $('#cr_imaging_history_list').DataTable().ajax.reload(null, false); }
                    } else {
                        toastr.error(r.message || 'Failed to remove combo');
                        callback(true);
                    }
                },
                error: function() { toastr.error('Error removing combo'); callback(true); }
            });
        }
    });
}
