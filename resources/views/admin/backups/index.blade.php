@extends('admin.layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="card-title mb-0">Database Backup Management</h4>
                <div>
                    <button class="btn btn-primary" id="btn-create-backup">
                        <i class="mdi mdi-database-plus mr-2"></i> Create Backup Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Database Size</h4>
                    <h2 class="mb-0 text-primary" id="stat-db-size">Loading...</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Local Backups</h4>
                    <h2 class="mb-0 text-info" id="stat-local-count">0</h2>
                </div>
            </div>
        </div>
        <div class="col-md-4 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Compression Status</h4>
                    <h2 class="mb-0" id="stat-compression">
                        <span class="badge badge-secondary">Unknown</span>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="local-tab" data-bs-toggle="tab" href="#local-backups" role="tab" aria-selected="true">
                                <i class="mdi mdi-harddisk mr-1"></i> Local Backups (Server)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="external-tab" data-bs-toggle="tab" href="#external-backups" role="tab" aria-selected="false">
                                <i class="mdi mdi-usb mr-1"></i> Mounted / USB Drives
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content border-0 px-0 pb-0">
                        <!-- Local Backups Tab -->
                        <div class="tab-pane fade show active" id="local-backups" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="local-backups-table" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Age</th>
                                            <th>Date Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- External Drives Tab -->
                        <div class="tab-pane fade" id="external-backups" role="tabpanel">
                            <h4 class="card-title mt-3">Connected Drives</h4>
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered" id="drives-table" width="100%">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Drive Label</th>
                                            <th>Mount Point</th>
                                            <th>Filesystem</th>
                                            <th>Usage</th>
                                            <th>Backups Stored</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Drives loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>

                            <h4 class="card-title mt-4">External Backups</h4>
                            <div class="table-responsive">
                                <table class="table table-hover" id="external-backups-table" width="100%">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Drive</th>
                                            <th>Size</th>
                                            <th>Age</th>
                                            <th>Date Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- External Backups loaded via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restoration Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-alert-circle mr-2"></i> Confirm Restoration</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> You are about to overwrite the current database with the selected backup file.
                </div>
                <p>This action will replace all current data. A temporary safety backup will be created before restoration.</p>
                <p class="mb-0"><strong>File to restore:</strong> <span id="restore-filename" class="text-primary font-weight-bold"></span></p>
                <input type="hidden" id="restore-filepath" value="">
                <input type="hidden" id="restore-type" value="local">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-restore">
                    <i class="mdi mdi-database-sync mr-1"></i> Proceed with Restore
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-white"><i class="mdi mdi-delete mr-2"></i> Delete Backup?</h5>
                <button type="button" data-bs-dismiss="modal" class="btn-close text-white btn-close-white" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete-filename"></strong>?</p>
                <p class="text-danger mb-0"><small>This action cannot be undone.</small></p>
                <input type="hidden" id="delete-filepath" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-delete">
                    Yes, delete it!
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading/Processing Modal -->
<div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-center py-4 px-4">
            <div class="modal-body">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3.5rem; height: 3.5rem;">
                    <span class="sr-only">Loading...</span>
                </div>
                <h4 id="loadingModalTitle" class="font-weight-bold mb-2">Processing...</h4>
                <p class="text-muted mb-4" id="loadingModalText" style="font-size: 0.95rem;">Please wait.</p>
                
                <div class="progress mb-2" style="height: 10px; border-radius: 10px; background-color: #e9ecef;">
                    <div id="loadingModalProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%; transition: width 0.4s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="text-primary mt-2 mb-0 font-weight-bold" id="loadingModalSubtitle" style="font-size: 0.85rem; letter-spacing: 0.5px;"></p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTables
    const localTable = $('#local-backups-table').DataTable({
        order: [[3, 'desc']], // Sort by Date Created descending
        columns: [
            { data: 'filename', render: function(data, type, row) {
                let badge = row.compressed ? '<span class="badge badge-success badge-sm ml-2">GZ</span>' : '';
                let safetyBadge = data.startsWith('pre_restore_') ? '<span class="badge badge-danger badge-sm ml-1" title="Safety snapshot taken before a restore">SAFETY</span>' : '';
                return `<strong>${data}</strong> ${badge} ${safetyBadge}`;
            }},
            { data: 'size_human' },
            { data: 'age' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });

    const externalTable = $('#external-backups-table').DataTable({
        order: [[4, 'desc']],
        columns: [
            { data: 'filename', render: function(data, type, row) {
                let badge = row.compressed ? '<span class="badge badge-success badge-sm ml-2">GZ</span>' : '';
                let safetyBadge = data.startsWith('pre_restore_') ? '<span class="badge badge-danger badge-sm ml-1" title="Safety snapshot taken before a restore">SAFETY</span>' : '';
                return `<strong>${data}</strong> ${badge} ${safetyBadge}`;
            }},
            { data: 'drive_label' },
            { data: 'size_human' },
            { data: 'age' },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ]
    });

    // Load Data
    function loadLocalBackups() {
        $.get('{{ route("backups.list") }}', function(res) {
            $('#stat-db-size').text(res.db_size);
            $('#stat-local-count').text(res.total);
            
            if (res.compression_enabled) {
                $('#stat-compression').html('<span class="badge badge-success"><i class="mdi mdi-check-circle mr-1"></i> Enabled</span>');
            } else {
                $('#stat-compression').html('<span class="badge badge-warning"><i class="mdi mdi-alert-circle mr-1"></i> Disabled</span>');
            }

            localTable.clear();
            
            res.backups.forEach(function(b) {
                b.actions = `
                    <div class="btn-group">
                        <a href="{{ url('admin/backups/download') }}/${b.filename}" class="btn btn-sm btn-info" title="Download">
                            <i class="mdi mdi-download"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-warning btn-restore" data-filename="${b.filename}" data-type="local" title="Restore">
                            <i class="mdi mdi-restore"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-delete" data-filename="${b.filename}" title="Delete">
                            <i class="mdi mdi-delete"></i>
                        </button>
                    </div>
                `;
                localTable.row.add(b);
            });
            
            localTable.draw();
        });
    }

    function loadDrives() {
        $.get('{{ route("backups.drives") }}', function(res) {
            let tbody = $('#drives-table tbody');
            tbody.empty();
            
            if (res.total === 0) {
                tbody.append('<tr><td colspan="6" class="text-center">No external drives detected</td></tr>');
                return;
            }

            res.drives.forEach(function(d) {
                let pcent = d.usage_percent ? `<div class="progress" style="height: 15px;"><div class="progress-bar bg-info" style="width: ${d.usage_percent}">${d.usage_percent}</div></div> <small class="text-muted d-block mt-1">${d.free_space} free of ${d.total_space}</small>` : 'N/A';
                let icon = d.is_usb ? '<i class="mdi mdi-usb text-primary mr-1" title="USB Device"></i>' : '<i class="mdi mdi-harddisk text-secondary mr-1"></i>';
                
                tbody.append(`
                    <tr>
                        <td><strong>${d.label}</strong></td>
                        <td><code>${d.mountpoint}</code></td>
                        <td>${d.filesystem}</td>
                        <td style="width:200px;">${pcent}</td>
                        <td class="text-center">
                            <span class="badge badge-primary badge-pill">${d.backup_count}</span>
                        </td>
                        <td>${icon} ${d.is_usb ? 'USB' : 'Internal/Mounted'}</td>
                    </tr>
                `);
            });
        });
    }

    function loadExternalBackups() {
        $.get('{{ route("backups.external") }}', function(res) {
            externalTable.clear();
            
            res.backups.forEach(function(b) {
                b.actions = `
                    <button type="button" class="btn btn-sm btn-warning btn-restore" data-filepath="${b.full_path}" data-filename="${b.filename}" data-type="external" title="Restore from External Drive">
                        <i class="mdi mdi-restore"></i> Restore
                    </button>
                `;
                externalTable.row.add(b);
            });
            
            externalTable.draw();
        });
    }

    // Initial Load
    loadLocalBackups();
    
    // Refresh on tab change
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'external-tab') {
            loadDrives();
            loadExternalBackups();
        } else {
            loadLocalBackups();
        }
    });

    // --- Progress Animation Helpers ---
    let progressInterval;
    let subtitleInterval;
    
    function startProgress(type) {
        clearInterval(progressInterval);
        clearInterval(subtitleInterval);
        
        let progress = 0;
        $('#loadingModalProgress').css('width', '0%').attr('aria-valuenow', 0).removeClass('bg-success bg-danger').addClass('bg-primary');
        $('#loadingModalSubtitle').removeClass('text-success text-danger').addClass('text-primary').text('Initializing...');
        
        let subtitles = [];
        if (type === 'backup') {
            subtitles = [
                "Connecting to database...",
                "Exporting tables and schemas...",
                "Applying gzip compression (if enabled)...",
                "Replicating to external drives...",
                "Finalizing backup file..."
            ];
        } else if (type === 'restore') {
            subtitles = [
                "Creating safety pre-restore backup...",
                "Extracting backup file...",
                "Connecting to database...",
                "Overwriting existing tables...",
                "Verifying data integrity...",
                "Finalizing restoration..."
            ];
        }
        
        let subIdx = 0;
        subtitleInterval = setInterval(() => {
            if (subIdx < subtitles.length) {
                $('#loadingModalSubtitle').hide().text(subtitles[subIdx]).fadeIn(300);
                subIdx++;
            }
        }, 3000);
        
        progressInterval = setInterval(() => {
            progress += (Math.random() * 4) + 1; // 1% to 5% each tick
            if (progress> 92) progress = 92; // Cap at 92% until ajax completes
            $('#loadingModalProgress').css('width', progress + '%').attr('aria-valuenow', progress);
        }, 800);
    }
    
    function completeProgress(success, message) {
        clearInterval(progressInterval);
        clearInterval(subtitleInterval);
        
        $('#loadingModalProgress').css('width', '100%').attr('aria-valuenow', 100);
        if (success) {
            $('#loadingModalProgress').removeClass('bg-primary').addClass('bg-success');
            $('#loadingModalSubtitle').removeClass('text-primary text-danger').addClass('text-success').text(message || 'Completed successfully!');
        } else {
            $('#loadingModalProgress').removeClass('bg-primary').addClass('bg-danger');
            $('#loadingModalSubtitle').removeClass('text-primary text-success').addClass('text-danger').text(message || 'Operation failed!');
        }
    }
    // ----------------------------------

    // Create Backup
    $('#btn-create-backup').click(function() {
        let btn = $(this);
        let originalText = btn.html();
        
        btn.html('<i class="mdi mdi-loading mdi-spin mr-2"></i> Creating...');
        btn.prop('disabled', true);
        
        $('#loadingModalTitle').text('Creating Backup');
        $('#loadingModalText').text('Please wait while the database is being backed up. This may take a few moments depending on the database size.');
        $('#loadingModal').modal({ backdrop: 'static', keyboard: false });
        $('#loadingModal').modal('show');
        startProgress('backup');

        $.ajax({
            url: '{{ route("backups.create") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                completeProgress(true, 'Backup Created!');
                setTimeout(() => {
                    $('#loadingModal').modal('hide');
                    toastr.success(res.message, 'Success!');
                    loadLocalBackups();
                    if ($('#external-tab').hasClass('active')) {
                        loadDrives();
                        loadExternalBackups();
                    }
                }, 1000);
            },
            error: function(xhr) {
                let msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred';
                completeProgress(false, 'Backup Failed');
                setTimeout(() => {
                    $('#loadingModal').modal('hide');
                    toastr.error(msg, 'Error');
                }, 1500);
            },
            complete: function() {
                btn.html(originalText);
                btn.prop('disabled', false);
            }
        });
    });

    // Delete Backup
    $(document).on('click', '.btn-delete', function() {
        let filename = $(this).data('filename');
        $('#delete-filename').text(filename);
        $('#delete-filepath').val(filename);
        $('#deleteModal').modal('show');
    });

    $('#btn-confirm-delete').click(function() {
        let filename = $('#delete-filepath').val();
        $('#deleteModal').modal('hide');
        
        $.ajax({
            url: `{{ url('admin/backups') }}/${filename}`,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                toastr.success(res.message);
                loadLocalBackups();
            },
            error: function(xhr) {
                let msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error deleting backup';
                toastr.error(msg);
            }
        });
    });

    // Open Restore Modal
    $(document).on('click', '.btn-restore', function() {
        let type = $(this).data('type');
        let filename = $(this).data('filename');
        let filepath = type === 'external' ? $(this).data('filepath') : filename;
        
        $('#restore-filename').text(filename);
        $('#restore-filepath').val(filepath);
        $('#restore-type').val(type);
        
        $('#restoreModal').modal('show');
    });

    // Confirm Restore
    $('#btn-confirm-restore').click(function() {
        let btn = $(this);
        let filepath = $('#restore-filepath').val();
        let type = $('#restore-type').val();
        
        btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Starting...');
        
        $('#restoreModal').modal('hide');
        
        // Wait for Bootstrap fade transition to complete before showing next modal
        setTimeout(() => {
            $('#loadingModalTitle').text('Restoring Database...');
            $('#loadingModalText').html('This process will overwrite the database and cannot be interrupted.<br><small class="text-muted mt-2 d-block">A pre-restore safety backup is being created automatically.</small>');
            $('#loadingModal').modal({ backdrop: 'static', keyboard: false });
            $('#loadingModal').modal('show');
            startProgress('restore');

            let url = type === 'local' ? '{{ route("backups.restore") }}' : '{{ route("backups.restore-external") }}';
            let data = { _token: '{{ csrf_token() }}' };
            
            if (type === 'local') {
                data.filename = filepath;
            } else {
                data.full_path = filepath;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: data,
                success: function(res) {
                    completeProgress(true, 'Restoration Complete!');
                    
                    setTimeout(() => {
                        $('#loadingModal').modal('hide');
                        
                        let msg = res.message;
                        if (res.pre_restore_backup) {
                            msg += `<br>Safety backup created: ${res.pre_restore_backup}`;
                        }
                        
                        toastr.success(msg, 'Restoration Complete', { timeOut: 5000 });
                        setTimeout(() => {
                            window.location.reload(); // Reload to refresh app state
                        }, 3000);
                    }, 1000);
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during restoration';
                    completeProgress(false, 'Restoration Failed');
                    
                    setTimeout(() => {
                        $('#loadingModal').modal('hide');
                        toastr.error(msg, 'Restoration Failed', { timeOut: 7000 });
                        btn.prop('disabled', false).html('<i class="mdi mdi-database-sync mr-1"></i> Proceed with Restore');
                    }, 1500);
                }
            });
        }, 500); // 500ms allows the backdrop and modal to completely disappear
    });
});
</script>
@endsection
