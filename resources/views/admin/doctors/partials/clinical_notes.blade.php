{{-- Clinical Notes & Diagnosis: Combined History + New Entry --}}
<div class="card-modern">
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

                    <div class="diagnosis-fields-wrapper collapsed" id="diagnosisFields" style="display: none;">
                        <div class="form-group">
                            <label for="reasons_for_encounter_search">
                                Search ICPC-2 Reason(s) for Encounter/Diagnosis <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-2 mb-2">
                                <input type="text"
                                    class="form-control"
                                    id="reasons_for_encounter_search"
                                    placeholder="Type to search diagnosis codes... (e.g., 'A03', 'Fever', 'Hypertension')"
                                    autocomplete="off">
                                <div class="dropdown">
                                    <button class="btn btn-outline-warning dropdown-toggle" type="button" id="favoritesDropdownBtn"
                                            data-bs-toggle="dropdown" aria-expanded="false" onclick="loadDiagnosisFavorites()">
                                        <i class="fa fa-star"></i> Favorites
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" id="favorites_dropdown_menu" style="min-width: 280px; max-height: 300px; overflow-y: auto;">
                                        <li><span class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</span></li>
                                    </ul>
                                </div>
                            </div>
                            <small class="text-muted d-block mb-2">
                                <i class="mdi mdi-information"></i> Type at least 2 characters to search. You can also add custom reasons.
                            </small>
                            <ul class="list-group" id="reasons_search_results" style="display: none; max-height: 250px; overflow-y: auto;"></ul>

                            <!-- Selected diagnoses table with per-diagnosis comments -->
                            <div id="selected_reasons_container" class="mt-3">
                                <label class="d-block mb-2"><strong>Selected Diagnoses:</strong></label>
                                <div id="selected_reasons_list">
                                    <span class="text-muted"><i>No diagnoses selected yet</i></span>
                                </div>
                            </div>

                            <!-- Hidden input to store selected reason values -->
                            <input type="hidden" name="reasons_for_encounter_data" id="reasons_for_encounter_data" value="[]">

                            <!-- Save as favorite button (shown when diagnoses selected) -->
                            <div id="save_favorite_section" class="mt-2" style="display: none;">
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="showSaveFavoriteModal()">
                                    <i class="fa fa-star"></i> Save as Favorite
                                </button>
                            </div>
                        </div>
                        <br>
                        {{-- Global comments removed — now per-diagnosis in the table above --}}
                        {{-- Legacy hidden inputs for backward compatibility --}}
                        <input type="hidden" name="reasons_for_encounter_comment_1" id="reasons_for_encounter_comment_1" value="NA">
                        <input type="hidden" name="reasons_for_encounter_comment_2" id="reasons_for_encounter_comment_2" value="NA">
                    </div>
                    <hr>

<!-- Save Favorite Modal -->
<div class="modal fade" id="saveFavoriteModal" tabindex="-1" aria-labelledby="saveFavoriteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="saveFavoriteModalLabel">
                        <i class="fa fa-star text-warning"></i> Save Diagnosis Set
                    </h5>
                    <small class="text-muted">Save your current diagnoses as a reusable favorite</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="mb-3">
                    <label for="favorite_set_name" class="form-label fw-semibold">Set Name</label>
                    <input type="text" class="form-control form-control-lg" id="favorite_set_name" placeholder="e.g. Hypertension + Diabetes Workup" maxlength="100">
                    <div class="invalid-feedback">Please enter a name for this set.</div>
                </div>
                <div class="card bg-light border-0 mb-2">
                    <div class="card-body py-2 px-3">
                        <small class="fw-semibold text-muted d-block mb-1">Diagnoses to save:</small>
                        <div id="favorite_preview"></div>
                    </div>
                </div>
                <div id="favorite_save_feedback" style="display: none;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmSaveFavoriteBtn" onclick="confirmSaveFavorite()">
                    <i class="fa fa-star"></i> Save Favorite
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Insert Template Modal -->
<div class="modal fade" id="insertTemplateModal" tabindex="-1" aria-labelledby="insertTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold" id="insertTemplateModalLabel">
                        <i class="mdi mdi-file-document-edit text-primary"></i> Insert Template
                    </h5>
                    <small class="text-muted">Choose a template to insert into your clinical notes</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="mb-3">
                    <input type="text" class="form-control" id="templateSearchInput" placeholder="Search templates by name..." autocomplete="off">
                </div>
                <div id="templateModalContent" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center py-4 text-muted">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2 mb-0">Loading templates...</p>
                    </div>
                </div>
                <div id="templatePreviewSection" style="display: none;" class="mt-3">
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-semibold text-muted"><i class="mdi mdi-eye"></i> Preview</small>
                        <button type="button" class="btn btn-sm btn-link p-0" onclick="$('#templatePreviewSection').hide();">Hide</button>
                    </div>
                    <div id="templatePreviewBody" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;"></div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmInsertTemplateBtn" onclick="confirmInsertTemplate()" disabled>
                    <i class="mdi mdi-file-import"></i> Insert Selected
                </button>
            </div>
        </div>
    </div>
</div>
                @endif

                <div>
                    <label for="doctor_diagnosis_text">Clinical Notes / Diagnosis <span class="text-danger">*</span></label>

                    {{-- Template Selector --}}
                    <div class="d-flex align-items-center justify-content-between mb-2 mt-1">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-outline-primary btn-sm me-2" type="button" onclick="showInsertTemplateModal()">
                                <i class="mdi mdi-file-document-edit"></i> Insert Template
                            </button>
                        </div>
                        <small id="autosave_status_text" class="text-muted"><i class="mdi mdi-floppy"></i> <i class="mdi mdi-cloud-check-outline"></i> Autosave enabled</small>
                    </div>

                    <textarea name="doctor_diagnosis" id="doctor_diagnosis_text" class="form-control classic-editor2">{{ $encounter->notes }}</textarea>
                </div>

                <br>
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" onclick="switch_tab(event,'inj_imm_history_tab')"
                        class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Previous
                    </button>
                    <div>
                        <button type="button" onclick="saveDiagnosisAndNext()" id="save_diagnosis_next_btn"
                            class="btn btn-success me-2">
                            <i class="fa fa-save"></i> Save & Next
                        </button>
                        <button type="button" onclick="saveDiagnosis()" id="save_diagnosis_btn"
                            class="btn btn-outline-success">
                            <i class="fa fa-save"></i> Save
                        </button>
                    </div>
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

@push('scripts')
<script>
// AJAX Search for Diagnosis Reasons
var clinicalSelectedReasons = [];
var clinicalReasonSearchTimeout;

// Restore previously saved diagnosis from encounter on page load
(function() {
    var savedDiagnosis = @json($encounter->reasons_for_encounter ?? null);
    if (savedDiagnosis) {
        try {
            var parsed = (typeof savedDiagnosis === 'string') ? JSON.parse(savedDiagnosis) : savedDiagnosis;
            if (Array.isArray(parsed) && parsed.length > 0) {
                clinicalSelectedReasons = parsed.map(function(item) {
                    return {
                        value: item.value || item.code || item,
                        display: item.display || ((item.code || '') + ' - ' + (item.name || item.value || item)),
                        code: item.code || item.value || item,
                        name: item.name || item.value || item,
                        comment_1: item.comment_1 || 'NA',
                        comment_2: item.comment_2 || 'NA'
                    };
                });
            } else if (typeof parsed === 'string' && parsed.trim() !== '') {
                // Legacy comma-separated format
                parsed.split(',').forEach(function(val) {
                    val = val.trim();
                    if (val) {
                        clinicalSelectedReasons.push({
                            value: val, display: val, code: val, name: val,
                            comment_1: 'NA', comment_2: 'NA'
                        });
                    }
                });
            }
        } catch(e) {
            // If JSON parse fails, try comma-separated
            if (typeof savedDiagnosis === 'string' && savedDiagnosis.trim() !== '') {
                savedDiagnosis.split(',').forEach(function(val) {
                    val = val.trim();
                    if (val) {
                        clinicalSelectedReasons.push({
                            value: val, display: val, code: val, name: val,
                            comment_1: 'NA', comment_2: 'NA'
                        });
                    }
                });
            }
        }

        // If we restored diagnoses, click the toggle to enable it and render
        if (clinicalSelectedReasons.length > 0) {
            $(function() {
                // Defer the click so the change handler in new_encounter is registered first
                setTimeout(function() {
                    if (!$('#diagnosisApplicable').is(':checked')) {
                        $('#diagnosisApplicable').trigger('click');
                    }
                    updateSelectedReasonsDisplay();
                }, 200);
            });
        }
    }
})();

// Add a reason to selected list
function addReason(value, display, code, name) {
    // Check if already selected
    if (clinicalSelectedReasons.some(r => r.value === value)) {
        return;
    }

    clinicalSelectedReasons.push({value, display, code, name, comment_1: 'NA', comment_2: 'NA'});
    updateSelectedReasonsDisplay();

    // Clear search
    $('#reasons_for_encounter_search').val('');
    $('#reasons_search_results').hide();
}

// Remove a reason from selected list
function removeReason(value) {
    clinicalSelectedReasons = clinicalSelectedReasons.filter(r => r.value !== value);
    updateSelectedReasonsDisplay();
}

// Update per-diagnosis comment
function updateReasonComment(value, field, newVal) {
    const reason = clinicalSelectedReasons.find(r => r.value === value);
    if (reason) {
        reason[field] = newVal;
        // Update hidden input
        $('#reasons_for_encounter_data').val(JSON.stringify(clinicalSelectedReasons));
    }
}

// Update the visual display of selected reasons — now a table with per-diagnosis comments
function updateSelectedReasonsDisplay() {
    const container = $('#selected_reasons_list');

    if (clinicalSelectedReasons.length === 0) {
        container.html('<span class="text-muted"><i>No diagnoses selected yet</i></span>');
        $('#reasons_for_encounter_data').val('[]');
        $('#save_favorite_section').hide();
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
    html += '<thead class="table-light"><tr><th>Code</th><th>Diagnosis</th><th>Status</th><th>Course</th><th style="width:40px;"></th></tr></thead><tbody>';

    clinicalSelectedReasons.forEach(reason => {
        const escVal = reason.value.replace(/'/g, "\\'");
        html += `<tr>
            <td><span class="badge bg-primary">${reason.code}</span></td>
            <td>${reason.name}</td>
            <td>
                <select class="form-select form-select-sm" onchange="updateReasonComment('${escVal}', 'comment_1', this.value)">
                    <option value="NA" ${reason.comment_1 === 'NA' ? 'selected' : ''}>N/A</option>
                    <option value="QUERY" ${reason.comment_1 === 'QUERY' ? 'selected' : ''}>Query</option>
                    <option value="DIFFRENTIAL" ${reason.comment_1 === 'DIFFRENTIAL' ? 'selected' : ''}>Differential</option>
                    <option value="CONFIRMED" ${reason.comment_1 === 'CONFIRMED' ? 'selected' : ''}>Confirmed</option>
                </select>
            </td>
            <td>
                <select class="form-select form-select-sm" onchange="updateReasonComment('${escVal}', 'comment_2', this.value)">
                    <option value="NA" ${reason.comment_2 === 'NA' ? 'selected' : ''}>N/A</option>
                    <option value="ACUTE" ${reason.comment_2 === 'ACUTE' ? 'selected' : ''}>Acute</option>
                    <option value="CHRONIC" ${reason.comment_2 === 'CHRONIC' ? 'selected' : ''}>Chronic</option>
                    <option value="RECURRENT" ${reason.comment_2 === 'RECURRENT' ? 'selected' : ''}>Recurrent</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeReason('${escVal}')">
                    <i class="fa fa-times"></i>
                </button>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    container.html(html);

    // Update hidden input with JSON data
    $('#reasons_for_encounter_data').val(JSON.stringify(clinicalSelectedReasons));
    $('#save_favorite_section').show();
}

// ─── Favorites ───
function loadDiagnosisFavorites() {
    const menu = $('#favorites_dropdown_menu');
    menu.html('<li><span class="dropdown-item text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</span></li>');

    $.ajax({
        url: '{{ route("diagnosis-favorites.index") }}',
        method: 'GET',
        success: function(favorites) {
            menu.empty();
            if (favorites.length === 0) {
                menu.html('<li><span class="dropdown-item text-muted">No saved favorites</span></li>');
                return;
            }
            favorites.forEach(function(fav) {
                const count = fav.diagnoses ? fav.diagnoses.length : 0;
                menu.append(`
                    <li class="d-flex align-items-center px-2">
                        <a class="dropdown-item flex-grow-1 py-1" href="#" onclick="loadFavorite(${fav.id}, event)">
                            <i class="fa fa-star text-warning"></i> ${fav.name} <span class="badge bg-secondary">${count}</span>
                        </a>
                        <button class="btn btn-sm btn-outline-danger border-0 py-0" onclick="deleteFavorite(${fav.id}, event)" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    </li>
                `);
            });
        },
        error: function() {
            menu.html('<li><span class="dropdown-item text-danger">Error loading favorites</span></li>');
        }
    });
}

function loadFavorite(favoriteId, e) {
    e.preventDefault();
    $.ajax({
        url: '{{ route("diagnosis-favorites.index") }}',
        method: 'GET',
        success: function(favorites) {
            const fav = favorites.find(f => f.id === favoriteId);
            if (!fav || !fav.diagnoses) return;

            fav.diagnoses.forEach(function(diag) {
                const value = diag.code + '-' + diag.name;
                if (!clinicalSelectedReasons.some(r => r.value === value)) {
                    clinicalSelectedReasons.push({
                        value: value,
                        display: diag.code + ' - ' + diag.name,
                        code: diag.code,
                        name: diag.name,
                        comment_1: diag.comment_1 || 'NA',
                        comment_2: diag.comment_2 || 'NA'
                    });
                }
            });

            updateSelectedReasonsDisplay();
        }
    });
}

function deleteFavorite(favoriteId, e) {
    e.preventDefault();
    e.stopPropagation();
    if (!confirm('Delete this favorite?')) return;

    $.ajax({
        url: '/diagnosis-favorites/' + favoriteId,
        method: 'DELETE',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function() {
            loadDiagnosisFavorites();
        }
    });
}

function showSaveFavoriteModal() {
    // Reset modal state
    $('#favorite_set_name').val('').removeClass('is-invalid');
    $('#favorite_save_feedback').hide();
    $('#confirmSaveFavoriteBtn').prop('disabled', false).html('<i class="fa fa-star"></i> Save Favorite');

    // Show preview of what will be saved
    let preview = '';
    clinicalSelectedReasons.forEach(function(r) {
        preview += '<span class="badge bg-primary me-1 mb-1 py-1 px-2">' + r.code + ' — ' + r.name + '</span> ';
    });
    $('#favorite_preview').html(preview);

    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('saveFavoriteModal'));
    modal.show();

    // Focus input after modal opens
    $('#saveFavoriteModal').off('shown.bs.modal').on('shown.bs.modal', function() {
        $('#favorite_set_name').focus();
    });

    // Allow Enter key to save
    $('#favorite_set_name').off('keypress').on('keypress', function(e) {
        if (e.which === 13) { e.preventDefault(); confirmSaveFavorite(); }
    });
}

function confirmSaveFavorite() {
    const nameInput = $('#favorite_set_name');
    const name = nameInput.val().trim();
    const feedback = $('#favorite_save_feedback');
    const btn = $('#confirmSaveFavoriteBtn');

    if (!name) {
        nameInput.addClass('is-invalid').focus();
        return;
    }
    nameInput.removeClass('is-invalid');

    const diagnoses = clinicalSelectedReasons.map(r => ({
        code: r.code,
        name: r.name,
        comment_1: r.comment_1 || 'NA',
        comment_2: r.comment_2 || 'NA'
    }));

    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.ajax({
        url: '{{ route("diagnosis-favorites.store") }}',
        method: 'POST',
        data: {
            name: name,
            diagnoses: diagnoses,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                feedback.html('<div class="alert alert-success d-flex align-items-center py-2 px-3 mb-0"><i class="fa fa-check-circle me-2"></i> Saved as <strong class="ms-1">"' + name + '"</strong></div>').show();
                btn.addClass('btn-success').removeClass('btn-warning').html('<i class="fa fa-check"></i> Saved!');
                setTimeout(function() {
                    $('#saveFavoriteModal .btn-close').trigger('click');
                }, 800);
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || 'Unknown error';
            feedback.html('<div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-0"><i class="fa fa-exclamation-circle me-2"></i> ' + msg + '</div>').show();
            btn.prop('disabled', false).html('<i class="fa fa-star"></i> Save Favorite');
        }
    });
}

// ─── Insert Template Modal ───
var _templateData = []; // cached template groups
var _selectedTemplateId = null;
var _templateModalLoaded = false;

function showInsertTemplateModal() {
    _selectedTemplateId = null;
    $('#templateSearchInput').val('');
    $('#templatePreviewSection').hide();
    $('#confirmInsertTemplateBtn').prop('disabled', true);

    var modal = new bootstrap.Modal(document.getElementById('insertTemplateModal'));
    modal.show();

    if (!_templateModalLoaded) {
        loadTemplatesForModal();
    } else {
        renderTemplateList(_templateData, '');
    }

    // Focus search after modal opens
    $('#insertTemplateModal').off('shown.bs.modal').on('shown.bs.modal', function() {
        $('#templateSearchInput').focus();
    });

    // Live search
    $('#templateSearchInput').off('input').on('input', function() {
        renderTemplateList(_templateData, $(this).val().trim().toLowerCase());
    });
}

function loadTemplatesForModal() {
    const clinicId = '{{ $clinic->id ?? "" }}';
    $.get('{{ route("clinic-note-templates.by-clinic") }}', { clinic_id: clinicId }, function(response) {
        if (response.success && response.groups && response.groups.length > 0) {
            _templateData = response.groups;
            _templateModalLoaded = true;
            renderTemplateList(_templateData, '');
        } else {
            $('#templateModalContent').html('<div class="text-center py-4 text-muted"><i class="mdi mdi-file-document-outline fa-2x"></i><p class="mt-2">No templates available for this clinic.</p></div>');
        }
    }).fail(function() {
        $('#templateModalContent').html('<div class="text-center py-4 text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><p class="mt-2">Failed to load templates</p></div>');
    });
}

function renderTemplateList(groups, filter) {
    const container = $('#templateModalContent');
    container.empty();
    let anyMatch = false;

    groups.forEach(function(group) {
        let items = '';
        group.templates.forEach(function(t) {
            // Filter by name or category
            if (filter && t.name.toLowerCase().indexOf(filter) === -1 && group.category.toLowerCase().indexOf(filter) === -1) return;
            anyMatch = true;
            const globalBadge = t.is_global ? ' <span class="badge bg-info-subtle text-info">Global</span>' : '';
            const desc = t.description ? '<br><small class="text-muted">' + t.description + '</small>' : '';
            const selectedClass = _selectedTemplateId === t.id ? 'active' : '';
            items += '<a href="#" class="list-group-item list-group-item-action py-2 px-3 template-item ' + selectedClass + '" data-template-id="' + t.id + '" data-template-content="' + encodeURIComponent(t.content) + '" onclick="selectTemplate(' + t.id + ', this, event)">' +
                '<div class="d-flex justify-content-between align-items-start">' +
                '<div><strong>' + t.name + '</strong>' + globalBadge + desc + '</div>' +
                '<i class="mdi mdi-chevron-right text-muted"></i>' +
                '</div></a>';
        });
        if (items) {
            container.append('<div class="mb-2"><div class="px-3 py-1 bg-light border-bottom"><small class="fw-semibold text-muted text-uppercase"><i class="mdi mdi-tag"></i> ' + group.category + '</small></div><div class="list-group list-group-flush">' + items + '</div></div>');
        }
    });

    if (!anyMatch) {
        container.html('<div class="text-center py-4 text-muted"><i class="mdi mdi-magnify fa-2x"></i><p class="mt-2 mb-0">No templates matching "' + (filter || '') + '"</p></div>');
    }
}

function selectTemplate(templateId, el, e) {
    e.preventDefault();
    _selectedTemplateId = templateId;

    // Highlight selected
    $('.template-item').removeClass('active');
    $(el).addClass('active');

    // Enable insert button
    $('#confirmInsertTemplateBtn').prop('disabled', false);

    // Show preview
    let content = decodeURIComponent($(el).data('template-content'));
    $('#templatePreviewBody').html(content);
    $('#templatePreviewSection').show();
}

function confirmInsertTemplate() {
    if (!_selectedTemplateId) return;

    const item = $('.template-item[data-template-id="' + _selectedTemplateId + '"]');
    let content = decodeURIComponent(item.data('template-content'));
    if (!content) return;

    // Replace placeholders
    const placeholders = {
        '@{{patient_name}}': '{{ ($patient->surname ?? "") . " " . ($patient->first_name ?? "") . " " . ($patient->other_names ?? "") }}',
        '@{{patient_file_no}}': '{{ $patient->file_no ?? "" }}',
        '@{{patient_dob}}': '{{ isset($patient->dob) ? \Carbon\Carbon::parse($patient->dob)->format("M j, Y") : "" }}',
        '@{{patient_age}}': '{{ isset($patient->dob) ? \Carbon\Carbon::parse($patient->dob)->age . " years" : "" }}',
        '@{{patient_sex}}': '{{ $patient->sex ?? "" }}',
        '@{{patient_phone}}': '{{ $patient->phone ?? "" }}',
        '@{{patient_address}}': '{{ $patient->address ?? "" }}',
        '@{{patient_blood_group}}': '{{ $patient->blood_group ?? "" }}',
        '@{{doctor_name}}': '{{ ($doctor->surname ?? "") . " " . ($doctor->first_name ?? "") }}',
        '@{{clinic_name}}': '{{ $clinic->name ?? "" }}',
        '@{{date}}': new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
        '@{{date_short}}': new Date().toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }),
        '@{{encounter_id}}': '{{ $encounter->id ?? "" }}',
    };
    for (const [placeholder, value] of Object.entries(placeholders)) {
        content = content.replaceAll(placeholder, value);
    }

    // Find CKEditor
    const editorElement = document.querySelector('#doctor_diagnosis_text');
    let editor = null;
    if (window.editor && typeof window.editor.setData === 'function') {
        editor = window.editor;
    } else if (editorElement && editorElement.ckeditorInstance) {
        editor = editorElement.ckeditorInstance;
    } else if (typeof window.classicEditors !== 'undefined') {
        window.classicEditors.forEach(function(ed) {
            if (ed.sourceElement && ed.sourceElement.id === 'doctor_diagnosis_text') editor = ed;
        });
    }

    if (editor) {
        const currentContent = editor.getData();
        if (currentContent && currentContent.trim() !== '' && currentContent !== '<p>&nbsp;</p>') {
            editor.setData(currentContent + content);
        } else {
            editor.setData(content);
        }
    } else {
        const $ta = $('#doctor_diagnosis_text');
        const current = $ta.val();
        if (current && current.trim()) {
            $ta.val(current + '\n' + content);
        } else {
            $ta.val(content);
        }
    }

    // Close modal
    $('#insertTemplateModal .btn-close').trigger('click');
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
    // Listen for when Clinical Notes tab is shown
    $('#clinical_notes_tab').on('shown.bs.tab', function() {
        const isWardRound = {{ (request()->get('queue_id') == 'ward_round' || request()->get('queue_id') === null) ? 'true' : 'false' }};
        const requireDiagnosis = {{ appsettings('requirediagnosis', 0) ? 'true' : 'false' }};
        const $checkbox = $('#diagnosisApplicable');

        // If not ward round and diagnosis is required, trigger the toggle
        if (!isWardRound && requireDiagnosis && !$checkbox.is(':checked')) {
            $checkbox.trigger('click');
        }
    });

    // Toggle diagnosis fields
    $('#diagnosisApplicable').change(function() {
        const $diagnosisFields = $('#diagnosisFields');

        if ($(this).is(':checked')) {
            $diagnosisFields.removeClass('collapsed').slideDown(300);
        } else {
            $diagnosisFields.slideUp(300, function() {
                $(this).addClass('collapsed');
            });
        }
    });

    // Search input handler
    var clinicalSearchTimeout;
    $('#reasons_for_encounter_search').on('input', function() {
        clearTimeout(clinicalSearchTimeout);
        const query = $(this).val();
        clinicalSearchTimeout = setTimeout(() => searchReasons(query), 300);
    });

    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#reasons_for_encounter_search, #reasons_search_results').length) {
            $('#reasons_search_results').hide();
        }
    });

});

// Note Templates and Insert Template functions are in the showInsertTemplateModal/confirmInsertTemplate functions above
</script>
@endpush
