@extends('admin.layouts.app')
@section('title', 'Services')
@section('page_name', 'Services')
@section('subpage_name', isset($categoryName) ? $categoryName . ' Services' : 'All Services')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .filter-bar { background: #f8f9fa; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .filter-bar select { max-width: 180px; display: inline-block; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/modern-forms.css') }}">
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card-modern">
            <div class="card-header-modern d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 font-weight-bold text-dark">
                        <i class="mdi mdi-medical-bag text-primary"></i> {{ isset($categoryName) ? $categoryName . ' Services' : 'Services' }}
                    </h2>
                    <p class="text-muted mb-0">Manage hospital services, pricing and result templates</p>
                </div>
                <a href="{{ route('services.create', ['category' => $filterCategory]) }}" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-plus"></i> Add Service
                </a>
            </div>

            <div class="card-body">
                {{-- Filter Bar --}}
                <div class="filter-bar d-flex align-items-center gap-2 flex-wrap">
                    <label class="mb-0 mr-2 font-weight-bold"><i class="mdi mdi-filter-outline"></i> Filters:</label>
                    <select id="filter-category" class="form-control form-control-sm form-control-modern">
                        <option value="all">All Categories</option>
                        @foreach ($categories as $catId => $catName)
                            <option value="{{ $catId }}" {{ (isset($filterCategory) && $filterCategory == $catId) ? 'selected' : '' }}>{{ $catName }}</option>
                        @endforeach
                    </select>

                    <div class="custom-control custom-switch ml-3">
                        <input type="checkbox" class="custom-control-input" id="show-deactivated">
                        <label class="custom-control-label" for="show-deactivated">Show Deactivated</label>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="services-list" class="table table-sm table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivation Confirmation Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Confirm Status Change</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to <span id="status-action-text" class="font-weight-bold text-lowercase"></span> the service "<span id="status-item-name" class="font-weight-bold"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-status-btn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('/plugins/dataT/datatables.js') }}" defer></script>
    <script>
        $(function() {
            var table = $('#services-list').DataTable({
                "dom": 'Bfrtip',
                "iDisplayLength": 50,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "buttons": ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "{{ route('services-list') }}",
                    "type": "GET",
                    "data": function(d) {
                        d.category = $('#filter-category').val();
                        d.show_deactivated = $('#show-deactivated').is(':checked');
                    }
                },
                "columns": [
                    { data: "DT_RowIndex", name: "DT_RowIndex", orderable: false, searchable: false },
                    { data: "service_info", name: "service_name" },
                    { data: "price_info", name: "price_info", orderable: false },
                    { data: "actions", name: "actions", orderable: false, searchable: false }
                ],
                "paging": true
            });

            // Filter change reloads table
            $('#filter-category, #show-deactivated').on('change', function() {
                table.ajax.reload();
            });

            let currentServiceId = null;

            window.toggleServiceStatus = function(id, action, name) {
                currentServiceId = id;
                $('#status-action-text').text(action);
                $('#status-item-name').text(name);
                $('#statusModal').modal('show');
            };

            $('#confirm-status-btn').click(function() {
                if (!currentServiceId) return;
                
                $(this).prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Processing...');
                
                $.ajax({
                    url: `/services/${currentServiceId}/toggle-status`,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        $('#statusModal').modal('hide');
                        if (response.success) {
                            toastr.success(response.message);
                            table.ajax.reload(null, false);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function(xhr) {
                        $('#statusModal').modal('hide');
                        toastr.error('An error occurred while updating status.');
                    },
                    complete: function() {
                        $('#confirm-status-btn').prop('disabled', false).text('Confirm');
                    }
                });
            });
        });
    </script>
@endsection
