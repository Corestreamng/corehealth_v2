@extends('admin.layouts.app')
@section('title', 'Service Category')
@section('page_name', 'Service Category')
@section('subpage_name', 'Edit Service Category')
@section('content')
<div class="card border-info mb-3">
    {!! Form::model($productCat, ['method' => 'PATCH', 'route'=> ['services-category.update', $productCat->id], 'class' =>
    'form-horizontal'])
    !!}
    {{ csrf_field() }}
    <input name="_method" type="hidden" value="PUT">

    <div class="card-header bg-transparent border-info">{{ __('Edit Service Category') }}</div>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-sm-2 col-form-label">{{ __('Name') }}</label>
            <div class="col-sm-10">
                <input type="text" id="category_name" class="form-control" name="category_name"
                    value="{{ (old('category_name')) ? old('category_name') : $productCat->category_name }}"
                    placeholder="Category Name">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-sm-2 col-form-label">{{ __('Code') }}</label>
            <div class="col-sm-10">
                <input type="text" id="category_code" class="form-control" name="category_code"
                    value="{{ (old('category_code')) ? old('category_code') : $productCat->category_code }}"
                    placeholder="Category Name">
            </div>
        </div>

        <div class="form-group row">
            <label for="category_description " class="col-sm-2 col-form-label">{{ __('Description') }}</label>
            <div class="col-sm-10">
                {!! Form::textarea("category_description", $productCat->category_description, ['class' =>
                'form-control',
                'rows' => 4,'name' => 'category_description', 'id' => 'category_description', 'placeholder' =>
                'Category Description']) !!}
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-success">
        <div class="form-group row">

            <div class="col-md-6"><a href="{{ route('services-category.index') }}" class="btn btn-success"> <i
                        class="fa fa-close"></i> Back</a></div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary float-right"> <i class="fa fa-send"></i>
                    Update</button></div>
        </div>
    </div>
    </form>
</div>
@endsection
