@extends('admin.layouts.app')
@section('title', 'Product Category')
@section('page_name', 'Product Category')
@section('subpage_name', 'Edit Product Category')
@section('content')
<div class="card-modern border-info mb-3">
    {!! Form::model($productCat, ['method' => 'PATCH', 'route'=> ['product-category.update', $productCat->id], 'class' =>
    'form-horizontal'])
    !!}
    {{ csrf_field() }}
    <input name="_method" type="hidden" value="PUT">

    <div class="card-header bg-transparent border-info">{{ __('Edit Product') }}</div>
    <div class="card-body">
        <div class="form-group row">
            <label for="name" class="col-md-2 col-form-label">{{ __('Name') }}</label>
            <div class="col-md-10">
                <input type="text" id="category_name" class="form-control" name="category_name"
                    value="{{ (old('category_name')) ? old('category_name') : $productCat->category_name }}"
                    placeholder="Category Name">
            </div>
        </div>

        <div class="form-group row">
            <label for="name" class="col-md-2 col-form-label">{{ __('Code') }}</label>
            <div class="col-md-10">
                <input type="text" id="category_code" class="form-control" name="category_code"
                    value="{{ (old('category_code')) ? old('category_code') : $productCat->category_code }}"
                    placeholder="Category Code">
            </div>
        </div>

        <div class="form-group row">
            <label for="department_id" class="col-md-2 col-form-label">{{ __('Department') }}</label>
            <div class="col-md-10">
                <select name="department_id" id="department_id" class="form-control">
                    <option value="">-- {{ __('Select Operating Department (Optional)') }} --</option>
                    @if(isset($departments))
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ (old('department_id', $productCat->department_id)) == $dept->id ? 'selected' : '' }}>
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
                {!! Form::textarea("category_description", $productCat->category_description, ['class' =>
                'form-control',
                'rows' => 4,'name' => 'category_description', 'id' => 'category_description', 'placeholder' =>
                'Category Description']) !!}
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent border-success">
        <div class="form-group row">

            <div class="col-md-6"><a href="{{ route('product-category.index') }}" class="btn btn-success"> <i
                        class="fa fa-close"></i> Back</a></div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary float-right"> <i class="fa fa-send"></i>
                    Update</button></div>
        </div>
    </div>
    </form>
</div>
@endsection
