@extends('admin.layouts.app')
@section('title', 'Edit Clinic - ' . $clinic->name)
@section('page_name', 'Clinic')
@section('subpage_name', 'Edit Clinic')

@section('content')
    <div class="container-fluid">
        <div class="card-modern shadow-sm border-0">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 fw-bold text-dark">
                        <i class="mdi mdi-pencil-box-outline text-primary"></i> Edit Clinic Configuration
                    </h4>
                    <p class="text-muted mb-0 small">Customize clinic details and specialized vital sign metrics.</p>
                </div>
                <div class="btn-group">
                    <a href="{{ route('clinics.show', $clinic->id) }}" class="btn btn-outline-info btn-sm">
                        <i class="mdi mdi-eye"></i> View Profile
                    </a>
                    <a href="{{ route('clinics.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('clinics.update', $clinic->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-bold">Clinic Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-modern" id="name" name="name" value="{{ $clinic->name }}" required>
                        </div>
                    </div>

                    <hr class="light my-4">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">
                                <i class="mdi mdi-heart-pulse text-danger me-2"></i> Specialized Vitals Template
                            </h5>
                            <p class="text-muted small mb-0">Define custom metrics for this specific clinic. Standard metrics (BP, Temp, etc.) can also be overridden here.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm shadow-sm" id="add-field">
                            <i class="mdi mdi-plus-circle-outline"></i> Add Metric Field
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover" id="template-table">
                            <thead class="bg-light text-dark">
                                <tr>
                                    <th width="200">Field ID (Unique)</th>
                                    <th width="250">Display Label</th>
                                    <th width="150">Field Type</th>
                                    <th>Unit / Options</th>
                                    <th width="100" class="text-center">Required</th>
                                    <th width="80" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="template-body">
                                @if($clinic->vitals_template)
                                    @foreach($clinic->vitals_template as $index => $field)
                                        @php
                                            $val = $field['unit'] ?? ($field['options'] ?? '');
                                            $displayVal = is_array($val) ? implode(',', $val) : $val;
                                        @endphp
                                        <tr>
                                            <td><input type="text" name="vitals_template[{{ $index }}][name]" class="form-control form-control-sm" value="{{ $field['name'] }}" required placeholder="e.g. fundal_height"></td>
                                            <td><input type="text" name="vitals_template[{{ $index }}][label]" class="form-control form-control-sm" value="{{ $field['label'] }}" required placeholder="e.g. Fundal Height"></td>
                                            <td>
                                                <select name="vitals_template[{{ $index }}][type]" class="form-select form-select-sm">
                                                    <option value="text" {{ $field['type'] == 'text' ? 'selected' : '' }}>Text Input</option>
                                                    <option value="number" {{ $field['type'] == 'number' ? 'selected' : '' }}>Numeric Input</option>
                                                    <option value="select" {{ $field['type'] == 'select' ? 'selected' : '' }}>Dropdown Selection</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="vitals_template[{{ $index }}][unit]" class="form-control form-control-sm" value="{{ $displayVal }}" placeholder="Unit (cm) or Options (a,b,c)">
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check form-switch d-inline-block">
                                                    <input class="form-check-input" type="checkbox" name="vitals_template[{{ $index }}][required]" value="1" {{ (isset($field['required']) && $field['required']) ? 'checked' : '' }}>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-danger btn-xs remove-row"><i class="mdi mdi-delete"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end">
                        <a href="{{ route('clinics.index') }}" class="btn btn-light me-2">Cancel</a>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="mdi mdi-content-save me-1"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            let rowIndex = {{ $clinic->vitals_template ? count($clinic->vitals_template) : 0 }};

            $('#add-field').click(function() {
                const row = `
                    <tr>
                        <td><input type="text" name="vitals_template[${rowIndex}][name]" class="form-control form-control-sm" required placeholder="e.g. head_circumference"></td>
                        <td><input type="text" name="vitals_template[${rowIndex}][label]" class="form-control form-control-sm" required placeholder="e.g. Head Circumference"></td>
                        <td>
                            <select name="vitals_template[${rowIndex}][type]" class="form-select form-select-sm">
                                <option value="text">Text Input</option>
                                <option value="number" selected>Numeric Input</option>
                                <option value="select">Dropdown Selection</option>
                            </select>
                        </td>
                        <td><input type="text" name="vitals_template[${rowIndex}][unit]" class="form-control form-control-sm" placeholder="Unit (e.g. cm) or options (0,1,2)"></td>
                        <td class="text-center">
                             <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" name="vitals_template[${rowIndex}][required]" value="1">
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-danger btn-xs remove-row"><i class="mdi mdi-delete"></i></button>
                        </td>
                    </tr>
                `;
                $('#template-body').append(row);
                rowIndex++;
            });

            $(document).on('click', '.remove-row', function() {
                $(this).closest('tr').fadeOut(300, function() { $(this).remove(); });
            });
        });
    </script>
    @endpush
@endsection
