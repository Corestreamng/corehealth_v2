@extends('admin.layouts.app')
@section('title', 'Service Price Setting ')
@section('page_name', 'Services ')
@section('subpage_name', 'New Price Setting')
@section('content')
    <div id="content-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="raw">
                    <div class="card-modern">
                        <div class="card-header">{{ $service->service_name }} -{{ __(' Price Setting') }}</div>

                        <div class="card-body">

                            @csrf
                            {!! Form::open(['route' => 'service-prices.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}


                            <div class="table-responsive">
                                <table class="table table-sm table-bordered table-striped ">

                                    <thead>
                                        <tr>
                                            <th>Cost Price (NGN)</th>
                                            <th>Issue Price (NGN)</th>
                                            <th>Maximum Discount (NGN)</th>
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
                                        </tr>
                                    </tbody>

                                </table>
                            </div>
                            <input type="hidden" name="service" id="service" placeholder=" service"
                                class=" form-control" value="{{ $service->id }}" />
                            <div class="box-footer" align="center">
                                <a href="{{ route('services.index') }}" class="btn btn-success"> Back</a>
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
