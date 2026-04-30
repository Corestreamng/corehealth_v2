@extends('admin.layouts.app')
@section('title', 'Tracking Calendar')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Tracking Calendar')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <link rel="stylesheet" href="{{ asset('plugins/fullcalendar/fullcalendar.min.css') }}">
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        #trackingCalendar { min-height: 600px; }
        .fc-event { cursor: pointer; border: none; padding: 2px 5px; font-size: 0.78rem; border-radius: 4px; }
        .legend-item { display: inline-flex; align-items: center; margin-right: 1rem; font-size: 0.82rem; }
        .legend-dot { width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; display: inline-block; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-calendar-month text-primary"></i> Tracking Calendar
                </h2>
                <p class="text-muted mb-0">Medical exams, trainings, promotions, license expiry & confirmation dates</p>
            </div>
        </div>

        <div class="card-body">
            <div class="mb-3">
                <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Overdue Exam</span>
                <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> Upcoming Exam</span>
                <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span> Training (In Progress)</span>
                <span class="legend-item"><span class="legend-dot" style="background:#8b5cf6;"></span> Training (Planned)</span>
                <span class="legend-item"><span class="legend-dot" style="background:#059669;"></span> Promotion Due</span>
                <span class="legend-item"><span class="legend-dot" style="background:#dc2626;"></span> Promotion Overdue</span>
                <span class="legend-item"><span class="legend-dot" style="background:#0891b2;"></span> License Expiry</span>
                <span class="legend-item"><span class="legend-dot" style="background:#7c3aed;"></span> Confirmation Due</span>
                <span class="legend-item"><span class="legend-dot" style="background:#ea580c;"></span> Retirement</span>
            </div>
            <div id="trackingCalendar"></div>
        </div>
    </div>
</div>

<!-- Event Detail Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                <h5 class="modal-title" id="eventModalTitle"></h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="eventModalBody" style="padding: 1.5rem;"></div>
            <div class="modal-footer">
                <a id="eventProfileLink" href="#" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                    <i class="mdi mdi-account mr-1"></i> View Profile
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/fullcalendar.min.js') }}"></script>
<script>
$(function(){
    $('#trackingCalendar').fullCalendar({
        header: { left: 'prev,next today', center: 'title', right: 'month,agendaWeek,listMonth' },
        defaultView: 'month',
        editable: false,
        events: function(start, end, timezone, callback) {
            $.ajax({
                url: "{{ route('hr.tracking.calendar.events') }}",
                data: { start: start.format('YYYY-MM-DD'), end: end.format('YYYY-MM-DD') },
                success: function(data) { callback(data); },
                error: function() { toastr.error('Failed to load calendar events'); callback([]); }
            });
        },
        eventClick: function(event) {
            var p = event.extendedProps || {};
            var title = '', body = '<p class="mb-1"><strong>' + (p.staff || '') + '</strong></p>';

            if (p.type === 'medical_exam') {
                title = '<i class="mdi mdi-stethoscope text-warning mr-1"></i> Medical Exam Due';
                body += '<p class="mb-1">Type: ' + (p.exam_type || '') + '</p>';
                if (p.overdue) body += '<p class="text-danger mb-0"><i class="mdi mdi-alert mr-1"></i>Overdue</p>';
            } else if (p.type === 'training') {
                title = '<i class="mdi mdi-certificate text-primary mr-1"></i> Training';
                body += '<p class="mb-1">Type: ' + (p.training_type || '') + '</p>';
                body += '<p class="mb-1">Status: ' + (p.status || '') + '</p>';
                if (p.institution) body += '<p class="mb-1">Institution: ' + p.institution + '</p>';
            } else if (p.type === 'promotion_due') {
                title = '<i class="mdi mdi-arrow-up-bold-circle text-success mr-1"></i> Promotion Due';
                if (p.overdue) body += '<p class="text-danger mb-0"><i class="mdi mdi-alert mr-1"></i>Overdue</p>';
            } else if (p.type === 'license_expiry') {
                title = '<i class="mdi mdi-card-account-details text-info mr-1"></i> License Expiry';
                if (p.license_number) body += '<p class="mb-1">License #: ' + p.license_number + '</p>';
                if (p.overdue) body += '<p class="text-danger mb-0"><i class="mdi mdi-alert mr-1"></i>Expired</p>';
            } else if (p.type === 'confirmation_due') {
                title = '<i class="mdi mdi-account-check text-purple mr-1"></i> Confirmation Due';
                if (p.overdue) body += '<p class="text-danger mb-0"><i class="mdi mdi-alert mr-1"></i>Overdue</p>';
            } else if (p.type === 'retirement') {
                title = '<i class="mdi mdi-account-clock text-warning mr-1"></i> Retirement';
                if (p.past) body += '<p class="text-muted mb-0"><i class="mdi mdi-check mr-1"></i>Already retired</p>';
            }

            $('#eventModalTitle').html(title);
            $('#eventModalBody').html(body);
            $('#eventProfileLink').attr('href', "{{ url('hr/tracking/staff') }}/" + p.staff_id);
            $('#eventModal').modal('show');
        }
    });
});
</script>
@endsection
