{{--
    Imaging Result View Modal (shared partial)

    Include this in any view that needs to display imaging results in a modal.
    Requires a JS function `setImagingResViewInModal(obj)` to populate fields.
    Also requires `PrintElem()` JS function for printing.
--}}
@php
    $sett = $sett ?? appsettings();
    $hosColor = $sett->hos_color ?? '#0066cc';
@endphp

<div class="modal fade" id="imagingResViewModal" tabindex="-1" role="dialog" aria-labelledby="imagingResViewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
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
