/**
 * ClinicalContext — Shared Clinical Context Modal Module
 *
 * Provides unified data loading and rendering for the clinical context modal
 * used across all workbenches (Nursing, HMO, Pharmacy, Lab, Imaging).
 *
 * Usage:
 *   <script src="{{ asset('js/clinical-context.js') }}"></script>
 *   ClinicalContext.load(patientId);
 *
 * The module relies on:
 *   - jQuery (global)
 *   - DataTables jQuery plugin
 *   - The shared clinical_context_modal.blade.php partial being @include'd
 *   - Bootstrap modal (jQuery .modal() method)
 */
(function(window, $) {
    'use strict';

    // ─── Configuration ───────────────────────────────────────────────────
    var config = {
        baseUrl: '/clinical-context/patient',
    };

    // ─── State ───────────────────────────────────────────────────────────
    var tabsInitialized = false;

    // ─── Public API ──────────────────────────────────────────────────────
    var ClinicalContext = {};

    /**
     * Load clinical context for a patient and show the modal.
     * Sets global patient ID variables, initializes tabs, and eagerly loads
     * vitals, medications, and allergies. Encounter notes, injections/immunizations,
     * and procedures are lazy-loaded by the shared modal IIFE handlers on tab click.
     *
     * @param {number|string} patientId
     */
    ClinicalContext.load = function(patientId) {
        // Store patient ID for shared modal IIFE handlers
        window.currentClinicalPatientId = patientId;
        window.currentPatient = patientId;
        $('#clinical-context-modal').data('patient-id', patientId);

        // Initialize manual tab switching (once)
        initTabs();

        // Show modal
        $('#clinical-context-modal').modal('show');

        // Eager-load vitals (first/active tab)
        $.get(config.baseUrl + '/' + patientId + '/vitals', function(vitals) {
            ClinicalContext.displayVitals(vitals, patientId);
        }).fail(function() {
            $('#vitals-panel-body').html('<div class="alert alert-danger">Failed to load vitals</div>');
        });

        // Eager-load medications
        $.get(config.baseUrl + '/' + patientId + '/medications', function(meds) {
            ClinicalContext.displayMedications(meds, patientId);
        }).fail(function() {
            $('#clinical-meds-container').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load medications</div>');
        });

        // Eager-load allergies
        $.get(config.baseUrl + '/' + patientId + '/allergies', function(data) {
            ClinicalContext.displayAllergies(data);
        }).fail(function() {
            $('#allergies-panel-body').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load allergy information</div>');
        });

        // Encounter notes, Injection/Immunization, and Procedures are loaded
        // by the shared clinical_context_modal IIFE handlers when their tabs are clicked.
    };


    // ─── Tab Initialization ──────────────────────────────────────────────
    // BS5 data-bs-toggle="tab" may not auto-initialize in environments that
    // also load BS4 (vendor.bundle.base.js). This manual handler ensures
    // tabs work reliably everywhere.

    function initTabs() {
        if (tabsInitialized) return;
        tabsInitialized = true;

        // Main clinical tabs
        $('#clinical-context-modal').on('click', '#clinical-tabs .nav-link', function(e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.attr('data-bs-target') || $this.attr('href');
            if (!target) return;

            // Deactivate all tabs and panes
            $('#clinical-tabs .nav-link').removeClass('active').attr('aria-selected', 'false');
            $('#clinical-tab-content .tab-pane').removeClass('show active');

            // Activate clicked tab and target pane
            $this.addClass('active').attr('aria-selected', 'true');
            $(target).addClass('show active');

            // Fire shown.bs.tab so the shared modal IIFE data loaders trigger
            $this.trigger('shown.bs.tab');
        });

        // Inner sub-tabs (e.g. injection/immunization sub-tabs)
        $('#clinical-context-modal').on('click', '.tab-content [data-bs-toggle="tab"]', function(e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.attr('data-bs-target') || $this.attr('href');
            if (!target) return;

            var $tabList = $this.closest('.nav-tabs');
            var $tabContent = $tabList.next('.tab-content');
            if (!$tabContent.length) $tabContent = $tabList.siblings('.tab-content');

            $tabList.find('.nav-link').removeClass('active');
            $tabContent.find('.tab-pane').removeClass('show active');

            $this.addClass('active');
            $(target).addClass('show active');

            $this.trigger('shown.bs.tab');
        });
    }


    // ─── Vitals Rendering ────────────────────────────────────────────────

    /**
     * Render vitals into the modal's vitals tab using DataTable card layout.
     * Shows all 10 supported vital parameters with color classification.
     *
     * @param {Array} vitals - Array of vital sign objects from the API
     * @param {number|string} patientId
     */
    ClinicalContext.displayVitals = function(vitals, patientId) {
        if (typeof $.fn.DataTable === 'undefined') {
            $('#vitals-panel-body').html('<p class="text-danger">Error: DataTables library not loaded</p>');
            return;
        }

        // Restore the table structure if previously overwritten
        $('#vitals-panel-body').html(
            '<div class="table-responsive">' +
            '<table class="table" id="vitals-table" style="width: 100%">' +
            '<thead><tr><th>Vital Signs History</th></tr></thead>' +
            '</table></div>'
        );

        if ($.fn.DataTable.isDataTable('#vitals-table')) {
            $('#vitals-table').DataTable().destroy();
        }

        $('#vitals-table').DataTable({
            data: vitals,
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            dom: 't',
            language: {
                emptyTable: '<p class="text-muted">No recent vitals recorded</p>'
            },
            columns: [{
                data: null,
                render: function(data, type, row) {
                    var vitalDate = formatDateTime(row.time_taken || row.created_at);
                    var temp = row.temp || 'N/A';
                    var heartRate = row.heart_rate || 'N/A';
                    var bp = row.blood_pressure || 'N/A';
                    var respRate = row.resp_rate || 'N/A';
                    var weight = row.weight || 'N/A';
                    var height = row.height || 'N/A';
                    var spo2 = row.spo2 || 'N/A';
                    var bloodSugar = row.blood_sugar || 'N/A';
                    var painScore = row.pain_score;
                    var bmi = row.bmi || 'N/A';
                    var takenBy = row.taken_by || '';
                    var source = row.source || '';
                    var notes = row.other_notes || '';

                    // Calculate BMI if not stored but weight/height available
                    if (bmi === 'N/A' && weight !== 'N/A' && height !== 'N/A') {
                        var w = parseFloat(weight), h = parseFloat(height);
                        if (w > 0 && h > 0) bmi = (w / ((h / 100) ** 2)).toFixed(1);
                    }

                    var headerExtra = '';
                    if (takenBy) headerExtra += '<span class="badge bg-light text-dark"><i class="mdi mdi-account"></i> ' + takenBy + '</span> ';
                    if (source) headerExtra += '<span class="badge bg-secondary">' + source + '</span>';

                    var html =
                        '<div class="vital-entry">' +
                            '<div class="vital-entry-header">' +
                                '<span class="vital-date"><i class="mdi mdi-clock-outline"></i> ' + vitalDate + '</span>' +
                                '<span>' + headerExtra + '</span>' +
                            '</div>' +
                            '<div class="vital-entry-grid">' +
                                '<div class="vital-item ' + getTempClass(temp) + '">' +
                                    '<i class="mdi mdi-thermometer"></i>' +
                                    '<span class="vital-value">' + (temp !== 'N/A' ? temp + '\u00b0C' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Temperature</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getHeartRateClass(heartRate) + '">' +
                                    '<i class="mdi mdi-heart-pulse"></i>' +
                                    '<span class="vital-value">' + (heartRate !== 'N/A' ? heartRate + ' bpm' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Heart Rate</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getBPClass(bp) + '">' +
                                    '<i class="mdi mdi-water"></i>' +
                                    '<span class="vital-value">' + (bp !== 'N/A' ? bp + ' mmHg' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Blood Pressure</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getRespRateClass(respRate) + '">' +
                                    '<i class="mdi mdi-lungs"></i>' +
                                    '<span class="vital-value">' + (respRate !== 'N/A' ? respRate + ' bpm' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Respiratory Rate</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getSpO2Class(spo2) + '">' +
                                    '<i class="mdi mdi-pulse"></i>' +
                                    '<span class="vital-value">' + (spo2 !== 'N/A' ? spo2 + '%' : 'N/A') + '</span>' +
                                    '<span class="vital-label">SpO2</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getBloodSugarClass(bloodSugar) + '">' +
                                    '<i class="mdi mdi-water-opacity"></i>' +
                                    '<span class="vital-value">' + (bloodSugar !== 'N/A' ? bloodSugar + ' mg/dL' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Blood Sugar</span>' +
                                '</div>' +
                                '<div class="vital-item">' +
                                    '<i class="mdi mdi-weight-kilogram"></i>' +
                                    '<span class="vital-value">' + (weight !== 'N/A' ? weight + ' kg' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Weight</span>' +
                                '</div>' +
                                '<div class="vital-item">' +
                                    '<i class="mdi mdi-human-male-height"></i>' +
                                    '<span class="vital-value">' + (height !== 'N/A' ? height + ' cm' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Height</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getBMIClass(bmi) + '">' +
                                    '<i class="mdi mdi-calculator"></i>' +
                                    '<span class="vital-value">' + bmi + '</span>' +
                                    '<span class="vital-label">BMI</span>' +
                                '</div>' +
                                '<div class="vital-item ' + getPainClass(painScore) + '">' +
                                    '<i class="mdi mdi-emoticon-sad-outline"></i>' +
                                    '<span class="vital-value">' + (painScore != null && painScore !== '' ? painScore + '/10' : 'N/A') + '</span>' +
                                    '<span class="vital-label">Pain Score</span>' +
                                '</div>' +
                            '</div>';

                    // Show notes if present
                    if (notes) {
                        html +=
                            '<div class="mt-2 p-2 bg-light rounded" style="border-left: 3px solid #6c757d;">' +
                                '<small class="text-muted"><i class="mdi mdi-note-text"></i> <strong>Notes:</strong> ' + notes + '</small>' +
                            '</div>';
                    }

                    html += '</div>';
                    return html;
                }
            }],
            drawCallback: function() {
                var pid = window.currentPatient || window.currentClinicalPatientId;
                var $wrapper = $('#vitals-table_wrapper');
                $wrapper.find('.show-all-link').remove();
                $wrapper.append(
                    '<a href="/patients/show/' + pid + '?section=vitalsCardBody" target="_blank" class="show-all-link">' +
                        'Show All Vitals \u2192' +
                    '</a>'
                );
            }
        });
    };


    // ─── Medications Rendering ───────────────────────────────────────────

    /**
     * Render medications into the modal's medications tab using card layout.
     *
     * @param {Array} meds - Array of medication objects from the API
     * @param {number|string} patientId
     */
    ClinicalContext.displayMedications = function(meds, patientId) {
        if (!meds || meds.length === 0) {
            $('#clinical-meds-container').html(
                '<div class="text-center py-4">' +
                    '<i class="mdi mdi-pill mdi-48px text-muted"></i>' +
                    '<p class="text-muted mt-2">No medications found for this patient</p>' +
                '</div>'
            );
            $('#clinical-meds-show-all').html('');
            return;
        }

        var html = '';
        meds.forEach(function(med) {
            var drugName = med.drug_name || 'N/A';
            var productCode = med.product_code || '';
            var dose = med.dose || 'N/A';
            var freq = med.freq || '';
            var duration = med.duration || '';
            var status = med.status || 'pending';
            var requestedDate = med.requested_date || 'N/A';
            var doctor = med.doctor || 'N/A';

            var statusBadge = '';
            if (status === 'dispensed') {
                statusBadge = '<span class="badge bg-info">Dispensed</span>';
            } else if (status === 'billed') {
                statusBadge = '<span class="badge bg-primary">Billed</span>';
            } else {
                statusBadge = '<span class="badge bg-secondary">Pending</span>';
            }

            var doseInfo = dose;
            if (freq) doseInfo += ' | Freq: ' + freq;
            if (duration) doseInfo += ' | Duration: ' + duration;

            html += '<div class="card mb-2" style="border-left: 4px solid #0d6efd;">';
            html += '<div class="card-body p-3">';
            html += '<div class="d-flex justify-content-between align-items-start mb-3">';
            html += '<h6 class="mb-0"><span class="badge bg-success">' + (productCode ? '[' + productCode + '] ' : '') + drugName + '</span></h6>';
            html += statusBadge;
            html += '</div>';
            html += '<div class="alert alert-light mb-3"><small><b><i class="mdi mdi-pill"></i> Dose/Frequency:</b><br>' + doseInfo + '</small></div>';
            html += '<div class="mb-2"><small>';
            html += '<div class="mb-1"><i class="mdi mdi-account-arrow-right text-primary"></i> <b>Requested by:</b> '
                + doctor + ' <span class="text-muted">(' + requestedDate + ')</span></div>';
            html += '</small></div>';
            html += '</div>';
            html += '</div>';
        });

        $('#clinical-meds-container').html(html);

        $('#clinical-meds-show-all').html(
            '<a href="/patients/show/' + patientId + '?section=prescriptionsCardBody" target="_blank" class="btn btn-outline-primary btn-sm">' +
                '<i class="mdi mdi-open-in-new"></i> See More Prescriptions' +
            '</a>'
        );
    };


    // ─── Allergies Rendering ─────────────────────────────────────────────

    /**
     * Render allergies into the modal's allergies tab.
     * Handles arrays, JSON strings, comma-separated strings, and objects.
     *
     * @param {Object} data - { allergies: Array|string, medical_history: string|null }
     */
    ClinicalContext.displayAllergies = function(data) {
        var allergiesArray = parseAllergies(data && data.allergies ? data.allergies : []);

        var html = '';

        if (allergiesArray.length > 0) {
            html +=
                '<div class="alert alert-danger d-flex align-items-center mb-3" role="alert">' +
                    '<i class="mdi mdi-alert-circle mdi-24px me-2"></i>' +
                    '<strong>' + allergiesArray.length + ' known allerg' + (allergiesArray.length === 1 ? 'y' : 'ies') + ' on record</strong>' +
                '</div>' +
                '<div class="d-flex flex-wrap gap-2 mb-3">';

            allergiesArray.forEach(function(allergy) {
                var allergyName = allergy;
                var severity = '';
                var reaction = '';

                // Handle if allergy is an object with name/severity/reaction
                if (typeof allergy === 'object' && allergy !== null) {
                    allergyName = allergy.name || allergy.allergen || allergy.allergy || JSON.stringify(allergy);
                    severity = allergy.severity || '';
                    reaction = allergy.reaction || '';
                }

                var severityClass = 'allergy-card';
                if (severity && severity.toLowerCase() === 'severe') {
                    severityClass += ' severe';
                }

                html += '<div class="' + severityClass + '">';
                html += '<div class="allergy-name"><i class="mdi mdi-alert"></i> ' + allergyName + '</div>';

                if (reaction) {
                    html += '<div class="allergy-reaction"><strong>Reaction:</strong> ' + reaction + '</div>';
                }

                if (severity) {
                    var sevClass = severity.toLowerCase() === 'severe' ? 'severity-severe' :
                                   (severity.toLowerCase() === 'moderate' ? 'severity-moderate' : 'severity-mild');
                    html += '<span class="allergy-severity ' + sevClass + '">' + severity + '</span>';
                }

                html += '</div>';
            });

            html += '</div>';
        } else {
            html =
                '<div class="text-center py-4">' +
                    '<i class="mdi mdi-check-circle mdi-48px text-success"></i>' +
                    '<p class="text-success mt-2 mb-0"><strong>No Known Allergies (NKA)</strong></p>' +
                    '<small class="text-muted">No allergy information has been recorded for this patient</small>' +
                '</div>';
        }

        // Add medical history if available
        if (data && data.medical_history && data.medical_history !== 'N/A') {
            html +=
                '<div class="mt-3 p-3 bg-light rounded">' +
                    '<h6 class="mb-2"><i class="mdi mdi-clipboard-text"></i> Medical History</h6>' +
                    '<p class="mb-0 text-muted">' + data.medical_history + '</p>' +
                '</div>';
        }

        $('#allergies-list').html(html);
    };


    // ─── Allergy Parser ──────────────────────────────────────────────────

    function parseAllergies(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw;
        if (typeof raw === 'string') {
            try {
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            } catch(e) {
                return raw.split(',').map(function(a) { return a.trim(); }).filter(function(a) { return a; });
            }
        }
        if (typeof raw === 'object') {
            return Object.values(raw).filter(function(a) { return a; });
        }
        return [];
    }


    // ─── Vital Sign Classification Helpers ───────────────────────────────

    function getTempClass(temp) {
        if (temp === 'N/A') return '';
        var t = parseFloat(temp);
        if (t < 34 || t > 39) return 'vital-critical';
        if (t < 36.1 || t > 38.0) return 'vital-warning';
        return 'vital-normal';
    }

    function getHeartRateClass(heartRate) {
        if (heartRate === 'N/A') return '';
        var hr = parseInt(heartRate);
        if (hr < 50 || hr > 220) return 'vital-critical';
        if (hr < 60 || hr > 100) return 'vital-warning';
        return 'vital-normal';
    }

    function getRespRateClass(respRate) {
        if (respRate === 'N/A') return '';
        var rr = parseInt(respRate);
        if (rr < 10 || rr > 35) return 'vital-critical';
        if (rr < 12 || rr > 30) return 'vital-warning';
        return 'vital-normal';
    }

    function getBPClass(bp) {
        if (bp === 'N/A' || !bp || !bp.includes('/')) return '';
        var parts = bp.split('/').map(function(v) { return parseInt(v); });
        var systolic = parts[0], diastolic = parts[1];
        if (systolic > 180 || systolic < 80 || diastolic > 110 || diastolic < 50) return 'vital-critical';
        if (systolic > 140 || systolic < 90 || diastolic > 90 || diastolic < 60) return 'vital-warning';
        return 'vital-normal';
    }

    function getSpO2Class(spo2) {
        if (spo2 === 'N/A') return '';
        var val = parseFloat(spo2);
        if (val < 90) return 'vital-critical';
        if (val < 95) return 'vital-warning';
        return 'vital-normal';
    }

    function getBloodSugarClass(bs) {
        if (bs === 'N/A') return '';
        var val = parseFloat(bs);
        if (val < 54 || val > 400) return 'vital-critical';
        if (val < 70 || val > 180) return 'vital-warning';
        return 'vital-normal';
    }

    function getBMIClass(bmi) {
        if (bmi === 'N/A') return '';
        var val = parseFloat(bmi);
        if (val < 16 || val > 40) return 'vital-critical';
        if (val < 18.5 || val > 30) return 'vital-warning';
        return 'vital-normal';
    }

    function getPainClass(painScore) {
        if (painScore == null || painScore === '' || painScore === 'N/A') return '';
        var val = parseInt(painScore);
        if (val >= 7) return 'vital-critical';
        if (val >= 4) return 'vital-warning';
        return 'vital-normal';
    }


    // ─── Utility ─────────────────────────────────────────────────────────

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        var dateOptions = { month: 'short', day: 'numeric' };
        var timeOptions = { hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-US', dateOptions) + ', ' + date.toLocaleTimeString('en-US', timeOptions);
    }


    // ─── Refresh Handler ─────────────────────────────────────────────────
    // Delegates to ClinicalContext.load() when any refresh button is clicked,
    // but specifically handles the allergies panel refresh inline.

    $(document).on('click', '.refresh-clinical-btn[data-panel="allergies"]', function() {
        var patientId = window.currentPatient || window.currentClinicalPatientId;
        if (!patientId) return;
        $('#allergies-list').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i></div>');
        $.get(config.baseUrl + '/' + patientId + '/allergies', function(data) {
            ClinicalContext.displayAllergies(data);
        }).fail(function() {
            $('#allergies-list').html('<div class="alert alert-danger"><i class="mdi mdi-alert-circle"></i> Failed to load allergy information</div>');
        });
    });

    // General refresh handler — reloads everything for the current patient
    $(document).on('click', '.refresh-clinical-btn:not([data-panel="allergies"])', function() {
        var panel = $(this).data('panel');
        var patientId = window.currentPatient || window.currentClinicalPatientId;
        if (!patientId) return;

        if (panel === 'vitals') {
            $('#vitals-panel-body').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px text-muted"></i></div>');
            $.get(config.baseUrl + '/' + patientId + '/vitals', function(vitals) {
                ClinicalContext.displayVitals(vitals, patientId);
            }).fail(function() {
                $('#vitals-panel-body').html('<div class="alert alert-danger">Failed to load vitals</div>');
            });
        }
        // enc-notes, inj-imm, and procedures refresh are handled by the shared modal IIFEs
    });


    // ─── Expose globally ─────────────────────────────────────────────────
    window.ClinicalContext = ClinicalContext;

})(window, jQuery);
