@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Store List')
@section('content')
    <div id="content-wrapper">
        <div class="container">
            <div class="card-modern">
                <div class="card-header">
                    <div class="clearfix">
                        <h3 class="float-left">{{ __('Store List') }}</h3>
                        @can('can-manage-store')
                            <a href="{{ route('stores.create') }}" class="btn btn-primary btn-sm float-right">New Store</a>
                        @endcan
                    </div>


                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="stores" class="table table-sm table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Store</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Edit </th>
                                    <th>Products</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>



    @endsection
@include('admin.stores.partials._scripts')
