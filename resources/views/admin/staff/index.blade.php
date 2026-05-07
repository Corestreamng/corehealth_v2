@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page_name', 'Staff Management')
@section('subpage_name', 'All Staff')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .filter-bar { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .filter-bar select { max-width: 180px; display: inline-block; }
        .staff-table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            background: #f9fafb;
        }
        .staff-table td { vertical-align: middle !important; }
        .btn-group .btn { padding: 0.35rem 0.65rem; }
        .badge { font-weight: 500; padding: 0.35rem 0.65rem; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-account-group text-primary"></i> Staff Directory
                    </h2>
                    <p class="text-muted mb-0">Manage all staff members and their information</p>
                </div>
                <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-plus"></i> Add New Staff
                </a>
            </div>

            <div class="card-body">
                {{-- Filter Bar --}}
                <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                    <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filters:</label>
                    <select id="filter-department" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Departments</option>
                        @foreach ($departments as $deptId => $deptName)
                            <option value="{{ $deptId }}">{{ $deptName }}</option>
                        @endforeach
                    </select>
                    <select id="filter-category" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Categories</option>
                        @foreach ($statuses as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select id="filter-status" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="resigned">Resigned</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table id="staffTable" class="table table-sm table-bordered table-striped staff-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Staff</th>
                                <th>Department</th>
                                <th>Contact</th>
                                <th>Category & Roles</th>
                                <th>Status</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            var table = $('#staffTable').DataTable({
                "dom": '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
                "iDisplayLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "buttons": [
                    { extend: 'pageLength', className: 'btn btn-sm btn-outline-secondary' },
                    { extend: 'excel', className: 'btn btn-sm btn-outline-success', text: '<i class="mdi mdi-file-excel"></i> Excel', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                    { extend: 'pdf', className: 'btn btn-sm btn-outline-danger', text: '<i class="mdi mdi-file-pdf"></i> PDF', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                    { extend: 'print', className: 'btn btn-sm btn-outline-info', text: '<i class="mdi mdi-printer"></i> Print', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } }
                ],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('listStaff') }}",
                    "type": "GET",
                    "data": function(d) {
                        d.department_id = $('#filter-department').val();
                        d.category_id = $('#filter-category').val();
                        d.employment_status = $('#filter-status').val();
                    }
                },
                "columns": [
                    { "data": "DT_RowIndex", "orderable": false, "searchable": false },
                    { "data": "staff_info" },
                    { "data": "dept_info", "orderable": false },
                    { "data": "contact" },
                    { "data": "category_roles", "orderable": false },
                    { "data": "status_info", "orderable": false },
                    { "data": "actions", "orderable": false, "searchable": false }
                ],
                "language": {
                    "processing": '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Loading...',
                    "emptyTable": "No staff members found",
                    "zeroRecords": "No matching staff found"
                }
            });

            // Filter change reloads table
            $('#filter-department, #filter-category, #filter-status').on('change', function() {
                table.ajax.reload();
            });
        });
    </script>
@endsection
