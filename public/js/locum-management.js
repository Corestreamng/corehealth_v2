/**
 * CoreHealth v2 - Locum Ephemeral Access & Shift Passes Management Module
 */
(function ($) {
    'use strict';

    var LocumApp = {
        config: window.LOCUM_CONFIG || {},
        table: null,

        init: function () {
            this.initDataTable();
            this.initFilters();
            this.initCreateForm();
            this.initIssuePassForm();
            this.initPassActions();
            this.initModalDismissals();
        },

        initDataTable: function () {
            var self = this;
            if (!$('#locumTable').length) return;

            self.table = $('#locumTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: self.config.dataUrl || '/admin/locum/data',
                    data: function (d) {
                        d.cadre = $('#filter-cadre').val();
                        d.status = $('#filter-status').val();
                        d.clinic_id = $('#filter-clinic').val();
                    }
                },
                columns: [
                    { data: 'practitioner', name: 'practitioner' },
                    { data: 'cadre_roles', name: 'cadre_roles' },
                    { data: 'licensing', name: 'licensing' },
                    { data: 'shift_status', name: 'shift_status' },
                    { data: 'pin_preview', name: 'pin_preview', className: 'text-center' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center' }
                ],
                order: [[0, 'asc']],
                dom: '<"d-flex justify-content-between align-items-center mb-2"<"d-flex align-items-center"l><"ms-auto"f>>rtip',
                pageLength: 25,
                language: {
                    emptyTable: 'No locum practitioners registered yet. Click "Issue Locum Pass" to create one.'
                }
            });
        },

        initFilters: function () {
            var self = this;
            $('#filter-cadre, #filter-status, #filter-clinic').on('change', function () {
                if (self.table) {
                    self.table.ajax.reload();
                }
            });
        },

        notify: function (title, message, icon) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title || 'Notification',
                    text: message || '',
                    icon: icon || 'info',
                    confirmButtonColor: 'var(--hospital-primary, #011b33)'
                });
            } else {
                alert((title ? title + ': ' : '') + message);
            }
        },

        initCreateForm: function () {
            var self = this;

            // Setup Select2 with modal dropdownParent so dropdowns appear above modal
            function initModalSelect2() {
                if ($.fn.select2) {
                    var $modal = $('#modal-create-locum');
                    $modal.find('.select2').each(function () {
                        if ($(this).data('select2')) {
                            $(this).select2('destroy');
                        }
                        $(this).select2({
                            dropdownParent: $modal,
                            width: '100%'
                        });
                    });
                }
            }

            $('#modal-create-locum').on('shown.bs.modal shown', function () {
                initModalSelect2();
            });
            initModalSelect2();

            // Cadre template change auto-configures regulatory body and suggested role
            $('#locum_cadre_template').on('change', function () {
                var cadre = $(this).val();
                var regMap = {
                    'DOCTOR': 'MDCN',
                    'RADIOLOGIST': 'RRBN',
                    'LAB_SCIENTIST': 'MLSCN',
                    'NURSE': 'NMCN',
                    'PHARMACIST': 'PCN',
                    'CUSTOM': 'OTHER'
                };

                var roleMap = {
                    'DOCTOR': ['DOCTOR'],
                    'RADIOLOGIST': ['RADIOLOGIST'],
                    'LAB_SCIENTIST': ['LAB SCIENTIST'],
                    'NURSE': ['NURSE'],
                    'PHARMACIST': ['PHARMACIST'],
                    'CUSTOM': []
                };

                if (regMap[cadre]) {
                    $('#locum_regulatory_body').val(regMap[cadre]);
                }

                if (roleMap[cadre] && $('#locum_roles').length) {
                    $('#locum_roles').val(roleMap[cadre]).trigger('change');
                }
            });

            // Shift type toggle for custom dates
            $('#locum_shift_type').on('change', function () {
                if ($(this).val() === 'CUSTOM') {
                    $('#locum-custom-dates').slideDown();
                } else {
                    $('#locum-custom-dates').slideUp();
                }
            });

            // Form Submit
            $('#form-create-locum').on('submit', function (e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('button[type="submit"]');
                var originalText = $btn.html();

                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Provisioning...');

                $.ajax({
                    url: self.config.storeUrl || '/admin/locum',
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (res) {
                        $btn.prop('disabled', false).html(originalText);
                        if (res.success) {
                            $('#modal-create-locum').modal('hide');
                            $form[0].reset();
                            if ($('#locum_roles').length) {
                                $('#locum_roles').val(null).trigger('change');
                            }
                            if (self.table) {
                                self.table.ajax.reload();
                            }

                            // Show slip preview
                            self.showSlipModal(res.data, false);
                        } else {
                            self.notify('Creation Failed', res.message || 'Failed to create locum practitioner.', 'error');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html(originalText);
                        var msg = 'An error occurred during provisioning.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                            msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                        }
                        self.notify('Error', msg, 'error');
                    }
                });
            });
        },

        initIssuePassForm: function () {
            var self = this;

            $('#issue_pass_shift_type').on('change', function () {
                if ($(this).val() === 'CUSTOM') {
                    $('#issue-pass-custom-dates').slideDown();
                } else {
                    $('#issue-pass-custom-dates').slideUp();
                }
            });

            $('#form-issue-pass').on('submit', function (e) {
                e.preventDefault();
                var $form = $(this);
                var userId = $('#issue_pass_user_id').val();
                var $btn = $form.find('button[type="submit"]');
                var originalText = $btn.html();

                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Generating Pass...');

                var targetUrl = (self.config && self.config.issuePassUrl)
                    ? self.config.issuePassUrl.replace('__UID__', userId)
                    : (window.location.pathname.startsWith('/admin') ? '/admin/locum/' : '/locum/') + userId + '/issue-pass';

                $.ajax({
                    url: targetUrl,
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (res) {
                        $btn.prop('disabled', false).html(originalText);
                        if (res.success) {
                            $('#modal-issue-pass').modal('hide');
                            $form[0].reset();
                            if (self.table) {
                                self.table.ajax.reload();
                            }
                            // Show slip preview modal with newly generated pass details
                            self.showSlipModal(res.data, false);
                        } else {
                            self.notify('Pass Issuance Failed', res.message || 'Failed to issue shift pass.', 'error');
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html(originalText);
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error issuing shift pass.';
                        self.notify('Error', msg, 'error');
                    }
                });
            });
        },

        initPassActions: function () {
            var self = this;

            // Open Issue Pass Modal
            $(document).on('click', '.btn-locum-issue', function () {
                var userId = $(this).data('user-id');
                var userName = $(this).data('user-name');
                $('#issue_pass_user_id').val(userId);
                $('#issue_pass_practitioner_name').text(userName);
                $('#modal-issue-pass').modal('show');
            });

            // Extend Pass (+4h)
            $(document).on('click', '.btn-locum-extend', function () {
                var passId = $(this).data('id');

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Extend Shift Pass?',
                        text: 'Extend this locum clinician shift by 4 hours?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--hospital-primary, #011b33)',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="mdi mdi-clock-fast mr-1"></i> Yes, Extend (+4h)',
                        cancelButtonText: 'Cancel'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            self.executeExtendPass(passId);
                        }
                    });
                } else {
                    if (confirm('Extend this locum clinician shift by 4 hours?')) {
                        self.executeExtendPass(passId);
                    }
                }
            });

            // Revoke Pass
            $(document).on('click', '.btn-locum-revoke', function () {
                var passId = $(this).data('id');

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Revoke Shift Pass',
                        text: 'Please enter the reason for immediately revoking this shift pass:',
                        input: 'text',
                        inputPlaceholder: 'Reason for revocation...',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="mdi mdi-cancel mr-1"></i> Revoke Access',
                        cancelButtonText: 'Cancel',
                        inputValidator: function (value) {
                            if (!value || !value.trim()) {
                                return 'A revocation reason is required!';
                            }
                        }
                    }).then(function (result) {
                        if (result.isConfirmed && result.value) {
                            self.executeRevokePass(passId, result.value.trim());
                        }
                    });
                } else {
                    var reason = prompt('Please enter the reason for immediately revoking this shift pass:');
                    if (reason === null) return; // User cancelled
                    if ($.trim(reason) === '') {
                        self.notify('Error', 'A revocation reason is required.', 'error');
                        return;
                    }
                    self.executeRevokePass(passId, $.trim(reason));
                }
            });

            // Print Pass: opens the same slip modal that shows after create
            $(document).on('click', '.btn-locum-print', function () {
                var $btn = $(this);
                var passId = $btn.data('pass-id');
                var name = $btn.data('name');
                var email = $btn.data('email');
                var roles = $btn.data('roles');
                var pin = $btn.data('pin');
                var shift = $btn.data('shift');

                if (name) {
                    self.showSlipModal({
                        full_name: name,
                        email: email,
                        roles: roles,
                        pin: pin,
                        shift_type: shift,
                        expires_at: '',
                        temporary_password: 'Shift Pass (Active)'
                    }, true);
                } else if (passId) {
                    var detailsUrl = (self.config && self.config.passDetailsUrl)
                        ? self.config.passDetailsUrl.replace('__PID__', passId)
                        : (window.location.pathname.startsWith('/admin') ? '/admin/locum/pass/' : '/locum/pass/') + passId + '/details';

                    $.ajax({
                        url: detailsUrl,
                        method: 'GET',
                        success: function (res) {
                            if (res.success && res.data) {
                                self.showSlipModal(res.data, true);
                            } else {
                                self.notify('Error', 'Could not load shift pass slip.', 'error');
                            }
                        },
                        error: function () {
                            self.notify('Error', 'Could not load shift pass slip.', 'error');
                        }
                    });
                }
            });
        },

        executeExtendPass: function (passId) {
            var self = this;
            var targetUrl = (self.config && self.config.extendPassUrl)
                ? self.config.extendPassUrl.replace('__PID__', passId)
                : (window.location.pathname.startsWith('/admin') ? '/admin/locum/pass/' : '/locum/pass/') + passId + '/extend';

            $.ajax({
                url: targetUrl,
                method: 'POST',
                data: { minutes: 240 },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (res) {
                    if (res.success) {
                        if (self.table) self.table.ajax.reload();
                        self.notify('Shift Pass Extended', res.message, 'success');
                    } else {
                        self.notify('Failed', res.message || 'Failed to extend shift pass.', 'error');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not extend shift pass.';
                    self.notify('Extension Failed', msg, 'error');
                }
            });
        },

        executeRevokePass: function (passId, reason) {
            var self = this;
            var targetUrl = (self.config && self.config.revokePassUrl)
                ? self.config.revokePassUrl.replace('__PID__', passId)
                : (window.location.pathname.startsWith('/admin') ? '/admin/locum/pass/' : '/locum/pass/') + passId + '/revoke';

            $.ajax({
                url: targetUrl,
                method: 'POST',
                data: { reason: reason },
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (res) {
                    if (res.success) {
                        if (self.table) self.table.ajax.reload();
                        self.notify('Pass Revoked', res.message, 'success');
                    } else {
                        self.notify('Failed', res.message || 'Failed to revoke shift pass.', 'error');
                    }
                },
                error: function (xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not revoke shift pass.';
                    self.notify('Revocation Failed', msg, 'error');
                }
            });
        },

        showSlipModal: function (data, isPrint) {
            if (!data) return;
            if (isPrint) {
                $('#modalSlipPreviewLabel').html('<i class="mdi mdi-printer me-2"></i> Locum Shift Pass Slip');
            } else {
                $('#modalSlipPreviewLabel').html('<i class="mdi mdi-check-decagram me-2"></i> Pass Issued Successfully');
            }
            $('#slip_modal_name').text(data.full_name || '—');
            $('#slip_modal_email').text(data.email || '—');
            $('#slip_modal_roles').text(Array.isArray(data.roles) ? data.roles.join(', ') : (data.roles || '—'));
            $('#slip_modal_pin').text(data.pin || '******');
            $('#slip_modal_shift').text(data.shift_type ? (data.shift_type + (data.expires_at ? ' (Expires ' + data.expires_at + ')' : '')) : (data.shift || '—'));
            $('#slip_modal_temp_pass').text(data.temporary_password || 'Shift Pass (Active)');
            $('#modal-slip-preview').modal('show');
        },

        initModalDismissals: function () {
            // Universal modal close / dismiss handler ensuring clean closure across BS4 & BS5
            $(document).on('click', '[data-dismiss="modal"], [data-bs-dismiss="modal"], .modal .close, .modal .btn-close', function (e) {
                var $modal = $(this).closest('.modal');
                if ($modal.length) {
                    if (typeof $modal.modal === 'function') {
                        $modal.modal('hide');
                    }
                    if (window.bootstrap && window.bootstrap.Modal) {
                        var inst = window.bootstrap.Modal.getInstance($modal[0]);
                        if (inst) {
                            inst.hide();
                        }
                    }
                    $modal.removeClass('show').hide();
                    setTimeout(function () {
                        if (!$('.modal.show:visible').length) {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('overflow', '');
                        }
                    }, 200);
                }
            });
        }
    };

    $(document).ready(function () {
        LocumApp.init();
    });

})(jQuery);
