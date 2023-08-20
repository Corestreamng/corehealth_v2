@extends('admin.layouts.app')
@section('title', 'Service Management')
@section('page_name', 'Service Management')
@section('subpage_name', 'Service Report')
@section('content')

    <div id="content-wrapper">

        <div class="container">
            <button onclick="printDiv('resul')">Print Div Content</button>
            <div class="card" id="resul">
                <div class="card-header">
                    <h4 class="card-title">Service Name</h4>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="products-issue" class="table table-sm table-responsive table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SN</th>
                                    <th>Product</th>
                                    <th>SIV Number</th>
                                    <th>Client</th>
                                    <th>Qty</th>
                                    <th>Issued Price</th>
                                    <th>Total Amount</th>
                                    <th> Date</th>
                                    <th>Budget Year</th>
                                    <th>Store</th>
                                    <th>Voucher</th>
                                </tr>
                        </table>
                    </div>
                </div>
                <!-- </div> -->
            </div>
        </div>



    @endsection
    @section('scripts')
        <script>
            function printDiv(id) {
                var content = document.getElementById(id).innerHTML;
                var popupWindow = window.open('', '_blank', 'width=600,height=600');
                popupWindow.document.open();
                popupWindow.document.write('<html><head><title>' + document.title + '</title>');
                // Reference the external stylesheet from the main page
                popupWindow.document.write('<link rel="stylesheet" type="text/css" href="' + window.location.href + '">');
                popupWindow.document.write('</head><body>');
                popupWindow.document.write(content);
                popupWindow.document.write('</body></html>');
                popupWindow.document.close();
                popupWindow.print();
            }
        </script>
    @endsection
    <!-- {{-- <script>
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

    </script> --}} -->
