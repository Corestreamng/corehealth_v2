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
                        <div class="col-md-6">
                            {{-- {{ __('Services') }} --}}
                        </div>
                        <div class="col-md-6">
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
                <form action="{{ route('complete-payment') }}" method="post" target="_blank">
                    @csrf
                    <div class="card-body">
                        <h4>Services</h4>
                        <div class="table-responsive">
                            <table id="products-list" class="table table-sm table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>SN</th>
                                        <th>Service Name</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                    </tr>
                                </thead>
                                @isset($services)
                                    @php
                                        $i = 0;
                                    @endphp
                                    @forelse ($services as $service)
                                        <tbody>
                                            <td>{{ $service->id }}</td>
                                            <td>{{ $service->service->service_name }}</td>
                                            <td><span>&#8358;</span>{{ $service->service->price->sale_price ?? 0 }}</td>
                                            <td>{{$serviceQty[$i]}}</td>
                                        </tbody>
                                        @php
                                            $i++;
                                        @endphp
                                    @empty
                                        <p>No service selected for payment</p>
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
                                        <th>Price</th>
                                        <th>Qty</th>
                                    </tr>
                                </thead>
                                @isset($products)
                                    @php
                                        $j = 0;
                                    @endphp
                                    @forelse ($products as $product)
                                        <tbody>
                                            <td>{{ $product->id }}</td>
                                            <td>{{ $product->product->product_name }}</td>
                                            <td><span>&#8358;</span>{{ $product->product->price->current_sale_price }}</td>
                                            <td>{{$productQty[$j]}}</td>
                                        </tbody>
                                        @php
                                            $j++;
                                        @endphp
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
                                <option value="CASH">Cash</option>
                                <option value="TRANSFER">Transfer</option>
                                <option value="TELLER">Teller</option>
                                <option value="CHEQUE">Cheque</option>
                                <option value="ACC_WITHDRAW">Credit Account(NGN {{(($services[0]->user->patient_profile->account) ? $services[0]->user->patient_profile->account->balance : (($products[0]->user->patient_profile->balance) ? $products[0]->user->patient_profile->account->balance : "N/A"))}} )</option>
                                <option value="CLAIMS">Claims</option>
                            </select>
                        </div>

                        <div class="form-group">
                            @if (array_key_exists('sumServices', get_defined_vars()) == false)
                                {{ $sumServices = 0 }}
                            @endif
                            @if (array_key_exists('sumProducts', get_defined_vars()) == false)
                                {{ $sumProducts = 0 }}
                            @endif
                            {{-- <p>{{dd($sumServices)}}</p> --}}
                            <label for="total">Total</label>
                            <input type="text" class="form-control" name="total" id="total"
                                value="{{ $sumProducts + $sumServices }}" required>
                        </div>
                        <div class="form-group">
                            <label for="reference_no"> Reference Number</label>
                            <input type="text" class="form-control" name="reference_no" id="reference number" value="{{generate_invoice_no()}}">
                            <input type="hidden" name="patient_id" value="{{(($services[0]->user->patient_profile) ? $services[0]->user->patient_profile->id : (($products[0]->user->patient_profile) ? $products[0]->user->patient_profile->id : "N/A"))}}">
                        </div>
                        <div>
                            <button type="submit" class="align-self-end btn btn-lg btn-block btn-primary"
                                style="margin-top: auto;">complete payment</button>
                        </div>
                    </div>
            </div>
            </form>
        </div>
    </div>
@endsection
