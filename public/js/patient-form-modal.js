// Shared flag – used by both the main form logic (document.ready) and the
// emergency-mode IIFE below.
var pfEmergencyMode = false;
var pfActiveScheme = '';

// Patient Form Config - must be set by the including page
if (typeof window.patientFormConfig === 'undefined') {
    window.patientFormConfig = {
        nextFileNumberUrl: '/reception/patient/next-file-number',
        checkFileNumberUrl: '/reception/patient/check-file-number',
        updateUrl: '/reception/patient/__ID__/update',
        registerUrl: wbRoute('reception.patient.quick-register', '/reception/patient/quick-register'),
        emergencyIntakeUrl: wbRoute('emergency.intake', '/emergency/intake'),
        hmos: [],
        onSuccess: function(patientId, mode) {}
    };
} else {
    // Ensure defaults exist even if partially provided
    window.patientFormConfig.nextFileNumberUrl = window.patientFormConfig.nextFileNumberUrl || '/reception/patient/next-file-number';
    window.patientFormConfig.checkFileNumberUrl = window.patientFormConfig.checkFileNumberUrl || '/reception/patient/check-file-number';
    window.patientFormConfig.updateUrl = window.patientFormConfig.updateUrl || '/reception/patient/__ID__/update';
    window.patientFormConfig.registerUrl = window.patientFormConfig.registerUrl || '/reception/patient/quick-register';
    window.patientFormConfig.emergencyIntakeUrl = window.patientFormConfig.emergencyIntakeUrl || '/emergency/intake';
    window.patientFormConfig.submitUrl = window.patientFormConfig.submitUrl || window.patientFormConfig.emergencyIntakeUrl;
}

// =============================================
// PATIENT FORM MODAL (REGISTER/EDIT)
// =============================================
let patientFormCurrentStep = 1;
let patientFormTotalSteps = 6;
let pfWalkInMode = false;
let pfStepSequence = [1, 2, 3, 4];
let patientFormAllergies = [];

function showPatientFormModal(mode = 'create', patientData = null) {
    // Reset form
    resetPatientForm();

    // Set mode
    $('#patient-form-mode').val(mode);

    if (mode === 'edit' && patientData) {
        $('#patient-form-id').val(patientData.id);
        $('#patient-form-title').html('<i class="mdi mdi-account-edit"></i> Edit Patient');
        $('#patient-form-header').addClass('edit-mode');
        $('#pf-submit-text').text('Update Patient');

        // Populate form with patient data
        populatePatientForm(patientData);
    } else {
        $('#patient-form-title').html('<i class="mdi mdi-account-plus"></i> New Patient Registration');
        $('#patient-form-header').removeClass('edit-mode');
        $('#pf-submit-text').text('Register Patient');

        // Generate new file number
        generatePatientFormFileNumber();
    }

    // Populate HMO dropdown
    populatePatientFormHMO();

    // Show modal
    $('#patientFormModal').modal('show');
}

function resetPatientForm() {
    // Reset form fields
    $('#patient-form')[0].reset();
    $('#patient-form-id').val('');
    $('#patient-form-mode').val('create');

    // Reset age/DOB toggle to Age mode
    $('.pf-adt-btn').removeClass('active');
    $('.pf-adt-btn[data-mode="age"]').addClass('active');
    $('.pf-age-dob-wrapper').removeClass('mode-dob').addClass('mode-age');
    $('#pf-age-val').val('');
    $('#pf-age-unit').val('years');
    $('#pf-age-dob-hint').html('');
    $('#pf-age-dob-error').hide().text('');

    // Reset duplicate detection
    _dupDismissed = false;
    $('#pf-duplicate-panel').hide();
    $('#pf-dup-list').empty();

    // Reset file number toggle to Auto mode
    $('#pf-file-no').prop('readonly', true);
    $('#pf-file-no-toggle').prop('checked', false);
    $('#mode-auto-label').addClass('active');
    $('#mode-manual-label').removeClass('active');
    $('#pf-file-no-hint').html('<i class="mdi mdi-information-outline"></i> Next number: <strong id="pf-last-file-no">--</strong> + 1 = <strong id="pf-next-file-no">--</strong>').removeClass('manual-mode');

    // Reset stepper
    patientFormCurrentStep = 1;
    updatePatientFormStepper();

    // Reset allergies
    patientFormAllergies = [];
    updateAllergiesTags();

    // Reset validation states
    $('#patient-form .form-control').removeClass('is-valid is-invalid');

    // Clear file uploads
    $('#pf-passport').val('').removeData('existing');
    $('#pf-old-records').val('').removeData('existing');
    $('#pf-passport-data').val(''); // Clear webcam capture data

    // Reset photo capture UI
    $('#photo-preview-wrapper').removeClass('show');
    $('#photo-preview-img').attr('src', '');
    $('.photo-capture-tab').removeClass('active').first().addClass('active');
    $('.photo-capture-panel').removeClass('active');
    $('#panel-upload').addClass('active');

    // Reset family folder
    $('#pf-is-family-principal').prop('checked', false);
    $('#pf-principal-select-container').show();
    if ($.fn.select2) {
        $('#pf-principal-id').val(null).trigger('change');
    }

    // Legacy passport preview elements (keep for compatibility)
    $('.passport-preview-container').hide();
    $('#passport-preview-img').attr('src', '');
    $('#pf-passport-new-preview').hide();
    $('#passport-new-img').attr('src', '');

    // Old records
    $('.old-records-preview-container').hide();
    $('#old-records-preview-img').attr('src', '').hide();
    $('#old-records-preview-icon').hide();
    $('#old-records-preview-name').text('');
    $('#pf-old-records-new-preview').hide();
    $('#old-records-new-name').text('');
    $('#pf-view-old-records').attr('href', '#');

    // Show step 1
    $('.form-step').removeClass('active');
    $('.form-step[data-step="1"]').addClass('active');

    // Show/hide navigation buttons
    updatePatientFormNavigation();

    // Clear summary
    clearPatientFormSummary();
}

function generatePatientFormFileNumber() {
    if (pfWalkInMode || pfEmergencyMode) return;
    const $input = $('#pf-file-no');
    $input.removeClass('status-valid status-checking status-duplicate');

    $.ajax({
        url: patientFormConfig.nextFileNumberUrl,
        method: 'GET',
        success: function(response) {
            $('#pf-file-no').val(response.file_no);
            $('#pf-next-file-no').text(response.file_no);

            // Update format pattern display
            if (response.format_pattern) {
                $('#pf-format-pattern').text(response.format_pattern);
            } else {
                $('#pf-format-pattern').text('Sequential');
            }

            // Populate recent file numbers
            const $recentList = $('#pf-recent-file-nos');
            $recentList.empty();
            if (response.recent_file_nos && response.recent_file_nos.length> 0) {
                response.recent_file_nos.forEach(fileNo => {
                    $recentList.append(`<span class="file-no-recent-item" data-file-no="${fileNo}">${fileNo}</span>`);
                });
            }

            // Store data for later use
            $input.data('lastFileNo', response.last_file_no);
            $input.data('formatPattern', response.format_pattern);

            // Mark as valid (auto-generated)
            $input.addClass('status-valid');
            $('#pf-duplicate-warning').hide();
        },
        error: function() {
            const now = new Date();
            const fallbackNo = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`;
            $('#pf-file-no').val(fallbackNo);
            $('#pf-next-file-no').text(fallbackNo);
            $('#pf-format-pattern').text('Auto-generated');
            $('#pf-recent-file-nos').empty();
        }
    });
}

// Debounced file number duplicate check
let fileNoCheckTimeout = null;
function checkFileNumberDuplicate(fileNo, excludePatientId = null) {
    const $input = $('#pf-file-no');

    // Clear previous timeout
    if (fileNoCheckTimeout) {
        clearTimeout(fileNoCheckTimeout);
    }

    if (!fileNo || fileNo.trim() === '') {
        $input.removeClass('status-valid status-checking status-duplicate');
        $('#pf-duplicate-warning').hide();
        return;
    }

    // Show checking state
    $input.removeClass('status-valid status-duplicate').addClass('status-checking');

    // Debounce the AJAX call
    fileNoCheckTimeout = setTimeout(function() {
        $.ajax({
            url: patientFormConfig.checkFileNumberUrl,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                file_no: fileNo,
                exclude_patient_id: excludePatientId
            },
            success: function(response) {
                $input.removeClass('status-checking');

                if (response.exists) {
                    // Show warning (not blocking, just informative)
                    $input.addClass('status-duplicate');
                    const $warning = $('#pf-duplicate-warning');
                    const $patients = $('#pf-duplicate-patients');

                    let html = '';
                    response.patients.forEach(p => {
                        html += `<div class="duplicate-patient"><i class="mdi mdi-account"></i> ${p.name} (${p.file_no})</div>`;
                    });
                    if (response.count> 3) {
                        html += `<div class="duplicate-patient text-muted">...and ${response.count - 3} more</div>`;
                    }
                    $patients.html(html);
                    $warning.show();
                } else {
                    // File number is unique
                    $input.addClass('status-valid');
                    $('#pf-duplicate-warning').hide();
                }
            },
            error: function() {
                $input.removeClass('status-checking');
            }
        });
    }, 400); // 400ms debounce
}

// Click handler for recent file numbers (copy to input)
$(document).on('click', '.file-no-recent-item', function() {
    const fileNo = $(this).data('file-no');
    const $input = $('#pf-file-no');

    // Switch to manual mode
    toggleFileNumberEdit('manual');

    // Set the value
    $input.val(fileNo);

    // Check for duplicates
    checkFileNumberDuplicate(fileNo);

    toastr.info(`Copied "${fileNo}" - you can edit it now`);
});

// Keyboard shortcut: Ctrl+G to regenerate file number
$(document).on('keydown', function(e) {
    if (e.ctrlKey && e.key === 'g' && $('#patientFormModal').is(':visible')) {
        e.preventDefault();
        toggleFileNumberEdit('auto');
        toastr.info('File number regenerated');
    }
});

// Refresh button click handler
$('#pf-file-no-refresh').on('click', function() {
    toggleFileNumberEdit('auto');
    toastr.info('File number regenerated');
});

// Input change handler for duplicate check (in manual mode)
$('#pf-file-no').on('input', function() {
    const $input = $(this);
    if (!$input.prop('readonly')) {
        const fileNo = $input.val();
        const excludeId = $input.data('editPatientId'); // Set when editing existing patient
        checkFileNumberDuplicate(fileNo, excludeId);
    }
});

// ============================================
// DUPLICATE PATIENT DETECTION
// ============================================
let _dupCheckTimeout = null;
let _dupDismissed = false;

function checkDuplicatePatient() {
    if (_dupDismissed) return;
    if ($('#patient-form-mode').val() === 'edit') return; // Skip in edit mode

    var surname = $('#pf-surname').val().trim();
    var firstname = $('#pf-firstname').val().trim();
    var phone = $('#pf-phone').val().trim();
    var dob = $('#pf-dob').val();

    // Need at least surname+firstname (2+ chars each) or phone (7+ chars)
    var hasName = surname.length>= 2 && firstname.length>= 2;
    var hasPhone = phone.length>= 7;
    if (!hasName && !hasPhone) {
        $('#pf-duplicate-panel').slideUp(200);
        return;
    }

    if (_dupCheckTimeout) clearTimeout(_dupCheckTimeout);
    _dupCheckTimeout = setTimeout(function() {
        $.ajax({
            url: wbUrl('/reception/patient/check-duplicate'),
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                surname: surname,
                firstname: firstname,
                phone: phone,
                dob: dob,
                exclude_patient_id: $('#patient-form-id').val() || null
            },
            success: function(resp) {
                if (_dupDismissed) return;
                if (resp.count> 0) {
                    renderDuplicateHints(resp.matches);
                } else {
                    $('#pf-duplicate-panel').slideUp(200);
                }
            }
        });
    }, 600);
}

function renderDuplicateHints(matches) {
    var $list = $('#pf-dup-list').empty();
    $('#pf-dup-plural').text(matches.length> 1 ? 's' : '');

    matches.forEach(function(m) {
        var initials = (m.name || '??').split(' ').map(function(w) { return w[0]; }).join('').substring(0, 2).toUpperCase();
        var scoreClass = m.score>= 50 ? 'high' : (m.score>= 30 ? 'medium' : 'low');

        var metaParts = [];
        if (m.file_no) metaParts.push('<i class="mdi mdi-file-document-outline"></i> ' + m.file_no);
        if (m.phone) metaParts.push('<i class="mdi mdi-phone"></i> ' + m.phone);
        if (m.dob) metaParts.push('<i class="mdi mdi-calendar"></i> ' + m.dob);
        if (m.gender) metaParts.push(m.gender);

        var reasons = m.reasons.map(function(r) {
            return '<span class="pf-dup-reason ' + scoreClass + '">' + r + '</span>';
        }).join('');

        $list.append(
            '<div class="pf-dup-item" data-patient-id="' + m.id + '">' +
                '<div class="pf-dup-avatar">' + initials + '</div>' +
                '<div class="pf-dup-info">' +
                    '<div class="pf-dup-name">' + (m.name || 'Unknown') + '</div>' +
                    '<div class="pf-dup-meta">' + metaParts.join(' &middot; ') + '</div>' +
                '</div>' +
                '<div class="pf-dup-reasons">' + reasons + '</div>' +
                '<button type="button" class="pf-dup-select" title="Select this patient"><i class="mdi mdi-account-check"></i> Select</button>' +
            '</div>'
        );
    });

    $('#pf-duplicate-panel').slideDown(300);
}

function toggleFileNumberEdit(mode) {
    const $input = $('#pf-file-no');
    const $hint = $('#pf-file-no-hint');
    const $buttons = $('.file-no-mode-btn');

    // Update button states
    $buttons.removeClass('active');
    $buttons.filter('[data-mode="' + mode + '"]').addClass('active');

    if (mode === 'manual') {
        // Manual mode - allow editing
        $input.prop('readonly', false).attr('placeholder', 'Enter file number');
        $hint.addClass('manual-mode');
        $input.focus().select();

        // Check current value for duplicates
        if ($input.val()) {
            checkFileNumberDuplicate($input.val(), $input.data('editPatientId'));
        }
    } else {
        // Auto mode - readonly with generated number
        $input.prop('readonly', true).attr('placeholder', 'Auto-generated');
        $hint.removeClass('manual-mode');
        generatePatientFormFileNumber();
    }
}

function populatePatientFormHMO() {
    const $select = $('#pf-hmo');
    $select.empty();

    if (!patientFormConfig.hmos || !patientFormConfig.hmos.length) return;

    // Group HMOs by scheme
    const grouped = {};
    patientFormConfig.hmos.forEach(hmo => {
        const schemeName = hmo.scheme_name || hmo.scheme || 'Other';
        if (!grouped[schemeName]) grouped[schemeName] = [];
        grouped[schemeName].push(hmo);
    });

    // Sort: Self/Private first, then alphabetical
    const schemes = Object.keys(grouped).sort((a, b) => {
        if (a === 'Self/Private') return -1;
        if (b === 'Self/Private') return 1;
        return a.localeCompare(b);
    });

    // Render scheme cards
    const $grid = $('#pf-scheme-grid').empty();
    const schemeIcons = {
        'Self/Private': 'mdi-account-outline',
        'Private Health Insurance Scheme': 'mdi-shield-account-outline',
        'National Health Insurance Scheme': 'mdi-shield-check-outline',
        'State Health Insurance Scheme': 'mdi-shield-star-outline',
        'Corporate': 'mdi-office-building-outline',
        'Others': 'mdi-dots-horizontal-circle-outline',
    };
    schemes.forEach(function(scheme) {
        const icon = schemeIcons[scheme] || 'mdi-shield-account-outline';
        const $card = $(`<button type="button" class="pf-scheme-card" data-scheme="${scheme}"><i class="mdi ${icon}"></i><span>${scheme}</span><span class="pf-scheme-count">${grouped[scheme].length}</span></button>`);
        $grid.append($card);
    });

    // Default active scheme: Self/Private if exists, else first
    const defaultScheme = grouped['Self/Private'] ? 'Self/Private' : schemes[0];
    function activateScheme(scheme) {
        pfActiveScheme = scheme;
        $('.pf-scheme-card').removeClass('active');
        $(`.pf-scheme-card[data-scheme="${scheme}"]`).addClass('active');

        const isSelfPrivate = (scheme === 'Self/Private');
        $select.empty();
        if (!isSelfPrivate) {
            // Empty placeholder — user must explicitly pick an HMO
            $select.append('<option value="">-- Select HMO --</option>');
        }
        (grouped[scheme] || []).forEach(hmo => {
            $select.append(`<option value="${hmo.id}">${hmo.name}</option>`);
        });

        // Show HMO select only for non-self-pay schemes
        $('#pf-hmo-select-col').toggle(!isSelfPrivate);

        if ($.fn.select2) {
            if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
            $select.select2({
                dropdownParent: $('#patientFormModal'),
                placeholder: isSelfPrivate ? '' : 'Select HMO',
                allowClear: false,
                width: '100%'
            });
            $select.trigger('change');
        }
    }

    $(document).off('click.pfScheme').on('click.pfScheme', '.pf-scheme-card', function() {
        activateScheme($(this).data('scheme'));
    });

    activateScheme(defaultScheme);
}

function populatePatientForm(data) {
    // Basic info
    $('#pf-file-no').val(data.file_no || '');
    $('#pf-surname').val(data.surname || '');
    $('#pf-firstname').val(data.firstname || '');
    $('#pf-othername').val(data.othername || '');
    $('#pf-gender').val(data.gender || '');

    // Parse DOB (may be in d/m/Y format)
    if (data.dob) {
        let dob = data.dob;
        // Check if it's in d/m/Y format
        if (dob.includes('/')) {
            const parts = dob.split('/');
            if (parts.length === 3) {
                dob = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        $('#pf-dob').val(dob).trigger('change');
        // Switch to DOB mode for edit since we have an exact date
        $('.pf-adt-btn').removeClass('active');
        $('.pf-adt-btn[data-mode="dob"]').addClass('active');
        $('.pf-age-dob-wrapper').removeClass('mode-age').addClass('mode-dob');
        updatePatientFormAge();
    }

    $('#pf-phone').val(data.phone_no || '');
    $('#pf-email').val(data.email || '');
    $('#pf-address').val(data.address || '');

    // Medical info
    $('#pf-blood-group').val(data.blood_group || '');
    $('#pf-genotype').val(data.genotype || '');
    $('#pf-disability').val(data.disability ? '1' : '0');
    $('#pf-nationality').val(data.nationality || 'Nigerian');
    $('#pf-ethnicity').val(data.ethnicity || '');
    $('#pf-medical-history').val(data.medical_history || '');
    $('#pf-misc').val(data.misc || '');

    // Allergies
    if (data.allergies) {
        try {
            patientFormAllergies = typeof data.allergies === 'string' ? JSON.parse(data.allergies) : data.allergies;
            updateAllergiesTags();
        } catch (e) {
            patientFormAllergies = [];
        }
    }

    // Next of Kin
    $('#pf-nok-name').val(data.next_of_kin_name || '');
    $('#pf-nok-phone').val(data.next_of_kin_phone || '');
    $('#pf-nok-address').val(data.next_of_kin_address || '');

    // Insurance
    if (data.hmo_id) {
        setTimeout(() => {
            // Find the scheme for this HMO and activate it
            if (patientFormConfig && patientFormConfig.hmos) {
                var hmoEntry = patientFormConfig.hmos.find(function(h) { return h.id == data.hmo_id; });
                if (hmoEntry) {
                    var schemeName = hmoEntry.scheme_name || hmoEntry.scheme || 'Other';
                    var $card = $('.pf-scheme-card[data-scheme="' + schemeName + '"]');
                    if ($card.length) $card.trigger('click');
                }
            }
            $('#pf-hmo').val(data.hmo_id).trigger('change');
            $('#pf-hmo-no').val(data.hmo_no || '');
            $('#pf-hmo-no-container').show();
        }, 150);
    }

    // Family Folder
    if (data.is_family_principal == 1) {
        $('#pf-is-family-principal').prop('checked', true).trigger('change');
    } else {
        $('#pf-is-family-principal').prop('checked', false).trigger('change');
        if (data.principal_id && data.principal) {
            // Append the option to select2
            var option = new Option(data.principal.user.firstname + ' ' + data.principal.user.surname + ' (' + data.principal.file_no + ')', data.principal_id, true, true);
            $('#pf-principal-id').append(option).trigger('change');
        }
    }

    // Handle existing passport photo - use new photo capture UI
    if (data.passport_url) {
        // Show in the new photo preview UI
        $('#photo-preview-img').attr('src', data.passport_url);
        $('#photo-source-badge').html('<i class="mdi mdi-check-circle"></i> Current Photo');
        $('#photo-filename').text(data.filename || 'Patient photo');
        $('#photo-preview-wrapper').addClass('show');
        $('.photo-capture-panel').removeClass('active');
        // Store existing reference
        $('#pf-passport').data('existing', data.filename);
    } else {
        $('#photo-preview-wrapper').removeClass('show');
        $('.photo-capture-tab').removeClass('active').first().addClass('active');
        $('.photo-capture-panel').removeClass('active');
        $('#panel-upload').addClass('active');
    }

    // Handle existing old records
    if (data.old_records_url) {
        $('.old-records-preview-container').show();
        const ext = data.old_records.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
            $('#old-records-preview-img').attr('src', data.old_records_url).show();
            $('#old-records-preview-icon').hide();
        } else {
            $('#old-records-preview-img').hide();
            $('#old-records-preview-icon').show();
        }
        $('#old-records-preview-name').text(data.old_records);
        $('#pf-view-old-records').attr('href', data.old_records_url);
        // Store existing filename for reference
        $('#pf-old-records').data('existing', data.old_records);
    } else {
        $('.old-records-preview-container').hide();
        $('#old-records-preview-img').attr('src', '').hide();
        $('#old-records-preview-name').text('');
        $('#pf-view-old-records').attr('href', '#');
    }

    // Trigger change for floating labels
    $('#patient-form .form-control').each(function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        }
    });
}

function updatePatientFormStepper() {
    $('.stepper-item').each(function() {
        const step = parseInt($(this).data('step'));
        $(this).removeClass('active completed');

        if (step < patientFormCurrentStep) {
            $(this).addClass('completed');
        } else if (step === patientFormCurrentStep) {
            $(this).addClass('active');
        }
    });

    $('.stepper-line').each(function(index) {
        $(this).removeClass('completed');
        if (index + 1 < patientFormCurrentStep) {
            $(this).addClass('completed');
        }
    });
}

function updatePatientFormNavigation() {
    // Show/hide prev button
    if (patientFormCurrentStep === pfStepSequence[0]) {
        $('#pf-btn-prev').hide();
    } else {
        $('#pf-btn-prev').show();
    }

    // Show/hide next/submit buttons
    var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
    var isLast = (currentIdx === pfStepSequence.length - 1);

    if (isLast) {
        $('#pf-btn-next').hide();
        $('#pf-btn-submit').show();
        // Show registration summary, hide emergency summary
        $('#registration-summary').show();
        $('#emergency-intake-summary').hide();
        updatePatientFormSummary();
    } else {
        $('#pf-btn-next').show();
        $('#pf-btn-submit').hide();
    }
}

function goToPatientFormStep(step) {
    if (step < 1 || step> patientFormTotalSteps) return;

    // Validate current step before moving forward
    if (step> patientFormCurrentStep && !validatePatientFormStep(patientFormCurrentStep)) {
        return;
    }

    patientFormCurrentStep = step;

    // Show the step
    $('.form-step').removeClass('active');
    $(`.form-step[data-step="${step}"]`).addClass('active');

    // Update stepper
    updatePatientFormStepper();

    // Update navigation
    updatePatientFormNavigation();

    // Scroll to top of modal body
    $('.form-steps-container').scrollTop(0);
}

function validatePatientFormStep(step) {
    let isValid = true;
    const $step = $(`.form-step[data-step="${step}"]`);

    // Clear previous validations
    $step.find('.form-control').removeClass('is-valid is-invalid');
    $step.find('.invalid-feedback').text('');

    if (step === 1) {
        // Validate basic info
        const surname = $('#pf-surname').val().trim();
        const firstname = $('#pf-firstname').val().trim();
        const gender = $('#pf-gender').val();
        const dob = $('#pf-dob').val();

        if (!surname) {
            $('#pf-surname').addClass('is-invalid');
            $('#pf-surname').siblings('.invalid-feedback').text('Surname is required');
            isValid = false;
        } else {
            $('#pf-surname').addClass('is-valid');
        }

        if (!firstname) {
            $('#pf-firstname').addClass('is-invalid');
            $('#pf-firstname').siblings('.invalid-feedback').text('First name is required');
            isValid = false;
        } else {
            $('#pf-firstname').addClass('is-valid');
        }

        if (!gender) {
            $('#pf-gender').addClass('is-invalid');
            $('#pf-gender').siblings('.invalid-feedback').text('Gender is required');
            isValid = false;
        } else {
            $('#pf-gender').addClass('is-valid');
        }

        if (!dob) {
            // Show error on whichever panel is visible
            var $ageWrapper = $('.pf-age-dob-wrapper');
            if ($ageWrapper.hasClass('mode-age')) {
                $('#pf-age-val').addClass('is-invalid');
            } else {
                $('#pf-dob').addClass('is-invalid');
            }
            $('#pf-age-dob-error').text('Enter age or date of birth').show();
            isValid = false;
        } else {
            if ($('.pf-age-dob-wrapper').hasClass('mode-age')) {
                $('#pf-age-val').addClass('is-valid');
            } else {
                $('#pf-dob').addClass('is-valid');
            }
            $('#pf-age-dob-error').hide();
        }

        // Validate phone if provided
        const phone = $('#pf-phone').val().trim();
        if (phone && !isValidPhone(phone)) {
            $('#pf-phone').addClass('is-invalid');
            $('#pf-phone').siblings('.invalid-feedback').text('Invalid phone number');
            isValid = false;
        }

        // Validate email if provided
        const email = $('#pf-email').val().trim();
        if (email && !isValidEmail(email)) {
            $('#pf-email').addClass('is-invalid');
            $('#pf-email').siblings('.invalid-feedback').text('Invalid email address');
            isValid = false;
        }
    }

    if (step === 4) {
        // Emergency mode: existing patients already have HMO on record — skip check.
        if (pfEmergencyMode && $('#pf-emergency-patient-id').val()) return true;
        // Emergency mode: new/unidentified patients default to Self/Private — safe to skip
        // only if the user hasn't explicitly selected a non-private scheme.
        if (pfEmergencyMode && (!pfActiveScheme || pfActiveScheme === 'Self/Private')) return true;
        if (pfActiveScheme !== 'Self/Private' && !$('#pf-hmo').val()) {
            toastr.warning('Please select an HMO provider.');
            return false;
        }
        return true;
    }

    if (!isValid) {
        // Focus first invalid field
        $step.find('.is-invalid:first').focus();
        toastr.warning('Please fill in all required fields correctly');
    }

    return isValid;
}

function isValidPhone(phone) {
    return /^[\d\s+\-()]{7,20}$/.test(phone);
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function updatePatientFormAge() {
    const dob = $('#pf-dob').val();
    if (!dob) {
        $('#pf-age-display').text('');
        return;
    }

    const birthDate = new Date(dob);
    const today = new Date();

    let years = today.getFullYear() - birthDate.getFullYear();
    let months = today.getMonth() - birthDate.getMonth();
    let days = today.getDate() - birthDate.getDate();

    if (days < 0) {
        months--;
        days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();
    }

    if (months < 0) {
        years--;
        months += 12;
    }

    let ageText = '';
    if (years> 0) {
        ageText = `${years} year${years !== 1 ? 's' : ''}`;
        if (months> 0) {
            ageText += `, ${months} month${months !== 1 ? 's' : ''}`;
        }
    } else if (months> 0) {
        ageText = `${months} month${months !== 1 ? 's' : ''}`;
        if (days> 0) {
            ageText += `, ${days} day${days !== 1 ? 's' : ''}`;
        }
    } else {
        ageText = `${days} day${days !== 1 ? 's' : ''}`;
    }

    $('#pf-age-display').html(`<i class="mdi mdi-calendar-account"></i> Age: ${ageText}`);
}

function updateAllergiesTags() {
    const $container = $('#pf-allergies-tags');
    $container.empty();

    patientFormAllergies.forEach((allergy, index) => {
        $container.append(`
            <span class="allergy-tag-item">
                <i class="mdi mdi-alert-circle"></i>
                ${escapeHtml(allergy)}
                <span class="remove-allergy" data-index="${index}">&times;</span>
            </span>
        `);
    });

    $('#pf-allergies').val(JSON.stringify(patientFormAllergies));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function updatePatientFormSummary() {
    const fullName = [
        $('#pf-surname').val().trim(),
        $('#pf-firstname').val().trim(),
        $('#pf-othername').val().trim()
    ].filter(Boolean).join(' ');

    // Basic Information
    $('#summary-file-no').text($('#pf-file-no').val() || '-');
    $('#summary-name').text(fullName || '-');
    $('#summary-gender').text($('#pf-gender').val() || '-');
    $('#summary-phone').text($('#pf-phone').val() || 'N/A');
    $('#summary-email').text($('#pf-email').val() || 'N/A');
    $('#summary-address').text($('#pf-address').val().trim() || 'N/A');

    // Date of Birth & Age
    const dob = $('#pf-dob').val();
    if (dob) {
        const birthDate = new Date(dob);
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        $('#summary-dob').text(birthDate.toLocaleDateString('en-US', options));

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        $('#summary-age').text(`${age} years`);
    } else {
        $('#summary-dob').text('-');
        $('#summary-age').text('-');
    }

    // File uploads - check for new file or existing file
    const passportFile = $('#pf-passport')[0].files[0];
    const existingPassport = $('#pf-passport').data('existing');
    if (passportFile) {
        $('#summary-passport').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + passportFile.name + ' <em>(new)</em></span>');
    } else if (existingPassport) {
        $('#summary-passport').html('<span class="text-info"><i class="mdi mdi-file-image"></i> ' + existingPassport + ' <em>(existing)</em></span>');
    } else {
        $('#summary-passport').text('Not uploaded');
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    const existingOldRecords = $('#pf-old-records').data('existing');
    if (oldRecordsFile) {
        $('#summary-old-records').html('<span class="text-success"><i class="mdi mdi-check-circle"></i> ' + oldRecordsFile.name + ' <em>(new)</em></span>');
    } else if (existingOldRecords) {
        $('#summary-old-records').html('<span class="text-info"><i class="mdi mdi-file-document"></i> ' + existingOldRecords + ' <em>(existing)</em></span>');
    } else {
        $('#summary-old-records').text('Not uploaded');
    }

    // Medical Information
    $('#summary-blood-group').text($('#pf-blood-group').val() || '-');
    $('#summary-genotype').text($('#pf-genotype').val() || '-');
    $('#summary-disability').text($('#pf-disability option:selected').text() || '-');
    $('#summary-nationality').text($('#pf-nationality').val() || '-');
    $('#summary-ethnicity').text($('#pf-ethnicity').val() || '-');

    // Allergies
    if (patientFormAllergies && patientFormAllergies.length> 0) {
        $('#summary-allergies').html(patientFormAllergies.map(a => '<span class="badge bg-danger me-1">' + a + '</span>').join(' '));
    } else {
        $('#summary-allergies').text('None');
    }

    $('#summary-medical-history').text($('#pf-medical-history').val().trim() || 'None');

    // Next of Kin
    $('#summary-nok-name').text($('#pf-nok-name').val().trim() || '-');
    $('#summary-nok-phone').text($('#pf-nok-phone').val().trim() || '-');
    $('#summary-nok-address').text($('#pf-nok-address').val().trim() || '-');

    // HMO
    const hmoId = $('#pf-hmo').val();
    if (hmoId) {
        $('#summary-hmo').text($('#pf-hmo option:selected').text());
    } else {
        $('#summary-hmo').text('Private');
    }
    $('#summary-hmo-no').text($('#pf-hmo-no').val().trim() || '-');
}

function clearPatientFormSummary() {
    // Clear all summary fields
    $('#summary-file-no, #summary-name, #summary-gender, #summary-dob, #summary-age').text('-');
    $('#summary-phone, #summary-email, #summary-address').text('-');
    $('#summary-passport, #summary-old-records').text('Not uploaded');
    $('#summary-blood-group, #summary-genotype, #summary-disability').text('-');
    $('#summary-nationality, #summary-ethnicity').text('-');
    $('#summary-allergies').text('None');
    $('#summary-medical-history').text('-');
    $('#summary-nok-name, #summary-nok-phone, #summary-nok-address').text('-');
    $('#summary-hmo').text('Private');
    $('#summary-hmo-no').text('-');
}

function submitPatientForm() {
    // Final validation
    if (!validatePatientFormStep(1)) {
        goToPatientFormStep(1);
        return;
    }

    const mode = $('#patient-form-mode').val();
    const patientId = $('#patient-form-id').val();
    const $btn = $('#pf-btn-submit');
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    // Use FormData for file uploads
    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    formData.append('file_no', $('#pf-file-no').val());
    formData.append('surname', $('#pf-surname').val().trim());
    formData.append('firstname', $('#pf-firstname').val().trim());
    formData.append('othername', $('#pf-othername').val().trim());
    formData.append('gender', $('#pf-gender').val());
    formData.append('dob', $('#pf-dob').val());
    formData.append('phone_no', $('#pf-phone').val().trim());
    formData.append('email', $('#pf-email').val().trim());
    formData.append('address', $('#pf-address').val().trim());
    formData.append('blood_group', $('#pf-blood-group').val());
    formData.append('genotype', $('#pf-genotype').val());
    formData.append('disability', $('#pf-disability').val());
    formData.append('nationality', $('#pf-nationality').val());
    formData.append('ethnicity', $('#pf-ethnicity').val());
    formData.append('allergies', JSON.stringify(patientFormAllergies));
    formData.append('medical_history', $('#pf-medical-history').val().trim());
    formData.append('misc', $('#pf-misc').val().trim());
    formData.append('next_of_kin_name', $('#pf-nok-name').val().trim());
    formData.append('next_of_kin_phone', $('#pf-nok-phone').val().trim());
    formData.append('next_of_kin_address', $('#pf-nok-address').val().trim());
    formData.append('hmo_id', $('#pf-hmo').val() || 1);
    formData.append('hmo_no', $('#pf-hmo-no').val().trim());
    formData.append('is_family_principal', $('#pf-is-family-principal').is(':checked') ? 1 : 0);
    formData.append('principal_id', $('#pf-principal-id').val() || '');

    // Add registration service if selected
    const registrationServiceId = $('#pf-registration-service').val();
    if (registrationServiceId) {
        formData.append('registration_service_id', registrationServiceId);
    }

    // Add file uploads if present
    const passportFile = $('#pf-passport')[0].files[0];
    const passportDataUrl = $('#pf-passport-data').val();

    if (passportFile) {
        // User uploaded a file
        formData.append('filename', passportFile);
    } else if (passportDataUrl) {
        // User captured from webcam - send as base64
        formData.append('passport_data', passportDataUrl);
    }

    const oldRecordsFile = $('#pf-old-records')[0].files[0];
    if (oldRecordsFile) {
        formData.append('old_records', oldRecordsFile);
    }

    let url;
    if (mode === 'edit' && patientId) {
        url = patientFormConfig.updateUrl.replace('__ID__', patientId);
        formData.append('_method', 'PUT');
    } else {
        url = patientFormConfig.registerUrl;
    }

    $.ajax({
        url: url,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                toastr.success(mode === 'edit' ? 'Patient updated successfully' : 'Patient registered successfully');
                $('#patientFormModal').modal('hide');

                // Reload patient data if editing current patient or load new patient
                const newPatientId = response.patient?.id || patientId;
                if (newPatientId && typeof patientFormConfig.onSuccess === 'function') {
                    patientFormConfig.onSuccess(newPatientId, mode);
                }
            } else {
                toastr.error(response.message || 'Operation failed');
            }
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors) {
                Object.values(errors).forEach(err => {
                    toastr.error(err[0]);
                });
            } else {
                toastr.error(xhr.responseJSON?.message || 'Operation failed');
            }
        },
        complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
        }
    });
}

// Patient Form Event Listeners
$(document).ready(function() {
    // Stepper navigation
    $('.stepper-item').on('click', function() {
        const step = parseInt($(this).data('step'));
        goToPatientFormStep(step);
    });

    // Next button
    $('#pf-btn-next').on('click', function() {
        // In emergency or walk-in mode, navigate by sequence index
        if ($('#patientFormModal').hasClass('pf-emergency-mode') || $('#patientFormModal').hasClass('pf-walkin-mode')) {
            var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
            if (currentIdx < pfStepSequence.length - 1) {
                goToPatientFormStep(pfStepSequence[currentIdx + 1]);
            }
            return;
        }
        goToPatientFormStep(patientFormCurrentStep + 1);
    });

    // Previous button
    $('#pf-btn-prev').on('click', function() {
        if ($('#patientFormModal').hasClass('pf-emergency-mode') || $('#patientFormModal').hasClass('pf-walkin-mode')) {
            var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
            if (currentIdx > 0) {
                goToPatientFormStep(pfStepSequence[currentIdx - 1]);
            }
            return;
        }
        goToPatientFormStep(patientFormCurrentStep - 1);
    });

    // Submit button
    $('#patient-form').on('submit', function(e) {
        e.preventDefault();
        submitPatientForm();
    });

    // File number mode buttons
    $(document).on('click', '.file-no-mode-btn', function() {
        const mode = $(this).data('mode');
        toggleFileNumberEdit(mode);
    });

    // ---- Age / DOB Toggle Widget ----
    var _pfAgeDobSyncing = false; // prevent infinite loops

    // Toggle between Age and DOB modes
    $(document).on('click', '.pf-adt-btn', function() {
        var mode = $(this).data('mode');
        $('.pf-adt-btn').removeClass('active');
        $(this).addClass('active');
        $('.pf-age-dob-wrapper').removeClass('mode-age mode-dob').addClass('mode-' + mode);
    });

    // Age input changed → compute DOB → sync
    $(document).on('input change', '#pf-age-val, #pf-age-unit', function() {
        if (_pfAgeDobSyncing) return;
        _pfAgeDobSyncing = true;

        var val = parseInt($('#pf-age-val').val());
        var unit = $('#pf-age-unit').val();
        if (!isNaN(val) && val>= 0) {
            var d = new Date();
            if (unit === 'years') d.setFullYear(d.getFullYear() - val);
            else if (unit === 'months') d.setMonth(d.getMonth() - val);
            else if (unit === 'days') d.setDate(d.getDate() - val);

            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso);

            // Show hint ≈ year
            var hint = unit === 'years' ? '≈ ' + d.getFullYear() : d.toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric'});
            $('#pf-age-dob-hint').html('<span class="badge bg-light text-secondary">' + hint + '</span>');

            updatePatientFormAge();
            checkDuplicatePatient();
        } else {
            $('#pf-dob').val('');
            $('#pf-age-dob-hint').html('');
            $('#pf-age-display').text('');
        }
        _pfAgeDobSyncing = false;
    });

    // DOB change → update age display + back-sync to age input
    $(document).on('change', '#pf-dob', function() {
        updatePatientFormAge();
        checkDuplicatePatient();

        if (_pfAgeDobSyncing) return;
        _pfAgeDobSyncing = true;
        var dob = $(this).val();
        if (dob) {
            var bd = new Date(dob), now = new Date();
            var diffMs = now - bd;
            var totalDays = Math.floor(diffMs / 86400000);
            if (totalDays < 91) {
                $('#pf-age-val').val(totalDays);
                $('#pf-age-unit').val('days');
            } else if (totalDays < 730) {
                $('#pf-age-val').val(Math.floor(totalDays / 30.44));
                $('#pf-age-unit').val('months');
            } else {
                var yrs = now.getFullYear() - bd.getFullYear();
                if (now.getMonth() < bd.getMonth() || (now.getMonth() === bd.getMonth() && now.getDate() < bd.getDate())) yrs--;
                $('#pf-age-val').val(yrs);
                $('#pf-age-unit').val('years');
            }
            $('#pf-age-dob-hint').html('');
        } else {
            $('#pf-age-val').val('');
            $('#pf-age-dob-hint').html('');
        }
        _pfAgeDobSyncing = false;

        // Also clear emergency approx-age if user typed exact DOB
        if ($(this).val() && pfEmergencyMode) { $('#pf-approx-age').val(''); }
    });

    // Duplicate detection: trigger on key fields
    $('#pf-surname, #pf-firstname').on('input', function() {
        checkDuplicatePatient();
    });
    $('#pf-phone').on('input', function() {
        checkDuplicatePatient();
    });

    // Dismiss duplicate panel
    $(document).on('click', '.pf-dup-dismiss', function() {
        _dupDismissed = true;
        $('#pf-duplicate-panel').slideUp(200);
    });

    // Select existing patient from duplicate suggestions
    $(document).on('click', '.pf-dup-item', function() {
        var patientId = $(this).data('patient-id');
        if (!patientId) return;
        $('#patientFormModal').modal('hide');
        if (window.patientFormConfig && typeof window.patientFormConfig.onSelectExisting === 'function') {
            window.patientFormConfig.onSelectExisting(patientId);
        }
    });

    // HMO change - show/hide HMO number field
    $(document).on('change', '#pf-hmo', function() {
        if ($(this).val() && $(this).val() != 1) {
            $('#pf-hmo-no-container').show();
        } else {
            $('#pf-hmo-no-container').hide();
            $('#pf-hmo-no').val('');
        }
    });

    // ============================================
    // PHOTO CAPTURE FUNCTIONALITY (Upload & Webcam)
    // ============================================
    let webcamStream = null;
    let capturedPhotoBlob = null;

    // Photo capture tab switching
    $('.photo-capture-tab').on('click', function() {
        const panel = $(this).data('panel');

        // Update tabs
        $('.photo-capture-tab').removeClass('active');
        $(this).addClass('active');

        // Update panels
        $('.photo-capture-panel').removeClass('active');
        $(`#panel-${panel}`).addClass('active');

        // Stop webcam when switching away from webcam tab
        if (panel !== 'webcam' && webcamStream) {
            stopWebcam();
        }
    });

    // Upload dropzone functionality
    const dropzone = $('#photo-dropzone');
    const fileInput = $('#pf-passport');

    // Click to browse
    dropzone.on('click', function() {
        fileInput.click();
    });

    // Drag and drop
    dropzone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    dropzone.on('dragleave dragend drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    dropzone.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length> 0 && files[0].type.startsWith('image/')) {
            fileInput[0].files = files;
            fileInput.trigger('change');
        }
    });

    // File input change handler
    fileInput.on('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size> 5 * 1024 * 1024) {
                toastr.error('File size must be less than 5MB');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                showPhotoPreview(e.target.result, 'Uploaded', file.name);
                capturedPhotoBlob = null; // Clear any webcam capture
            };
            reader.readAsDataURL(file);
        }
    });

    // Webcam functions
    function startWebcam() {
        navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            }
        })
        .then(function(stream) {
            webcamStream = stream;
            const video = document.getElementById('pf-webcam-video');
            video.srcObject = stream;

            $('#webcam-placeholder').hide();
            $('#btn-start-webcam').addClass('d-none');
            $('#btn-capture-photo, #btn-stop-webcam').removeClass('d-none');
        })
        .catch(function(err) {
            console.error('Webcam error:', err);
            toastr.error('Could not access camera. Please check permissions.');
        });
    }

    function stopWebcam() {
        if (webcamStream) {
            webcamStream.getTracks().forEach(track => track.stop());
            webcamStream = null;
        }

        const video = document.getElementById('pf-webcam-video');
        video.srcObject = null;

        $('#webcam-placeholder').show();
        $('#btn-start-webcam').removeClass('d-none');
        $('#btn-capture-photo, #btn-stop-webcam').addClass('d-none');
    }

    function capturePhoto() {
        const video = document.getElementById('pf-webcam-video');
        const canvas = document.getElementById('pf-photo-canvas');
        const ctx = canvas.getContext('2d');

        // Set canvas size to video size
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw mirrored image
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0);

        // Convert to blob
        canvas.toBlob(function(blob) {
            capturedPhotoBlob = blob;
            const url = URL.createObjectURL(blob);
            showPhotoPreview(url, 'Captured', 'webcam-photo.jpg');

            // Store base64 data for form submission
            const reader = new FileReader();
            reader.onloadend = function() {
                $('#pf-passport-data').val(reader.result);
            };
            reader.readAsDataURL(blob);

            // Stop webcam after capture
            stopWebcam();
        }, 'image/jpeg', 0.85);
    }

    function showPhotoPreview(src, source, filename) {
        // Hide capture panels and show preview
        $('.photo-capture-panel').removeClass('active');
        $('#photo-preview-wrapper').addClass('show');

        $('#photo-preview-img').attr('src', src);
        $('#photo-source-badge').html(`<i class="mdi mdi-check-circle"></i> ${source}`);
        $('#photo-filename').text(filename);
    }

    function resetPhotoCapture() {
        // Reset everything
        capturedPhotoBlob = null;
        $('#pf-passport').val('');
        $('#pf-passport-data').val('');
        $('#photo-preview-wrapper').removeClass('show');

        // Show first panel (upload)
        $('.photo-capture-tab').removeClass('active').first().addClass('active');
        $('.photo-capture-panel').removeClass('active');
        $('#panel-upload').addClass('active');

        stopWebcam();
    }

    // Webcam button handlers
    $('#btn-start-webcam').on('click', startWebcam);
    $('#btn-stop-webcam').on('click', stopWebcam);
    $('#btn-capture-photo').on('click', capturePhoto);

    // Photo preview actions
    $('#btn-change-photo').on('click', resetPhotoCapture);
    $('#btn-remove-photo').on('click', resetPhotoCapture);

    // Stop webcam when modal closes and restore default state
    $('#patientFormModal').on('hidden.bs.modal', function() {
        stopWebcam();
        // Restore file number controls (may have been hidden by ANC mode)
        $('.file-no-btn-group').show();
        $('#pf-file-no-info').show();
    });

    // Old records file preview - when new file selected
    $('#pf-old-records').on('change', function() {
        const file = this.files[0];
        if (file) {
            $('#old-records-new-name').text(file.name);
            $('#pf-old-records-new-preview').show();
            // Hide existing preview if showing
            $('.old-records-preview-container').hide();
        } else {
            $('#pf-old-records-new-preview').hide();
            // Show existing preview back if there was one
            if ($('#pf-old-records').data('existing')) {
                $('.old-records-preview-container').show();
            }
        }
    });

    // Cancel new old records selection - revert to existing
    $('#pf-cancel-old-records').on('click', function() {
        $('#pf-old-records').val('');
        $('#pf-old-records-new-preview').hide();
        // Show existing preview back if there was one
        if ($('#pf-old-records').data('existing')) {
            $('.old-records-preview-container').show();
        }
    });

    // Clear existing old records (mark for removal)
    $('#pf-clear-old-records').on('click', function() {
        $('#pf-old-records').val('').removeData('existing');
        $('.old-records-preview-container').hide();
        $('#pf-old-records-new-preview').hide();
    });

    // Allergies input
    $('#pf-allergy-input').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const allergy = $(this).val().trim();
            if (allergy && !patientFormAllergies.includes(allergy)) {
                patientFormAllergies.push(allergy);
                updateAllergiesTags();
            }
            $(this).val('');
        }
    });

    // Remove allergy
    $(document).on('click', '.remove-allergy', function() {
        const index = $(this).data('index');
        patientFormAllergies.splice(index, 1);
        updateAllergiesTags();
    });

    // Live validation
    $('#patient-form .form-control[required]').on('blur', function() {
        const $field = $(this);
        if ($field.val().trim()) {
            $field.removeClass('is-invalid').addClass('is-valid');
        } else {
            $field.removeClass('is-valid');
        }
    });

    // Floating labels - detect value
    $('#patient-form .form-control').on('input change', function() {
        if ($(this).val()) {
            $(this).addClass('has-value');
        } else {
            $(this).removeClass('has-value');
        }
    });
});

// =============================================
// WALK-IN MODE LOGIC
// =============================================
function enableWalkInMode(prefix) {
    pfWalkInMode = true;
    pfStepSequence = [1, 4];
    
    var $modal = $('#patientFormModal');
    $modal.addClass('pf-walkin-mode');
    
    // Hide specific stepper items and lines for brevity
    $('.stepper-item[data-step="2"], .stepper-item[data-step="3"]').hide();
    $('.stepper-line').hide();
    
    $('#patient-form-title').html('<i class="mdi mdi-account-plus"></i> Register Walk-in Patient');
    
    // Customize file number field
    $('.file-no-btn-group').hide();
    $('#pf-file-no').prop('readonly', false);
    
    // Update total steps for navigation
    window._pfTotalSteps = pfStepSequence.length;
    
    generatePrefixedFileNumber(prefix);
}

function generatePrefixedFileNumber(prefix) {
    $.ajax({
        url: wbUrl('/reception/patient/next-file-number'),
        method: 'GET',
        data: { prefix: prefix },
        success: function(response) {
            var nextFileNo = response.file_no;
            $('#pf-file-no').val(nextFileNo).addClass('status-valid');
            $('#pf-next-file-no').text(nextFileNo);
            $('#pf-duplicate-warning').hide();
            
            var recent = response.recent_file_nos || [];
            var lastTwo = recent.slice(0, 2);
            if (lastTwo.length > 0) {
                $('#pf-file-no-hint').html('<small class="text-muted">Recent: ' + lastTwo.join(', ') + '</small>').show();
            } else {
                $('#pf-file-no-hint').hide();
            }
        },
        error: function() {
            $('#pf-file-no').val(prefix + '001');
            $('#pf-next-file-no').text(prefix + '001');
        }
    });
}

function disableWalkInMode() {
    pfWalkInMode = false;
    pfStepSequence = [1, 2, 3, 4];
    var $modal = $('#patientFormModal');
    $modal.removeClass('pf-walkin-mode');
    $('.stepper-item').show();
    $('.stepper-line').show();
    $('.file-no-btn-group').show();
    $('#pf-file-no').prop('readonly', true);
    $('#patient-form-title').html('<i class="mdi mdi-account-plus"></i> New Patient Registration');
    
    // Reset total steps
    window._pfTotalSteps = 4;
}

// =============================================
// EMERGENCY MODE LOGIC
// =============================================
(function() {
    'use strict';

    // pfEmergencyMode is declared at outer <script> scope
    let pfEmergencyTimerInterval = null;
    let pfEmergencyTimerSeconds = 0;
    let pfEmergencySearchTimeout = null;
    let pfDirectServiceSearchTimeout = null;
    let pfDirectServices = []; // [{type, id, name}]
    // Step sequence: normal = [1,2,3,4], emergency = [1,5,6,4] (skip Medical & NOK)
    // Remove redundant definition if exists, or keep as is if it's the only one.
    // Actually, I already added it at the top, so I'll just remove this one to avoid redeclaration issues if it's let.
    // let pfStepSequence = [1, 2, 3, 4]; 


    const pfApproxAgeMap = {
        'neonate': 14, 'infant': 183, 'child_1_5': 1095, 'child_6_12': 3285,
        'adolescent': 5475, 'adult_18_30': 8760, 'adult_31_50': 14600,
        'adult_51_65': 21170, 'elderly': 27375
    };

    // Expose function to open modal in emergency mode
    window.showEmergencyIntakeModal = function() {
        showPatientFormModal('create');
        enableEmergencyMode();
    };

    // Expose function to open modal in direct morgue admission mode
    window.showMorgueAdmissionModal = function(config) {
        if (config) {
            window.patientFormConfig = $.extend(true, {}, window.patientFormConfig || {}, config);
        }

        enableEmergencyMode();
        showPatientFormModal('create');

        // Customizations for Morgue Admission
        var $modal = $('#patientFormModal');
        $modal.addClass('pf-morgue-mode');
        $('#patient-form-title').html('<i class="mdi mdi-emoticon-dead mdi-24px"></i> Direct Morgue Admission (BID)');
        $('#patient-form-header').css('background', 'linear-gradient(135deg, #1a202c 0%, #2d3748 100%)');

        // Morgue skips Triage — sequence is Patient → Disposition → Summary
        pfStepSequence = [1, 6, 4];
        window._pfTotalSteps = pfStepSequence.length;
        // Hide the triage stepper item and its connector line
        $('.stepper-item[data-step="5"]').hide();
        $('.stepper-item[data-step="5"]').prev('.stepper-line').hide();
        $('.stepper-item[data-step="5"]').next('.stepper-line').hide();

        // Update submit button text
        $('#pf-submit-text').text('Complete Admission');

        // Preset BID fields (date/time are already set today by default)
        $('#pf-is-bid').prop('checked', true).trigger('change');
        $('#pf-bid-date').val(new Date().toISOString().split('T')[0]);
        $('#pf-bid-time').val(new Date().toTimeString().split(' ')[0].substring(0, 5));

        // Auto-select morgue disposition immediately (triage step is skipped)
        $('input[name="pf_disposition"][value="morgue_mortal"]').prop('checked', true).trigger('change');

        // Lock BID toggle — morgue is always BID, prevent user from unchecking
        $('#pf-is-bid').prop('disabled', true);
    };

    function enableEmergencyMode() {
        pfEmergencyMode = true;
        // Default sequence includes HMO step (step 4). It is removed dynamically
        // when an existing patient is selected (their HMO is already on file).
        pfStepSequence = [1, 5, 6, 4];

        var $modal = $('#patientFormModal');
        $modal.addClass('pf-emergency-mode');

        // Update title
        $('#patient-form-title').html('<i class="mdi mdi-ambulance mdi-24px"></i> Emergency / Walk-In Intake');
        $('#pf-submit-text').text('Submit Emergency Intake');

        // Show emergency stepper items (not form-steps — CSS .form-step handles those via .active class)
        $('.pf-emergency-stepper').show();

        // Update total steps for navigation
        window._pfTotalSteps = pfStepSequence.length;

        // Reset BID fields
        $('#pf-is-bid').prop('checked', false);
        $('#pf-bid-info').hide();

        // Apply Select2 to bare emergency-step selects (ESI, arrival mode, etc.) if select2 plugin is loaded
        if ($.fn && $.fn.select2) {
            var s2Opts = { dropdownParent: $modal, width: '100%', allowClear: false };
            ['#pf-esi-level', '#pf-arrival-mode', '#pf-gender', '#pf-approx-age-unid', '#pf-gender-unid'].forEach(function(sel) {
                var $s = $(sel);
                if ($s.length && !$s.hasClass('select2-hidden-accessible')) {
                    $s.select2($.extend({}, s2Opts, { placeholder: $s.find('option:first').text() || 'Select...' }));
                }
            });
        }

        // Generate EX- prefixed file number for emergency patients
        generateEmergencyFileNumber();

        // Start timer when modal actually shows
        $modal.off('shown.bs.modal.emergency').on('shown.bs.modal.emergency', function() {
            startEmergencyTimer();
        });
    }

    function generateEmergencyFileNumber() {
        $.ajax({
            url: wbUrl('/reception/patient/next-file-number'),
            method: 'GET',
            data: { prefix: 'EX-' },
            success: function(response) {
                var nextFileNo = response.file_no;
                $('#pf-file-no').val(nextFileNo).addClass('status-valid');
                $('#pf-next-file-no').text(nextFileNo);
                $('#pf-duplicate-warning').hide();
                // Show recent EX- numbers as hint
                var recent = response.recent_file_nos || [];
                var lastTwo = recent.slice(0, 2);
                if (lastTwo.length> 0) {
                    $('#pf-file-no-hint').html('<small class="text-muted">Recent: ' + lastTwo.join(', ') + '</small>').show();
                }
            },
            error: function() {
                $('#pf-file-no').val('EX-001');
                $('#pf-next-file-no').text('EX-001');
            }
        });
    }

    // BID toggle listener
    $(document).on('change', '#pf-is-bid', function() {
        var $modal = $('#patientFormModal');
        if ($(this).is(':checked')) {
            $('#pf-bid-info').slideDown();
            // In emergency mode (non-morgue), expose morgue disposition and auto-select it
            if (pfEmergencyMode && !$modal.hasClass('pf-morgue-mode')) {
                $modal.addClass('pf-bid-active');
                $('input[name="pf_disposition"][value="morgue_mortal"]').prop('checked', true).trigger('change');
            }
        } else {
            $('#pf-bid-info').slideUp();
            $modal.removeClass('pf-bid-active');
            // Clear morgue disposition if it was auto-selected
            if ($('input[name="pf_disposition"]:checked').val() === 'morgue_mortal') {
                $('input[name="pf_disposition"]').prop('checked', false);
                $('#pf-morgue-options').hide();
            }
        }
    });

    function disableEmergencyMode() {
        pfEmergencyMode = false;
        pfStepSequence = [1, 2, 3, 4];
        var $modal = $('#patientFormModal');
        $modal.removeClass('pf-emergency-mode pf-bid-active');
        $('.pf-emergency-stepper').hide();
        // Reset summaries to default state
        $('#registration-summary').show();
        $('#emergency-intake-summary').hide();

        // Reset total steps
        window._pfTotalSteps = 4;

        stopEmergencyTimer();
        resetEmergencyFields();
    }



    // ---- Timer ----
    function startEmergencyTimer() {
        pfEmergencyTimerSeconds = 0;
        clearInterval(pfEmergencyTimerInterval);
        $('#pf-emergency-timer').text('00:00');
        pfEmergencyTimerInterval = setInterval(function() {
            pfEmergencyTimerSeconds++;
            var m = String(Math.floor(pfEmergencyTimerSeconds / 60)).padStart(2, '0');
            var s = String(pfEmergencyTimerSeconds % 60).padStart(2, '0');
            $('#pf-emergency-timer').text(m + ':' + s);
        }, 1000);
    }

    function stopEmergencyTimer() {
        clearInterval(pfEmergencyTimerInterval);
        pfEmergencyTimerInterval = null;
    }

    // ---- Patient Search (emergency) ----
    $(document).on('input', '#pf-emergency-patient-search', function() {
        clearTimeout(pfEmergencySearchTimeout);
        var query = $(this).val().trim();
        if (query.length < 2) { $('#pf-emergency-patient-results').hide(); return; }

        pfEmergencySearchTimeout = setTimeout(function() {
            $.get('/emergency/search-patient', { q: query }, function(patients) {
                var $results = $('#pf-emergency-patient-results').empty();
                if (patients.length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No patients found</div>');
                } else {
                    patients.forEach(function(p) {
                        var schemeBadgeClass = (p.hmo_scheme === 'Self/Private') ? 'bg-secondary' : 'bg-success';
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action pf-emergency-patient-item py-1"' +
                            ' data-id="' + p.id + '" data-name="' + escapeHtml(p.name) + '" data-fileno="' + escapeHtml(p.file_no) + '"' +
                            ' data-phone="' + escapeHtml(p.phone || '') + '"' +
                            ' data-hmo="' + escapeHtml(p.hmo || 'Private') + '"' +
                            ' data-hmo-id="' + (p.hmo_id || '') + '"' +
                            ' data-hmo-scheme="' + escapeHtml(p.hmo_scheme || 'Self/Private') + '"' +
                            ' data-hmo-no="' + escapeHtml(p.hmo_no || '') + '"' +
                            ' data-allergies="' + escapeHtml(p.allergies || '') + '">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                            '<div><strong>' + escapeHtml(p.name) + '</strong>' +
                            '<small class="d-block text-muted">' + escapeHtml(p.file_no) + ' | ' + (p.gender || '') + ' | ' + escapeHtml(p.phone || '') + '</small></div>' +
                            '<span class="badge ' + schemeBadgeClass + '">' + escapeHtml(p.hmo || 'Private') + '</span>' +
                            '</div></a>'
                        );
                    });
                }
                $results.show();
            });
        }, 300);
    });

    // Select patient from emergency search
    $(document).on('click', '.pf-emergency-patient-item', function(e) {
        e.preventDefault();
        var $el = $(this);
        var name       = $el.data('name');
        var hmoId      = $el.data('hmo-id');
        var hmoScheme  = $el.data('hmo-scheme') || 'Self/Private';
        var hmoName    = $el.data('hmo')   || 'Private';
        var hmoNo      = $el.data('hmo-no') || '';

        $('#pf-emergency-patient-id').val($el.data('id'));
        $('#pf-emergency-patient-name').text(name);
        $('#pf-emergency-patient-fileno').text($el.data('fileno'));
        $('#pf-emergency-patient-phone').text($el.data('phone'));
        $('#pf-emergency-patient-hmo').text(hmoName);
        // Avatar initials
        var initials = name ? name.split(' ').map(function(w){ return w[0]; }).join('').substring(0,2).toUpperCase() : '?';
        $('#pf-emergency-patient-avatar').text(initials);
        $('#pf-emergency-selected-patient').addClass('show');
        $('#pf-emergency-patient-results').hide();
        $('#pf-emergency-patient-search').val('');
        $('#pf-existing-empty-state').hide();

        // Pre-fill HMO step with patient's existing insurance
        if (hmoId) {
            $('#pf-hmo').val(hmoId);
            if ($('#pf-hmo').hasClass('select2-hidden-accessible')) {
                $('#pf-hmo').trigger('change');
            }
            $('#pf-hmo-no').val(hmoNo);
            if (hmoNo) $('#pf-hmo-no-container').show();
        }
        // Activate correct scheme card so pfActiveScheme is in sync
        pfActiveScheme = hmoScheme;
        $('.pf-scheme-card').removeClass('active');
        $('.pf-scheme-card[data-scheme="' + hmoScheme + '"]').addClass('active');
        // Show/hide HMO provider select based on scheme
        if (hmoScheme === 'Self/Private') {
            $('#pf-hmo-select-col').hide();
            $('#pf-hmo-no-container').hide();
        } else {
            $('#pf-hmo-select-col').show();
        }

        // Pre-fill allergies if existing
        var allergies = $el.data('allergies');
        if (allergies && allergies !== 'null' && String(allergies).length > 2) {
            $('#pf-allergy-has').prop('checked', true).trigger('change');
            var clean = String(allergies);
            try { var arr = JSON.parse(clean); if (Array.isArray(arr)) clean = arr.join(', '); } catch(e) {}
            $('#pf-allergies-text').val(clean);
            $('#pf-allergy-text-input').show();
        }

        // Hide new-patient form fields when existing selected
        $('#pf-new-patient-wrapper').addClass('collapsed');

        // In emergency mode with an existing patient:
        // Remove step 4 (HMO) from the sequence — their insurance is already on file.
        // Sequence becomes [1, 5, 6] so the modal goes Patient → Triage → Disposition.
        if (pfEmergencyMode) {
            pfStepSequence = [1, 5, 6];
            window._pfTotalSteps = pfStepSequence.length;
            setTimeout(function() { goToPatientFormStep(5); }, 400);
        }
    });

    // Clear selected patient
    $(document).on('click', '#pf-emergency-clear-patient', function() {
        $('#pf-emergency-patient-id').val('');
        $('#pf-emergency-selected-patient').removeClass('show');
        $('#pf-existing-empty-state').show();
        // Restore HMO step in sequence now that no existing patient is locked
        if (pfEmergencyMode) {
            pfStepSequence = [1, 5, 6, 4];
            window._pfTotalSteps = pfStepSequence.length;
        }
    });

    // ---- Patient Chooser Tab Switching ----
    $(document).on('click', '.pf-chooser-tab', function() {
        var panel = $(this).data('panel');
        $('#pf-patient-chooser-mode').val(panel);

        // Switch active tab
        $('.pf-chooser-tab').removeClass('active');
        $(this).addClass('active');

        // Switch panel
        $('.pf-chooser-panel').removeClass('active');
        $('.pf-chooser-panel[data-panel="' + panel + '"]').addClass('active');

        // Update body border color
        $('#pf-chooser-body').removeClass('border-existing border-new border-unidentified')
            .addClass('border-' + panel);

        // Reset unidentified class on wrapper and emergency fields
        $('#pf-new-patient-wrapper, .pf-emergency-fields').removeClass('pf-unidentified-active');

        if (panel === 'existing') {
            // Collapse new-patient wrapper (existing patient already selected or searching)
            $('#pf-new-patient-wrapper').addClass('collapsed');
            $('#pf-is-unidentified').val('0');
            // Restore name fields if they were set for unidentified
            if ($('#pf-surname').val() === 'Unknown') $('#pf-surname').val('');
            if ($('#pf-firstname').val() === 'Patient') $('#pf-firstname').val('');
        } else if (panel === 'new') {
            // Clear any selected existing patient
            $('#pf-emergency-patient-id').val('');
            $('#pf-emergency-selected-patient').removeClass('show');
            $('#pf-existing-empty-state').show();
            // Show full new patient form fields
            $('#pf-new-patient-wrapper').removeClass('collapsed');
            $('#pf-is-unidentified').val('0');
            // Restore name fields if they were set for unidentified
            if ($('#pf-surname').val() === 'Unknown') $('#pf-surname').val('');
            if ($('#pf-firstname').val() === 'Patient') $('#pf-firstname').val('');
        } else if (panel === 'unidentified') {
            // Clear any selected existing patient
            $('#pf-emergency-patient-id').val('');
            $('#pf-emergency-selected-patient').removeClass('show');
            $('#pf-existing-empty-state').show();
            // Show wrapper but in unidentified mode — only Gender, Approx Age, Phone visible
            $('#pf-new-patient-wrapper, .pf-emergency-fields').removeClass('collapsed').addClass('pf-unidentified-active');
            $('#pf-is-unidentified').val('1');
            $('#pf-surname').val('Unknown');
            $('#pf-firstname').val('Patient');
            // Sync unidentified gender field from main gender if set
            $('#pf-gender-unid').val($('#pf-gender').val());
        }
    });

    // Sync unidentified-only fields back to main fields
    $(document).on('change', '#pf-gender-unid', function() {
        $('#pf-gender').val($(this).val());
    });
    $(document).on('change', '#pf-approx-age-unid', function() {
        var key = $(this).val();
        $('#pf-approx-age').val(key); // sync to main approx age
        if (key && pfApproxAgeMap[key]) {
            var d = new Date();
            d.setDate(d.getDate() - pfApproxAgeMap[key]);
            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso).trigger('change');
        }
    });
    $(document).on('input', '#pf-phone-unid', function() {
        $('#pf-phone').val($(this).val());
    });

    // ---- Approx Age → DOB ----
    $(document).on('change', '#pf-approx-age', function() {
        var key = $(this).val();
        if (key && pfApproxAgeMap[key]) {
            var d = new Date();
            d.setDate(d.getDate() - pfApproxAgeMap[key]);
            var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            $('#pf-dob').val(iso).trigger('change');
        }
    });

    // ---- ESI Level Selection ----
    $(document).on('click', '.pf-esi-btn', function() {
        $('.pf-esi-btn').removeClass('selected');
        $(this).addClass('selected');
        $('#pf-esi-level').val($(this).data('esi'));

        var hint = $(this).data('hint');
        if (hint) {
            $('#pf-esi-hint-text').text(hint);
            $('#pf-esi-hint-box').slideDown(150);
        }

        // Auto-expand GCS + vitals for ESI 1-2
        var esi = parseInt($(this).data('esi'));
        if (esi <= 2) {
            $('#pf-vitals-panel').collapse('show');
            $('#pf-gcs-panel').collapse('show');
        }
    });

    // ---- GCS Auto-Calculation ----
    $(document).on('change', '.pf-gcs-input', function() {
        var eye = parseInt($('#pf-gcs-eye').val()) || 0;
        var verbal = parseInt($('#pf-gcs-verbal').val()) || 0;
        var motor = parseInt($('#pf-gcs-motor').val()) || 0;

        if (eye && verbal && motor) {
            var total = eye + verbal + motor;
            $('#pf-gcs-total').val(total);
            $('#pf-gcs-total-val').val(total);
            var $el = $('#pf-gcs-total');
            $el.removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
            if (total <= 8) $el.addClass('pf-gcs-severe');
            else if (total <= 12) $el.addClass('pf-gcs-moderate');
            else $el.addClass('pf-gcs-mild');
        } else {
            $('#pf-gcs-total').val('--').removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
            $('#pf-gcs-total-val').val('');
        }
    });

    // Pain scale display
    $(document).on('input', '#pf-pain-scale', function() {
        $('#pf-pain-display').text($(this).val());
    });

    // Allergy radio toggle
    $(document).on('change', 'input[name="pf_allergy_status"]', function() {
        if ($(this).val() === 'has_allergies') {
            $('#pf-allergy-text-input').slideDown(150);
        } else {
            $('#pf-allergy-text-input').slideUp(150);
        }
    });

    // ---- Disposition Toggle ----
    $(document).on('change', '.pf-disposition-radio', function() {
        var val = $(this).val();
        $('#pf-admit-options, #pf-consult-options, #pf-direct-options, #pf-morgue-options').hide();
        if (val === 'admit_emergency') { $('#pf-admit-options').slideDown(200); loadDispositionData(); }
        else if (val === 'queue_consultation') { $('#pf-consult-options').slideDown(200); loadDispositionData(); }
        else if (val === 'direct_service') { $('#pf-direct-options').slideDown(200); }
        else if (val === 'morgue_mortal') { $('#pf-morgue-options').slideDown(200); loadMorgueServices(); }
    });

    var pfDispositionLoaded = false;
    var pfAllBeds = []; // cache all beds for ward filter
        function loadDispositionData() {
        if (pfDispositionLoaded) return;
        pfDispositionLoaded = true;

        $.get('/emergency/available-beds', function(beds) {
            pfAllBeds = beds;

            // Build ward list (unique)
            var wards = {};
            beds.forEach(function(b) {
                if (b.ward_id && !wards[b.ward_id]) {
                    wards[b.ward_id] = b.ward;
                }
            });

            var $wardSel = $('#pf-ward-select');
            if ($wardSel.hasClass('select2-hidden-accessible')) $wardSel.select2('destroy');
            $wardSel.empty().append('<option value="">-- Select Ward --</option>');
            Object.keys(wards).forEach(function(wid) {
                $wardSel.append('<option value="' + wid + '">' + escapeHtml(wards[wid]) + '</option>');
            });
            $wardSel.select2({
                dropdownParent: $('#patientFormModal'),
                placeholder: '-- Select Ward --',
                allowClear: true,
                width: '100%'
            });

            // Init bed select2 (empty until ward chosen)
            var $bedSel = $('#pf-bed-select');
            if ($bedSel.hasClass('select2-hidden-accessible')) $bedSel.select2('destroy');
            $bedSel.empty().append('<option value="">-- No bed (assign later) --</option>');
            $bedSel.select2({
                dropdownParent: $('#patientFormModal'),
                placeholder: '-- No bed (assign later) --',
                allowClear: true,
                width: '100%'
            });
        });

        $.get('/emergency/clinics', function(clinics) {
            var opts = '<option value="">-- Select Clinic --</option>';
            clinics.forEach(function(c) { opts += '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>'; });
            $('#pf-clinic-select').html(opts);
            $('#pf-admit-clinic-select').html(opts);
        });

        $.get('/emergency/services', function(data) {
            var $admitSvc = $('#pf-admit-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.admission) data.admission.forEach(function(s) {
                $admitSvc.append('<option value="' + s.id + '">' + escapeHtml(s.name) + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
            });
            if ($admitSvc.hasClass('select2-hidden-accessible')) $admitSvc.select2('destroy');
            $admitSvc.select2({
                dropdownParent: $('#patientFormModal'),
                placeholder: '-- Select Service --',
                allowClear: true,
                width: '100%'
            });

            var $consultSvc = $('#pf-service-select').empty().append('<option value="">-- Select Service --</option>');
            if (data.consultation) data.consultation.forEach(function(s) {
                $consultSvc.append('<option value="' + s.id + '">' + escapeHtml(s.name) + ' — ₦' + Number(s.price).toLocaleString() + '</option>');
            });
            if ($consultSvc.hasClass('select2-hidden-accessible')) $consultSvc.select2('destroy');
            $consultSvc.select2({
                dropdownParent: $('#patientFormModal'),
                placeholder: '-- Select Service --',
                allowClear: true,
                width: '100%'
            });
        });
    }

    // Ward → Bed filtering
    $(document).on('change', '#pf-ward-select', function() {
        var wardId = $(this).val();
        var $bedSel = $('#pf-bed-select');
        if ($bedSel.hasClass('select2-hidden-accessible')) $bedSel.select2('destroy');
        $bedSel.empty().append('<option value="">-- No bed (assign later) --</option>');
        if (wardId) {
            pfAllBeds.filter(function(b) { return String(b.ward_id) === String(wardId); })
                     .forEach(function(b) {
                         $bedSel.append('<option value="' + b.id + '">' + escapeHtml(b.name) + ' (' + escapeHtml(b.bed_type) + ')</option>');
                     });
        }
        $bedSel.select2({
            dropdownParent: $('#patientFormModal'),
            placeholder: '-- No bed (assign later) --',
            allowClear: true,
            width: '100%'
        });
    });

    var pfMorgueServicesLoaded = false;
    function loadMorgueServices() {
        if (pfMorgueServicesLoaded) return;

        var patientId = $('#pf-emergency-patient-id').val() || $('#pf-patient-id').val();
        var url = '/morgue/services';

        var params = patientId ? { patient_id: patientId } : {};

        $('#pf-morgue-service-select').html('<option value="">-- Loading services... --</option>');

        $.get(url, params, function(services) {
            var $sel = $('#pf-morgue-service-select').empty().append('<option value="">-- Select Daily Rate --</option>');
            services.forEach(function(s) {
                var price = s.payable_amount || (s.price ? s.price.sale_price : 0);
                var priceStr = ' — ₦' + Number(price).toLocaleString();
                $sel.append('<option value="' + s.id + '">' + escapeHtml(s.service_name) + priceStr + '</option>');
            });
            pfMorgueServicesLoaded = true;
        }).fail(function() {
            $('#pf-morgue-service-select').html('<option value="">-- Error loading services --</option>');
        });
    }

    // ---- Direct Service Search ----
    $(document).on('input', '#pf-direct-service-search', function() {
        clearTimeout(pfDirectServiceSearchTimeout);
        var query = $(this).val().trim();
        if (query.length < 2) { $('#pf-direct-service-results').hide(); return; }

        pfDirectServiceSearchTimeout = setTimeout(function() {
            var labUrl = '/reception/services/lab';
            var imgUrl = '/reception/services/imaging';
            Promise.all([$.get(labUrl, {q: query}), $.get(imgUrl, {q: query})]).then(function(results) {
                var $results = $('#pf-direct-service-results').empty();
                results[0].forEach(function(s) {
                    if (!pfDirectServices.find(function(x){ return x.type==='lab' && x.id===s.id; })) {
                        $results.append('<a href="#" class="list-group-item list-group-item-action pf-add-direct-service py-1" data-type="lab" data-id="'+s.id+'" data-name="'+escapeHtml(s.name)+'"><span class="badge bg-primary me-1">LAB</span> '+escapeHtml(s.name)+'</a>');
                    }
                });
                results[1].forEach(function(s) {
                    if (!pfDirectServices.find(function(x){ return x.type==='imaging' && x.id===s.id; })) {
                        $results.append('<a href="#" class="list-group-item list-group-item-action pf-add-direct-service py-1" data-type="imaging" data-id="'+s.id+'" data-name="'+escapeHtml(s.name)+'"><span class="badge bg-info me-1">IMG</span> '+escapeHtml(s.name)+'</a>');
                    }
                });
                if ($results.children().length === 0) {
                    $results.html('<div class="list-group-item text-muted text-center">No services found</div>');
                }
                $results.show();
            });
        }, 300);
    });

    $(document).on('click', '.pf-add-direct-service', function(e) {
        e.preventDefault();
        pfDirectServices.push({ type: $(this).data('type'), id: $(this).data('id'), name: $(this).data('name') });
        renderDirectServices();
        $('#pf-direct-service-results').hide();
        $('#pf-direct-service-search').val('');
    });

    $(document).on('click', '.pf-remove-service', function() {
        pfDirectServices.splice($(this).data('index'), 1);
        renderDirectServices();
    });

    function renderDirectServices() {
        var $c = $('#pf-selected-direct-services').empty();
        if (pfDirectServices.length === 0) { $c.html('<small class="text-muted">No services selected</small>'); return; }
        pfDirectServices.forEach(function(s, i) {
            var badge = s.type === 'lab' ? 'bg-primary' : 'bg-info';
            $c.append('<span class="pf-service-chip"><span class="badge '+badge+' me-1">'+s.type.toUpperCase()+'</span>'+escapeHtml(s.name)+' <span class="pf-remove-service" data-index="'+i+'"><i class="mdi mdi-close-circle"></i></span></span>');
        });
    }

    // ---- Override step navigation for emergency mode ----
    var _origGoToStep = window.goToPatientFormStep || goToPatientFormStep;

    // Patch the global goToPatientFormStep if emergency mode is active
    var origGoTo = goToPatientFormStep;
    goToPatientFormStep = function(step) {
        if (!pfEmergencyMode) { return origGoTo(step); }

        // Map logical index to step number
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        var targetIdx = -1;

        // If step is being called as a step NUMBER (from stepper clicks), find its index
        if (pfStepSequence.indexOf(step) !== -1) {
            targetIdx = pfStepSequence.indexOf(step);
        } else {
            return; // Invalid step
        }

        // Validate when moving forward
        if (targetIdx> currentIdx) {
            for (var i = currentIdx; i < targetIdx; i++) {
                if (!validateEmergencyStep(pfStepSequence[i])) return;
            }
        }

        patientFormCurrentStep = step;

        // Show the step
        $('.form-step').removeClass('active');
        $('.form-step[data-step="' + step + '"]').addClass('active');

        // If navigating to the disposition step (6), re-trigger the selected disposition's
        // change event now that the parent step is visible. This fixes slideDown() measuring
        // height=0 when it was called during modal init while the parent was display:none.
        if (step === 6) {
            var $checkedDisp = $('input[name="pf_disposition"]:checked');
            if ($checkedDisp.length) {
                $('#pf-admit-options, #pf-consult-options, #pf-direct-options, #pf-morgue-options').hide();
                $checkedDisp.trigger('change');
            }
        }

        // Update stepper
        updateEmergencyStepper();

        // Update navigation
        updateEmergencyNavigation();

        // Scroll to top
        $('.form-steps-container').scrollTop(0);
    };

    function updateEmergencyStepper() {
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        // Update all stepper items that are in our sequence
        $('.stepper-item').each(function() {
            var stepNum = parseInt($(this).data('step'));
            var stepIdx = pfStepSequence.indexOf(stepNum);
            $(this).removeClass('active completed');
            if (stepIdx === -1) return; // Not in current sequence
            if (stepIdx < currentIdx) $(this).addClass('completed');
            else if (stepIdx === currentIdx) $(this).addClass('active');
        });

        // Update stepper lines
        var lineIdx = 0;
        $('.stepper-line:visible').each(function() {
            $(this).removeClass('completed');
            if (lineIdx < currentIdx) $(this).addClass('completed');
            lineIdx++;
        });
    }

    function updateEmergencyNavigation() {
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        var isFirst = (currentIdx === 0);
        var isLast = (currentIdx === pfStepSequence.length - 1);

        if (isFirst) $('#pf-btn-prev').hide(); else $('#pf-btn-prev').show();

        if (isLast) {
            $('#pf-btn-next').hide();
            $('#pf-btn-submit').show();

            var submitText = 'Submit Emergency Intake';
            if ($('#patientFormModal').hasClass('pf-morgue-mode')) {
                submitText = 'Complete Admission';
            }
            $('#pf-submit-text').text(submitText);
            // Show emergency summary instead of registration summary
            $('#registration-summary').hide();
            $('#emergency-intake-summary').show();
            updateEmergencyIntakeSummary();
        } else {
            $('#pf-btn-next').show();
            $('#pf-btn-submit').hide();
        }
    }

    function validateEmergencyStep(step) {
        if (step === 1) {
            // In emergency mode, either existing patient or new patient info
            var hasExisting = !!$('#pf-emergency-patient-id').val();
            if (hasExisting) return true;

            var isUnidentified = $('#pf-is-unidentified').val() === '1';
            if (!isUnidentified) {
                if (!$('#pf-surname').val().trim() || !$('#pf-firstname').val().trim()) {
                    toastr.warning('Surname and First Name are required.');
                    return false;
                }
            }
            if (!$('#pf-gender').val()) {
                toastr.warning('Gender is required.');
                return false;
            }
            // DOB optional in emergency (may use approx age)
            return true;
        }
        if (step === 5) {
            // Triage: ESI + chief complaint required
            if (!$('#pf-esi-level').val()) {
                toastr.warning('Please select an ESI triage level.');
                return false;
            }
            if (!$('#pf-chief-complaint').val().trim()) {
                toastr.warning('Chief complaint is required.');
                return false;
            }
            return true;
        }
        if (step === 6) {
            // Disposition validation
            var disp = $('input[name="pf_disposition"]:checked').val();
            var isBid = $('#pf-is-bid').is(':checked');

            if (!disp && !isBid) { toastr.warning('Please select a disposition.'); return false; }

            // If BID and no disposition selected, default to morgue_mortal if we are in morgue mode
            if (!disp && isBid) {
                if ($('#patientFormModal').hasClass('pf-morgue-mode')) {
                    $('input[name="pf_disposition"][value="morgue_mortal"]').prop('checked', true).trigger('change');
                    disp = 'morgue_mortal';
                } else {
                    toastr.warning('Please select a disposition (e.g. Direct to Morgue).');
                    return false;
                }
            }
            if (disp === 'admit_emergency') {
                if (!$('#pf-admit-service-select').val()) { toastr.warning('Admission service is required.'); return false; }
                if (!$('#pf-admit-clinic-select').val()) { toastr.warning('Clinic is required.'); return false; }
            }
            if (disp === 'queue_consultation') {
                if (!$('#pf-clinic-select').val()) { toastr.warning('Clinic is required.'); return false; }
                if (!$('#pf-service-select').val()) { toastr.warning('Service is required.'); return false; }
            }
            if (disp === 'direct_service' && pfDirectServices.length === 0) {
                toastr.warning('Add at least one lab or imaging service.');
                return false;
            }
            if (disp === 'morgue_mortal') {
                if (!$('#pf-morgue-service-select').val()) {
                    toastr.warning('Daily service rate is required.');
                    return false;
                }
            }
            return true;
        }
        // For other steps, delegate to original validation
        return validatePatientFormStep(step);
    }

    // Override Next/Prev for emergency mode
    $(document).off('click.pfEmergencyNav').on('click.pfEmergencyNav', '#pf-btn-next', function() {
        if (!pfEmergencyMode) return; // Let original handler work
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        if (currentIdx < pfStepSequence.length - 1) {
            goToPatientFormStep(pfStepSequence[currentIdx + 1]);
        }
    });

    $(document).off('click.pfEmergencyPrev').on('click.pfEmergencyPrev', '#pf-btn-prev', function() {
        if (!pfEmergencyMode) return;
        var currentIdx = pfStepSequence.indexOf(patientFormCurrentStep);
        if (currentIdx> 0) {
            goToPatientFormStep(pfStepSequence[currentIdx - 1]);
        }
    });

    // ---- Override form submission for emergency mode ----
    var origSubmit = submitPatientForm;
    submitPatientForm = function() {
        if (!pfEmergencyMode) { return origSubmit(); }

        // Emergency two-phase submit:
        // Phase 1: Register patient (if new) via existing endpoint
        // Phase 2: Submit emergency intake (triage + disposition) to /emergency/intake
        var existingPatientId = $('#pf-emergency-patient-id').val();
        var $btn = $('#pf-btn-submit');
        var originalHtml = $btn.html();

        // Validate all steps in current sequence before submitting
        for (var i = 0; i < pfStepSequence.length; i++) {
            if (!validateEmergencyStep(pfStepSequence[i])) {
                return;
            }
        }

        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');

        if (existingPatientId) {
            // Skip patient creation — go straight to emergency intake
            submitEmergencyIntake(existingPatientId, $btn, originalHtml);
        } else {
            // Phase 1: Create the patient first
            var isUnidentified = $('#pf-is-unidentified').val() == '1';

            // For unidentified patients without DOB, default to adult (~30 years)
            if (isUnidentified && !$('#pf-dob').val()) {
                var d = new Date();
                d.setFullYear(d.getFullYear() - 30);
                var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                $('#pf-dob').val(iso);
            }

            // For unidentified, pack distinguishing features + unidentified flag into misc
            var miscVal = $('#pf-misc').val().trim();
            if (isUnidentified) {
                var miscObj = {};
                if (miscVal) {
                    try { miscObj = JSON.parse(miscVal); } catch(e) { miscObj = { notes: miscVal }; }
                }
                miscObj.unidentified = true;
                miscObj.distinguishing_features = $('#pf-distinguishing-features').val() || '';
                miscObj.arrival_mode = $('#pf-arrival-mode').val() || '';
                miscObj.approx_age = $('#pf-approx-age').val() || '';
                miscVal = JSON.stringify(miscObj);
            }

            var formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content') || $('meta[name="csrf-token"]').attr('content'));
            formData.append('file_no', $('#pf-file-no').val());
            formData.append('surname', $('#pf-surname').val().trim());
            formData.append('firstname', $('#pf-firstname').val().trim());
            formData.append('othername', $('#pf-othername').val().trim());
            formData.append('gender', $('#pf-gender').val());
            formData.append('dob', $('#pf-dob').val());
            formData.append('phone_no', $('#pf-phone').val().trim());
            formData.append('email', $('#pf-email').val().trim());
            formData.append('address', $('#pf-address').val().trim());
            formData.append('blood_group', $('#pf-blood-group').val());
            formData.append('genotype', $('#pf-genotype').val());
            formData.append('disability', $('#pf-disability').val());
            formData.append('nationality', $('#pf-nationality').val());
            formData.append('ethnicity', $('#pf-ethnicity').val());
            formData.append('allergies', JSON.stringify(patientFormAllergies));
            formData.append('medical_history', $('#pf-medical-history').val().trim());
            formData.append('misc', miscVal);
            formData.append('next_of_kin_name', $('#pf-nok-name').val().trim());
            formData.append('next_of_kin_phone', $('#pf-nok-phone').val().trim());
            formData.append('next_of_kin_address', $('#pf-nok-address').val().trim());
            formData.append('hmo_id', $('#pf-hmo').val() || 1);
            formData.append('hmo_no', $('#pf-hmo-no').val().trim());
            formData.append('is_family_principal', $('#pf-is-family-principal').is(':checked') ? 1 : 0);
            formData.append('principal_id', $('#pf-principal-id').val() || '');

            // File uploads
            var passportFile = $('#pf-passport')[0].files[0];
            var passportData = $('#pf-passport-data').val();
            if (passportFile) formData.append('filename', passportFile);
            else if (passportData) formData.append('passport_data', passportData);
            var oldRecords = $('#pf-old-records')[0].files[0];
            if (oldRecords) formData.append('old_records', oldRecords);

            $.ajax({
                url: patientFormConfig.registerUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success && response.patient) {
                        submitEmergencyIntake(response.patient.id, $btn, originalHtml);
                    } else {
                        toastr.error(response.message || 'Patient registration failed.');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        Object.values(errors).forEach(function(err) { toastr.error(err[0]); });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Patient registration failed.');
                    }
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        }
    };

    function submitEmergencyIntake(patientId, $btn, originalHtml) {
        var disposition = $('input[name="pf_disposition"]:checked').val();

        var intakeData = {
            _token: $('meta[name="csrf-token"]').attr('content') || $('meta[name="csrf-token"]').attr('content'),
            patient_id: patientId,
            is_new_patient: 0,
            is_unidentified: $('#pf-is-unidentified').val() == '1' ? 1 : 0,
            is_bid: $('#pf-is-bid').is(':checked') ? 1 : 0,
            bid_record: $('#pf-is-bid').is(':checked') ? {
                date: $('#pf-bid-date').val(),
                time: $('#pf-bid-time').val(),
                cause: $('#pf-bid-cause').val()
            } : null,
            // Triage
            esi_level: $('#pf-esi-level').val(),
            chief_complaint: $('#pf-chief-complaint').val(),
            triage_notes: $('#pf-triage-notes').val(),
            // Vitals
            vital_hr: $('#pf-vital-hr').val() || null,
            vital_bp_sys: $('#pf-vital-bp-sys').val() || null,
            vital_bp_dia: $('#pf-vital-bp-dia').val() || null,
            vital_spo2: $('#pf-vital-spo2').val() || null,
            vital_temp: $('#pf-vital-temp').val() || null,
            vital_rr: $('#pf-vital-rr').val() || null,
            vital_bs: $('#pf-vital-bs').val() || null,
            // GCS + Pain
            gcs_eye: $('#pf-gcs-eye').val() || null,
            gcs_verbal: $('#pf-gcs-verbal').val() || null,
            gcs_motor: $('#pf-gcs-motor').val() || null,
            gcs_total: $('#pf-gcs-total-val').val() || null,
            pain_scale: $('#pf-pain-scale').val(),
            // Allergies
            allergy_status: $('input[name="pf_allergy_status"]:checked').val(),
            allergies_text: $('#pf-allergies-text').val(),
            // Arrival
            arrival_mode: $('#pf-arrival-mode').val(),
            brought_by_name: $('#pf-brought-by-name').val(),
            brought_by_phone: $('#pf-brought-by-phone').val(),
            distinguishing_features: $('#pf-distinguishing-features').val(),
            // Disposition
            disposition: disposition,
            clinic_id: $('#pf-clinic-select').val() || null,
            service_id: $('#pf-service-select').val() || null,
            admit_service_id: $('#pf-admit-service-select').val() || null,
            admit_clinic_id: $('#pf-admit-clinic-select').val() || null,
            bed_id: $('#pf-bed-select').val() || null,
            // Morgue fields
            daily_service_id: $('#pf-morgue-service-select').val() || null,
            fridge_no: $('#pf-morgue-fridge').val() || null,
            tray_no: $('#pf-morgue-tray').val() || null,
            notes: $('#pf-morgue-notes').val() || null,
            elapsed_seconds: pfEmergencyTimerSeconds
        };

        if (disposition === 'direct_service') {
            intakeData.direct_services = pfDirectServices.map(function(s) { return {type: s.type, id: s.id}; });
        }

        var submitUrl = patientFormConfig.submitUrl || patientFormConfig.emergencyIntakeUrl;

        if (!submitUrl) {
            console.error('Submission URL not configured.');
            toastr.error('System error: Submission route not configured.');
            $btn.prop('disabled', false).html(originalHtml);
            return;
        }

        $.ajax({
            url: submitUrl,
            method: 'POST',
            data: JSON.stringify(intakeData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message || 'Emergency intake completed.');
                    $('#patientFormModal').modal('hide');

                    if (typeof patientFormConfig.onSuccess === 'function') {
                        patientFormConfig.onSuccess(patientId, 'emergency');
                    }
                    if (typeof loadPatient === 'function') loadPatient(patientId);
                    else if (typeof selectPatient === 'function') selectPatient(patientId);
                    if (typeof loadQueueCounts === 'function') loadQueueCounts();
                } else {
                    toastr.error(response.message || 'Emergency intake failed.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Server error during emergency intake.';
                if (xhr.responseJSON?.errors) {
                    Object.values(xhr.responseJSON.errors).flat().forEach(function(e) { toastr.error(e); });
                } else {
                    toastr.error(msg);
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    // ---- Emergency Intake Summary ----
    function updateEmergencyIntakeSummary() {
        var $modal = $('#patientFormModal');
        var isMorgue = $modal.hasClass('pf-morgue-mode');

        // Update summary title and accent color based on mode
        if (isMorgue) {
            $('#emergency-intake-summary')
                .css('border-left-color', '#1a202c')
                .find('h6').first()
                .html('<i class="mdi mdi-emoticon-dead text-dark"></i> Morgue Admission Summary');
        } else {
            $('#emergency-intake-summary')
                .css('border-left-color', '#dc3545')
                .find('h6').first()
                .html('<i class="mdi mdi-ambulance text-danger"></i> Emergency Intake Summary');
        }

        // BID record info
        if ($('#pf-is-bid').is(':checked')) {
            $('#emg-summary-bid-date').text($('#pf-bid-date').val() || '-');
            $('#emg-summary-bid-time').text($('#pf-bid-time').val() || '-');
            $('#emg-summary-bid-cause').text($('#pf-bid-cause').val() || 'Unknown');
        }

        var existingId = $('#pf-emergency-patient-id').val();

        // Patient info
        if (existingId) {
            $('#emg-summary-name').text($('#pf-emergency-patient-name').text() || '-');
            $('#emg-summary-fileno').text($('#pf-emergency-patient-fileno').text() || '-');
            $('#emg-summary-gender').text('-');
            $('#emg-summary-patient-type').html('<span class="badge bg-info">Existing Patient</span>');
        } else {
            var fullName = [$('#pf-surname').val(), $('#pf-firstname').val(), $('#pf-othername').val()].filter(Boolean).join(' ');
            $('#emg-summary-name').text(fullName || '-');
            $('#emg-summary-fileno').text($('#pf-file-no').val() || '-');
            $('#emg-summary-gender').text($('#pf-gender').val() || '-');
            var isUnidentified = $('#pf-is-unidentified').val() === '1';
            $('#emg-summary-patient-type').html(isUnidentified
                ? '<span class="badge bg-warning text-dark">Unidentified</span>'
                : '<span class="badge bg-success">New Patient</span>');
        }

        // Triage
        var esi = $('#pf-esi-level').val();
        var esiLabels = {1:'1 - Resuscitation',2:'2 - Emergent',3:'3 - Urgent',4:'4 - Less Urgent',5:'5 - Non-Urgent'};
        var esiColors = {1:'danger',2:'danger',3:'warning',4:'info',5:'success'};
        if (esi) {
            $('#emg-summary-esi').html('<span class="badge bg-' + (esiColors[esi]||'secondary') + '">' + (esiLabels[esi]||esi) + '</span>');
        } else {
            $('#emg-summary-esi').text('-');
        }
        $('#emg-summary-complaint').text($('#pf-chief-complaint').val() || '-');
        var gcs = $('#pf-gcs-total-val').val();
        $('#emg-summary-gcs').text(gcs ? gcs + '/15' : 'Not assessed');
        $('#emg-summary-pain').text($('#pf-pain-scale').val()> 0 ? $('#pf-pain-scale').val() + '/10' : '0/10');

        var allergyStatus = $('input[name="pf_allergy_status"]:checked').val();
        if (allergyStatus === 'nkda') $('#emg-summary-allergy').html('<span class="badge bg-success">NKDA</span>');
        else if (allergyStatus === 'has_allergies') $('#emg-summary-allergy').html('<span class="badge bg-danger">' + ($('#pf-allergies-text').val() || 'Has Allergies') + '</span>');
        else $('#emg-summary-allergy').html('<span class="badge bg-secondary">Unknown</span>');

        // Vitals
        var vitals = [];
        var hr = $('#pf-vital-hr').val(); if (hr) vitals.push({label:'HR', val: hr + ' bpm'});
        var bps = $('#pf-vital-bp-sys').val(); var bpd = $('#pf-vital-bp-dia').val();
        if (bps && bpd) vitals.push({label:'BP', val: bps + '/' + bpd + ' mmHg'});
        var spo2 = $('#pf-vital-spo2').val(); if (spo2) vitals.push({label:'SpO2', val: spo2 + '%'});
        var temp = $('#pf-vital-temp').val(); if (temp) vitals.push({label:'Temp', val: temp + '°C'});
        var rr = $('#pf-vital-rr').val(); if (rr) vitals.push({label:'RR', val: rr + '/min'});
        var bs = $('#pf-vital-bs').val(); if (bs) vitals.push({label:'BS', val: bs + ' mg/dl'});

        if (vitals.length> 0) {
            var html = '';
            vitals.forEach(function(v) {
                html += '<div class="summary-item"><span class="summary-label">' + v.label + ':</span><span class="summary-value">' + v.val + '</span></div>';
            });
            $('#emg-summary-vitals-grid').html(html);
            $('#emg-summary-vitals-section').show();
        } else {
            $('#emg-summary-vitals-section').hide();
        }

        // Disposition
        var disp = $('input[name="pf_disposition"]:checked').val();
        var dispLabels = {
            'admit_emergency': '<i class="mdi mdi-bed text-danger"></i> Admit to Emergency Ward',
            'queue_consultation': '<i class="mdi mdi-account-clock text-warning"></i> Queue for Consultation',
            'direct_service': '<i class="mdi mdi-flask text-info"></i> Direct to Lab/Imaging',
            'morgue_mortal': '<i class="mdi mdi-emoticon-dead text-dark"></i> Direct to Morgue'
        };
        $('#emg-summary-disposition').html(dispLabels[disp] || '-');

        var detail = '';
        if (disp === 'admit_emergency') {
            var svc = $('#pf-admit-service-select option:selected').text();
            var clinic = $('#pf-admit-clinic-select option:selected').text();
            var ward = $('#pf-ward-select option:selected').text();
            var bed = $('#pf-bed-select option:selected').text();
            var bedInfo = (ward && !ward.startsWith('--') ? ward + ' / ' : '') + (bed && !bed.startsWith('--') ? bed : '');
            detail = [svc, clinic, bedInfo].filter(function(x){ return x && !x.startsWith('--') && x.trim(); }).join(' | ');
        } else if (disp === 'queue_consultation') {
            var clinic = $('#pf-clinic-select option:selected').text();
            var svc = $('#pf-service-select option:selected').text();
            detail = [clinic, svc].filter(function(x){ return x && !x.startsWith('--'); }).join(' | ');
        } else if (disp === 'direct_service') {
            detail = pfDirectServices.map(function(s){ return s.type.toUpperCase() + ': ' + s.name; }).join(', ');
        } else if (disp === 'morgue_mortal') {
            var svc = $('#pf-morgue-service-select option:selected').text();
            var fridge = $('#pf-morgue-fridge').val();
            var tray = $('#pf-morgue-tray').val();
            detail = [svc, fridge ? 'Fridge: ' + fridge : null, tray ? 'Tray: ' + tray : null].filter(Boolean).join(' | ');
        }
        if (detail) {
            $('#emg-summary-disposition-detail').text(detail);
            $('#emg-summary-disposition-detail-row').show();
        } else {
            $('#emg-summary-disposition-detail-row').hide();
        }

        // Elapsed time
        var m = String(Math.floor(pfEmergencyTimerSeconds / 60)).padStart(2, '0');
        var s = String(pfEmergencyTimerSeconds % 60).padStart(2, '0');
        $('#emg-summary-elapsed').text(m + ':' + s);
    }

    // ---- Reset emergency fields ----
    function resetEmergencyFields() {
        // Patient chooser tabs — reset to "existing" tab
        $('.pf-chooser-tab').removeClass('active');
        $('.pf-chooser-tab[data-panel="existing"]').addClass('active');
        $('.pf-chooser-panel').removeClass('active');
        $('.pf-chooser-panel[data-panel="existing"]').addClass('active');
        $('#pf-chooser-body').removeClass('border-new border-unidentified').addClass('border-existing');
        $('#pf-patient-chooser-mode').val('existing');
        $('#pf-existing-empty-state').show();

        // Patient search
        $('#pf-emergency-patient-id').val('');
        $('#pf-emergency-selected-patient').removeClass('show');
        $('#pf-emergency-patient-search').val('');
        $('#pf-emergency-patient-results').hide();
        $('#pf-new-patient-wrapper').removeClass('collapsed pf-unidentified-active');

        // Identity mode
        $('#pf-is-unidentified').val('0');
        $('#pf-distinguishing-features').val('');
        // Clear unidentified-only fields
        $('#pf-gender-unid').val('');
        $('#pf-approx-age-unid').val('');
        $('#pf-phone-unid').val('');

        // Arrival
        $('#pf-approx-age').val('');
        $('#pf-arrival-mode').val('walk_in');
        $('#pf-brought-by-name').val('');
        $('#pf-brought-by-phone').val('');

        // Triage
        $('#pf-esi-level').val('');
        $('.pf-esi-btn').removeClass('selected');
        $('#pf-esi-hint-box').hide();
        $('#pf-chief-complaint').val('');
        $('#pf-vital-hr, #pf-vital-bp-sys, #pf-vital-bp-dia, #pf-vital-spo2, #pf-vital-temp, #pf-vital-rr, #pf-vital-bs').val('');
        $('#pf-gcs-eye, #pf-gcs-verbal, #pf-gcs-motor').val('');
        $('#pf-gcs-total').val('--').removeClass('pf-gcs-severe pf-gcs-moderate pf-gcs-mild');
        $('#pf-gcs-total-val').val('');
        $('#pf-pain-scale').val(0);
        $('#pf-pain-display').text('0');
        $('input[name="pf_allergy_status"][value="nkda"]').prop('checked', true);
        $('#pf-allergy-text-input').hide();
        $('#pf-allergies-text').val('');
        $('#pf-triage-notes').val('');
        $('#pf-vitals-panel, #pf-gcs-panel').collapse('hide');

        // Disposition
        $('input[name="pf_disposition"]').prop('checked', false);
        $('#pf-admit-options, #pf-consult-options, #pf-direct-options, #pf-morgue-options').hide();
        pfDirectServices = [];
        renderDirectServices();
        pfDispositionLoaded = false;
        pfAllBeds = [];
        ['#pf-ward-select','#pf-bed-select','#pf-admit-service-select','#pf-service-select'].forEach(function(id) {
            var $el = $(id);
            if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
            $el.empty().append('<option value=""></option>');
        });

        // Morgue-specific resets
        pfMorgueServicesLoaded = false;
        $('#pf-morgue-fridge').val('');
        $('#pf-morgue-tray').val('');
        $('#pf-morgue-notes').val('');
        $('#pf-morgue-service-select').html('<option value="">-- Select Daily Rate --</option>');
        $('#patientFormModal').removeClass('pf-bid-active');
        // Re-enable BID toggle (was disabled in morgue mode)
        $('#pf-is-bid').prop('disabled', false);
    }

    // Reset on modal close
    $('#patientFormModal').on('hidden.bs.modal', function() {
        if (pfEmergencyMode) {
            disableEmergencyMode();
        }
    });

})();


// Family folder Select2 and Toggle Logic
$(document).ready(function() {
    // Initialize select2 for principal
    if ($.fn.select2) {
        $('#pf-principal-id').select2({
            dropdownParent: $('#patientFormModal'),
            placeholder: 'Search for principal...',
            allowClear: true,
            ajax: {
                url: wbRoute('reception.search-patients', '/reception/search-patients'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        principals_only: 1
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data, function (item) {
                            var ageStr = item.age ? item.age + ' yrs' : 'N/A';
                            return {
                                text: item.name + ' (' + item.file_no + ') - ' + item.gender + ', ' + ageStr,
                                id: item.id
                            }
                        })
                    };
                },
                cache: true
            }
        });
    }

    // Toggle container based on checkbox
    $('#pf-is-family-principal').on('change', function() {
        if ($(this).is(':checked')) {
            $('#pf-principal-select-container').hide();
            $('#pf-principal-id').val(null).trigger('change');
        } else {
            $('#pf-principal-select-container').show();
        }
    });
});