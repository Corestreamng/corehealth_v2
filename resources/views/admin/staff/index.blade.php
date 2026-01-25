@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page_name', 'Staff Management')
@section('subpage_name', 'All Staff')

@section('style')
<style>
    .staff-table th {
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6b7280;
        background: #f9fafb;
    }
    .staff-table td {
        vertical-align: middle !important;
    }
    .btn-group .btn {
        padding: 0.35rem 0.65rem;
    }
    .badge {
        font-weight: 500;
        padding: 0.35rem 0.65rem;
    }
</style>
@endsection

@section('content')
    <section class="content">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title-modern mb-1">
                            <i class="mdi mdi-account-group text-primary mr-2"></i>Staff Directory
                        </h5>
                        <small class="text-muted">Manage all staff members and their information</small>
                    </div>
                    <a href="{{ route('staff.create') }}" class="btn btn-primary" style="border-radius: 8px;">
                        <i class="mdi mdi-plus mr-1"></i> Add New Staff
                    </a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table id="staffTable" class="table table-hover staff-table" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th style="width: 50px;">Photo</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Job Title</th>
                                    <th>Category</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Role</th>
                                    <th style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            $('#staffTable').DataTable({
                "dom": '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
                "iDisplayLength": 25,
                "lengthMenu": [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "All"]
                ],
                "buttons": [
                    {
                        extend: 'pageLength',
                        className: 'btn btn-sm btn-outline-secondary'
                    },
                    {
                        extend: 'excel',
                        className: 'btn btn-sm btn-outline-success',
                        text: '<i class="mdi mdi-file-excel"></i> Excel',
                        exportOptions: {
                            columns: [0, 2, 3, 4, 5, 6, 7, 8]
                        }
                    },
                    {
                        extend: 'pdf',
                        className: 'btn btn-sm btn-outline-danger',
                        text: '<i class="mdi mdi-file-pdf"></i> PDF',
                        exportOptions: {
                            columns: [0, 2, 3, 4, 5, 6, 7, 8]
                        }
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-sm btn-outline-info',
                        text: '<i class="mdi mdi-printer"></i> Print',
                        exportOptions: {
                            columns: [0, 2, 3, 4, 5, 6, 7, 8]
                        }
                    }
                ],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('listStaff') }}",
                    "type": "GET"
                },
                "columns": [
                    { "data": "DT_RowIndex", "orderable": false, "searchable": false },
                    { "data": "filename", "orderable": false, "searchable": false },
                    { "data": "full_name" },
                    { "data": "department" },
                    { "data": "job_title" },
                    { "data": "is_admin" },
                    { "data": "phone" },
                    { "data": "employment_status" },
                    { "data": "leadership_role", "orderable": false },
                    { "data": "actions", "orderable": false, "searchable": false }
                ],
                "order": [[2, 'asc']],
                "language": {
                    "processing": '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Loading...',
                    "emptyTable": "No staff members found",
                    "zeroRecords": "No matching staff found"
                }
            });
        });
    </script>
@endsection
