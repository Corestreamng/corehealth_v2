@extends('admin.layouts.app')
@section('title', 'Context Resolution Rules')
@section('page_name', 'Configuration')
@section('subpage_name', 'Context Resolution Rules')

@section('content')
{{--
    Context Resolution Rules Admin Page
    Plan §9.3, §10 StoreContextResolver 5-step chain
    P0 BUG FIX: $u->firstname (not $u->first_name)
    Features: 5-step stepper, fallback card at top of right col,
    unconfigured-roles warning, edit buttons on rules (icon+label stacked),
    enriched test trace, candidate stores display.
--}}
<div id="content-wrapper">
    <div class="container-fluid">

        {{-- ── Shared Nav Tabs ──────────────────────────────────────────── --}}
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('inventory.config.store-governance.index') }}">
                    <i class="fas fa-store me-1"></i> Store Catalog
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('inventory.config.store-governance.lane-matrix') }}">
                    <i class="fas fa-project-diagram me-1"></i> Lane Matrix
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active fw-semibold" href="{{ route('inventory.config.store-governance.context-rules') }}">
                    <i class="fas fa-filter me-1"></i> Resolution Rules
                </a>
            </li>
        </ul>

        {{-- ── 5-Step Resolver Stepper ──────────────────────────────────── --}}
        <div class="card-modern mb-3">
            <div class="card-body py-2">
                <p class="small text-muted mb-2 fw-semibold">
                    <i class="fas fa-sitemap me-1"></i>
                    StoreContextResolver — 5-step resolution chain (Plan §10):
                </p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <i class="fas fa-link me-1"></i>1. Session Explicit
                    </span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-primary rounded-pill px-3 py-2">
                        <i class="fas fa-moon me-1"></i>2. Active Shift
                    </span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                        <i class="fas fa-building me-1"></i>3. Dept Override
                    </span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                        <i class="fas fa-user-tag me-1"></i>4. Staff Default
                    </span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                        <i class="fas fa-shield-alt me-1"></i>5. Role Default
                    </span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-danger rounded-pill px-3 py-2">
                        <i class="fas fa-random me-1"></i>Fallback
                    </span>
                </div>
                <p class="small text-muted mt-2 mb-0">
                    Steps 1–2 are automatic. Steps 3 &amp; 5 are configured below.
                    Fallback behavior is set in the right column.
                </p>
            </div>
        </div>

        {{-- ── Unconfigured Roles Warning ───────────────────────────────── --}}
        @php
            $configuredRoles = $roleRules->pluck('user_role')->toArray();
            $bypassRoles = $rolesWithCandidateAll->toArray();
            $unconfigured = collect($spatieRoles)
                ->filter(fn($r) => !in_array($r, $configuredRoles) && !in_array($r, $bypassRoles))
                ->values();
        @endphp
        @if($unconfigured->isNotEmpty())
        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
            <i class="fas fa-exclamation-triangle mt-1"></i>
            <div>
                <strong>Unconfigured roles</strong> — the following roles have no Role Default rule
                and no <code>stores.candidate-all</code> permission. They may fail to resolve a store:
                <div class="mt-1">
                    @foreach($unconfigured as $r)
                    <code class="me-1">{{ $r }}</code>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="row g-3">

            {{-- ── Col 1: Rules Tables ─────────────────────────────────── --}}
            <div class="col-lg-7">

                {{-- Role defaults (Step 5) --}}
                <div class="card-modern mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <span class="badge bg-warning text-dark me-1">5</span>
                            Role Defaults
                            <small class="text-muted">(Step 5)</small>
                        </h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roleRuleModal"
                                data-mode="add">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                        @endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Resolved Store</th>
                                    <th>Notes</th>
                                    @can('store-governance.manage')<th class="text-center" style="width:80px">Actions</th>@endcan
                                </tr>
                            </thead>
                            <tbody id="roleRulesBody">
                                @forelse($roleRules as $rule)
                                <tr data-rule-id="{{ $rule->id }}">
                                    <td><code>{{ $rule->user_role }}</code></td>
                                    <td>
                                        @if($rule->store)
                                            <span class="badge bg-info text-dark">{{ $rule->store->store_name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $rule->notes }}</td>
                                    @can('store-governance.manage')
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <button class="btn btn-xs btn-outline-primary rule-edit-btn"
                                                    data-id="{{ $rule->id }}"
                                                    data-type="role_default"
                                                    data-role="{{ $rule->user_role }}"
                                                    data-store="{{ $rule->store_id }}"
                                                    data-notes="{{ $rule->notes }}">
                                                <i class="fas fa-pencil-alt d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Edit</span>
                                            </button>
                                            <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                    data-id="{{ $rule->id }}">
                                                <i class="fas fa-trash d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                    @endcan
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No role defaults configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Department Overrides (Step 3) --}}
                <div class="card-modern mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <span class="badge bg-warning text-dark me-1">3</span>
                            Department Overrides
                            <small class="text-muted">(Step 3)</small>
                        </h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#deptRuleModal"
                                data-mode="add">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                        @endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Resolved Store</th>
                                    <th>Notes</th>
                                    @can('store-governance.manage')<th class="text-center" style="width:80px">Actions</th>@endcan
                                </tr>
                            </thead>
                            <tbody id="deptRulesBody">
                                @forelse($deptRules as $rule)
                                <tr data-rule-id="{{ $rule->id }}">
                                    <td>{{ $rule->department?->name ?? '—' }}</td>
                                    <td>
                                        @if($rule->store)
                                            <span class="badge bg-info text-dark">{{ $rule->store->store_name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $rule->notes }}</td>
                                    @can('store-governance.manage')
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <button class="btn btn-xs btn-outline-primary rule-edit-btn"
                                                    data-id="{{ $rule->id }}"
                                                    data-type="department_override"
                                                    data-dept="{{ $rule->department_id }}"
                                                    data-store="{{ $rule->store_id }}"
                                                    data-notes="{{ $rule->notes }}">
                                                <i class="fas fa-pencil-alt d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Edit</span>
                                            </button>
                                            <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                    data-id="{{ $rule->id }}">
                                                <i class="fas fa-trash d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                    @endcan
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No department overrides configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Type Bucket Rules --}}
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-layer-group me-1 text-info"></i>
                            Type Bucket Rules
                            <small class="text-muted">(Candidate set expansion)</small>
                        </h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bucketRuleModal"
                                data-mode="add">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                        @endcan
                    </div>
                    <div class="card-body py-2">
                        <p class="text-muted small mb-0">
                            Grant a role access to all stores of a given type in their candidate set.
                            Use <code>all</code> to mirror the <code>stores.candidate-all</code> permission.
                        </p>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Type Filter</th>
                                    <th>Notes</th>
                                    @can('store-governance.manage')<th class="text-center" style="width:80px">Actions</th>@endcan
                                </tr>
                            </thead>
                            <tbody id="bucketRulesBody">
                                @forelse($bucketRules as $rule)
                                <tr data-rule-id="{{ $rule->id }}">
                                    <td><code>{{ $rule->user_role }}</code></td>
                                    <td>
                                        @php
                                            $badgeColor = match($rule->type_filter) {
                                                'all'        => 'danger',
                                                'pharmacy'   => 'primary',
                                                'ward'       => 'success',
                                                'department' => 'warning',
                                                default      => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $badgeColor }} {{ $badgeColor === 'warning' ? 'text-dark' : '' }}">
                                            {{ $rule->type_filter ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">{{ $rule->notes }}</td>
                                    @can('store-governance.manage')
                                    <td>
                                        <div class="d-flex flex-column gap-1 align-items-center">
                                            <button class="btn btn-xs btn-outline-primary rule-edit-btn"
                                                    data-id="{{ $rule->id }}"
                                                    data-type="type_bucket"
                                                    data-role="{{ $rule->user_role }}"
                                                    data-filter="{{ $rule->type_filter }}"
                                                    data-notes="{{ $rule->notes }}">
                                                <i class="fas fa-pencil-alt d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Edit</span>
                                            </button>
                                            <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                    data-id="{{ $rule->id }}">
                                                <i class="fas fa-trash d-block mx-auto mb-1"></i>
                                                <span class="d-block" style="font-size:0.7rem">Delete</span>
                                            </button>
                                        </div>
                                    </td>
                                    @endcan
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">No type bucket rules configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>{{-- /col-lg-7 --}}

            {{-- ── Col 2: Fallback + Test Panel ────────────────────────── --}}
            <div class="col-lg-5">

                {{-- Fallback Behavior (at top — most critical config) --}}
                <div class="card-modern mb-3 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0">
                            <i class="fas fa-random me-1 text-warning"></i>
                            Fallback Behavior
                            <span class="badge bg-danger ms-1">Fallback</span>
                        </h6>
                        <small class="text-muted">What happens when all 5 resolver steps return null.</small>
                    </div>
                    <div class="card-body">
                        @can('store-governance.manage')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fallback Action</label>
                            <select class="form-select" id="fallbackActionSelect">
                                <option value="block"            {{ ($fallbackRule?->fallback_action ?? 'block') === 'block'            ? 'selected' : '' }}>
                                    Block — 403, user must manually pick a store
                                </option>
                                <option value="allow_manual"     {{ ($fallbackRule?->fallback_action ?? '') === 'allow_manual'     ? 'selected' : '' }}>
                                    Allow Manual — show store-picker banner
                                </option>
                                <option value="use_role_default" {{ ($fallbackRule?->fallback_action ?? '') === 'use_role_default' ? 'selected' : '' }}>
                                    Use Role Default — fall through to Step 5
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="fallbackNotes"
                                   value="{{ $fallbackRule?->notes ?? '' }}" placeholder="Optional note…">
                        </div>
                        <button class="btn btn-warning btn-sm" id="saveFallbackBtn">
                            <i class="fas fa-save me-1"></i> Save Fallback Rule
                        </button>
                        @else
                        <p class="mb-0">
                            <strong>Current:</strong>
                            <span class="badge bg-secondary">{{ $fallbackRule?->fallback_action ?? 'block' }}</span>
                        </p>
                        @endcan
                    </div>
                </div>

                {{-- Test Resolution Panel (Plan §9.3 Section F) --}}
                <div class="card-modern">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-flask me-1 text-info"></i>
                            Test Resolution
                        </h6>
                        <small class="text-muted">Simulate which store resolves for a given user + context.</small>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">User</label>
                            <select class="form-select form-select-sm" id="testUserId">
                                <option value="">— select user —</option>
                                {{-- P0 BUG FIX: $u->firstname (not $u->first_name) --}}
                                @foreach(\App\Models\User::orderBy('surname')->get() as $u)
                                <option value="{{ $u->id }}">{{ $u->surname }} {{ $u->firstname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Ward (simulates active shift)</label>
                            <select class="form-select form-select-sm" id="testWardId">
                                <option value="">— no ward —</option>
                                @foreach(\App\Models\Ward::orderBy('name')->get() as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="testShiftActive">
                            <label class="form-check-label small" for="testShiftActive">Simulate active shift</label>
                        </div>
                        <button class="btn btn-info btn-sm text-white w-100" id="testResolveBtn">
                            <i class="fas fa-play me-1"></i> Resolve
                        </button>

                        <div id="testResolveResult" class="mt-3" style="display:none">
                            <hr>
                            <p class="mb-1 fw-semibold">Resolved Store:</p>
                            <div id="resolvedStoreBadge" class="mb-2"></div>

                            <p class="mb-1 small text-muted fw-semibold">Candidate Stores:</p>
                            <div id="candidateStoresList" class="mb-2"></div>

                            <p class="mb-1 small text-muted fw-semibold">Resolution Trace:</p>
                            <ol class="small ps-3 mb-0" id="resolutionTrace"></ol>
                        </div>
                    </div>
                </div>

            </div>{{-- /col-lg-5 --}}
        </div>{{-- /row --}}

    </div>
</div>

{{-- Role Rule Modal --}}
@can('store-governance.manage')
<div class="modal fade" id="roleRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="roleRuleForm">
            @csrf
            <input type="hidden" name="rule_type" value="role_default">
            <input type="hidden" id="roleRuleEditId" name="edit_id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roleRuleModalTitle">Add Role Default</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" id="roleRuleRoleSelect" required>
                            <option value="">— select role —</option>
                            @foreach($spatieRoles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store</label>
                        <select class="form-select" name="store_id" id="roleRuleStoreSelect">
                            <option value="">— none —</option>
                            @foreach($stores as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" id="roleRuleNotes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Department Rule Modal --}}
<div class="modal fade" id="deptRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="deptRuleForm">
            @csrf
            <input type="hidden" name="rule_type" value="department_override">
            <input type="hidden" id="deptRuleEditId" name="edit_id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deptRuleModalTitle">Add Department Override</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" id="deptRuleDeptSelect" required>
                            <option value="">— select dept —</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store</label>
                        <select class="form-select" name="store_id" id="deptRuleStoreSelect">
                            <option value="">— none —</option>
                            @foreach($stores as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" id="deptRuleNotes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Type Bucket Rule Modal --}}
<div class="modal fade" id="bucketRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="bucketRuleForm">
            @csrf
            <input type="hidden" name="rule_type" value="type_bucket">
            <input type="hidden" id="bucketRuleEditId" name="edit_id" value="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bucketRuleModalTitle">Add Type Bucket Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Grants a role access to all active stores of the selected type in their candidate set.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" id="bucketRuleRoleSelect" required>
                            <option value="">— select role —</option>
                            @foreach($spatieRoles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type Filter</label>
                        <select class="form-select" name="type_filter" id="bucketRuleFilterSelect" required>
                            <option value="all">All active stores</option>
                            <option value="pharmacy">Pharmacy stores (hub + satellite)</option>
                            <option value="ward">Ward stores</option>
                            <option value="department">Department stores</option>
                        </select>
                        <div class="form-text">Use <strong>All</strong> to give the role visibility of every store.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" id="bucketRuleNotes" placeholder="Optional note…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection

@section('scripts')
<script>
(function () {
    const SAVE_URL   = '{{ route("inventory.config.store-governance.context-rules.save") }}';
    const DELETE_URL = id => `/inventory/config/store-governance/context-rules/${id}`;
    const TEST_URL   = '{{ route("inventory.config.store-governance.context-rules.test") }}';
    const CSRF       = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function postJSON(url, body) {
        const res  = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(body),
        });
        return [res.status, await res.json()];
    }

    // ── Save Fallback Rule ───────────────────────────────────────────────
    document.getElementById('saveFallbackBtn')?.addEventListener('click', async function () {
        const [status, json] = await postJSON(SAVE_URL, {
            rule_type:       'fallback_behavior',
            fallback_action: document.getElementById('fallbackActionSelect').value,
            notes:           document.getElementById('fallbackNotes').value,
        });
        if (json.success) toastr.success('Fallback rule saved.');
        else toastr.error(json.message ?? 'Save failed.');
    });

    // ── Edit button → pre-fill modal ─────────────────────────────────────
    document.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.rule-edit-btn');
        if (!editBtn) return;

        const type = editBtn.dataset.type;

        if (type === 'role_default') {
            document.getElementById('roleRuleModalTitle').textContent = 'Edit Role Default';
            document.getElementById('roleRuleEditId').value           = editBtn.dataset.id;
            document.getElementById('roleRuleRoleSelect').value       = editBtn.dataset.role ?? '';
            document.getElementById('roleRuleStoreSelect').value      = editBtn.dataset.store ?? '';
            document.getElementById('roleRuleNotes').value            = editBtn.dataset.notes ?? '';
            new bootstrap.Modal(document.getElementById('roleRuleModal')).show();
        } else if (type === 'department_override') {
            document.getElementById('deptRuleModalTitle').textContent = 'Edit Department Override';
            document.getElementById('deptRuleEditId').value           = editBtn.dataset.id;
            document.getElementById('deptRuleDeptSelect').value       = editBtn.dataset.dept ?? '';
            document.getElementById('deptRuleStoreSelect').value      = editBtn.dataset.store ?? '';
            document.getElementById('deptRuleNotes').value            = editBtn.dataset.notes ?? '';
            new bootstrap.Modal(document.getElementById('deptRuleModal')).show();
        } else if (type === 'type_bucket') {
            document.getElementById('bucketRuleModalTitle').textContent = 'Edit Type Bucket Rule';
            document.getElementById('bucketRuleEditId').value           = editBtn.dataset.id;
            document.getElementById('bucketRuleRoleSelect').value       = editBtn.dataset.role   ?? '';
            document.getElementById('bucketRuleFilterSelect').value     = editBtn.dataset.filter ?? '';
            document.getElementById('bucketRuleNotes').value            = editBtn.dataset.notes  ?? '';
            new bootstrap.Modal(document.getElementById('bucketRuleModal')).show();
        }
    });

    // Reset modal title/id on "Add" button clicks
    document.querySelectorAll('[data-bs-target="#roleRuleModal"]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.dataset.mode === 'add') {
                document.getElementById('roleRuleModalTitle').textContent = 'Add Role Default';
                document.getElementById('roleRuleEditId').value           = '';
                document.getElementById('roleRuleForm').reset();
            }
        });
    });
    document.querySelectorAll('[data-bs-target="#deptRuleModal"]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.dataset.mode === 'add') {
                document.getElementById('deptRuleModalTitle').textContent = 'Add Department Override';
                document.getElementById('deptRuleEditId').value           = '';
                document.getElementById('deptRuleForm').reset();
            }
        });
    });
    document.querySelectorAll('[data-bs-target="#bucketRuleModal"]').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.dataset.mode === 'add') {
                document.getElementById('bucketRuleModalTitle').textContent = 'Add Type Bucket Rule';
                document.getElementById('bucketRuleEditId').value           = '';
                document.getElementById('bucketRuleForm').reset();
            }
        });
    });

    // ── Save Role Rule Modal ─────────────────────────────────────────────
    document.getElementById('roleRuleForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const [status, json] = await postJSON(SAVE_URL, Object.fromEntries(fd));
        if (json.success) {
            toastr.success('Role rule saved.');
            bootstrap.Modal.getInstance(document.getElementById('roleRuleModal')).hide();
            location.reload();
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    });

    // ── Save Dept Rule Modal ─────────────────────────────────────────────
    document.getElementById('deptRuleForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const [status, json] = await postJSON(SAVE_URL, Object.fromEntries(fd));
        if (json.success) {
            toastr.success('Department rule saved.');
            bootstrap.Modal.getInstance(document.getElementById('deptRuleModal')).hide();
            location.reload();
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    });

    // ── Save Type Bucket Rule Modal ──────────────────────────────────────
    document.getElementById('bucketRuleForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const [status, json] = await postJSON(SAVE_URL, Object.fromEntries(fd));
        if (json.success) {
            toastr.success('Type bucket rule saved.');
            bootstrap.Modal.getInstance(document.getElementById('bucketRuleModal')).hide();
            location.reload();
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    });

    // ── Delete rule ──────────────────────────────────────────────────────
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.rule-delete-btn');
        if (!btn) return;
        if (!confirm('Delete this rule?')) return;

        const res  = await fetch(DELETE_URL(btn.dataset.id), {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        });
        const json = await res.json();
        if (json.success) {
            toastr.success('Rule deleted.');
            btn.closest('tr').remove();
        } else {
            toastr.error(json.message ?? 'Delete failed.');
        }
    });

    // ── Test Resolution Panel ────────────────────────────────────────────
    document.getElementById('testResolveBtn')?.addEventListener('click', async function () {
        const userId  = document.getElementById('testUserId').value;
        const wardId  = document.getElementById('testWardId').value;
        const shifted = document.getElementById('testShiftActive').checked;

        if (!userId) { toastr.warning('Please select a user.'); return; }

        const [status, json] = await postJSON(TEST_URL, {
            user_id:      userId,
            ward_id:      wardId || null,
            shift_active: shifted ? 1 : 0,
        });

        const resultDiv = document.getElementById('testResolveResult');
        resultDiv.style.display = 'block';

        if (json.resolved_store) {
            document.getElementById('resolvedStoreBadge').innerHTML =
                `<span class="badge bg-success fs-6">${json.resolved_store.name}</span>
                 <span class="badge bg-secondary ms-1">${json.resolved_store.role_label ?? ''}</span>`;
        } else {
            document.getElementById('resolvedStoreBadge').innerHTML =
                `<span class="badge bg-danger">Unresolved — fallback triggered</span>`;
        }

        const candidates = json.candidate_stores ?? [];
        document.getElementById('candidateStoresList').innerHTML = candidates.length
            ? candidates.map(s =>
                `<span class="badge bg-light text-dark border me-1 mb-1">${s.name} <small class="text-muted">[${s.role}]</small></span>`
              ).join('')
            : '<span class="text-muted small">None</span>';

        const trace = json.resolution_steps ?? [];
        document.getElementById('resolutionTrace').innerHTML = trace.length
            ? trace.map((step, i) => {
                const isResolved = typeof step === 'object' ? step.resolved : false;
                const text       = typeof step === 'object' ? step.text     : step;
                const cls        = isResolved ? 'text-success fw-semibold' : 'text-muted';
                const icon       = isResolved ? '✓' : '○';
                return `<li class="${cls}">${icon} ${text}</li>`;
              }).join('')
            : '<li class="text-muted">No trace available.</li>';
    });
})();
</script>
@endsection
