@extends('admin.layouts.app')
@section('title', 'Services ')
@section('page_name', 'Services ')
@section('subpage_name', 'Create Service')
@section('content')
    <section class="container">

        <div class="card-modern  mb-3">
            {!! Form::open(['route' => 'services.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
            {{ csrf_field() }}
            <div class="card-header bg-transparent ">{{ __('Create Service') }}</div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="category_id" class="col-md-2 col-form-label">{{ __('Category') }} <i class="text-danger">*</i>
                    </label>
                    <div class="col-md-10">
                        {!! Form::select('category', $category, null, [
                            'id' => 'category_id',
                            'name' => 'category_id',
                            'placeholder' => 'Pick Category',
                            'class' => 'form-control',
                            'data-live-search' => 'true',
                            'required' => 'true',
                        ]) !!}
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-md-2 col-form-label">{{ __('Name') }} <i
                            class="text-danger">*</i></label>
                    <div class="col-md-10">
                        <input type="text" id="service_name" class="form-control" name="service_name"
                            value="{{ old('service_name') }}" placeholder="Service Name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="service_code" class="col-md-2 col-form-label">{{ __('Code') }}</label>
                    <div class="col-md-10">
                        <input type="text" id="service_code" class="form-control" name="service_code"
                            value="{{ old('service_code') }}" placeholder="Service Code">
                    </div>
                </div>

                {{-- Procedure-specific fields (shown only when Procedure category is selected) --}}
                <div id="procedure-fields" style="display: none; border: 1px solid #e3e6f0; border-radius: 8px; padding: 20px; margin-top: 15px; background: #f8f9fc;">
                    <h6 class="mb-3 font-weight-bold text-primary">
                        <i class="mdi mdi-hospital-box mr-2"></i>Procedure Details
                    </h6>

                    <div class="form-group row">
                        <label for="procedure_category_id" class="col-md-2 col-form-label">
                            {{ __('Procedure Category') }} <i class="text-danger">*</i>
                        </label>
                        <div class="col-md-10">
                            <select name="procedure_category_id" id="procedure_category_id" class="form-control">
                                <option value="">-- Select Procedure Category --</option>
                                @foreach($procedureCategories as $procCat)
                                    <option value="{{ $procCat->id }}" {{ old('procedure_category_id') == $procCat->id ? 'selected' : '' }}>
                                        {{ $procCat->name }} ({{ $procCat->code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Surgical specialty category (e.g., General Surgery, ENT, O&G)</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="procedure_code" class="col-md-2 col-form-label">{{ __('Procedure Code') }}</label>
                        <div class="col-md-10">
                            <input type="text" id="procedure_code" class="form-control" name="procedure_code"
                                value="{{ old('procedure_code') }}" placeholder="e.g., LAP-CHOLE-001">
                            <small class="text-muted">Optional specific procedure code (defaults to service code)</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="is_surgical" class="col-md-2 col-form-label">{{ __('Surgical Procedure') }}</label>
                        <div class="col-md-10">
                            <div class="custom-control custom-switch mt-2">
                                <input type="checkbox" class="custom-control-input" id="is_surgical" name="is_surgical" value="1" {{ old('is_surgical') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_surgical">This is a surgical procedure (requires operating room, surgical team)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estimated_duration_minutes" class="col-md-2 col-form-label">{{ __('Est. Duration') }}</label>
                        <div class="col-md-10">
                            <div class="input-group" style="max-width: 200px;">
                                <input type="number" id="estimated_duration_minutes" class="form-control"
                                    name="estimated_duration_minutes" value="{{ old('estimated_duration_minutes') }}"
                                    placeholder="60" min="1">
                                <div class="input-group-append">
                                    <span class="input-group-text">minutes</span>
                                </div>
                            </div>
                            <small class="text-muted">Estimated procedure duration for scheduling</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="procedure_description" class="col-md-2 col-form-label">{{ __('Description') }}</label>
                        <div class="col-md-10">
                            <textarea id="procedure_description" class="form-control" name="procedure_description"
                                rows="3" placeholder="Procedure description, indications, or notes...">{{ old('procedure_description') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-transparent ">
                <div class="form-group row">
                    <div class="col-md-6"><a href="{{ route('services.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6 "><button type="submit" class="btn btn-primary pull-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

    </section>

@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const procedureCategoryId = {{ $procedureCategoryId ?? 'null' }};
        const $categorySelect = $('#category_id');
        const $procedureFields = $('#procedure-fields');
        const $procedureCategorySelect = $('#procedure_category_id');

        function toggleProcedureFields() {
            const selectedCategoryId = parseInt($categorySelect.val());

            if (procedureCategoryId && selectedCategoryId === procedureCategoryId) {
                $procedureFields.slideDown(300);
                $procedureCategorySelect.attr('required', true);
            } else {
                $procedureFields.slideUp(300);
                $procedureCategorySelect.attr('required', false);
            }
        }

        // Initial check
        toggleProcedureFields();

        // On category change
        $categorySelect.on('change', toggleProcedureFields);
    });
</script>
@endsection
