{{--
    Enhanced Unified Vitals Partial

    Usage: @include('admin.partials.unified_vitals', ['patient' => $patient])

    Features:
    - Complete vital signs form with all fields
    - Validation hints with normal ranges
    - Auto BMI calculation
    - Visual status indicators (normal/warning/critical)
    - History DataTable with comprehensive display

    Fields:
    - Blood Pressure (systolic/diastolic)
    - Temperature (°C)
    - Heart Rate (bpm)
    - Respiratory Rate (bpm)
    - SpO2 (%)
    - Pain Score (0-10)
    - Weight (kg)
    - Height (cm)
    - BMI (auto-calculated)
    - Blood Sugar (mg/dL)
    - Notes

    @see App\Models\VitalSign
    @see NursingWorkbenchController::storeVitals()
--}}

@php
    $vitalsContainerId = 'unified-vitals-' . uniqid();
@endphp

<style>
    .vital-input-group .form-control:focus {
        box-shadow: none;
    }
    .vital-hint {
        font-size: 0.7rem;
        color: #6c757d;
        margin-top: 2px;
    }
    .vital-input-group .input-group-text {
        min-width: 38px;
        justify-content: center;
    }
    .vital-input-group.vital-normal .form-control {
        border-color: #28a745;
        background-color: #f8fff9;
    }
    .vital-input-group.vital-warning .form-control {
        border-color: #ffc107;
        background-color: #fffdf5;
    }
    .vital-input-group.vital-critical .form-control {
        border-color: #dc3545;
        background-color: #fff5f5;
    }
    .bmi-display {
        font-size: 0.85rem;
        padding: 8px 12px;
        border-radius: 4px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .bmi-display.bmi-underweight { background: #fff3cd; border-color: #ffc107; }
    .bmi-display.bmi-normal { background: #d4edda; border-color: #28a745; }
    .bmi-display.bmi-overweight { background: #fff3cd; border-color: #ffc107; }
    .bmi-display.bmi-obese { background: #f8d7da; border-color: #dc3545; }
    .pain-scale {
        display: flex;
        gap: 2px;
    }
    .pain-btn {
        width: 28px;
        height: 28px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: #f8f9fa;
        cursor: pointer;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    .pain-btn:hover {
        background: #e9ecef;
    }
    .pain-btn.selected {
        color: white;
        border-color: transparent;
    }
    .pain-btn.pain-0 { }
    .pain-btn.pain-1, .pain-btn.pain-2, .pain-btn.pain-3 { }
    .pain-btn.pain-4, .pain-btn.pain-5, .pain-btn.pain-6 { }
    .pain-btn.pain-7, .pain-btn.pain-8, .pain-btn.pain-9, .pain-btn.pain-10 { }
    .pain-btn.selected.pain-0 { background: #28a745; }
    .pain-btn.selected.pain-1, .pain-btn.selected.pain-2, .pain-btn.selected.pain-3 { background: #28a745; }
    .pain-btn.selected.pain-4, .pain-btn.selected.pain-5, .pain-btn.selected.pain-6 { background: #ffc107; color: #212529; }
    .pain-btn.selected.pain-7, .pain-btn.selected.pain-8, .pain-btn.selected.pain-9, .pain-btn.selected.pain-10 { background: #dc3545; }
    .vitals-history-card {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        background: #fff;
    }
    .vitals-history-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .vital-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        margin: 2px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .vital-badge i {
        margin-right: 4px;
        font-size: 0.85rem;
    }
    .vital-badge.vital-normal { background: #d4edda; border-color: #c3e6cb; color: #155724; }
    .vital-badge.vital-warning { background: #fff3cd; border-color: #ffeeba; color: #856404; }
    .vital-badge.vital-critical { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
</style>

<div class="card border-0 shadow-sm unified-vitals-container" id="{{ $vitalsContainerId }}">
    <div class="card-header bg-white border-bottom">
        <ul class="nav nav-pills" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="pill" data-toggle="pill" href="#{{ $vitalsContainerId }}-new-vital" role="tab" aria-selected="true">
                    <i class="mdi mdi-plus-circle me-1"></i> New Vital Reading
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link vitals-history-tab-link" data-bs-toggle="pill" data-toggle="pill" href="#{{ $vitalsContainerId }}-vitals-history" role="tab" aria-selected="false">
                    <i class="mdi mdi-history me-1"></i> History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="pill" data-toggle="pill" href="#{{ $vitalsContainerId }}-vitals-charts" role="tab" aria-selected="false">
                    <i class="mdi mdi-chart-line me-1"></i> Trends
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-3">
        <div class="tab-content">
            <!-- New Vital Form -->
            <div class="tab-pane fade show active" id="{{ $vitalsContainerId }}-new-vital" role="tabpanel">
                <form class="unified-vital-form" method="post" action="{{ route('vitals.store') }}">
                    @csrf
                    <input type="hidden" name="patient_id" class="unified-vitals-patient-id" value="{{ $patient->id ?? '' }}">
                    <input type="hidden" name="bmi" class="vitals-bmi-hidden" value="">

                    <!-- Row 1: Primary Vitals -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-heart-pulse text-danger"></i> Blood Pressure <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="bp">
                                <span class="input-group-text bg-light"><i class="mdi mdi-speedometer"></i></span>
                                <input type="text" class="form-control vital-bp-input" name="bloodPressure"
                                       placeholder="120/80" pattern="\d{2,3}/\d{2,3}" required
                                       title="Format: systolic/diastolic (e.g., 120/80)">
                            </div>
                            <div class="vital-hint">Normal: 90-140 / 60-90 mmHg</div>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-thermometer text-warning"></i> Temperature <span class="text-danger">*</span>
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="temp">
                                <span class="input-group-text bg-light"><i class="mdi mdi-thermometer"></i></span>
                                <input type="number" class="form-control vital-temp-input" name="bodyTemperature"
                                       step="0.1" min="34" max="42" required placeholder="36.5">
                                <span class="input-group-text bg-light">°C</span>
                            </div>
                            <div class="vital-hint">Normal: 36.1-37.2°C</div>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-heart text-danger"></i> Heart Rate
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="hr">
                                <span class="input-group-text bg-light"><i class="mdi mdi-heart-pulse"></i></span>
                                <input type="number" class="form-control vital-hr-input" name="heartRate"
                                       min="30" max="250" placeholder="72">
                                <span class="input-group-text bg-light">bpm</span>
                            </div>
                            <div class="vital-hint">Normal: 60-100 bpm</div>
                        </div>
                    </div>

                    <!-- Row 2: Secondary Vitals -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-lungs text-primary"></i> Respiratory Rate
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="rr">
                                <span class="input-group-text bg-light"><i class="mdi mdi-weather-windy"></i></span>
                                <input type="number" class="form-control vital-rr-input" name="respiratoryRate"
                                       min="5" max="60" placeholder="16">
                                <span class="input-group-text bg-light">bpm</span>
                            </div>
                            <div class="vital-hint">Normal: 12-20 bpm</div>
                        </div>
                        <div class="col-md-4 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-percent text-info"></i> SpO2 (Oxygen)
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="spo2">
                                <span class="input-group-text bg-light"><i class="mdi mdi-percent"></i></span>
                                <input type="number" class="form-control vital-spo2-input" name="spo2"
                                       min="50" max="100" step="0.1" placeholder="98">
                                <span class="input-group-text bg-light">%</span>
                            </div>
                            <div class="vital-hint">Normal: 95-100%, Critical: &lt;90%</div>
                        </div>
                        <div class="col-md-4 col-12">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-emoticon-sad text-secondary"></i> Pain Score (0-10)
                            </label>
                            <div class="pain-scale">
                                @for($i = 0; $i <= 10; $i++)
                                    <button type="button" class="pain-btn pain-{{ $i }}" data-value="{{ $i }}">{{ $i }}</button>
                                @endfor
                            </div>
                            <input type="hidden" name="painScore" class="vital-pain-input" value="">
                            <div class="vital-hint">0=None, 1-3=Mild, 4-6=Moderate, 7-10=Severe</div>
                        </div>
                    </div>

                    <!-- Row 3: Measurements -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-weight text-success"></i> Weight
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-weight"></i></span>
                                <input type="number" class="form-control vital-weight-input" name="bodyWeight"
                                       step="0.1" min="0.5" max="500" placeholder="70">
                                <span class="input-group-text bg-light">kg</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-human-male-height text-primary"></i> Height
                            </label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="mdi mdi-ruler"></i></span>
                                <input type="number" class="form-control vital-height-input" name="height"
                                       step="0.1" min="30" max="300" placeholder="170">
                                <span class="input-group-text bg-light">cm</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-calculator text-secondary"></i> BMI
                            </label>
                            <div class="bmi-display vitals-bmi-display">
                                <span class="bmi-value">--</span>
                                <span class="bmi-class ms-2 small"></span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-water text-info"></i> Blood Sugar
                            </label>
                            <div class="input-group input-group-sm vital-input-group" data-vital="sugar">
                                <span class="input-group-text bg-light"><i class="mdi mdi-water"></i></span>
                                <input type="number" class="form-control vital-sugar-input" name="bloodSugar"
                                       step="0.1" min="20" max="600" placeholder="100">
                                <span class="input-group-text bg-light">mg/dL</span>
                            </div>
                            <div class="vital-hint">Fasting: 70-100, Random: &lt;140</div>
                        </div>
                    </div>

                    <!-- Row 4: Time & Notes -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-clock text-secondary"></i> Time Taken <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" class="form-control form-control-sm vitals-datetime-field"
                                   name="datetimeField" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted mb-1">
                                <i class="mdi mdi-note-text text-secondary"></i> Notes
                            </label>
                            <input type="text" class="form-control form-control-sm" name="otherNotes"
                                   placeholder="Optional notes (e.g., patient positioning, symptoms)...">
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="reset" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-refresh"></i> Clear
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-check"></i> Save Vitals
                        </button>
                    </div>
                </form>
            </div>

            <!-- History Table -->
            <div class="tab-pane fade" id="{{ $vitalsContainerId }}-vitals-history" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm table-hover w-100 unified-vitals-history-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Vital Signs Record</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Trends/Charts Tab -->
            <div class="tab-pane fade" id="{{ $vitalsContainerId }}-vitals-charts" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header py-2 bg-light">
                                <small class="fw-bold"><i class="mdi mdi-heart-pulse text-danger"></i> Blood Pressure</small>
                            </div>
                            <div class="card-body p-2">
                                <canvas class="vitals-bp-chart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header py-2 bg-light">
                                <small class="fw-bold"><i class="mdi mdi-thermometer text-warning"></i> Temperature</small>
                            </div>
                            <div class="card-body p-2">
                                <canvas class="vitals-temp-chart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header py-2 bg-light">
                                <small class="fw-bold"><i class="mdi mdi-heart text-danger"></i> Heart Rate & SpO2</small>
                            </div>
                            <div class="card-body p-2">
                                <canvas class="vitals-hr-chart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header py-2 bg-light">
                                <small class="fw-bold"><i class="mdi mdi-weight text-success"></i> Weight & BMI</small>
                            </div>
                            <div class="card-body p-2">
                                <canvas class="vitals-weight-chart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
/**
 * Enhanced Unified Vitals Module
 *
 * Features:
 * - Real-time validation with visual feedback
 * - Auto BMI calculation
 * - Pain scale selector
 * - Trend charts
 * - Comprehensive history display
 */

window.initUnifiedVitals = function(patientId, containerId) {
    if (!patientId) {
        console.error("initUnifiedVitals called without patientId");
        return;
    }

    var $container = containerId ? $('#' + containerId) : $('.unified-vitals-container').first();
    if ($container.length === 0) {
        console.error("Unified vitals container not found");
        return;
    }

    // Set form patient ID
    $container.find('.unified-vitals-patient-id').val(patientId);

    // Initialize DataTable for history
    initVitalsHistoryTable($container, patientId);

    // Initialize form interactions
    initVitalsFormInteractions($container);

    // Initialize timestamp
    setCurrentTimestamp($container);

    // Load charts when tab is shown
    $container.find('a[href$="-vitals-charts"]').on('shown.bs.tab show.bs.tab', function() {
        loadVitalsCharts($container, patientId);
    });
};

function initVitalsHistoryTable($container, patientId) {
    var $historyTable = $container.find('.unified-vitals-history-table');

    if ($.fn.DataTable.isDataTable($historyTable)) {
        $historyTable.DataTable().destroy();
    }

    $historyTable.DataTable({
        processing: true,
        serverSide: true,
        ajax: '/nursing-workbench/patient/' + patientId + '/vitals-history-dt',
        columns: [
            { data: 'info', name: 'info', orderable: false, searchable: false }
        ],
        ordering: false,
        lengthChange: false,
        pageLength: 10,
        searching: true,
        dom: "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6 text-end'<'btn btn-sm btn-outline-primary refresh-vitals-btn'>>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-5'i><'col-sm-7'p>>",
        language: {
            emptyTable: "<div class='text-center py-4'>" +
                           "<i class='mdi mdi-heart-pulse text-muted' style='font-size: 3rem;'></i>" +
                           "<p class='text-muted mt-2'>No vitals history found</p>" +
                       "</div>",
            search: "<i class='mdi mdi-magnify'></i>"
        },
        drawCallback: function() {
            $container.find('.refresh-vitals-btn').html('<i class="mdi mdi-refresh"></i> Refresh');
        }
    });

    // Refresh button handler
    $container.on('click', '.refresh-vitals-btn', function() {
        $historyTable.DataTable().ajax.reload();
    });
}

function initVitalsFormInteractions($container) {
    // Pain scale buttons
    $container.find('.pain-btn').on('click', function() {
        $container.find('.pain-btn').removeClass('selected');
        $(this).addClass('selected');
        $container.find('.vital-pain-input').val($(this).data('value'));
    });

    // BMI calculation
    $container.find('.vital-weight-input, .vital-height-input').on('input', function() {
        calculateAndDisplayBMI($container);
    });

    // Real-time validation coloring
    $container.find('.vital-bp-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'bp', $(this).val());
    });
    $container.find('.vital-temp-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'temp', $(this).val());
    });
    $container.find('.vital-hr-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'hr', $(this).val());
    });
    $container.find('.vital-rr-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'rr', $(this).val());
    });
    $container.find('.vital-spo2-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'spo2', $(this).val());
    });
    $container.find('.vital-sugar-input').on('input', function() {
        validateVitalInput($(this).closest('.vital-input-group'), 'sugar', $(this).val());
    });
}

function calculateAndDisplayBMI($container) {
    var weight = parseFloat($container.find('.vital-weight-input').val());
    var height = parseFloat($container.find('.vital-height-input').val());
    var $display = $container.find('.vitals-bmi-display');
    var $hidden = $container.find('.vitals-bmi-hidden');

    if (weight && height && height > 0) {
        var bmi = weight / Math.pow(height / 100, 2);
        bmi = Math.round(bmi * 10) / 10;

        var classification = '';
        var cssClass = '';

        if (bmi < 18.5) { classification = 'Underweight'; cssClass = 'bmi-underweight'; }
        else if (bmi < 25) { classification = 'Normal'; cssClass = 'bmi-normal'; }
        else if (bmi < 30) { classification = 'Overweight'; cssClass = 'bmi-overweight'; }
        else { classification = 'Obese'; cssClass = 'bmi-obese'; }

        $display.find('.bmi-value').text(bmi);
        $display.find('.bmi-class').text('(' + classification + ')');
        $display.removeClass('bmi-underweight bmi-normal bmi-overweight bmi-obese').addClass(cssClass);
        $hidden.val(bmi);
    } else {
        $display.find('.bmi-value').text('--');
        $display.find('.bmi-class').text('');
        $display.removeClass('bmi-underweight bmi-normal bmi-overweight bmi-obese');
        $hidden.val('');
    }
}

function validateVitalInput($group, type, value) {
    $group.removeClass('vital-normal vital-warning vital-critical');

    if (!value || value === '') return;

    var status = '';

    switch(type) {
        case 'bp':
            if (!value.includes('/')) return;
            var parts = value.split('/');
            var sys = parseInt(parts[0]);
            var dia = parseInt(parts[1]);
            if (isNaN(sys) || isNaN(dia)) return;
            if (sys > 180 || sys < 80 || dia > 110 || dia < 50) status = 'vital-critical';
            else if (sys > 140 || sys < 90 || dia > 90 || dia < 60) status = 'vital-warning';
            else status = 'vital-normal';
            break;
        case 'temp':
            var t = parseFloat(value);
            if (t < 34 || t > 39) status = 'vital-critical';
            else if (t < 36.1 || t > 38) status = 'vital-warning';
            else status = 'vital-normal';
            break;
        case 'hr':
            var hr = parseInt(value);
            if (hr < 50 || hr > 150) status = 'vital-critical';
            else if (hr < 60 || hr > 100) status = 'vital-warning';
            else status = 'vital-normal';
            break;
        case 'rr':
            var rr = parseInt(value);
            if (rr < 8 || rr > 30) status = 'vital-critical';
            else if (rr < 12 || rr > 20) status = 'vital-warning';
            else status = 'vital-normal';
            break;
        case 'spo2':
            var spo2 = parseFloat(value);
            if (spo2 < 90) status = 'vital-critical';
            else if (spo2 < 95) status = 'vital-warning';
            else status = 'vital-normal';
            break;
        case 'sugar':
            var sugar = parseFloat(value);
            if (sugar < 70 || sugar > 200) status = 'vital-critical';
            else if (sugar < 80 || sugar > 140) status = 'vital-warning';
            else status = 'vital-normal';
            break;
    }

    if (status) {
        $group.addClass(status);
    }
}

function setCurrentTimestamp($container) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $container.find('.vitals-datetime-field').val(now.toISOString().slice(0, 16));
}

// Store chart instances to destroy them before recreating
var vitalsChartInstances = {};

function destroyVitalsCharts($container) {
    var containerId = $container.attr('id');
    if (vitalsChartInstances[containerId]) {
        Object.values(vitalsChartInstances[containerId]).forEach(function(chart) {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        vitalsChartInstances[containerId] = {};
    }
}

function loadVitalsCharts($container, patientId) {
    var containerId = $container.attr('id');
    
    // Destroy existing charts first
    destroyVitalsCharts($container);
    vitalsChartInstances[containerId] = {};

    $.get('/nursing-workbench/patient/' + patientId + '/vitals?limit=30', function(vitals) {
        if (!vitals || vitals.length === 0) {
            $container.find('.vitals-bp-chart, .vitals-temp-chart, .vitals-hr-chart, .vitals-weight-chart')
                .closest('.card-body').html('<p class="text-muted text-center py-4">No vitals data available</p>');
            return;
        }

        // Reverse for chronological order
        vitals = vitals.reverse();

        var labels = vitals.map(function(v) {
            return v.time_taken ? new Date(v.time_taken).toLocaleDateString() : '';
        });

        var chartOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        // Blood Pressure Chart
        var bpCanvas = $container.find('.vitals-bp-chart')[0];
        if (bpCanvas && typeof Chart !== 'undefined' && bpCanvas.offsetParent !== null) {
            var systolic = [], diastolic = [];
            vitals.forEach(function(v) {
                if (v.blood_pressure && v.blood_pressure.includes('/')) {
                    var parts = v.blood_pressure.split('/');
                    systolic.push(parseInt(parts[0]) || null);
                    diastolic.push(parseInt(parts[1]) || null);
                } else {
                    systolic.push(null);
                    diastolic.push(null);
                }
            });

            var bpCtx = bpCanvas.getContext('2d');
            vitalsChartInstances[containerId].bp = new Chart(bpCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Systolic',
                        data: systolic,
                        borderColor: '#dc3545',
                        tension: 0.3,
                        fill: false,
                        spanGaps: true
                    }, {
                        label: 'Diastolic',
                        data: diastolic,
                        borderColor: '#007bff',
                        tension: 0.3,
                        fill: false,
                        spanGaps: true
                    }]
                },
                options: Object.assign({}, chartOptions, {
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: false } }
                })
            });
        }

        // Temperature Chart
        var tempCanvas = $container.find('.vitals-temp-chart')[0];
        if (tempCanvas && typeof Chart !== 'undefined' && tempCanvas.offsetParent !== null) {
            var tempCtx = tempCanvas.getContext('2d');
            vitalsChartInstances[containerId].temp = new Chart(tempCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Temperature (°C)',
                        data: vitals.map(function(v) { return v.temp ? parseFloat(v.temp) : null; }),
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255,193,7,0.1)',
                        tension: 0.3,
                        fill: true,
                        spanGaps: true
                    }]
                },
                options: Object.assign({}, chartOptions, {
                    plugins: { legend: { display: false } },
                    scales: { y: { min: 35, max: 40 } }
                })
            });
        }

        // Heart Rate & SpO2 Chart
        var hrCanvas = $container.find('.vitals-hr-chart')[0];
        if (hrCanvas && typeof Chart !== 'undefined' && hrCanvas.offsetParent !== null) {
            var hrCtx = hrCanvas.getContext('2d');
            vitalsChartInstances[containerId].hr = new Chart(hrCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Heart Rate',
                        data: vitals.map(function(v) { return v.heart_rate ? parseInt(v.heart_rate) : null; }),
                        borderColor: '#dc3545',
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y',
                        spanGaps: true
                    }, {
                        label: 'SpO2 (%)',
                        data: vitals.map(function(v) { return v.spo2 ? parseFloat(v.spo2) : null; }),
                        borderColor: '#17a2b8',
                        tension: 0.3,
                        fill: false,
                        yAxisID: 'y1',
                        spanGaps: true
                    }]
                },
                options: Object.assign({}, chartOptions, {
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { type: 'linear', position: 'left', title: { display: true, text: 'HR (bpm)' } },
                        y1: { type: 'linear', position: 'right', min: 80, max: 100, title: { display: true, text: 'SpO2 (%)' }, grid: { drawOnChartArea: false } }
                    }
                })
            });
        }

        // Weight Chart
        var weightCanvas = $container.find('.vitals-weight-chart')[0];
        if (weightCanvas && typeof Chart !== 'undefined' && weightCanvas.offsetParent !== null) {
            var weightCtx = weightCanvas.getContext('2d');
            vitalsChartInstances[containerId].weight = new Chart(weightCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: vitals.map(function(v) { return v.weight ? parseFloat(v.weight) : null; }),
                        borderColor: '#28a745',
                        tension: 0.3,
                        fill: false,
                        spanGaps: true
                    }]
                },
                options: Object.assign({}, chartOptions, {
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: false } }
                })
            });
        }
    });
}

// Form Submission Handler
$(document).off('submit.unifiedVitals', '.unified-vital-form').on('submit.unifiedVitals', '.unified-vital-form', function(e) {
    e.preventDefault();
    const btn = $(this).find('button[type="submit"]');
    const form = $(this);
    const $container = form.closest('.unified-vitals-container');
    const originalText = btn.html();

    btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

    $.ajax({
        url: form.attr('action'),
        method: 'POST',
        data: form.serialize(),
        success: function(response) {
            toastr.success('Vitals saved successfully');

            var currentPatientId = form.find('.unified-vitals-patient-id').val();

            form[0].reset();
            $container.find('.pain-btn').removeClass('selected');
            $container.find('.vitals-bmi-display .bmi-value').text('--');
            $container.find('.vitals-bmi-display .bmi-class').text('');
            $container.find('.vital-input-group').removeClass('vital-normal vital-warning vital-critical');
            setCurrentTimestamp($container);
            form.find('.unified-vitals-patient-id').val(currentPatientId);

            // Switch to history tab
            var $historyTabLink = $container.find('.vitals-history-tab-link');
            if($historyTabLink.length) {
                try {
                    var tab = new bootstrap.Tab($historyTabLink[0]);
                    tab.show();
                } catch(e) {
                    $historyTabLink.tab('show');
                }
            }

            // Reload DataTable
            var $historyTable = $container.find('.unified-vitals-history-table');
            if ($.fn.DataTable.isDataTable($historyTable)) {
                $historyTable.DataTable().ajax.reload();
            }
        },
        error: function(xhr) {
            let msg = 'Failed to save vitals';
            if(xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            toastr.error(msg);
        },
        complete: function() {
            btn.prop('disabled', false).html(originalText);
        }
    });
});
</script>
@endpush
@endonce
