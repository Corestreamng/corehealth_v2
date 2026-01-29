@extends('admin.layouts.app')

@section('page_name', 'Accounting')
@section('subpage_name', 'Chart of Accounts')

@section('content')
<div class="container-fluid">
    {{-- Header with Title and Action Buttons --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Chart of Accounts</h4>
            <p class="text-muted mb-0">Manage account structure and hierarchy</p>
        </div>
        @can('accounts.create')
        <div>
            <a href="{{ route('accounting.chart-of-accounts.groups.create') }}" class="btn btn-outline-primary">
                <i class="mdi mdi-folder-plus mr-1"></i> New Group
            </a>
            <a href="{{ route('accounting.chart-of-accounts.create') }}" class="btn btn-primary">
                <i class="mdi mdi-plus mr-1"></i> New Account
            </a>
        </div>
        @endcan
    </div>

    {{-- Stat Cards Row --}}
    <div class="row mb-4">
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-primary">{{ number_format($stats['total_accounts']) }}</h3>
                    <small class="text-muted">Total Accounts</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-success">{{ number_format($stats['active_accounts']) }}</h3>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-secondary">{{ number_format($stats['inactive_accounts']) }}</h3>
                    <small class="text-muted">Inactive</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-info">{{ number_format($stats['bank_accounts']) }}</h3>
                    <small class="text-muted">Bank Accounts</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-warning">{{ number_format($stats['total_classes']) }}</h3>
                    <small class="text-muted">Classes</small>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 mb-3">
            <div class="card card-modern h-100">
                <div class="card-body text-center">
                    <h3 class="mb-1 text-dark">{{ number_format($stats['total_groups']) }}</h3>
                    <small class="text-muted">Groups</small>
                </div>
            </div>
        </div>
    </div>

    {{-- View Toggle Tabs --}}
    <ul class="nav nav-tabs mb-4" id="viewTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="tree-tab" data-toggle="tab" href="#treeView" role="tab">
                <i class="mdi mdi-file-tree mr-1"></i> Tree View
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="table-tab" data-toggle="tab" href="#tableView" role="tab">
                <i class="mdi mdi-table mr-1"></i> Table View
            </a>
        </li>
    </ul>

    <div class="tab-content" id="viewTabsContent">
        {{-- Tree View Tab --}}
        <div class="tab-pane fade show active" id="treeView" role="tabpanel">
            {{-- Search Box for Tree View --}}
            <div class="card card-modern mb-4">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                </div>
                                <input type="text" id="treeSearch" class="form-control" placeholder="Search accounts...">
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <button class="btn btn-sm btn-outline-secondary" id="expandAll">
                                <i class="mdi mdi-expand-all mr-1"></i>Expand All
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="collapseAll">
                                <i class="mdi mdi-collapse-all mr-1"></i>Collapse All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Account Classes Accordion --}}
            <div class="accordion" id="chartAccordion">
                @foreach($classes as $class)
                    <div class="card card-modern mb-2 account-class-card" data-class="{{ strtolower($class->name) }}">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center"
                             id="heading{{ $class->id }}" data-toggle="collapse" data-target="#collapse{{ $class->id }}"
                             style="cursor: pointer;">
                            <div>
                                <span class="badge badge-primary mr-2">{{ $class->class_code }}</span>
                                <strong>{{ $class->name }}</strong>
                                <span class="ml-2 text-muted">({{ $class->normal_balance === 'debit' ? 'Debit' : 'Credit' }} balance)</span>
                            </div>
                            <i class="mdi mdi-chevron-down collapse-icon"></i>
                        </div>
                        <div id="collapse{{ $class->id }}" class="collapse {{ $loop->first ? 'show' : '' }}"
                             data-parent="#chartAccordion">
                            <div class="card-body">
                                @if($class->description)
                                    <p class="text-muted">{{ $class->description }}</p>
                                @endif

                                @forelse($class->groups as $group)
                                    <div class="card mb-3 account-group-card" data-group="{{ strtolower($group->name) }}">
                                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center"
                                             data-toggle="collapse" data-target="#group{{ $group->id }}" style="cursor: pointer;">
                                            <div>
                                                <span class="badge badge-secondary mr-2">{{ $group->group_code }}</span>
                                                <strong>{{ $group->name }}</strong>
                                                <small class="text-muted ml-2">({{ $group->accounts->count() }} accounts)</small>
                                            </div>
                                            <i class="mdi mdi-chevron-down collapse-icon-sm"></i>
                                        </div>
                                        <div class="collapse show" id="group{{ $group->id }}">
                                            <div class="card-body p-0">
                                                @if($group->accounts->count() > 0)
                                                    <table class="table table-hover table-sm mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th width="120">Code</th>
                                                                <th>Account Name</th>
                                                                <th width="100">Balance</th>
                                                                <th width="80">Type</th>
                                                                <th width="80">Status</th>
                                                                <th width="80">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($group->accounts as $account)
                                                                <tr class="account-row {{ !$account->is_active ? 'table-secondary' : '' }}"
                                                                    data-account="{{ strtolower($account->account_code . ' ' . $account->name) }}">
                                                                    <td>
                                                                        <code>{{ $account->account_code }}</code>
                                                                    </td>
                                                                    <td>
                                                                        <a href="{{ route('accounting.chart-of-accounts.show', $account->id) }}">
                                                                            {{ $account->name }}
                                                                        </a>
                                                                        @if($account->description)
                                                                            <br><small class="text-muted">{{ Str::limit($account->description, 50) }}</small>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-{{ $account->normal_balance === 'debit' ? 'info' : 'warning' }}">
                                                                            {{ ucfirst($account->normal_balance) }}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        @if($account->is_bank_account)
                                                                            <span class="badge badge-success">
                                                                                <i class="mdi mdi-bank mr-1"></i>Bank
                                                                            </span>
                                                                        @else
                                                                            <span class="text-muted">-</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @if($account->is_active)
                                                                            <span class="badge badge-success">Active</span>
                                                                        @else
                                                                            <span class="badge badge-secondary">Inactive</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                                    data-toggle="dropdown">
                                                                                <i class="mdi mdi-dots-vertical"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <a class="dropdown-item" href="{{ route('accounting.chart-of-accounts.show', $account->id) }}">
                                                                                    <i class="mdi mdi-eye mr-2"></i>View
                                                                                </a>
                                                                                @can('accounts.edit')
                                                                                <a class="dropdown-item" href="{{ route('accounting.chart-of-accounts.edit', $account->id) }}">
                                                                                    <i class="mdi mdi-pencil mr-2"></i>Edit
                                                                                </a>
                                                                                @endcan
                                                                                <a class="dropdown-item" href="{{ route('accounting.chart-of-accounts.sub-accounts', $account->id) }}">
                                                                                    <i class="mdi mdi-format-list-bulleted mr-2"></i>Sub-Accounts
                                                                                </a>
                                                                                @can('accounts.edit')
                                                                                <div class="dropdown-divider"></div>
                                                                                @if($account->is_active)
                                                                                    <a class="dropdown-item text-danger btn-deactivate" href="#" data-id="{{ $account->id }}">
                                                                                        <i class="mdi mdi-close-circle mr-2"></i>Deactivate
                                                                                    </a>
                                                                                @else
                                                                                    <a class="dropdown-item text-success btn-activate" href="#" data-id="{{ $account->id }}">
                                                                                        <i class="mdi mdi-check-circle mr-2"></i>Activate
                                                                                    </a>
                                                                                @endif
                                                                                @endcan
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    <div class="p-3 text-center text-muted">
                                                        No accounts in this group.
                                                        @can('accounts.create')
                                                        <a href="{{ route('accounting.chart-of-accounts.create') }}?group={{ $group->id }}">Add one</a>
                                                        @endcan
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center text-muted py-4">
                                        No account groups in this class.
                                        @can('accounts.create')
                                        <a href="{{ route('accounting.chart-of-accounts.groups.create') }}?class={{ $class->id }}">Create one</a>
                                        @endcan
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($classes->isEmpty())
                <div class="card card-modern">
                    <div class="card-body text-center py-5">
                        <i class="mdi mdi-file-tree mdi-48px text-muted mb-3"></i>
                        <h5>No Chart of Accounts Set Up</h5>
                        <p class="text-muted">Start by running the Chart of Accounts seeder or adding accounts manually.</p>
                        @can('accounts.create')
                        <a href="{{ route('accounting.chart-of-accounts.create') }}" class="btn btn-primary">
                            <i class="mdi mdi-plus mr-1"></i> Add First Account
                        </a>
                        @endcan
                    </div>
                </div>
            @endif
        </div>

        {{-- Table View Tab --}}
        <div class="tab-pane fade" id="tableView" role="tabpanel">
            {{-- Filters for Table View --}}
            <div class="card card-modern mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-filter-variant mr-2"></i>Filters</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearTableFilters">
                        <i class="mdi mdi-close mr-1"></i>Clear All
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Account Class</label>
                            <select id="filterClass" class="form-control">
                                <option value="">All Classes</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->class_code }} - {{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Account Group</label>
                            <select id="filterGroup" class="form-control">
                                <option value="">All Groups</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select id="filterStatus" class="form-control">
                                <option value="">All</option>
                                <option value="active">Active Only</option>
                                <option value="inactive">Inactive Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Type</label>
                            <select id="filterBank" class="form-control">
                                <option value="">All Types</option>
                                <option value="1">Bank Accounts Only</option>
                                <option value="0">Non-Bank Accounts</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" id="btnApplyTableFilters">
                                <i class="mdi mdi-magnify mr-1"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- DataTable --}}
            <div class="card card-modern">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="mdi mdi-format-list-bulleted mr-2"></i>All Accounts</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportAccounts">
                        <i class="mdi mdi-download mr-1"></i>Export
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="accountsTable" class="table table-hover table-striped w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Account Name</th>
                                    <th>Class</th>
                                    <th>Group</th>
                                    <th>Balance</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .card-modern {
        border: none;
        box-shadow: 0 0 10px rgba(0,0,0,0.05);
        border-radius: 10px;
    }
    .card-modern .card-header {
        background-color: transparent;
        border-bottom: 1px solid #eee;
    }

    .collapse-icon {
        transition: transform 0.2s;
    }
    .collapsed .collapse-icon {
        transform: rotate(-90deg);
    }
    .collapse-icon-sm {
        transition: transform 0.2s;
        font-size: 14px;
    }
    [data-toggle="collapse"].collapsed .collapse-icon-sm {
        transform: rotate(-90deg);
    }

    .account-row.highlight {
        background-color: #fff3cd !important;
    }

    .nav-tabs .nav-link {
        border-radius: 10px 10px 0 0;
    }
    .nav-tabs .nav-link.active {
        font-weight: 600;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Group data for filtering
    var groupsByClass = @json($classes->mapWithKeys(function($c) {
        return [$c->id => $c->groups->map(function($g) {
            return ['id' => $g->id, 'code' => $g->group_code, 'name' => $g->name];
        })];
    }));

    // DataTable initialization (lazy - only when tab is shown)
    var accountsTable = null;

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (e.target.id === 'table-tab' && accountsTable === null) {
            accountsTable = $('#accountsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("accounting.chart-of-accounts.datatable") }}',
                    data: function(d) {
                        d.class_id = $('#filterClass').val();
                        d.group_id = $('#filterGroup').val();
                        d.status = $('#filterStatus').val();
                        d.is_bank = $('#filterBank').val();
                    }
                },
                columns: [
                    { data: 'account_link', name: 'account_code' },
                    { data: 'account_name', name: 'name' },
                    { data: 'class_name', name: 'accountGroup.accountClass.name' },
                    { data: 'group_name', name: 'accountGroup.name' },
                    { data: 'balance_badge', name: 'normal_balance', className: 'text-center' },
                    { data: 'type_badge', name: 'is_bank_account', className: 'text-center' },
                    { data: 'status_badge', name: 'is_active', className: 'text-center' },
                    { data: 'actions', name: 'actions', orderable: false, searchable: false }
                ],
                order: [[0, 'asc']],
                pageLength: 25
            });
        }
    });

    // Tree view search
    $('#treeSearch').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        if (search === '') {
            $('.account-row').show().removeClass('highlight');
            $('.account-group-card').show();
            $('.account-class-card').show();
        } else {
            $('.account-row').each(function() {
                var text = $(this).data('account');
                if (text.indexOf(search) > -1) {
                    $(this).show().addClass('highlight');
                } else {
                    $(this).hide().removeClass('highlight');
                }
            });
            // Show/hide groups based on visible accounts
            $('.account-group-card').each(function() {
                var hasVisible = $(this).find('.account-row:visible').length > 0;
                $(this).toggle(hasVisible);
            });
            // Show/hide classes based on visible groups
            $('.account-class-card').each(function() {
                var hasVisible = $(this).find('.account-group-card:visible').length > 0;
                $(this).toggle(hasVisible);
            });
        }
    });

    // Expand/Collapse all
    $('#expandAll').on('click', function() {
        $('#chartAccordion .collapse').collapse('show');
        $('.account-group-card .collapse').collapse('show');
    });

    $('#collapseAll').on('click', function() {
        $('#chartAccordion .collapse').collapse('hide');
    });

    // Class filter change - populate groups
    $('#filterClass').on('change', function() {
        var classId = $(this).val();
        var $groupSelect = $('#filterGroup');
        $groupSelect.html('<option value="">All Groups</option>');

        if (classId && groupsByClass[classId]) {
            groupsByClass[classId].forEach(function(g) {
                $groupSelect.append('<option value="' + g.id + '">' + g.code + ' - ' + g.name + '</option>');
            });
        }
    });

    // Apply table filters
    $('#btnApplyTableFilters').on('click', function() {
        if (accountsTable) {
            accountsTable.ajax.reload();
        }
    });

    // Clear table filters
    $('#btnClearTableFilters').on('click', function() {
        $('#filterClass, #filterGroup, #filterStatus, #filterBank').val('');
        if (accountsTable) {
            accountsTable.ajax.reload();
        }
    });

    // Activate account (AJAX)
    $(document).on('click', '.btn-activate', function(e) {
        e.preventDefault();
        var id = $(this).data('id');

        $.post('{{ url("accounting/chart-of-accounts") }}/' + id + '/activate', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                location.reload();
            } else {
                toastr.error(response.message || 'Error activating account');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error activating account');
        });
    });

    // Deactivate account (AJAX)
    $(document).on('click', '.btn-deactivate', function(e) {
        e.preventDefault();
        var id = $(this).data('id');

        if (!confirm('Are you sure you want to deactivate this account?')) {
            return;
        }

        $.post('{{ url("accounting/chart-of-accounts") }}/' + id + '/deactivate', {
            _token: '{{ csrf_token() }}'
        })
        .done(function(response) {
            if (response.success) {
                toastr.success(response.message);
                location.reload();
            } else {
                toastr.error(response.message || 'Error deactivating account');
            }
        })
        .fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error deactivating account');
        });
    });

    // Export
    $('#btnExportAccounts').on('click', function() {
        var params = $.param({
            class_id: $('#filterClass').val(),
            group_id: $('#filterGroup').val(),
            status: $('#filterStatus').val(),
            is_bank: $('#filterBank').val(),
            export: 'excel'
        });
        window.location.href = '{{ route("accounting.chart-of-accounts.index") }}?' + params;
    });
});
</script>
@endpush
