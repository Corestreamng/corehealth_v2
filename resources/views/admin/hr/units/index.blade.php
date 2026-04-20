@extends('admin.layouts.app')
@section('title', 'Units')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Units')

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

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 font-weight-bold text-dark">
                    <i class="mdi mdi-office-building text-primary"></i> Units
                </h2>
                <p class="text-muted mb-0">Manage organisational units within departments</p>
            </div>
            @can('hr-units.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="resetForm()" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> Add Unit
            </button>
            @endcan
        </div>

        <div class="card-body">
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Unit</th>
                                    <th>Department</th>
                                    <th>Head of Unit</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($units as $i => $unit)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td class="font-weight-bold">{{ $unit->name }} @if($unit->code)<br><small class="text-muted"><code>{{ $unit->code }}</code></small>@endif</td>
                                    <td>{{ $unit->department?->name ?? '—' }}</td>
                                    <td>{{ $unit->headOfUnit ? $unit->headOfUnit->surname . ' ' . $unit->headOfUnit->firstname : '—' }}</td>
                                    <td>{!! $unit->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' !!}</td>
                                    <td>
                                        @can('hr-units.edit')
                                        <button class="btn btn-sm btn-outline-primary edit-btn" data-id="{{ $unit->id }}" data-name="{{ $unit->name }}" data-code="{{ $unit->code }}" data-department_id="{{ $unit->department_id }}" data-head_of_unit_id="{{ $unit->head_of_unit_id }}" data-description="{{ $unit->description }}" data-is_active="{{ $unit->is_active }}"><i class="mdi mdi-pencil"></i></button>
                                        @endcan
                                        @can('hr-units.delete')
                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-url="{{ route('hr.units.destroy', $unit) }}"><i class="mdi mdi-delete"></i></button>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No units configured yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Unit Modal -->
<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="unitForm" method="POST" action="{{ route('hr.units.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title" id="modalTitle"><i class="mdi mdi-office-building mr-2" style="color: var(--primary-color);"></i> Add Unit</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-tag text-primary mr-1"></i>Name *</label>
                            <input type="text" class="form-control" name="name" id="f_name" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-barcode text-info mr-1"></i>Code</label>
                            <input type="text" class="form-control" name="code" id="f_code" maxlength="20" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-domain text-success mr-1"></i>Department</label>
                            <select class="form-control" name="department_id" id="f_department_id" style="border-radius: 8px;">
                                <option value="">— None —</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-account-tie text-warning mr-1"></i>Head of Unit</label>
                            <select class="form-control select2" name="head_of_unit_id" id="f_head_of_unit_id" style="border-radius: 8px;">
                                <option value="">— None —</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->surname }} {{ $u->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-text-box text-secondary mr-1"></i>Description</label>
                            <textarea class="form-control" name="description" id="f_description" rows="2" style="border-radius: 8px;"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="f_is_active" name="is_active" value="1" checked>
                                <label class="custom-control-label" for="f_is_active" style="font-weight: 600;">Active</label>
                            </div>
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
@endsection
@section('scripts')
<script>
function resetForm() {
    $('#unitForm')[0].reset();
    $('#unitForm').data('url', "{{ route('hr.units.store') }}");
    $('#unitForm').data('method', 'POST');
    $('#modalTitle').html('<i class="mdi mdi-office-building mr-2" style="color: var(--primary-color);"></i> Add Unit');
    $('#f_is_active').prop('checked', true);
}
$(document).on('click', '.edit-btn', function() {
    var d = $(this).data();
    $('#unitForm').data('url', "{{ url('hr/units') }}/" + d.id);
    $('#unitForm').data('method', 'PUT');
    $('#modalTitle').html('<i class="mdi mdi-office-building mr-2" style="color: var(--primary-color);"></i> Edit Unit');
    $('#f_name').val(d.name);
    $('#f_code').val(d.code);
    $('#f_department_id').val(d.department_id);
    $('#f_head_of_unit_id').val(d.head_of_unit_id).trigger('change');
    $('#f_description').val(d.description);
    $('#f_is_active').prop('checked', d.is_active == 1);
    $('#unitModal').modal('show');
});
$(function(){
    $('.select2').select2({ dropdownParent: $('#unitModal') });

    // AJAX form submit
    $('#unitForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        $.ajax({
            url: $(this).data('url'),
            type: 'POST',
            data: $(this).serialize() + '&_method=' + $(this).data('method'),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Saved successfully'); $('#unitModal').modal('hide'); setTimeout(()=>location.reload(), 800); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save'); }
        });
    });

    // AJAX delete
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Delete this unit?')) return;
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: $(this).data('url'), type: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Deleted'); setTimeout(()=>location.reload(), 800); },
            error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Delete failed'); $btn.prop('disabled', false); }
        });
    });
});
</script>
@endsection
