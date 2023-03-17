@extends('admin.layouts.app')
@section('title', 'Hompage')
@section('page_name', 'Home')
@section('content')
    <div class="row">
        <div class="col-sm-6 stretch-card grid-margin">
            <table class="table table-bordered details">
                <thead>
                  <tr>
                    <th scope="col">id</th>
                    <th scope="col">Name</th>
                    <th scope="col">price</th>
                    <th scope="col">Action</th>
                  </tr>
                </thead>
                <tbody>

                </tbody>
              </table>

    </div>
@endsection
@section('scripts')
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.js" ></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.0/jquery.validate.js" ></script>
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js" ></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" ></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js" ></script>


<script type="text/javascript">
    $(function () {

      var table = $('.details').DataTable({
          processing: true,
          serverSide: true,
          ajax: "{{ route('details') }}",
          columns: [
              {data: 'DT_RowIndex', name: 'DT_RowIndex'},
              {data: 'id', name: 'id'},
              {data: 'service_rendered', name: 'service_rendered'},
              {data: 'price', name: 'price'},
              {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},.
          ]
      });
    });
  </script>
  @endsection


