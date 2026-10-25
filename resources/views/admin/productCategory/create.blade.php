@extends('admin.layouts.app')
@section('title', 'Product Category')
@section('page_name', 'Product Category')
@section('subpage_name', 'Product Category List')
@section('content')
    <section class="content">

        <div class="card-modern mb-3">

            <div class="card-header bg-transparent ">
                <div class="row">
                    <div class="col-md-6">
                        {{ __('Product Category') }}
                    </div>
                    <div class="col-md-6">

                    </div>
                </div>
            </div>
            {!! Form::open(['route' => 'product-category.store', 'method' => 'POST', 'class' => 'form-horizontal']) !!}
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
                    <label for="department_id" class="col-md-2 col-form-label">{{ __('Department') }}</label>
                    <div class="col-md-10">
                        <select name="department_id" id="department_id" class="form-control">
                            <option value="">-- {{ __('Select Operating Department (Optional)') }} --</option>
                            @if(isset($departments))
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }} {{ $dept->code ? "({$dept->code})" : '' }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                        <small class="form-text text-muted">{{ __('Products under this category will default to this department for revenue attribution and reporting.') }}</small>
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
                    <div class="col-md-6"><a href="{{ route('product-category.index') }}" class="btn btn-success"> <i
                                class="fa fa-close"></i> Back</a></div>
                    <div class="col-md-6 "><button type="submit" class="btn btn-primary pull-right"> <i
                                class="fa fa-send"></i> Submit</button></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>

    </section>
@endsection
