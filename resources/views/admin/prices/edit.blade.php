@extends('admin.layouts.app')
@section('title', 'Product Price Setting ')
@section('page_name', 'Products ')
@section('subpage_name', 'Edit Product Price Setting')
@section('content')
    <section class="container">
        <div class="card-modern border-info mb-3">
            <div class="card-header bg-transparent border-info">{{ __(ucwords($data->product->product_name)) }}</div>
            <form class="form-horizontal" method="POST" action="{{ route('prices.update', $data->id) }}">
                {{ csrf_field() }}
                <input name="_method" type="hidden" value="PUT">
                <div class="card-body">
                    <table class="table table-sm table-responsive table-bordered table-striped ">
                        <thead>
                            <tr>
                                <th>Supplier Price (NGN)</th>
                                <th>Issue Price (NGN)</th>
                                <th>Maximum Discount (NGN)</th>
                                @if ($application->allow_piece_sale == 1)
                                    <th>Pieces Price (NGN)</th>
                                    <th>Pieces Mxd (NGN)</th>
                                @endif

                                <input type="hidden" name="id" id="id" class=" form-control"
                                    value="{{ old('name') ? old('name') : $data->id }}" />
                                <input type="hidden" name="products" id="products" placeholder=" products"
                                    class=" form-control" value="{{ $data->id }}" />

                            </tr>
                        </thead>
                        <tbody>

                            <tr>

                                <td>{{ formatMoney($data->pr_buy_price) }}
                                </td>
                                <td>{{ formatMoney($data->current_sale_price) }}
                                </td>
                                <td> {{ formatMoney($data->max_discount) }}
                                </td>
                                @if ($application->allow_piece_sale == 1)
                                    <td> {{ formatMoney($data->pieces_price) }}
                                    </td>
                                    <td> {{ formatMoney($data->pieces_max_discount) }}
                                    </td>
                                @endif

                            </tr>
                            <tr>

                                <td>
                                    <input type="number" name="new_buy_price" id="new_buy_price"
                                        placeholder="New Buy Price" class=" form-control"
                                        value="{{ old('new_buy_price') ? old('new_buy_price') : $data->pr_buy_price }}" />
                                </td>
                                <td>
                                    <input type="number" name="price" id="price" placeholder="Price"
                                        class=" form-control"
                                        value="{{ old('price') ? old('price') : $data->current_sale_price }}" />
                                </td>
                                <td>
                                    <input type="number" name="max_discount" id="max_discount"
                                        placeholder=" Maximum Discount" class=" form-control"
                                        value="{{ old('max_discount') ? old('max_discount') : $data->max_discount }}" />
                                </td>
                                @if ($application->allow_piece_sale == 1)
                                    <td>
                                        <input type="number" name="pieces_price" id="pieces_price"
                                            placeholder=" Pieces Price" class=" form-control"
                                            value="{{ old('pieces_price') ? old('pieces_price') : $data->pieces_price }}" />
                                    </td>
                                    <td>
                                        <input type="number" name="pieces_max_discount" id="pieces_max_discount"
                                            placeholder=" Maximum discount" class=" form-control"
                                            value="{{ old('pieces_max_discount') ? old('pieces_max_discount') : $data->pieces_max_discount }}" />
                                    </td>
                                @endif

                            </tr>
                        </tbody>

                    </table>
                </div>
                <div class="card-footer bg-transparent border-info">
                    <div class="form-group row">
                        <div class="col-md-6"><a href="{{ route('products.index') }}" class="btn btn-success"> <i
                                    class="fa fa-close"></i> Back</a></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary float-right"> <i
                                    class="fa fa-send"></i> Submit</button></div>
                    </div>
                </div>
            </form>
        </div>
    </section>


@endsection
