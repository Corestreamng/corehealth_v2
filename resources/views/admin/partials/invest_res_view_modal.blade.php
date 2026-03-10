{{--
    Investigation Result View Modal (shared partial)

    Include this in any view that needs to display lab/investigation results in a modal.
    Pair with invest_res_view_js partial for the JS logic.

    Optional variable:
        $resultViewTitle  — title shown in header + title section (default: 'Test Results')
        $showLabNumber    — whether to show the Lab Number field (default: false)
--}}
@php
    $sett = $sett ?? appsettings();
    $hosColor = $sett->hos_color ?? '#0066cc';
    $resultViewTitle = $resultViewTitle ?? 'Test Results';
    $showLabNumber = $showLabNumber ?? false;
@endphp

<div class="modal fade" id="investResViewModal" tabindex="-1" role="dialog"
    aria-labelledby="investResViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <style>
                /* Base Container */
                #resultViewTable {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    width: 100%;
                    max-width: 100%;
                }

                /* Result Modal Header & Branding */
                .result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    padding: 20px;
                    border-bottom: 3px solid {{ $hosColor }};
                    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                }
                .result-header-left {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .result-logo {
                    width: 80px;
                    height: 80px;
                    object-fit: contain;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                .result-hospital-name {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: {{ $hosColor }};
                    max-width: 300px;
                    line-height: 1.3;
                }
                .result-header-right {
                    text-align: right;
                    font-size: 0.9rem;
                    color: #495057;
                    line-height: 1.6;
                }
                .result-header-right strong {
                    color: #212529;
                }

                /* Result Title Section */
                .result-title-section {
                    background: {{ $hosColor }};
                    color: white;
                    text-align: center;
                    padding: 12px 20px;
                    font-size: 1.1rem;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                /* Patient Info Section */
                .result-patient-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-bottom: 1px solid #dee2e6;
                }
                .result-info-box {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
                }
                .result-info-row {
                    display: flex;
                    margin-bottom: 8px;
                    padding-bottom: 8px;
                    border-bottom: 1px dashed #eee;
                }
                .result-info-row:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .result-info-label {
                    font-weight: 600;
                    color: #6c757d;
                    min-width: 120px;
                    font-size: 0.9rem;
                }
                .result-info-value {
                    color: #212529;
                    font-weight: 500;
                    flex: 1;
                }

                /* Result Section */
                .result-section {
                    padding: 20px;
                }
                .result-section-title {
                    font-size: 1rem;
                    font-weight: 700;
                    color: {{ $hosColor }};
                    margin-bottom: 15px;
                    padding-bottom: 10px;
                    border-bottom: 2px solid {{ $hosColor }};
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                /* Result Table Styling */
                .result-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                .result-table th {
                    background: {{ $hosColor }};
                    color: white;
                    padding: 12px 15px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 0.9rem;
                }
                .result-table td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #dee2e6;
                    vertical-align: middle;
                }
                .result-table tbody tr:hover {
                    background: #f8f9fa;
                }
                .result-table td, .result-table th {
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }

                /* Status Badges */
                .result-status-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 0.75rem;
                    font-weight: 700;
                    text-transform: uppercase;
                }
                .status-normal { background: #d4edda; color: #155724; }
                .status-high { background: #f8d7da; color: #721c24; }
                .status-low { background: #fff3cd; color: #856404; }
                .status-abnormal { background: #f8d7da; color: #721c24; }

                /* Attachments Section */
                .result-attachments {
                    margin: 0 20px 20px 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #dee2e6;
                }
                .result-attachments h6 {
                    color: {{ $hosColor }};
                    font-weight: 700;
                    margin-bottom: 15px;
                }

                /* Result Footer */
                .result-footer {
                    padding: 20px;
                    border-top: 2px solid #dee2e6;
                    font-size: 0.85rem;
                    color: #6c757d;
                    text-align: center;
                    background: #f8f9fa;
                }

                /* Print Styles */
                @media print {
                    .modal-header, .modal-footer, .result-print-btn { display: none !important; }
                    .modal-dialog { max-width: 100% !important; margin: 0 !important; }
                    .modal-content { border: none !important; box-shadow: none !important; }
                    body { background: white !important; }
                    .result-header { border-bottom: 3px solid #000 !important; }
                    .result-title-section { background: #333 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                    .result-table th { background: #333 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .result-header {
                        flex-direction: column;
                        gap: 15px;
                        text-align: center;
                    }
                    .result-header-left {
                        flex-direction: column;
                    }
                    .result-header-right {
                        text-align: center;
                    }
                    .result-patient-info {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <div class="modal-header" style="background: {{ $hosColor }}; color: white;">
                <h5 class="modal-title" id="investResViewModalLabel">
                    <i class="mdi mdi-file-document-outline"></i> {{ $resultViewTitle }}
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
                        {{ strtoupper($resultViewTitle) }}
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
                            @if($showLabNumber)
                            <div class="result-info-row">
                                <div class="result-info-label">Lab Number:</div>
                                <div class="result-info-value" id="res_lab_number"></div>
                            </div>
                            @endif
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
                        <div class="result-section-title">{{ strtoupper($resultViewTitle) }}</div>
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
                        <div style="margin-top: 10px; font-size: 11px;">
                            This is a computer-generated document. Report generated on <span id="res_generated_date"></span>
                        </div>
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
