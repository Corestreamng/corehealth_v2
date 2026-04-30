@extends('admin.layouts.app')
@section('title', 'All Encounters')
@section('page_name', 'Encounters')
@section('subpage_name', 'All Encounters')
@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; }
        .filter-bar { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
    </style>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-stethoscope text-primary"></i> All Encounters
                    </h2>
                    <p class="text-muted mb-0">Browse and review clinical encounter records</p>
                </div>
            </div>

            <div class="card-body">
                {{-- Filter Bar --}}
                <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                    <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-calendar-range mr-1"></i> Date Range:</label>
                    <input type="date" id="start_date" class="form-control form-control-sm form-control-modern" style="max-width:160px;"
                        value="{{ date('Y-m-d', strtotime('-1 day')) }}">
                    <span class="text-muted">to</span>
                    <input type="date" id="end_date" class="form-control form-control-sm form-control-modern" style="max-width:160px;"
                        value="{{ date('Y-m-d') }}">
                    <button id="fetchData" class="btn btn-primary btn-sm ml-2">
                        <i class="mdi mdi-magnify"></i> Fetch
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="all_prev_consult_list" class="table table-sm table-bordered table-striped" style="width:100%">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>HMO / Insurance</th>
                                <th>Clinic</th>
                                <th>Doctor</th>
                                <th>Date / Time</th>
                                <th>Action</th>
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
        $(function () {
            var table = $('#all_prev_consult_list').DataTable({
                dom: 'Bfrtip',
                iDisplayLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ url("AllprevEncounterList") }}',
                    type: 'GET',
                    data: function (d) {
                        d.start_date = $('#start_date').val();
                        d.end_date   = $('#end_date').val();
                    }
                },
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    {
                        data: 'fullname', name: 'fullname', searchable: true,
                        render: function (data, type, row) {
                            if (row.patient_link) {
                                return '<a href="' + row.patient_link + '">' + data + '</a>'
                                     + (row.file_no && row.file_no !== 'N/A' ? ' <small class="text-muted">[' + row.file_no + ']</small>' : '');
                            }
                            return data;
                        }
                    },
                    { data: 'hmo_id',     name: 'hmo_id' },
                    { data: 'clinic_id',  name: 'clinic_id' },
                    { data: 'doctor_id',  name: 'doctor_id' },
                    { data: 'created_at', name: 'created_at' },
                    { data: 'view',       name: 'view', orderable: false, searchable: false }
                ],
                paging: true
            });

            $('#fetchData').on('click', function () {
                table.ajax.reload();
            });
        });
    </script>
@endsection


