@extends('admin.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">MySQL Slow Query Monitor</h4>
                <div>
                    <button class="btn btn-outline-primary mr-2" id="btn-configure">
                        <i class="mdi mdi-cog mr-1"></i> Configure MySQL
                    </button>
                    <button class="btn btn-primary" id="btn-refresh">
                        <i class="mdi mdi-refresh mr-1"></i> Parse Log Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Status</h4>
                    <h2 class="mb-0" id="stat-enabled">
                        @if($config && $config['enabled'])
                            <span class="badge badge-success">Enabled</span>
                        @else
                            <span class="badge badge-danger">Disabled</span>
                        @endif
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Threshold</h4>
                    <h2 class="mb-0 text-primary" id="stat-threshold">{{ $config['threshold'] ?? 'N/A' }}s</h2>
                </div>
            </div>
        </div>
        <div class="col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Log File Path</h4>
                    <p class="mb-0 text-muted overflow-hidden text-truncate" id="stat-log-path" title="{{ $config['log_file'] ?? 'Not set' }}">
                        <code>{{ $config['log_file'] ?? 'Not set' }}</code>
                    </p>
                    @if($appSettings->slow_query_log_path && $appSettings->slow_query_log_path !== ($config['log_file'] ?? ''))
                        <small class="text-warning">App tracking: {{ $appSettings->slow_query_log_path }}</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Advanced Filters</h4>
                    <form id="filter-form" class="row align-items-end mb-4">
                        <div class="col-md-2">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label>Min Duration (s)</label>
                            <input type="number" name="min_duration" step="0.1" class="form-control form-control-sm" value="2">
                        </div>
                        <div class="col-md-2">
                            <label>Min Rows Examined</label>
                            <input type="number" name="min_rows" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label>Search Query</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="SQL keyword...">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-info btn-sm btn-block">Filter</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover" id="slow-queries-table" width="100%">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Duration</th>
                                    <th>Lock</th>
                                    <th>Examined</th>
                                    <th>Sent</th>
                                    <th>Query</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    <div id="pagination-container" class="mt-4"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration Modal -->
<div class="modal fade" id="configModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">MySQL Slow Query Configuration</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <form id="config-form">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Long Query Time (seconds)</label>
                        <input type="number" name="threshold" step="0.1" class="form-control" value="{{ $config['threshold'] ?? 2 }}" required>
                        <small class="text-muted">Queries taking longer than this will be logged.</small>
                    </div>
                    <div class="form-group">
                        <label>Custom Log Path (Optional)</label>
                        <input type="text" name="custom_path" class="form-control" value="{{ $appSettings->slow_query_log_path }}" placeholder="/var/log/mysql/mysql-slow.log">
                        <small class="text-muted">Leave empty to use MySQL's current setting. Ensure PHP has read permission.</small>
                    </div>
                    <div class="alert alert-info py-2">
                        <small><i class="mdi mdi-information-outline mr-1"></i> This requires SUPER or SYSTEM_VARIABLES_ADMIN privileges on the database.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Query Detail Modal -->
<div class="modal fade" id="queryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Query Detail</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Timestamp:</strong> <span id="modal-time"></span></div>
                    <div class="col-md-4"><strong>Duration:</strong> <span id="modal-duration"></span></div>
                    <div class="col-md-4"><strong>User/Host:</strong> <span id="modal-user"></span></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Lock Time:</strong> <span id="modal-lock"></span></div>
                    <div class="col-md-4"><strong>Rows Examined:</strong> <span id="modal-examined"></span></div>
                    <div class="col-md-4"><strong>Rows Sent:</strong> <span id="modal-sent"></span></div>
                </div>
                <label><strong>SQL Query:</strong></label>
                <div class="p-3 bg-light rounded border">
                    <pre id="modal-query" class="mb-0" style="white-space: pre-wrap; word-break: break-all; font-family: 'Courier New', Courier, monospace;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    let currentPage = 1;

    function loadQueries(page = 1) {
        currentPage = page;
        const formData = $('#filter-form').serialize();
        
        $.get(`{{ route('admin.slow_queries.index') }}?page=${page}&${formData}`, function(res) {
            const tbody = $('#slow-queries-table tbody');
            tbody.empty();

            if (res.queries.data.length === 0) {
                tbody.append('<tr><td colspan="6" class="text-center text-muted">No slow queries found.</td></tr>');
            } else {
                res.queries.data.forEach(function(q) {
                    const durationClass = q.query_time > 10 ? 'text-danger font-weight-bold' : (q.query_time > 5 ? 'text-warning' : '');
                    const rowClass = q.rows_examined > 100000 ? 'table-warning' : '';
                    
                    tbody.append(`
                        <tr class="${rowClass} pointer" onclick="showQuery(${JSON.stringify(q).replace(/"/g, '&quot;')})">
                            <td>${new Date(q.timestamp).toLocaleString()}</td>
                            <td class="${durationClass}">${q.query_time}s</td>
                            <td>${q.lock_time}s</td>
                            <td>${q.rows_examined.toLocaleString()}</td>
                            <td>${q.rows_sent.toLocaleString()}</td>
                            <td class="text-truncate" style="max-width: 300px;"><code>${q.query.substring(0, 100)}...</code></td>
                        </tr>
                    `);
                });
            }

            renderPagination(res.queries);
        });
    }

    function renderPagination(data) {
        const container = $('#pagination-container');
        container.empty();
        
        if (data.last_page <= 1) return;

        let html = '<ul class="pagination pagination-sm justify-content-center">';
        html += `<li class="page-item ${data.current_page === 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadQueries(${data.current_page - 1})">Prev</a></li>`;
        
        for (let i = 1; i <= data.last_page; i++) {
            if (i === 1 || i === data.last_page || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                html += `<li class="page-item ${data.current_page === i ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadQueries(${i})">${i}</a></li>`;
            } else if (i === data.current_page - 3 || i === data.current_page + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }
        
        html += `<li class="page-item ${data.current_page === data.last_page ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="loadQueries(${data.current_page + 1})">Next</a></li>`;
        html += '</ul>';
        
        container.html(html);
    }

    // Export loadQueries to global scope for pagination links
    window.loadQueries = loadQueries;

    window.showQuery = function(q) {
        $('#modal-time').text(new Date(q.timestamp).toLocaleString());
        $('#modal-duration').text(q.query_time + 's');
        $('#modal-user').text(q.user_host || 'Unknown');
        $('#modal-lock').text(q.lock_time + 's');
        $('#modal-examined').text(q.rows_examined.toLocaleString());
        $('#modal-sent').text(q.rows_sent.toLocaleString());
        $('#modal-query').text(q.query);
        $('#queryModal').modal('show');
    };

    $('#filter-form').submit(function(e) {
        e.preventDefault();
        loadQueries(1);
    });

    $('#btn-refresh').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Parsing...');
        
        $.post('{{ route("admin.slow_queries.refresh") }}', { _token: '{{ csrf_token() }}' }, function(res) {
            toastr.success(res.message);
            loadQueries(1);
        }).always(function() {
            btn.prop('disabled', false).html('<i class="mdi mdi-refresh mr-1"></i> Parse Log Now');
        });
    });

    $('#btn-configure').click(function() {
        $('#configModal').modal('show');
    });

    $('#config-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Applying...');

        $.ajax({
            url: '{{ route("admin.slow_queries.setup") }}',
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    location.reload();
                } else {
                    toastr.error(res.message);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error occurred');
            },
            complete: function() {
                btn.prop('disabled', false).text('Apply Settings');
            }
        });
    });

    // Initial load
    loadQueries(1);
});
</script>

<style>
.pointer { cursor: pointer; }
#slow-queries-table tbody tr:hover { background-color: rgba(0,0,0,0.05); }
.text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endsection
