@extends('admin.layouts.app')
@section('title', 'Services and Products ')
@section('page_name', 'Summary')
@section('subpage_name', 'Services and Products Request Summary')
@section('content')
    <div id="content-wrapper">
        <div class="container">

            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-sm-6">
                            {{-- {{ __('Services') }} --}}
                        </div>
                        <div class="col-sm-6">
                            {{-- @if (auth()->user()->can('user-create')) --}}
                            {{-- <a href="{{ route('add-to-queue') }}" id="loading-btn" data-loading-text="Loading..."
                                class="btn btn-primary btn-sm float-right">
                                <i class="fa fa-plus"></i>
                                New Request
                            </a> --}}
                            {{-- @endif --}}
                        </div>
                    </div>
                </div>
<form action="{{route('complete-payment')}}" method="post">
    @csrf
                <div class="card-body">
                    <h4>Services</h4>
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Service Name</th>
                                    <th>price</th>
                                </tr>
                            </thead>
                            @isset($services)
                            @forelse ($services as $service )
                            <tbody>
                                <td>{{$service->id}}</td>
                                <td>{{$service->service_name}}</td>
                                <td><span>&#8358;</span>{{ $service->price->sale_price ?? 0}}</td>

                            </tbody>
                            @empty
                            <p>no service selected for payment</p>
                            @endforelse

                            @endisset


                        </table>
                    </div>

                </div>
                <div class="card-body">
                    <h4>Products</h4>
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product Name</th>
                                    <th>price</th>
                                </tr>
                            </thead>
                            @isset($products)
                            @forelse ($products as $product )
                            <tbody>
                                <td>{{$product->id}}</td>
                                <td>{{$product->product_name}}</td>
                                <td><span>&#8358;</span>{{ $product->price->current_sale_price}}</td>

                            </tbody>
                            @empty
                            <P>No product selected for payment</P>

                            @endforelse

                            @endisset


                        </table>
                    </div>
                </div>
                <div class="card-body">

                        <div class="form-group">
                            <label for="payment_type">Payment Type</label>
                            <select class="form-control" name="payment_type" id="payment_type">
                            <option value="POS">POS</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        @if (array_key_exists('sumServices', get_defined_vars()) ==FALSE)
                        {{$sumServices = 0}}
                        @endif
                        @if (array_key_exists('sumProducts', get_defined_vars()) ==FALSE)
                        {{$sumProducts = 0}}
                        @endif
                        {{-- <p>{{dd($sumServices)}}</p> --}}
                        <label for="total">Total</label>
                        <input type="text" class="form-control" name="total" id="total" value="{{$sumProducts  + $sumServices}}" required>
                    </div>
                    <div class="form-group">
                        <label for="reference_no"> Reference Number</label>
                        <input type="text" class="form-control" name="reference_no" id="reference number">
                    </div>
                    <div>
                    <button type="submit" class="align-self-end btn btn-lg btn-block btn-primary" style="margin-top: auto;">complete payment</button>
                </div>
            </div>
                </div>
            </form>
            </div>
        </div>
@endsection
