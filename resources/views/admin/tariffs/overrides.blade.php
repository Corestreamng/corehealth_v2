@extends('admin.layouts.app')
@section('title', 'HMO Tariff Overrides')
@section('page_name', 'HMO Tariffs')
@section('subpage_name', 'Overrides Configuration')

@section('style')
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">

    <style>
        .tariff-page .stat-card {
            border-radius: 12px; padding: 20px;
            border: 1px solid rgba(0,0,0,0.05); background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Modern Axis Cards */
        .axis-selector-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
        .axis-card {
            background: #fff; border: 2px solid #edf2f7; border-radius: 16px; padding: 20px;
            cursor: pointer; transition: all 0.2s; position: relative; text-align: center;
        }
        .axis-card:hover { border-color: #cbd5e0; background: #f7fafc; }
        .axis-card.active { border-color: #4299e1; background: #ebf8ff; box-shadow: 0 0 0 4px rgba(66, 153, 225, 0.15); }
        .axis-card .axis-icon {
            width: 48px; height: 48px; background: #f1f5f9; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;
            font-size: 1.5rem; color: #64748b; transition: all 0.2s;
        }
        .axis-card.active .axis-icon { background: #4299e1; color: #fff; }
        .axis-card .axis-title { font-weight: 700; color: #2d3748; margin-bottom: 4px; }
        .axis-card .axis-desc { font-size: 0.75rem; color: #718096; }
        .axis-card .active-check {
            position: absolute; top: 12px; right: 12px; color: #4299e1;
            display: none; font-size: 1.2rem;
        }
        .axis-card.active .active-check { display: block; }

        .selector-area { background: #fff; border-radius: 20px; padding: 30px; margin-bottom: 30px; border: 1px solid #e2e8f0; }

        .rounded-xl { border-radius: 1rem !important; }
        .smallest { font-size: 0.7rem; }

        /* Fix Select2 z-index and scrolling in modals */
        .select2-container--open { z-index: 10000 !important; }
        .select2-results__options { max-height: 280px !important; overflow-y: auto !important; }
    </style>
@endsection

@section('content')
<div class="container-fluid tariff-page animate-fade-in">
    {{-- Global Tools Bar --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="h3 mb-1 text-gray-900 font-weight-bold">Tariff Management Central</h1>
            <p class="text-muted small mb-0">Multi-axis configuration engine for HMOs, Clinical Services, and Payment Schemes.</p>
        </div>
        <div class="d-flex align-items-center">
            <a href="{{ route('hmo-tariffs.index') }}" class="btn btn-outline-primary shadow-sm px-4 font-weight-bold mr-2">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Main Config
            </a>
            <button class="btn btn-warning shadow-sm px-4 font-weight-bold" onclick="$('#addOverrideModal').modal('show')">
                <i class="mdi mdi-flash mr-1"></i> Add Override
            </button>
        </div>
    </div>

    {{-- Axis Selector Area --}}
    <div class="selector-area shadow-sm">
        <label class="small font-weight-bold text-uppercase text-muted mb-4 d-block">1. Select Management Axis</label>
        <div class="axis-selector-grid">
            <div class="axis-card" data-type="product" onclick="window.location.href='/admin/hmo-tariffs'">
                <i class="mdi mdi-check-circle active-check"></i>
                <div class="axis-icon"><i class="mdi mdi-pills"></i></div>
                <div class="axis-title">Product Axis</div>
                <div class="axis-desc">Edit one drug across all HMOs</div>
            </div>
            <div class="axis-card" data-type="service" onclick="window.location.href='/admin/hmo-tariffs'">
                <i class="mdi mdi-check-circle active-check"></i>
                <div class="axis-icon"><i class="mdi mdi-stethoscope"></i></div>
                <div class="axis-title">Service Axis</div>
                <div class="axis-desc">Edit one service across all HMOs</div>
            </div>
            <div class="axis-card" data-type="hmo" onclick="window.location.href='/admin/hmo-tariffs'">
                <i class="mdi mdi-check-circle active-check"></i>
                <div class="axis-icon"><i class="mdi mdi-office-building"></i></div>
                <div class="axis-title">HMO Axis</div>
                <div class="axis-desc">Edit entire catalog for one HMO</div>
            </div>
            <div class="axis-card" data-type="scheme" onclick="window.location.href='/admin/hmo-tariffs'">
                <i class="mdi mdi-check-circle active-check"></i>
                <div class="axis-icon"><i class="mdi mdi-domain"></i></div>
                <div class="axis-title">Scheme Axis</div>
                <div class="axis-desc">Edit entire catalog for a Scheme</div>
            </div>
            <div class="axis-card active" data-type="overrides">
                <i class="mdi mdi-check-circle active-check"></i>
                <div class="axis-icon"><i class="mdi mdi-flash text-warning"></i></div>
                <div class="axis-title">Overrides</div>
                <div class="axis-desc">Manage Tariff Overrides</div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4 border-0 rounded-lg">
        <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between border-bottom">
            <h6 class="m-0 font-weight-bold text-dark"><i class="mdi mdi-flash text-warning mr-2"></i>Active Tariff Overrides</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered table-striped" id="overridesTable" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Context (HMO/Scheme)</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Target Type</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Target Name</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Override Type</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Payable Amount</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Status</th>
                            <th class="font-weight-bold text-uppercase text-secondary text-xs">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data populated by DataTables -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Override Modal --}}
<div class="modal fade" id="addOverrideModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content rounded-xl border-0 shadow-lg">
            <div class="modal-header bg-white border-bottom rounded-top py-4">
                <div class="d-flex align-items-center">
                    <div class="bg-warning-soft p-3 rounded-circle mr-3" style="background-color: #fff3cd;">
                        <i class="mdi mdi-flash text-warning" style="font-size: 24px;"></i>
                    </div>
                    <div>
                        <h4 class="modal-title font-weight-bold text-dark mb-0">Create New Override</h4>
                        <p class="text-muted small mb-0 mt-1">Configure custom pricing rules that intercept and replace standard HMO Tariffs.</p>
                    </div>
                </div>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="font-size: 28px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addOverrideForm">
                @csrf
                <div class="modal-body bg-light p-4">
                    <div class="row">
                        <div class="col-lg-8">
                            {{-- Step 1: Context Card --}}
                            <div class="card border-0 shadow-sm rounded-lg mb-4">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h6 class="font-weight-bold text-primary text-uppercase mb-0"><i class="mdi mdi-account-group mr-2"></i>1. Applicability Context</h6>
                                    <p class="text-muted small mt-1 mb-0">Who does this override apply to?</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small">Context Type <span class="text-danger">*</span></label>
                                            <select name="context_type" id="context_type" class="form-control form-control-solid select2" required>
                                                <option value="">Select Context Type</option>
                                                <option value="hmo">Specific HMO</option>
                                                <option value="scheme">HMO Scheme (Category)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small" id="context_id_label">Select Context <span class="text-danger">*</span></label>
                                            <select name="context_id" id="context_id" class="form-control form-control-solid select2" required disabled>
                                                <option value="">Select above first...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 2: Target Scope Card --}}
                            <div class="card border-0 shadow-sm rounded-lg mb-4">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h6 class="font-weight-bold text-primary text-uppercase mb-0"><i class="mdi mdi-target mr-2"></i>2. Target Scope</h6>
                                    <p class="text-muted small mt-1 mb-0">What items or categories are affected?</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small">Target Type <span class="text-danger">*</span></label>
                                            <select name="target_type" id="target_type" class="form-control form-control-solid select2" required>
                                                <option value="">Select Target Type</option>
                                                <option value="product">Specific Product (Drug/Consumable)</option>
                                                <option value="service">Specific Service</option>
                                                <option value="product_category">Product Category</option>
                                                <option value="service_category">Service Category</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small" id="target_id_label">Select Target <span class="text-danger">*</span></label>
                                            <select name="target_id" id="target_id" class="form-control form-control-solid select2" required disabled>
                                                <option value="">Select above first...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Step 3: Pricing Rule Card --}}
                            <div class="card border-0 shadow-sm rounded-lg">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h6 class="font-weight-bold text-primary text-uppercase mb-0"><i class="mdi mdi-cash-register mr-2"></i>3. Pricing Rule</h6>
                                    <p class="text-muted small mt-1 mb-0">How much should the patient pay?</p>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small">Patient Pays By <span class="text-danger">*</span></label>
                                            <select name="override_type" id="override_type" class="form-control form-control-solid select2" required>
                                                <option value="percentage">Percentage (%) of Retail Price</option>
                                                <option value="fixed">Fixed Amount (₦)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 form-group mb-0">
                                            <label class="font-weight-bold text-dark small" id="amount_label">Percentage Amount (%) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control form-control-solid form-control-lg" required placeholder="e.g. 20">
                                                <div class="input-group-append" id="amount_addon">
                                                    <span class="input-group-text bg-white border-0 font-weight-bold">%</span>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block mt-2" id="amount_hint">If 20%, patient pays 20% and HMO pays 80%.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Live Preview Sidebar --}}
                        <div class="col-lg-4 mt-4 mt-lg-0">
                            <div class="card border-0 shadow-sm rounded-lg h-100 bg-white" style="border: 2px solid #edf2f7 !important;">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="font-weight-bold text-dark text-uppercase mb-3"><i class="mdi mdi-eye text-primary mr-2"></i>Live Preview</h6>
                                    
                                    <div id="preview_loading" class="text-center py-5" style="display: none;">
                                        <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="text-muted mt-2 small">Fetching retail price...</p>
                                    </div>

                                    <div id="preview_empty" class="text-center py-5">
                                        <i class="mdi mdi-calculator text-light" style="font-size: 4rem;"></i>
                                        <p class="text-muted mt-2 small">Select a specific product or service to see a live pricing breakdown.</p>
                                    </div>

                                    <div id="preview_data" style="display: none; flex: 1;">
                                        <div class="bg-light rounded p-3 mb-4 text-center">
                                            <span class="text-uppercase small font-weight-bold text-muted d-block mb-1">Base Retail Price</span>
                                            <h3 class="font-weight-bold text-dark mb-0" id="preview_base_price">₦0.00</h3>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                            <span class="text-muted font-weight-bold">Patient Pays</span>
                                            <h5 class="font-weight-bold text-danger mb-0" id="preview_patient_pays">₦0.00</h5>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                            <span class="text-muted font-weight-bold">HMO Pays (Claims)</span>
                                            <h5 class="font-weight-bold text-success mb-0" id="preview_hmo_pays">₦0.00</h5>
                                        </div>

                                        <div class="bg-primary-soft p-3 rounded mb-3" style="background-color: #e3f2fd; border: 1px solid #90caf9;">
                                            <p class="mb-0 small text-dark" id="preview_story" style="line-height: 1.5;">
                                                <!-- Story will be injected here -->
                                            </p>
                                        </div>

                                        <div class="mt-auto pt-3">
                                            <div class="alert alert-warning border-0 small mb-0">
                                                <i class="mdi mdi-alert-circle mr-1"></i> Preview assumes base retail price. If product is dispensed from a stock batch with a different cost, actual numbers may vary proportionally.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-4 border-top">
                    <button type="button" class="btn btn-light shadow-sm px-4 font-weight-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning shadow-sm font-weight-bold px-5" id="btnSaveOverride">
                        <i class="mdi mdi-check-circle mr-1"></i> Activate Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    var dataMaps = {
        hmos: @json($hmos->map(function($h) { return ['id' => $h->id, 'text' => $h->name]; })->values()),
        schemes: @json($schemes->map(function($s) { return ['id' => $s->id, 'text' => $s->name]; })->values()),
        products: @json($products->map(function($p) { return ['id' => $p->id, 'text' => $p->product_name]; })->values()),
        services: @json($services->map(function($s) { return ['id' => $s->id, 'text' => $s->service_name]; })->values()),
        product_categories: @json($productCategories->map(function($c) { return ['id' => $c->id, 'text' => $c->category_name]; })->values()),
        service_categories: @json($serviceCategories->map(function($c) { return ['id' => $c->id, 'text' => $c->category_name]; })->values())
    };
</script>
@endsection

@section('scripts')
<!-- Scripts -->
<script>
    $(document).ready(function() {
        $('.select2').select2({ 
            width: '100%',
            dropdownParent: $('#addOverrideModal')
        });

        var table = $('#overridesTable').DataTable({
            processing: true,
            ajax: '{{ route("hmo-tariffs.overrides.data") }}',
            columns: [
                { data: 'context' },
                { data: 'target_type' },
                { data: 'target_name' },
                { data: 'override_type' },
                { data: 'amount' },
                { data: 'is_active', render: function(data) {
                    return data === 'Active' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
                }},
                { data: 'id', render: function(data) {
                    return `<button class="btn btn-sm btn-danger shadow-sm delete-btn" data-id="${data}"><i class="mdi mdi-trash-can"></i> Delete</button>`;
                }}
            ]
        });

        $('#context_type').change(function() {
            var type = $(this).val();
            var select = $('#context_id');
            select.empty().append('<option value="">Select Context</option>');
            
            if (type === 'hmo') {
                $('#context_id_label').html('Select HMO <span class="text-danger">*</span>');
                dataMaps.hmos.forEach(function(item) {
                    select.append(new Option(item.text, item.id));
                });
                select.prop('disabled', false);
            } else if (type === 'scheme') {
                $('#context_id_label').html('Select Scheme <span class="text-danger">*</span>');
                dataMaps.schemes.forEach(function(item) {
                    select.append(new Option(item.text, item.id));
                });
                select.prop('disabled', false);
            } else {
                select.prop('disabled', true);
            }
            select.trigger('change');
        });

        $('#target_type').change(function() {
            var type = $(this).val();
            var select = $('#target_id');
            select.empty().append('<option value="">Select Target</option>');
            
            var mapKey = null;
            if (type === 'product') mapKey = 'products';
            else if (type === 'service') mapKey = 'services';
            else if (type === 'product_category') mapKey = 'product_categories';
            else if (type === 'service_category') mapKey = 'service_categories';

            if (mapKey) {
                dataMaps[mapKey].forEach(function(item) {
                    select.append(new Option(item.text, item.id));
                });
                select.prop('disabled', false);
            } else {
                select.prop('disabled', true);
            }
            select.trigger('change');
        });

        var currentBasePrice = 0;

        function updateLivePreview() {
            if ($('#preview_data').is(':hidden')) return;
            
            var targetType = $('#target_type').val();
            var targetText = $('#target_id option:selected').text();
            var contextText = $('#context_id option:selected').text();
            var contextType = $('#context_type option:selected').text();
            var cText = contextType.replace('Specific ', '');

            var type = $('#override_type').val();
            var amt = parseFloat($('#amount').val()) || 0;
            
            var isCategory = (targetType === 'product_category' || targetType === 'service_category');

            if (isCategory) {
                $('#preview_base_price').text('Varies by Item');
                
                if (type === 'percentage') {
                    $('#preview_patient_pays').html('<span class="text-dark small">Varies</span> (' + amt + '%)');
                    $('#preview_hmo_pays').html('<span class="text-dark small">Varies</span> (' + (100 - amt) + '%)');
                    
                    var story = `When a patient under the <strong>${contextText}</strong> ${cText} accesses any item in <strong>${targetText}</strong>, the patient will be charged <strong>${amt}%</strong> of its base retail price, while the remaining <strong>${100 - amt}%</strong> will be sent to the HMO as a claim.`;
                    $('#preview_story').html(story);
                } else {
                    $('#preview_patient_pays').text('₦' + amt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#preview_hmo_pays').html('<span class="text-dark small">Remaining Balance</span>');
                    
                    var story = `When a patient under the <strong>${contextText}</strong> ${cText} accesses any item in <strong>${targetText}</strong>, the patient will be charged exactly <strong>₦${amt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>, while any remaining balance of the item's cost will be sent to the HMO as a claim.`;
                    $('#preview_story').html(story);
                }
            } else {
                var patientPays = 0;
                if (type === 'percentage') {
                    patientPays = currentBasePrice * (amt / 100);
                } else {
                    patientPays = amt;
                }
                var hmoPays = Math.max(0, currentBasePrice - patientPays);

                $('#preview_base_price').text('₦' + currentBasePrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#preview_patient_pays').text('₦' + patientPays.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                $('#preview_hmo_pays').text('₦' + hmoPays.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                var strAmount = type === 'percentage' ? amt + '% (₦' + patientPays.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ')' : '₦' + patientPays.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                var strHmoPays = '₦' + hmoPays.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                var story = `When a patient under the <strong>${contextText}</strong> ${cText} comes to access <strong>${targetText}</strong>, the patient will be charged <strong>${strAmount}</strong>, while the remaining <strong>${strHmoPays}</strong> will be sent to the HMO as a claim.`;
                $('#preview_story').html(story);
            }
        }

        $('#amount').on('input', updateLivePreview);
        $('#context_id').change(updateLivePreview);

        $('#target_type, #target_id, #context_id').change(function() {
            var targetType = $('#target_type').val();
            var targetId = $('#target_id').val();
            var contextId = $('#context_id').val();

            if (!targetId || !contextId) {
                $('#preview_loading').hide();
                $('#preview_data').hide();
                $('#preview_empty').show();
                currentBasePrice = 0;
                return;
            }

            var isCategory = (targetType === 'product_category' || targetType === 'service_category');
            
            if (isCategory) {
                $('#preview_empty').hide();
                $('#preview_loading').hide();
                $('#preview_data').show();
                currentBasePrice = 0;
                updateLivePreview();
                return;
            }

            $('#preview_empty').hide();
            $('#preview_data').hide();
            $('#preview_loading').show();

            $.ajax({
                url: '{{ route("hmo-tariffs.overrides.item-price") }}',
                data: { target_type: targetType, target_id: targetId },
                success: function(res) {
                    $('#preview_loading').hide();
                    if (res.success) {
                        currentBasePrice = res.data.base_price;
                        $('#preview_base_price').text('₦' + currentBasePrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                        $('#preview_data').show();
                        updateLivePreview();
                    } else {
                        $('#preview_empty').show();
                    }
                },
                error: function() {
                    $('#preview_loading').hide();
                    $('#preview_empty').show();
                }
            });
        });

        $('#override_type').change(function() {
            var type = $(this).val();
            if (type === 'percentage') {
                $('#amount_label').html('Percentage Amount (%) <span class="text-danger">*</span>');
                $('#amount').attr('placeholder', 'e.g. 20');
                $('#amount_addon').html('<span class="input-group-text bg-white border-0 font-weight-bold">%</span>');
                $('#amount_hint').text('If 20%, patient pays 20% and HMO pays 80%.');
            } else {
                $('#amount_label').html('Fixed Amount (₦) <span class="text-danger">*</span>');
                $('#amount').attr('placeholder', 'e.g. 1000');
                $('#amount_addon').html('<span class="input-group-text bg-white border-0 font-weight-bold">₦</span>');
                $('#amount_hint').text('Patient will pay exactly this amount regardless of base price.');
            }
            updateLivePreview();
        });

        $('#addOverrideForm').submit(function(e) {
            e.preventDefault();
            var btn = $('#btnSaveOverride');
            btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Activating...');
            
            $.ajax({
                url: '{{ route("hmo-tariffs.overrides.store") }}',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#addOverrideModal').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Success', response.message, 'success');
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred';
                    Swal.fire('Error', msg, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="mdi mdi-check-circle mr-1"></i> Save Override');
                }
            });
        });

        $('#overridesTable').on('click', '.delete-btn', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This will remove the override and return calculations to normal tariffs.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/admin/hmo-tariffs/overrides/' + id,
                        type: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            table.ajax.reload();
                            Swal.fire('Deleted!', response.message, 'success');
                        },
                        error: function(xhr) {
                            var msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred';
                            Swal.fire('Error', msg, 'error');
                        }
                    });
                }
            });
        });
    });
</script>
@endsection
