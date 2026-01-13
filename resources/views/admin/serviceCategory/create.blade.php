@extends('admin.layouts.app')
@section('title', 'Services Category')
@section('page_name', 'Services Category')
@section('subpage_name', 'New Service Category')
@section('content')
    <section class="content">

        <div class="card-modern mb-3">

            <div class="card-header bg-transparent ">
                <div class="row">
                    <div class="col-md-6">
                        {{ __('Service Category') }}
                    </div>
                    <div class="col-md-6">

                    </div>
                </div>
            </div>
            {!! Form::open(['route' => 'services-category.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
            {{ csrf_field() }}
            <div class="card-body">
                <div class="form-group row">
                    <label for="category_name" class="col-md-2 col-form-label">{{ __('Name') }}</label>
                    <div class="col-md-10">
                        <input type="text" id="category_name" class="form-control" name="category_name"
                            value="{{ old('category_name') }}" placeholder="Category Name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="category_code " class="col-md-2 col-form-label">{{ __('Code') }}</label>
                    <div class="col-md-10">
                        <input type="text" id="category_code" class="form-control" name="category_code"
                            value="{{ old('category_code') }}" placeholder="Category Code">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="category_description " class="col-md-2 col-form-label">{{ __('Description') }}</label>
                    <div class="col-md-10">
                        {!! Form::textarea('category_description', null, [
                            'class' => 'form-control',
                            'rows' => 4,
                            'name' => 'category_description',
                            'id' => 'category_description',
                            'placeholder' => 'Category Description',
                        ]) !!}
                    </div>
                </div>

            </div>
            <div class="card-footer bg-transparent">
                <div class="form-group row">
                    <div class="col-md-6"><a href="{{ route('services-category.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6 "><button type="submit" class="btn btn-primary pull-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

    </section>
@endsection
