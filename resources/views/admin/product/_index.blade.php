@extends('admin.layouts.admin')

@section('main-content')

	<div id="content-wrapper">
		
		<div class="content-header">
		  <div class="container-fluid">

		    <div class="row mb-2">

		      <div class="col-sm-6">
		        <h1 class="m-0 text-dark">Product Setting List </h1>
		      </div>

		      <div class="col-sm-6">
		        <ol class="breadcrumb float-sm-right">
		          <li class="breadcrumb-item"><a href="#">Home</a></li>
		          <li class="breadcrumb-item active">Product Setting List</li>
		        </ol>
		      </div>

		    </div>
		    
		  </div>
		</div>

		<div class="container">

		    {{-- @include('admin.layouts.partials.infoBox') --}}
		    
		    <div class="card">
	            <div class="card-header">
	              <h3 class="card-title">Data Table With Full Features</h3>
	            </div>
	            <!-- /.card-header -->
	            <div class="card-body">
	              <table id="products" class="table table-sm table-responsive table-bordered table-striped">
	                <thead>
	                <tr>
	                  <th>#</th>
	                  <th>Product</th>
	                  <th>Reorder Alert</th>
	                  <th>Halve</th>
	                  <th>Piece</th>
	                  <th>Quantity</th>
	                  <th>Visible</th>
	                  <th> Price Settings</th>
	                  <th>Edit</th>
	                </tr>
	                </thead>
	                <tbody>
	                <tr>
	                 <?php  $sn = 1; ?>
	                @foreach($data as $datas)
	                 <th>{{$sn}}</th>
	                  <th>{!! $datas->product_name !!}</th>
	                  <th>{!! $datas->reorder_alert !!}</th>
	                  <th>@if($datas->has_have == 1)<a class="btn btn-success btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> Yes</a>
	                  @else<a class="btn btn-danger btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> No</a>
	                  @endif </th>
	              
	                  <th>@if($datas->has_piece == 1)<a class="btn btn-success btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> Yes</a>
	                  @else<a class="btn btn-danger btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> No</a>
	                  @endif </th>

	                  <th>{!! $datas->howmany_to !!}</th>
	                  <th> @if($datas->visible == 1)<a class="btn btn-success btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> Yes</a>
	                  @else<a class="btn btn-danger btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href=""> NO</a>
	                  @endif </th>

                      @if($datas->price_assign == 1)
	                  <th><a class="btn btn-primary btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href="{{ route('prices.edit',$datas->id) }}"><i class="fa fa-pencil"></i> Ajust Price</a> 
                      </th>
                      @else <th> <a class="btn btn-warning btn-xs " data-toggle="tooltip" data-placement="top" title="edit" d href="{{ route('prices.show',$datas->id) }}"><i class="fa fa-plus"></i> Add Price</a> 
                      </th>
                      @endif

                      <th><a class="btn btn-primary btn-xs" data-toggle="tooltip" data-placement="top" title="edit" href="{{ route('products.edit',$datas->id) }}"><i class="fa fa-pencil"></i> Edit</a> 
                      </th>
	                </tr>
	                    <?php  $sn ++ ; ?>
	                   @endforeach
	                </tbody>
	               
	              </table>
	            </div>
	            <!-- /.card-body -->
	          </div>
	          <!-- /.card -->

			</div>

	</div>

@endsection
{{-- <script>
            ( function($) {
                $(function() {
                    $('#product').DataTable({
                        dom: 'Bfrtip',
                        buttons: [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
                        processing: true,
                        serverSide: true,
                        ajax: '{!! route('products.data') !!}',
                        columns: [
                            { data: 'id', name: 'id' },
                            { data: 'tin', name: 'tin' },
                            { data: 'surname', name: 'surname' },
                            { data: 'firstname', name: 'firstname' },
                            { data: 'phone_no', name: 'phone_no' },
                            { data: 'email', name: 'email' },
                            { data: 'visible', name: 'visible' },
                            { data: 'status', name: 'status' },
                            { data: 'created_at', name: 'created_at' },
                            { data: 'action', name: 'action' }
                        ]
                    });
                });
            }) ( jQuery );
        
    </script> --}}