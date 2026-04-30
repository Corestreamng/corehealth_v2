@extends('admin.layouts.app')
@section('title', 'Grade Levels')
@section('page_name', 'Human Resources')
@section('subpage_name', 'Grade Levels')

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
                    <i class="mdi mdi-stairs text-primary"></i> Grade Levels (CONHESS)
                </h2>
                <p class="text-muted mb-0">Salary grade levels, steps, and retirement parameters</p>
            </div>
            @can('hr-grade-levels.create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#glModal" onclick="resetForm()" style="border-radius: 8px;">
                <i class="mdi mdi-plus mr-1"></i> Add Grade Level
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
                                    <th>Grade Level</th>
                                    <th>Salary Range</th>
                                    <th>Retirement</th>
                                    <th>Staff</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($gradeLevels as $i => $gl)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td class="font-weight-bold">{{ $gl->name }}<br><small class="text-muted">Level {{ $gl->level }} / Step {{ $gl->step }} @if($gl->min_years_to_next)· {{ $gl->min_years_to_next }}yr to next @endif</small></td>
                                    <td>
                                        @if($gl->min_salary || $gl->max_salary)
                                            ₦{{ number_format($gl->min_salary ?? 0) }}<br><small class="text-muted">to ₦{{ number_format($gl->max_salary ?? 0) }}</small>
                                        @else —
                                        @endif
                                    </td>
                                    <td>{{ $gl->retirement_age ?? '—' }}yr @if($gl->max_years_of_service)<br><small class="text-muted">Max {{ $gl->max_years_of_service }}yr svc</small>@endif</td>
                                    <td><span class="badge badge-info">{{ $gl->staff_count }}</span></td>
                                    <td>
                                        @can('hr-grade-levels.edit')
                                        <button class="btn btn-sm btn-outline-primary edit-btn" data-id="{{ $gl->id }}" data-name="{{ $gl->name }}" data-level="{{ $gl->level }}" data-step="{{ $gl->step }}"
                                            data-min_years_to_next="{{ $gl->min_years_to_next }}" data-retirement_age="{{ $gl->retirement_age }}"
                                            data-max_years_of_service="{{ $gl->max_years_of_service }}" data-min_salary="{{ $gl->min_salary }}"
                                            data-max_salary="{{ $gl->max_salary }}" data-description="{{ $gl->description }}"
                                            data-is_active="{{ $gl->is_active }}"><i class="mdi mdi-pencil"></i></button>
                                        @endcan
                                        @can('hr-grade-levels.delete')
                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-url="{{ route('hr.grade-levels.destroy', $gl) }}"><i class="mdi mdi-delete"></i></button>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No grade levels configured. Run the HR seeder to populate CONHESS levels.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="glModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none;">
            <form id="glForm" method="POST" action="{{ route('hr.grade-levels.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-header bg-white" style="border-bottom: 1px solid #e9ecef;">
                    <h5 class="modal-title" id="modalTitle"><i class="mdi mdi-stairs mr-2" style="color: var(--primary-color);"></i> Add Grade Level</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-tag text-primary mr-1"></i>Name *</label>
                            <input type="text" class="form-control" name="name" id="f_name" required style="border-radius: 8px;" placeholder="e.g. CONHESS 06/1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-stairs text-info mr-1"></i>Level *</label>
                            <input type="number" class="form-control" name="level" id="f_level" required min="1" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-numeric text-info mr-1"></i>Step *</label>
                            <input type="number" class="form-control" name="step" id="f_step" required min="1" style="border-radius: 8px;">
                        </div>
                    </div>
                    <hr class="my-3">
                    <h6 class="mb-3" style="font-weight: 600;"><i class="mdi mdi-cog mr-1"></i> Parameters</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-clock-outline text-warning mr-1"></i>Min Years to Next</label>
                            <input type="number" class="form-control" name="min_years_to_next" id="f_min_years_to_next" min="1" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-account-clock text-danger mr-1"></i>Retirement Age</label>
                            <input type="number" class="form-control" name="retirement_age" id="f_retirement_age" min="40" max="75" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-calendar-range text-success mr-1"></i>Max Years of Service</label>
                            <input type="number" class="form-control" name="max_years_of_service" id="f_max_years_of_service" min="10" max="45" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-currency-ngn text-success mr-1"></i>Min Salary (₦)</label>
                            <input type="number" class="form-control" name="min_salary" id="f_min_salary" min="0" step="0.01" style="border-radius: 8px;">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label" style="font-weight: 600;"><i class="mdi mdi-currency-ngn text-danger mr-1"></i>Max Salary (₦)</label>
                            <input type="number" class="form-control" name="max_salary" id="f_max_salary" min="0" step="0.01" style="border-radius: 8px;">
                        </div>
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
    $('#glForm')[0].reset();
    $('#glForm').data('url', "{{ route('hr.grade-levels.store') }}");
    $('#glForm').data('method', 'POST');
    $('#modalTitle').html('<i class="mdi mdi-stairs mr-2" style="color: var(--primary-color);"></i> Add Grade Level');
    $('#f_is_active').prop('checked', true);
}
$(document).on('click', '.edit-btn', function() {
    var d = $(this).data();
    $('#glForm').data('url', "{{ url('hr/grade-levels') }}/" + d.id);
    $('#glForm').data('method', 'PUT');
    $('#modalTitle').html('<i class="mdi mdi-stairs mr-2" style="color: var(--primary-color);"></i> Edit Grade Level');
    ['name','level','step','min_years_to_next','retirement_age','max_years_of_service','min_salary','max_salary','description'].forEach(function(f){ $('#f_'+f).val(d[f]); });
    $('#f_is_active').prop('checked', d.is_active == 1);
    $('#glModal').modal('show');
});
$(function(){
    $('#glForm').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('[type=submit]').prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
        $.ajax({
            url: $(this).data('url'), type: 'POST',
            data: $(this).serialize() + '&_method=' + $(this).data('method'),
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            success: function(res) { toastr.success(res.message || 'Saved successfully'); $('#glModal').modal('hide'); setTimeout(()=>location.reload(), 800); },
            error: function(xhr) { var msg = xhr.responseJSON?.message || 'Something went wrong'; if(xhr.responseJSON?.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('<br>'); toastr.error(msg); },
            complete: function() { $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Save'); }
        });
    });
    $(document).on('click', '.delete-btn', function() {
        if(!confirm('Delete this grade level?')) return;
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
