@extends('admin.layouts.app')
@section('title', 'Services ')
@section('page_name', 'Services ')
@section('subpage_name', 'Create Service')
@section('content')
    <section class="container">

        <div class="card  mb-3">
            {!! Form::open(['route' => 'services.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
            {{ csrf_field() }}
            <div class="card-header bg-transparent ">{{ __('Create Service') }}</div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="category_id" class="col-md-2 col-form-label">{{ __('Category') }} <i class="text-danger">*</i>
                    </label>
                    <div class="col-md-10">
                        {!! Form::select('category', $category, null, [
                            'id' => 'category_id',
                            'name' => 'category_id',
                            'placeholder' => 'Pick Category',
                            'class' => 'form-control',
                            'data-live-search' => 'true',
                            'required' => 'true',
                        ]) !!}
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-md-2 col-form-label">{{ __('Name') }} <i
                            class="text-danger">*</i></label>
                    <div class="col-md-10">
                        <input type="text" id="service_name" class="form-control" name="service_name"
                            value="{{ old('service_name') }}" placeholder="Service Name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="service_code" class="col-md-2 col-form-label">{{ __('Code') }}</label>
                    <div class="col-md-10">
                        <input type="text" id="service_code" class="form-control" name="service_code"
                            value="{{ old('service_code') }}" placeholder="Service Code">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent ">
                <div class="form-group row">
                    <div class="col-md-6"><a href="{{ route('services.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6 "><button type="submit" class="btn btn-primary pull-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

    </section>


@endsection
