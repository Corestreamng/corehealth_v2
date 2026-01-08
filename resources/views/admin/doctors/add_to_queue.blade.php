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

                            <div class="form-group col-md-12">
                                <div class="table-responsive" style="display:none" id="showMe">
                                    <table id="patient_table_id" class="table table-sm table-responsive table-striped">
                                        <thead>
                                            <tr>
                                                <th>SN</th>
                                                <th>File Number:</th>
                                                <th>Fullname</th>
                                                <th>Date of Birth</th>
                                                <th>Blood Group</th>
                                                <th>Genotype</th>
                                                <th>HMO/Insurance</th>
                                                <th>HMO Number</th>
                                                {{-- <th>A/C Bal.</th> --}}
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

    <!-- Modal form to edit a user -->
    <div id="editModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send To Service Queue</h5>
                    <button type="button" class="close" dal" aria-label="Close">
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
                        dal">
                        <span class='glyphicon glyphicon-check'></span> Continue
                    </button>
                    <button type="button" class="btn btn-warning" dal">
                        <span class='glyphicon glyphicon-remove'></span> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <!-- DataTables -->
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <!-- jQuery -->
    <script src="{{ asset('plugins/datatables/jquery-3.3.1.js') }}"></script>
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- DataTables -->
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    {{-- <script src="{{ asset('plugins/datatables/dataTables.select.min.js') }}"></script>
     <script src="{{ asset('plugins/datatables/dataTables.buttons.min.js') }}"></script> --}}
    <script>
        // jQuery.noConflict();
        jQuery(function($) {
            var table = $('#patient_table_id').DataTable({
                "initComplete": function(settings, json) {
                    $('div.loading').remove();
                },
                dom: 'Bfrtip',
                select: true,
                processing: true,
                serverSide: true,
                ajax: {
                    "url": "{{ route('listReturningPatients') }}",
                    "type": "GET",
                    "data": function(data) {
                        data.q = $('#q').val();

                    }
                },
                columnDefs: [{
                    orderable: true,
                    //className: 'select-checkbox',
                    data: null,
                    defaultContent: '',
                    targets: 0
                }],
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        'visible': false
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
                        data: 'dob',
                        name: 'dob'
                    },
                    {
                        data: 'blood_group',
                        name: 'blood_group'
                    },
                    {
                        data: 'genotype',
                        name: 'genotype'
                    },
                    {
                        data: 'hmo',
                        name: 'hmo'
                    },
                    {
                        data: 'hmo_no',
                        name: 'hmo_no'
                    },
                    // {
                    //     data: 'acc_bal',
                    //     name: 'acc_bal'
                    // },
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
                {{-- select: {
            style:    'os',
            selector: 'td:first-child'
        }, --}}
                responsive: true,
                order: [
                    [1, 'asc']
                ],
                paging: true,
                lengthChange: false,
                searchable: false,
                "info": true,
                "autoWidth": false,
                buttons: [
                    'selected',
                    'selectedSingle',
                    'selectAll',
                    'selectNone',
                    'selectRows',
                    //'selectColumns',
                    //'selectCells'
                    {{-- {
                extend: 'selected', // Bind to Selected row
                text: 'Edit',
                name: 'edit'        // do not change name
            }, --}}
                    {{-- { extend: "edit",   editor: editor }, --}}
                ]

            });

            $("#patients_search_form").on('submit', function(e) {
                e.preventDefault();
                $('#showMe').show();
                $('#patient_table_id').DataTable().draw(true);
            });
        });
    </script>
@endsection
