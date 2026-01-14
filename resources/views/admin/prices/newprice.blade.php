@extends('admin.layouts.app')
@section('title', 'Product Price Setting ')
@section('page_name', 'Products ')
@section('subpage_name', 'New Price Setting')
@section('content')
    <div id="content-wrapper">



        <div class="container">
            <div class="row justify-content-center">
                <div class="raw">
                    <div class="card-modern">
                        <div class="card-header">{{ $products->product_name }} -{{ __(' Price Setting') }}</div>

                        <div class="card-body">

                            @csrf
                            {!! Form::open(['route' => 'prices.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}


                            <table class="table table-sm table-responsive table-bordered table-striped ">

                                <thead>
                                    <tr>
                                        <th>Buy Price (&#8358;)</th>
                                        <th>Price (&#8358;)</th>
                                        <th>Mxd (&#8358;)</th>
                                        @if ($application->allow_piece_sale == 1)
                                            <th>Pieces Price (&#8358;)</th>
                                            <th>Pieces Mxd (&#8358;)</th>
                                        @endif

                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>

                                        <td>
                                            <input type="number" name="buy_price" id="buy_price" placeholder="buy_price"
                                                class=" form-control" value="{{ old('buy_price') }}" />
                                        </td>
                                        <td>
                                            <input type="number" name="price" id="price" placeholder=" price"
                                                class=" form-control" value="{{ old('price') }}" />
                                        </td>
                                        <td>
                                            <input type="number" name="max_discount" id="max_discount"
                                                placeholder=" max_discount" class=" form-control" readonly="1"
                                                value="{{ old('max_discount') ? old('max_discount') : 0 }}" />
                                        </td>
                                        @if ($application->allow_piece_sale == 1)
                                            <td>
                                                <input type="number" name="piece_sprice" id="pieces_price"
                                                    placeholder=" Pieces Price" class=" form-control"
                                                    value="{{ old('piece_sprice') ? old('piece_sprice') : 0 }}" />
                                            </td>
                                            <td>
                                                <input type="number" name="pieces_max_discount" id="pieces_max_discount"
                                                    placeholder=" Max discount" class=" form-control" readonly="1"
                                                    value="{{ old('pieces_max_discount') ? old('pieces_max_discount') : 0 }}" />
                                            </td>
                                        @endif

                                    </tr>
                                </tbody>

                            </table>
                            <input type="hidden" name="products" id="products" placeholder=" products"
                                class=" form-control" value="{{ $products->id }}" />
                            <div class="box-footer" align="center">
                                <a href="{{ route('products.index') }}" class="btn btn-success"> Back</a>
                                <button type="submit" class="btn btn-primary"> <i class="fa fa-send"></i> Submit</button>
                            </div>
                            <br>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection
