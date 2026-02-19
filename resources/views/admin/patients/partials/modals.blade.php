<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('service-save-result') }}" method="post" enctype="multipart/form-data" onsubmit="copyResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- V1 Template: WYSIWYG Editor -->
                    <div id="v1_template_container">
                        <textarea id="invest_res_template_editor" class="ckeditor"></textarea>
                    </div>

                    <!-- V2 Template: Structured Form -->
                    <div id="v2_template_container" style="display: none;">
                        <div id="v2_form_fields"></div>
                    </div>

                    <input type="hidden" id="invest_res_entry_id" name="invest_res_entry_id">
                    <input type="hidden" name="invest_res_template_submited" id="invest_res_template_submited">
                    <input type="hidden" id="invest_res_template_version" name="invest_res_template_version" value="1">
                    <input type="hidden" id="invest_res_template_data" name="invest_res_template_data">
                    <input type="hidden" id="invest_res_is_edit" name="invest_res_is_edit" value="0">
                    <input type="hidden" id="deleted_attachments" name="deleted_attachments" value="[]">

                    <hr>

                    <!-- Existing Attachments -->
                    <div id="existing_attachments_container" style="display: none;">
                        <label><i class="mdi mdi-paperclip"></i> Existing Attachments</label>
                        <div id="existing_attachments_list" class="mb-3"></div>
                    </div>

                    <!-- New File Upload -->
                    <div class="form-group">
                        <label for="result_attachments"><i class="mdi mdi-file-plus"></i> Add New Files (Optional)</label>
                        <input type="file" class="form-control" id="result_attachments" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">You can attach multiple files (PDF, Images, Word documents). Max 10MB per file.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="invest_res_submit_btn"
                        class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog" aria-labelledby="investResViewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            @php
                $sett = appsettings();
                $hosColor = $sett->hos_color ?? '#0066cc';
            @endphp
            <style>
                #resultViewTable {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    width: 100%;
                    max-width: 100%;
                }
                .result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 3px solid {{ $hosColor }};
                }
                .result-header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .result-logo {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                }
                .result-hospital-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    text-transform: uppercase;
                }
                .result-header-right {
                    text-align: right;
                    font-size: 13px;
                    color: #666;
                    line-height: 1.6;
                }
                .result-title-section {
                    background: {{ $hosColor }};
                    color: white;
                    text-align: center;
                    padding: 15px;
                    font-size: 28px;
                    font-weight: bold;
                    letter-spacing: 6px;
                }
                .result-patient-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    padding: 20px;
                    background: #f8f9fa;
                }
                .result-info-box {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                .result-info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .result-info-row:last-child {
                    border-bottom: none;
                }
                .result-info-label {
                    font-weight: 600;
                    color: #333;
                    min-width: 120px;
                }
                .result-info-value {
                    color: #666;
                    flex: 1;
                }
                .result-section {
                    padding: 20px;
                }
                .result-section-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid {{ $hosColor }};
                }
                .result-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse;
                    margin-top: 15px;
                    table-layout: fixed;
                }
                .result-table thead {
                    background: {{ $hosColor }};
                    color: white;
                }
                .result-table th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                }
                .result-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #ddd;
                }
                .result-table tbody tr:hover {
                    background: #f8f9fa;
                }
                .result-table td, .result-table th {
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                .result-status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status-normal {
                    background: #d4edda;
                    color: #155724;
                }
                .status-high {
                    background: #f8d7da;
                    color: #721c24;
                }
                .status-low {
                    background: #fff3cd;
                    color: #856404;
                }
                .status-abnormal {
                    background: #f8d7da;
                    color: #721c24;
                }
                .result-attachments {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                .result-footer {
                    padding: 20px;
                    border-top: 2px solid #eee;
                    font-size: 12px;
                    color: #999;
                    text-align: center;
                }
                @media print {
                    .modal-header, .modal-footer, .result-print-btn {
                        display: none !important;
                    }
                    .modal-dialog {
                        max-width: 100% !important;
                        margin: 0 !important;
                    }
                    .modal-content {
                        border: none !important;
                        box-shadow: none !important;
                    }
                    body {
                        background: white !important;
                    }
                }
            </style>

            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="investResViewModalLabel">
                    <i class="mdi mdi-file-document-outline"></i> Test Results
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <div id="resultViewTable">
                    <!-- Header -->
                    <div class="result-header">
                        <div class="result-header-left">
                            <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Hospital Logo" class="result-logo" />
                            <div class="result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
                        </div>
                        <div class="result-header-right">
                            <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                            <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                            <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <!-- Title Section -->
                    <div class="result-title-section">
                        TEST RESULTS
                    </div>

                    <!-- Patient Information -->
                    <div class="result-patient-info">
                        <div class="result-info-box">
                            <div class="result-info-row">
                                <div class="result-info-label">Patient Name:</div>
                                <div class="result-info-value" id="res_patient_name"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Patient ID:</div>
                                <div class="result-info-value" id="res_patient_id"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Age:</div>
                                <div class="result-info-value" id="res_patient_age"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Gender:</div>
                                <div class="result-info-value" id="res_patient_gender"></div>
                            </div>
                        </div>
                        <div class="result-info-box">
                            <div class="result-info-row">
                                <div class="result-info-label">Test Name:</div>
                                <div class="result-info-value invest_res_service_name_view"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Test ID:</div>
                                <div class="result-info-value" id="res_test_id"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Sample Date:</div>
                                <div class="result-info-value" id="res_sample_date"></div>
                            </div>
                            <div class="result-info-row">
                                <div class="result-info-label">Result Date:</div>
                                <div class="result-info-value" id="res_result_date"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div class="result-section">
                        <div class="result-section-title">TEST RESULTS</div>
                        <div id="invest_res"></div>
                    </div>

                    <!-- Attachments -->
                    <div id="invest_attachments" style="margin: 0 20px;"></div>

                    <!-- Footer Section -->
                    <div class="result-section" style="padding-top: 40px;">
                        <div style="display: flex; justify-content: space-between; border-top: 2px solid #eee; padding-top: 20px;">
                            <div>
                                <div style="margin-bottom: 5px;"><strong>Results By:</strong></div>
                                <div id="res_result_by" style="color: #666;"></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="margin-bottom: 5px;"><strong>Authorized Signature:</strong></div>
                                <div style="border-top: 1px solid #333; min-width: 200px; padding-top: 5px;">
                                    <span id="res_signature_date"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="result-footer">
                        <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
                        <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
                        <div style="margin-top: 10px; font-size: 11px;">This is a computer-generated document. Report generated on <span id="res_generated_date"></span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
                <button type="button" onclick="PrintElem('resultViewTable')" class="btn btn-primary">
                    <i class="mdi mdi-printer"></i> Print Results
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Imaging Result Entry Modal --}}
<div class="modal fade" id="imagingResModal" tabindex="-1" role="dialog" aria-labelledby="imagingResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('save-imaging-result') }}" method="post" enctype="multipart/form-data" onsubmit="copyImagingResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="imagingResModalLabel">Enter Imaging Result (<span
                            id="imaging_res_service_name"></span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="imaging_res_template_editor" class="ckeditor"></textarea>
                    <input type="hidden" id="imaging_res_entry_id" name="imaging_res_entry_id">
                    <input type="hidden" name="imaging_res_template_submited" id="imaging_res_template_submited">
                    <input type="hidden" id="imaging_res_is_edit" name="imaging_res_is_edit" value="0">
                    <input type="hidden" id="imaging_deleted_attachments" name="deleted_attachments" value="[]">

                    <hr>

                    <!-- Existing Attachments -->
                    <div id="imaging_existing_attachments_container" style="display: none;">
                        <label><i class="mdi mdi-paperclip"></i> Existing Attachments</label>
                        <div id="imaging_existing_attachments_list" class="mb-3"></div>
                    </div>

                    <!-- New File Upload -->
                    <div class="form-group">
                        <label for="result_attachments"><i class="mdi mdi-file-plus"></i> Add New Files (Optional)</label>
                        <input type="file" class="form-control" id="imaging_result_attachments" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">You can attach multiple files (PDF, Images, Word documents). Max 10MB per file.</small>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="imaging_res_submit_btn"
                        class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Imaging Result View Modal --}}
<div class="modal fade" id="imagingResViewModal" tabindex="-1" role="dialog" aria-labelledby="imagingResViewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            @php
                $sett = appsettings();
                $hosColor = $sett->hos_color ?? '#0066cc';
            @endphp
            <style>
                #imagingResultViewTable {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    width: 100%;
                    max-width: 100%;
                }
                .imaging-result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 3px solid {{ $hosColor }};
                }
                .imaging-result-header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .imaging-result-logo {
                    width: 70px;
                    height: 70px;
                    object-fit: contain;
                }
                .imaging-result-hospital-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    text-transform: uppercase;
                }
                .imaging-result-header-right {
                    text-align: right;
                    font-size: 13px;
                    color: #666;
                    line-height: 1.6;
                }
                .imaging-result-title-section {
                    background: {{ $hosColor }};
                    color: white;
                    text-align: center;
                    padding: 15px;
                    font-size: 28px;
                    font-weight: bold;
                    letter-spacing: 6px;
                }
                .imaging-result-patient-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    padding: 20px;
                    background: #f8f9fa;
                }
                .imaging-result-info-box {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                .imaging-result-info-row {
                    display: flex;
                    padding: 8px 0;
                    border-bottom: 1px solid #eee;
                }
                .imaging-result-info-row:last-child {
                    border-bottom: none;
                }
                .imaging-result-info-label {
                    font-weight: 600;
                    color: #333;
                    min-width: 120px;
                }
                .imaging-result-info-value {
                    color: #666;
                    flex: 1;
                }
                .imaging-result-section {
                    padding: 20px;
                }
                .imaging-result-section-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: {{ $hosColor }};
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid {{ $hosColor }};
                }
                .imaging-result-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse;
                    margin-top: 15px;
                    table-layout: fixed;
                }
                .imaging-result-table thead {
                    background: {{ $hosColor }};
                    color: white;
                }
                .imaging-result-table th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                }
                .imaging-result-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #ddd;
                }
                .imaging-result-table tbody tr:hover {
                    background: #f8f9fa;
                }
                .imaging-result-table td, .imaging-result-table th {
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                .imaging-result-status-badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .imaging-status-normal {
                    background: #d4edda;
                    color: #155724;
                }
                .imaging-status-high {
                    background: #f8d7da;
                    color: #721c24;
                }
                .imaging-status-low {
                    background: #fff3cd;
                    color: #856404;
                }
                .imaging-status-abnormal {
                    background: #f8d7da;
                    color: #721c24;
                }
                .imaging-result-attachments {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                .imaging-result-footer {
                    padding: 20px;
                    border-top: 2px solid #eee;
                    font-size: 12px;
                    color: #999;
                    text-align: center;
                }
                @media print {
                    .modal-header, .modal-footer {
                        display: none !important;
                    }
                    .modal-dialog {
                        max-width: 100% !important;
                        margin: 0 !important;
                    }
                    .modal-content {
                        border: none !important;
                        box-shadow: none !important;
                    }
                    body {
                        background: white !important;
                    }
                }
            </style>

            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="imagingResViewModalLabel">
                    <i class="mdi mdi-image-multiple"></i> Imaging Results
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <div id="imagingResultViewTable">
                    <!-- Header -->
                    <div class="imaging-result-header">
                        <div class="imaging-result-header-left">
                            <img src="data:image/jpeg;base64,{{ $sett->logo ?? '' }}" alt="Hospital Logo" class="imaging-result-logo" />
                            <div class="imaging-result-hospital-name">{{ $sett->site_name ?? 'Hospital Name' }}</div>
                        </div>
                        <div class="imaging-result-header-right">
                            <div><strong>Address:</strong> {{ $sett->contact_address ?? 'N/A' }}</div>
                            <div><strong>Phone:</strong> {{ $sett->contact_phones ?? 'N/A' }}</div>
                            <div><strong>Email:</strong> {{ $sett->contact_emails ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <!-- Title Section -->
                    <div class="imaging-result-title-section">
                        IMAGING RESULTS
                    </div>

                    <!-- Patient Information -->
                    <div class="imaging-result-patient-info">
                        <div class="imaging-result-info-box">
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Patient Name:</div>
                                <div class="imaging-result-info-value" id="imaging_patient_name"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Patient ID:</div>
                                <div class="imaging-result-info-value" id="imaging_patient_id"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Age:</div>
                                <div class="imaging-result-info-value" id="imaging_patient_age"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Gender:</div>
                                <div class="imaging-result-info-value" id="imaging_patient_gender"></div>
                            </div>
                        </div>
                        <div class="imaging-result-info-box">
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Imaging Type:</div>
                                <div class="imaging-result-info-value imaging_res_service_name_view"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Test ID:</div>
                                <div class="imaging-result-info-value" id="imaging_test_id"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Result Date:</div>
                                <div class="imaging-result-info-value" id="imaging_result_date"></div>
                            </div>
                            <div class="imaging-result-info-row">
                                <div class="imaging-result-info-label">Status:</div>
                                <div class="imaging-result-info-value" id="imaging_status"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div class="imaging-result-section">
                        <div class="imaging-result-section-title">IMAGING FINDINGS</div>
                        <div id="imaging_res"></div>
                    </div>

                    <!-- Attachments -->
                    <div id="imaging_attachments" style="margin: 0 20px;"></div>

                    <!-- Footer Section -->
                    <div class="imaging-result-section" style="padding-top: 40px;">
                        <div style="display: flex; justify-content: space-between; border-top: 2px solid #eee; padding-top: 20px;">
                            <div>
                                <div style="margin-bottom: 5px;"><strong>Results By:</strong></div>
                                <div id="imaging_result_by" style="color: #666;"></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="margin-bottom: 5px;"><strong>Authorized Signature:</strong></div>
                                <div style="border-top: 1px solid #333; min-width: 200px; padding-top: 5px;">
                                    <span id="imaging_signature_date"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="imaging-result-footer">
                        <div>{{ $sett->site_name ?? 'Hospital Name' }} | {{ $sett->contact_address ?? '' }}</div>
                        <div>{{ $sett->contact_phones ?? '' }} | {{ $sett->contact_emails ?? '' }}</div>
                        <div style="margin-top: 10px; font-size: 11px;">This is a computer-generated document. Report generated on <span id="imaging_generated_date"></span></div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Close
                    </button>
                <button type="button" onclick="PrintElem('imagingResultViewTable')" class="btn btn-primary">
                    <i class="mdi mdi-printer"></i> Print Results
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="nursingNoteModal" tabindex="-1" role="dialog" aria-labelledby="nursingNoteModal"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="investResModalLabel">Nursing Note Result (<span
                        id="note_type_name_"></span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
            </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignBillModal" tabindex="-1" role="dialog" aria-labelledby="assignBillModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('assign-bill') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Bill </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assign_bed_req_id_" name="assign_bed_req_id">
                    <div class="form-group">
                        <label for="admit_days">No of days admitted</label>
                        <input type="text" name="days" class="form-control" id="admit_days" readonly>
                    </div>
                    <div class="form-group">
                        <h6>Bed Details</h6>
                        <p id="admit_bed_details"></p>
                        <label>Price</label>
                        <input type="text" class="form-control" id="admit_price" readonly>
                    </div>
                    <div class="form-group">
                        <label for="">Total</label>
                        <input type="text" id="admit_total" class="form-control" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                        class="btn btn-primary">Save Bill </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignBedModal" tabindex="-1" role="dialog" aria-labelledby="assignBedModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('assign-bed') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Bed </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assign_bed_req_id" name="assign_bed_req_id">
                    <input type="hidden" id="assign_bed_patient_id" name="assign_bed_patient_id">
                    {{-- Redundent --}}
                    <input type="hidden" id="assign_bed_reassign" name="assign_bed_reassign">
                    <div class="form-group">
                        <label for="bed_id_select">Select Bed</label>
                        <select name="bed_id" id="bed_id_select" class="form-control">
                            <option value="">--select bed--</option>
                            @if(isset($avail_beds))
                                @foreach ($avail_beds as $bed)
                                    <option value="{{ $bed->id }}">{{ $bed->name }}[Price: NGN
                                        {{ $bed->price }}, Ward: {{ $bed->ward }}, Unit: {{ $bed->unit }}]
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- HMO Coverage Information -->
                    <div id="bed_coverage_info" class="mt-3" style="display: none;">
                        <div class="alert alert-info">
                            <h6 class="mb-3"><i class="fa fa-info-circle"></i> <strong>Coverage Breakdown</strong></h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>HMO:</strong> <span id="coverage_hmo_name">N/A</span></p>
                                    <p class="mb-2"><strong>Bed Price:</strong> NGN <span id="coverage_bed_price">0</span>/day</p>
                                    <p class="mb-2"><strong>Coverage Mode:</strong> <span id="coverage_mode" class="badge bg-secondary">N/A</span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Patient Pays:</strong> <span class="text-danger">NGN <span id="coverage_payable">0</span>/day</span></p>
                                    <p class="mb-2"><strong>HMO Covers:</strong> <span class="text-success">NGN <span id="coverage_claims">0</span>/day</span></p>
                                    <p class="mb-2" id="validation_required_text" style="display: none;">
                                        <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> HMO Validation Required</span>
                                    </p>
                                </div>
                            </div>
                            <div id="coverage_error" class="alert alert-warning mt-2" style="display: none;">
                                <small><i class="fa fa-exclamation-triangle"></i> <span id="coverage_error_text"></span></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                        class="btn btn-primary">Assign Bed </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function waitForJQuery() {
    if (!window.jQuery) {
        setTimeout(waitForJQuery, 50);
        return;
    }
    const $ = window.jQuery;
    $(document).ready(function() {
        $('#bed_id_select').on('change', function() {
            const bedId = $(this).val();
            const patientId = $('#assign_bed_patient_id').val();

            if (!bedId || !patientId) {
                $('#bed_coverage_info').hide();
                return;
            }

            // Fetch coverage information
            $.ajax({
                url: "{{ route('bed-coverage') }}",
                method: 'GET',
                data: {
                    bed_id: bedId,
                    patient_id: patientId
                },
                success: function(response) {
                    if (response.success && response.coverage) {
                        const coverage = response.coverage;

                        // Update coverage display
                        $('#coverage_bed_price').text((coverage.bed_price || 0).toLocaleString());
                        $('#coverage_payable').text((coverage.payable_amount || 0).toLocaleString());
                        $('#coverage_claims').text((coverage.claims_amount || 0).toLocaleString());

                        if (coverage.has_hmo) {
                            $('#coverage_hmo_name').text(coverage.hmo_name || 'N/A');

                            // Coverage mode badge
                            let modeClass = 'bg-secondary';
                            if (coverage.coverage_mode === 'express') modeClass = 'bg-success';
                            else if (coverage.coverage_mode === 'primary') modeClass = 'bg-primary';
                            else if (coverage.coverage_mode === 'secondary') modeClass = 'bg-warning';

                            $('#coverage_mode').removeClass('bg-secondary bg-success bg-primary bg-warning')
                                .addClass(modeClass)
                                .text((coverage.coverage_mode || 'N/A').toUpperCase());

                            // Show validation warning if needed
                            if (coverage.requires_validation) {
                                $('#validation_required_text').show();
                            } else {
                                $('#validation_required_text').hide();
                            }

                            // Show error if any
                            if (coverage.error_message) {
                                $('#coverage_error_text').text(coverage.error_message);
                                $('#coverage_error').show();
                            } else {
                                $('#coverage_error').hide();
                            }
                        } else {
                            $('#coverage_hmo_name').text('None (Cash Patient)');
                            $('#coverage_mode').text('CASH').removeClass('bg-success bg-primary bg-warning').addClass('bg-secondary');
                            $('#validation_required_text').hide();
                            $('#coverage_error').hide();
                        }

                        $('#bed_coverage_info').show();
                    }
                },
                error: function(xhr) {
                    console.error('Error fetching coverage:', xhr);
                    $('#bed_coverage_info').hide();
                }
            });
        });
    });
})();
</script>

{{-- Edit Encounter Note Modal --}}
{{-- Edit Encounter Note Modal --}}
<div class="modal fade" id="editEncounterModal" tabindex="-1" aria-labelledby="editEncounterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: {{ appsettings('hos_color', '#007bff') }}; color: white;">
                <h5 class="modal-title" id="editEncounterModalLabel">
                    <i class="mdi mdi-pencil"></i> Edit Encounter Note
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editEncounterId">

                @if (appsettings('requirediagnosis', 0))
                <!-- Modern Toggle Switch for Diagnosis Not Applicable -->
                <div class="diagnosis-toggle-container mb-4">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                        <div>
                            <strong class="d-block mb-1">Diagnosis Applicable?</strong>
                            <small class="text-muted">Toggle to show/hide diagnosis fields</small>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="editEncounterDiagnosisApplicable" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="diagnosis-fields-wrapper" id="editEncounterDiagnosisFields">
                <div class="form-group mb-3" id="editEncounterReasonsGroup">
                    <label for="editEncounterReasonsSearch" class="form-label">
                        <strong>Search ICPC-2 Reason(s) for Encounter/Diagnosis <span class="text-danger">*</span></strong>
                    </label>
                    <input type="text"
                        class="form-control mb-2"
                        id="editEncounterReasonsSearch"
                        placeholder="Type to search diagnosis codes... (e.g., 'A03', 'Fever', 'Hypertension')"
                        autocomplete="off">
                    <small class="text-muted d-block mb-2">
                        <i class="mdi mdi-information"></i> Type at least 2 characters to search. You can also add custom reasons.
                    </small>
                    <ul class="list-group" id="editReasons_search_results" style="display: none; max-height: 250px; overflow-y: auto;"></ul>

                    <!-- Selected reasons display -->
                    <div id="editSelected_reasons_container" class="mt-3">
                        <label class="d-block mb-2"><strong>Selected Diagnoses:</strong></label>
                        <div id="editSelected_reasons_list">
                            <span class="text-muted"><i>No diagnoses selected yet</i></span>
                        </div>
                    </div>

                    <!-- Hidden input to store selected reason values -->
                    <input type="hidden" id="editEncounterReasonsData" value="[]">
                </div>

                <div class="row mb-3" id="editEncounterCommentsGroup">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="editEncounterComment1" class="form-label">
                                <strong>Select Diagnosis Comment 1 <span class="text-danger">*</span></strong>
                            </label>
                            <select class="form-control" id="editEncounterComment1" required>
                                <option value="NA">Not Applicable</option>
                                <option value="QUERY">Query</option>
                                <option value="DIFFRENTIAL">Diffrential</option>
                                <option value="CONFIRMED">Confirmed</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="editEncounterComment2" class="form-label">
                                <strong>Select Diagnosis Comment 2 <span class="text-danger">*</span></strong>
                            </label>
                            <select class="form-control" id="editEncounterComment2" required>
                                <option value="NA">Not Applicable</option>
                                <option value="ACUTE">Acute</option>
                                <option value="CHRONIC">Chronic</option>
                                <option value="RECURRENT">Recurrent</option>
                            </select>
                        </div>
                    </div>
                </div>
                </div>
                @endif

                <div class="form-group mb-3">
                    <label for="editEncounterNotes" class="form-label">
                        <strong>Clinical Notes / Diagnosis <span class="text-danger">*</span></strong>
                    </label>
                    <textarea class="form-control" id="editEncounterNotes" rows="10" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="saveEncounterEditBtn">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern Toggle Switch Styling */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 30px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: 0.4s;
    border-radius: 30px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: 0.4s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: #28a745;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(30px);
}

.toggle-switch input:focus + .toggle-slider {
    box-shadow: 0 0 1px #28a745;
}

.diagnosis-fields-wrapper {
    overflow: hidden;
    transition: max-height 0.5s ease, opacity 0.5s ease;
}

.diagnosis-fields-wrapper.hidden {
    max-height: 0 !important;
    opacity: 0;
}

.diagnosis-toggle-container {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Diagnosis Search Styles */
.diagnosis-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    margin: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.diagnosis-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.diagnosis-badge .remove-btn {
    cursor: pointer;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    font-size: 12px;
}

.diagnosis-badge .remove-btn:hover {
    background: rgba(255,255,255,0.5);
}

#editReasons_search_results .list-group-item,
#reasons_search_results .list-group-item {
    cursor: pointer;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

#editReasons_search_results .list-group-item:hover,
#reasons_search_results .list-group-item:hover {
    background: #f8f9fa;
    border-left-color: #667eea;
    padding-left: 18px;
}

.reason-code {
    font-weight: 600;
    color: #667eea;
    margin-right: 8px;
}

.reason-name {
    color: #333;
}

.reason-category {
    font-size: 11px;
    color: #6c757d;
    margin-top: 2px;
}
</style>

<script>
// Edit Encounter Note - Populate modal with full functionality
if (typeof editEncounterEditorInstance === 'undefined') {
    var editEncounterEditorInstance = null;
}
let editSelectedReasons = [];
let editReasonSearchTimeout = null;

// Function to add a reason to edit modal selected list
function editAddReason(reason) {
    if (editSelectedReasons.find(r => r.value === reason.value)) {
        return;
    }
    editSelectedReasons.push(reason);
    editUpdateSelectedReasonsDisplay();
    editUpdateHiddenInput();
}

// Function to remove a reason from edit modal
function editRemoveReason(value) {
    editSelectedReasons = editSelectedReasons.filter(r => r.value !== value);
    editUpdateSelectedReasonsDisplay();
    editUpdateHiddenInput();
}

// Update the visual display of selected reasons in edit modal — TABLE layout with per-row dropdowns
function editUpdateSelectedReasonsDisplay() {
    const container = $('#editSelected_reasons_list');
    container.empty();

    if (editSelectedReasons.length === 0) {
        container.html('<span class="text-muted"><i>No diagnoses selected yet</i></span>');
        $('#editEncounterCommentsGroup').show();
    } else {
        // Hide global comment dropdowns — we use per-row now
        $('#editEncounterCommentsGroup').hide();

        let html = `<table class="table table-sm table-bordered mb-0" style="font-size:0.85rem;">
            <thead class="table-light"><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th><th style="width:40px;"></th></tr></thead><tbody>`;
        editSelectedReasons.forEach((reason, idx) => {
            const c1 = reason.comment_1 || 'NA';
            const c2 = reason.comment_2 || 'NA';
            const safeValue = reason.value.replace(/'/g, "\\'");
            html += `<tr>
                <td><code>${reason.code || reason.value.split('-')[0] || '-'}</code></td>
                <td>${reason.name || reason.display || reason.value}</td>
                <td>
                    <select class="form-select form-select-sm" onchange="editUpdateReasonComment(${idx}, 'comment_1', this.value)">
                        <option value="NA" ${c1==='NA'?'selected':''}>NA</option>
                        <option value="QUERY" ${c1==='QUERY'?'selected':''}>Query</option>
                        <option value="DIFFRENTIAL" ${c1==='DIFFRENTIAL'?'selected':''}>Differential</option>
                        <option value="CONFIRMED" ${c1==='CONFIRMED'?'selected':''}>Confirmed</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" onchange="editUpdateReasonComment(${idx}, 'comment_2', this.value)">
                        <option value="NA" ${c2==='NA'?'selected':''}>NA</option>
                        <option value="ACUTE" ${c2==='ACUTE'?'selected':''}>Acute</option>
                        <option value="CHRONIC" ${c2==='CHRONIC'?'selected':''}>Chronic</option>
                        <option value="RECURRENT" ${c2==='RECURRENT'?'selected':''}>Recurrent</option>
                    </select>
                </td>
                <td><button type="button" class="btn btn-sm btn-outline-danger p-0 px-1" onclick="editRemoveReasonByValue('${safeValue}')">×</button></td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.html(html);
    }
}

// Update a specific reason's comment field
function editUpdateReasonComment(index, field, value) {
    if (editSelectedReasons[index]) {
        editSelectedReasons[index][field] = value;
        editUpdateHiddenInput();
    }
}

// Update hidden input with selected reason values in edit modal
function editUpdateHiddenInput() {
    $('#editEncounterReasonsData').val(JSON.stringify(editSelectedReasons));
    console.log('Edit modal - Selected reasons:', editSelectedReasons);
}

// Make remove function accessible globally
window.editRemoveReasonByValue = function(value) {
    editRemoveReason(value);
};

function editEncounterNote(btn) {
    console.log('=== editEncounterNote called ===');
    const $btn = $(btn);
    const id = $btn.data('id');
    const notes = $btn.data('notes');
    const reasons = $btn.attr('data-reasons'); // Use attr to ensure string
    const comment1 = $btn.data('comment1');
    const comment2 = $btn.data('comment2');
    const isWardRound = $btn.data('is-ward-round');

    console.log('Encounter Data:', {
        id: id,
        reasons: reasons,
        comment1: comment1,
        comment2: comment2,
        isWardRound: isWardRound,
        notesLength: notes ? notes.length : 0
    });

    // Store the encounter ID
    $('#editEncounterId').val(id);

    // Show the modal first
    $('#editEncounterModal').modal('show');

    // Initialize AJAX search for reasons after modal is shown
    @if(appsettings('requirediagnosis', 0))
    setTimeout(function() {
        console.log('Initializing diagnosis search for edit modal...');

        // Clear previous selections
        editSelectedReasons = [];

        // Populate diagnosis selection if available
        if (reasons && reasons.trim() !== '') {
            console.log('Setting reasons:', reasons);

            // Try parsing as JSON (new per-diagnosis format)
            let isJsonFormat = false;
            try {
                let parsed = JSON.parse(reasons);
                if (Array.isArray(parsed) && parsed.length && parsed[0].code) {
                    isJsonFormat = true;
                    parsed.forEach(dx => {
                        const reason = {
                            value: (dx.code || '') + '-' + (dx.name || ''),
                            display: (dx.code || '') + '-' + (dx.name || ''),
                            code: dx.code || 'CUSTOM',
                            name: dx.name || dx.code || '',
                            comment_1: dx.comment_1 || 'NA',
                            comment_2: dx.comment_2 || 'NA'
                        };
                        editAddReason(reason);
                    });
                }
            } catch(e) { /* not JSON, use legacy */ }

            if (!isJsonFormat) {
                const reasonsArray = reasons.split(',').map(r => r.trim());
                console.log('Reasons array:', reasonsArray);
                reasonsArray.forEach(reasonValue => {
                    if (reasonValue) {
                        const reason = {
                            value: reasonValue,
                            display: reasonValue,
                            code: reasonValue.split('-')[0] || 'CUSTOM',
                            name: reasonValue.split('-').slice(1).join('-') || reasonValue
                        };
                        editAddReason(reason);
                    }
                });
            }

            console.log('Selected reasons after setting:', editSelectedReasons);

            // Check Diagnosis Applicable toggle (ON = diagnosis applies)
            $('#editEncounterDiagnosisApplicable').prop('checked', true).trigger('change');
        } else {
            console.log('No reasons found, setting Diagnosis Not Applicable');
            $('#editEncounterDiagnosisApplicable').prop('checked', false).trigger('change');
        }

        $('#editEncounterComment1').val(comment1 || 'NA');
        $('#editEncounterComment2').val(comment2 || 'NA');
        console.log('Comments set:', {comment1: comment1 || 'NA', comment2: comment2 || 'NA'});
    }, 200);
    @endif

    // Store notes to set after editor initialization
    const notesContent = notes || '';

    // Initialize ClassicEditor (CKEditor 5) after modal is shown
    // Wait a bit to ensure modal DOM is ready
    setTimeout(function() {
        // Destroy existing editor instance if any
        if (editEncounterEditorInstance) {
            editEncounterEditorInstance.destroy()
                .then(() => {
                    initializeEditEncounterEditor(notesContent);
                })
                .catch(error => {
                    console.error('Error destroying editor:', error);
                    initializeEditEncounterEditor(notesContent);
                });
        } else {
            initializeEditEncounterEditor(notesContent);
        }
    }, 300);
}

function initializeEditEncounterEditor(content) {
    const editorElement = document.querySelector('#editEncounterNotes');

    if (!editorElement) {
        console.error('Editor element not found');
        return;
    }

    ClassicEditor
        .create(editorElement, {
            toolbar: {
                items: [
                    'undo', 'redo',
                    '|', 'heading',
                    '|', 'bold', 'italic',
                    '|', 'link', 'uploadImage', 'insertTable', 'mediaEmbed',
                    '|', 'bulletedList', 'numberedList', 'outdent', 'indent'
                ]
            }
        })
        .then(editor => {
            editEncounterEditorInstance = editor;
            // Set the content
            editor.setData(content);
        })
        .catch(error => {
            console.error('Error initializing editor:', error);
            // Fallback to plain textarea
            $('#editEncounterNotes').val(content);
        });
}

// Save Encounter Edit
$('#saveEncounterEditBtn').on('click', function() {
    console.log('=== Save button clicked ===');
    const encounterId = $('#editEncounterId').val();
    console.log('Encounter ID:', encounterId);

    // Get notes from ClassicEditor or textarea
    let notes = '';
    if (editEncounterEditorInstance) {
        notes = editEncounterEditorInstance.getData();
    } else {
        notes = $('#editEncounterNotes').val();
    }
    console.log('Notes length:', notes ? notes.length : 0);

    @if(appsettings('requirediagnosis', 0))
    const diagnosisApplicable = $('#editEncounterDiagnosisApplicable').is(':checked');
    let reasonValues = [];
    let comment1 = 'NA';
    let comment2 = 'NA';

    console.log('Diagnosis Applicable checked:', diagnosisApplicable);

    if (diagnosisApplicable) {
        // Get selected reasons from the new AJAX search component
        const reasonsData = $('#editEncounterReasonsData').val();
        let selectedReasons = [];

        try {
            selectedReasons = JSON.parse(reasonsData);
        } catch (e) {
            console.error('Error parsing reasons data:', e);
        }

        comment1 = $('#editEncounterComment1').val();
        comment2 = $('#editEncounterComment2').val();

        console.log('Diagnosis data:', {
            selectedReasons: selectedReasons,
            comment1: comment1,
            comment2: comment2
        });

        if (!selectedReasons || selectedReasons.length === 0) {
            alert('Please select at least one diagnosis reason or toggle off "Diagnosis Applicable".');
            return;
        }

        // Extract values for submission
        reasonValues = selectedReasons.map(r => r.value);
    }
    @endif

    if (!notes || !notes.trim()) {
        alert('Clinical notes are required.');
        return;
    }

    $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    const ajaxData = {
        notes: notes,
        @if(appsettings('requirediagnosis', 0))
        diagnosis_applicable: diagnosisApplicable ? 1 : 0,
        reasons_for_encounter: diagnosisApplicable ? reasonValues.join(',') : '',
        reasons_for_encounter_comment_1: diagnosisApplicable ? comment1 : '',
        reasons_for_encounter_comment_2: diagnosisApplicable ? comment2 : '',
        @endif
    };

    // Build per-diagnosis comments JSON if we have per-reason data
    @if(appsettings('requirediagnosis', 0))
    if (diagnosisApplicable && selectedReasons && selectedReasons.length > 0) {
        let perDiag = selectedReasons.map(r => ({
            code: r.code || r.value.split('-')[0] || '',
            name: r.name || r.value.split('-').slice(1).join('-') || r.value,
            comment_1: r.comment_1 || comment1 || 'NA',
            comment_2: r.comment_2 || comment2 || 'NA'
        }));
        ajaxData.per_diagnosis_comments = JSON.stringify(perDiag);
    }
    @endif

    console.log('Sending AJAX request with data:', ajaxData);

    $.ajax({
        url: `/encounters/${encounterId}/notes`,
        type: 'PUT',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: ajaxData,
        success: function(response) {
            console.log('Save successful:', response);
            if (response.success) {
                $('#editEncounterModal').modal('hide');

                // Reload DataTable if it exists
                if ($.fn.DataTable.isDataTable('#encounter_history_list')) {
                    $('#encounter_history_list').DataTable().ajax.reload(null, false);
                }

                alert('Encounter note updated successfully!');

                // Reload page if DataTable doesn't exist (for patient show page)
                if (!$.fn.DataTable.isDataTable('#encounter_history_list')) {
                    console.log('Reloading page...');
                    location.reload();
                }
            } else {
                alert(response.message || 'Failed to update encounter note');
            }
        },
        error: function(xhr) {
            console.error('Save error:', xhr);
            const errorMsg = xhr.responseJSON?.message || 'An error occurred while updating the encounter note';
            alert(errorMsg);
        },
        complete: function() {
            $('#saveEncounterEditBtn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
        }
    });
});

// Cleanup ClassicEditor when modal is closed
$('#editEncounterModal').on('hidden.bs.modal', function() {
    if (editEncounterEditorInstance) {
        editEncounterEditorInstance.destroy()
            .then(() => {
                editEncounterEditorInstance = null;
            })
            .catch(error => {
                console.error('Error destroying editor on modal close:', error);
                editEncounterEditorInstance = null;
            });
    }
});

// Delete Encounter Note - Add to existing delete functionality
function deleteEncounter(encounterId, encounterDate) {
    if (!confirm(`Are you sure you want to delete the encounter note from ${encounterDate}? This action cannot be undone.`)) {
        return;
    }

    const reason = prompt('Please provide a reason for deleting this encounter note:');
    if (!reason || !reason.trim()) {
        alert('A reason is required to delete an encounter note.');
        return;
    }

    $.ajax({
        url: `/encounters/${encounterId}`,
        type: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            reason: reason
        },
        success: function(response) {
            if (response.success) {
                alert('Encounter note deleted successfully');
                $('#encounter_history_list').DataTable().ajax.reload();
            } else {
                alert(response.message || 'Failed to delete encounter note');
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON?.message || 'An error occurred while deleting the encounter note';
            alert(errorMsg);
        }
    });
}

// Initialize Select2 for the edit modal diagnosis selection
$(document).ready(function() {
    console.log('Document ready - initializing toggle handlers and AJAX search');

    // Handle Diagnosis Applicable Toggle
    $('#editEncounterDiagnosisApplicable').on('change', function() {
        const isChecked = $(this).is(':checked');
        console.log('Diagnosis Applicable toggle changed:', isChecked);

        const $diagnosisFields = $('#editEncounterDiagnosisFields');

        if (isChecked) {
            // Diagnosis IS applicable - show fields with animation
            $diagnosisFields.removeClass('hidden');
            $diagnosisFields.css('max-height', $diagnosisFields[0].scrollHeight + 'px');

            // Clear NA values if present
            if ($('#editEncounterComment1').val() === 'NA') $('#editEncounterComment1').val('');
            if ($('#editEncounterComment2').val() === 'NA') $('#editEncounterComment2').val('');
        } else {
            // Diagnosis is NOT applicable - hide fields with animation
            $diagnosisFields.css('max-height', '0');
            $diagnosisFields.addClass('hidden');

            // Clear selected reasons
            editSelectedReasons = [];
            editUpdateSelectedReasonsDisplay();
            editUpdateHiddenInput();

            $('#editEncounterComment1').val('NA');
            $('#editEncounterComment2').val('NA');
        }
    });

    // AJAX search for reasons in edit modal
    $('#editEncounterReasonsSearch').on('keyup', function() {
        const searchTerm = $(this).val().trim();

        clearTimeout(editReasonSearchTimeout);

        if (searchTerm.length < 2) {
            $('#editReasons_search_results').hide().empty();
            return;
        }

        editReasonSearchTimeout = setTimeout(function() {
            $.ajax({
                url: "{{ url('live-search-reasons') }}",
                method: "GET",
                dataType: 'json',
                data: { term: searchTerm },
                success: function(data) {
                    $('#editReasons_search_results').empty();

                    if (data.length === 0) {
                        // Show option to add custom reason
                        const customItem = $(`
                            <li class="list-group-item" style="background-color: #fff3cd;">
                                <div>
                                    <span class="reason-code">CUSTOM</span>
                                    <span class="reason-name">Add custom reason: "${searchTerm}"</span>
                                </div>
                                <div class="reason-category">
                                    <i class="mdi mdi-plus-circle"></i> Click to add as custom diagnosis
                                </div>
                            </li>
                        `);

                        customItem.on('click', function() {
                            editAddReason({
                                value: searchTerm,
                                display: 'CUSTOM - ' + searchTerm,
                                code: 'CUSTOM',
                                name: searchTerm
                            });
                            $('#editEncounterReasonsSearch').val('');
                            $('#editReasons_search_results').hide();
                        });

                        $('#editReasons_search_results').append(customItem);
                    } else {
                        // Show search results
                        data.forEach(function(reason) {
                            const item = $(`
                                <li class="list-group-item">
                                    <div>
                                        <span class="reason-code">${reason.code}</span>
                                        <span class="reason-name">${reason.name}</span>
                                    </div>
                                    <div class="reason-category">
                                        ${reason.category} › ${reason.sub_category}
                                    </div>
                                </li>
                            `);

                            item.on('click', function() {
                                editAddReason(reason);
                                $('#editEncounterReasonsSearch').val('');
                                $('#editReasons_search_results').hide();
                            });

                            $('#editReasons_search_results').append(item);
                        });
                    }

                    $('#editReasons_search_results').show();
                },
                error: function() {
                    $('#editReasons_search_results').html(`
                        <li class="list-group-item text-danger">
                            <i class="mdi mdi-alert"></i> Error searching diagnoses. Please try again.
                        </li>
                    `).show();
                }
            });
        }, 300);
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#editEncounterReasonsSearch, #editReasons_search_results').length) {
            $('#editReasons_search_results').hide();
        }
    });

    // Set initial max-height for animation
    setTimeout(function() {
        const $diagnosisFields = $('#editEncounterDiagnosisFields');
        if ($diagnosisFields.length) {
            $diagnosisFields.css('max-height', $diagnosisFields[0].scrollHeight + 'px');
        }
    }, 100);
});
</script>
