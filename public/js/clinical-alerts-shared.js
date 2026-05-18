/**
 * Clinical Alerts Shared Module
 * Handles the CRUD operations for Patient Clinical Alerts and rendering
 * them in the sticky headers of various clinical workbenches.
 *
 * Renders a compact, clickable banner in the sticky header that
 * shows alert count + highest-severity preview. Full details live
 * in the modal, which is paginated (5 per page) for scalability.
 */
(function(window) {
    'use strict';

    // Wait for jQuery to be available
    function onReady(fn) {
        if (typeof jQuery !== 'undefined') {
            jQuery(fn);
        } else {
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof jQuery !== 'undefined') fn();
            });
        }
    }

    var ClinicalAlerts = {};
    var currentPatientId = null;
    var alertsData = [];
    var modalPage = 1;
    var modalPageSize = 5;

    // Initialize module
    ClinicalAlerts.init = function(patientId, role) {
        currentPatientId = patientId;
        modalPage = 1;

        // Define the role/department of the current workbench to filter alerts
        window.currentWorkbenchRole = role || 'doctors';

        if (patientId) {
            this.fetchAlerts();
        }

        this.bindEvents();
    };

    var eventsBound = false;

    ClinicalAlerts.bindEvents = function() {
        if (eventsBound) return;

        onReady(function() {
            var $ = jQuery;

            // Open modal from the manage-alerts button OR the sticky banner
            $(document).on('click', '.btn-manage-alerts, .clinical-alerts-banner:not(.no-alerts)', function(e) {
                e.preventDefault();
                var pid = $(this).data('patient-id') || currentPatientId;
                if (!pid) return;
                currentPatientId = pid;
                ClinicalAlerts.openModal();
            });

            // Refresh alerts in modal
            $(document).on('click', '#btn-refresh-alerts', function() {
                ClinicalAlerts.fetchAlerts();
            });

            // Save Alert Form Submit
            $(document).on('submit', '#clinical-alert-form', function(e) {
                e.preventDefault();
                ClinicalAlerts.saveAlert();
            });

            // Edit Alert Click
            $(document).on('click', '.btn-edit-alert', function() {
                var alertId = $(this).data('id');
                ClinicalAlerts.editAlert(alertId);
            });

            // Delete Alert Click
            $(document).on('click', '.btn-delete-alert', function() {
                var alertId = $(this).data('id');
                if(confirm('Are you sure you want to deactivate this alert?')) {
                    ClinicalAlerts.deleteAlert(alertId);
                }
            });

            // Cancel Edit
            $(document).on('click', '#btn-cancel-alert-edit', function() {
                ClinicalAlerts.resetForm();
            });

            // Pagination
            $(document).on('click', '#alerts-prev-page', function() {
                if (modalPage > 1) {
                    modalPage--;
                    ClinicalAlerts.renderModalList();
                }
            });

            $(document).on('click', '#alerts-next-page', function() {
                var totalPages = Math.ceil(alertsData.length / modalPageSize);
                if (modalPage < totalPages) {
                    modalPage++;
                    ClinicalAlerts.renderModalList();
                }
            });
        });

        eventsBound = true;
    };

    ClinicalAlerts.openModal = function() {
        this.resetForm();
        modalPage = 1;
        this.fetchAlerts();
        jQuery('#clinical-alerts-modal').modal('show');
    };

    ClinicalAlerts.fetchAlerts = function() {
        if (!currentPatientId) return;
        var $ = jQuery;

        $('#alerts-list-container').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i></div>');

        $.ajax({
            url: '/clinical-alerts/patient/' + currentPatientId,
            method: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    alertsData = response.data || [];
                    ClinicalAlerts.renderModalList();
                    ClinicalAlerts.renderStickyHeaderAlerts();
                }
            },
            error: function() {
                $('#alerts-list-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load alerts.</div>');
            }
        });
    };

    ClinicalAlerts.renderModalList = function() {
        var $ = jQuery;
        var container = $('#alerts-list-container');

        // Update total count
        $('#alerts-total-count').text(alertsData.length);

        if (alertsData.length === 0) {
            container.html('<div class="alert alert-info"><i class="mdi mdi-information-outline"></i> No active clinical alerts found for this patient.</div>');
            $('#alerts-pagination').hide();
            return;
        }

        // Calculate pagination
        var totalPages = Math.ceil(alertsData.length / modalPageSize);
        if (modalPage > totalPages) modalPage = totalPages;
        var start = (modalPage - 1) * modalPageSize;
        var end = Math.min(start + modalPageSize, alertsData.length);
        var pageAlerts = alertsData.slice(start, end);

        var html = '';
        pageAlerts.forEach(function(alert) {
            var creator = (alert.creator && alert.creator.user) ? (alert.creator.user.firstname + ' ' + alert.creator.user.surname) : 'Unknown';
            var date = new Date(alert.created_at).toLocaleDateString('en-GB');

            var visHtml = '';
            if (alert.visibility && alert.visibility.length > 0) {
                alert.visibility.forEach(function(v) {
                    visHtml += '<span class="badge bg-secondary me-1" style="font-size:.65rem;">' + v.replace(/_/g, ' ').toUpperCase() + '</span>';
                });
            } else {
                visHtml = '<span class="badge bg-info" style="font-size:.65rem;">ALL DEPARTMENTS</span>';
            }

            var severityBadge = alert.severity === 'high' ? 'bg-danger' : (alert.severity === 'medium' ? 'bg-warning text-dark' : 'bg-secondary');

            var canModify = !!(window.currentUserIsAdmin || (window.currentUserStaffId && window.currentUserStaffId == alert.created_by));
            var actionsHtml = '';
            if (canModify) {
                actionsHtml = '<div class="alert-actions">' +
                    '<button class="btn btn-sm btn-light btn-edit-alert" data-id="' + alert.id + '" title="Edit"><i class="mdi mdi-pencil text-primary"></i></button>' +
                    '<button class="btn btn-sm btn-light btn-delete-alert" data-id="' + alert.id + '" title="Deactivate"><i class="mdi mdi-delete text-danger"></i></button>' +
                '</div>';
            }

            html += '<div class="alert-card severity-' + alert.severity + '">' +
                '<div class="alert-card-header">' +
                    '<div style="flex:1;">' +
                        '<span class="badge ' + severityBadge + ' text-uppercase mb-1" style="font-size:.65rem;">' +
                            alert.severity + '</span>' +
                        '<div class="alert-text-content">' + _esc(alert.alert_text) + '</div>' +
                        '<div class="alert-visibility-badges">' +
                            '<small class="text-muted me-1">Visible:</small>' + visHtml +
                        '</div>' +
                    '</div>' +
                    actionsHtml +
                '</div>' +
                '<div class="alert-meta">' +
                    '<span><i class="mdi mdi-account-edit"></i> ' + _esc(creator) + '</span>' +
                    '<span><i class="mdi mdi-calendar"></i> ' + date + '</span>' +
                '</div>' +
            '</div>';
        });

        container.html(html);

        // Show/hide pagination
        if (totalPages > 1) {
            $('#alerts-pagination').show().css('display', 'flex');
            $('#alerts-page-info').text('Page ' + modalPage + ' of ' + totalPages + ' (' + alertsData.length + ' total)');
            $('#alerts-prev-page').prop('disabled', modalPage <= 1);
            $('#alerts-next-page').prop('disabled', modalPage >= totalPages);
        } else {
            $('#alerts-pagination').hide();
        }
    };

    ClinicalAlerts.renderStickyHeaderAlerts = function() {
        var $ = jQuery;
        var container = $('.sticky-header-alerts');
        if (!container.length) return;

        var role = window.currentWorkbenchRole || 'doctors';

        // Filter alerts by visibility for the current workbench
        var visibleAlerts = alertsData.filter(function(alert) {
            if (!alert.visibility || alert.visibility.length === 0) return true;
            return alert.visibility.indexOf(role) !== -1;
        });

        if (visibleAlerts.length === 0) {
            container.html(
                '<div class="clinical-alerts-banner no-alerts">' +
                    '<i class="mdi mdi-check-circle text-success"></i>' +
                    '<span class="banner-text">No active alerts</span>' +
                '</div>'
            );
            // Also update the manage-alerts button badge
            $('.btn-manage-alerts .alert-badge-count').remove();
            return;
        }

        // Determine highest severity for banner color
        var highCount = 0, medCount = 0, lowCount = 0;
        var previewTexts = [];
        visibleAlerts.forEach(function(a) {
            if (a.severity === 'high') highCount++;
            else if (a.severity === 'medium') medCount++;
            else lowCount++;
            if (previewTexts.length < 2) previewTexts.push(a.alert_text);
        });

        var bannerSeverity = highCount > 0 ? 'high' : (medCount > 0 ? 'medium' : 'low');
        var icon = bannerSeverity === 'high' ? 'alert-octagon' : (bannerSeverity === 'medium' ? 'alert' : 'information');
        var totalCount = visibleAlerts.length;

        // Build compact summary: show first alert text, and "+N more" if there are more
        var summaryText = previewTexts[0];
        if (summaryText && summaryText.length > 60) {
            summaryText = summaryText.substring(0, 57) + '...';
        }
        if (totalCount > 1) {
            summaryText += ' (+' + (totalCount - 1) + ' more)';
        }

        var bannerHtml =
            '<div class="clinical-alerts-banner severity-' + bannerSeverity + '" title="Click to manage alerts">' +
                '<i class="mdi mdi-' + icon + '"></i>' +
                '<span class="banner-count">' + totalCount + '</span>' +
                '<span class="banner-text">' + _esc(summaryText || 'Clinical alert active') + '</span>' +
                '<span class="banner-cta"><i class="mdi mdi-chevron-right"></i> View All</span>' +
            '</div>';

        container.html(bannerHtml);

        // Update the manage-alerts button with count badge
        var $manageBtn = $('.btn-manage-alerts');
        $manageBtn.find('.alert-badge-count').remove();
        if (totalCount > 0) {
            $manageBtn.append(' <span class="badge bg-white text-danger alert-badge-count">' + totalCount + '</span>');
        }
    };

    // HTML-escape utility
    function _esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }

    ClinicalAlerts.saveAlert = function() {
        var $ = jQuery;
        var alertId = $('#alert_id').val();
        var isEdit = !!alertId;

        var visibility = [];
        $('.visibility-check:checked').each(function() {
            visibility.push($(this).val());
        });

        var payload = {
            alert_text: $('#alert_text').val(),
            severity: $('#alert_severity').val(),
            visibility: visibility,
            _token: $('meta[name="csrf-token"]').attr('content')
        };

        var url = '/clinical-alerts/patient/' + currentPatientId;
        var method = 'POST';

        if (isEdit) {
            url += '/' + alertId;
            method = 'PUT';
        }

        var btn = $('#btn-save-alert');
        var originalText = btn.html();
        btn.html('<i class="mdi mdi-loading mdi-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: url,
            method: method,
            data: payload,
            success: function(response) {
                if (response.status === 'success') {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(isEdit ? 'Alert updated successfully.' : 'Alert created successfully.');
                    }
                    ClinicalAlerts.resetForm();
                    modalPage = 1;
                    ClinicalAlerts.fetchAlerts();
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save alert.';
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
            },
            complete: function() {
                btn.html(originalText).prop('disabled', false);
            }
        });
    };

    ClinicalAlerts.editAlert = function(id) {
        var $ = jQuery;
        var alert = alertsData.find(function(a) { return a.id == id; });
        if (!alert) return;

        $('#alert-form-title').html('<i class="mdi mdi-pencil"></i> Edit Alert');
        $('#alert_id').val(alert.id);
        $('#alert_text').val(alert.alert_text);
        $('#alert_severity').val(alert.severity);

        $('.visibility-check').prop('checked', false);
        if (alert.visibility && alert.visibility.length > 0) {
            alert.visibility.forEach(function(v) {
                $('.visibility-check[value="' + v + '"]').prop('checked', true);
            });
        }

        $('#btn-save-alert').html('<i class="mdi mdi-content-save"></i> Update Alert');
        $('#btn-cancel-alert-edit').show();

        // Scroll form into view inside modal
        $('#alert-form-title')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    ClinicalAlerts.deleteAlert = function(id) {
        var $ = jQuery;
        $.ajax({
            url: '/clinical-alerts/patient/' + currentPatientId + '/' + id,
            method: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status === 'success') {
                    if (typeof toastr !== 'undefined') {
                        toastr.success('Alert deactivated.');
                    }
                    ClinicalAlerts.fetchAlerts();
                }
            }
        });
    };

    ClinicalAlerts.resetForm = function() {
        var $ = jQuery;
        $('#alert-form-title').html('<i class="mdi mdi-plus-circle"></i> Add New Alert');
        var form = document.getElementById('clinical-alert-form');
        if (form) form.reset();
        $('#alert_id').val('');
        $('#btn-save-alert').html('<i class="mdi mdi-content-save"></i> Save Alert');
        $('#btn-cancel-alert-edit').hide();
    };

    window.ClinicalAlerts = ClinicalAlerts;

})(window);
