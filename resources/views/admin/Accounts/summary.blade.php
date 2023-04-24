@extends('admin.layouts.app')
@section('title', 'Services and Products ')
@section('page_name', 'Summary')
@section('subpage_name', 'Services Request List')
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
                                <td>{{$service->price->sale_price ?? 0}}</td>
                                <td></td>
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
                                <td>{{$product->id}}</td>

                            @empty
                            <P>No product selected for payment</P>

                            @endforelse

                            @endisset


                        </table>
                        {{-- <div>
                            <label for="">Reference number</label>
                            <input type="text">
                        </div>
                        <label for="">payment type</label>
                        <div>
                            <input type="text">
                        </div>
                        <div>
                            <input type="text">
                        </div> --}}
                    </div>
                    <div>
                        <div>
                            <label for="payment_type">Type</label>
                            <select name="payment_type" id="">
                            <option value="POS">POS</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                    </div>
                    <div>
                        @if (@isset($sumServices) || @isset($sumProducts))
                        {{-- <p>{{dd($sumServices)}}</p> --}}
                        <label for="total">Total</label>
                       <input type="text" name="total" id="" value="{{$sumProducts ?? 0 + $sumServices ?? 0}}" required>
                        @endif
                    </div>
                    <div>
                        <label for="reference_no"> Ref</label>
                        <input type="text" name="reference_no" id="">
                    </div>
                    </div>
                    <div>
                    <button type="submit" class="align-self-end btn btn-lg btn-block btn-primary" style="margin-top: auto;">complete payment</button>
                </div>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection
