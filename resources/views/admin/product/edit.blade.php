@extends('admin.layouts.app')
@section('title', 'Edit Product')
@section('page_name', 'Products')
@section('subpage_name', 'Edit Product')
@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .type-option { display: inline-flex; align-items: center; padding: 10px 18px; border: 2px solid #dee2e6; border-radius: 8px; cursor: pointer; margin-right: 8px; transition: all .2s; }
        .type-option:hover { border-color: var(--primary-color); }
        .type-option.active { border-color: var(--primary-color); background: var(--primary-light); }
        .type-option input[type="radio"] { display: none; }
        .type-option i { font-size: 1.3rem; margin-right: 6px; }
        .type-option small { display: block; font-size: 0.75rem; color: #6c757d; }
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
                        <h2 class="mb-1 font-weight-bold text-dark">Edit Product</h2>
                        <p class="text-muted mb-0">{{ $product->product_name }} &mdash; {{ $product->product_code }}</p>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('products.update', $product->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            {{-- Left Sidebar --}}
                            <div class="col-lg-3">
                                <div class="card-modern">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-tag-outline text-primary"></i> Product Type
                                        </h5>
                                    </div>
                                    <div class="card-body p-3">
                                        @php $pType = old('product_type', $product->product_type ?? 'drug'); @endphp
                                        <div class="product-type-selector">
                                            <label class="type-option d-block mb-2 {{ $pType == 'drug' ? 'active' : '' }}" data-type="drug">
                                                <input type="radio" name="product_type" value="drug" {{ $pType == 'drug' ? 'checked' : '' }}>
                                                <i class="mdi mdi-pill" style="color:#28a745"></i>
                                                <div><span class="font-weight-bold">Drug</span><small>Medications & pharmaceuticals</small></div>
                                            </label>
                                            <label class="type-option d-block mb-2 {{ $pType == 'consumable' ? 'active' : '' }}" data-type="consumable">
                                                <input type="radio" name="product_type" value="consumable" {{ $pType == 'consumable' ? 'checked' : '' }}>
                                                <i class="mdi mdi-bandage" style="color:#ffc107"></i>
                                                <div><span class="font-weight-bold">Consumable</span><small>Gloves, syringes, cotton</small></div>
                                            </label>
                                            <label class="type-option d-block mb-2 {{ $pType == 'utility' ? 'active' : '' }}" data-type="utility">
                                                <input type="radio" name="product_type" value="utility" {{ $pType == 'utility' ? 'checked' : '' }}>
                                                <i class="mdi mdi-broom" style="color:#17a2b8"></i>
                                                <div><span class="font-weight-bold">Utility</span><small>Cleaning & office supplies</small></div>
                                            </label>
                                        </div>
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
                                            <i class="mdi mdi-check-circle text-success"></i> Changing the type affects which workbenches display this product.
                                        </small>
                                        <small class="text-muted d-block mb-2">
                                            <i class="mdi mdi-check-circle text-success"></i> The <strong>base unit</strong> is the smallest countable/measurable unit.
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="mdi mdi-check-circle text-success"></i> You can edit, add, or remove packaging levels at any time.
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
                                            <div class="col-lg-6">
                                                <label class="form-label-modern">Product Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-modern" name="product_name" value="{{ old('product_name', $product->product_name) }}" placeholder="e.g. Paracetamol 500mg" required>
                                            </div>
                                            <div class="col-lg-3">
                                                <label class="form-label-modern">Product Code <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-modern" name="product_code" value="{{ old('product_code', $product->product_code) }}" placeholder="e.g. PCM-500" required>
                                            </div>
                                            <div class="col-lg-3">
                                                <label class="form-label-modern">Category <span class="text-danger">*</span></label>
                                                {!! Form::select('category', $category, old('category_id', $product->category_id), [
                                                    'id' => 'category_id', 'name' => 'category_id',
                                                    'placeholder' => 'Select Category',
                                                    'class' => 'form-control form-control-modern',
                                                    'required' => 'true'
                                                ]) !!}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Base Unit & Packaging --}}
                                <div class="card-modern">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-package-variant-closed text-primary"></i> Base Unit & Packaging
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label-modern">Base Unit Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control form-control-modern" name="base_unit_name" value="{{ old('base_unit_name', $product->base_unit_name ?? 'Piece') }}" placeholder="e.g. Tablet, ml, Piece" required>
                                                <small class="text-muted">The smallest countable/measurable unit</small>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label-modern">Allow Decimal Quantities?</label>
                                                <div class="custom-control custom-switch mt-2">
                                                    <input type="checkbox" class="custom-control-input" id="allow_decimal_qty" name="allow_decimal_qty" {{ old('allow_decimal_qty', $product->allow_decimal_qty) ? 'checked' : '' }}>
                                                    <label class="custom-control-label" for="allow_decimal_qty">Enable for liquids, creams</label>
                                                </div>
                                                <small class="text-muted">When ON: 15.5 ml, 0.5 Tablets allowed</small>
                                            </div>
                                        </div>

                                        <hr>
                                        <h6 class="font-weight-bold mb-3">
                                            <i class="mdi mdi-layers-outline"></i> Packaging Levels
                                            <small class="text-muted d-block">Define how base units group into larger packages</small>
                                        </h6>

                                        {{-- Base unit display --}}
                                        <div class="d-flex align-items-center p-2 mb-2" style="background:#e8f5e9; border-radius:6px">
                                            <span class="badge badge-success mr-2">Base</span>
                                            <span class="base-unit-name-display font-weight-bold">{{ old('base_unit_name', $product->base_unit_name ?? 'Piece') }}</span>
                                            <span class="text-muted ml-2">= 1 base unit</span>
                                        </div>

                                        <div id="packaging-levels-container">
                                            {{-- JS will populate from existing data --}}
                                        </div>

                                        <button type="button" id="add-packaging-level" class="btn btn-outline-primary btn-sm mt-2">
                                            <i class="mdi mdi-plus"></i> Add Packaging Level
                                        </button>
                                    </div>
                                </div>

                                {{-- Inventory Settings --}}
                                <div class="card-modern">
                                    <div class="card-header-modern">
                                        <h5 class="card-title-modern">
                                            <i class="mdi mdi-cog-outline text-primary"></i> Inventory Settings
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Re-Order Alert Level <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control form-control-modern" name="reorder_alert" value="{{ old('reorder_alert', $product->reorder_alert) }}" placeholder="Minimum stock level" required>
                                            </div>

                                            @if ($application->allow_halve_sale == 1)
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Allow Half Sale</label>
                                                <select name="s1" class="form-control form-control-modern">
                                                    <option value="">--Pick--</option>
                                                    <option value="0" {{ old('s1', $product->has_have) == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ old('s1', $product->has_have) == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                            @endif

                                            @if ($application->allow_piece_sale == 1)
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Allow Piece Sale</label>
                                                <select name="s2" class="form-control form-control-modern">
                                                    <option value="">--Pick--</option>
                                                    <option value="0" {{ old('s2', $product->has_piece) == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ old('s2', $product->has_piece) == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                            @endif

                                            @if ($application->allow_halve_sale == 1 || $application->allow_piece_sale == 1)
                                            <div class="col-lg-4">
                                                <label class="form-label-modern">Quantity In Unit</label>
                                                <input type="number" class="form-control form-control-modern" name="quantity_in" value="{{ old('quantity_in', $product->howmany_to) }}" placeholder="Pieces per unit">
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Submit --}}
                                <div class="d-flex justify-content-between mt-3">
                                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">
                                        <i class="mdi mdi-arrow-left"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="mdi mdi-content-save"></i> Update Product
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
<script src="{{ asset('js/product-packaging.js') }}"></script>
<script>
    // Type selector toggle
    document.querySelectorAll('.type-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.type-option').forEach(function(o) { o.classList.remove('active'); });
            this.classList.add('active');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });

    // Load existing packagings into repeater
    $(function() {
        @php
            $existingPkgs = $product->packagings->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'units_in_parent' => $p->units_in_parent,
                    'base_unit_qty' => $p->base_unit_qty,
                    'description' => $p->description,
                    'barcode' => $p->barcode,
                    'is_default_purchase' => $p->is_default_purchase,
                    'is_default_dispense' => $p->is_default_dispense,
                ];
            })->values();
        @endphp
        var existingPackagings = @json($existingPkgs);

        if (existingPackagings.length) {
            existingPackagings.forEach(function(pkg, idx) {
                ProductPackaging.addLevel();
                var $row = $('#packaging-levels-container .packaging-level-row').eq(idx);
                if (pkg.id) $row.find('input[name*="[id]"]').val(pkg.id);
                $row.find('input[name*="[name]"]').val(pkg.name);
                $row.find('input[name*="[units_in_parent]"]').val(pkg.units_in_parent);
                $row.find('input[name*="[description]"]').val(pkg.description || '');
                $row.find('input[name*="[barcode]"]').val(pkg.barcode || '');
                if (pkg.is_default_purchase) $row.find('input[name*="[is_default_purchase]"]').prop('checked', true);
                if (pkg.is_default_dispense) $row.find('input[name*="[is_default_dispense]"]').prop('checked', true);
            });
            ProductPackaging.recalculate();
        }
    });
</script>
@endsection
