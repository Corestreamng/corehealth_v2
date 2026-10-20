/**
 * CoreHealth v2 — Universal Workbench Shell Framework
 * ============================================================================
 * Provides standardized layout controls, patient lifecycle, queue management,
 * and responsive pane coordination across all clinical workbenches (Billing,
 * Maternity, Nursing, Pharmacy, Lab, Imaging, Reception).
 * ============================================================================
 */
(function(window, $) {
    'use strict';

    var WorkbenchShell = {
        config: {
            module: 'workbench',
            walkinPrefix: 'WALK-',
            queueCountsUrl: '',
            queueDatatableSelector: '#queue-datatable',
            pollIntervalMs: 30000,
            onPatientSelected: null,
            onPatientCleared: null,
            onTabSwitch: null,
            onQueueFilter: null
        },

        state: {
            currentPatient: null,
            currentPatientData: null,
            activeTab: null,
            activeQueueFilter: 'all',
            isLeftPanelCollapsed: false,
            pollTimer: null
        },

        init: function(userConfig) {
            this.config = $.extend({}, this.config, userConfig || {});
            
            // Read from global WORKBENCH_CONFIG if available
            if (window.WORKBENCH_CONFIG) {
                if (!this.config.queueCountsUrl && window.WORKBENCH_CONFIG.routes) {
                    var routeKey = this.config.module + '.queue-counts';
                    if (window.WORKBENCH_CONFIG.routes[routeKey]) {
                        this.config.queueCountsUrl = window.WORKBENCH_CONFIG.routes[routeKey];
                    }
                }
            }

            this.bindEvents();
            this.checkUrlParams();
            this.startQueuePolling();

            console.log('[WorkbenchShell] Initialized for module:', this.config.module);
        },

        bindEvents: function() {
            var self = this;

            // 1. Left Panel / Search Toggle
            $(document).on('click', '#btn-toggle-search, .btn-toggle-search', function(e) {
                e.preventDefault();
                self.toggleLeftPanel();
            });

            // 2. Mobile back-to-search & view-work-pane
            $(document).on('click', '#btn-back-to-search, .btn-back-to-search', function(e) {
                e.preventDefault();
                self.showSearchPaneMobile();
            });

            $(document).on('click', '#btn-view-work-pane, .btn-view-work-pane', function(e) {
                e.preventDefault();
                self.showWorkPaneMobile();
            });

            // 3. Queue Drawer toggles
            $(document).on('click', '#show-all-queue-btn, #view-queue-btn', function(e) {
                e.preventDefault();
                self.showQueueView('all');
            });

            $(document).on('click', '#btn-close-queue, .btn-close-queue', function(e) {
                e.preventDefault();
                self.hideQueueView();
            });

            // 4. Queue item clicks in left panel
            $(document).on('click', '.queue-widget .queue-item', function(e) {
                e.preventDefault();
                var filter = $(this).data('filter') || 'all';
                $('.queue-widget .queue-item').removeClass('active');
                $(this).addClass('active');
                self.state.activeQueueFilter = filter;
                
                if (typeof self.config.onQueueFilter === 'function') {
                    self.config.onQueueFilter(filter);
                }
                $(document).trigger('workbench:queue-filter-changed', [filter]);
            });

            // 5. Workspace Tab clicks
            $(document).on('click', '.workspace-tabs .workspace-tab', function(e) {
                e.preventDefault();
                var tabKey = $(this).data('tab');
                if (tabKey) {
                    self.switchTab(tabKey);
                }
            });

            // 6. Patient Biodata Drawer Expand/Collapse
            $(document).on('click', '#btn-expand-patient, .btn-expand-patient', function(e) {
                e.preventDefault();
                var $drawer = $('#patient-details-expanded');
                var $btn = $(this);
                if ($drawer.is(':visible')) {
                    $drawer.slideUp(180);
                    $btn.find('.btn-expand-text').text('Biodata');
                    $btn.find('i').removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
                } else {
                    $drawer.slideDown(180);
                    $btn.find('.btn-expand-text').text('Hide');
                    $btn.find('i').removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
                }
            });

            // 7. Clear Active Patient
            $(document).on('click', '#btn-switch-patient, #btn-clear-patient', function(e) {
                e.preventDefault();
                self.clearPatient();
            });

            // 8. Keyboard Shortcuts (Alt+S or / to search)
            $(document).on('keydown', function(e) {
                if ((e.altKey && e.key.toLowerCase() === 's') || (e.key === '/' && !$(e.target).is('input, textarea, select'))) {
                    e.preventDefault();
                    var $input = $('#patient-search-input');
                    if ($input.length) {
                        $input.focus().select();
                    }
                }
            });
        },

        collapseLeftPanel: function() {
            var $panel = $('#left-panel');
            if ($panel.length) {
                $panel.addClass('collapsed hidden');
                this.state.isLeftPanelCollapsed = true;
                $(document).trigger('workbench:panel-toggled', [false]);
            }
        },

        expandLeftPanel: function() {
            var $panel = $('#left-panel');
            if ($panel.length) {
                $panel.removeClass('collapsed hidden');
                this.state.isLeftPanelCollapsed = false;
                $(document).trigger('workbench:panel-toggled', [true]);
            }
        },

        toggleLeftPanel: function() {
            var $panel = $('#left-panel');
            if ($panel.length) {
                if ($panel.hasClass('collapsed') || $panel.hasClass('hidden')) {
                    this.expandLeftPanel();
                } else {
                    this.collapseLeftPanel();
                }
            }
        },

        showSearchPaneMobile: function() {
            $('#main-workspace').removeClass('active');
            this.expandLeftPanel();
        },

        showWorkPaneMobile: function() {
            this.collapseLeftPanel();
            $('#main-workspace').addClass('active');
        },

        showQueueView: function(filter) {
            filter = filter || this.state.activeQueueFilter || 'all';
            var $queueView = $('#queue-view');
            if ($queueView.length) {
                $queueView.addClass('active').show();
                if ($('#empty-state').length) {
                    $('#empty-state').hide();
                }
                $(document).trigger('workbench:queue-view-opened', [filter]);
            }
        },

        hideQueueView: function() {
            var $queueView = $('#queue-view');
            if ($queueView.length) {
                $queueView.removeClass('active').hide();
                if (!this.state.currentPatient && $('#empty-state').length) {
                    $('#empty-state').show();
                }
                $(document).trigger('workbench:queue-view-closed');
            }
        },

        switchTab: function(tabKey) {
            if (!tabKey) return;
            $('.workspace-tabs .workspace-tab').removeClass('active');
            $('.workspace-tabs .workspace-tab[data-tab="' + tabKey + '"]').addClass('active');

            $('.workspace-content .workspace-tab-content').removeClass('active').hide();
            var $targetContent = $('#' + tabKey + '-tab, #' + tabKey + '-content, [data-tab-content="' + tabKey + '"]');
            if ($targetContent.length) {
                $targetContent.addClass('active').show();
            }

            this.state.activeTab = tabKey;
            if (typeof this.config.onTabSwitch === 'function') {
                this.config.onTabSwitch(tabKey);
            }
            $(document).trigger('workbench:tab-switched', [tabKey]);
        },

        setPatient: function(patient) {
            if (!patient) return;
            var patientId = patient.id || patient;

            this.state.currentPatient = patientId;
            this.state.currentPatientData = (typeof patient === 'object') ? patient : { id: patientId };

            // Legacy compatibility globals
            window.currentPatient = patientId;
            window.currentPatientData = this.state.currentPatientData;

            // UI updates
            this.hideQueueView();
            $('#empty-state').hide().css('display', 'none').addClass('d-none hidden');
            $('#workspace-content').addClass('active').removeClass('d-none hidden').css('display', 'flex').show();
            $('#patient-header').addClass('active').removeClass('d-none hidden').css('display', 'block').show();

            // Automatically collapse/hide search sidebar upon selecting patient
            this.collapseLeftPanel();

            // Mobile view transition
            this.showWorkPaneMobile();

            // Populate header if full object provided
            if (typeof patient === 'object' && (patient.firstname || patient.surname || patient.file_no)) {
                this.updatePatientHeader(patient);
            }

            if (typeof this.config.onPatientSelected === 'function') {
                this.config.onPatientSelected(patient);
            }

            $(document).trigger('workbench:patient-selected', [patient, patientId]);
        },

        clearPatient: function() {
            this.state.currentPatient = null;
            this.state.currentPatientData = null;
            window.currentPatient = null;
            window.currentPatientData = null;

            $('#workspace-content').removeClass('active').addClass('d-none hidden').css('display', 'none').hide();
            $('#patient-header').removeClass('active').addClass('d-none hidden').css('display', 'none').hide();
            $('#empty-state').removeClass('d-none hidden').css('display', 'flex').show();

            $('#patient-name').text('');
            $('#patient-meta').html('');
            $('#patient-details-grid').html('');
            $('#patient-details-expanded').hide();
            $('#patient-header-balance').hide();

            var $input = $('#patient-search-input');
            if ($input.length) {
                $input.val('');
            }

            // Restore/re-open search sidebar when returning to empty state
            this.expandLeftPanel();

            if (typeof this.config.onPatientCleared === 'function') {
                this.config.onPatientCleared();
            }

            $(document).trigger('workbench:patient-cleared');
        },

        updatePatientHeader: function(patient) {
            if (!patient) return;

            // Formatted patient name with othername
            var fullName = [patient.surname, patient.firstname, patient.othername].filter(Boolean).join(' ');
            if (!fullName && patient.name) {
                fullName = patient.name;
            }
            $('#patient-name').text(fullName || 'Unknown Patient');

            // Initials avatar
            var initials = '';
            if (patient.firstname) initials += patient.firstname.charAt(0);
            if (patient.surname) initials += patient.surname.charAt(0);
            if (initials) {
                $('#patient-avatar').text(initials.toUpperCase());
            }

            // Rich characteristic Meta tags
            var metaHtml = '';
            if (patient.file_no) {
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-folder-account-outline"></i> <span>File: <strong>' + window.escapeHtml(patient.file_no) + '</strong></span></div>';
            }
            var gender = patient.gender || patient.sex || '';
            if (gender) {
                var iconGender = (gender.toLowerCase() === 'male') ? 'mdi-gender-male' : (gender.toLowerCase() === 'female' ? 'mdi-gender-female' : 'mdi-gender-male-female');
                metaHtml += '<div class="patient-meta-item"><i class="mdi ' + iconGender + '"></i> <span>' + window.escapeHtml(gender) + '</span></div>';
            }
            var age = patient.age || (patient.dob ? patient.dob : '');
            if (age) {
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-calendar-clock"></i> <span>Age: ' + window.escapeHtml(age) + '</span></div>';
            }
            if (patient.blood_group && patient.blood_group !== 'N/A') {
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-water"></i> <span>' + window.escapeHtml(patient.blood_group) + (patient.genotype && patient.genotype !== 'N/A' ? ' (' + window.escapeHtml(patient.genotype) + ')' : '') + '</span></div>';
            }
            if (patient.phone_no || patient.phone) {
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-phone"></i> <span>' + window.escapeHtml(patient.phone_no || patient.phone) + '</span></div>';
            }
            if (patient.registration_workbench_label) {
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-source-branch"></i> <span>From: <strong>' + window.escapeHtml(patient.registration_workbench_label) + '</strong></span></div>';
            }
            var hmoName = (patient.hmo && patient.hmo.name) ? patient.hmo.name : (patient.hmo_name || '');
            if (hmoName) {
                var hmoText = window.escapeHtml(hmoName);
                if (patient.hmo_no) hmoText += ' (' + window.escapeHtml(patient.hmo_no) + ')';
                metaHtml += '<div class="patient-meta-item"><i class="mdi mdi-hospital-building"></i> <span>HMO: ' + hmoText + '</span></div>';
            }

            $('#patient-meta').html(metaHtml);

            // Balance if available
            if (typeof patient.account_balance !== 'undefined' && patient.account_balance !== null) {
                var balNum = parseFloat(patient.account_balance) || 0;
                var sign = balNum < 0 ? '-' : '';
                var formatted = sign + '₦' + Math.abs(balNum).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                $('#header-balance-amount').text(formatted);
                if (balNum < 0) {
                    $('#header-balance-amount').removeClass('text-success').addClass('text-danger');
                } else {
                    $('#header-balance-amount').removeClass('text-danger').addClass('text-success');
                }
                $('#patient-header-balance').show();
            }

            // Expandable details if grid exists and not yet populated
            if ($('#patient-details-grid').length && !$('#patient-details-grid').children().length) {
                var detailsHtml = '';
                if (patient.registration_workbench_label) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-source-branch"></i> Registered From</div><div class="patient-detail-value">' + window.escapeHtml(patient.registration_workbench_label) + '</div></div>';
                }
                if (patient.registered_on) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-calendar-clock"></i> Registered On</div><div class="patient-detail-value">' + window.escapeHtml(patient.registered_on) + '</div></div>';
                }
                if (patient.registered_by_name) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-account-tie"></i> Registered By</div><div class="patient-detail-value">' + window.escapeHtml(patient.registered_by_name) + '</div></div>';
                }
                if (patient.blood_group) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-water"></i> Blood Group</div><div class="patient-detail-value">' + window.escapeHtml(patient.blood_group) + '</div></div>';
                }
                if (patient.genotype) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-dna"></i> Genotype</div><div class="patient-detail-value">' + window.escapeHtml(patient.genotype) + '</div></div>';
                }
                if (patient.address) {
                    detailsHtml += '<div class="patient-detail-item"><div class="patient-detail-label"><i class="mdi mdi-map-marker"></i> Residential Address</div><div class="patient-detail-value">' + window.escapeHtml(patient.address) + '</div></div>';
                }
                if (detailsHtml) {
                    $('#patient-details-grid').html(detailsHtml);
                }
            }

            $('#btn-edit-patient').show();
        },

        openWalkInModal: function(prefix) {
            prefix = prefix || this.config.walkinPrefix || 'WALK-';
            if (typeof window.openWalkInRegistration === 'function') {
                window.openWalkInRegistration(prefix);
            } else if (typeof window.showPatientFormModal === 'function') {
                window.showPatientFormModal('create');
                if (typeof window.enableWalkInMode === 'function') {
                    window.enableWalkInMode(prefix);
                }
            }
        },

        checkUrlParams: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var patientId = urlParams.get('patient_id');
            if (patientId) {
                this.setPatient(patientId);
            }

            var queueFilter = urlParams.get('queue_filter');
            if (queueFilter) {
                var self = this;
                setTimeout(function() {
                    self.showQueueView(queueFilter);
                }, 300);
            }
        },

        startQueuePolling: function() {
            if (!this.config.queueCountsUrl || this.config.pollIntervalMs <= 0) return;
            var self = this;
            this.refreshQueueCounts();
            this.state.pollTimer = setInterval(function() {
                self.refreshQueueCounts();
            }, this.config.pollIntervalMs);
        },

        refreshQueueCounts: function() {
            if (!this.config.queueCountsUrl) return;
            $.ajax({
                url: this.config.queueCountsUrl,
                method: 'GET',
                cache: false,
                success: function(counts) {
                    if (!counts || typeof counts !== 'object') return;
                    $.each(counts, function(key, val) {
                        var $badge = $('#queue-' + key + '-count');
                        if ($badge.length) {
                            $badge.text(val || 0);
                        }
                    });
                    $(document).trigger('workbench:queue-counts-updated', [counts]);
                },
                error: function(err) {
                    console.warn('[WorkbenchShell] Failed to refresh queue counts:', err);
                }
            });
        }
    };

    window.WorkbenchShell = WorkbenchShell;

})(window, jQuery);
