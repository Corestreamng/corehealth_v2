@extends('admin.layouts.app')
@section('title', 'Edit Service')
@section('page_name', 'Services')
@section('subpage_name', 'Edit Service')
@section('content')
    <div class="card border-info mb-3">
        <form class="form-horizontal" method="POST" action="{{ route('services.update', $product->id) }}">
            {{ csrf_field() }}
            <input name="_method" type="hidden" value="PUT">
            <div class="card-header bg-transparent border-info">{{ __('Edit Service') }}</div>
            <div class="card-body">
                <div class="form-group row">
                    <label for="category_id" class="col-md-2 col-form-label">{{ __('Category') }} <i
                            class="text-danger">*</i></label>
                    <div class="col-md-10">
                        {!! Form::select('category', $category, $product->category_id, [
                            'id' => 'category_id',
                            'name' => 'category_id',
                            'placeholder' => 'Pick Category',
                            'class' => 'form-control',
                            'data-live-search' => 'true',
                        ]) !!}
                    </div>
                </div>

                <div class="form-group row">
                    <label for="name" class="col-md-2 col-form-label">{{ __('Name') }}<i
                            class="text-danger">*</i></label>
                    <div class="col-md-10">
                        <input type="text" id="service_name" class="form-control" name="service_name"
                            value="{{ old('service_name') ? old('service_name') : $product->service_name }}"
                            placeholder="Service Name">
                    </div>
                </div>

                <div class="form-group row">
                    <label for="service_code" class="col-md-2 col-form-label">{{ __('Code') }}</label>
                    <div class="col-md-10">
                        <input type="text" id="service_code" class="form-control" name="service_code"
                            value="{{ old('service_code') ? old('service_code') : $product->service_code }}"
                            placeholder="Service Code">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-success">
                <div class="form-group row">

                    <div class="col-md-6">
                        <a href="{{ route('services.index') }}" class="btn btn-success"> <i class="fa fa-close"></i>
                            Back</a>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-primary float-right"> <i class="fa fa-send"></i>
                            Update</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

@endsection
