@extends('admin.layouts.app')
@section('title', 'Product Category')
@section('page_name', 'Product Category')
@section('subpage_name', 'Show Category')
@section('content')
    <div class="card-modern border-info mb-3">
        <div class="card-header bg-transparent border-info">{{ __('List of Products') }}</div>
        <div class="card-body">
            <div class="form-group col-md-12">
                <label for="permission">List of Products Assigned to <span
                        class="badge badge-dark">{!! $reqCat[0]->category_name !!}</span> Category</label>
                <div class="col-md-12">
                    <div class="row">
                        {{-- @foreach ($reqCat[0]->products as $product)
                            @if (!empty($product))
                                <div class="col-md-4">
                                    <div class="checkbox">
                                        <label class="form-check-label" for="{{ $product->product_name }}"><i
                                                class="fa fa-compass"></i>
                                            {{ $product->product_name }}</label>
                                        <br />
                                    </div>
                                </div>
                            @else
                                <div class="col-md-4">
                                    <div class="checkbox">
                                        <label class="form-check-label" for="{{ $product->product_name }}"><i
                                                class="fa fa-compass"></i>
                                            {{ $product->product_name }}</label>
                                        <p>No Product for this category <span
                                                class="badge badge-dark">{!! $reqCat[0]->category_name !!}</span></p>
                                        <br />
                                    </div>
                                </div>
                            @endif
                        @endforeach --}}
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent border-info">
            <div class="form-group row">

                <div class="col-md-6"><a href="{{ route('product-category.index') }}" class="btn btn-success"> <i
                            class="fa fa-close"></i> Back</a></div>
                <div class="col-md-6">
                    {{-- <button type="submit" class="btn btn-primary float-right"> <i class="fa fa-send"></i>Update</button></div> --}}
                </div>
            </div>
        </div>

    @endsection
