{{-- Modals Partial — Encounter Intelligence Workbench --}}

{{-- ══ Encounter Detail Modal ═══════════════════════════════════════════════ --}}
<div class="modal fade" id="ewb-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4"
                style="background: linear-gradient(135deg, var(--hospital-primary, #011b33) 0%, #1e40af 100%); border-bottom: 3px solid #3b82f6;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="ewb-detail-modal-title">
                    <i class="mdi mdi-stethoscope me-2"></i> Encounter Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="ewb-detail-modal-body" style="background:#f8fafc; min-height: 300px;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 small">Loading encounter details...</p>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ Encounters List Modal (Drilldown) ═════════════════════════════════════ --}}
<div class="modal fade" id="ewb-list-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4"
                style="background: linear-gradient(135deg, var(--hospital-primary, #011b33) 0%, #1e40af 100%); border-bottom: 3px solid #3b82f6;">
                <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="ewb-list-modal-title">
                    <i class="mdi mdi-format-list-bulleted me-2"></i> <span id="ewb-list-modal-title-text" class="ms-1">Encounters</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background:#f8fafc; min-height: 300px;">
                <div class="bg-white p-3 border-bottom d-flex gap-3 align-items-end flex-wrap" id="ewb-list-modal-filters">
                    <div>
                        <label class="small text-muted mb-0">Date Range (Fixed)</label>
                        <div class="fw-bold" id="ewb-list-modal-date-display" style="font-size:0.85rem"></div>
                    </div>
                    <div>
                        <label class="small text-muted mb-0">Status</label>
                        <select class="form-select form-select-sm" id="ewb-lm-status">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="small text-muted mb-0">HMO / Payer</label>
                        <select class="form-select form-select-sm" id="ewb-lm-hmo">
                            <option value="">All HMOs</option>
                            @foreach($hmos as $h)
                                <option value="{{ $h->id }}">{{ $h->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2 align-items-center mb-1">
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input ewb-lm-tag" type="checkbox" id="ewb-lmt-lab" value="has_lab">
                            <label class="form-check-label small" for="ewb-lmt-lab">Lab</label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input ewb-lm-tag" type="checkbox" id="ewb-lmt-img" value="has_imaging">
                            <label class="form-check-label small" for="ewb-lmt-img">Img</label>
                        </div>
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input ewb-lm-tag" type="checkbox" id="ewb-lmt-rx" value="has_prescription">
                            <label class="form-check-label small" for="ewb-lmt-rx">Rx</label>
                        </div>
                    </div>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-sm table-hover w-100 m-0" id="ewb-table-list-modal">
                        <thead class="bg-light">
                            <tr>
                                <th>Patient</th><th>HMO</th><th>Clinic</th><th>Date</th><th>Tags</th><th>Status</th><th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══ Consultation Log Book Patient Drilldown Modal ═══════════════════════════ --}}
<div class="modal fade" id="ewb-logbook-drilldown-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header text-white py-3 px-4"
                style="background: linear-gradient(135deg, var(--hospital-primary, #011b33) 0%, #1e40af 100%); border-bottom: 3px solid #10b981;">
                <div>
                    <h5 class="modal-title font-weight-bold text-white d-flex align-items-center" id="ewb-lb-modal-title">
                        <i class="mdi mdi-book-open-page-variant me-2"></i> <span id="ewb-lb-modal-doctor-name">Clinician Log Book</span>
                    </h5>
                    <div class="small text-white-50 mt-1 d-flex gap-3 flex-wrap">
                        <span><i class="mdi mdi-hospital-building me-1"></i> <strong class="text-white" id="ewb-lb-modal-clinic-name">—</strong></span>
                        <span><i class="mdi mdi-calendar-range me-1"></i> <span id="ewb-lb-modal-period">—</span></span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background:#f8fafc; min-height: 350px;">
                {{-- KPI strip --}}
                <div class="p-3 bg-white border-bottom">
                    <div class="row g-2 text-center" id="ewb-lb-modal-kpis">
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Visits Seen</small>
                                <span class="h6 font-weight-bold mb-0 text-primary" id="ewb-lb-kpi-visits">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Clinical Notes</small>
                                <span class="h6 font-weight-bold mb-0 text-dark" id="ewb-lb-kpi-notes">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Unique Patients</small>
                                <span class="h6 font-weight-bold mb-0 text-secondary" id="ewb-lb-kpi-patients">0</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Consult Paid</small>
                                <span class="h6 font-weight-bold mb-0 text-success" id="ewb-lb-kpi-paid">₦0.00</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">HMO Claims</small>
                                <span class="h6 font-weight-bold mb-0 text-info" id="ewb-lb-kpi-claims">₦0.00</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="p-2 border rounded bg-light">
                                <small class="text-muted d-block">Total Value</small>
                                <span class="h6 font-weight-bold mb-0 text-primary" id="ewb-lb-kpi-total">₦0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light border py-1 px-3 mt-2 mb-0 small text-muted d-flex align-items-center gap-2">
                        <i class="mdi mdi-information-outline text-primary fs-5"></i>
                        <span><strong>Audit Transparency:</strong> Financial funds are counted strictly once per consultation visit. Any subsequent clinical review/follow-up notes on the same visit are indicated as <em>Follow-up Note</em> with ₦0.00 to guarantee zero fund inflation.</span>
                    </div>
                </div>

                {{-- Patients Table --}}
                <div class="table-responsive p-3">
                    <table class="table table-sm table-hover w-100 m-0" id="ewb-table-logbook-drilldown">
                        <thead class="bg-light">
                            <tr>
                                <th>Date &amp; Patient</th>
                                <th>Payer &amp; Intake Type</th>
                                <th class="text-end">Consult Financials &amp; Settlement</th>
                                <th class="text-center" style="width: 130px;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 d-flex justify-content-between">
                <small class="text-muted"><i class="mdi mdi-shield-check text-success"></i> Audit-compliant consultation ledger</small>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@include('admin.partials.patient-form-modal')
@include('admin.partials.hospital_contacts_modal')
@include('admin.partials.price_list_modal')
