@extends('admin.layouts.app')

@section('title', 'Team Leave Calendar')

@include('admin.hr.leave-calendar._calendar-grid')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-calendar-group mr-2"></i>Team Leave Calendar
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">Team Calendar</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex">
            <a href="{{ route('hr.ess.team-approvals.index') }}" class="btn btn-outline-primary mr-2" style="border-radius: 8px;">
                <i class="mdi mdi-checkbox-marked-circle-outline mr-1"></i> Pending Approvals
            </a>
            <button type="button" class="btn btn-primary" id="refreshBtn" style="border-radius: 8px;">
                <i class="mdi mdi-refresh mr-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-2">
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
        <div class="col-md-3 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Pending Approval</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statPending">{{ $stats['pending_approval'] }}</h3>
                        </div>
                        <i class="mdi mdi-clock-outline" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Upcoming (14 Days)</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statUpcoming">{{ $stats['upcoming'] }}</h3>
                        </div>
                        <i class="mdi mdi-calendar-arrow-right" style="font-size: 1.8rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <div class="card-body text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white-50 small">Team Size</h6>
                            <h3 class="mb-0" style="font-weight: 700;" id="statTeamSize">{{ $stats['team_size'] }}</h3>
                        </div>
                        <i class="mdi mdi-account-group" style="font-size: 1.8rem; opacity: 0.5;"></i>
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
            <!-- Team Overview -->
            <div class="card-modern mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-account-group mr-2" style="color: var(--primary-color);"></i>Team Overview
                    </h6>
                </div>
                <div class="card-body p-2">
                    <!-- Team Stats -->
                    <div class="row text-center mb-2">
                        <div class="col-4">
                            <div class="p-2" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-radius: 8px;">
                                <div class="small text-muted">Members</div>
                                <div class="font-weight-bold" style="font-size: 1.3rem; color: #667eea;">{{ $stats['team_size'] }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2" style="background: linear-gradient(135deg, #28a74515 0%, #20c99715 100%); border-radius: 8px;">
                                <div class="small text-muted">On Leave</div>
                                <div class="font-weight-bold" style="font-size: 1.3rem; color: #28a745;">{{ $stats['on_leave_today'] }}</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2" style="background: linear-gradient(135deg, #ffc10715 0%, #ff8c0015 100%); border-radius: 8px;">
                                <div class="small text-muted">Pending</div>
                                <div class="font-weight-bold" style="font-size: 1.3rem; color: #ffc107;">{{ $stats['pending_approval'] }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Team Members List -->
                    <div class="mt-2">
                        <small class="font-weight-bold text-muted">TEAM MEMBERS</small>
                        <div class="mt-1" style="max-height: 150px; overflow-y: auto;">
                            @foreach($teamMembers->take(10) as $member)
                            @php $memberUser = $member->user; @endphp
                            <div class="d-flex align-items-center py-1 px-1" style="border-bottom: 1px solid #f1f1f1;">
                                <div class="mr-2" style="width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: white; font-weight: 600;">
                                    {{ $memberUser ? strtoupper(substr($memberUser->firstname, 0, 1) . substr($memberUser->surname, 0, 1)) : '??' }}
                                </div>
                                <div class="flex-grow-1 small">
                                    <div class="font-weight-bold">{{ $memberUser ? $memberUser->firstname . ' ' . $memberUser->surname : $member->employee_id }}</div>
                                    <small class="text-muted">{{ $member->department->name ?? 'No Dept' }}</small>
                                </div>
                            </div>
                            @endforeach
                            @if($teamMembers->count() > 10)
                            <div class="text-center py-1">
                                <small class="text-muted">+{{ $teamMembers->count() - 10 }} more members</small>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card-modern mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-filter-outline mr-2" style="color: var(--primary-color);"></i>Filters
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="small font-weight-bold">Team Member</label>
                        <select id="filterStaff" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Members</option>
                            @foreach($teamMembers as $member)
                            @php $memberUser = $member->user; @endphp
                            <option value="{{ $member->id }}">
                                {{ $memberUser ? $memberUser->firstname . ' ' . $memberUser->surname : $member->employee_id }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Leave Type</label>
                        <select id="filterLeaveType" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Types</option>
                            @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Status</label>
                        <select id="filterStatus" class="form-control form-control-sm" style="border-radius: 6px;">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending My Approval</option>
                            <option value="supervisor_approved">Awaiting HR Approval</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- On Leave Today -->
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
                            <p class="mb-0 small">No team members on leave</p>
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
            <div class="modal-footer" id="leaveDetailFooter">
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Day Detail Modal -->
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
        const bgColor = color + '20';
        
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
            url: '{{ route("hr.ess.team-calendar.events") }}',
            data: {
                start: formatDate(startDate),
                end: formatDate(endDate),
                staff_id: $('#filterStaff').val(),
                leave_type_id: $('#filterLeaveType').val(),
                status: $('#filterStatus').val()
            },
            success: function(data) {
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
                    can_approve: event.extendedProps.can_approve
                }));
                renderCalendar();
            },
            error: function(xhr) {
                console.error('Error loading leave data:', xhr);
            }
        });
    }
    
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
            ${leave.reason ? '<p class="mb-0"><strong>Reason:</strong><br>' + leave.reason + '</p>' : ''}
        `;
        
        $('#leaveDetailContent').html(html);
        
        // Show/hide action buttons based on can_approve
        if (leave.can_approve) {
            $('#leaveDetailFooter').html(`
                <a href="{{ url('hr/ess/team-approvals') }}/${leave.leave_id}" class="btn btn-primary" style="border-radius: 8px;">
                    <i class="mdi mdi-eye mr-1"></i>View & Approve
                </a>
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            `);
        } else {
            $('#leaveDetailFooter').html(`
                <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            `);
        }
        
        $('#leaveDetailModal').modal('show');
    };
    
    window.showDayDetail = function(dateStr) {
        const dayLeaves = getLeavesForDate(dateStr);
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
        
        $('#dayDetailTitle').html(`<i class="mdi mdi-calendar mr-2"></i>${formattedDate}`);
        
        if (dayLeaves.length === 0) {
            $('#dayDetailContent').html('<div class="text-center text-muted py-4">No team members on leave</div>');
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
    
    function loadOnLeaveForDate(date) {
        $('#onLeaveDateLabel').text('Loading...').data('date', date);
        
        $.get('{{ route("hr.ess.team-calendar.on-leave") }}', { date: date }, function(data) {
            var dateFormatted = new Date(data.date).toLocaleDateString('en-US', {
                weekday: 'short', month: 'short', day: 'numeric'
            });
            
            $('#onLeaveDateLabel').text('On Leave - ' + dateFormatted);
            $('#onLeaveCount').text(data.count);
            
            if (data.staff.length === 0) {
                $('#onLeaveList').html(`
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-check-circle" style="font-size: 2rem;"></i>
                        <p class="mb-0 small">No team members on leave</p>
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
                        </div>
                    `;
                });
                html += '</div>';
                $('#onLeaveList').html(html);
            }
        });
    }
    
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
    $('#filterStaff, #filterLeaveType, #filterStatus').change(function() {
        loadLeaveData();
    });
    
    // Refresh
    $('#refreshBtn').click(function() {
        loadLeaveData();
        loadOnLeaveForDate($('#onLeaveDateLabel').data('date') || todayStr);
    });
    
    // Click on day cell to show on-leave list
    $(document).on('click', '.calendar-day-cell:not(.empty-day)', function(e) {
        if ($(e.target).hasClass('leave-item') || $(e.target).closest('.leave-item').length || 
            $(e.target).hasClass('more-leaves')) {
            return;
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
