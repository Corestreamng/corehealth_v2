@extends('admin.layouts.app')
@section('title', 'New Bed')
@section('page_name', 'Bed')
@section('subpage_name', 'New Bed')

@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Bed</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => 'beds.store', 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="name" class=" control-label">Name/ Number <i class = "text-danger">*</i> </label>
                        <div class="">
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ old('name') }}" required autofocus placeholder="Example: Private bed 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ward" class=" control-label">Ward <i class = "text-danger">*</i></label>
                        <div class="">
                            <input type="text" class="form-control" id="ward" name="ward"
                                value="{{ old('ward') }}" placeholder="Pediatrics">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="unit" class=" control-label">Unit(Optional)</label>
                        <div class="">
                            <input type="text" class="form-control" id="unit" name="unit"
                                value="{{ old('unit') }}" placeholder="Block 2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="price" class="control-label">Price <i class = "text-danger">*</i></label>
                        <div>
                            <input type="number" class="form-control" id="price" name="price" min = '0' step='0.01'
                                value="{{ old('price') }}" required placeholder="Enter price">
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
                                    <a href="{{ route('beds.index') }}" class="pull-right btn btn-danger"><i
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
    <!-- jQuery -->
    {{-- <script src="{{ asset('plugins/jQuery/jquery.min.js') }}"></script> --}}
    <script src="{{ asset('plugins/select2/select2.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <!-- <script src="{{ asset('plugins/bootstrap/js/bootstrap.min.js') }}"></script> -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- DataTables -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('plugins/datatables/dataTables.bootstrap4.js') }}"></script>
    <script src="{{ asset('plugins/ckeditor/ckeditor.js') }}"></script>

    <script>
        // CKEDITOR.replace('content_edit');
        CKEDITOR.replace('content');
    </script>
    <script>
        $(document).ready(function() {
            $(".select2").select2();
        });
    </script>

@endsection
