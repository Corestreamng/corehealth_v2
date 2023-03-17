@extends('admin.layouts.app')
@section('title', 'Products ')
@section('page_name', 'Products ')
@section('subpage_name', 'Create Product')
@section('content')
    <section class="container">

        <div class="card  mb-3">
            {!! Form::open(['route' => 'products.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
            {{ csrf_field() }}
            <div class="card-header bg-transparent ">{{ __('Product') }}</div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="category_id" class="col-sm-2 col-form-label">{{ __('Category') }}</label>
                    <div class="col-sm-10">
                        {!! Form::select('category', $category, null, [
                            'id' => 'category_id',
                            'name' => 'category_id',
                            'placeholder' => 'Pick Category',
                            'class' => 'form-control',
                            'data-live-search' => 'true',
                        ]) !!}
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-sm-2 col-form-label">{{ __('Name') }}</label>
                    <div class="col-sm-10">
                        <input type="text" id="product_name" class="form-control" name="product_name"
                            value="{{ old('product_name') }}" placeholder="Product Name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="product_code" class="col-sm-2 col-form-label">{{ __('Code') }}</label>
                    <div class="col-sm-10">
                        <input type="text" id="product_code" class="form-control" name="product_code"
                            value="{{ old('product_code') }}" placeholder="Product Code">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="reorder_alert" class="col-sm-2 col-form-label">{{ __('Re-Order Alert') }}</label>

                    <div class="col-sm-10">
                        <input id="reorder_alert" type="number" class="form-control" name="reorder_alert"
                            value="{{ old('reorder_alert') }}" placeholder="Re-Order Alert Value">
                    </div>
                </div>

                @if ($application->allow_halve_sale == 1)
                    <div class="form-group row">
                        <label for="select" class="col-sm-2 col-form-label">Allow to Sale Half</label>
                        <div class="col-sm-10">
                            <select id="s1" name="s1" class="form-control">
                                <option value="">--Pick--</option>
                                <option value="2">No</option>
                                <option value="1">Yes </option>
                            </select>
                        </div>
                    </div>
                @endif

                @if ($application->allow_piece_sale == 1)
                    <div class="form-group row">
                        <label for="select" class="col-sm-2 col-form-label">Allow to Sale Pieces</label>
                        <div class="col-sm-10">
                            <select id="s2" name="s2" class="form-control">
                                <option value="">--Pick--</option>
                                <option value="2">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                    </div>
                @endif

                @if ($application->allow_halve_sale == 1 || $application->allow_piece_sale == 1)
                    <div class="form-group row">
                        <label for="quantity_in" class="col-sm-2 col-form-label">{{ __('Quantity In') }}</label>
                        <div class="col-sm-10">
                            <input id="quantity_in" type="number" class="form-control" name="quantity_in"
                                value="{{ old('quantity_in') }}" placeholder="Quantity In">
                        </div>
                    </div>
                @endif

            </div>
            <div class="card-footer bg-transparent ">
                <div class="form-group row">
                    <div class="col-md-6"><a href="{{ route('products.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6 "><button type="submit" class="btn btn-primary pull-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

    </section>


@endsection
