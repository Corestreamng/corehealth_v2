@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Edit Store')
@section('content')
    <section class="content">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Store</h3>
                </div>

                <div class="card-body">
                    {!! Form::model($store, ['method' => 'PATCH', 'route' => ['stores.update', $store->id]]) !!}
                    {{ csrf_field() }}

                    <div class="form-group">
                        <label for="store_name" class="col-sm-2 control-label">Store Name </label>

                        <div class="col-sm-12">
                            <input type="text" class="form-control" id="store_name" name="store_name"
                                value="{!! !empty($store->store_name) ? $store->store_name : old('store_name') !!}" required autofocus
                                placeholder="Enter Store Name e.g (Musa Store, Store 1 etc)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="location" class="col-sm-2 control-label">Location</label>

                        <div class="col-sm-12">
                            <textarea id="location" name="location" class="form-control"
                                placeholder="Enter location">
                            {!! !empty($store->location) ? $store->location : old('location') !!}
                        </textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-2 control-label">Is Store Active</label>
                        <div class="col-sm-12 checkbox checkbox-info checkbox-circle">
                            <label>
                                <input id="visible" type="checkbox" name="visible" {!! $store->visible ? 'checked="checked"' : '' !!}>
                                True
                            </label>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-sm-offset-1 col-sm-6">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-sm-6">
                                    <a href="{{ route('stores.index') }}" class="pull-right btn btn-danger"><i
                                            class="fa fa-close"></i> Back </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {!! Form::close() !!}

                </div>
            </div>

        </div>

    </section>

@endsection

@section('scripts')

    

@endsection
