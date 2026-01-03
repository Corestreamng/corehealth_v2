@extends('admin.layouts.app')
@section('title', 'Service Price Setting ')
@section('page_name', 'Services ')
@section('subpage_name', 'Service Price Setting')
@section('content')
    <section class="container">
        <div class="card border-info mb-3">
            {!! Form::open(['route' => 'service-prices.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
            @csrf
            <div class="card-header bg-transparent border-info">{{ __('Product Price Setting') }}</div>
            <div class="card-body">
                <table class="table table-sm table-responsive table-bordered table-striped ">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Cost Price (NGN)</th>
                            <th>Issue Price (NGN)</th>
                            <th>Maximum Discount (NGN)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>

                            <td>
                                <input type="text" name="service_name" id="service_name" class=" form-control"
                                    value="{{ $service->service_name }}" />
                                <input type="hidden" name="service_id" value="{{ $service->id }}">
                            </td>

                            <td>
                                <input type="number" name="buy_price" id="buy_price" placeholder="Buying Price"
                                    class=" form-control" value="{{ old('buy_price') }}" />
                            </td>
                            <td>
                                <input type="number" name="price" id="price" placeholder="Price"
                                    class=" form-control" value="{{ old('price') }}" />
                            </td>
                            <td>
                                <input type="number" name="max_discount" id="max_discount" placeholder="Maximum Discount"
                                    class=" form-control" value="{{ old('max_discount') ? old('max_discount') : 0 }}" />
                            </td>
                            @if ($application->allow_piece_sale == 1)
                                <td>
                                    <input type="text" name="piece_sprice" id="pieces_price" placeholder="Pieces Price"
                                        class=" form-control" value="{{ old('piece_sprice') ? old('piece_sprice') : 0 }}" />
                                </td>
                                <td>
                                    <input type="text" name="pieces_max_discount" id="pieces_max_discount"
                                        placeholder="Maximum Discount" class=" form-control" readonly="1"
                                        value="{{ old('pieces_max_discount') ? old('pieces_max_discount') : 0 }}" />
                                </td>
                            @endif

                        </tr>
                    </tbody>
                </table>

            </div>
            <div class="card-footer bg-transparent border-info">
                <div class="form-group row">
                    <div class="col-md-6"><a href="{{ route('services.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6"><button type="submit" class="btn btn-primary float-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </section>
@endsection
