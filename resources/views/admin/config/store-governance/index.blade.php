@extends('admin.layouts.app')
@section('title', 'Store Governance Config')
@section('page_name', 'Configuration')
@section('subpage_name', 'Store Governance')

@section('content')
{{--
    Store Governance Config Page
    Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
    § 9.1  Admin Module: Store Governance
      Section A — Store Role Catalog (distribution_role, dispense flags, shift context)
      Section B — Lane Policy Matrix  (rendered by AJAX via laneMatrix())
      Section C — Store Ownership / Manager mapping
    § 9.4  Save guards for breaking changes
    § 11   Permission gate: store-governance.view / store-governance.manage
--}}
<div id="content-wrapper">
    <div class="container-fluid">

        {{-- ── Page Header ──────────────────────────────────────────────── --}}
        <div class="card-modern mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-sitemap me-2 text-primary"></i>
                    Store Governance Configuration
                </h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('inventory.config.store-governance.context-rules') }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-filter me-1"></i> Context Rules
                    </a>
                    <a href="{{ route('inventory.config.store-governance.lane-matrix') }}"
                       class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-project-diagram me-1"></i> Lane Matrix
                    </a>
                </div>
            </div>
            <div class="card-body pb-0">
                <p class="text-muted small mb-0">
                    Configure the <strong>distribution role</strong>, parent store hierarchy, dispense flags,
                    and manager ownership for each store. Changes are audited.
                    Lane policy and context resolution are on the adjacent tabs.
                </p>
            </div>
        </div>

        {{-- ── Section A: Store Role Catalog ──────────────────────────── --}}
        {{-- Plan §9.1 Section A --}}
        <div class="card-modern">
            <div class="card-header">
                <h6 class="mb-0">Section A — Store Role Catalog</h6>
                <small class="text-muted">Edit inline. Click <em>Save Row</em> to persist changes.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="storeRoleCatalog">
                        <thead class="table-light">
                            <tr>
                                <th>Store</th>
                                <th>Type</th>
                                <th>Distribution Role</th>
                                <th>Parent Store</th>
                                <th>Ward</th>
                                <th>Department</th>
                                <th>Manager</th>
                                <th style="width:80px">Direct Dispense</th>
                                <th style="width:80px">Shift Context</th>
                                @can('store-governance.manage')
                                <th style="width:90px">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stores as $store)
                            <tr data-store-id="{{ $store->id }}">
                                <td class="fw-semibold">{{ $store->store_name }}</td>
                                <td><span class="badge bg-secondary">{{ $store->store_type }}</span></td>

                                {{-- distribution_role select --}}
                                <td>
                                    @can('store-governance.manage')
                                    <select class="form-select form-select-sm sg-field" name="distribution_role"
                                            data-store="{{ $store->id }}">
                                        @foreach($distributionRoles as $role)
                                        <option value="{{ $role }}"
                                            {{ $store->distribution_role === $role ? 'selected' : '' }}>
                                            {{ \App\Models\Store::ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @else
                                        <span class="badge bg-info text-dark">
                                            {{ \App\Models\Store::ROLE_LABELS[$store->distribution_role] ?? $store->distribution_role }}
                                        </span>
                                    @endcan
                                </td>

                                {{-- parent_store_id --}}
                                <td>
                                    @can('store-governance.manage')
                                    <select class="form-select form-select-sm sg-field" name="parent_store_id"
                                            data-store="{{ $store->id }}">
                                        <option value="">— none —</option>
                                        @foreach($stores as $ps)
                                        @if($ps->id !== $store->id)
                                        <option value="{{ $ps->id }}"
                                            {{ $store->parent_store_id == $ps->id ? 'selected' : '' }}>
                                            {{ $ps->store_name }}
                                        </option>
                                        @endif
                                        @endforeach
                                    </select>
                                    @else
                                        {{ $store->parentStore?->store_name ?? '—' }}
                                    @endcan
                                </td>

                                {{-- ward_id --}}
                                <td>
                                    @can('store-governance.manage')
                                    <select class="form-select form-select-sm sg-field" name="ward_id"
                                            data-store="{{ $store->id }}">
                                        <option value="">— none —</option>
                                        @foreach($wards as $ward)
                                        <option value="{{ $ward->id }}"
                                            {{ $store->ward_id == $ward->id ? 'selected' : '' }}>
                                            {{ $ward->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @else
                                        {{ $store->ward?->name ?? '—' }}
                                    @endcan
                                </td>

                                {{-- department_id --}}
                                <td>
                                    @can('store-governance.manage')
                                    <select class="form-select form-select-sm sg-field" name="department_id"
                                            data-store="{{ $store->id }}">
                                        <option value="">— none —</option>
                                        @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}"
                                            {{ $store->department_id == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @else
                                        {{ $store->department?->name ?? '—' }}
                                    @endcan
                                </td>

                                {{-- manager_id --}}
                                <td>
                                    @can('store-governance.manage')
                                    <select class="form-select form-select-sm sg-field" name="manager_id"
                                            data-store="{{ $store->id }}">
                                        <option value="">— none —</option>
                                        @foreach($managers as $mgr)
                                        <option value="{{ $mgr->id }}"
                                            {{ $store->manager_id == $mgr->id ? 'selected' : '' }}>
                                            {{ $mgr->surname }} {{ $mgr->firstname }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @else
                                        {{ $store->manager ? $store->manager->surname . ' ' . $store->manager->firstname : '—' }}
                                    @endcan
                                </td>

                                {{-- allows_direct_patient_dispense --}}
                                <td class="text-center">
                                    @can('store-governance.manage')
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input sg-toggle"
                                               type="checkbox"
                                               name="allows_direct_patient_dispense"
                                               data-store="{{ $store->id }}"
                                               {{ $store->allows_direct_patient_dispense ? 'checked' : '' }}>
                                    </div>
                                    @else
                                        <i class="fas fa-{{ $store->allows_direct_patient_dispense ? 'check text-success' : 'times text-muted' }}"></i>
                                    @endcan
                                </td>

                                {{-- requires_shift_context --}}
                                <td class="text-center">
                                    @can('store-governance.manage')
                                    <div class="form-check form-switch d-flex justify-content-center">
                                        <input class="form-check-input sg-toggle"
                                               type="checkbox"
                                               name="requires_shift_context"
                                               data-store="{{ $store->id }}"
                                               {{ $store->requires_shift_context ? 'checked' : '' }}>
                                    </div>
                                    @else
                                        <i class="fas fa-{{ $store->requires_shift_context ? 'check text-success' : 'times text-muted' }}"></i>
                                    @endcan
                                </td>

                                @can('store-governance.manage')
                                <td class="text-center">
                                    <button class="btn btn-xs btn-primary sg-save-row"
                                            data-store="{{ $store->id }}"
                                            title="Save this row">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </td>
                                @endcan
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>{{-- /container-fluid --}}
</div>{{-- /content-wrapper --}}

{{-- Save Guard Modal (Plan §9.4) --}}
<div class="modal fade" id="saveGuardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Breaking Change Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saveGuardMessage">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="saveGuardConfirm">
                    <i class="fas fa-exclamation me-1"></i>Save Anyway
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    /**
     * Store Governance — Store Role Catalog JS
     * Plan §9.1 Section A, §9.4 save guards
     */
    const SAVE_URL = id => `/inventory/config/store-governance/stores/${id}`;
    const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let pendingSaveStore = null;
    let pendingSaveData  = null;

    // Collect row data for a given store ID
    function collectRowData(storeId) {
        const row = document.querySelector(`tr[data-store-id="${storeId}"]`);
        if (! row) return null;
        const data = {};

        row.querySelectorAll('.sg-field[data-store]').forEach(el => {
            data[el.name] = el.value || null;
        });
        row.querySelectorAll('.sg-toggle[data-store]').forEach(el => {
            data[el.name] = el.checked ? 1 : 0;
        });

        return data;
    }

    async function saveStore(storeId, data, forceSave = false) {
        if (forceSave) data.force_save = 1;

        const res  = await fetch(SAVE_URL(storeId), {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(data),
        });
        const json = await res.json();

        if (res.status === 409 && json.save_guard) {
            // Plan §9.4 — save guard triggered
            pendingSaveStore = storeId;
            pendingSaveData  = data;
            document.getElementById('saveGuardMessage').innerHTML =
                `<p>${json.message}</p>`;
            new bootstrap.Modal(document.getElementById('saveGuardModal')).show();
            return;
        }

        if (json.success) {
            toastr.success(json.message ?? 'Saved.');
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    }

    // Save Row button click
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sg-save-row');
        if (! btn) return;
        const storeId = btn.dataset.store;
        const data    = collectRowData(storeId);
        if (data) saveStore(storeId, data);
    });

    // Confirm forced save from modal
    document.getElementById('saveGuardConfirm').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('saveGuardModal')).hide();
        if (pendingSaveStore && pendingSaveData) {
            saveStore(pendingSaveStore, pendingSaveData, true);
            pendingSaveStore = null;
            pendingSaveData  = null;
        }
    });
})();
</script>
@endsection
