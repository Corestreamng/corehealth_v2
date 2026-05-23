/**
 * Patient Clinical Story Module
 * Path: public/js/clinical-story.js
 * Date-based vertical timeline with encounter sub-grouping and category folders.
 */
(function (window, document, $) {
    'use strict';

    class ClinicalStory {
        constructor(wrapper) {
            this.$wrapper = $(wrapper);
            this.encounterId = this.$wrapper.data('encounter-id');
            this.patientId = this.$wrapper.data('patient-id');
            this.uniqueId = this.$wrapper.attr('id').replace('clinical-story-', '');

            const past = new Date();
            past.setMonth(past.getMonth() - 3);
            this.dateFrom = past.toISOString().split('T')[0];
            this.dateTo = new Date().toISOString().split('T')[0];
            this.encounterFilter = '';

            this.currentPage = 1;
            this.hasMore = true;
            this.isLoading = false;
            this.categoryCache = {};

            this.folderDefinitions = [
                { id: 'clinical_data', title: 'Clinical Data', icon: 'fa-user-md', categories: ['vitals', 'clinical_notes', 'nursing_notes', 'care_plans'] },
                { id: 'medications', title: 'Medications & Administration', icon: 'fa-medkit', categories: ['prescriptions', 'med_admin', 'injections'] },
                { id: 'diagnostics', title: 'Diagnostics & Reports', icon: 'fa-flask', categories: ['labs', 'imaging'] },
                { id: 'flowsheets', title: 'Flowsheets & Tracking', icon: 'fa-tint', categories: ['intake_output'] },
                { id: 'administrative', title: 'Administrative & Procedures', icon: 'fa-hospital-o', categories: ['procedures', 'admissions', 'referrals'] }
            ];

            this.maternityFolder = { id: 'maternity', title: 'Maternity', icon: 'fa-female', categories: ['anc_visits', 'delivery', 'postnatal'] };

            this.categoryConfigs = {
                'vitals': { title: 'Vital Signs', icon: 'fa-heartbeat', colorClass: 'bg-vitals' },
                'clinical_notes': { title: 'Clinical Notes & Diagnoses', icon: 'fa-file-text-o', colorClass: 'bg-notes' },
                'nursing_notes': { title: 'Nursing Notes', icon: 'fa-user-md', colorClass: 'bg-nurse' },
                'med_admin': { title: 'Medication Administration', icon: 'fa-check-square-o', colorClass: 'bg-med-admin' },
                'intake_output': { title: 'Intake & Output', icon: 'fa-tint', colorClass: 'bg-io' },
                'injections': { title: 'Injections & Immunization', icon: 'fa-eyedropper', colorClass: 'bg-injection' },
                'labs': { title: 'Laboratory Services', icon: 'fa-flask', colorClass: 'bg-labs' },
                'imaging': { title: 'Imaging & Radiology', icon: 'fa-television', colorClass: 'bg-imaging' },
                'prescriptions': { title: 'Prescriptions', icon: 'fa-pencil-square-o', colorClass: 'bg-prescriptions' },
                'care_plans': { title: 'Care Plans', icon: 'fa-calendar-check-o', colorClass: 'bg-care' },
                'procedures': { title: 'Clinical Procedures', icon: 'fa-scissors', colorClass: 'bg-procedures' },
                'admissions': { title: 'Admissions & Discharges', icon: 'fa-bed', colorClass: 'bg-admission' },
                'referrals': { title: 'Referrals', icon: 'fa-arrow-circle-right', colorClass: 'bg-referrals' },
                'anc_visits': { title: 'ANC Visits', icon: 'fa-female', colorClass: 'bg-anc' },
                'delivery': { title: 'Labour & Delivery', icon: 'fa-child', colorClass: 'bg-delivery' },
                'postnatal': { title: 'Postnatal Logs', icon: 'fa-heart', colorClass: 'bg-postnatal' }
            };

            this.initElements();
            this.bindEvents();
            this.setInitialFilters();
            this.loadTimeline();
        }

        initElements() {
            const u = this.uniqueId;
            this.$btnRefresh = $(`#btn-refresh-story-${u}`);
            this.$dateFromInput = $(`#filter-date-from-${u}`);
            this.$dateToInput = $(`#filter-date-to-${u}`);
            this.$encounterSelect = $(`#filter-encounter-${u}`);
            this.$btnApplyFilters = $(`#btn-apply-filters-${u}`);
            this.$btnResetFilters = $(`#btn-reset-filters-${u}`);
            this.$timelineContainer = $(`#clinical-timeline-container-${u}`);
            this.$paginationContainer = $(`#clinical-timeline-pagination-${u}`);
            this.$btnLoadMore = $(`#btn-load-more-${u}`);
            this.$modal = $(`#story-detail-modal-${u}`);
            this.$modalTitle = $(`#story-detail-title-${u}`);
            this.$modalBody = $(`#story-detail-body-${u}`);
        }

        setInitialFilters() {
            this.$dateFromInput.val(this.dateFrom);
            this.$dateToInput.val(this.dateTo);
        }

        bindEvents() {
            this.$btnRefresh.on('click', () => { this.resetTimeline(); this.loadTimeline(); });
            this.$btnApplyFilters.on('click', () => {
                this.dateFrom = this.$dateFromInput.val();
                this.dateTo = this.$dateToInput.val();
                this.encounterFilter = this.$encounterSelect.val();
                this.resetTimeline();
                this.loadTimeline();
            });
            this.$btnResetFilters.on('click', () => {
                this.$dateFromInput.val('');
                this.$dateToInput.val('');
                this.$encounterSelect.val('');
                this.dateFrom = '';
                this.dateTo = '';
                this.encounterFilter = '';
                this.resetTimeline();
                this.loadTimeline();
            });
            this.$btnLoadMore.on('click', () => {
                if (this.hasMore && !this.isLoading) { this.currentPage++; this.loadTimeline(); }
            });

            // Folder toggle
            this.$timelineContainer.on('click', '.timeline-folder-header', (e) => {
                const $header = $(e.currentTarget);
                const $body = $header.next('.timeline-folder-body');
                const isExpanded = $header.attr('aria-expanded') === 'true';
                if (isExpanded) {
                    $body.slideUp(200);
                    $header.attr('aria-expanded', 'false');
                } else {
                    $body.slideDown(200);
                    $header.attr('aria-expanded', 'true');
                    const dateFilter = $header.data('date');
                    const categories = $header.data('categories').split(',');
                    categories.forEach(cat => this.loadCategoryPreview(dateFilter, cat, $body));
                }
            });

            // Preview card click → detail modal
            this.$timelineContainer.on('click', '.category-preview-card', (e) => {
                const $card = $(e.currentTarget);
                this.openCategoryDetails($card.data('date'), $card.data('category'));
            });
        }

        resetTimeline() {
            this.currentPage = 1;
            this.hasMore = true;
            this.categoryCache = {};
            this.$timelineContainer.html('<div class="text-center text-muted py-5"><i class="fa fa-spinner fa-spin fa-2x mb-2"></i><div>Loading timeline...</div></div>');
            this.$paginationContainer.addClass('d-none');
        }

        loadTimeline() {
            this.isLoading = true;
            const $btnIcon = this.$btnLoadMore.find('i');
            $btnIcon.removeClass('fa-angle-double-down').addClass('fa-spinner fa-spin');

            let url = this.encounterId 
                ? `/encounters/${this.encounterId}/clinical-story/timeline?page=${this.currentPage}` 
                : `/patients/${this.patientId}/clinical-story/timeline?page=${this.currentPage}`;
            if (this.dateFrom) url += `&date_from=${this.dateFrom}`;
            if (this.dateTo) url += `&date_to=${this.dateTo}`;
            if (this.encounterFilter) url += `&encounter_filter=${this.encounterFilter}`;

            $.ajax({
                url: url,
                type: 'GET',
                success: (response) => {
                    this.isLoading = false;
                    $btnIcon.addClass('fa-angle-double-down').removeClass('fa-spinner fa-spin');

                    if (!response.success) return;

                    if (this.currentPage === 1) {
                        this.$timelineContainer.empty();
                        
                        // Populate Consultation/Encounter filter dropdown
                        if (response.consultations && response.consultations.length > 0) {
                            const currentVal = this.$encounterSelect.val();
                            this.$encounterSelect.empty().append('<option value="">All Encounters</option>');
                            response.consultations.forEach(c => {
                                const selected = c.id == currentVal ? 'selected' : '';
                                this.$encounterSelect.append(`<option value="${c.id}" ${selected}>${c.date_formatted} — ${c.clinic_name} (${c.doctor_name})</option>`);
                            });
                        } else {
                            this.$encounterSelect.empty().append('<option value="">All Encounters</option>');
                        }
                    }

                    if (response.timeline && response.timeline.length > 0) {
                        response.timeline.forEach(day => this.renderDateNode(day, response.maternity_enrolled));
                    } else if (this.currentPage === 1) {
                        this.$timelineContainer.append('<div class="text-center text-muted py-4">No clinical records found for the selected date range.</div>');
                    }

                    if (response.pagination) {
                        this.hasMore = response.pagination.has_more;
                        this.$paginationContainer.toggleClass('d-none', !this.hasMore);
                    }
                },
                error: (err) => {
                    this.isLoading = false;
                    $btnIcon.addClass('fa-angle-double-down').removeClass('fa-spinner fa-spin');
                    console.error('Error fetching timeline:', err);
                }
            });
        }

        /**
         * Render a single date node on the timeline.
         * day = { date, date_formatted, categories: {cat: count}, encounters: [...] }
         */
        renderDateNode(day, hasMaternity) {
            const dateCategories = day.categories || {};
            let folders = [...this.folderDefinitions];
            if (hasMaternity) folders.push(this.maternityFolder);

            // Only include folders that have at least one category with data
            const activeFolders = folders.filter(f =>
                f.categories.some(cat => (dateCategories[cat] || 0) > 0)
            );

            if (activeFolders.length === 0) return;

            // Build encounter sub-group info
            let encounterHtml = '';
            if (day.encounters && day.encounters.length > 0) {
                const encItems = day.encounters.map(enc => {
                    const dotClass = enc.completed ? 'enc-dot-active' : 'enc-dot-pending';
                    return `<span class="enc-badge me-1 mb-1">
                        <span class="enc-dot ${dotClass}"></span>
                        <i class="fa fa-stethoscope text-primary me-1" style="font-size:0.7rem;"></i>${enc.clinic_name}
                        <span class="text-muted ms-1">${enc.started_at} — ${enc.doctor_name}</span>
                    </span>`;
                }).join('');
                encounterHtml = `<div class="mb-2">${encItems}</div>`;
            }

            // Build folder HTML — tree-style toggles
            const foldersHtml = activeFolders.map(f => {
                const activeCats = f.categories.filter(cat => (dateCategories[cat] || 0) > 0);
                if (activeCats.length === 0) return '';

                const categoryHtml = activeCats.map(cat => {
                    const config = this.categoryConfigs[cat];
                    const count = dateCategories[cat] || 0;
                    return `
                        <div class="col-md-6 col-lg-4 mb-2">
                            <div class="category-preview-card" data-date="${day.date}" data-category="${cat}">
                                <div class="category-preview-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-1">
                                        <i class="fa ${config.icon} cat-icon"></i>
                                        <span class="cat-title">${config.title}</span>
                                    </div>
                                    <span class="cat-count">${count}</span>
                                </div>
                                <div class="category-preview-body" id="preview-body-${day.date}-${cat}">
                                    <span class="text-muted"><i class="fa fa-spinner fa-spin me-1"></i></span>
                                </div>
                            </div>
                        </div>`;
                }).join('');

                return `
                    <div class="timeline-folder">
                        <div class="timeline-folder-header d-flex align-items-center justify-content-between"
                             aria-expanded="false" data-date="${day.date}" data-categories="${activeCats.join(',')}">
                            <div class="d-flex align-items-center gap-1">
                                <span class="folder-toggle-icon"></span>
                                <i class="fa folder-icon"></i>
                                <span class="folder-title">${f.title}</span>
                            </div>
                            <i class="fa fa-chevron-right text-muted folder-chevron me-2"></i>
                        </div>
                        <div class="timeline-folder-body" style="display:none;">
                            <div class="row">${categoryHtml}</div>
                        </div>
                    </div>`;
            }).join('');

            const nodeHtml = `
                <div class="timeline-node">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="timeline-date-heading"><i class="fa fa-calendar"></i> ${day.date_formatted}</div>
                        ${encounterHtml}
                        <div class="timeline-folders">${foldersHtml}</div>
                    </div>
                </div>`;
            this.$timelineContainer.append(nodeHtml);
        }

        loadCategoryPreview(dateFilter, category, $folderBody) {
            const cacheKey = `${dateFilter}_${category}`;
            if (this.categoryCache[cacheKey]) {
                this.renderPreviewCard(dateFilter, category, this.categoryCache[cacheKey]);
                return;
            }

            let url = this.encounterId
                ? `/encounters/${this.encounterId}/clinical-story?category=${category}&date_filter=${dateFilter}`
                : `/patients/${this.patientId}/clinical-story?category=${category}&date_filter=${dateFilter}`;
            if (this.encounterFilter) url += `&encounter_filter=${this.encounterFilter}`;

            $.ajax({
                url: url,
                type: 'GET',
                success: (response) => {
                    const items = response.data || response || [];
                    this.categoryCache[cacheKey] = items;
                    this.renderPreviewCard(dateFilter, category, items);
                },
                error: () => {
                    $(`#preview-body-${dateFilter}-${category}`).html('<span class="text-danger">Failed to load.</span>');
                }
            });
        }

        renderPreviewCard(dateFilter, category, data) {
            const items = Array.isArray(data) ? data : [];
            const count = items.length;
            const $body = $(`#preview-body-${dateFilter}-${category}`);

            if (count === 0) {
                $body.html('<span class="text-muted fst-italic">No records found.</span>');
                return;
            }

            const first = items[0];
            let preview = '';

            switch (category) {
                case 'vitals':
                    preview = `BP: ${first.blood_pressure || '-'}, Temp: ${first.temp || '-'}°C, SpO2: ${first.spo2 || '-'}%`;
                    break;
                case 'clinical_notes':
                    preview = first.doctor_name ? `Encounter — ${first.doctor_name}` : 'Encounter recorded.';
                    break;
                case 'nursing_notes':
                    let txt = (first.note || '').replace(/<[^>]*>?/gm, '');
                    preview = txt.length > 50 ? txt.substring(0, 50) + '...' : (txt || 'Note recorded.');
                    break;
                case 'med_admin':
                    preview = `${first.product_name || 'Drug'} (${first.dose || '-'})`;
                    break;
                case 'labs': case 'imaging': case 'procedures':
                    preview = `${first.name || 'Service'} - ${first.status || 'Pending'}`;
                    break;
                case 'prescriptions':
                    preview = `${first.name || 'Drug'} ${first.dose || ''} (Qty: ${first.qty || '-'})`;
                    break;
                default:
                    preview = `${count} record${count !== 1 ? 's' : ''} on this date`;
                    break;
            }

            $body.html(`
                <div class="text-truncate">${preview}</div>
                ${count > 1 ? `<div class="text-primary small mt-1">+ ${count - 1} more</div>` : ''}
            `);
        }

        openCategoryDetails(dateFilter, category) {
            const config = this.categoryConfigs[category];
            this.$modalTitle.html(`
                <span class="category-icon-bg ${config.colorClass} text-white shadow-sm" style="width:36px;height:36px;font-size:1rem;">
                    <i class="fa ${config.icon}"></i>
                </span>
                <span class="fw-bold">${config.title}</span>
            `);
            this.$modalBody.html('<div class="p-4 text-center text-muted"><i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i><div>Loading details...</div></div>');
            this.$modal.modal('show');

            const cacheKey = `${dateFilter}_${category}`;
            if (this.categoryCache[cacheKey]) {
                this.renderCategoryDetailed(category, this.categoryCache[cacheKey]);
            } else {
                let url = this.encounterId
                    ? `/encounters/${this.encounterId}/clinical-story?category=${category}&date_filter=${dateFilter}`
                    : `/patients/${this.patientId}/clinical-story?category=${category}&date_filter=${dateFilter}`;
                if (this.encounterFilter) url += `&encounter_filter=${this.encounterFilter}`;
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: (response) => {
                        const items = response.data || response || [];
                        this.categoryCache[cacheKey] = items;
                        this.renderCategoryDetailed(category, items);
                    },
                    error: () => {
                        this.$modalBody.html('<div class="p-4 text-danger">Failed to load details.</div>');
                    }
                });
            }
        }

        renderCategoryDetailed(category, data) {
            const items = Array.isArray(data) ? data : [];
            if (items.length === 0) {
                this.$modalBody.html('<div class="p-5 text-center text-muted">No records available.</div>');
                return;
            }

            // If items have pre-rendered info_html from backend, use it directly
            const hasHtml = items[0] && items[0].info_html;
            if (hasHtml) {
                let html = '<div class="p-3">';
                items.forEach(item => {
                    html += item.info_html;
                });
                html += '</div>';
                this.$modalBody.html(html);
                return;
            }

            // Fallback JS rendering for categories without info_html
            let html = '<div class="p-3">';
            items.forEach(item => {
                html += `<div class="card-modern mb-2" style="border-left: 4px solid #6c757d;">`;
                html += `<div class="card-body p-3">`;
                html += `<div class="small text-muted mb-2"><i class="fa fa-clock-o me-1"></i> ${item.date || 'Unknown Date'}</div>`;

                switch (category) {
                    case 'vitals':
                        html += `<div class="row g-2">
                            <div class="col-4"><strong>BP:</strong> ${item.blood_pressure || '-'} mmHg</div>
                            <div class="col-4"><strong>Temp:</strong> ${item.temp || '-'} °C</div>
                            <div class="col-4"><strong>HR:</strong> ${item.heart_rate || '-'} bpm</div>
                            <div class="col-4"><strong>SpO2:</strong> ${item.spo2 || '-'}%</div>
                            <div class="col-4"><strong>RR:</strong> ${item.resp_rate || '-'}</div>
                            <div class="col-4"><strong>Weight:</strong> ${item.weight || '-'} kg</div>
                            ${item.blood_sugar ? `<div class="col-4"><strong>BG:</strong> ${item.blood_sugar}</div>` : ''}
                            ${item.pain_score ? `<div class="col-4"><strong>Pain:</strong> ${item.pain_score}/10</div>` : ''}
                        </div>
                        ${item.other_notes ? `<div class="mt-2 p-2 bg-light rounded small">${item.other_notes}</div>` : ''}`;
                        break;
                    case 'nursing_notes':
                        html += `<div class="fw-bold text-primary mb-1">${item.type || 'Note'}</div>
                            <div class="p-2 bg-light rounded">${item.note || ''}</div>`;
                        break;
                    case 'med_admin':
                        html += `<div class="fw-bold">${item.product_name || 'Drug'}</div>
                            <div class="mt-1"><i class="mdi mdi-pill"></i> Dose: ${item.dose || '-'} | Route: ${item.route || '-'} | Qty: ${item.qty || '-'}</div>
                            ${item.comment ? `<div class="mt-1 small text-muted">${item.comment}</div>` : ''}`;
                        break;
                    case 'injections':
                        html += `<div class="d-flex justify-content-between"><strong>${item.product_name || 'Injectable'}</strong>
                            <span class="badge bg-info">${item.type || 'Injection'}</span></div>
                            <div class="mt-1"><i class="mdi mdi-pill"></i> ${item.dose || '-'} | Route: ${item.route || '-'} | Site: ${item.site || '-'}</div>
                            ${item.batch_number ? `<div class="small text-muted">Batch: ${item.batch_number}</div>` : ''}
                            ${item.notes ? `<div class="mt-1 p-2 bg-light rounded small">${item.notes}</div>` : ''}`;
                        break;
                    case 'care_plans':
                        html += `<div class="fw-bold">${item.category || 'Order'}</div>
                            <div class="mt-1">${item.instructions || ''}</div>
                            <div class="small text-muted mt-1">For: ${item.target_executor || '-'} | ${item.frequency || ''} | ${item.duration || ''}</div>
                            <span class="badge ${item.status === 'completed' ? 'bg-success' : 'bg-warning text-dark'} mt-1">${item.status || 'Active'}</span>`;
                        break;
                    case 'procedures':
                        html += `<div class="d-flex justify-content-between"><strong>${item.name || 'Procedure'}</strong>
                            <span class="badge bg-info">${item.status || 'Pending'}</span></div>
                            ${item.priority ? `<div class="small mt-1">Priority: <span class="badge bg-secondary">${item.priority}</span></div>` : ''}
                            ${item.pre_notes ? `<div class="mt-2"><small class="fw-bold">Pre-Op Notes:</small><div class="p-2 bg-light rounded small">${item.pre_notes}</div></div>` : ''}
                            ${item.post_notes ? `<div class="mt-2"><small class="fw-bold">Post-Op Notes:</small><div class="p-2 bg-light rounded small">${item.post_notes}</div></div>` : ''}`;
                        break;
                    case 'admissions':
                        html += `<div class="d-flex justify-content-between"><strong>${item.admission_reason || 'Admission'}</strong>
                            <span class="badge bg-info">${item.status_label || item.admission_status || 'Admitted'}</span></div>
                            <div class="small mt-1">${item.ward_bed || ''}</div>
                            ${item.chief_complaint ? `<div class="mt-1 small">${item.chief_complaint}</div>` : ''}
                            ${item.days_admitted ? `<div class="small text-muted mt-1">Duration: ${item.days_admitted} days</div>` : ''}`;
                        break;
                    case 'referrals':
                        html += `<div class="fw-bold">${item.target || 'Referral'}</div>
                            <div class="mt-1"><span class="badge bg-info">${item.referral_type || ''}</span>
                            ${item.urgency ? `<span class="badge bg-warning text-dark ms-1">${item.urgency}</span>` : ''}</div>
                            ${item.reason ? `<div class="mt-2 p-2 bg-light rounded small">${item.reason}</div>` : ''}`;
                        break;
                    case 'intake_output':
                        html += `<div class="fw-bold">${item.type || 'I/O Period'}</div>
                            <div class="small text-muted">${item.started_at || ''} → ${item.ended_at || 'Ongoing'}</div>`;
                        if (item.records && item.records.length > 0) {
                            html += '<table class="table table-sm table-bordered mt-2" style="font-size:0.8rem;"><thead><tr><th>Type</th><th>Amount</th><th>Description</th><th>Time</th></tr></thead><tbody>';
                            item.records.forEach(r => {
                                html += `<tr><td>${r.type}</td><td>${r.amount}</td><td>${r.description || '-'}</td><td>${r.recorded_at || '-'}</td></tr>`;
                            });
                            html += '</tbody></table>';
                        }
                        break;
                    default:
                        html += `<div class="pre-wrap small">${JSON.stringify(item, (k, v) => (k === 'raw_object' || k === 'timestamp' || k === 'info_html') ? undefined : v, 2).replace(/[{}"]/g, '')}</div>`;
                        break;
                }

                const author = item.doctor_name || item.taken_by_name || item.administered_by || item.nurse_name || item.created_by;
                if (author) html += `<div class="mt-2 text-end small text-muted border-top pt-2">By: ${author}</div>`;
                html += `</div></div>`;
            });

            html += '</div>';
            this.$modalBody.html(html);
        }
    }

    $(document).ready(function () {
        $('.clinical-story-wrapper').each(function () {
            const $wrapper = $(this);
            if (!$wrapper.data('clinicalStory')) {
                $wrapper.data('clinicalStory', new ClinicalStory(this));
            }
        });
    });

}(window, document, jQuery));
