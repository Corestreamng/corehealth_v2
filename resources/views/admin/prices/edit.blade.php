@extends('admin.layouts.app')
@section('title', 'Product Price Setting ')
@section('page_name', 'Products ')
@section('subpage_name', 'Edit Product Price Setting')
@section('style')
{{-- Tariff CSS is included via the shared partial --}}
@endsection
@section('content')
    <section class="container">
        {{-- ══════════════════════════════════════════════════════════ --}}
        {{-- SECTION 1: Price Edit (existing, cleaned up) --}}
        {{-- ══════════════════════════════════════════════════════════ --}}
        <div class="card-modern border-info mb-3">
            <div class="card-header bg-transparent border-info">
                <i class="mdi mdi-tag-outline mr-1"></i>
                {{ __(ucwords($data->product->product_name)) }}
                <span class="text-muted ml-2" style="font-size: 0.85rem;">— Price Adjustment</span>
            </div>
            <form id="priceEditForm" class="form-horizontal" method="POST" action="{{ route('prices.update', $data->id) }}">
                {{ csrf_field() }}
                <input name="_method" type="hidden" value="PUT">
                <input type="hidden" name="products" value="{{ $data->id }}">

                <div class="card-body">
                    {{-- Current prices (read-only) --}}
                    <h6 class="font-weight-bold text-muted mb-2">
                        <i class="mdi mdi-history mr-1"></i> Current Prices
                    </h6>
                    <table class="table table-sm table-bordered table-striped mb-3">
                        <thead class="thead-light">
                            <tr>
                                <th>Supplier Price (&#8358;)</th>
                                <th>Issue Price (&#8358;)</th>
                                <th>Max Discount (&#8358;)</th>
                                @if ($application->allow_piece_sale == 1)
                                    <th>Pieces Price (&#8358;)</th>
                                    <th>Pieces Mxd (&#8358;)</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>{{ formatMoney($data->pr_buy_price) }}</strong></td>
                                <td><strong>{{ formatMoney($data->current_sale_price) }}</strong></td>
                                <td>{{ formatMoney($data->max_discount) }}</td>
                                @if ($application->allow_piece_sale == 1)
                                    <td>{{ formatMoney($data->pieces_price) }}</td>
                                    <td>{{ formatMoney($data->pieces_max_discount) }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>

                    {{-- New price inputs --}}
                    <h6 class="font-weight-bold text-primary mb-2">
                        <i class="mdi mdi-pencil mr-1"></i> New Prices
                    </h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Supplier Price (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="new_buy_price" id="new_buy_price"
                                class="form-control" placeholder="New buy price"
                                value="{{ old('new_buy_price', $data->pr_buy_price) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Issue/Sale Price (&#8358;) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="price" id="price"
                                class="form-control" placeholder="New sale price" required
                                value="{{ old('price', $data->current_sale_price) }}">
                            <small class="text-muted">This is the price patients/HMOs are charged</small>
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Max Discount (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="max_discount" id="max_discount"
                                class="form-control" placeholder="Maximum discount"
                                value="{{ old('max_discount', $data->max_discount) }}">
                        </div>
                    </div>
                    @if ($application->allow_piece_sale == 1)
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Pieces Price (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="pieces_price" id="pieces_price"
                                class="form-control" placeholder="Pieces price"
                                value="{{ old('pieces_price', $data->pieces_price) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="small font-weight-bold mb-1">Pieces Max Discount (&#8358;)</label>
                            <input type="number" step="0.01" min="0" name="pieces_max_discount" id="pieces_max_discount"
                                class="form-control" placeholder="Pieces max discount"
                                value="{{ old('pieces_max_discount', $data->pieces_max_discount) }}">
                        </div>
                    </div>
                    @endif

                {{-- ══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 2: Tariff Propagation (shared component) --}}
                {{-- ══════════════════════════════════════════════════════════ --}}
                @include('admin.partials.hmo-tariff-propagation', [
                    'schemeSummary' => $schemeSummary,
                    'standaloneData' => $standaloneData,
                    'totalHmoCount' => $totalHmoCount,
                    'currentSalePrice' => $data->current_sale_price,
                    'itemType' => 'product',
                ])
                {{-- End tariff section --}}

                </div>
                <div class="card-footer bg-transparent border-info">
                    <div class="form-group row mb-0">
                        <div class="col-md-6">
                            <a href="{{ route('products.index') }}" class="btn btn-success">
                                <i class="fa fa-close"></i> Back
                            </a>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary float-right">
                                <i class="fa fa-send"></i> Save Price
                                <span id="submitTariffHint" class="ml-1" style="display: none; font-size: 0.8rem;">+ Tariffs</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('scripts')
{{-- Tariff JS is included via the shared partial --}}
@endsection
