@extends('admin.layouts.app')
@section('title', 'Product Stock')
@section('page_name', 'Product Stock')
@section('subpage_name', 'Manage Product Stock')
@section('content')
    <div id="content-wrapper">

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="card-modern">
                        <div class="card-header">{{ __('Product Stock') }}</div>

                        <div class="card-body">
                            @csrf
                            {!! Form::open(['route' => 'stocks.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}

                            <table id="products" class="table table-sm table-responsive table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Store</th>
                                        <th>Order Quantity </th>
                                        <th>Total Amount</th>
                                    </tr>
                                </thead>

                                <tr>
                                    <td>
                                        <input type="text" name="products" id="products" class=" form-control"
                                            value="{{ old('products') ? old('products') : $products->product_name }}"
                                            readonly="1" />
                                        <input type="hidden" name="product_id" id="product_id" class=" form-control"
                                            value="{{ old('product_id') ? old('product_id') : $products->id }}"
                                            readonly="1" />
                                    </td>
                                    <td>
                                        {!! Form::select('stores ', $stores, null, [
                                            'id' => 'stores',
                                            'placeholder' => 'Please Select store',
                                            'class' => 'select2 show-tick form-control',
                                            'data-live-search' => 'true',
                                        ]) !!}
                                    </td>

                                    <td>
                                        <input type="number" name="quantity" id="quantity" placeholder=" Quantity"
                                            class=" form-control" value="{{ old('quantity') }}" required="1" />
                                    </td>
                                    <td>
                                        <input type="number" name="total_amount" id="total_amount" placeholder=" Amount"
                                            class="form-control" value="{{ old('total_amount') }}" required="1" />
                                    </td>

                                </tr>
                            </table>

                            <div class="box-footer" align="center">
                                <a href="{{ route('products.index') }}" class="btn btn-success"> Back</a>
                                <button type="submit" class="btn btn-primary"> <i class="fa fa-send"></i> Submit</button>
                            </div>

                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>


@endsection
