/**
 * Staff Directory & Quick Status Governance
 * Handles DataTables rendering, multi-criteria filtering, and 1-tap quick status transitions.
 */
$(function () {
    'use strict';

    var config = window.STAFF_DIRECTORY_CONFIG || {
        listUrl: '/liststaff',
        updateStatusUrl: '/staff'
    };

    var $table = $('#staffTable');
    if (!$table.length) {
        return;
    }

    var table = $table.DataTable({
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
        iDisplayLength: 50,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        buttons: [
            { extend: 'pageLength', className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'excel', className: 'btn btn-sm btn-outline-success', text: '<i class="mdi mdi-file-excel"></i> Excel', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
            { extend: 'pdf', className: 'btn btn-sm btn-outline-danger', text: '<i class="mdi mdi-file-pdf"></i> PDF', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
            { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="mdi mdi-printer"></i> Print', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } }
        ],
        processing: true,
        serverSide: true,
        ajax: {
            url: config.listUrl,
            type: 'GET',
            data: function (d) {
                d.department_id = $('#filter-department').val();
                d.category_id = $('#filter-category').val();
                d.employment_type = $('#filter-type').val();
                d.employment_status = $('#filter-status').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'staff_info' },
            { data: 'dept_info', orderable: false },
            { data: 'contact' },
            { data: 'category_roles', orderable: false },
            { data: 'status_info', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Loading staff...',
            emptyTable: 'No staff members found',
            zeroRecords: 'No matching staff found'
        },
        drawCallback: function () {
            if (typeof $('[data-toggle="tooltip"]').tooltip === 'function') {
                $('[data-toggle="tooltip"]').tooltip();
            }
        }
    });

    // Reload table on filter change
    $('#filter-department, #filter-category, #filter-type, #filter-status').on('change', function () {
        table.ajax.reload();
    });

    var $modal = $('#quickStatusModal');
    var $form = $('#quickStatusForm');
    var $statusSelect = $('#qs-employment-status');
    var $helpText = $('#qs-status-help');
    var $suspensionDates = $('#qs-suspension-dates');
    var $locumAlert = $('#qs-locum-alert');
    var $submitBtn = $('#qs-submit-btn');

    var statusDescriptions = {
        'active': 'Full active access across clinic queues, doctor availability, and credentials.',
        'probation': 'Active status under probation. Access permitted; flagged for performance review.',
        'on_leave': 'Portal self-service access allowed; automatically excluded from active clinical intake and doctor queues.',
        'suspended': 'Disciplinary hold. All system logins blocked immediately; active sessions revoked. If Locum, shift passes are revoked.',
        'resigned': 'Voluntary resignation. System access revoked immediately.',
        'terminated': 'Involuntary termination. System access revoked immediately.',
        'retired': 'Retirement exit. System access revoked immediately.'
    };

    function updateModalFormState(status, isLocum) {
        $helpText.text(statusDescriptions[status] || '');

        if (status === 'suspended') {
            $suspensionDates.slideDown(150);
            $('#qs-end-date-col').show();
            $('#qs-reason').attr('placeholder', 'Enter reason for suspension (required for disciplinary audit)...');
        } else if (status === 'terminated' || status === 'resigned' || status === 'retired') {
            $suspensionDates.slideDown(150);
            $('#qs-end-date-col').hide();
            $('#qs-reason').attr('placeholder', 'Enter reason or exit details...');
        } else {
            $suspensionDates.slideUp(150);
            $('#qs-reason').attr('placeholder', 'Enter reason or notes for status update (optional)...');
        }

        if (isLocum && (status === 'suspended' || status === 'terminated' || status === 'resigned' || status === 'retired')) {
            $locumAlert.show();
        } else {
            $locumAlert.hide();
        }
    }

    // Open Quick Status Modal
    $(document).on('click', '.btn-quick-status', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var userId = $btn.data('user-id');
        var name = $btn.data('name') || 'Staff Member';
        var currentStatus = $btn.data('status') || 'active';
        var reason = $btn.data('reason') || '';
        var endDate = $btn.data('end-date') || '';
        var isLocum = $btn.data('is-locum') == '1';

        $('#qs-user-id').val(userId);
        $('#qs-staff-name').text(name);

        var badgeClass = 'badge-secondary';
        if (currentStatus === 'active') badgeClass = 'badge-success';
        else if (currentStatus === 'probation') badgeClass = 'badge-info';
        else if (currentStatus === 'on_leave') badgeClass = 'badge-warning text-dark';
        else if (currentStatus === 'suspended') badgeClass = 'badge-danger';
        else if (currentStatus === 'terminated') badgeClass = 'badge-dark';

        $('#qs-current-status-badge')
            .attr('class', 'badge ' + badgeClass)
            .text(currentStatus.toUpperCase().replace('_', ' '));

        $statusSelect.val(currentStatus);
        $('#qs-reason').val(reason);
        $('#qs-end-date').val(endDate);

        $modal.data('is-locum', isLocum);
        updateModalFormState(currentStatus, isLocum);

        showQuickStatusModal();
    });

    function showQuickStatusModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        } else if (typeof $modal.modal === 'function') {
            $modal.modal('show');
        }
    }

    function hideQuickStatusModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance($modal[0]) || bootstrap.Modal.getOrCreateInstance($modal[0]);
            if (inst) {
                inst.hide();
            }
        }
        if (typeof $modal.modal === 'function') {
            $modal.modal('hide');
        }
    }

    $statusSelect.on('change', function () {
        var isLocum = $modal.data('is-locum');
        updateModalFormState($(this).val(), isLocum);
    });

    // Submit Quick Status Form
    $form.on('submit', function (e) {
        e.preventDefault();

        var userId = $('#qs-user-id').val();
        if (!userId) {
            return;
        }

        var selectedStatus = $statusSelect.val();
        var reason = $('#qs-reason').val().trim();

        if (selectedStatus === 'suspended' && !reason) {
            alert('Please provide a reason for suspension.');
            $('#qs-reason').focus();
            return;
        }

        var postUrl = config.updateStatusUrl.replace(/\/+$/, '') + '/' + userId + '/update-status';
        var formData = {
            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
            employment_status: selectedStatus,
            reason: reason,
            effective_date: $('#qs-effective-date').val(),
            suspension_end_date: $('#qs-end-date').val()
        };

        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

        $.ajax({
            url: postUrl,
            type: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function (res) {
            hideQuickStatusModal();
            table.ajax.reload(null, false);

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Status Updated',
                    text: res.message || 'Staff status updated successfully.',
                    timer: 2500,
                    showConfirmButton: false
                });
            } else if (typeof toastr !== 'undefined') {
                toastr.success(res.message || 'Staff status updated successfully.');
            } else {
                alert(res.message || 'Staff status updated successfully.');
            }
        }).fail(function (xhr) {
            var msg = 'Failed to update employment status.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            alert(msg);
        }).always(function () {
            $submitBtn.prop('disabled', false).html('<i class="mdi mdi-check"></i> Apply Status');
        });
    });
});
