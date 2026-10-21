@include('admin.shared._my_transactions_modal')
@if($canBillUser)
    @include('admin.partials.payment_modal')
@endif

<!-- Receipt Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-receipt"></i> Receipt Preview</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="receipt-modal-tabs">
                    <button class="receipt-modal-tab active" data-format="a4">
                        <i class="mdi mdi-file-document"></i> A4 Receipt
                    </button>
                    <button class="receipt-modal-tab" data-format="thermal">
                        <i class="mdi mdi-receipt"></i> Thermal Receipt
                    </button>
                </div>
                <div class="receipt-modal-content">
                    <div class="receipt-modal-pane active" id="modal-receipt-a4"></div>
                    <div class="receipt-modal-pane" id="modal-receipt-thermal" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="modal-print-a4">
                    <i class="mdi mdi-printer"></i> Print A4
                </button>
                <button type="button" class="btn btn-info" id="modal-print-thermal">
                    <i class="mdi mdi-printer"></i> Print Thermal
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
            </div>
        </div>
    </div>
</div>

<!-- Patient Form Modal (Shared Partial) -->
@include('admin.partials.patient-form-modal')


<!-- Quick Register Modal (Legacy - kept for compatibility) -->
<div class="modal fade" id="quickRegisterModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="mdi mdi-account-plus"></i> Register Walk-in Patient</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <form id="quick-register-form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <div class="file-no-label-row">
                                    <label class="form-label mb-0">File Number <span class="text-danger">*</span></label>
                                    <span class="file-no-next-badge" id="qr-file-no-hint" title="Next auto-generated number">
                                        Next: <strong id="qr-next-file-no">--</strong>
                                    </span>
                                </div>
                                <div class="file-no-btn-group">
                                    <button type="button" class="file-no-mode-btn active" data-mode="auto" id="qr-mode-auto">
                                        <i class="mdi mdi-autorenew"></i> Auto
                                    </button>
                                    <button type="button" class="file-no-mode-btn" data-mode="manual" id="qr-mode-manual">
                                        <i class="mdi mdi-pencil"></i> Manual
                                    </button>
                                    <button type="button" class="file-no-mode-btn" id="qr-file-no-refresh" title="Regenerate (Ctrl+G)" style="margin-left: auto;">
                                        <i class="mdi mdi-refresh"></i>
                                    </button>
                                </div>
                                <input type="text" class="form-control file-no-input" id="quick-register-file-no" readonly placeholder="Auto-generated (e.g. EX-0001)">
                                <!-- Info panel showing format and recent numbers -->
                                <div class="file-no-info-panel" id="qr-file-no-info">
                                    <div class="format-display">
                                        <span class="text-muted">Format:</span>
                                        <span class="format-pattern" id="qr-format-pattern">--</span>
                                    </div>
                                    <div class="text-muted">Recent: <span id="qr-recent-label">click to copy</span></div>
                                    <div class="file-no-recent-list" id="qr-recent-file-nos"></div>
                                </div>
                                <!-- Duplicate warning (hidden by default) -->
                                <div class="file-no-duplicate-warning" id="qr-duplicate-warning" style="display: none;">
                                    <div class="warning-title"><i class="mdi mdi-alert"></i> File number already in use</div>
                                    <div id="qr-duplicate-patients"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" class="form-control" id="quick-register-phone" placeholder="08012345678">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-firstname" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-register-lastname" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Gender <span class="text-danger">*</span></label>
                                <select class="form-control" id="quick-register-gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0">Age / DOB <span class="text-danger">*</span></label>
                                    <span class="qr-age-dob-toggle">
                                        <button type="button" class="qr-adt-btn active" data-mode="age">Age</button>
                                        <button type="button" class="qr-adt-btn" data-mode="age_group">Group</button>
                                        <button type="button" class="qr-adt-btn" data-mode="dob">DOB</button>
                                    </span>
                                </div>
                                <div class="qr-age-dob-wrapper mode-age">
                                    {{-- Age mode (default for quick walk-in) --}}
                                    <div class="qr-age-panel">
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="quick-register-age-val" min="0" max="130" placeholder="Age" inputmode="numeric">
                                            <select class="form-select form-control" id="quick-register-age-unit" style="max-width: 80px;">
                                                <option value="years" selected>yrs</option>
                                                <option value="months">mos</option>
                                                <option value="days">days</option>
                                            </select>
                                        </div>
                                        <div class="qr-age-hint text-muted mt-1" id="quick-register-age-hint" style="font-size: 0.75rem;"></div>
                                    </div>
                                    {{-- Age Group mode --}}
                                    <div class="qr-age-group-panel" style="display: none;">
                                        <select class="form-select form-control" id="quick-register-age-group">
                                            <option value="">Select Age Group</option>
                                            <option value="neonate" data-days="14" data-years="0">Neonate (0 - 28 days)</option>
                                            <option value="infant" data-months="6" data-years="0">Infant (1 - 11 months)</option>
                                            <option value="toddler" data-years="3">Toddler (1 - 4 years)</option>
                                            <option value="child" data-years="8">Child (5 - 11 years)</option>
                                            <option value="adolescent" data-years="15">Adolescent (12 - 17 years)</option>
                                            <option value="young_adult" data-years="25">Young Adult (18 - 35 years)</option>
                                            <option value="adult" data-years="45">Adult (36 - 55 years)</option>
                                            <option value="older_adult" data-years="60">Older Adult (56 - 64 years)</option>
                                            <option value="elderly" data-years="70">Elderly (65+ years)</option>
                                        </select>
                                        <div class="qr-age-hint text-muted mt-1" id="quick-register-age-group-hint" style="font-size: 0.75rem;"></div>
                                    </div>
                                    {{-- DOB mode --}}
                                    <div class="qr-dob-panel" style="display: none;">
                                        <input type="date" class="form-control" id="quick-register-dob">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>HMO</label>
                                <select class="form-control" id="quick-register-hmo">
                                    <option value="">No HMO (Private)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="hmo-no-row" style="display: none;">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>HMO Number</label>
                                <input type="text" class="form-control" id="quick-register-hmo-no" placeholder="Enter HMO enrollment number">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0"><i class="mdi mdi-account-arrow-right text-success me-1"></i> Referred From</label>
                                    <span class="text-muted" style="font-size: 0.72rem;">Who referred the patient to us?</span>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="mdi mdi-account-switch-outline"></i></span>
                                    <input type="text" class="form-control" id="quick-register-referred-from" list="qr-referred-from-suggestions" placeholder="e.g. Dr. Okon, St. Luke Clinic, Self / Walk-in, Family / Friend, Outreach...">
                                </div>
                                <datalist id="qr-referred-from-suggestions">
                                    <option value="Self / Walk-in">
                                    <option value="Family / Friend">
                                    <option value="Doctor / Physician">
                                    <option value="Hospital / Clinic">
                                    <option value="Primary Health Centre (PHC)">
                                    <option value="Community Outreach">
                                    <option value="Company / Corporate Partner">
                                    <option value="HMO Partner">
                                    <option value="Church / Religious Org">
                                    <option value="School / Institution">
                                </datalist>
                                <div class="d-flex flex-wrap gap-1 mt-1 qr-referral-chips">
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Self / Walk-in" style="cursor: pointer;">Self</span>
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Family / Friend" style="cursor: pointer;">Family/Friend</span>
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Dr. " style="cursor: pointer;">Doctor</span>
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Hospital / Clinic: " style="cursor: pointer;">Hospital/Clinic</span>
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Community Outreach" style="cursor: pointer;">Outreach</span>
                                    <span class="badge bg-light text-secondary border qr-ref-chip" role="button" data-val="Company / Corporate" style="cursor: pointer;">Corporate</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-account-plus"></i> Register Patient
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.shared.modals.request_details')
@include('admin.shared.modals.discard_request_modal')

<!-- Hospital Patient Card Modal -->
<div class="modal fade" id="hospitalCardModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: {{ appsettings()->hos_color ?? '#0066cc' }}; color: white;">
                <h5 class="modal-title"><i class="mdi mdi-card-account-details"></i> Hospital Patient Card</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Card View Tabs -->
                <ul class="nav nav-pills nav-fill mb-3 justify-content-center" id="cardViewTabs" style="max-width: 340px; margin: 0 auto;">
                    <li class="nav-item">
                        <a class="nav-link active" data-card-tab="front" href="#">Front</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-card-tab="back" href="#">Back</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-card-tab="combined" href="#">Combined</a>
                    </li>
                </ul>

                <!-- Card Preview Container -->
                <div id="hospital-card-container" style="display: inline-block;">
                    <!-- FRONT SIDE -->
                    <div class="hospital-card hospital-card-front" id="hospital-card-preview">
                        <!-- Card Header with Hospital Info -->
                        <div class="card-header-section">
                            <div class="hospital-logo-section">
                                @if(appsettings()->logo)
                                    <img src="data:image/jpeg;base64,{{ appsettings()->logo }}" alt="Hospital Logo" class="hospital-logo">
                                @else
                                    <div class="hospital-logo-placeholder">
                                        <i class="mdi mdi-hospital-building"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="hospital-info-section">
                                <div class="hospital-name-text">{{ appsettings()->site_name ?? 'Hospital Name' }}</div>
                                <div class="hospital-address-text">{{ appsettings()->contact_address ?? '' }}</div>
                                <div class="hospital-phone-text">{{ appsettings()->contact_phones ?? '' }}</div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body-section">
                            <div class="patient-photo-section">
                                <img src="" alt="Patient Photo" id="card-patient-photo" class="patient-photo">
                            </div>
                            <div class="patient-info-section">
                                <div class="patient-name-text" id="card-patient-name">Jane Doe</div>
                                <div class="patient-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Patient ID</span>
                                        <span class="detail-value" id="card-patient-id">JD123456</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">DOB</span>
                                        <span class="detail-value" id="card-dob">01/01/1970</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Blood</span>
                                        <span class="detail-value" id="card-blood-type">O+</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Gender</span>
                                        <span class="detail-value" id="card-gender">Female</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Barcode Section -->
                        <div class="card-barcode-section">
                            <svg id="card-barcode"></svg>
                            <div class="barcode-number" id="card-barcode-number"></div>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer-section">
                            <span class="card-type-badge">PATIENT CARD</span>
                            <span class="powered-by">CoreHealth by corestream.ng</span>
                        </div>
                    </div>

                    <!-- BACK SIDE -->
                    <div class="hospital-card hospital-card-back" id="hospital-card-back" style="margin-top: 15px;">
                        <!-- Back Header -->
                        <div class="card-back-header">
                            <div class="back-title">PATIENT INFORMATION</div>
                        </div>

                        <!-- Back Body -->
                        <div class="card-back-body">
                            <div class="back-info-row">
                                <span class="back-label">Genotype:</span>
                                <span class="back-value" id="card-genotype">AA</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-phone">08012345678</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Address:</span>
                                <span class="back-value" id="card-address">123 Main Street, Lagos</span>
                            </div>
                            <div class="back-info-row full-width">
                                <span class="back-label">Allergies:</span>
                                <span class="back-value" id="card-allergies">None known</span>
                            </div>
                            <div class="back-divider"></div>
                            <div class="back-section-title">Emergency Contact</div>
                            <div class="back-info-row">
                                <span class="back-label">Name:</span>
                                <span class="back-value" id="card-nok-name">John Doe</span>
                            </div>
                            <div class="back-info-row">
                                <span class="back-label">Phone:</span>
                                <span class="back-value" id="card-nok-phone">08098765432</span>
                            </div>
                        </div>

                        <!-- Back Footer -->
                        <div class="card-back-footer">
                            <div class="emergency-note">In case of emergency, please contact the hospital</div>
                            <div class="powered-by-back">CoreHealth by corestream.ng</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-print-card-now">
                    <i class="mdi mdi-printer"></i> Print Card
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Hospital Card Styles - Front */
.hospital-card {
    width: 340px;
    height: 215px;
    background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%);
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    position: relative;
    display: flex;
    flex-direction: column;
}

.hospital-card .card-header-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 8px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo-section {
    width: 35px;
    height: 35px;
    flex-shrink: 0;
}

.hospital-card .hospital-logo {
    width: 35px;
    height: 35px;
    object-fit: contain;
    background: white;
    border-radius: 4px;
    padding: 2px;
}

.hospital-card .hospital-logo-placeholder {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hospital-card .hospital-logo-placeholder i {
    font-size: 20px;
}

.hospital-card .hospital-info-section {
    flex: 1;
    line-height: 1.2;
}

.hospital-card .hospital-name-text {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.hospital-card .hospital-address-text {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card .hospital-phone-text {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card .card-body-section {
    display: flex;
    padding: 8px 10px;
    gap: 10px;
    flex: 1;
    min-height: 0;
}

.hospital-card .patient-photo-section {
    flex-shrink: 0;
}

.hospital-card .patient-photo {
    width: 60px;
    height: 75px;
    object-fit: cover;
    border-radius: 6px;
    border: 2px solid {{ appsettings()->hos_color ?? '#0066cc' }};
    background: #f0f0f0;
}

.hospital-card .patient-info-section {
    flex: 1;
    text-align: left;
    overflow: visible;
}

.hospital-card .patient-name-text {
    font-size: 14px;
    font-weight: 800;
    color: #222;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    border-bottom: 1px solid #ddd;
    padding-bottom: 2px;
}

.hospital-card .patient-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 8px;
}

.hospital-card .detail-item {
    display: flex;
    flex-direction: column;
}

.hospital-card .detail-label {
    font-size: 8px;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
}

.hospital-card .detail-value {
    font-size: 11px;
    font-weight: 700;
    color: #222;
    white-space: nowrap;
    overflow: visible;
}

.hospital-card .card-barcode-section {
    padding: 2px 10px;
    text-align: center;
    flex-shrink: 0;
    background: #fff;
}

.hospital-card .card-barcode-section svg {
    height: 20px;
    width: auto;
    max-width: 100%;
}

.hospital-card .barcode-number {
    font-size: 9px;
    font-family: 'Courier New', monospace;
    font-weight: 700;
    color: #222;
    letter-spacing: 1px;
}

.hospital-card .card-footer-section {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 3px 10px;
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.hospital-card .card-type-badge {
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hospital-card .powered-by {
    font-size: 7px;
    font-weight: 600;
    opacity: 0.8;
}

/* Hospital Card Styles - Back */
.hospital-card-back {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.hospital-card-back .card-back-header {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 6px 10px;
    text-align: center;
}

.hospital-card-back .back-title {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1px;
}

.hospital-card-back .card-back-body {
    padding: 8px 10px;
    flex: 1;
}

.hospital-card-back .back-info-row {
    display: flex;
    gap: 5px;
    margin-bottom: 4px;
    font-size: 10px;
}

.hospital-card-back .back-info-row.full-width {
    flex-direction: column;
    gap: 1px;
}

.hospital-card-back .back-label {
    font-weight: 700;
    color: #444;
    min-width: 50px;
}

.hospital-card-back .back-value {
    color: #333;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hospital-card-back .back-info-row.full-width .back-value {
    white-space: normal;
    font-size: 9px;
    font-weight: 600;
    line-height: 1.3;
}

.hospital-card-back .back-divider {
    border-top: 1px dashed #ccc;
    margin: 6px 0;
}

.hospital-card-back .back-section-title {
    font-size: 10px;
    font-weight: 800;
    color: {{ appsettings()->hos_color ?? '#0066cc' }};
    margin-bottom: 4px;
    text-transform: uppercase;
}

.hospital-card-back .card-back-footer {
    background: {{ appsettings()->hos_color ?? '#0066cc' }};
    color: white;
    padding: 4px 10px;
    text-align: center;
}

.hospital-card-back .emergency-note {
    font-size: 8px;
    font-weight: 600;
    opacity: 0.9;
}

.hospital-card-back .powered-by-back {
    font-size: 7px;
    font-weight: 600;
    opacity: 0.7;
    margin-top: 2px;
}

/* Print Styles for Hospital Card */
@media print {
    body * {
        visibility: hidden;
    }

    #hospital-card-container, #hospital-card-container * {
        visibility: visible;
    }

    #hospital-card-container {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
    }

    .hospital-card {
        box-shadow: none;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .hospital-card-back {
        page-break-before: always;
        margin-top: 20px;
    }
}
</style>

{{-- Medical Report History Modal --}}
@include('admin.partials.medical_report_history_modal')

{{-- Emergency Intake Modal --}}
@include('admin.partials.emergency-intake-modal')

{{-- Reschedule Appointment Modal --}}
<div class="modal fade" id="rescheduleAppointmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-calendar-edit"></i> Reschedule Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reschedule-form">
                <div class="modal-body">
                    <input type="hidden" id="reschedule-appt-id">
                    <div class="alert alert-light border mb-3">
                        <div class="d-flex justify-content-between">
                            <span><strong>Patient:</strong> <span id="reschedule-patient-name"></span></span>
                            <span class="badge bg-secondary" id="reschedule-count-info"></span>
                        </div>
                        <div class="text-muted small mt-1">Original: <span id="reschedule-original-date"></span></div>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-calendar"></i> New Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reschedule-date" required min="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-clock-outline"></i> Time Slot <span class="text-danger">*</span></label>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="reschedule-custom-time-toggle">
                                <label class="form-check-label small text-muted" for="reschedule-custom-time-toggle">Custom time</label>
                            </div>
                        </div>
                        <select class="form-control" id="reschedule-time">
                            <option value="">-- Select date first --</option>
                        </select>
                        <input type="time" class="form-control d-none" id="reschedule-custom-time-input" placeholder="HH:MM">
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-hospital-building"></i> Clinic</label>
                        <select class="form-control" id="reschedule-clinic">
                            @foreach(\App\Models\Clinic::orderBy('name')->get() as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-doctor"></i> Doctor</label>
                        <select class="form-control" id="reschedule-doctor">
                            <option value="">Same Doctor</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-note-text"></i> Reason</label>
                        <select class="form-control" id="reschedule-reason">
                            <option value="">-- Select reason --</option>
                            <option value="Patient requested">Patient requested</option>
                            <option value="Doctor schedule change">Doctor schedule change</option>
                            <option value="Emergency rescheduling">Emergency rescheduling</option>
                            <option value="Clinic unavailable">Clinic unavailable</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm"><i class="mdi mdi-calendar-edit"></i> Reschedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reassign Doctor Modal --}}
<div class="modal fade" id="reassignDoctorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#6f42c1; color:#fff;">
                <h5 class="modal-title"><i class="mdi mdi-account-switch"></i> Reassign Doctor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reassign-form">
                <div class="modal-body">
                    <input type="hidden" id="reassign-appt-id">
                    <div class="alert alert-light border mb-3">
                        <strong>Patient:</strong> <span id="reassign-patient-name"></span><br>
                        <span class="text-muted small">Current Doctor: <strong id="reassign-current-doctor"></strong></span>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-doctor"></i> New Doctor <span class="text-danger">*</span></label>
                        <select class="form-control" id="reassign-doctor" required>
                            <option value="">-- Select Doctor --</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label><i class="mdi mdi-note-text"></i> Reason</label>
                        <select class="form-control" id="reassign-reason">
                            <option value="">-- Select reason --</option>
                            <option value="Doctor on leave">Doctor on leave</option>
                            <option value="Doctor unavailable">Doctor unavailable</option>
                            <option value="Patient request">Patient request</option>
                            <option value="Schedule conflict">Schedule conflict</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#6f42c1; color:#fff;"><i class="mdi mdi-account-switch"></i> Reassign</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Appointment Chain/History Modal --}}
<div class="modal fade" id="appointmentChainModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="mdi mdi-link-variant"></i> Appointment History Chain</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="chain-body">
                <div class="text-center py-4"><i class="mdi mdi-loading mdi-spin mdi-36px"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.appointment-chain-timeline { position: relative; padding-left: 30px; }
.chain-item { position: relative; padding: 10px 0 10px 20px; border-left: 2px solid #dee2e6; margin-left: 10px; }
.chain-item:last-child { border-left-color: transparent; }
.chain-item-active { background: #f0f7ff; border-radius: 8px; margin-right: -10px; padding-right: 10px; }
.chain-marker { position: absolute; left: -12px; top: 10px; }
.chain-dot { display: inline-flex; width: 22px; height: 22px; border-radius: 50%; background: #6c757d; color: #fff; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; }
.chain-dot.active { background: #0d6efd; }
.badge-sm { font-size: 0.7em; }
.btn-purple { background: #6f42c1; color: #fff; border-color: #6f42c1; }
.btn-purple:hover { background: #5a32a3; color: #fff; }
</style>

{{-- ═══════════════════════════════════════════════════════════════════ --}}
{{-- Referral Action Modals                                            --}}
{{-- ═══════════════════════════════════════════════════════════════════ --}}

{{-- Book Referral Appointment Modal --}}
<div class="modal fade" id="bookReferralModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title"><i class="mdi mdi-calendar-plus me-1"></i> Book Referral Appointment</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="book-ref-id">
                <input type="hidden" id="book-ref-clinic">
                <input type="hidden" id="book-ref-doctor">
                <input type="hidden" id="book-ref-patient">
                <div class="alert alert-info py-2 mb-3">
                    <i class="mdi mdi-information-outline me-1"></i>
                    Schedule an appointment for this internal referral. The patient will be added to the target clinic's queue.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-calendar me-1"></i> Appointment Date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="book-ref-date" min="{{ date('Y-m-d') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-clock-outline me-1"></i> Start Time <span class="text-danger">*</span></label>
                    <input type="time" class="form-control" id="book-ref-time" value="09:00">
                </div>
                <div class="alert alert-light border py-2 mb-3" id="book-ref-target-info" style="display:none;">
                    <small class="text-muted">Referring doctor requested:</small><br>
                    <strong id="book-ref-target-text"></strong>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><i class="mdi mdi-hospital-building me-1"></i> Clinic</label>
                        <select class="form-select" id="book-ref-clinic-override">
                            <option value="">Use referral's target clinic</option>
                            @foreach(\App\Models\Clinic::orderBy('name')->get() as $clinic)
                                <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Override only if needed</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><i class="mdi mdi-doctor me-1"></i> Doctor</label>
                        <select class="form-select" id="book-ref-doctor-override">
                            <option value="">Use referral's target doctor</option>
                            @foreach(\App\Models\Staff::whereHas('user', function($q) { $q->whereHas('roles', fn($r) => $r->where('name', 'DOCTOR')); })->with('user:id,surname,firstname,othername')->orderBy('id')->get() as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->user ? trim(($staff->user->surname ?? '').' '.($staff->user->firstname ?? '').' '.($staff->user->othername ?? '')) : 'Staff #'.$staff->id }}</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Override only if needed</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="confirm-book-referral">
                    <i class="mdi mdi-calendar-check me-1"></i> Confirm Booking
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Refer Out Modal --}}
<div class="modal fade" id="referOutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h6 class="modal-title"><i class="mdi mdi-arrow-right-bold me-1"></i> Mark as Referred Out</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refer-out-ref-id">
                <div class="alert alert-warning py-2 mb-3">
                    <i class="mdi mdi-alert-outline me-1"></i>
                    This confirms the patient's referral letter has been given and they have been directed to the external facility. You can print the referral letter after this action.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-note-text me-1"></i> Action Notes</label>
                    <textarea class="form-control" id="refer-out-notes" rows="3" placeholder="e.g., Letter given to patient, appointment confirmed with facility..."></textarea>
                    <small class="form-text text-muted">Optional — document handover details for record keeping</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info btn-sm" id="confirm-refer-out">
                    <i class="mdi mdi-check me-1"></i> Confirm Referred Out
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Decline Referral Modal --}}
<div class="modal fade" id="declineReferralModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h6 class="modal-title"><i class="mdi mdi-close-circle me-1"></i> Decline Referral</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="decline-ref-id">
                <div class="alert alert-danger py-2 mb-3">
                    <i class="mdi mdi-alert me-1"></i>
                    Declining will notify the referring doctor. Please provide a clear reason so they can take alternative action for the patient.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="mdi mdi-text-box-outline me-1"></i> Reason for Decline <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="decline-reason" rows="3" placeholder="e.g., Specialist unavailable this week, clinic fully booked..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="confirm-decline-referral">
                    <i class="mdi mdi-close-circle me-1"></i> Decline Referral
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Referral Detail / Print Modal --}}
<div class="modal fade" id="referralDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h6 class="modal-title"><i class="mdi mdi-file-document-outline me-1"></i> Referral Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="referral-detail-loading" class="text-center py-4">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                </div>
                <div id="referral-detail-content" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-dark btn-sm" id="btn-print-referral-letter" style="display:none;">
                    <i class="mdi mdi-printer me-1"></i> Print Referral Letter
                </button>
            </div>
        </div>
    </div>
</div>
