{{--
    Ward Dashboard Partial

    Usage: @include('admin.partials.ward_dashboard')

    Features:
    - Ward occupancy overview
    - Bed status visualization (available, occupied, maintenance, reserved)
    - Admission/Discharge queues
    - Bed assignment interface
    - Real-time updates

    @see App\Models\Ward
    @see App\Models\Bed
    @see App\Models\AdmissionRequest
--}}

<style>
    /* Ward Dashboard Styles */
    .ward-dashboard {
        padding: 1.5rem;
        max-width: 100%;
    }

    .ward-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .ward-header h4 {
        margin: 0;
        color: #333;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ward-summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid;
        transition: transform 0.2s;
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .summary-card.total { border-left-color: #007bff; }
    .summary-card.occupied { border-left-color: #dc3545; }
    .summary-card.available { border-left-color: #28a745; }
    .summary-card.pending { border-left-color: #ffc107; }

    .summary-card-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        opacity: 0.7;
    }

    .summary-card h3 {
        font-size: 2rem;
        margin: 0 0 0.25rem 0;
        font-weight: 700;
    }

    .summary-card p {
        margin: 0;
        color: #6c757d;
        font-size: 0.9rem;
    }

    /* Ward Grid */
    .ward-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .ward-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .ward-card-header {
        background: linear-gradient(135deg, var(--hospital-primary, #007bff), #0056b3);
        color: white;
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ward-card-header h5 {
        margin: 0;
        font-weight: 600;
    }

    .ward-card-header .ward-type-badge {
        background: rgba(255,255,255,0.2);
        padding: 0.25rem 0.75rem;
        border-radius: 1rem;
        font-size: 0.75rem;
        text-transform: uppercase;
    }

    .ward-card-stats {
        display: flex;
        justify-content: space-around;
        padding: 1rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
    }

    .ward-stat {
        text-align: center;
    }

    .ward-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }

    .ward-stat-label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
    }

    .ward-stat-value.text-success { color: #28a745; }
    .ward-stat-value.text-danger { color: #dc3545; }
    .ward-stat-value.text-warning { color: #ffc107; }

    .ward-occupancy-bar {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        margin: 0.5rem 1rem;
        overflow: hidden;
    }

    .ward-occupancy-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s;
    }

    .occupancy-low { background: #28a745; }
    .occupancy-medium { background: #ffc107; }
    .occupancy-high { background: #dc3545; }

    /* Bed Grid */
    .bed-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 0.75rem;
        padding: 1rem;
    }

    .bed-item {
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .bed-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .bed-item.available {
        background: #d4edda;
        border-color: #c3e6cb;
    }

    .bed-item.available:hover {
        border-color: #28a745;
    }

    .bed-item.occupied {
        background: #f8d7da;
        border-color: #f5c6cb;
    }

    .bed-item.occupied:hover {
        border-color: #dc3545;
    }

    .bed-item.maintenance {
        background: #fff3cd;
        border-color: #ffeeba;
    }

    .bed-item.reserved {
        background: #d1ecf1;
        border-color: #bee5eb;
    }

    .bed-icon {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .bed-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #333;
    }

    .bed-patient {
        font-size: 0.7rem;
        color: #666;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Action Queues */
    .action-queues {
        margin-top: 2rem;
    }

    .queue-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .queue-tab {
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
        position: relative;
    }

    .queue-tab:hover {
        background: #e9ecef;
    }

    .queue-tab.active {
        background: var(--hospital-primary, #007bff);
        color: white;
        border-color: var(--hospital-primary, #007bff);
    }

    .queue-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: white;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 600;
    }

    .queue-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .queue-item-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
        transition: background 0.2s;
    }

    .queue-item-card:hover {
        background: #f8f9fa;
    }

    .queue-item-card:last-child {
        border-bottom: none;
    }

    .queue-patient-info {
        flex: 1;
    }

    .queue-patient-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.25rem;
    }

    .queue-patient-details {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .queue-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Bed Assignment Modal Specific */
    .bed-selection-grid {
        max-height: 300px;
        overflow-y: auto;
    }

    .checklist-container {
        max-height: 400px;
        overflow-y: auto;
    }

    .checklist-item {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid #e9ecef;
        transition: background 0.2s;
    }

    .checklist-item:hover {
        background: #f8f9fa;
    }

    .checklist-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-right: 1rem;
    }

    .checklist-item.completed {
        background: #d4edda;
    }

    .checklist-item.waived {
        background: #fff3cd;
        text-decoration: line-through;
        opacity: 0.7;
    }

    .checklist-progress {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .progress-bar-container {
        flex: 1;
        height: 10px;
        background: #e9ecef;
        border-radius: 5px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: #28a745;
        transition: width 0.3s;
    }

    .progress-text {
        font-weight: 600;
        min-width: 60px;
        text-align: right;
    }

    /* Legend */
    .bed-legend {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
    }

    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        border: 2px solid;
    }

    .legend-color.available { background: #d4edda; border-color: #c3e6cb; }
    .legend-color.occupied { background: #f8d7da; border-color: #f5c6cb; }
    .legend-color.maintenance { background: #fff3cd; border-color: #ffeeba; }
    .legend-color.reserved { background: #d1ecf1; border-color: #bee5eb; }
</style>

<!-- Ward Dashboard View -->
<div class="ward-dashboard" id="ward-dashboard-content">
    <div class="ward-header">
        <h4><i class="mdi mdi-hospital-building"></i> Ward Dashboard</h4>
        <div>
            <button class="btn btn-outline-primary btn-sm" id="refresh-ward-dashboard">
                <i class="mdi mdi-refresh"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="ward-summary-cards">
        <div class="summary-card total">
            <div class="summary-card-icon"><i class="mdi mdi-bed"></i></div>
            <h3 id="total-beds-count">0</h3>
            <p>Total Beds</p>
        </div>
        <div class="summary-card occupied">
            <div class="summary-card-icon"><i class="mdi mdi-account-check"></i></div>
            <h3 id="occupied-beds-count">0</h3>
            <p>Occupied</p>
        </div>
        <div class="summary-card available">
            <div class="summary-card-icon"><i class="mdi mdi-bed-empty"></i></div>
            <h3 id="available-beds-count">0</h3>
            <p>Available</p>
        </div>
        <div class="summary-card pending">
            <div class="summary-card-icon"><i class="mdi mdi-clock-alert"></i></div>
            <h3 id="pending-admissions-count">0</h3>
            <p>Pending Admission</p>
        </div>
    </div>

    <!-- Legend -->
    <div class="bed-legend">
        <div class="legend-item">
            <span class="legend-color available"></span>
            <span>Available</span>
        </div>
        <div class="legend-item">
            <span class="legend-color occupied"></span>
            <span>Occupied</span>
        </div>
        <div class="legend-item">
            <span class="legend-color maintenance"></span>
            <span>Maintenance</span>
        </div>
        <div class="legend-item">
            <span class="legend-color reserved"></span>
            <span>Reserved</span>
        </div>
    </div>

    <!-- Action Queues -->
    <div class="action-queues">
        <div class="queue-tabs">
            <button class="queue-tab active" data-queue="admission">
                <i class="mdi mdi-login"></i> Admission Queue
                <span class="queue-badge" id="admission-queue-badge">0</span>
            </button>
            <button class="queue-tab" data-queue="discharge">
                <i class="mdi mdi-logout"></i> Discharge Queue
                <span class="queue-badge" id="discharge-queue-badge">0</span>
            </button>
        </div>

        <div class="queue-content" id="queue-content">
            <!-- Admission Queue -->
            <div class="queue-panel" id="admission-queue-panel">
                <div id="admission-queue-list">
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-loading mdi-spin mdi-48px"></i>
                        <p>Loading admission queue...</p>
                    </div>
                </div>
            </div>

            <!-- Discharge Queue -->
            <div class="queue-panel" id="discharge-queue-panel" style="display: none;">
                <div id="discharge-queue-list">
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-loading mdi-spin mdi-48px"></i>
                        <p>Loading discharge queue...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ward Grid -->
    <h5 class="mt-4 mb-3"><i class="mdi mdi-view-grid"></i> Wards & Beds</h5>
    <div class="ward-grid" id="ward-grid">
        <div class="text-center py-4 text-muted">
            <i class="mdi mdi-loading mdi-spin mdi-48px"></i>
            <p>Loading wards...</p>
        </div>
    </div>
</div>

<!-- Bed Assignment Modal -->
<div class="modal fade" id="bedAssignmentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-bed"></i> Assign Bed to Patient</h5>
                <button type="button" class="close text-white" data-dismiss="modal" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Patient Info -->
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="assign-patient-name">--</strong>
                                <br><small class="text-muted">File: <span id="assign-patient-file">--</span></small>
                            </div>
                            <div>
                                <span class="badge bg-info" id="assign-admission-status">Pending</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Admission Checklist -->
                <div id="admission-checklist-step">
                    <h6><i class="mdi mdi-checkbox-marked-outline"></i> Admission Checklist</h6>
                    <p class="text-muted small">Complete or waive all items before assigning a bed.</p>

                    <div class="checklist-progress">
                        <span><i class="mdi mdi-checkbox-multiple-marked"></i> Progress:</span>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="checklist-progress-bar" style="width: 0%"></div>
                        </div>
                        <span class="progress-text" id="checklist-progress-text">0%</span>
                    </div>

                    <div class="checklist-container" id="admission-checklist-items">
                        <!-- Checklist items will be loaded here -->
                    </div>
                </div>

                <!-- Step 2: Bed Selection -->
                <div id="bed-selection-step" style="display: none;">
                    <h6><i class="mdi mdi-bed-empty"></i> Select Available Bed</h6>
                    <div class="mb-3">
                        <select class="form-control" id="ward-filter-select">
                            <option value="">All Wards</option>
                        </select>
                    </div>
                    <div class="bed-selection-grid" id="available-beds-grid">
                        <!-- Available beds will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="proceed-to-bed-selection" style="display: none;">
                    <i class="mdi mdi-arrow-right"></i> Proceed to Bed Selection
                </button>
                <button type="button" class="btn btn-success" id="confirm-bed-assignment" style="display: none;">
                    <i class="mdi mdi-check"></i> Confirm Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Discharge Modal -->
<div class="modal fade" id="dischargeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="mdi mdi-logout"></i> Process Discharge</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Patient Info -->
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="discharge-patient-name">--</strong>
                                <br><small class="text-muted">File: <span id="discharge-patient-file">--</span> | Bed: <span id="discharge-bed-name">--</span></small>
                            </div>
                            <div>
                                <span class="badge bg-warning text-dark">Discharge Requested</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discharge Checklist -->
                <h6><i class="mdi mdi-checkbox-marked-outline"></i> Discharge Checklist</h6>
                <p class="text-muted small">Complete all items before releasing the bed.</p>

                <div class="checklist-progress">
                    <span><i class="mdi mdi-checkbox-multiple-marked"></i> Progress:</span>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="discharge-checklist-progress-bar" style="width: 0%"></div>
                    </div>
                    <span class="progress-text" id="discharge-checklist-progress-text">0%</span>
                </div>

                <div class="checklist-container" id="discharge-checklist-items">
                    <!-- Checklist items will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-discharge" disabled>
                    <i class="mdi mdi-check"></i> Complete Discharge & Release Bed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bed Details Modal -->
<div class="modal fade" id="bedDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-bed"></i> <span id="bed-detail-name">Bed Details</span></h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="bed-details-content">
                <!-- Bed details will be loaded here -->
            </div>
            <div class="modal-footer" id="bed-details-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
/**
 * Ward Dashboard JavaScript Module
 */
window.WardDashboard = (function() {
    let currentAdmissionId = null;
    let currentChecklistId = null;
    let selectedBedId = null;
    let currentDischargeId = null;

    function init() {
        loadDashboardData();
        bindEvents();
    }

    function bindEvents() {
        // Refresh button
        $('#refresh-ward-dashboard').on('click', loadDashboardData);

        // Queue tabs
        $('.queue-tab').on('click', function() {
            $('.queue-tab').removeClass('active');
            $(this).addClass('active');

            var queue = $(this).data('queue');
            $('.queue-panel').hide();
            $('#' + queue + '-queue-panel').show();
        });

        // Checklist item toggle
        $(document).on('change', '.checklist-checkbox', function() {
            var itemId = $(this).data('item-id');
            var checklistType = $(this).data('checklist-type');
            var checked = $(this).is(':checked');

            toggleChecklistItem(itemId, checklistType, checked);
        });

        // Waive checklist item
        $(document).on('click', '.waive-item-btn', function() {
            var itemId = $(this).data('item-id');
            var checklistType = $(this).data('checklist-type');
            waiveChecklistItem(itemId, checklistType);
        });

        // Proceed to bed selection
        $('#proceed-to-bed-selection').on('click', function() {
            $('#admission-checklist-step').hide();
            $('#bed-selection-step').show();
            $(this).hide();
            $('#confirm-bed-assignment').show();
            loadAvailableBeds();
        });

        // Confirm bed assignment
        $('#confirm-bed-assignment').on('click', confirmBedAssignment);

        // Confirm discharge
        $('#confirm-discharge').on('click', confirmDischarge);

        // Bed item click
        $(document).on('click', '.bed-item', function() {
            var bedId = $(this).data('bed-id');
            var status = $(this).data('status');

            if ($(this).closest('#available-beds-grid').length) {
                // Selecting bed for assignment
                $('.bed-item').removeClass('selected');
                $(this).addClass('selected');
                selectedBedId = bedId;
            } else {
                // Viewing bed details
                showBedDetails(bedId, status);
            }
        });

        // Ward filter
        $('#ward-filter-select').on('change', function() {
            loadAvailableBeds($(this).val());
        });
    }

    function loadDashboardData() {
        loadSummaryStats();
        loadWards();
        loadAdmissionQueue();
        loadDischargeQueue();
    }

    function loadSummaryStats() {
        $.get('/nursing-workbench/ward-dashboard/stats', function(data) {
            $('#total-beds-count').text(data.total_beds || 0);
            $('#occupied-beds-count').text(data.occupied_beds || 0);
            $('#available-beds-count').text(data.available_beds || 0);
            $('#pending-admissions-count').text(data.pending_admissions || 0);
        }).fail(function() {
            console.error('Failed to load ward stats');
        });
    }

    function loadWards() {
        $.get('/nursing-workbench/ward-dashboard/wards', function(wards) {
            var html = '';

            if (!wards || wards.length === 0) {
                html = '<div class="col-12 text-center py-4 text-muted">' +
                       '<i class="mdi mdi-hospital-building mdi-48px"></i>' +
                       '<p>No wards configured. Please contact administrator.</p></div>';
                $('#ward-grid').html(html);
                return;
            }

            wards.forEach(function(ward) {
                var occupancyRate = ward.capacity > 0 ?
                    Math.round((ward.occupied_beds / ward.capacity) * 100) : 0;
                var occupancyClass = occupancyRate < 50 ? 'occupancy-low' :
                                    occupancyRate < 80 ? 'occupancy-medium' : 'occupancy-high';

                html += '<div class="ward-card">';
                html += '<div class="ward-card-header">';
                html += '<h5>' + ward.name + '</h5>';
                html += '<span class="ward-type-badge">' + ward.type + '</span>';
                html += '</div>';

                html += '<div class="ward-card-stats">';
                html += '<div class="ward-stat">';
                html += '<div class="ward-stat-value text-success">' + ward.available_beds + '</div>';
                html += '<div class="ward-stat-label">Available</div></div>';
                html += '<div class="ward-stat">';
                html += '<div class="ward-stat-value text-danger">' + ward.occupied_beds + '</div>';
                html += '<div class="ward-stat-label">Occupied</div></div>';
                html += '<div class="ward-stat">';
                html += '<div class="ward-stat-value">' + ward.capacity + '</div>';
                html += '<div class="ward-stat-label">Total</div></div>';
                html += '</div>';

                html += '<div class="ward-occupancy-bar">';
                html += '<div class="ward-occupancy-fill ' + occupancyClass + '" style="width: ' + occupancyRate + '%"></div>';
                html += '</div>';

                html += '<div class="bed-grid">';
                if (ward.beds && ward.beds.length > 0) {
                    ward.beds.forEach(function(bed) {
                        var statusClass = bed.status || 'available';
                        var patientName = bed.current_patient || '';
                        var icon = statusClass === 'occupied' ? 'mdi-bed' :
                                  statusClass === 'maintenance' ? 'mdi-wrench' :
                                  statusClass === 'reserved' ? 'mdi-clock' : 'mdi-bed-empty';

                        html += '<div class="bed-item ' + statusClass + '" data-bed-id="' + bed.id + '" data-status="' + statusClass + '">';
                        html += '<div class="bed-icon"><i class="mdi ' + icon + '"></i></div>';
                        html += '<div class="bed-name">' + bed.name + '</div>';
                        if (patientName) {
                            html += '<div class="bed-patient" title="' + patientName + '">' + patientName + '</div>';
                        }
                        html += '</div>';
                    });
                } else {
                    html += '<div class="col-12 text-center py-2 text-muted">';
                    html += '<small>No beds configured</small></div>';
                }
                html += '</div>';
                html += '</div>';
            });

            $('#ward-grid').html(html);

            // Populate ward filter
            var filterOptions = '<option value="">All Wards</option>';
            wards.forEach(function(ward) {
                filterOptions += '<option value="' + ward.id + '">' + ward.name + '</option>';
            });
            $('#ward-filter-select').html(filterOptions);

        }).fail(function() {
            $('#ward-grid').html('<div class="text-center py-4 text-danger">' +
                '<i class="mdi mdi-alert mdi-48px"></i>' +
                '<p>Failed to load wards. Please try again.</p></div>');
        });
    }

    function loadAdmissionQueue() {
        $.get('/nursing-workbench/ward-dashboard/admission-queue', function(queue) {
            var html = '';

            $('#admission-queue-badge').text(queue.length);

            if (queue.length === 0) {
                html = '<div class="text-center py-4 text-muted">' +
                       '<i class="mdi mdi-check-circle mdi-48px text-success"></i>' +
                       '<p>No pending admissions</p></div>';
            } else {
                queue.forEach(function(item) {
                    html += '<div class="queue-item-card">';
                    html += '<div class="queue-patient-info">';
                    html += '<div class="queue-patient-name">' + item.patient_name + '</div>';
                    html += '<div class="queue-patient-details">';
                    html += '<span><i class="mdi mdi-file-document"></i> ' + item.file_no + '</span> | ';
                    html += '<span><i class="mdi mdi-doctor"></i> ' + item.doctor_name + '</span> | ';
                    html += '<span><i class="mdi mdi-clock"></i> ' + item.requested_at + '</span>';
                    html += '</div></div>';
                    html += '<div class="queue-actions">';
                    html += '<button class="btn btn-sm btn-primary" onclick="WardDashboard.openBedAssignment(' + item.id + ', \'' + item.patient_name + '\', \'' + item.file_no + '\')">';
                    html += '<i class="mdi mdi-bed"></i> Assign Bed</button>';
                    html += '</div></div>';
                });
            }

            $('#admission-queue-list').html(html);
        }).fail(function() {
            $('#admission-queue-list').html('<div class="text-center py-4 text-danger">' +
                '<p>Failed to load admission queue</p></div>');
        });
    }

    function loadDischargeQueue() {
        $.get('/nursing-workbench/ward-dashboard/discharge-queue', function(queue) {
            var html = '';

            $('#discharge-queue-badge').text(queue.length);

            if (queue.length === 0) {
                html = '<div class="text-center py-4 text-muted">' +
                       '<i class="mdi mdi-check-circle mdi-48px text-success"></i>' +
                       '<p>No pending discharges</p></div>';
            } else {
                queue.forEach(function(item) {
                    html += '<div class="queue-item-card">';
                    html += '<div class="queue-patient-info">';
                    html += '<div class="queue-patient-name">' + item.patient_name + '</div>';
                    html += '<div class="queue-patient-details">';
                    html += '<span><i class="mdi mdi-file-document"></i> ' + item.file_no + '</span> | ';
                    html += '<span><i class="mdi mdi-bed"></i> ' + item.bed_name + '</span> | ';
                    html += '<span><i class="mdi mdi-clock"></i> ' + item.discharge_requested_at + '</span>';
                    html += '</div></div>';
                    html += '<div class="queue-actions">';
                    html += '<button class="btn btn-sm btn-warning" onclick="WardDashboard.openDischarge(' + item.id + ', \'' + item.patient_name + '\', \'' + item.file_no + '\', \'' + item.bed_name + '\')">';
                    html += '<i class="mdi mdi-logout"></i> Process Discharge</button>';
                    html += '</div></div>';
                });
            }

            $('#discharge-queue-list').html(html);
        }).fail(function() {
            $('#discharge-queue-list').html('<div class="text-center py-4 text-danger">' +
                '<p>Failed to load discharge queue</p></div>');
        });
    }

    function openBedAssignment(admissionId, patientName, fileNo) {
        currentAdmissionId = admissionId;
        selectedBedId = null;

        $('#assign-patient-name').text(patientName);
        $('#assign-patient-file').text(fileNo);

        // Reset modal state
        $('#admission-checklist-step').show();
        $('#bed-selection-step').hide();
        $('#proceed-to-bed-selection').hide();
        $('#confirm-bed-assignment').hide();

        // Load checklist
        loadAdmissionChecklist(admissionId);

        $('#bedAssignmentModal').modal('show');
    }

    function loadAdmissionChecklist(admissionId) {
        $.get('/nursing-workbench/admission/' + admissionId + '/checklist', function(checklist) {
            currentChecklistId = checklist.id;
            renderChecklist(checklist.items, 'admission');
            updateChecklistProgress(checklist.progress, 'admission');

            if (checklist.progress >= 100 || checklist.all_complete) {
                $('#proceed-to-bed-selection').show();
            }
        }).fail(function() {
            $('#admission-checklist-items').html('<div class="text-danger p-3">Failed to load checklist</div>');
        });
    }

    function renderChecklist(items, type) {
        var containerId = type === 'admission' ? '#admission-checklist-items' : '#discharge-checklist-items';
        var html = '';

        items.forEach(function(item) {
            var statusClass = item.completed ? 'completed' : (item.waived ? 'waived' : '');

            html += '<div class="checklist-item ' + statusClass + '">';
            html += '<input type="checkbox" class="checklist-checkbox" data-item-id="' + item.id + '" data-checklist-type="' + type + '"';
            if (item.completed || item.waived) html += ' checked disabled';
            html += '>';
            html += '<div class="flex-grow-1">';
            html += '<strong>' + item.name + '</strong>';
            if (item.description) html += '<br><small class="text-muted">' + item.description + '</small>';
            if (item.waived) html += '<br><small class="text-warning"><i class="mdi mdi-alert"></i> Waived';
            if (item.waived_by) html += ' by ' + item.waived_by;
            if (item.waived_reason) html += ' - ' + item.waived_reason;
            html += '</small>';
            html += '</div>';
            if (!item.completed && !item.waived && item.is_waivable) {
                html += '<button class="btn btn-sm btn-outline-warning waive-item-btn" data-item-id="' + item.id + '" data-checklist-type="' + type + '">';
                html += '<i class="mdi mdi-close"></i> Waive</button>';
            }
            html += '</div>';
        });

        $(containerId).html(html);
    }

    function updateChecklistProgress(progress, type) {
        var barId = type === 'admission' ? '#checklist-progress-bar' : '#discharge-checklist-progress-bar';
        var textId = type === 'admission' ? '#checklist-progress-text' : '#discharge-checklist-progress-text';

        $(barId).css('width', progress + '%');
        $(textId).text(progress + '%');

        if (type === 'admission' && progress >= 100) {
            $('#proceed-to-bed-selection').show();
        }

        if (type === 'discharge' && progress >= 100) {
            $('#confirm-discharge').prop('disabled', false);
        }
    }

    function toggleChecklistItem(itemId, type, checked) {
        var url = type === 'admission' ?
            '/nursing-workbench/admission-checklist/item/' + itemId + '/complete' :
            '/nursing-workbench/discharge-checklist/item/' + itemId + '/complete';

        $.post(url, { _token: CSRF_TOKEN, completed: checked }, function(response) {
            if (response.success) {
                updateChecklistProgress(response.progress, type);
                toastr.success('Checklist updated');
            }
        }).fail(function() {
            toastr.error('Failed to update checklist');
        });
    }

    function waiveChecklistItem(itemId, type) {
        var reason = prompt('Please provide a reason for waiving this item:');
        if (!reason) return;

        var url = type === 'admission' ?
            '/nursing-workbench/admission-checklist/item/' + itemId + '/waive' :
            '/nursing-workbench/discharge-checklist/item/' + itemId + '/waive';

        $.post(url, { _token: CSRF_TOKEN, reason: reason }, function(response) {
            if (response.success) {
                if (type === 'admission') {
                    loadAdmissionChecklist(currentAdmissionId);
                } else {
                    loadDischargeChecklist(currentDischargeId);
                }
                toastr.success('Item waived');
            }
        }).fail(function() {
            toastr.error('Failed to waive item');
        });
    }

    function loadAvailableBeds(wardId) {
        var url = '/nursing-workbench/ward-dashboard/available-beds';
        if (wardId) url += '?ward_id=' + wardId;

        $.get(url, function(beds) {
            var html = '';

            if (beds.length === 0) {
                html = '<div class="text-center py-4 text-muted">' +
                       '<i class="mdi mdi-bed-empty mdi-48px"></i>' +
                       '<p>No available beds</p></div>';
            } else {
                html = '<div class="bed-grid">';
                beds.forEach(function(bed) {
                    html += '<div class="bed-item available" data-bed-id="' + bed.id + '">';
                    html += '<div class="bed-icon"><i class="mdi mdi-bed-empty"></i></div>';
                    html += '<div class="bed-name">' + bed.name + '</div>';
                    html += '<div class="bed-patient">' + bed.ward_name + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            $('#available-beds-grid').html(html);
        }).fail(function() {
            $('#available-beds-grid').html('<div class="text-danger p-3">Failed to load beds</div>');
        });
    }

    function confirmBedAssignment() {
        if (!selectedBedId) {
            toastr.error('Please select a bed');
            return;
        }

        $.post('/nursing-workbench/admission/' + currentAdmissionId + '/assign-bed', {
            _token: CSRF_TOKEN,
            bed_id: selectedBedId
        }, function(response) {
            if (response.success) {
                toastr.success('Bed assigned successfully');
                $('#bedAssignmentModal').modal('hide');
                loadDashboardData();
            } else {
                toastr.error(response.message || 'Failed to assign bed');
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to assign bed');
        });
    }

    function openDischarge(admissionId, patientName, fileNo, bedName) {
        currentDischargeId = admissionId;

        $('#discharge-patient-name').text(patientName);
        $('#discharge-patient-file').text(fileNo);
        $('#discharge-bed-name').text(bedName);

        // Load discharge checklist
        loadDischargeChecklist(admissionId);

        $('#dischargeModal').modal('show');
    }

    function loadDischargeChecklist(admissionId) {
        $.get('/nursing-workbench/admission/' + admissionId + '/discharge-checklist', function(checklist) {
            currentChecklistId = checklist.id;
            renderChecklist(checklist.items, 'discharge');
            updateChecklistProgress(checklist.progress, 'discharge');

            if (checklist.progress >= 100 || checklist.all_complete) {
                $('#confirm-discharge').prop('disabled', false);
            } else {
                $('#confirm-discharge').prop('disabled', true);
            }
        }).fail(function() {
            $('#discharge-checklist-items').html('<div class="text-danger p-3">Failed to load checklist</div>');
        });
    }

    function confirmDischarge() {
        $.post('/nursing-workbench/admission/' + currentDischargeId + '/complete-discharge', {
            _token: CSRF_TOKEN
        }, function(response) {
            if (response.success) {
                toastr.success('Discharge completed, bed released');
                $('#dischargeModal').modal('hide');
                loadDashboardData();
            } else {
                toastr.error(response.message || 'Failed to complete discharge');
            }
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to complete discharge');
        });
    }

    function showBedDetails(bedId, status) {
        $.get('/nursing-workbench/bed/' + bedId + '/details', function(bed) {
            var html = '';

            html += '<div class="mb-3">';
            html += '<strong>Ward:</strong> ' + (bed.ward_name || 'N/A') + '<br>';
            html += '<strong>Status:</strong> <span class="badge bg-' + (status === 'available' ? 'success' : status === 'occupied' ? 'danger' : 'warning') + '">' + status + '</span>';
            html += '</div>';

            if (bed.current_patient) {
                html += '<div class="card">';
                html += '<div class="card-header bg-light py-2"><strong>Current Patient</strong></div>';
                html += '<div class="card-body py-2">';
                html += '<strong>' + bed.current_patient.name + '</strong><br>';
                html += '<small class="text-muted">File: ' + bed.current_patient.file_no + '</small><br>';
                html += '<small class="text-muted">Admitted: ' + bed.admitted_date + '</small>';
                html += '</div></div>';
            }

            $('#bed-detail-name').text(bed.name);
            $('#bed-details-content').html(html);

            // Add actions to footer
            var footerHtml = '<button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>';
            if (status === 'available') {
                footerHtml += ' <button type="button" class="btn btn-warning btn-sm" onclick="WardDashboard.setBedMaintenance(' + bedId + ')"><i class="mdi mdi-wrench"></i> Set Maintenance</button>';
            } else if (status === 'maintenance') {
                footerHtml += ' <button type="button" class="btn btn-success btn-sm" onclick="WardDashboard.setBedAvailable(' + bedId + ')"><i class="mdi mdi-check"></i> Set Available</button>';
            }
            $('#bed-details-footer').html(footerHtml);

            $('#bedDetailsModal').modal('show');
        }).fail(function() {
            toastr.error('Failed to load bed details');
        });
    }

    function setBedMaintenance(bedId) {
        $.post('/nursing-workbench/bed/' + bedId + '/maintenance', { _token: CSRF_TOKEN }, function(response) {
            if (response.success) {
                toastr.success('Bed set to maintenance');
                $('#bedDetailsModal').modal('hide');
                loadWards();
            }
        }).fail(function() {
            toastr.error('Failed to update bed status');
        });
    }

    function setBedAvailable(bedId) {
        $.post('/nursing-workbench/bed/' + bedId + '/available', { _token: CSRF_TOKEN }, function(response) {
            if (response.success) {
                toastr.success('Bed set to available');
                $('#bedDetailsModal').modal('hide');
                loadWards();
            }
        }).fail(function() {
            toastr.error('Failed to update bed status');
        });
    }

    return {
        init: init,
        loadDashboardData: loadDashboardData,
        openBedAssignment: openBedAssignment,
        openDischarge: openDischarge,
        setBedMaintenance: setBedMaintenance,
        setBedAvailable: setBedAvailable
    };
})();

// Initialize when ward dashboard view is shown
$(document).on('shown.bs.modal shown', '#ward-dashboard-view', function() {
    WardDashboard.init();
});
</script>
@endpush
