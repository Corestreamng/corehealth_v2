@extends('admin.layouts.app')
@section('title', 'Edit Service')
@section('page_name', 'Services')
@section('subpage_name', 'Edit Service')
@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .cat-option { display: inline-flex; align-items: center; padding: 10px 14px; border: 2px solid #dee2e6; border-radius: 8px; cursor: pointer; transition: all .2s; }
        .cat-option:hover { border-color: var(--primary-color); }
        .cat-option.active { border-color: var(--primary-color); background: var(--primary-light); }
        .cat-option input[type="radio"] { display: none; }
        .cat-option i { font-size: 1.2rem; margin-right: 6px; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection
@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1 font-weight-bold text-dark">Edit Service</h2>
                        <p class="text-muted mb-0">{{ $product->service_name }} &mdash; {{ $product->service_code }}</p>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('services.update', $product->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            {{-- Left Sidebar --}}
                            <div class="col-lg-3">
                                <div class="card-modern">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-shape-outline text-primary"></i> Category
                                        </h5>
                                    </div>
                                    <div class="card-body p-3">
                                        @foreach($category as $catId => $catName)
                                        <label class="cat-option d-block mb-2 {{ old('category_id', $product->category_id) == $catId ? 'active' : '' }}" data-cat="{{ $catId }}">
                                            <input type="radio" name="category_id" value="{{ $catId }}" {{ old('category_id', $product->category_id) == $catId ? 'checked' : '' }}>
                                            <div><span class="font-weight-bold">{{ $catName }}</span></div>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="card-modern mt-3">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-information-outline text-primary"></i> Tips
                                        </h5>
                                    </div>
                                    <div class="card-body p-3">
                                        <small class="text-muted d-block mb-2">
                                            <i class="mdi mdi-check-circle text-success"></i> Changing the category may affect where this service appears.
                                        </small>
                                        <small class="text-muted d-block mb-2">
                                            <i class="mdi mdi-check-circle text-success"></i> The <strong>service code</strong> is used for quick lookup and billing.
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="mdi mdi-check-circle text-success"></i> Procedure details are only needed for surgical/procedure services.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            {{-- Right Content --}}
                            <div class="col-lg-9">
                                {{-- Basic Information --}}
                                <div class="card-modern">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-clipboard-text-outline text-primary"></i> Basic Information
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-lg-8">
                                                <label class="form-label-modern">Service Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-modern" name="service_name" value="{{ old('service_name', $product->service_name) }}" placeholder="e.g. Full Blood Count, Chest X-Ray" required>
                                            </div>
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Service Code</label>
                                                <input type="text" class="form-control form-control-modern" name="service_code" value="{{ old('service_code', $product->service_code) }}" placeholder="e.g. FBC-001">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Procedure Details (conditional) --}}
                                <div class="card-modern" id="procedure-fields" style="display: none;">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-hospital-box text-primary"></i> Procedure Details
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-lg-6">
                                                <label class="form-label-modern">Procedure Category <span class="text-danger">*</span></label>
                                                <select name="procedure_category_id" id="procedure_category_id" class="form-control form-control-modern">
                                                    <option value="">-- Select Procedure Category --</option>
                                                    @foreach($procedureCategories as $procCat)
                                                        <option value="{{ $procCat->id }}" {{ old('procedure_category_id', $procedure->procedure_category_id ?? '') == $procCat->id ? 'selected' : '' }}>
                                                            {{ $procCat->name }} ({{ $procCat->code }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">e.g. General Surgery, ENT, O&G</small>
                                            </div>
                                            <div class="col-lg-6">
                                                <label class="form-label-modern">Procedure Code</label>
                                                <input type="text" class="form-control form-control-modern" name="procedure_code" value="{{ old('procedure_code', $procedure->code ?? '') }}" placeholder="e.g., LAP-CHOLE-001">
                                                <small class="text-muted">Optional — defaults to service code</small>
                                            </div>
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Surgical Procedure</label>
                                                <div class="custom-control custom-switch mt-2">
                                                    <input type="checkbox" class="custom-control-input" id="is_surgical" name="is_surgical" value="1" {{ old('is_surgical', $procedure->is_surgical ?? false) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="is_surgical">Requires OR & surgical team</label>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Estimated Duration</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control form-control-modern" name="estimated_duration_minutes" value="{{ old('estimated_duration_minutes', $procedure->estimated_duration_minutes ?? '') }}" placeholder="60" min="1">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">mins</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label-modern">Description</label>
                                                <textarea class="form-control form-control-modern" name="procedure_description" rows="3" placeholder="Procedure description, indications, or notes...">{{ old('procedure_description', $procedure->description ?? '') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Submit --}}
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="{{ route('services.index') }}" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-arrow-left"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="mdi mdi-content-save"></i> Update Service
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        const procedureCategoryId = {{ $procedureCategoryId ?? 'null' }};
        const $procedureFields = $('#procedure-fields');
        const $procedureCategorySelect = $('#procedure_category_id');

        // Category card selector
        document.querySelectorAll('.cat-option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                document.querySelectorAll('.cat-option').forEach(function(o) { o.classList.remove('active'); });
                this.classList.add('active');
                this.querySelector('input[type="radio"]').checked = true;
                toggleProcedureFields();
            });
        });

        function toggleProcedureFields() {
            const selectedCategoryId = parseInt($('input[name="category_id"]:checked').val());
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
    });
</script>
@endsection
