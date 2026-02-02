@extends('admin.layouts.app')

@section('title', 'My Leave Calendar')

@include('admin.hr.leave-calendar._calendar-grid')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1" style="font-weight: 700; color: var(--primary-color);">
                <i class="mdi mdi-calendar-month mr-2"></i>My Leave Calendar
            </h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('hr.ess.index') }}">ESS</a></li>
                    <li class="breadcrumb-item active">My Calendar</li>
                </ol>
            </nav>
        </div>
        <div>
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
        <div class="col-md-3 col-6 mb-2">
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
        <div class="col-md-3 col-6 mb-2">
            <div class="card-modern stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
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
            <!-- On Leave Today -->
            <div class="card-modern mb-3" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-information-outline mr-2" style="color: var(--primary-color);"></i>
                        <span id="onLeaveDateLabel">Leave Status</span>
                    </h6>
                    <span class="badge badge-primary" id="onLeaveCount">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="on-leave-list" id="onLeaveList">
                        <div class="text-center py-4 text-muted">
                            <i class="mdi mdi-information" style="font-size: 2rem;"></i>
                            <p class="mb-0 small">Click a date to view status</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave Balances -->
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-header bg-white" style="border-radius: 12px 12px 0 0; border-bottom: 1px solid #e9ecef;">
                    <h6 class="mb-0" style="font-weight: 600;">
                        <i class="mdi mdi-chart-donut mr-2" style="color: var(--primary-color);"></i>Leave Balances
                    </h6>
                </div>
                <div class="card-body p-2">
                    @foreach($leaveTypes as $type)
                    @php
                        $balance = $balances[$type->id] ?? null;
                        $available = $balance ? $balance->available : 0;
                        $entitled = $balance ? $balance->total_entitled : $type->max_days_per_year;
                        $used = $entitled - $available;
                        $percentage = $entitled > 0 ? (($entitled - $available) / $entitled * 100) : 0;
                        $remainingPercentage = $entitled > 0 ? ($available / $entitled * 100) : 0;
                    @endphp
                    <div class="mb-3 p-3" style="border-radius: 8px; background: {{ $type->color }}08; border-left: 3px solid {{ $type->color }};">
                        <!-- Leave Type Header -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 font-weight-bold" style="color: {{ $type->color }};">
                                <i class="mdi mdi-calendar-blank" style="font-size: 1rem;"></i>
                                {{ $type->name }}
                            </h6>
                            @if($available <= 0 && $entitled > 0)
                            <span class="badge badge-danger">Exhausted</span>
                            @elseif($remainingPercentage <= 20 && $entitled > 0)
                            <span class="badge badge-warning">Low</span>
                            @else
                            <span class="badge badge-success">Available</span>
                            @endif
                        </div>

                        <!-- Stats Grid -->
                        <div class="row mb-2">
                            <div class="col-4 text-center">
                                <div class="small text-muted">Entitled</div>
                                <div class="font-weight-bold" style="font-size: 1.1rem; color: #333;">{{ number_format($entitled, 1) }}</div>
                            </div>
                            <div class="col-4 text-center" style="border-left: 1px solid #dee2e6; border-right: 1px solid #dee2e6;">
                                <div class="small text-muted">Taken</div>
                                <div class="font-weight-bold" style="font-size: 1.1rem; color: {{ $type->color }};">{{ number_format($used, 1) }}</div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="small text-muted">Left</div>
                                <div class="font-weight-bold" style="font-size: 1.1rem; color: {{ $available > 0 ? '#28a745' : '#dc3545' }};">{{ number_format($available, 1) }}</div>
                            </div>
                        </div>

                        <!-- Progress Bar (shows remaining/available as green portion) -->
                        <div class="mb-1">
                            <div class="progress" style="height: 8px; border-radius: 4px; background-color: #e9ecef;">
                                <div class="progress-bar bg-success" role="progressbar"
                                     style="width: {{ min($remainingPercentage, 100) }}%; border-radius: 4px;"
                                     aria-valuenow="{{ $available }}" aria-valuemin="0" aria-valuemax="{{ $entitled }}">
                                </div>
                            </div>
                        </div>

                        <!-- Percentage -->
                        <div class="text-center small text-muted">
                            <strong>{{ number_format($remainingPercentage, 0) }}% remaining</strong>
                        </div>
                    </div>
                    @endforeach

                    @if($leaveTypes->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="mdi mdi-information-outline" style="font-size: 2rem;"></i>
                        <p class="mb-0 small">No leave types assigned</p>
                    </div>
                    @endif
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

            html += `
                <div class="${cellClass}" data-date="${dateStr}">
                    <div class="day-header">
                        <span class="day-name">${dayName}</span>
                        <span class="day-number">${day}</span>
                    </div>
                    <div class="leave-items-container">
                        ${dayLeaves.map(leave => renderLeaveItem(leave)).join('')}
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
                 title="My Leave - ${leave.leave_type}"
                 style="background-color: ${bgColor}; border-left-color: ${color}; color: ${color};">
                <span class="staff-name">My Leave</span>
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
            url: '{{ route("hr.ess.my-calendar.events") }}',
            data: {
                start: formatDate(startDate),
                end: formatDate(endDate)
            },
            success: function(data) {
                leaveData = data.map(event => ({
                    leave_id: event.extendedProps.leave_id,
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
                    is_half_day: event.extendedProps.is_half_day
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
            <div class="text-center mb-3">
                <span class="badge badge-${statusColors[leave.status] || 'secondary'} badge-lg" style="font-size: 1rem; padding: 8px 16px;">
                    ${leave.status_label}
                </span>
            </div>
            <table class="table table-borderless">
                <tr>
                    <td class="text-muted" style="width: 40%;">Leave Type</td>
                    <td><strong>${leave.leave_type}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Period</td>
                    <td><strong>${leave.start_date_formatted}</strong> - <strong>${leave.end_date_formatted}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Duration</td>
                    <td><strong>${leave.total_days}</strong> day(s) ${leave.is_half_day ? '<span class="badge badge-info">Half Day</span>' : ''}</td>
                </tr>
                ${leave.reason ? '<tr><td class="text-muted">Reason</td><td>' + leave.reason + '</td></tr>' : ''}
            </table>
        `;

        $('#leaveDetailContent').html(html);
        $('#leaveDetailModal').modal('show');
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
    });

    // Refresh
    $('#refreshBtn').click(function() {
        loadLeaveData();
    });

    // Click on day cell to show leave status for that day
    $(document).on('click', '.calendar-day-cell:not(.empty-day)', function(e) {
        if ($(e.target).hasClass('leave-item') || $(e.target).closest('.leave-item').length) {
            return;
        }
        const date = $(this).data('date');
        if (date) {
            showLeaveStatusForDate(date);
        }
    });

    function showLeaveStatusForDate(dateStr) {
        const dayLeaves = getLeavesForDate(dateStr);
        const date = new Date(dateStr);
        const formattedDate = date.toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });

        $('#onLeaveDateLabel').text(formattedDate).data('date', dateStr);
        $('#onLeaveCount').text(dayLeaves.length);

        if (dayLeaves.length === 0) {
            $('#onLeaveList').html(`
                <div class="text-center py-4 text-muted">
                    <i class="mdi mdi-check-circle text-success" style="font-size: 2rem;"></i>
                    <p class="mb-0 small">You are not on leave</p>
                </div>
            `);
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
                    <div class="list-group-item" style="cursor: pointer;" onclick="showLeaveDetail(${leave.leave_id});">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${leave.leave_type}</strong>
                                <span class="badge badge-${statusColors[leave.status] || 'secondary'} ml-2">${leave.status_label}</span>
                                <br>
                                <small class="text-muted">${leave.total_days} day(s) ${leave.is_half_day ? '(Half Day)' : ''}</small>
                            </div>
                        </div>
                        ${leave.reason ? '<p class="mb-0 mt-2 small text-muted"><strong>Reason:</strong> ' + leave.reason + '</p>' : ''}
                    </div>
                `;
            });
            html += '</div>';
            $('#onLeaveList').html(html);
        }
    }

    // Initial load
    loadLeaveData();
});
</script>
@endsection
