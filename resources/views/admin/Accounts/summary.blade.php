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
<form action="{{route('service-payment')}}" method="post">
    @csrf
                <div class="card-body">
                    <h4>Services</h4>
                    <div class="table-responsive">
                        <table id="products-list" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Service Name</th>
                                    <th>select</th>
                                </tr>
                            </thead>
                            <tbody>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tbody>
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
                                    <th>select</th>
                                </tr>
                            </thead>
                            <tbody>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tbody>
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
                    <button type="submit" class="align-self-end btn btn-lg btn-block btn-primary" style="margin-top: auto;">proceed</button>
                </div>
            </form>
            </div>
        </div>
    </div>
@endsection
