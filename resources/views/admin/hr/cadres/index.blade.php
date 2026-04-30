@extends('admin.layouts.app')
@section('title', 'Cadres')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Cadres')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .hr-table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; background: #f9fafb; }
        .hr-table td { vertical-align: middle !important; }
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
                    <i class="mdi mdi-account-group text-primary"></i> Cadres
                </h2>
                <p class="text-muted mb-0">Staff cadre classifications (e.g. Nursing, Medical, Admin)</p>
            </div>
            @can('hr-cadres.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cadreModal" onclick="resetForm()" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> Add Cadre
            </button>
            @endcan
        </div>

        <div class="card-body">
            <div class="card-modern" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped hr-table">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Staff Count</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cadres as $i => $cadre)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td class="font-weight-bold">{{ $cadre->name }}</td>
                                    <td><code>{{ $cadre->code }}</code></td>
                                    <td><span class="badge badge-info">{{ $cadre->staff_count }}</span></td>
                                    <td>{!! $cadre->is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>' !!}</td>
                                    <td>
                                        @can('hr-cadres.edit')
                                        <button class="btn btn-sm btn-outline-primary edit-btn" data-id="{{ $cadre->id }}" data-name="{{ $cadre->name }}" data-code="{{ $cadre->code }}" data-description="{{ $cadre->description }}" data-is_active="{{ $cadre->is_active }}"><i class="mdi mdi-pencil"></i></button>
                                        @endcan
                                        @can('hr-cadres.delete')
                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-url="{{ route('hr.cadres.destroy', $cadre) }}"><i class="mdi mdi-delete"></i></button>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No cadres configured yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cadreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="cadreForm" method="POST" action="{{ route('hr.cadres.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title" id="modalTitle"><i class="mdi mdi-account-group mr-2" style="color: var(--primary-color);"></i> Add Cadre</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-tag text-primary mr-1"></i>Name *</label>
                        <input type="text" class="form-control" name="name" id="f_name" required style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-barcode text-info mr-1"></i>Code</label>
                        <input type="text" class="form-control" name="code" id="f_code" maxlength="20" style="border-radius: 8px;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-text-box text-secondary mr-1"></i>Description</label>
                        <textarea class="form-control" name="description" id="f_description" rows="2" style="border-radius: 8px;"></textarea>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="f_is_active" name="is_active" value="1" checked>
                        <label class="custom-control-label" for="f_is_active" style="font-weight: 600;">Active</label>
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
    $('#cadreForm')[0].reset();
    $('#cadreForm').data('url', "{{ route('hr.cadres.store') }}");
    $('#cadreForm').data('method', 'POST');
    $('#modalTitle').html('<i class="mdi mdi-account-group mr-2" style="color: var(--primary-color);"></i> Add Cadre');
    $('#f_is_active').prop('checked', true);
}
$(document).on('click', '.edit-btn', function() {
    var d = $(this).data();
    $('#cadreForm').data('url', "{{ url('hr/cadres') }}/" + d.id);
    $('#cadreForm').data('method', 'PUT');
    $('#modalTitle').html('<i class="mdi mdi-account-group mr-2" style="color: var(--primary-color);"></i> Edit Cadre');
    $('#f_name').val(d.name); $('#f_code').val(d.code); $('#f_description').val(d.description);
    $('#f_is_active').prop('checked', d.is_active == 1);
    $('#cadreModal').modal('show');
});
$(function(){
    $('#cadreForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        $.ajax({
            url: $(this).data('url'), type: 'POST',
            data: $(this).serialize() + '&_method=' + $(this).data('method'),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Saved successfully'); $('#cadreModal').modal('hide'); setTimeout(()=>location.reload(), 800); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save'); }
        });
    });
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Delete this cadre?')) return;
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
