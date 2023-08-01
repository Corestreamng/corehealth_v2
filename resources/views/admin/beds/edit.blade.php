@extends('admin.layouts.app')
@section('title', 'Update Bed')
@section('page_name', 'Bed')
@section('subpage_name', 'Update Bed')

@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Update Bed</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => ['beds.update',$bed->id], 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    @method('PUT')
                    <div class="form-group">
                        <label for="name" class=" control-label">Name/ Number <i class = "text-danger">*</i> </label>
                        <div class="">
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ $bed->name ?? old('name') }}" required autofocus placeholder="Example: Private bed 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ward" class=" control-label">Ward <i class = "text-danger">*</i></label>
                        <div class="">
                            <input type="text" class="form-control" id="ward" name="ward"
                                value="{{ $bed->ward ?? old('ward') }}" placeholder="Pediatrics">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="unit" class=" control-label">Unit(Optional)</label>
                        <div class="">
                            <input type="text" class="form-control" id="unit" name="unit"
                                value="{{ $bed->unit ?? old('unit') }}" placeholder="Block 2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="price" class="control-label">Price <i class = "text-danger">*</i></label>
                        <div>
                            <input type="number" class="form-control" id="price" name="price" min = '0' step='0.01'
                                value="{{ $bed->price ?? old('price') }}" required placeholder="Enter price">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-offset-1 col-md-6">
                                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i>
                                        Update</button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="col-md-6">
                                    <a href="{{ route('hmo.index') }}" class="pull-right btn btn-danger"><i
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
