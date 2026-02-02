@extends('admin.layouts.app')

@section('title', 'Sub-Accounts')
@section('page_name', 'Accounting')
@section('subpage_name', 'Sub-Accounts')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts.index'), 'icon' => 'mdi-file-tree'],
    ['label' => $account->full_code . ' - ' . $account->name, 'url' => route('accounting.chart-of-accounts.show', $account->id), 'icon' => 'mdi-information'],
    ['label' => 'Sub-Accounts', 'url' => '#', 'icon' => 'mdi-format-list-bulleted']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Sub-Accounts</h4>
            <p class="text-muted mb-0">{{ $account->full_code }} - {{ $account->name }}</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary mr-2" data-toggle="modal" data-target="#createSubAccountModal">
                <i class="mdi mdi-plus mr-1"></i> Create Sub-Account
            </button>
            <a href="{{ route('accounting.chart-of-accounts.show', $account->id) }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Account
            </a>
        </div>
    </div>

    {{-- Sub-Accounts List --}}
    <div class="card-modern shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Sub-Accounts</h6>
        </div>
        <div class="card-body">
            @if($account->subAccounts->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Entity</th>
                            <th>Status</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($account->subAccounts as $subAccount)
                        <tr>
                            <td>{{ $subAccount->full_code }}</td>
                            <td>{{ $subAccount->name }}</td>
                            <td>
                                @if($subAccount->entity)
                                    <span class="badge badge-info">
                                        {{ $subAccount->entity_type }}: {{ $subAccount->entity->name ?? $subAccount->entity_id }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $subAccount->is_active ? 'success' : 'secondary' }}">
                                    {{ $subAccount->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                ₦ {{ number_format($subAccount->getBalance(), 2) }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('accounting.chart-of-accounts.show', $subAccount->id) }}"
                                   class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="mdi mdi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-5">
                <i class="mdi mdi-folder-open mdi-48px text-muted mb-3"></i>
                <h5>No Sub-Accounts</h5>
                <p class="text-muted mb-3">This account has no sub-accounts yet.</p>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createSubAccountModal">
                    <i class="mdi mdi-plus mr-1"></i> Create First Sub-Account
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Create Sub-Account Modal --}}
<div class="modal fade" id="createSubAccountModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('accounting.chart-of-accounts.sub-accounts.store', $account->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Sub-Account</h5>
                    <button type="button" class="close"  data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Sub-Account Code <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">{{ $account->full_code }}-</span>
                            </div>
                            <input type="text" name="sub_code" class="form-control"
                                   placeholder="e.g., 001" required>
                        </div>
                        <small class="text-muted">Will be combined with parent code</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sub-Account Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="e.g., Patient Account" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="Optional description..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Entity Type</label>
                        <select name="entity_type" id="entityType" class="form-control">
                            <option value="">None</option>
                            <option value="Patient">Patient</option>
                            <option value="HMO">HMO</option>
                            <option value="Supplier">Supplier</option>
                            <option value="Employee">Employee</option>
                        </select>
                        <small class="text-muted">Link this sub-account to a specific entity</small>
                    </div>

                    <div class="mb-3" id="entityIdDiv" style="display: none;">
                        <label class="form-label">Entity ID</label>
                        <input type="number" name="entity_id" class="form-control"
                               placeholder="Enter entity ID">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"  data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Sub-Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#entityType').on('change', function() {
        if ($(this).val()) {
            $('#entityIdDiv').slideDown();
        } else {
            $('#entityIdDiv').slideUp();
        }
    });
});
</script>
@endpush
@endsection
