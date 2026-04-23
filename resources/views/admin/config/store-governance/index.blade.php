@extends('admin.layouts.app')
@section('title', 'Store Governance Config')
@section('page_name', 'Configuration')
@section('subpage_name', 'Store Governance')

@section('content')
{{--
    Store Governance Config — Store Role Catalog
    Columns (≤7): Store | Status | Distribution Role | Linked To | Manager | Flags | Actions
    Editing is via modal. Immutable stores are locked.
--}}
<div id="content-wrapper">
    <div class="container-fluid">

        {{-- ── Shared Nav Tabs ──────────────────────────────────────────── --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link active fw-semibold" href="{{ route('inventory.config.store-governance.index') }}">
                    <i class="fas fa-store me-1"></i> Store Catalog
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('inventory.config.store-governance.lane-matrix') }}">
                    <i class="fas fa-project-diagram me-1"></i> Lane Matrix
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('inventory.config.store-governance.context-rules') }}">
                    <i class="fas fa-filter me-1"></i> Resolution Rules
                </a>
            </li>
        </ul>

        {{-- ── Role Stat Bar ─────────────────────────────────────────────── --}}
        @php
            $roleCounts = $stores->groupBy('distribution_role')->map->count();
            $roleColors = [
                'central'            => ['bg' => '#6610f2', 'label' => 'Central'],
                'pharmacy_hub'       => ['bg' => '#0d6efd', 'label' => 'Hub'],
                'pharmacy_satellite' => ['bg' => '#0dcaf0', 'label' => 'Satellite'],
                'department'         => ['bg' => '#fd7e14', 'label' => 'Department'],
                'ward'               => ['bg' => '#198754', 'label' => 'Ward'],
                'other'              => ['bg' => '#6c757d', 'label' => 'Other'],
            ];
        @endphp
        <div class="row g-2 mb-3">
            @foreach($roleColors as $role => $meta)
            <div class="col-6 col-sm-4 col-md-2">
                <div class="card text-white text-center p-2 stat-role-card"
                     style="background:{{ $meta['bg'] }}; cursor:pointer" data-role="{{ $role }}">
                    <div class="fw-bold fs-4">{{ $roleCounts[$role] ?? 0 }}</div>
                    <div class="small">{{ $meta['label'] }}</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ── Filter Bar ───────────────────────────────────────────────── --}}
        <div class="card-modern mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="storeSearch" class="form-control form-control-sm"
                           style="max-width:240px" placeholder="Search stores…">
                    <div class="btn-group btn-group-sm" id="roleFilterBtns" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                        @foreach($roleColors as $role => $meta)
                        <button type="button" class="btn btn-outline-secondary" data-filter="{{ $role }}">
                            {{ $meta['label'] }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Store Role Catalog Table (7 cols max) ───────────────────── --}}
        <div class="card-modern">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-list me-1 text-primary"></i> Store Role Catalog</h6>
                <small class="text-muted">Click <strong>Edit</strong> to modify a store's governance settings.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="storeRoleCatalog">
                        <thead class="table-light">
                            <tr>
                                <th>Store</th>
                                <th>Status</th>
                                <th>Distribution Role</th>
                                <th>Linked To</th>
                                <th>Manager</th>
                                <th class="text-center" title="Direct Dispense / Shift Context">Flags</th>
                                @can('store-governance.manage')
                                <th class="text-center" style="width:90px">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stores as $store)
                            @php
                                $roleKey     = $store->distribution_role ?? 'other';
                                $roleColor   = $roleColors[$roleKey]['bg']    ?? '#6c757d';
                                $roleLabel   = $roleColors[$roleKey]['label'] ?? ucfirst($roleKey);
                                $linked      = $store->ward?->name ?? $store->department?->name ?? $store->parentStore?->store_name ?? '—';
                                $isImmutable = $store->is_immutable ?? false;
                            @endphp
                            <tr data-store-id="{{ $store->id }}"
                                data-role="{{ $roleKey }}"
                                data-name="{{ strtolower($store->store_name) }}">
                                <td>
                                    @if($isImmutable)
                                        <i class="fas fa-lock fa-xs text-muted me-1" title="Protected store"></i>
                                    @endif
                                    <span class="fw-semibold">{{ $store->store_name }}</span>
                                </td>
                                <td>
                                    @if($store->status)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-white" style="background:{{ $roleColor }}">
                                        {{ $roleLabel }}
                                    </span>
                                </td>
                                <td class="small text-muted">{{ $linked }}</td>
                                <td class="small">
                                    {{ $store->manager ? $store->manager->surname . ' ' . $store->manager->firstname : '—' }}
                                </td>
                                <td class="text-center">
                                    <span title="Direct Dispense"
                                          class="{{ $store->allows_direct_patient_dispense ? 'text-success' : 'text-muted' }}">
                                        <i class="fas fa-syringe fa-xs"></i>
                                    </span>
                                    <span title="Shift Context Required"
                                          class="{{ $store->requires_shift_context ? 'text-warning' : 'text-muted' }} ms-1">
                                        <i class="fas fa-clock fa-xs"></i>
                                    </span>
                                </td>
                                @can('store-governance.manage')
                                <td class="text-center">
                                    @if($isImmutable)
                                        <button class="btn btn-xs btn-outline-secondary" disabled
                                                title="Protected store — cannot be edited">
                                            <i class="fas fa-lock d-block mx-auto mb-1"></i>
                                            <span class="d-block" style="font-size:0.7rem">Locked</span>
                                        </button>
                                    @else
                                        <button class="btn btn-xs btn-outline-primary sg-edit-btn"
                                                data-store="{{ $store->id }}">
                                            <i class="fas fa-pencil-alt d-block mx-auto mb-1"></i>
                                            <span class="d-block" style="font-size:0.7rem">Edit</span>
                                        </button>
                                    @endif
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

{{-- ── Edit Store Modal ─────────────────────────────────────────────────── --}}
@can('store-governance.manage')
<div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-store me-2"></i><span id="editStoreTitle">Edit Store</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editStoreId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Distribution Role</label>
                        <select class="form-select" id="editDistributionRole">
                            @foreach($distributionRoles as $role)
                            <option value="{{ $role }}">
                                {{ \App\Models\Store::ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Parent Store</label>
                        <select class="form-select" id="editParentStoreId">
                            <option value="">— none —</option>
                            @foreach($stores as $ps)
                            <option value="{{ $ps->id }}">{{ $ps->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ward</label>
                        <select class="form-select" id="editWardId">
                            <option value="">— none —</option>
                            @foreach($wards as $ward)
                            <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Department</label>
                        <select class="form-select" id="editDepartmentId">
                            <option value="">— none —</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Manager</label>
                        <select class="form-select" id="editManagerId">
                            <option value="">— none —</option>
                            @foreach($managers as $mgr)
                            <option value="{{ $mgr->id }}">{{ $mgr->surname }} {{ $mgr->firstname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Direct Dispense</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="editDirectDispense">
                            <label class="form-check-label small" for="editDirectDispense">Enabled</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Shift Context</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="editShiftContext">
                            <label class="form-check-label small" for="editShiftContext">Required</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="editStoreSaveBtn">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Save Guard Modal (Plan §9.4) --}}
<div class="modal fade" id="saveGuardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Breaking Change Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="saveGuardMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="saveGuardConfirm">
                    <i class="fas fa-exclamation me-1"></i> Save Anyway
                </button>
            </div>
        </div>
    </div>
</div>
@endcan

@endsection

@section('scripts')
<script>
(function () {
    const SAVE_URL = id => `/inventory/config/store-governance/stores/${id}`;
    const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const STORE_DATA = @json($stores->keyBy('id')->map(fn($s) => [
        'id'                             => $s->id,
        'store_name'                     => $s->store_name,
        'distribution_role'              => $s->distribution_role,
        'parent_store_id'                => $s->parent_store_id,
        'ward_id'                        => $s->ward_id,
        'department_id'                  => $s->department_id,
        'manager_id'                     => $s->manager_id,
        'allows_direct_patient_dispense' => $s->allows_direct_patient_dispense ? 1 : 0,
        'requires_shift_context'         => $s->requires_shift_context ? 1 : 0,
    ]));

    let pendingSaveStore = null;
    let pendingSaveData  = null;
    let activeFilter     = 'all';

    // ── Filter / search ───────────────────────────────────────────────────
    function applyFilter() {
        const q = document.getElementById('storeSearch').value.toLowerCase().trim();
        document.querySelectorAll('#storeRoleCatalog tbody tr').forEach(row => {
            const matchRole = activeFilter === 'all' || row.dataset.role === activeFilter;
            const matchName = !q || row.dataset.name.includes(q);
            row.style.display = (matchRole && matchName) ? '' : 'none';
        });
    }

    document.getElementById('storeSearch')?.addEventListener('input', applyFilter);

    document.querySelectorAll('#roleFilterBtns .btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#roleFilterBtns .btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            applyFilter();
        });
    });

    document.querySelectorAll('.stat-role-card').forEach(card => {
        card.addEventListener('click', function () {
            const role = this.dataset.role;
            document.querySelectorAll('#roleFilterBtns .btn').forEach(b => {
                b.classList.toggle('active', b.dataset.filter === role);
            });
            activeFilter = role;
            applyFilter();
        });
    });

    // ── Open edit modal ───────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sg-edit-btn');
        if (!btn) return;
        const storeId = btn.dataset.store;
        const s = STORE_DATA[storeId];
        if (!s) return;

        document.getElementById('editStoreId').value          = storeId;
        document.getElementById('editStoreTitle').textContent = 'Edit — ' + s.store_name;
        document.getElementById('editDistributionRole').value = s.distribution_role ?? '';
        document.getElementById('editParentStoreId').value    = s.parent_store_id   ?? '';
        document.getElementById('editWardId').value           = s.ward_id           ?? '';
        document.getElementById('editDepartmentId').value     = s.department_id     ?? '';
        document.getElementById('editManagerId').value        = s.manager_id        ?? '';
        document.getElementById('editDirectDispense').checked = s.allows_direct_patient_dispense == 1;
        document.getElementById('editShiftContext').checked   = s.requires_shift_context == 1;

        new bootstrap.Modal(document.getElementById('editStoreModal')).show();
    });

    function collectModalData() {
        return {
            distribution_role:              document.getElementById('editDistributionRole').value || null,
            parent_store_id:                document.getElementById('editParentStoreId').value    || null,
            ward_id:                        document.getElementById('editWardId').value           || null,
            department_id:                  document.getElementById('editDepartmentId').value     || null,
            manager_id:                     document.getElementById('editManagerId').value        || null,
            allows_direct_patient_dispense: document.getElementById('editDirectDispense').checked ? 1 : 0,
            requires_shift_context:         document.getElementById('editShiftContext').checked   ? 1 : 0,
        };
    }

    // ── Save store ────────────────────────────────────────────────────────
    async function saveStore(storeId, data, forceSave = false) {
        if (forceSave) data.force_save = 1;

        const res  = await fetch(SAVE_URL(storeId), {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(data),
        });
        const json = await res.json();

        if (res.status === 409 && json.save_guard) {
            pendingSaveStore = storeId;
            pendingSaveData  = data;
            bootstrap.Modal.getInstance(document.getElementById('editStoreModal'))?.hide();
            document.getElementById('saveGuardMessage').innerHTML = `<p>${json.message}</p>`;
            new bootstrap.Modal(document.getElementById('saveGuardModal')).show();
            return;
        }

        if (json.success) {
            toastr.success(json.message ?? 'Saved.');
            bootstrap.Modal.getInstance(document.getElementById('editStoreModal'))?.hide();
            location.reload();
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    }

    document.getElementById('editStoreSaveBtn')?.addEventListener('click', function () {
        const storeId = document.getElementById('editStoreId').value;
        if (!storeId) return;
        saveStore(storeId, collectModalData());
    });

    document.getElementById('saveGuardConfirm')?.addEventListener('click', function () {
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
