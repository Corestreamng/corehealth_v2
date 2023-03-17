@extends('admin.layouts.admin')

@section('main-content')

<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>Product</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="#">Home</a></li>
          <li class="breadcrumb-item active">Product</li>
        </ol>
      </div>
    </div>
  </div>
</section>

<section class="content">
    <div class="card border-info mb-3">

            <div class="card-header bg-transparent border-info">{{ __('Product') }}</div>
            <div class="card-body">

                <table id="products" class="table table-sm table-responsive table-sm table-responsive table-bordered table-striped">
                    <thead>
                        <tr>
                        <th>SN</th>
                        <th>Product</th>
                        <th>Visible</th>
                        <th>Quantity</th>
                        <th>Qurrent value</th>
                        <th>Total Value</th>
                        <th>Store</th>
                        </tr>
                    </thead>
                </table>


            </div>
            <div class="card-footer bg-transparent border-info">
                <table  class="table table-sm table-responsive table-sm table-responsive table-bordered table-striped">

                        <thead>
                        <tr>
                                <th colspan="8" style="text-align:center">Total Treasure Value:  &#x20A6;{!!number_format( $i,2,'.',',')!!}</th>

                        </tr>
                        </thead>
                </table>
            </div>
    </div>
</section>

@endsection

@section('scripts')
<!-- jQuery -->
<script src="{{ asset('/plugins/dataT/jQuery-3.3.1/jquery-3.3.1.min.js') }}"></script>
<!-- Bootstrap 4 -->
<!-- DataTables -->
<script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>

<script>
  $(function () {
    $('#products').DataTable({
        // "dom": 'Bfrtip',
        "buttons": [ 'copy', 'excel', 'pdf', 'print', 'colvis' ],
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "{{ url('listProductsTreasure') }}",
            "type": "GET"
        },
        "columns": [
             { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "product_name", name: "product_name" },
            { data: "visible", name: "visible" },
            { data: "current_quantity", name: "current_quantity" },
            { data: "value", name: "value" },
            { data: "total_value", name: "total_value" },
            { data: "store", name: "store" }
        ],
        // initComplete: function () {
        //     this.api().columns().every(function () {
        //         var column = this;
        //         var input = document.createElement("input");
        //         $(input).appendTo($(column.footer()).empty())
        //         .on('change', function () {
        //             column.search($(this).val(), false, false, true).draw();
        //         });
        //     });
        // },
      "paging": true
      // "lengthChange": false,
      // "searching": true,
      // "ordering": true,
      // "info": true,
      // "autoWidth": false
    });
  });

</script>
@endsection
