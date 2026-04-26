@extends('admin.layouts.app')
@section('title', 'HMO Tariff Management')
@section('page_name', 'HMO Tariffs')
@section('subpage_name', 'Dynamic Configuration')

@section('style')
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
    <style>
        .tariff-page .stat-card {
            border-radius: 12px; padding: 20px;
            border: 1px solid rgba(0,0,0,0.05); background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tariff-page .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); }
        .tariff-page .stat-card .stat-value { font-size: 1.5rem; font-weight: 800; color: #1a202c; }
        .tariff-page .stat-card .stat-label { font-size: 0.85rem; color: #718096; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Modern Axis Cards */
        .axis-selector-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
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
        
        /* Skeleton Loading */
        .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; border-radius: 8px; }
        @keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        
        .canvas-loading { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); display: none; flex-direction: column; justify-content: center; align-items: center; z-index: 10; border-radius: 20px; }
        .canvas-loading .spinner-border { width: 3rem; height: 3rem; color: #4299e1; }
    </style>
@endsection

@section('content')
<div class="container-fluid tariff-page">
    {{-- Global Tools Bar --}}
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="h3 mb-1 text-gray-900 font-weight-bold">Tariff Management</h1>
            <p class="text-muted small mb-0">Configure pricing agreements across HMOs, Products, and Schemes.</p>
        </div>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-white border dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fa fa-wrench mr-1"></i> Global Tools
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow border-0">
                    <a class="dropdown-item" href="#" onclick="$('#exportModal').modal('show')"><i class="fa fa-download mr-2 text-info"></i> Export Excel</a>
                    <a class="dropdown-item" href="#" onclick="$('#importModal').modal('show')"><i class="fa fa-upload mr-2 text-warning"></i> Import Excel</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="#" onclick="$('#normalizeModal').modal('show')"><i class="fa fa-magic mr-2 text-purple" style="color: #7B1FA2;"></i> Quick Normalize</a>
                </div>
            </div>
            <button class="btn btn-primary shadow-sm px-4" onclick="$('#tariffModal').modal('show')">
                <i class="fa fa-plus mr-2"></i> Add Single Tariff
            </button>
        </div>
    </div>

    {{-- Axis Selector Area --}}
    <div class="selector-area shadow-sm">
        <label class="small font-weight-bold text-uppercase text-muted mb-4 d-block">1. Select Configuration Axis</label>
        <div class="axis-selector-grid">
            <div class="axis-card active" data-type="product">
                <i class="fa fa-check-circle active-check"></i>
                <div class="axis-icon"><i class="fa fa-pills"></i></div>
                <div class="axis-title">Product</div>
                <div class="axis-desc">Edit one drug across all HMOs</div>
            </div>
            <div class="axis-card" data-type="service">
                <i class="fa fa-check-circle active-check"></i>
                <div class="axis-icon"><i class="fa fa-stethoscope"></i></div>
                <div class="axis-title">Service</div>
                <div class="axis-desc">Edit one service across all HMOs</div>
            </div>
            <div class="axis-card" data-type="hmo">
                <i class="fa fa-check-circle active-check"></i>
                <div class="axis-icon"><i class="fa fa-building"></i></div>
                <div class="axis-title">HMO</div>
                <div class="axis-desc">Edit entire catalog for one HMO</div>
            </div>
            <div class="axis-card" data-type="scheme">
                <i class="fa fa-check-circle active-check"></i>
                <div class="axis-icon"><i class="fa fa-sitemap"></i></div>
                <div class="axis-title">Scheme</div>
                <div class="axis-desc">Edit entire catalog for a Scheme</div>
            </div>
        </div>

        <div class="row align-items-center">
            <div class="col-md-9">
                <label class="small font-weight-bold text-uppercase text-muted mb-2 d-block" id="select-label">2. Select Specific Product</label>
                <select id="axis-item-select" class="form-control select2">
                    <option value="">-- Choose --</option>
                    @foreach($products as $p) <option value="{{ $p->id }}" data-type="product">{{ $p->product_name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="small text-white mb-2 d-block">_</label>
                <button id="load-config-btn" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm" disabled>
                    <i class="fa fa-sync-alt mr-2"></i> Load Configuration
                </button>
            </div>
        </div>
    </div>

    {{-- Canvas for Dynamic Content --}}
    <div id="tariffCanvas" class="position-relative">
        <div class="canvas-loading">
            <div class="spinner-border mb-3"></div>
            <div class="font-weight-bold text-muted">Building configuration interface...</div>
        </div>
        <div id="canvasContent">
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="mdi mdi-gesture-tap mdi-48px text-muted"></i>
                </div>
                <h4 class="text-gray-800">Ready to configure</h4>
                <p class="text-muted">Choose an axis and specific item above to begin auditing and updating tariffs.</p>
            </div>
        </div>
    </div>
</div>

{{-- Modals from original view --}}
@include('admin.tariffs.modals')

@endsection

@section('scripts')
<script>
$(function() {
    // Select2 Init
    $('.select2').select2({ theme: 'bootstrap4', placeholder: '-- Choose --' });

    // Handle Axis Toggle (Card Click)
    $('.axis-card').on('click', function() {
        $('.axis-card').removeClass('active');
        $(this).addClass('active');
        const type = $(this).data('type');
        updateSelectorDropdown(type);
    });

    function updateSelectorDropdown(type) {
        const $select = $('#axis-item-select');
        const $label = $('#select-label');
        $select.empty().append('<option value="">-- Choose --</option>');
        
        let data = [];
        if (type === 'product') {
            $label.text('2. Select Specific Product');
            @foreach($products as $p) data.push({id: {{ $p->id }}, text: '{{ $p->product_name }}'}); @endforeach
        } else if (type === 'service') {
            $label.text('2. Select Specific Service');
            @foreach($services as $s) data.push({id: {{ $s->id }}, text: '{{ $s->service_name }}'}); @endforeach
        } else if (type === 'hmo') {
            $label.text('2. Select Specific HMO');
            @foreach($hmos as $h) data.push({id: {{ $h->id }}, text: '{{ $h->name }}'}); @endforeach
        } else if (type === 'scheme') {
            $label.text('2. Select Specific Scheme');
            @foreach($schemes as $s) data.push({id: {{ $s->id }}, text: '{{ $s->name }}'}); @endforeach
        }

        data.forEach(item => {
            $select.append(new Option(item.text, item.id, false, false));
        });
        $select.trigger('change');
        $('#load-config-btn').prop('disabled', true);
    }

    $('#axis-item-select').on('change', function() {
        $('#load-config-btn').prop('disabled', !$(this).val());
    });

    // Load Configuration View
    $('#load-config-btn').on('click', function() {
        const type = $('.axis-card.active').data('type');
        const id = $('#axis-item-select').val();
        if (!id) return;

        $('.canvas-loading').css('display', 'flex');
        $.ajax({
            url: '{{ route("hmo-tariffs.load-view") }}',
            data: { type: type, id: id },
            success: function(html) {
                $('#canvasContent').html(html);
                $('.canvas-loading').hide();
                // Scroll to canvas
                $('html, body').animate({ scrollTop: $('#tariffCanvas').offset().top - 100 }, 500);
            },
            error: function() {
                toastr.error('Failed to load configuration view.');
                $('.canvas-loading').hide();
            }
        });
    });

    // Handle existing modals logic if needed (they use their own scripts in partials usually)
});
</script>

{{-- I'll move the original modal logic to a partial or just keep it here --}}
@stack('modal_scripts')
@endsection
