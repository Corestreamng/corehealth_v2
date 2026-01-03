<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('service-save-result') }}" method="post" enctype="multipart/form-data" onsubmit="copyResTemplateToField()">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
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
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="mdi mdi-close"></i> Close
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
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                </button>
            </div>
            <div class="modal-body">
                <div id="nursing_note_template_" class="table-reponsive" style="border: 1px solid black;">

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('assign-bed') }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Bed </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times; <small>Press ESC to exit</small></span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="assign_bed_req_id" name="assign_bed_req_id">
                    {{-- Redundent --}}
                    <input type="hidden" id="assign_bed_reassign" name="assign_bed_reassign">
                    <div class="form-group">
                        <label for="">Select Bed</label>
                        <select name="bed_id" class="form-control">
                            <option value="">--select bed--</option>
                            @foreach ($avail_beds as $bed)
                                <option value="{{ $bed->id }}">{{ $bed->name }}[Price: NGN
                                    {{ $bed->price }}, Ward: {{ $bed->ward }}, Unit: {{ $bed->unit }}]
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit"
                        onclick="return confirm('Are you sure you wish to save this entry? It can not be edited after!')"
                        class="btn btn-primary">Assign Bed </button>
                </div>
            </form>
        </div>
    </div>
</div>
