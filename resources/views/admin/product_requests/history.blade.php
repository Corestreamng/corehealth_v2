@extends('admin.layouts.app')
@section('title', 'List Product Requests History')
@section('page_name', 'Products')
@section('subpage_name', 'List Requests History')
@section('content')

    <section class="content">

        <div class="col-12">
            <div class="card">
                {{-- <div class="card-header">
                    <a href="{{ route('hmo.create') }}" class="btn btn-primary">New HMO</a>
                </div> --}}
                <!-- /.card-header -->
                <div class="card-body">
                    {{-- <div class="pull-right">
                        <!-- <a href="{{ route('hmo.create') }}" class="btn btn-primary" >New Role</a> -->
                    </div> --}}
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" style="width: 100%"
                            id="presc_history_list">
                            <thead>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Product</th>
                                <th>Details</th>
                                <th>Action</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </section>



    <!-- Modal form to delete a user -->
    <div id="deleteModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"></h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <h4 class="text-center">Are you sure you want to delete the following user?</h4>
                    <br />
                    <form class="form-horizontal" role="form">
                        <div class="form-group">
                            <div class="col-md-10">
                                <input type="hidden" class="form-control" id="id_delete">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger delete" data-dismiss="modal">
                        <span id="" class='glyphicon glyphicon-trash'></span> Delete
                    </button>
                    <button type="button" class="btn btn-warning" data-dismiss="modal">
                        <span class='glyphicon glyphicon-remove'></span> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            $('#presc_history_list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ url('prescQueueHistoryList') }}",
                    "type": "GET"
                },
                "columns": [{
                        data: "DT_RowIndex",
                        name: "DT_RowIndex"
                    },
                    {
                        data: "patient_id",
                        name: "patient_id"
                    },
                    {
                        data: "dose",
                        name: "dose"
                    },
                    {
                        data: "created_at",
                        name: "created_at"
                    },
                    {
                        data: "select",
                        name: "select"
                    },

                ],

                "paging": true
            });
        });
    </script>

@endsection
