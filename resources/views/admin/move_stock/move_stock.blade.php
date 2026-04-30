@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Show Store')
@section('content')
    <div id="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="card-modern">
                        <div class="card-header">{{ __('Product Stock') }}</div>

                        <div class="card-body">
                            {{--  <form method="POST" action="{{ route('move-stock.store') }}" aria-label="{{ __('Product') }}"> --}}
                            @csrf
                            {!! Form::open(['route' => 'move-stock.store', 'method' => 'POST', 'class' => 'form-horizontal', 'id' => 'move-stock-form']) !!}
                            <div class="table-responsive">
                                <table id="products" class="table table-sm table-bordered">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Source Store</th>
                                            <th> Available </th>
                                            <th> Destination Store</th>
                                            <th> Packaging</th>
                                            <th> Quantity to Move</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <strong>{{ $pc->product->product_name }}</strong>
                                                <input type="hidden" name="product_id" id="product_id" value="{{ $pc->product->id }}" />
                                            </td>
                                            <td>
                                                {{ $pc->store->store_name }}
                                                <input type="hidden" name="store_id" id="store_id" value="{{ $pc->store->id }}" />
                                            </td>
                                            <td>
                                                <span class="badge badge-info">{{ $pc->current_quantity }} {{ $pc->product->base_unit_name ?? 'pcs' }}</span>
                                                <input type="hidden" id="current_quantity_hidden" value="{{ $pc->current_quantity }}" />
                                            </td>
                                            <td>
                                                {!! Form::select('stores_', $stores, null, [
                                                    'id' => 'stores',
                                                    'placeholder' => 'Select destination...',
                                                    'class' => 'select2 form-control',
                                                    'required' => 'required'
                                                ]) !!}
                                            </td>
                                            <td>
                                                <select id="packaging_id" class="form-control form-control-sm">
                                                    <option value="" data-base="1">{{ $pc->product->base_unit_name ?? 'Base Unit' }}</option>
                                                </select>
                                                <small class="text-info" id="base-equiv-hint" style="display:none;">= <strong id="base-equiv-qty">0</strong> units</small>
                                            </td>
                                            <td>
                                                <input type="number" name="quantity" id="quantity" 
                                                    class="form-control" value="1" min="1" required />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="box-footer mt-4" align="center">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary"> Cancel</a>
                                <button type="submit" class="btn btn-primary"> <i class="fa fa-send"></i> Move Stock</button>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(function() {
    // Load packaging options
    const productId = {{ $pc->product->id }};
    const pkgSelect = $('#packaging_id');
    
    $.get('/products/' + productId + '/packagings', function(data) {
        const baseUnit = data.base_unit_name || 'pcs';
        pkgSelect.html('<option value="" data-base="1">' + baseUnit + ' (base)</option>');
        
        if (data.packagings && data.packagings.length > 0) {
            data.packagings.forEach(pkg => {
                pkgSelect.append('<option value="' + pkg.id + '" data-base="' + pkg.base_unit_qty + '">' + pkg.name + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + baseUnit + ')</option>');
            });
        }
    });

    function updateBaseEquiv() {
        const base = parseFloat(pkgSelect.find(':selected').data('base')) || 1;
        const qty = parseFloat($('#quantity').val()) || 0;
        const total = qty * base;
        
        if (base > 1 && qty > 0) {
            $('#base-equiv-qty').text(parseFloat(total.toFixed(4)));
            $('#base-equiv-hint').show();
        } else {
            $('#base-equiv-hint').hide();
        }

        // Update max quantity based on available stock
        const available = parseFloat($('#current_quantity_hidden').val()) || 0;
        const maxPkg = Math.floor(available / base);
        $('#quantity').attr('max', maxPkg);
    }

    pkgSelect.on('change', updateBaseEquiv);
    $('#quantity').on('input', updateBaseEquiv);

    $('#move-stock-form').on('submit', function() {
        const base = parseFloat(pkgSelect.find(':selected').data('base')) || 1;
        if (base > 1) {
            const qty = parseFloat($('#quantity').val()) || 0;
            $('#quantity').val(Math.round(qty * base));
        }
    });
});
</script>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>


@endsection
