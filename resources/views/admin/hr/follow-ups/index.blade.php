@extends('admin.layouts.app')
@section('title', 'HR Follow-ups')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Follow-ups')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .hr-table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; background: #f9fafb; }
        .hr-table td { vertical-align: middle !important; }
        .select2-container--open { z-index: 9999 !important; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
<div class="container-fluid">
    @include('admin.hr.partials.hr-subnav')

    @include('admin.hr.partials.scoped-staff-bar', ['scopedStaff' => $scopedStaff ?? null, 'scopedStaffLinks' => [
        ['url' => route('hr.promotions.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-arrow-up-bold-circle', 'label' => 'Promotions'],
        ['url' => route('hr.qualifications.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-school', 'label' => 'Qualifications'],
        ['url' => route('hr.trainings.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-certificate', 'label' => 'Trainings'],
        ['url' => route('hr.medical-exams.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-stethoscope', 'label' => 'Medical'],
    ]])

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-clipboard-check-outline text-primary"></i> {{ $scopedStaff ? ($scopedStaff->user?->surname . ' ' . $scopedStaff->user?->firstname . ' — ') : '' }}Follow-ups
                </h2>
                <p class="text-muted mb-0">{{ $scopedStaff ? 'Pending HR actions and follow-up items' : 'Pending HR actions and follow-up items for staff' }}</p>
            </div>
            @can('hr-follow-ups.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#followUpModal" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> New Follow-up
            </button>
            @endcan
        </div>

        <div class="card-body">

            @include('admin.hr.partials.tracking-stats', ['trackingStats' => [
                ['label' => 'Total Follow-ups', 'value' => $stats['total'], 'icon' => 'mdi-clipboard-check-outline', 'color' => '#6366f1'],
                ['label' => 'Open', 'value' => $stats['open'], 'icon' => 'mdi-alert-circle-outline', 'color' => '#f59e0b'],
                ['label' => 'In Progress', 'value' => $stats['in_progress'], 'icon' => 'mdi-progress-clock', 'color' => '#3b82f6'],
                ['label' => 'Overdue', 'value' => $stats['overdue'], 'icon' => 'mdi-alarm', 'color' => '#ef4444', 'subtitle' => 'Past due date'],
            ]])

            <!-- Filters -->
            <div class="filter-bar d-flex align-items-center gap-2 flex-wrap mb-3" style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filter:</label>
                @if(!$scopedStaff)
                <select class="form-control form-control-sm" id="staffFilter" style="min-width: 220px; border-radius: 8px;">
                    <option value="">All Staff</option>
                    @foreach($staffList as $s)
                        <option value="{{ $s->id }}">{{ $s->user?->surname }} {{ $s->user?->firstname }}</option>
                    @endforeach
                </select>
                @endif
                <select class="form-control form-control-sm" id="statusFilter" style="border-radius: 8px; min-width: 180px;">
                    <option value="">Open & In Progress</option>
                    <option value="open">Open Only</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                </select>
                <select class="form-control form-control-sm" id="priorityFilter" style="border-radius: 8px; min-width: 130px;">
                    <option value="">All Priorities</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="clearFilters" style="border-radius: 8px;">Clear</button>
                @if(!$scopedStaff)
                <div class="ms-auto">
                    <a href="{{ route('hr.follow-ups.index', ['export' => 'csv']) }}" class="btn btn-outline-success btn-sm export-btn" style="border-radius: 8px;" title="Export CSV">
                        <i class="mdi mdi-file-excel mr-1"></i> Export
                    </a>
                </div>
                @endif
            </div>

            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table" id="followUpTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Staff</th>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Follow-up Modal -->
<div class="modal fade" id="followUpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form method="POST" action="{{ route('hr.follow-ups.store') }}">
                @csrf
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-clipboard-check-outline mr-2" style="color: var(--primary-color);"></i> New Follow-up</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-account text-primary mr-1"></i>Staff *</label>
                            @if($scopedStaff)
                                <input type="hidden" name="staff_id" value="{{ $scopedStaff->id }}">
                                <div class="form-control" style="border-radius: 8px; background: #f3f4f6; font-weight: 600;">{{ $scopedStaff->user?->surname }} {{ $scopedStaff->user?->firstname }}</div>
                            @else
                                <select class="form-control modal-select2" name="staff_id" required>
                                    <option value="">Select Staff</option>
                                    @foreach($staffList as $s)
                                        <option value="{{ $s->id }}">{{ $s->user?->surname }} {{ $s->user?->firstname }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-flag text-warning mr-1"></i>Priority *</label>
                            <select class="form-control" name="priority" required style="border-radius: 8px;">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-clock text-danger mr-1"></i>Due Date</label>
                            <input type="date" class="form-control" name="due_date" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-format-title text-info mr-1"></i>Subject *</label>
                            <input type="text" class="form-control" name="subject" required style="border-radius: 8px;" placeholder="Brief description of follow-up item">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-text-box text-secondary mr-1"></i>Details</label>
                            <textarea class="form-control" name="details" rows="3" style="border-radius: 8px;" placeholder="Additional details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script src="{{ asset('/plugins/dataT/datatables.js') }}"></script>
<script>
$(function(){
    var scopedStaffId = @json($scopedStaff?->id);

    const table = $('#followUpTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: "{{ route('hr.follow-ups.index') }}", data: function(d) { d.staff_id = scopedStaffId || $('#staffFilter').val(); d.status = $('#statusFilter').val(); d.priority = $('#priorityFilter').val(); } },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
            { data: 'staff_name', name: 'staff.user.surname' },
            { data: 'subject_col', name: 'subject' },
            { data: 'priority_col', name: 'priority' },
            { data: 'due_date_col', name: 'due_date' },
            { data: 'status_col', name: 'status' },
            { data: 'created_by_col', name: 'created_by' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'asc']],
        language: { emptyTable: "No follow-ups found", processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...' }
    });

    if (!scopedStaffId) {
        $('#staffFilter').select2({ placeholder: 'All Staff', allowClear: true, width: '220px' }).on('change', function() { table.ajax.reload(); });
    }
    $('#statusFilter, #priorityFilter').on('change', function() { table.ajax.reload(); });
    $('#clearFilters').click(function() {
        if (!scopedStaffId) $('#staffFilter').val('').trigger('change');
        $('#statusFilter, #priorityFilter').val('').trigger('change');
    });

    // Modal select2
    $('#followUpModal').on('shown.bs.modal', function() {
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).select2({ dropdownParent: $('#followUpModal'), width: '100%' });
        });
    });

    // AJAX form submit
    $('#followUpModal form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        $.ajax({
            url: $(this).attr('action'), type: 'POST', data: $(this).serialize(),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Follow-up created'); $('#followUpModal').modal('hide'); table.ajax.reload(); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Create'); }
        });
    });

    // AJAX resolve
    $(document).on('click', '.resolve-btn', function() {
        if(!confirm('Mark this follow-up as resolved?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success('Follow-up resolved'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Resolve failed'); $btn.prop('disabled', false); }
        });
    });

    // AJAX delete
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Delete this follow-up?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Deleted'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Delete failed'); $btn.prop('disabled', false); }
        });
    });

    // Reset form on modal close
    $('#followUpModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).val(null);
        });
    });
});
</script>
@endsection
