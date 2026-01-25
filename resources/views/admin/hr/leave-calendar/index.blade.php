@extends('admin.layouts.app')

@section('title', 'Leave Calendar')

@section('style')
<style>
    /* Leave Calendar Grid Styles */
    .leave-calendar-container {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .calendar-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0;
    }
    
    .calendar-nav-btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .calendar-nav-btn:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .calendar-weekday-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background-color: #4a5568;
    }
    
    .weekday-name {
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: white;
    }
    
    .leave-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background-color: #dee2e6;
        border: 1px solid #dee2e6;
    }
    
    .calendar-day-cell {
        background-color: white;
        min-height: 120px;
        padding: 8px;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    .calendar-day-cell.empty-day {
        background-color: #f8f9fa;
        min-height: 60px;
    }
    
    .calendar-day-cell.today {
        background-color: #e3f2fd;
        border: 2px solid #2196F3;
    }
    
    .calendar-day-cell.weekend {
        background-color: #fffde7;
    }
    
    .calendar-day-cell.past-date {
        opacity: 0.7;
        background-color: #fafafa;
    }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
    }
    
    .day-name {
        font-size: 0.65rem;
        color: #888;
        text-transform: uppercase;
        font-weight: 500;
    }
    
    .day-number {
        font-weight: 700;
        font-size: 1rem;
        color: #333;
    }
    
    .today .day-number {
        background: #2196F3;
        color: white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .leave-items-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 3px;
        overflow-y: auto;
        max-height: 150px;
    }
    
    .leave-item {
        font-size: 0.7rem;
        padding: 4px 6px;
        border-radius: 4px;
        cursor: pointer;
        border-left: 3px solid;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
    }
    
    .leave-item:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    
    .leave-item .staff-name {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }
    
    .leave-item .leave-type-badge {
        font-size: 0.6rem;
        padding: 1px 4px;
        border-radius: 3px;
        background: rgba(255,255,255,0.8);
    }
    
    /* Status Modifiers */
    .leave-item.status-pending { opacity: 0.85; border-style: dashed; }
    .leave-item.status-supervisor_approved { border-left-width: 4px; }
    .leave-item.status-approved { }
    
    /* Stats Cards */
    .stat-card {
        border-radius: 12px;
        border: none;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    /* Legend */
    .legend-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        font-size: 0.75rem;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 3px;
        margin-right: 6px;
        border-left: 3px solid;
    }
    
    /* On Leave List */
    .on-leave-list {
        max-height: 300px;
        overflow-y: auto;
    }
    
    /* More indicator */
    .more-leaves {
        font-size: 0.65rem;
        color: #666;
        text-align: center;
        padding: 2px;
        background: #f5f5f5;
        border-radius: 3px;
        cursor: pointer;
    }
    
    .more-leaves:hover {
        background: #e0e0e0;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .calendar-day-cell {
            min-height: 90px;
            padding: 4px;
        }
        .leave-item {
            font-size: 0.6rem;
            padding: 2px 4px;
        }
        .day-number {
            font-size: 0.85rem;
        }
    }
    
    @media (max-width: 576px) {
        .calendar-day-cell {
            min-height: 70px;
        }
        .leave-item .leave-type-badge {
            display: none;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-calendar-month mr-2"></i>Leave Calendar
            </h3>
            <p class="text-muted mb-0">Organization-wide leave management calendar</p>
        </div>
        <div class="d-flex">
            <button type="button" class="btn btn-outline-info mr-2" id="conflictsBtn" style="border-radius: 8px;">
                <i class="mdi mdi-alert-circle mr-1"></i> Check Conflicts
            </button>
            <button type="button" class="btn btn-primary" id="refreshBtn" style="border-radius: 8px;">
                <i class="mdi mdi-refresh mr-1"></i> Refresh
            </button>
        </div>
    </div>

{{-- Remove any PHP helper function definitions (adjustBrightness, hexToRgba) from this view. Use only the ones from the layout. --}}
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">On Leave Today</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statOnLeave">{{ $stats['on_leave_today'] }}</h3>
                        </div>
                        <i class="mdi mdi-beach" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Pending</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statPending">{{ $stats['pending_requests'] }}</h3>
                        </div>
                        <i class="mdi mdi-clock-outline" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Approved (Month)</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statApproved">{{ $stats['approved_this_month'] }}</h3>
                        </div>
                        <i class="mdi mdi-check-circle" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Days Used</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statDays">{{ $stats['total_days_this_month'] }}</h3>
                        </div>
                        <i class="mdi mdi-calendar-range" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Upcoming (7 Days)</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statUpcoming">{{ $stats['upcoming_leaves'] }}</h3>
                        </div>
                        <i class="mdi mdi-calendar-arrow-right" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Calendar Grid -->
        <div class="col-lg-9 col-md-12 mb-4">
            <div class="leave-calendar-container">
                <!-- Calendar Header -->
                <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" id="prevMonthBtn">
                        <i class="mdi mdi-chevron-left"></i> Previous
                    </button>
                    <h4 class="calendar-title" id="calendarTitle">January 2026</h4>
                    <div>
                        <button type="button" class="calendar-nav-btn mr-2" id="todayBtn">Today</button>
                        <button type="button" class="calendar-nav-btn" id="nextMonthBtn">
                            Next <i class="mdi mdi-chevron-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Weekday Headers -->
                <div class="calendar-weekday-header">
                    <div class="weekday-name">Sun</div>
                    <div class="weekday-name">Mon</div>
                    <div class="weekday-name">Tue</div>
                    <div class="weekday-name">Wed</div>
                    <div class="weekday-name">Thu</div>
                    <div class="weekday-name">Fri</div>
                    <div class="weekday-name">Sat</div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="leave-calendar-grid" id="calendarGrid">
                    <!-- Days will be rendered by JavaScript -->
                </div>
            </div>
            
            <!-- Legend -->
            <div class="legend-container mt-3">
                <strong class="mr-3" style="font-size: 0.8rem;">Leave Types:</strong>
                @foreach($leaveTypes as $type)
                <div class="legend-item">
                    <div class="legend-color" style="background: {{ $type->color }}20; border-left-color: {{ $type->color }};"></div>
                    <span>{{ $type->name }}</span>
                </div>
                @endforeach
                <div class="ml-auto">
                    <span class="badge badge-secondary mr-1" style="border: 1px dashed #999;">Pending</span>
                    <span class="badge badge-info mr-1">Supervisor Approved</span>
                    <span class="badge badge-success">Approved</span>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-3 col-md-12">
            <!-- Filters -->
            <div class="card-modern mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-filter-outline mr-2" style="color: var(--primary-color);"></i>Filters
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Leave Type</label>
                        <select id="filterLeaveType" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Types</option>
                            @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Department</label>
                        <select id="filterDepartment" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Category</label>
                        <select id="filterCategory" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Categories</option>
                            @foreach($userCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Status</label>
                        <select id="filterStatus" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="supervisor_approved">Supervisor Approved</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- On Leave Panel -->
            <div class="card" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-account-clock mr-2" style="color: var(--primary-color);"></i>
                        <span id="onLeaveDateLabel">On Leave Today</span>
                    </h6>
                    <span class="badge badge-primary" id="onLeaveCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="on-leave-list" id="onLeaveList">
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-check-circle" style="font-size: 2rem;"></i>
                            <p class="mb-0 small">No one on leave</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal fade" id="leaveDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header" id="modalHeader" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white">
                    <i class="mdi mdi-calendar-account mr-2"></i>Leave Details
                </h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="leaveDetailContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <a href="#" id="viewRequestBtn" class="btn btn-primary" style="border-radius: 8px;">
                    <i class="mdi mdi-eye mr-1"></i>View Full Request
                </a>
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Day Detail Modal (when clicking on a day with many leaves) -->
<div class="modal fade" id="dayDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-primary" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title text-white" id="dayDetailTitle">
                    <i class="mdi mdi-calendar mr-2"></i>Leave Details
                </h5>
                <button type="button" class="close text-white"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="dayDetailContent" style="max-height: 400px; overflow-y: auto;">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Conflicts Modal -->
<div class="modal fade" id="conflictsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-warning" style="border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">
                    <i class="mdi mdi-alert-circle mr-2"></i>Leave Conflicts
                </h5>
                <button type="button" class="close"  data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="small font-weight-bold">Department</label>
                            <select id="conflictDepartment" class="form-control form-control-sm" style="border-radius: 6px;">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-bold">Threshold</label>
                            <select id="conflictThreshold" class="form-control form-control-sm" style="border-radius: 6px;">
                                <option value="2">2+ people</option>
                                <option value="3">3+ people</option>
                                <option value="5">5+ people</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="small font-weight-bold">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-primary btn-block" id="checkConflictsBtn" style="border-radius: 6px;">
                                <i class="mdi mdi-magnify mr-1"></i>Check
                            </button>
                        </div>
                    </div>
                </div>
                <div id="conflictsContent">
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-check-circle" style="font-size: 3rem;"></i>
                        <p class="mb-0">Click "Check" to detect conflicts</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Current calendar state
    let currentYear = {{ now()->year }};
    let currentMonth = {{ now()->month - 1 }}; // 0-indexed
    let leaveData = [];
    
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    function renderCalendar() {
        const grid = document.getElementById('calendarGrid');
        const titleEl = document.getElementById('calendarTitle');
        
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        titleEl.textContent = `${months[currentMonth]} ${currentYear}`;
        
        // Get first and last day of month
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const startDayOfWeek = firstDay.getDay();
        const daysInMonth = lastDay.getDate();
        
        let html = '';
        
        // Empty cells before first day
        for (let i = 0; i < startDayOfWeek; i++) {
            html += '<div class="calendar-day-cell empty-day"></div>';
        }
        
        // Days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day);
            const dateStr = formatDate(date);
            const isToday = dateStr === todayStr;
            const isPast = date < new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            const dayName = date.toLocaleDateString('en-US', { weekday: 'short' });
            
            let cellClass = 'calendar-day-cell';
            if (isToday) cellClass += ' today';
            if (isPast && !isToday) cellClass += ' past-date';
            if (isWeekend) cellClass += ' weekend';
            
            // Get leaves for this day
            const dayLeaves = getLeavesForDate(dateStr);
            const maxDisplay = 4;
            const displayLeaves = dayLeaves.slice(0, maxDisplay);
            const moreCount = dayLeaves.length - maxDisplay;
            
            html += `
                <div class="${cellClass}" data-date="${dateStr}">
                    <div class="day-header">
                        <span class="day-name">${dayName}</span>
                        <span class="day-number">${day}</span>
                    </div>
                    <div class="leave-items-container">
                        ${displayLeaves.map(leave => renderLeaveItem(leave)).join('')}
                        ${moreCount > 0 ? `<div class="more-leaves" onclick="showDayDetail('${dateStr}')">+${moreCount} more</div>` : ''}
                    </div>
                </div>
            `;
        }
        
        // Empty cells after last day
        const remainingCells = (7 - ((startDayOfWeek + daysInMonth) % 7)) % 7;
        for (let i = 0; i < remainingCells; i++) {
            html += '<div class="calendar-day-cell empty-day"></div>';
        }
        
        grid.innerHTML = html;
    }
    
    function renderLeaveItem(leave) {
        const color = leave.leave_type_color || '#667eea';
        const statusClass = `status-${leave.status}`;
        
        // Generate lighter background from the color
        const bgColor = color + '20'; // 20 = 12.5% opacity in hex
        
        return `
            <div class="leave-item ${statusClass}" 
                 onclick="showLeaveDetail(${leave.leave_id})"
                 title="${leave.staff_name} - ${leave.leave_type}"
                 style="background-color: ${bgColor}; border-left-color: ${color}; color: ${color};">
                <span class="staff-name">${leave.staff_name}</span>
                <span class="leave-type-badge">${leave.leave_type.substring(0, 3)}</span>
            </div>
        `;
    }
    
    function getLeavesForDate(dateStr) {
        return leaveData.filter(leave => {
            const start = new Date(leave.start_date);
            const end = new Date(leave.end_date);
            const check = new Date(dateStr);
            return check >= start && check <= end;
        });
    }
    
    function loadLeaveData() {
        const startDate = new Date(currentYear, currentMonth, 1);
        const endDate = new Date(currentYear, currentMonth + 1, 0);
        
        $.ajax({
            url: '{{ route("hr.leave-calendar.events") }}',
            data: {
                start: formatDate(startDate),
                end: formatDate(endDate),
                leave_type_id: $('#filterLeaveType').val(),
                department_id: $('#filterDepartment').val(),
                user_category_id: $('#filterCategory').val(),
                status: $('#filterStatus').val()
            },
            success: function(data) {
                // Transform data for easier date lookup
                leaveData = data.map(event => ({
                    leave_id: event.extendedProps.leave_id,
                    staff_id: event.extendedProps.staff_id,
                    staff_name: event.extendedProps.staff_name,
                    employee_id: event.extendedProps.employee_id,
                    department: event.extendedProps.department,
                    leave_type: event.extendedProps.leave_type,
                    leave_type_color: event.extendedProps.leave_type_color || '#667eea',
                    start_date: event.extendedProps.start_date_raw || event.start,
                    end_date: event.extendedProps.end_date_raw || event.end,
                    start_date_formatted: event.extendedProps.start_date,
                    end_date_formatted: event.extendedProps.end_date,
                    total_days: event.extendedProps.total_days,
                    status: event.extendedProps.status,
                    status_label: event.extendedProps.status_label,
                    reason: event.extendedProps.reason,
                    is_half_day: event.extendedProps.is_half_day,
                    relief_staff: event.extendedProps.relief_staff
                }));
                renderCalendar();
            },
            error: function(xhr) {
                console.error('Error loading leave data:', xhr);
            }
        });
    }
    
    // Expose showLeaveDetail to global scope
    window.showLeaveDetail = function(leaveId) {
        const leave = leaveData.find(l => l.leave_id === leaveId);
        if (!leave) return;
        
        const statusColors = {
            'pending': 'warning',
            'supervisor_approved': 'info',
            'approved': 'success'
        };
        
        $('#modalHeader').removeClass('bg-warning bg-info bg-success bg-primary')
                        .addClass('bg-' + (statusColors[leave.status] || 'primary'));
        
        const html = `
            <div class="row">
                <div class="col-6">
                    <p class="mb-2"><strong>Staff:</strong><br>${leave.staff_name}</p>
                    <p class="mb-2"><strong>Employee ID:</strong><br>${leave.employee_id}</p>
                    <p class="mb-2"><strong>Department:</strong><br>${leave.department}</p>
                </div>
                <div class="col-6">
                    <p class="mb-2"><strong>Leave Type:</strong><br>${leave.leave_type}</p>
                    <p class="mb-2"><strong>Period:</strong><br>${leave.start_date_formatted} - ${leave.end_date_formatted}</p>
                    <p class="mb-2"><strong>Duration:</strong><br>${leave.total_days} day(s) ${leave.is_half_day ? '(Half Day)' : ''}</p>
                </div>
            </div>
            <hr>
            <p class="mb-2"><strong>Status:</strong> <span class="badge badge-${statusColors[leave.status] || 'secondary'}">${leave.status_label}</span></p>
            ${leave.reason ? '<p class="mb-2"><strong>Reason:</strong><br>' + leave.reason + '</p>' : ''}
            ${leave.relief_staff ? '<p class="mb-0"><strong>Relief Staff:</strong> ' + leave.relief_staff + '</p>' : ''}
        `;
        
        $('#leaveDetailContent').html(html);
        $('#viewRequestBtn').attr('href', '{{ url("hr/leave-requests") }}/' + leave.leave_id);
        $('#leaveDetailModal').modal('show');
    };
    
    // Expose showDayDetail to global scope
    window.showDayDetail = function(dateStr) {
        const dayLeaves = getLeavesForDate(dateStr);
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
        
        $('#dayDetailTitle').html(`<i class="mdi mdi-calendar mr-2"></i>${formattedDate}`);
        
        if (dayLeaves.length === 0) {
            $('#dayDetailContent').html('<div class="text-center text-muted py-4">No leaves on this day</div>');
        } else {
            let html = '<div class="list-group list-group-flush">';
            dayLeaves.forEach(leave => {
                const statusColors = {
                    'pending': 'warning',
                    'supervisor_approved': 'info',
                    'approved': 'success'
                };
                const leaveColor = leave.leave_type_color || '#667eea';
                
                html += `
                    <div class="list-group-item" style="cursor: pointer;" onclick="$('#dayDetailModal').modal('hide'); showLeaveDetail(${leave.leave_id});">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${leave.staff_name}</strong>
                                <span class="badge badge-${statusColors[leave.status] || 'secondary'} ml-2">${leave.status_label}</span>
                                <br>
                                <small class="text-muted">${leave.department}</small>
                            </div>
                            <div class="text-right">
                                <span class="badge" style="background-color: ${leaveColor}20; color: ${leaveColor}; border: 1px solid ${leaveColor};">${leave.leave_type}</span>
                                <br>
                                <small class="text-muted">${leave.total_days} day(s)</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            $('#dayDetailContent').html(html);
        }
        
        $('#dayDetailModal').modal('show');
    };
    
    // Navigation
    document.getElementById('prevMonthBtn').addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        loadLeaveData();
    });
    
    document.getElementById('nextMonthBtn').addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        loadLeaveData();
    });
    
    document.getElementById('todayBtn').addEventListener('click', function() {
        currentYear = today.getFullYear();
        currentMonth = today.getMonth();
        loadLeaveData();
        loadOnLeaveForDate(todayStr);
    });
    
    // Filters
    $('#filterLeaveType, #filterDepartment, #filterCategory, #filterStatus').change(function() {
        loadLeaveData();
    });
    
    // Refresh
    $('#refreshBtn').click(function() {
        loadLeaveData();
        loadOnLeaveForDate($('#onLeaveDateLabel').data('date') || todayStr);
        loadStats();
    });
    
    // Conflicts
    $('#conflictsBtn').click(function() {
        $('#conflictsModal').modal('show');
    });
    
    $('#checkConflictsBtn').click(function() {
        checkConflicts();
    });
    
    function loadOnLeaveForDate(date) {
        $('#onLeaveDateLabel').text('Loading...').data('date', date);
        
        $.get('{{ route("hr.leave-calendar.on-leave-today") }}', { date: date }, function(data) {
            var dateFormatted = new Date(data.date).toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric'
            });
            
            $('#onLeaveDateLabel').text('On Leave - ' + dateFormatted);
            $('#onLeaveCount').text(data.count);
            
            if (data.staff.length === 0) {
                $('#onLeaveList').html(`
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-check-circle" style="font-size: 2rem;"></i>
                        <p class="mb-0 small">No one on leave</p>
                    </div>
                `);
            } else {
                var html = '<div class="list-group list-group-flush">';
                data.staff.forEach(function(s) {
                    html += `
                        <div class="list-group-item py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong class="small">${s.name}</strong>
                                    <br><small class="text-muted">${s.department}</small>
                                </div>
                                <span class="badge badge-light small">${s.leave_type}</span>
                            </div>
                            <small class="text-muted">Returns: ${s.return_date}</small>
                        </div>
                    `;
                });
                html += '</div>';
                $('#onLeaveList').html(html);
            }
        });
    }
    
    function loadStats() {
        $.get('{{ route("hr.leave-calendar.stats") }}', function(data) {
            $('#statOnLeave').text(data.on_leave_today);
            $('#statPending').text(data.pending_requests);
            $('#statApproved').text(data.approved_this_month);
            $('#statDays').text(data.total_days_this_month);
            $('#statUpcoming').text(data.upcoming_leaves);
        });
    }
    
    function checkConflicts() {
        $('#conflictsContent').html('<div class="text-center py-4"><i class="mdi mdi-loading mdi-spin" style="font-size: 2rem;"></i></div>');
        
        $.get('{{ route("hr.leave-calendar.conflicts") }}', {
            department_id: $('#conflictDepartment').val(),
            threshold: $('#conflictThreshold').val()
        }, function(data) {
            if (data.conflicts.length === 0) {
                $('#conflictsContent').html(`
                    <div class="text-center py-4 text-success">
                        <i class="mdi mdi-check-circle" style="font-size: 3rem;"></i>
                        <p class="mb-0">No conflicts found for threshold of ${data.threshold}+ people</p>
                    </div>
                `);
            } else {
                var html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Date</th><th>Count</th><th>Staff</th></tr></thead><tbody>';
                data.conflicts.forEach(function(c) {
                    var staffNames = c.staff.map(s => s.name + ' (' + s.department + ')').join(', ');
                    html += `
                        <tr>
                            <td><strong>${c.formatted_date}</strong></td>
                            <td><span class="badge badge-danger">${c.count}</span></td>
                            <td class="small">${staffNames}</td>
                        </tr>
                    `;
                });
                html += '</tbody></table></div>';
                $('#conflictsContent').html(html);
            }
        });
    }
    
    // Click on day cell to show on-leave list
    $(document).on('click', '.calendar-day-cell:not(.empty-day)', function(e) {
        if ($(e.target).hasClass('leave-item') || $(e.target).closest('.leave-item').length || 
            $(e.target).hasClass('more-leaves')) {
            return; // Don't trigger if clicking on a leave item
        }
        const date = $(this).data('date');
        if (date) {
            loadOnLeaveForDate(date);
        }
    });
    
    // Initial load
    loadLeaveData();
    loadOnLeaveForDate(todayStr);
});
</script>
@endsection
