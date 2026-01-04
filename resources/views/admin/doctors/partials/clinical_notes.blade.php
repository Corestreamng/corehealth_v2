{{-- Clinical Notes & Diagnosis: Combined History + New Entry --}}
<div class="card">
    <div class="card-body">
        {{-- Nav tabs for History and New Entry --}}
        <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active smooth-transition" id="notes-history-tab" data-bs-toggle="tab"
                    data-bs-target="#notes-history" type="button" role="tab" aria-controls="notes-history"
                    aria-selected="true">
                    <i class="mdi mdi-history"></i> Notes History
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link smooth-transition" id="notes-new-tab" data-bs-toggle="tab"
                    data-bs-target="#notes-new" type="button" role="tab" aria-controls="notes-new"
                    aria-selected="false">
                    <i class="mdi mdi-plus-circle"></i> Enter New Notes
                </button>
            </li>
        </ul>

        {{-- Tab content --}}
        <div class="tab-content mt-3">
            {{-- History Tab --}}
            <div class="tab-pane fade show active tab-content-fade" id="notes-history" role="tabpanel">
                <h5 class="mb-3"><i class="mdi mdi-note-multiple"></i> Clinical Notes History</h5>
                <div class="table-responsive">
                    <table class="table table-hover" style="width: 100%" id="encounter_history_list">
                        <thead class="table-light">
                            <th style="width: 100%;"><i class="mdi mdi-note-multiple"></i> Encounter Notes</th>
                        </thead>
                    </table>
                </div>
            </div>

            {{-- New Entry Tab --}}
            <div class="tab-pane fade tab-content-fade" id="notes-new" role="tabpanel">
                <h5 class="mb-3"><i class="mdi mdi-plus-circle"></i> Enter New Clinical Notes</h5>

                <input type="hidden" value="{{ $req_entry->service_id ?? 'ward_round' }}"
                    name="req_entry_service_id" required>
                <input type="hidden" value="{{ $req_entry->id ?? 'ward_round' }}" name="req_entry_id">
                <input type="hidden" value="{{ request()->get('patient_id') }}" name="patient_id"
                    id="encounter_patient_id__">
                <input type="hidden" value="{{ request()->get('queue_id') ?? 'ward_round' }}" name="queue_id">
                <input type="hidden" id="encounter_id__" name="encounter_id" value="{{ $encounter->id }}"
                    required>
                @if (request()->get('admission_req_id') != '')
                    <input type="hidden" value="{{ request()->get('admission_req_id') }}" name="queue_id">
                @endif

                <div class="form-group">
                    <div class="container">
                        <div class="accordion" id="accordionForProfile">
                            <div class="accordion-item">
                                <h4 class="accordion-header" id="flush-headingOne">
                                    <span class="collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#flush-collapseOne" aria-expanded="false"
                                        aria-controls="flush-collapseOne">
                                        <span class="fa fa-eye"></span>
                                        See Patient Profiles</span>
                                    <span class="fa fa-caret-down"></span>
                                </h4>
                                <div id="flush-collapseOne" class="accordion-collapse collapse"
                                    aria-labelledby="flush-headingOne" data-bs-parent="#accordionForProfile">
                                    <div class="accordion-body">
                                        <div class="d-flex justify-content-between">
                                            <h5>Forms/Profiles</h5>
                                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#profileModal"> <span class="fa fa-plus"></span>
                                                Fill New patient Profile
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table" id="profile_forms_table" style="width: 100%">
                                                <thead>
                                                    <th>#</th>
                                                    <th>Form Data</th>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                @if (appsettings('requirediagnosis', 0))
                    <!-- Modern Toggle Switch for Diagnosis -->
                    <div class="diagnosis-toggle-container mb-4">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded">
                            <div>
                                <strong class="d-block mb-1">Diagnosis Applicable?</strong>
                                <small class="text-muted">Toggle to show/hide diagnosis fields</small>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="diagnosisApplicable">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="diagnosis-fields-wrapper {{ (request()->get('queue_id') == 'ward_round' || request()->get('queue_id') === null) ? 'collapsed' : '' }}" id="diagnosisFields" style="{{ (request()->get('queue_id') == 'ward_round' || request()->get('queue_id') === null) ? 'display: none;' : '' }}">
                        <div class="form-group">
                            <label for="reasons_for_encounter_search">
                                Search ICPC-2 Reason(s) for Encounter/Diagnosis <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                class="form-control mb-2"
                                id="reasons_for_encounter_search"
                                placeholder="Type to search diagnosis codes... (e.g., 'A03', 'Fever', 'Hypertension')"
                                autocomplete="off">
                            <small class="text-muted d-block mb-2">
                                <i class="mdi mdi-information"></i> Type at least 2 characters to search. You can also add custom reasons.
                            </small>
                            <ul class="list-group" id="reasons_search_results" style="display: none; max-height: 250px; overflow-y: auto;"></ul>

                            <!-- Selected reasons display -->
                            <div id="selected_reasons_container" class="mt-3">
                                <label class="d-block mb-2"><strong>Selected Diagnoses:</strong></label>
                                <div id="selected_reasons_list" class="d-flex flex-wrap gap-2">
                                    <span class="text-muted"><i>No diagnoses selected yet</i></span>
                                </div>
                            </div>

                            <!-- Hidden input to store selected reason values -->
                            <input type="hidden" name="reasons_for_encounter_data" id="reasons_for_encounter_data" value="[]">
                        </div>
                        <br>
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="reasons_for_encounter_comment_1">Select Diagnosis Comment
                                        1(required)</label>
                                    <select class="form-control" name="reasons_for_encounter_comment_1"
                                        id="reasons_for_encounter_comment_1" required>
                                        <option value="NA">Not Applicable</option>
                                        <option value="QUERY">Query</option>
                                        <option value="DIFFRENTIAL">Diffrential</option>
                                        <option value="CONFIRMED">Confirmed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="reasons_for_encounter_comment_2"> Select Diagnosis Comment
                                        2(required)</label>
                                    <select class="form-control" name="reasons_for_encounter_comment_2"
                                        id="reasons_for_encounter_comment_2" required>
                                        <option value="NA">Not Applicable</option>
                                        <option value="ACUTE">Acute</option>
                                        <option value="CHRONIC">Chronic</option>
                                        <option value="RECURRENT">Recurrent</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                @endif

                <div>
                    <label for="doctor_diagnosis_text">Clinical Notes / Diagnosis <span class="text-danger">*</span></label>
                    <textarea name="doctor_diagnosis" id="doctor_diagnosis_text" class="form-control classic-editor2">{{ $encounter->notes }}</textarea>
                </div>

                <br>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" onclick="switch_tab(event,'nursing_notes_tab')"
                        class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Prev
                    </button>
                    <button type="button" onclick="saveDiagnosis()" id="save_diagnosis_btn"
                        class="btn btn-success">
                        <i class="fa fa-save"></i> Save
                    </button>
                </div>
                <div id="diagnosis_save_message" class="mt-2"></div>
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

.diagnosis-fields-wrapper {
    transition: opacity 0.3s ease;
}

.diagnosis-fields-wrapper:not(.collapsed) {
    display: block;
    opacity: 1;
}

.diagnosis-fields-wrapper.collapsed {
    display: none !important;
    opacity: 0;
}

/* AJAX Search Styling */
.diagnosis-badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    margin: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.diagnosis-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.reason-code {
    font-weight: 700;
    margin-right: 6px;
    padding: 2px 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 4px;
}

.reason-name {
    margin-right: 8px;
}

.remove-reason-btn {
    background: rgba(255,255,255,0.3);
    border: none;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    transition: all 0.2s ease;
}

.remove-reason-btn:hover {
    background: rgba(255,255,255,0.5);
    transform: rotate(90deg);
}

#reasons_search_results {
    position: relative;
    z-index: 1000;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: -8px;
}

#reasons_search_results .list-group-item {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

#reasons_search_results .list-group-item:hover {
    background-color: #f8f9fa;
}

#reasons_search_results .list-group-item.custom-reason-option {
    background-color: #e7f3ff;
    border-left: 4px solid #007bff;
    font-weight: 600;
}

/* Smooth tab transitions */
.tab-content-fade {
    animation: fadeIn 0.4s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.nav-tabs-custom .nav-link {
    border: 1px solid transparent;
    border-radius: 0.25rem 0.25rem 0 0;
    transition: all 0.3s ease;
}

.nav-tabs-custom .nav-link:hover {
    background-color: #f8f9fa;
    transform: translateY(-2px);
}

.nav-tabs-custom .nav-link.active {
    color: {{ appsettings('hos_color', '#007bff') }};
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
    font-weight: 600;
}

.smooth-transition {
    transition: all 0.3s ease;
}
</style>

<script>
// AJAX Search for Diagnosis Reasons
let selectedReasons = [];
let searchTimeout;

// Add a reason to selected list
function addReason(value, display, code, name) {
    // Check if already selected
    if (selectedReasons.some(r => r.value === value)) {
        return;
    }

    selectedReasons.push({value, display, code, name});
    updateSelectedReasonsDisplay();

    // Clear search
    $('#reasons_for_encounter_search').val('');
    $('#reasons_search_results').hide();
}

// Remove a reason from selected list
function removeReason(value) {
    selectedReasons = selectedReasons.filter(r => r.value !== value);
    updateSelectedReasonsDisplay();
}

// Update the visual display of selected reasons
function updateSelectedReasonsDisplay() {
    const container = $('#selected_reasons_list');

    if (selectedReasons.length === 0) {
        container.html('<span class="text-muted"><i>No diagnoses selected yet</i></span>');
        $('#reasons_for_encounter_data').val('[]');
        return;
    }

    let html = '';
    selectedReasons.forEach(reason => {
        html += `
            <div class="diagnosis-badge">
                <span class="reason-code">${reason.code}</span>
                <span class="reason-name">${reason.name}</span>
                <button type="button" class="remove-reason-btn" onclick="removeReason('${reason.value}')">
                    ×
                </button>
            </div>
        `;
    });

    container.html(html);

    // Update hidden input with JSON data
    $('#reasons_for_encounter_data').val(JSON.stringify(selectedReasons));
}

// Search for reasons via AJAX
function searchReasons(query) {
    if (query.length < 2) {
        $('#reasons_search_results').hide();
        return;
    }

    $.ajax({
        url: '/live-search-reasons',
        method: 'GET',
        data: { q: query },
        success: function(data) {
            const resultsContainer = $('#reasons_search_results');
            resultsContainer.empty();

            if (data.length === 0) {
                resultsContainer.append(`
                    <li class="list-group-item custom-reason-option" onclick="addReason('custom:${query}', 'Custom: ${query}', 'CUSTOM', '${query}')">
                        <i class="mdi mdi-plus-circle"></i> <strong>Add custom reason:</strong> "${query}"
                    </li>
                `);
            } else {
                data.forEach(reason => {
                    const display = `${reason.code} - ${reason.name}`;
                    const value = `${reason.code}-${reason.name}`;
                    resultsContainer.append(`
                        <li class="list-group-item" onclick="addReason('${value}', '${display}', '${reason.code}', '${reason.name}')">
                            <strong>${reason.code}</strong> ${reason.name}
                            <br><small class="text-muted">${reason.category} › ${reason.sub_category}</small>
                        </li>
                    `);
                });
            }

            resultsContainer.show();
        },
        error: function() {
            console.error('Error searching reasons');
        }
    });
}

$(document).ready(function() {
    // Initialize diagnosis fields state on page load
    function initializeDiagnosisFields() {
        const $checkbox = $('#diagnosisApplicable');
        const $diagnosisFields = $('#diagnosisFields');

        if ($checkbox.is(':checked')) {
            // Remove all hiding classes and inline styles
            $diagnosisFields.removeClass('collapsed hidden');
            $diagnosisFields.removeAttr('style');
            $diagnosisFields.css('display', 'block');
            $('#reasons_for_encounter_comment_1').prop('required', true);
            $('#reasons_for_encounter_comment_2').prop('required', true);
        } else {
            $diagnosisFields.addClass('collapsed').css('display', 'none');
            $('#reasons_for_encounter_comment_1').prop('required', false).val('NA');
            $('#reasons_for_encounter_comment_2').prop('required', false).val('NA');
        }
    }

    // Initialize on page load
    initializeDiagnosisFields();

    // Auto-toggle diagnosis if required and not ward round
    const isWardRound = {{ (request()->get('queue_id') == 'ward_round' || request()->get('queue_id') === null) ? 'true' : 'false' }};
    const requireDiagnosis = {{ appsettings('requirediagnosis', 0) ? 'true' : 'false' }};

    if (!isWardRound && requireDiagnosis) {
        // Trigger click to activate toggle and show fields
        $('#diagnosisApplicable').trigger('click');
    }

    // Toggle diagnosis fields
    $('#diagnosisApplicable').change(function() {
        const $diagnosisFields = $('#diagnosisFields');

        if ($(this).is(':checked')) {
            $diagnosisFields.removeClass('collapsed').slideDown(300);
            $('#reasons_for_encounter_comment_1').prop('required', true);
            $('#reasons_for_encounter_comment_2').prop('required', true);
        } else {
            $diagnosisFields.slideUp(300, function() {
                $(this).addClass('collapsed');
            });
            $('#reasons_for_encounter_comment_1').prop('required', false);
            $('#reasons_for_encounter_comment_2').prop('required', false);
        }
    });

    // Search input handler
    let searchTimeout;
    $('#reasons_for_encounter_search').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        searchTimeout = setTimeout(() => searchReasons(query), 300);
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#reasons_for_encounter_search, #reasons_search_results').length) {
            $('#reasons_search_results').hide();
        }
    });
});
</script>
