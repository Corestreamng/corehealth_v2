@extends('admin.layouts.app')
@section('title', 'Medical Exams')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Medical Exams')

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
        ['url' => route('hr.follow-ups.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-clipboard-check-outline', 'label' => 'Follow-ups'],
    ]])

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-stethoscope text-primary"></i> {{ $scopedStaff ? ($scopedStaff->user?->surname . ' ' . $scopedStaff->user?->firstname . ' ' . $scopedStaff->user?->othername . ' — ') : '' }}Medical Exams
                </h2>
                <p class="text-muted mb-0">{{ $scopedStaff ? 'Medical examination history and scheduling' : 'Pre-employment, periodic, and exit medical examinations' }}</p>
            </div>
            @can('hr-medical-exams.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#examModal" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> Record Exam
            </button>
            @endcan
        </div>

        <div class="card-body">

            @include('admin.hr.partials.tracking-stats', ['trackingStats' => [
                ['label' => 'Total Exams', 'value' => $stats['total'], 'icon' => 'mdi-stethoscope', 'color' => '#6366f1'],
                ['label' => 'Fit', 'value' => $stats['fit'], 'icon' => 'mdi-check-circle', 'color' => '#10b981'],
                ['label' => 'Overdue', 'value' => $stats['overdue'], 'icon' => 'mdi-alert-circle', 'color' => '#ef4444', 'subtitle' => 'Past next exam due'],
                ['label' => 'Upcoming (3mo)', 'value' => $stats['upcoming'], 'icon' => 'mdi-calendar-clock', 'color' => '#f59e0b'],
            ]])

            <!-- Filter -->
            <div class="filter-bar d-flex align-items-center gap-2 flex-wrap mb-3" style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filter:</label>
                @if(!$scopedStaff)
                <select class="form-control form-control-sm" id="staffFilter" style="min-width: 220px; border-radius: 8px;">
                    <option value="">All Staff</option>
                    @foreach($staffList as $s)
                        <option value="{{ $s->id }}">{{ $s->user?->surname }} {{ $s->user?->firstname }} {{ $s->user?->othername }} ({{ $s->employee_id }})</option>
                    @endforeach
                </select>
                @endif
                <select class="form-control form-control-sm" id="examTypeFilter" style="border-radius: 8px; min-width: 140px;">
                    <option value="">All Types</option>
                    <option value="pre_employment">Pre-Employment</option>
                    <option value="periodic">Periodic</option>
                    <option value="exit">Exit</option>
                </select>
                <select class="form-control form-control-sm" id="resultFilter" style="border-radius: 8px; min-width: 130px;">
                    <option value="">All Results</option>
                    <option value="fit">Fit</option>
                    <option value="unfit">Unfit</option>
                    <option value="conditional">Conditional</option>
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="clearFilters" style="border-radius: 8px;">Clear</button>
                @if(!$scopedStaff)
                <div class="ms-auto">
                    <a href="{{ route('hr.medical-exams.index', ['export' => 'csv']) }}" class="btn btn-outline-success btn-sm export-btn" style="border-radius: 8px;" title="Export CSV">
                        <i class="mdi mdi-file-excel mr-1"></i> Export
                    </a>
                </div>
                @endif
            </div>

            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table" id="examTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Staff</th>
                                    <th>Exam</th>
                                    <th>Result</th>
                                    <th>Next Due</th>
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

<!-- Record Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form method="POST" action="{{ route('hr.medical-exams.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-stethoscope mr-2" style="color: var(--primary-color);"></i> Record Medical Exam</h5>
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
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar text-danger mr-1"></i>Exam Date *</label>
                            <input type="date" class="form-control" name="exam_date" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-stethoscope text-info mr-1"></i>Exam Type *</label>
                            <select class="form-control" name="exam_type" required style="border-radius: 8px;">
                                <option value="pre_employment">Pre-Employment</option>
                                <option value="periodic">Periodic</option>
                                <option value="exit">Exit</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-clipboard-check text-success mr-1"></i>Result *</label>
                            <select class="form-control" name="result" required style="border-radius: 8px;">
                                <option value="fit">Fit</option>
                                <option value="unfit">Unfit</option>
                                <option value="conditional">Conditional</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-clock text-warning mr-1"></i>Next Exam Due</label>
                            <input type="date" class="form-control" name="next_exam_due" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-doctor text-primary mr-1"></i>Conducted By</label>
                            <input type="text" class="form-control" name="conducted_by" style="border-radius: 8px;" placeholder="Doctor / clinic name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-file-document text-info mr-1"></i>Document</label>
                            <input type="file" class="form-control" name="document" accept=".pdf,.jpg,.png" style="border-radius: 8px; height: auto; padding: 0.5rem;">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-note-text text-secondary mr-1"></i>Notes</label>
                            <textarea class="form-control" name="notes" rows="2" style="border-radius: 8px;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Exam Modal -->
<div class="modal fade" id="editExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="editExamForm">
                @csrf
                @method('PUT')
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-pencil mr-2" style="color: var(--primary-color);"></i> Edit Exam Result</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Result *</label>
                            <select class="form-control" name="result" id="editResult" required style="border-radius: 8px;">
                                <option value="fit">Fit</option>
                                <option value="unfit">Unfit</option>
                                <option value="conditional">Conditional</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;">Next Exam Due</label>
                            <input type="date" class="form-control" name="next_exam_due" id="editNextDue" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;">Notes</label>
                            <textarea class="form-control" name="notes" id="editNotes" rows="2" style="border-radius: 8px;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="mdi mdi-check mr-1"></i> Update</button>
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

    const table = $('#examTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: "{{ route('hr.medical-exams.index') }}", data: function(d) { d.staff_id = scopedStaffId || $('#staffFilter').val(); d.exam_type = $('#examTypeFilter').val(); d.result = $('#resultFilter').val(); } },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
            { data: 'staff_name', name: 'staff.user.surname' },
            { data: 'exam_col', name: 'exam_type' },
            { data: 'result_col', name: 'result' },
            { data: 'next_due_col', name: 'next_exam_due' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: { emptyTable: "No medical exams recorded yet", processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...' }
    });

    if (!scopedStaffId) {
        $('#staffFilter').select2({ placeholder: 'All Staff', allowClear: true, width: '220px' }).on('change', function() { table.ajax.reload(); });
    }
    $('#examTypeFilter, #resultFilter').on('change', function() { table.ajax.reload(); });
    $('#clearFilters').click(function() {
        if (!scopedStaffId) $('#staffFilter').val('').trigger('change');
        $('#examTypeFilter, #resultFilter').val('').trigger('change');
    });

    // Modal select2
    $('#examModal').on('shown.bs.modal', function() {
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).select2({ dropdownParent: $('#examModal'), width: '100%' });
        });
    });

    // AJAX form submit (with file upload)
    $('#examModal form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        var fd = new FormData(this);
        $.ajax({
            url: $(this).attr('action'), type: 'POST', data: fd, processData: false, contentType: false,
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Exam recorded'); $('#examModal').modal('hide'); table.ajax.reload(); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save'); }
        });
    });

    // AJAX delete
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Remove this exam record?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Deleted'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Delete failed'); $btn.prop('disabled', false); }
        });
    });

    // Edit exam - populate and open modal
    $(document).on('click', '.edit-exam-btn', function() {
        var $btn = $(this);
        $('#editResult').val($btn.data('result'));
        $('#editNextDue').val($btn.data('next-due'));
        $('#editNotes').val($btn.data('notes'));
        $('#editExamForm').attr('action', $btn.data('url'));
        $('#editExamModal').modal('show');
    });

    // AJAX edit submit
    $('#editExamForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Updating...');
        $.ajax({
            url: $(this).attr('action'), type: 'PUT', data: $(this).serialize(),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Updated'); $('#editExamModal').modal('hide'); table.ajax.reload(); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Update failed'; toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Update'); }
        });
    });

    // Reset form on modal close
    $('#examModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).val(null);
        });
        $(this).find('.custom-file-label').html('Choose file...');
    });
});
</script>
@endsection
