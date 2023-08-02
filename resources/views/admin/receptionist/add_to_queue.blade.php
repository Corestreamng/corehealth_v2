@extends('admin.layouts.app')
@section('title', 'Reception')
@section('page_name', 'Reception')
@section('subpage_name', 'Queue')
@section('content')
    <div class="content-fluid spark-screen">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header with-border">
                        <h3 class="card-title">{{ trans('Returning Patient') }}</h3>
                    </div>
                    <div class="card-body">
                        {{-- <button type="button" id="button" class="btn btn-box-tool"><i class="fa fa-minus"></i> Click Me</button> --}}
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <h5>Search Parameter: <span class="text-danger"></span></h5>
                                <form action="" method="get" id="patients_search_form">
                                    <div class="input-group">
                                        <input type="text" name="q" id="q" required class="form-control"
                                            placeholder="Enter File Number or Name to Search..." required>
                                        <span class="input-group-btn">
                                            <button id="btnFiterSubmitSearch" class="btn m-1 btn-primary"
                                                type="submit">Go!</button>
                                        </span>
                                        <div class="help-block"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <br><br>
                        <hr>

                        <div class="row">

                            <div class="form-group col-12 ">
                                <div class="table-responsive justify-content-center" style="display:none; width: 100%" id="showMe">
                                    <table id="patient_table_id" class="table table-responsive table-striped"
                                        style="width: 100%">
                                        <thead>
                                            <tr>
                                                <th>SN</th>
                                                <th>File Number:</th>
                                                <th>Fullname</th>
                                                <th>HMO/Insurance</th>
                                                <th>HMO Number</th>
                                                <th>A/C Bal.</th>
                                                <th>Phone</th>
                                                {{-- <th>Payment Valid</th> --}}
                                                <th>Manage</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- <!-- Modal form to edit a user -->
    <div id="editModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send To Service Queue</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="form-horizontal" action="{{ route('product-or-service-request.store') }}" method="POST"
                        role="form" id="dependants_form">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <h4 class="text-center">Configure requested service for each patient</h4>
                            <hr>
                            <div class="col-md-10">
                                <input type="hidden" class="form-control" id="id_edit" name="id_edit">
                                <input type="hidden" class="form-control" id="user_id_edit" name="user_id_edit">
                                <input type="hidden" class="form-control" id="file_no_edit" name="file_no_edit">
                                <input type="hidden" class="form-control" id="receptionist_id_edit"
                                    name="receptionist_id_edit">
                                <div class="checkbox">
                                    <div class="form-group" id="dependants_list">

                                    </div>
                                    <p class="errorPaymentValidation text-center alert alert-danger hidden"></p>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button id="myButtonForPaymentValidation" type="button" class="btn btn-primary edit"
                        data-dismiss="modal">
                        <span class='glyphicon glyphicon-check'></span> Continue
                    </button>
                    <button type="button" class="btn btn-warning" data-dismiss="modal">
                        <span class='glyphicon glyphicon-remove'></span> Close
                    </button>
                </div>
            </div>
        </div>
    </div> --}}
@endsection

@section('scripts')
    <!-- DataTables -->
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <!-- DataTables -->

    <script>
        $(function() {
            $('#patient_table_id').DataTable({
                "initComplete": function(settings, json) {
                    $('div.loading').remove();
                },
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "responsive": true,
                "ajax": {
                    "url": "{{ route('listReturningPatients') }}",
                    "type": "GET",
                    "data": function(data) {
                        data.q = $('#q').val() || 'a';

                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                    },
                    {
                        data: 'file_no',
                        name: 'file_no'
                    },
                    {
                        data: 'user_id',
                        name: 'user_id'
                    },
                    {
                        data: 'hmo',
                        name: 'hmo'
                    },
                    {
                        data: 'hmo_no',
                        name: 'hmo_no'
                    },
                    {
                        data: 'acc_bal',
                        name: 'acc_bal'
                    },
                    {
                        data: 'phone',
                        data: 'phone'
                    },
                    // { data: 'payment_validation', name: 'payment_validation' },
                    {
                        data: 'process',
                        name: 'process'
                    }

                ],

                "paging": true
            });
        });
        $("#patients_search_form").on('submit', function(e) {
            e.preventDefault();
            $('#showMe').show();
            $('#patient_table_id').DataTable().draw(true);
        });
    </script>
@endsection
