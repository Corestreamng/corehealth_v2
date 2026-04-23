@extends('admin.layouts.app')
@section('title', 'Context Resolution Rules')
@section('page_name', 'Configuration')
@section('subpage_name', 'Context Resolution Rules')

@section('content')
{{--
    Context Resolution Rules Admin Page
    Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
    § 9.3  Admin Module: Context Resolution Rules
      Three sub-sections:
        Role defaults        — which store to resolve for each Spatie role
        Department overrides — per-department store override
        Fallback behavior    — what happens if none of steps 1-4 match (block|allow_manual|use_role_default)
    § 10   StoreContextResolver 5-step chain (this page configures steps 3 and 5)
    § 9.3 Section F — Test Resolution panel
    § 11   Permission gate: store-governance.view / store-governance.manage
--}}
<div id="content-wrapper">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="card-modern mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2 text-primary"></i>
                    Context Resolution Rules
                </h5>
                <a href="{{ route('inventory.config.store-governance.index') }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Store Catalog
                </a>
            </div>
            <div class="card-body pb-1">
                <p class="text-muted small mb-0">
                    Configure how <strong>StoreContextResolver</strong> resolves a store for each user (Plan §10).
                    Steps 1–2 (session explicit, active shift) are automatic.
                    Steps 3–5 are configured here.
                </p>
            </div>
        </div>

        <div class="row g-3">

            {{-- ── Col 1: Role Defaults + Department Overrides ─────────── --}}
            <div class="col-lg-7">

                {{-- Role defaults (Step 5 in resolver chain — Plan §10) --}}
                <div class="card-modern mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Role Defaults <small class="text-muted">(Step 5)</small></h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roleRuleModal">
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
                                    @can('store-governance.manage')<th style="width:80px">Del</th>@endcan
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
                                        <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                data-id="{{ $rule->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

                {{-- Department overrides (Step 3 in resolver chain — Plan §10) --}}
                <div class="card-modern">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Department Overrides <small class="text-muted">(Step 3)</small></h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#deptRuleModal">
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
                                    @can('store-governance.manage')<th style="width:80px">Del</th>@endcan
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
                                        <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                data-id="{{ $rule->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

                {{-- Type Bucket Rules (Step E — Option B) --}}
                <div class="card-modern mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            Type Bucket Rules
                            <small class="text-muted">(Candidate set expansion)</small>
                        </h6>
                        @can('store-governance.manage')
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bucketRuleModal">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                        @endcan
                    </div>
                    <div class="card-body py-2">
                        <p class="text-muted small mb-0">
                            Grant a role access to all stores of a given type in their candidate set.
                            Use <code>all</code> to mirror the <code>stores.candidate-all</code> permission
                            but managed here instead of via role assignment.
                        </p>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Type Filter</th>
                                    <th>Notes</th>
                                    @can('store-governance.manage')<th style="width:80px">Del</th>@endcan
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
                                        <span class="badge bg-{{ $badgeColor }}">{{ $rule->type_filter ?? '—' }}</span>
                                    </td>
                                    <td class="small text-muted">{{ $rule->notes }}</td>
                                    @can('store-governance.manage')
                                    <td>
                                        <button class="btn btn-xs btn-outline-danger rule-delete-btn"
                                                data-id="{{ $rule->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

                {{-- Fallback Behavior (Plan §10 step 5 fallback) --}}
                <div class="card-modern mb-3 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h6 class="mb-0">
                            <i class="fas fa-random me-1 text-warning"></i>
                            Fallback Behavior
                        </h6>
                        <small class="text-muted">What happens when resolver returns null.</small>
                    </div>
                    <div class="card-body">
                        @can('store-governance.manage')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Fallback Action</label>
                            <select class="form-select" id="fallbackActionSelect">
                                <option value="block"            {{ ($fallbackRule?->fallback_action ?? 'block') === 'block'            ? 'selected' : '' }}>
                                    Block — 403 error, user must manually pick a store
                                </option>
                                <option value="allow_manual"     {{ ($fallbackRule?->fallback_action ?? '') === 'allow_manual'     ? 'selected' : '' }}>
                                    Allow Manual — show store-picker banner in workbench
                                </option>
                                <option value="use_role_default" {{ ($fallbackRule?->fallback_action ?? '') === 'use_role_default' ? 'selected' : '' }}>
                                    Use Role Default — fall through to Step 5 role rule
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" id="fallbackNotes"
                                   value="{{ $fallbackRule?->notes ?? '' }}"
                                   placeholder="Optional note…">
                        </div>
                        <button class="btn btn-warning btn-sm" id="saveFallbackBtn">
                            <i class="fas fa-save me-1"></i>Save Fallback Rule
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
                            <label class="form-label small">User</label>
                            <select class="form-select form-select-sm" id="testUserId">
                                <option value="">— select user —</option>
                                {{-- populated via AJAX or static from the page if users list available --}}
                                @foreach(\App\Models\User::orderBy('surname')->get() as $u)
                                <option value="{{ $u->id }}">{{ $u->surname }} {{ $u->first_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Ward (optional — simulates active shift)</label>
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
                        <button class="btn btn-info btn-sm text-white" id="testResolveBtn">
                            <i class="fas fa-play me-1"></i> Resolve
                        </button>

                        <div id="testResolveResult" class="mt-3" style="display:none">
                            <hr>
                            <p class="mb-1"><strong>Resolved Store:</strong></p>
                            <div id="resolvedStoreBadge"></div>
                            <p class="mt-2 mb-1 small text-muted"><strong>Candidate Stores:</strong></p>
                            <div id="candidateStoresList"></div>
                            <p class="mt-2 mb-1 small text-muted"><strong>Resolution Trace:</strong></p>
                            <ol class="small ps-3" id="resolutionTrace"></ol>
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Role Default</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" required>
                            <option value="">— select role —</option>
                            @foreach($spatieRoles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store</label>
                        <select class="form-select" name="store_id">
                            <option value="">— none —</option>
                            @foreach($stores as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Department Override</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id" required>
                            <option value="">— select dept —</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store</label>
                        <select class="form-select" name="store_id">
                            <option value="">— none —</option>
                            @foreach($stores as $s)
                            <option value="{{ $s->id }}">{{ $s->store_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
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
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Type Bucket Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Grants a role access to all active stores of the selected type
                        in their candidate set.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" required>
                            <option value="">— select role —</option>
                            @foreach($spatieRoles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type Filter</label>
                        <select class="form-select" name="type_filter" required>
                            <option value="all">All active stores</option>
                            <option value="pharmacy">Pharmacy stores (hub + satellite)</option>
                            <option value="ward">Ward stores</option>
                            <option value="department">Department stores</option>
                        </select>
                        <div class="form-text">Use <strong>All</strong> to give the role visibility of every store.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" placeholder="Optional note…">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
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
        if (! btn) return;
        if (! confirm('Delete this rule?')) return;

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

    // ── Test Resolution Panel (Plan §9.3 Section F) ──────────────────────
    document.getElementById('testResolveBtn')?.addEventListener('click', async function () {
        const userId  = document.getElementById('testUserId').value;
        const wardId  = document.getElementById('testWardId').value;
        const shifted = document.getElementById('testShiftActive').checked;

        if (! userId) { toastr.warning('Please select a user.'); return; }

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
                 <span class="badge bg-secondary ms-1">${json.resolved_store.role_label}</span>`;
        } else {
            document.getElementById('resolvedStoreBadge').innerHTML =
                `<span class="badge bg-danger">Unresolved (fallback triggered)</span>`;
        }

        const candidates = json.candidate_stores ?? [];
        document.getElementById('candidateStoresList').innerHTML = candidates.length
            ? candidates.map(s =>
                `<span class="badge bg-light text-dark border me-1 mb-1">${s.name} <small class="text-muted">[${s.role}]</small></span>`
              ).join('')
            : '<span class="text-muted small">None</span>';

        const trace = json.resolution_steps ?? [];
        document.getElementById('resolutionTrace').innerHTML =
            trace.map(step => `<li>${step}</li>`).join('') || '<li class="text-muted">No trace available.</li>';
    });
})();
</script>
@endsection
