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
                            {!! Form::open(['route' => 'move-stock.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
                            {{--   {{ csrf_field() }} --}}
                            <div class="table-responsive">
                                <table id="products" class="table table-sm table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Current Store</th>
                                            <th> Quantity </th>
                                            <th> New Store</th>
                                            <th> Quantity to Transfer</th>

                                        </tr>
                                    </thead>

                                    <tr>
                                        <td>
                                            <input type="text" name="products" id="products" class=" form-control"
                                                value="{{ old('products') ? old('products') : $pc->product->product_name }}"
                                                readonly="1" />
                                            <input type="hidden" name="product_id" id="product_id" class=" form-control"
                                                value="{{ old('product_id') ? old('product_id') : $pc->product->id }}"
                                                readonly="1" />
                                        </td>
                                        <td>
                                            <input type="text" name="current_store" id="current_store" class=" form-control"
                                                value="{{ old('products') ? old('products') : $pc->store->store_name }}"
                                                readonly="1" />
                                            <input type="hidden" name="store_id" id="store_id" class=" form-control"
                                                value="{{ old('product_id') ? old('product_id') : $pc->store->id }}"
                                                readonly="1" />
                                        </td>
                                        <td>
                                            <input type="text" name="current_quntity" id="current_quntity"
                                                class=" form-control"
                                                value="{{ old('current_quntity') ? old('current_quntity') : $pc->current_quantity }}"
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


                                    </tr>
                                </table>
                            </div>
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
