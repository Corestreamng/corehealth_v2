@extends('admin.layouts.app')
@section('title', 'Staff Promotions')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Promotions')

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
        ['url' => route('hr.qualifications.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-school', 'label' => 'Qualifications'],
        ['url' => route('hr.trainings.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-certificate', 'label' => 'Trainings'],
        ['url' => route('hr.medical-exams.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-stethoscope', 'label' => 'Medical'],
        ['url' => route('hr.follow-ups.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-clipboard-check-outline', 'label' => 'Follow-ups'],
    ]])

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-arrow-up-bold-circle text-primary"></i> {{ $scopedStaff ? ($scopedStaff->user?->surname . ' ' . $scopedStaff->user?->firstname . ' ' . $scopedStaff->user?->othername . ' — ') : '' }}Promotions
                </h2>
                <p class="text-muted mb-0">{{ $scopedStaff ? 'Promotion history and grade level changes' : 'Record and track staff promotions and grade level changes' }}</p>
            </div>
            @can('hr-promotions.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#promoModal" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> Record Promotion
            </button>
            @endcan
        </div>

        <div class="card-body">

            @include('admin.hr.partials.tracking-stats', ['trackingStats' => [
                ['label' => 'Total Promotions', 'value' => $stats['total'], 'icon' => 'mdi-arrow-up-bold-circle', 'color' => '#6366f1'],
                ['label' => 'This Year', 'value' => $stats['this_year'], 'icon' => 'mdi-calendar-check', 'color' => '#10b981'],
                ['label' => 'Due Within 3 Months', 'value' => $stats['due_soon'], 'icon' => 'mdi-clock-alert-outline', 'color' => '#f59e0b', 'subtitle' => 'Next promotion due'],
            ]])

            @if(!$scopedStaff)
            <!-- Filter -->
            <div class="filter-bar d-flex align-items-center gap-2 flex-wrap mb-3" style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filter:</label>
                <select class="form-control form-control-sm" id="staffFilter" style="min-width: 220px; border-radius: 8px;">
                    <option value="">All Staff</option>
                    @foreach($staffList as $s)
                        <option value="{{ $s->id }}">{{ $s->user?->surname }} {{ $s->user?->firstname }} {{ $s->user?->othername }} ({{ $s->employee_id }})</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="clearFilters" style="border-radius: 8px;">Clear</button>
                <div class="ms-auto">
                    <a href="{{ route('hr.promotions.index', ['export' => 'csv']) }}" class="btn btn-outline-success btn-sm export-btn" style="border-radius: 8px;" title="Export CSV">
                        <i class="mdi mdi-file-excel mr-1"></i> Export
                    </a>
                </div>
            </div>
            @endif

            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table" id="promoTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Staff</th>
                                    <th>Grade Change</th>
                                    <th>New Title</th>
                                    <th>Date</th>
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

<!-- Record Promotion Modal -->
<div class="modal fade" id="promoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form method="POST" action="{{ route('hr.promotions.store') }}">
                @csrf
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-arrow-up-bold-circle mr-2" style="color: var(--primary-color);"></i> Record Promotion</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-account text-primary mr-1"></i>Staff *</label>
                            @if($scopedStaff)
                                <input type="hidden" name="staff_id" value="{{ $scopedStaff->id }}">
                                <div class="form-control" style="border-radius: 8px; background: #f3f4f6; font-weight: 600;">{{ $scopedStaff->user?->surname }} {{ $scopedStaff->user?->firstname }} {{ $scopedStaff->user?->othername }} ({{ $scopedStaff->employee_id }})</div>
                            @else
                                <select class="form-control modal-select2" name="staff_id" required>
                                    <option value="">Select Staff</option>
                                    @foreach($staffList as $s)
                                        <option value="{{ $s->id }}">{{ $s->user?->surname }} {{ $s->user?->firstname }} {{ $s->user?->othername }} ({{ $s->employee_id }})</option>
                                    @endforeach
                                </select>
                            @endif
                            <small class="text-muted">Employee being promoted</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-stairs text-info mr-1"></i>New Grade Level</label>
                            <select class="form-control" name="to_grade_level_id" style="border-radius: 8px;">
                                <option value="">— Same —</option>
                                @foreach($gradeLevels as $gl)
                                    <option value="{{ $gl->id }}">{{ $gl->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Leave blank to keep current grade</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-briefcase text-success mr-1"></i>New Job Title</label>
                            <input type="text" class="form-control" name="to_job_title" style="border-radius: 8px;" placeholder="e.g. Senior Nurse">
                            <small class="text-muted">New designation after promotion</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-check text-warning mr-1"></i>Promotion Date *</label>
                            <input type="date" class="form-control" name="promotion_date" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-arrow-right text-secondary mr-1"></i>Effective Date</label>
                            <input type="date" class="form-control" name="effective_date" style="border-radius: 8px;">
                            <small class="text-muted">When changes take effect</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-clock text-purple mr-1"></i>Next Promotion Due</label>
                            <input type="date" class="form-control" name="next_promotion_due_date" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-shield-check text-teal mr-1"></i>Authority</label>
                            <input type="text" class="form-control" name="authority" style="border-radius: 8px;" placeholder="e.g. Board Resolution #123">
                            <small class="text-muted">Approving authority or reference</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-comment-text text-muted mr-1"></i>Remarks</label>
                            <textarea class="form-control" name="remarks" rows="2" style="border-radius: 8px;" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Save Promotion</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
$(function(){
    var scopedStaffId = @json($scopedStaff?->id);

    // DataTable
    const table = $('#promoTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: "{{ route('hr.promotions.index') }}", data: function(d) { d.staff_id = scopedStaffId || $('#staffFilter').val(); } },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
            { data: 'staff_name', name: 'staff.user.surname' },
            { data: 'grade_change', name: 'to_grade_level_id', orderable: false },
            { data: 'new_title', name: 'to_job_title' },
            { data: 'date_col', name: 'promotion_date' },
            { data: 'action', name: 'action', orderable: false, searchable: false, width: '60px' }
        ],
        order: [[4, 'desc']],
        language: { emptyTable: "No promotions recorded yet", processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...' }
    });

    if (!scopedStaffId) {
        $('#staffFilter').select2({ placeholder: 'All Staff', allowClear: true, width: '220px' }).on('change', function() {
            table.ajax.reload();
            var params = { export: 'csv' };
            if ($(this).val()) params.staff_id = $(this).val();
            $('.export-btn').attr('href', "{{ route('hr.promotions.index') }}?" + $.param(params));
        });
        $('#clearFilters').click(function() { $('#staffFilter').val('').trigger('change'); });
    }

    // Modal select2
    $('#promoModal').on('shown.bs.modal', function() {
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).select2({ dropdownParent: $('#promoModal'), width: '100%' });
        });
    });

    // AJAX form submit
    $('#promoModal form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        $.ajax({
            url: $(this).attr('action'), type: 'POST', data: $(this).serialize(),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Promotion recorded'); $('#promoModal').modal('hide'); table.ajax.reload(); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save Promotion'); }
        });
    });

    // Reset form on modal close
    $('#promoModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).val(null);
        });
    });

    // AJAX delete
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Remove this promotion record?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Deleted'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Delete failed'); $btn.prop('disabled', false); }
        });
    });
});
</script>
@endsection
