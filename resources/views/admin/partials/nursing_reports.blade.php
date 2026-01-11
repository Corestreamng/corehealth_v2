{{--
    Nursing Reports Partial

    Usage: @include('admin.partials.nursing_reports')

    Features:
    - Daily nursing summary
    - Patient census report
    - Vitals summary report
    - Medication administration report
    - Shift handover report

    @see NursingWorkbenchController
--}}

<style>
    .nursing-reports-container {
        padding: 1.5rem;
    }

    .report-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .report-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }

    .report-card-header {
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .report-card-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    .report-card-icon.census { background: linear-gradient(135deg, #667eea, #764ba2); }
    .report-card-icon.vitals { background: linear-gradient(135deg, #f093fb, #f5576c); }
    .report-card-icon.medication { background: linear-gradient(135deg, #ffecd2, #fcb69f); color: #333; }
    .report-card-icon.handover { background: linear-gradient(135deg, #a8edea, #fed6e3); color: #333; }
    .report-card-icon.discharge { background: linear-gradient(135deg, #84fab0, #8fd3f4); color: #333; }
    .report-card-icon.notes { background: linear-gradient(135deg, #fa709a, #fee140); color: #333; }

    .report-card-title {
        font-weight: 600;
        font-size: 1.1rem;
        color: #333;
        margin-bottom: 0.25rem;
    }

    .report-card-subtitle {
        font-size: 0.85rem;
        color: #666;
    }

    .report-card-body {
        padding: 0 1.25rem 1.25rem;
    }

    .report-card-stat {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-top: 1px solid #f1f1f1;
    }

    .report-card-stat:first-child {
        border-top: none;
    }

    .report-card-stat-label {
        color: #666;
        font-size: 0.85rem;
    }

    .report-card-stat-value {
        font-weight: 600;
        color: #333;
    }

    .report-card-footer {
        background: #f8f9fa;
        padding: 0.75rem 1.25rem;
        display: flex;
        justify-content: flex-end;
    }

    .report-filter-bar {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .quick-date-filters {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .quick-date-btn {
        padding: 0.5rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 2rem;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .quick-date-btn:hover,
    .quick-date-btn.active {
        background: var(--hospital-primary, #007bff);
        color: white;
        border-color: var(--hospital-primary, #007bff);
    }

    .report-summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-stat-card {
        background: white;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .summary-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .summary-stat-label {
        font-size: 0.8rem;
        color: #666;
        text-transform: uppercase;
    }

    .summary-stat-value.text-primary { color: #007bff; }
    .summary-stat-value.text-success { color: #28a745; }
    .summary-stat-value.text-warning { color: #ffc107; }
    .summary-stat-value.text-danger { color: #dc3545; }
    .summary-stat-value.text-info { color: #17a2b8; }
</style>

<div class="nursing-reports-container" id="nursing-reports-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="mdi mdi-chart-box"></i> Nursing Reports</h4>
        <button class="btn btn-outline-primary btn-sm" id="refresh-nursing-reports">
            <i class="mdi mdi-refresh"></i> Refresh
        </button>
    </div>

    <!-- Quick Date Filters -->
    <div class="report-filter-bar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="quick-date-filters">
                <button class="quick-date-btn active" data-period="today">Today</button>
                <button class="quick-date-btn" data-period="yesterday">Yesterday</button>
                <button class="quick-date-btn" data-period="week">This Week</button>
                <button class="quick-date-btn" data-period="month">This Month</button>
            </div>
            <div class="d-flex gap-2">
                <input type="date" class="form-control form-control-sm" id="report-from-date" style="width: auto;">
                <input type="date" class="form-control form-control-sm" id="report-to-date" style="width: auto;">
                <button class="btn btn-primary btn-sm" id="apply-date-filter">Apply</button>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="report-summary-stats">
        <div class="summary-stat-card">
            <div class="summary-stat-value text-primary" id="stat-total-patients">0</div>
            <div class="summary-stat-label">Patients Seen</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value text-success" id="stat-vitals-taken">0</div>
            <div class="summary-stat-label">Vitals Recorded</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value text-warning" id="stat-meds-given">0</div>
            <div class="summary-stat-label">Meds Administered</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value text-info" id="stat-admissions">0</div>
            <div class="summary-stat-label">Admissions</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value text-danger" id="stat-discharges">0</div>
            <div class="summary-stat-label">Discharges</div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="report-cards">
        <!-- Patient Census Report -->
        <div class="report-card" onclick="NursingReports.generateReport('census')">
            <div class="report-card-header">
                <div class="report-card-icon census"><i class="mdi mdi-account-group"></i></div>
                <div>
                    <div class="report-card-title">Patient Census</div>
                    <div class="report-card-subtitle">Current ward occupancy</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Total Beds</span>
                    <span class="report-card-stat-value" id="census-total-beds">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Occupied</span>
                    <span class="report-card-stat-value text-danger" id="census-occupied">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Available</span>
                    <span class="report-card-stat-value text-success" id="census-available">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Vitals Summary Report -->
        <div class="report-card" onclick="NursingReports.generateReport('vitals')">
            <div class="report-card-header">
                <div class="report-card-icon vitals"><i class="mdi mdi-heart-pulse"></i></div>
                <div>
                    <div class="report-card-title">Vitals Summary</div>
                    <div class="report-card-subtitle">Vital signs recorded</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Total Readings</span>
                    <span class="report-card-stat-value" id="vitals-total">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Abnormal</span>
                    <span class="report-card-stat-value text-warning" id="vitals-abnormal">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Critical</span>
                    <span class="report-card-stat-value text-danger" id="vitals-critical">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Medication Administration Report -->
        <div class="report-card" onclick="NursingReports.generateReport('medication')">
            <div class="report-card-header">
                <div class="report-card-icon medication"><i class="mdi mdi-pill"></i></div>
                <div>
                    <div class="report-card-title">Medication Report</div>
                    <div class="report-card-subtitle">Drug administration log</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Administered</span>
                    <span class="report-card-stat-value" id="med-administered">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Pending</span>
                    <span class="report-card-stat-value text-warning" id="med-pending">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Missed</span>
                    <span class="report-card-stat-value text-danger" id="med-missed">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Shift Handover Report -->
        <div class="report-card" onclick="NursingReports.generateReport('handover')">
            <div class="report-card-header">
                <div class="report-card-icon handover"><i class="mdi mdi-clipboard-text"></i></div>
                <div>
                    <div class="report-card-title">Shift Handover</div>
                    <div class="report-card-subtitle">End of shift summary</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Notes Created</span>
                    <span class="report-card-stat-value" id="handover-notes">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Pending Tasks</span>
                    <span class="report-card-stat-value text-warning" id="handover-tasks">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Critical Alerts</span>
                    <span class="report-card-stat-value text-danger" id="handover-alerts">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Admission/Discharge Report -->
        <div class="report-card" onclick="NursingReports.generateReport('admission')">
            <div class="report-card-header">
                <div class="report-card-icon discharge"><i class="mdi mdi-account-switch"></i></div>
                <div>
                    <div class="report-card-title">Admission/Discharge</div>
                    <div class="report-card-subtitle">Patient movement log</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Admissions</span>
                    <span class="report-card-stat-value text-success" id="adm-admissions">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Discharges</span>
                    <span class="report-card-stat-value text-info" id="adm-discharges">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Transfers</span>
                    <span class="report-card-stat-value" id="adm-transfers">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>

        <!-- Nursing Notes Report -->
        <div class="report-card" onclick="NursingReports.generateReport('notes')">
            <div class="report-card-header">
                <div class="report-card-icon notes"><i class="mdi mdi-note-text"></i></div>
                <div>
                    <div class="report-card-title">Nursing Notes</div>
                    <div class="report-card-subtitle">Documentation summary</div>
                </div>
            </div>
            <div class="report-card-body">
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Total Notes</span>
                    <span class="report-card-stat-value" id="notes-total">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Progress Notes</span>
                    <span class="report-card-stat-value" id="notes-progress">--</span>
                </div>
                <div class="report-card-stat">
                    <span class="report-card-stat-label">Incident Reports</span>
                    <span class="report-card-stat-value text-warning" id="notes-incidents">--</span>
                </div>
            </div>
            <div class="report-card-footer">
                <button class="btn btn-sm btn-outline-primary">
                    <i class="mdi mdi-file-document"></i> Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
/**
 * Nursing Reports Module
 */
window.NursingReports = (function() {
    let currentPeriod = 'today';

    function init() {
        loadReportStats();
        bindEvents();
    }

    function bindEvents() {
        // Quick date filters
        $('.quick-date-btn').on('click', function() {
            $('.quick-date-btn').removeClass('active');
            $(this).addClass('active');
            currentPeriod = $(this).data('period');
            loadReportStats();
        });

        // Custom date filter
        $('#apply-date-filter').on('click', function() {
            $('.quick-date-btn').removeClass('active');
            currentPeriod = 'custom';
            loadReportStats();
        });

        // Refresh
        $('#refresh-nursing-reports').on('click', loadReportStats);
    }

    function getDateRange() {
        const today = new Date();
        let fromDate, toDate;

        switch (currentPeriod) {
            case 'today':
                fromDate = toDate = formatDate(today);
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                fromDate = toDate = formatDate(yesterday);
                break;
            case 'week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                fromDate = formatDate(weekStart);
                toDate = formatDate(today);
                break;
            case 'month':
                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                fromDate = formatDate(monthStart);
                toDate = formatDate(today);
                break;
            case 'custom':
                fromDate = $('#report-from-date').val();
                toDate = $('#report-to-date').val();
                break;
            default:
                fromDate = toDate = formatDate(today);
        }

        return { from: fromDate, to: toDate };
    }

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }

    function loadReportStats() {
        const range = getDateRange();

        // Load ward dashboard stats for census
        $.get('/nursing-workbench/ward-dashboard/stats', function(data) {
            $('#census-total-beds').text(data.total_beds || 0);
            $('#census-occupied').text(data.occupied_beds || 0);
            $('#census-available').text(data.available_beds || 0);
            $('#stat-admissions').text(data.pending_admissions || 0);
        });

        // Load shift summary for other stats
        $.get('/nursing-workbench/shift-summary', { from: range.from, to: range.to }, function(data) {
            // Update summary stats
            $('#stat-total-patients').text(data.patients_seen || 0);
            $('#stat-vitals-taken').text(data.vitals_recorded || 0);
            $('#stat-meds-given').text(data.medications_administered || 0);
            $('#stat-discharges').text(data.discharges || 0);

            // Update vitals card
            $('#vitals-total').text(data.vitals_recorded || 0);
            $('#vitals-abnormal').text(data.vitals_abnormal || 0);
            $('#vitals-critical').text(data.vitals_critical || 0);

            // Update medication card
            $('#med-administered').text(data.medications_administered || 0);
            $('#med-pending').text(data.medications_pending || 0);
            $('#med-missed').text(data.medications_missed || 0);

            // Update handover card
            $('#handover-notes').text(data.notes_created || 0);
            $('#handover-tasks').text(data.pending_tasks || 0);
            $('#handover-alerts').text(data.critical_alerts || 0);

            // Update admission card
            $('#adm-admissions').text(data.admissions || 0);
            $('#adm-discharges').text(data.discharges || 0);
            $('#adm-transfers').text(data.transfers || 0);

            // Update notes card
            $('#notes-total').text(data.notes_created || 0);
            $('#notes-progress').text(data.progress_notes || 0);
            $('#notes-incidents').text(data.incident_reports || 0);

        }).fail(function() {
            console.error('Failed to load report stats');
        });
    }

    function generateReport(type) {
        const range = getDateRange();
        let url = '';

        switch (type) {
            case 'census':
                url = '/nursing-workbench/reports/census?from=' + range.from + '&to=' + range.to;
                break;
            case 'vitals':
                url = '/nursing-workbench/reports/vitals?from=' + range.from + '&to=' + range.to;
                break;
            case 'medication':
                url = '/nursing-workbench/reports/medication?from=' + range.from + '&to=' + range.to;
                break;
            case 'handover':
                // Use existing handover export
                url = '/nursing-workbench/handover-export?from=' + range.from + '&to=' + range.to;
                break;
            case 'admission':
                url = '/nursing-workbench/reports/admission?from=' + range.from + '&to=' + range.to;
                break;
            case 'notes':
                url = '/nursing-workbench/reports/notes?from=' + range.from + '&to=' + range.to;
                break;
            default:
                toastr.warning('Report type not recognized');
                return;
        }

        // For handover, open in new window
        if (type === 'handover') {
            window.open(url, '_blank');
            return;
        }

        // For other reports, show info that they're in development
        toastr.info('Report generation for "' + type + '" is being developed. Use Shift Handover for current summary.');
    }

    return {
        init: init,
        generateReport: generateReport,
        loadReportStats: loadReportStats
    };
})();
</script>
@endpush
