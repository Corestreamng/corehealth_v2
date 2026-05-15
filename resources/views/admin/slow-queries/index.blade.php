@extends('admin.layouts.app')

@section('style')
    @php $primaryColor = appsettings()->hos_color ?? '#011b33'; @endphp
    <style>
        :root { --primary-color: {{ $primaryColor }}; --primary-light: {{ $primaryColor }}15; }
        .card-stat { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-5px); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .filter-bar { background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #eee; }
        .pointer { cursor: pointer; }
        .bg-soft-primary { background-color: #e3f2fd; }
        .bg-soft-warning { background-color: #fff3e0; }
        .bg-soft-danger { background-color: #ffebee; }
        .bg-soft-success { background-color: #e8f5e9; }
    </style>
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-soft-primary text-primary mr-3">
                        <i class="mdi mdi-database-search"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small font-weight-bold">Total Slow Queries</h6>
                        <h3 class="mb-0 font-weight-bold">{{ number_format($stats['total_count']) }}</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-soft-warning text-warning mr-3">
                        <i class="mdi mdi-timer-outline"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small font-weight-bold">Avg Execution Time</h6>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['avg_time'] }}s</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-soft-danger text-danger mr-3">
                        <i class="mdi mdi-alert-circle-outline"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small font-weight-bold">Slowest Query</h6>
                        <h3 class="mb-0 font-weight-bold">{{ $stats['max_time'] }}s</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-stat bg-white h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-soft-success text-success mr-3">
                        <i class="mdi mdi-xml"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1 text-uppercase small font-weight-bold">Top Source</h6>
                        @php 
                            $topSource = optional($stats['top_source'])->source ?? 'N/A';
                            $displaySource = $topSource !== 'N/A' ? (str_contains($topSource, '@') ? explode('@', $topSource)[1] : $topSource) : 'N/A';
                        @endphp
                        <h5 class="mb-0 font-weight-bold text-truncate" style="max-width: 150px;" title="{{ $topSource }}">
                            {{ $displaySource }}
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-modern shadow-sm border-0">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 font-weight-bold"><i class="mdi mdi-database-clock text-primary mr-2"></i> MySQL Slow Query Monitor</h4>
                <p class="text-muted small mb-0">Identify and optimize expensive database operations across your application.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info btn-sm mr-2" id="btn-guide">
                    <i class="mdi mdi-help-circle-outline"></i> Setup Guide
                </button>
                <button class="btn btn-outline-primary btn-sm mr-2" id="btn-configure">
                    <i class="mdi mdi-cog-outline"></i> Configure MySQL
                </button>
                <button class="btn btn-success btn-sm" id="btn-refresh">
                    <i class="mdi mdi-sync"></i> Parse Log Now
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="alert alert-info py-2 small mb-4">
                <i class="mdi mdi-information-outline mr-1"></i> 
                <strong>Current Configuration:</strong> 
                Slow Log: <span class="badge {{ ($config['enabled'] ?? false) ? 'badge-success' : 'badge-danger' }}">{{ ($config['enabled'] ?? false) ? 'ON' : 'OFF' }}</span> | 
                Threshold: <span class="badge badge-light text-dark">{{ $config['threshold'] ?? 'N/A' }}s</span> | 
                Log File: <code>{{ $config['log_file'] ?? 'Not set' }}</code> |
                Last Background Check: <span class="text-primary font-weight-bold">{{ $appSettings->last_slow_query_check ? \Carbon\Carbon::parse($appSettings->last_slow_query_check)->diffForHumans() : 'Never' }}</span>
            </div>

            {{-- Modern Filter Bar --}}
            <div class="filter-bar">
                <form id="filter-form" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small font-weight-bold">Start Date</label>
                        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small font-weight-bold">End Date</label>
                        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small font-weight-bold">Source Filter</label>
                        <select name="source_filter" id="source_filter" class="form-control form-control-sm select2">
                            <option value="all">All Sources</option>
                            @foreach($sources as $source)
                                <option value="{{ $source }}">{{ $source }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small font-weight-bold">Min Time (s)</label>
                        <input type="number" name="min_duration" id="min_duration" step="0.1" value="2" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small font-weight-bold">Min Rows</label>
                        <input type="number" name="min_rows" id="min_rows" value="0" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small font-weight-bold">Search SQL</label>
                        <input type="text" name="search_query" id="search_query" class="form-control form-control-sm" placeholder="Keyword...">
                    </div>
                    <div class="col-md-1">
                        <button type="button" id="btn-apply-filters" class="btn btn-primary btn-sm btn-block">
                            <i class="mdi mdi-filter-variant"></i> Apply
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-responsive mt-2">
                <table class="table table-hover table-striped" id="slow-queries-table" width="100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Duration</th>
                            <th>Lock</th>
                            <th>Examined</th>
                            <th>Source</th>
                            <th>Query Preview</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via DataTables -->
                    </tbody>
                </table>
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
                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Long Query Time (seconds)</label>
                        <input type="number" name="threshold" step="0.1" class="form-control" value="{{ $config['threshold'] ?? 2 }}" required>
                        <small class="text-muted">Queries taking longer than this will be logged.</small>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label font-weight-bold">Custom Log Path (Optional)</label>
                        <input type="text" name="custom_path" class="form-control" value="{{ $appSettings->slow_query_log_path }}" placeholder="/var/log/mysql/mysql-slow.log">
                        <small class="text-muted">Leave empty to use MySQL's current setting. Ensure PHP has read permission.</small>
                    </div>
                    <div class="alert alert-warning py-2">
                        <small><i class="mdi mdi-information-outline mr-1"></i> This requires SUPER or SYSTEM_VARIABLES_ADMIN privileges on the database.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Apply Settings</button>
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
                <div class="row mb-3">
                    <div class="col-md-12"><strong>Source Code:</strong> <code id="modal-source" class="text-info"></code></div>
                </div>
                <label class="font-weight-bold">SQL Query:</label>
                <div class="p-3 bg-light rounded border">
                    <pre id="modal-query" class="mb-0" style="white-space: pre-wrap; word-break: break-all; font-family: 'Courier New', Courier, monospace;"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Setup Guide Modal -->
<div class="modal fade" id="guideModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">MySQL Slow Query Setup Guide</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <div class="mb-4">
                    <h6 class="text-primary font-weight-bold"><i class="mdi mdi-magnify mr-1"></i> Phase 0: Discover your current settings</h6>
                    <p>To see where MySQL is currently logging (if at all), run this in your MySQL console:</p>
                    <div class="p-2 bg-light border rounded">
                        <code>SHOW VARIABLES LIKE 'slow_query_log%';</code><br>
                        <code>SHOW VARIABLES LIKE 'long_query_time';</code>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-primary font-weight-bold"><i class="mdi mdi-numeric-1-box mr-1"></i> Phase 1: Option A - Use App Storage (Recommended)</h6>
                    <p>This allows the app to manage the file easily. Run these commands to create and authorize a custom log:</p>
                    @php
                        $customPath = base_path('storage/logs/mysql-slow.log');
                        $appDir = base_path();
                        $storageDir = storage_path();
                        $logsDir = storage_path('logs');
                    @endphp
                    <div class="p-3 bg-dark text-white rounded mb-2">
                        <code>sudo touch {{ $customPath }}</code><br>
                        <code>sudo chown mysql:www-data {{ $customPath }}</code><br>
                        <code>sudo chmod 664 {{ $customPath }}</code>
                    </div>
                    <p class="small text-muted">Ensure MySQL can traverse the path (Parent folders must have +x):</p>
                    <div class="p-2 bg-light border rounded mb-3">
                        <code>sudo chmod o+x {{ $appDir }}</code><br>
                        <code>sudo chmod o+x {{ $storageDir }}</code><br>
                        <code>sudo chmod o+x {{ $logsDir }}</code>
                    </div>
                    
                    <h6 class="text-primary font-weight-bold"><i class="mdi mdi-numeric-1-box mr-1"></i> Phase 1: Option B - Use Default Log Location</h6>
                    <p>If you prefer MySQL's default (usually <code>/var/log/mysql/mysql-slow.log</code>):</p>
                    <div class="p-2 bg-light border rounded">
                        <code>sudo chmod 644 /var/log/mysql/mysql-slow.log</code><br>
                        <small class="text-muted">This allows PHP to read the file, but MySQL already has write access.</small>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-primary font-weight-bold"><i class="mdi mdi-numeric-2-box mr-1"></i> Phase 2: Handle AppArmor (Ubuntu Systems)</h6>
                    <p>If you used <strong>Option A (App Storage)</strong>, you MUST authorize the path in AppArmor:</p>
                    <div class="p-3 bg-light border rounded">
                        <ol class="mb-0">
                            <li>Edit profile: <code>sudo nano /etc/apparmor.d/local/usr.sbin.mysqld</code></li>
                            <li>Add: <code>{{ $customPath }} rw,</code></li>
                            <li>Reload: <code>sudo systemctl reload apparmor</code></li>
                        </ol>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="text-primary font-weight-bold"><i class="mdi mdi-numeric-3-box mr-1"></i> Phase 3: Apply in MySQL</h6>
                    <p>Run these in MySQL to point to your chosen file (Option A or B):</p>
                    <div class="p-3 bg-dark text-white rounded">
                        <code>SET GLOBAL slow_query_log = 'ON';</code><br>
                        <code>SET GLOBAL long_query_time = 2;</code><br>
                        <code>SET GLOBAL slow_query_log_file = '{{ $appSettings->slow_query_log_path ?: $customPath }}';</code>
                    </div>
                    <p class="mt-2 small text-info"><i class="mdi mdi-information-outline"></i> Use the <strong>Configure MySQL</strong> button to do this automatically if you have privileges.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close Guide</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(document).ready(function() {
    let table = $('#slow-queries-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.slow_queries.index') }}",
            data: function(d) {
                d.start_date = $('#start_date').val();
                d.end_date = $('#end_date').val();
                d.source_filter = $('#source_filter').val();
                d.min_duration = $('#min_duration').val();
                d.min_rows = $('#min_rows').val();
                d.search_query = $('#search_query').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'timestamp', name: 'timestamp' },
            { data: 'query_time', name: 'query_time' },
            { data: 'lock_time', name: 'lock_time' },
            { data: 'rows_examined', name: 'rows_examined' },
            { data: 'source_info', name: 'source' },
            { data: 'query', name: 'query' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        drawCallback: function() {
            $('.pointer').css('cursor', 'pointer');
        }
    });

    $('#btn-apply-filters').click(function() {
        table.ajax.reload();
    });

    $('#filter-form input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            table.ajax.reload();
        }
    });

    $('#btn-guide').click(function() {
        $('#guideModal').modal('show');
    });

    $('#btn-configure').click(function() {
        $('#configModal').modal('show');
    });

    $('#config-form').submit(function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Saving...');

        $.post("{{ route('admin.slow_queries.setup') }}", $(this).serialize(), function(res) {
            if (res.success) {
                toastr.success(res.message);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                toastr.error(res.message);
                btn.prop('disabled', false).text('Apply Settings');
            }
        }).fail(function() {
            toastr.error('System error occurred.');
            btn.prop('disabled', false).text('Apply Settings');
        });
    });

    $('#btn-refresh').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i> Parsing...');

        $.post("{{ route('admin.slow_queries.refresh') }}", { _token: '{{ csrf_token() }}' }, function(res) {
            if (res.success) {
                toastr.success(res.message);
                table.ajax.reload();
            } else {
                toastr.error(res.message);
            }
        }).always(() => {
            btn.prop('disabled', false).html('<i class="mdi mdi-sync"></i> Parse Log Now');
        });
    });

    window.showQuery = function(q) {
        if (typeof q === 'string') {
            try {
                q = JSON.parse(q);
            } catch(e) {}
        }
        $('#modal-time').text(q.timestamp);
        $('#modal-duration').text(q.query_time + 's');
        $('#modal-user').text(q.user_host || 'Unknown');
        $('#modal-lock').text(q.lock_time + 's');
        $('#modal-examined').text(q.rows_examined.toLocaleString());
        $('#modal-sent').text(q.rows_sent.toLocaleString());
        $('#modal-source').text(q.source || 'Unknown/Tagging disabled');
        $('#modal-query').text(q.query);
        $('#queryModal').modal('show');
    };
});
</script>
@endsection
