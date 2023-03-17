@extends('admin.layouts.app')
@section('title', 'Service Price Setting ')
@section('page_name', 'Services ')
@section('subpage_name', 'Edit Service Price')
@section('content')
    <section class="container">
        <div class="card border-info mb-3">
            <div class="card-header bg-transparent border-info">{{ __(ucwords($data->service->service_name)) }}</div>
            <form class="form-horizontal" method="POST" action="{{ route('service-prices.update', $data->id) }}">
                {{ csrf_field() }}
                <input name="_method" type="hidden" value="PUT">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped ">
                            <thead>
                                <tr>
                                    <th>Cost Price {{ NAIRA_CODE }}</th>
                                    <th>Issue Price {{ NAIRA_CODE }}</th>
                                    <th>Maximum Discount {{ NAIRA_CODE }}</th>

                                    <input type="hidden" name="id" id="id" class=" form-control"
                                        value="{{ old('name') ? old('name') : $data->id }}" />
                                    <input type="hidden" name="service" id="service" placeholder=" service"
                                        class=" form-control" value="{{ $data->id }}" />

                                </tr>
                            </thead>
                            <tbody>

                                <tr>

                                    <td>{{ formatMoney($data->cost_price) }}
                                    </td>
                                    <td>{{ formatMoney($data->sale_price) }}
                                    </td>
                                    <td> {{ formatMoney($data->max_discount) }}
                                    </td>
                                </tr>
                                <tr>

                                    <td>
                                        <input type="number" name="cost_price" id="cost_price" placeholder="New Buy Price"
                                            class=" form-control"
                                            value="{{ old('cost_price') ? old('cost_price') : $data->cost_price }}" />
                                    </td>
                                    <td>
                                        <input type="number" name="price" id="price" placeholder="Price"
                                            class=" form-control"
                                            value="{{ old('price') ? old('price') : $data->sale_price }}" />
                                    </td>
                                    <td>
                                        <input type="number" name="max_discount" id="max_discount"
                                            placeholder=" Maximum Discount" class=" form-control"
                                            value="{{ old('max_discount') ? old('max_discount') : $data->max_discount }}" />
                                    </td>
                                </tr>
                            </tbody>

                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-info">
                    <div class="form-group row">
                        <div class="col-md-6"><a href="{{ route('services.index') }}" class="btn btn-success"> <i
                                    class="fa fa-close"></i> Back</a></div>
                        <div class="col-md-6"><button type="submit" class="btn btn-primary float-right"> <i
                                    class="fa fa-send"></i> Submit</button></div>
                    </div>
                </div>
            </form>
        </div>
    </section>


@endsection
