@extends('admin.layouts.app')
@section('title', 'New HMO')
@section('page_name', 'HMO')
@section('subpage_name', 'New HMO')

@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create HMO</h3>
                </div>
                <!-- /.card-header -->
                <div class="card-body">
                    {!! Form::open(['method' => 'POST', 'route' => 'hmo.store', 'class' => 'form-horizontal', 'role' => 'form']) !!}
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label for="name" class=" control-label">Name(Scheme/HMO name) <i class = "text-danger">*</i> </label>
                        <div class="">
                            <input type="text" class="form-control" id="name" name="name"
                                value="{{ old('name') }}" required autofocus placeholder="Example: NHIS/United">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description" class=" control-label">Description</label>
                        <div class="">
                            <input type="text" class="form-control" id="description" name="description"
                                value="{{ old('description') }}" placeholder="Enter description">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="discount" class="control-label">Discount(%) <i class = "text-danger">*</i></label>
                        <div>
                            <input type="number" class="form-control" id="discount" name="discount" min = '0' max='100' step='0.01'
                                value="{{ old('discount') }}" required placeholder="Enter discount">
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
