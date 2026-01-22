@extends('admin.layouts.app')
@section('title', 'Store Management')
@section('page_name', 'Store Management')
@section('subpage_name', 'Create Store')
@section('content')
    <section class="content row">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header">
                    <h3 class="card-title">Create Store</h3>
                </div>

                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => 'stores.store', 'class' => 'form-horizontal']) !!}
                    {{ csrf_field() }}

                    <div class="form-group">
                        <label for="store_name" class="col-md-2 control-label">Store Name <i class = "text-danger">*</i></label>

                        <div class="col-md-12">
                            <input type="text" class="form-control" id="store_name" name="store_name"
                                value="{{ old('store_name') }}" required autofocus
                                placeholder="Enter Store Name e.g (Pharmacy store, Nursing store etc)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="location" class="col-md-2 control-label">Location</label>

                        <div class="col-md-12">
                            <textarea id="location" name="location" class="form-control" placeholder="Enter location">
                      {{ old('location') }}
                      </textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">Is Store Active</label>
                        <div class="col-md-12 checkbox checkbox-info checkbox-circle">
                            <label>
                                <input id="status" type="checkbox" name="status" checked>
                                Active
                            </label>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-offset-1 col-md-6">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-6">
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

    <script>
        //  CKEDITOR.replace('content');
    </script>

    <script>
        $(document).ready(function() {
            $.noConflict();
            // CKEDITOR.replace('location');
        });
    </script>

@endsection
