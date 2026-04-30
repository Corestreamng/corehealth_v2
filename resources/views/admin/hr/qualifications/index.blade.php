@extends('admin.layouts.app')
@section('title', 'Staff Qualifications')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Qualifications')

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
        ['url' => route('hr.trainings.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-certificate', 'label' => 'Trainings'],
        ['url' => route('hr.medical-exams.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-stethoscope', 'label' => 'Medical'],
        ['url' => route('hr.follow-ups.index', ['staff_id' => ($scopedStaff->id ?? '')]), 'icon' => 'mdi-clipboard-check-outline', 'label' => 'Follow-ups'],
    ]])

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-school text-primary"></i> {{ $scopedStaff ? ($scopedStaff->user?->surname . ' ' . $scopedStaff->user?->firstname . ' ' . $scopedStaff->user?->othername . ' — ') : '' }}Qualifications
                </h2>
                <p class="text-muted mb-0">{{ $scopedStaff ? 'Academic qualifications and certifications' : 'Academic qualifications and professional certifications' }}</p>
            </div>
            @can('hr-qualifications.create')
            <div class="d-flex gap-2" style="gap: 0.5rem;">
                @if(!$scopedStaff)
                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#importModal" style="border-radius: 8px;">
                    <i class="mdi mdi-file-upload mr-1"></i> Import
                </button>
                @endif
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#qualModal" style="border-radius: 8px;">
                    <i class="mdi mdi-plus mr-1"></i> Add Qualification
                </button>
            </div>
            @endcan
        </div>

        <div class="card-body">

            @include('admin.hr.partials.tracking-stats', ['trackingStats' => [
                ['label' => 'Total Qualifications', 'value' => $stats['total'], 'icon' => 'mdi-school', 'color' => '#6366f1'],
                ['label' => 'Verified', 'value' => $stats['verified'], 'icon' => 'mdi-check-circle', 'color' => '#10b981'],
                ['label' => 'Unverified', 'value' => $stats['unverified'], 'icon' => 'mdi-alert-circle-outline', 'color' => '#f59e0b'],
                ['label' => 'With Documents', 'value' => $stats['with_docs'], 'icon' => 'mdi-file-document', 'color' => '#3b82f6'],
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
                    <a href="{{ route('hr.qualifications.index', ['export' => 'csv']) }}" class="btn btn-outline-success btn-sm export-btn" style="border-radius: 8px;" title="Export CSV">
                        <i class="mdi mdi-file-excel mr-1"></i> Export
                    </a>
                </div>
            </div>
            @endif

            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table" id="qualTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Staff</th>
                                    <th>Qualification</th>
                                    <th>Institution</th>
                                    <th>Year</th>
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

<!-- Add Qualification Modal -->
<div class="modal fade" id="qualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form method="POST" action="{{ route('hr.qualifications.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-school mr-2" style="color: var(--primary-color);"></i> Add Qualification</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
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
                            <small class="text-muted">Employee this qualification belongs to</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-tag text-info mr-1"></i>Type *</label>
                            <select class="form-control" name="type" required style="border-radius: 8px;">
                                <option value="entry">Entry Qualification</option>
                                <option value="additional">Additional Qualification</option>
                            </select>
                            <small class="text-muted">Entry = at hire, Additional = acquired later</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-certificate text-success mr-1"></i>Qualification Name *</label>
                            <input type="text" class="form-control" name="qualification_name" required style="border-radius: 8px;" placeholder="e.g. B.Sc Nursing">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-book-open text-warning mr-1"></i>Field of Study</label>
                            <input type="text" class="form-control" name="field_of_study" style="border-radius: 8px;" placeholder="e.g. Nursing Science">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-school text-purple mr-1"></i>Institution</label>
                            <input type="text" class="form-control" name="institution" style="border-radius: 8px;" placeholder="e.g. University of Lagos">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar text-secondary mr-1"></i>Year of Graduation</label>
                            <input type="number" class="form-control" name="year_of_graduation" min="1950" max="{{ date('Y') }}" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-check text-info mr-1"></i>Date Obtained</label>
                            <input type="date" class="form-control" name="date_obtained" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-file-upload text-danger mr-1"></i>Document</label>
                            <input type="file" class="form-control" name="document" accept=".pdf,.jpg,.png" style="border-radius: 8px; height: auto; padding: 0.5rem;">
                            <small class="text-muted">PDF, JPG or PNG (max 2MB)</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-comment-text text-muted mr-1"></i>Notes</label>
                            <textarea class="form-control" name="notes" rows="2" style="border-radius: 8px;" placeholder="Additional remarks..."></textarea>
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="importForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title"><i class="mdi mdi-file-upload mr-2" style="color: var(--primary-color);"></i> Import Qualifications</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <a href="{{ route('hr.qualifications.import-template') }}" class="btn btn-outline-secondary btn-sm" style="border-radius: 8px;">
                            <i class="mdi mdi-download mr-1"></i> Download Template
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">Upload Excel/CSV File *</label>
                        <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required style="border-radius: 8px; height: auto; padding: 0.5rem;">
                        <small class="text-muted">Columns: staff_id, type, qualification_name, field_of_study, institution, year_of_graduation, date_obtained, notes</small>
                    </div>
                    <div id="importResult" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 8px;"><i class="mdi mdi-upload mr-1"></i> Import</button>
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

    const table = $('#qualTable').DataTable({
        processing: true, serverSide: true,
        ajax: { url: "{{ route('hr.qualifications.index') }}", data: function(d) { d.staff_id = scopedStaffId || $('#staffFilter').val(); } },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '40px' },
            { data: 'staff_name', name: 'staff.user.surname' },
            { data: 'qualification_col', name: 'qualification_name' },
            { data: 'institution_col', name: 'institution' },
            { data: 'year_col', name: 'year_of_graduation' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[4, 'desc']],
        language: { emptyTable: "No qualifications recorded yet", processing: '<div class="spinner-border spinner-border-sm text-primary"></div> Loading...' }
    });

    if (!scopedStaffId) {
        $('#staffFilter').select2({ placeholder: 'All Staff', allowClear: true, width: '220px' }).on('change', function() {
            table.ajax.reload();
            var params = { export: 'csv' };
            if ($(this).val()) params.staff_id = $(this).val();
            $('.export-btn').attr('href', "{{ route('hr.qualifications.index') }}?" + $.param(params));
        });
        $('#clearFilters').click(function() { $('#staffFilter').val('').trigger('change'); });
    }

    // Modal select2
    $('#qualModal').on('shown.bs.modal', function() {
        $(this).find('.modal-select2').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).select2({ dropdownParent: $('#qualModal'), width: '100%' });
        });
    });

    // AJAX form submit (with file upload)
    $('#qualModal form').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        var fd = new FormData(this);
        $.ajax({
            url: $(this).attr('action'), type: 'POST', data: fd, processData: false, contentType: false,
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Qualification added'); $('#qualModal').modal('hide'); table.ajax.reload(); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save'); }
        });
    });

    // AJAX verify
    $(document).on('click', '.verify-btn', function() {
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success('Qualification verified'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Verification failed'); $btn.prop('disabled', false); }
        });
    });

    // AJAX delete
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Remove this qualification?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Deleted'); table.ajax.reload(); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Delete failed'); $btn.prop('disabled', false); }
        });
    });

    // Import form
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Importing...');
        var fd = new FormData(this);
        $.ajax({
            url: "{{ route('hr.qualifications.import') }}", type: 'POST', data: fd, processData: false, contentType: false,
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) {
                toastr.success(res.message);
                var $r = $('#importResult').removeClass('d-none alert-danger').addClass('alert alert-success').html('<i class="mdi mdi-check-circle mr-1"></i>' + res.message);
                if (res.errors_detail) $r.append('<br><small>' + res.errors_detail.join('<br>') + '</small>');
                table.ajax.reload();
            },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Import failed'; toastr.error(msg); $('#importResult').removeClass('d-none alert-success').addClass('alert alert-danger').html(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-upload mr-1"></i> Import'); }
        });
    });

    // Reset form on modal close
    $('#qualModal').on('hidden.bs.modal', function() {
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
