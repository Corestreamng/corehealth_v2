@extends('admin.layouts.app')
@section('title', 'Lane Policy Matrix')
@section('page_name', 'Configuration')
@section('subpage_name', 'Store Lane Policy Matrix')

@section('content')
{{--
    Lane Policy Matrix Admin Page
    Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
    § 9.1 Section B — Lane Policy Matrix
      Grid with source_role × destination_role cells.
      Each cell shows: Allowed toggle, Approval Level select, Notes.
      Save is per-cell via AJAX (Plan §9.4 save guard for disabling an active lane).
    § 5.1 — Lane matrix row definitions and defaults
    § 11   — Permission gate: store-governance.view / store-governance.manage
--}}
<div id="content-wrapper">
    <div class="container-fluid">

        <div class="card-modern mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-project-diagram me-2 text-primary"></i>
                    Lane Policy Matrix
                </h5>
                <a href="{{ route('inventory.config.store-governance.index') }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Store Catalog
                </a>
            </div>
            <div class="card-body pb-1">
                <p class="text-muted small mb-0">
                    Rows = <strong>source</strong> store role &nbsp;|&nbsp;
                    Columns = <strong>destination</strong> store role.
                    Cells not yet saved use the default-deny rule (Plan §5.1).
                    Click a cell to edit; click <em>Save Cell</em> to persist.
                </p>
            </div>
        </div>

        {{-- Legend --}}
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <span><span class="badge bg-success">Allowed</span> — lane is open</span>
            <span><span class="badge bg-danger">Blocked</span> — lane is denied (default)</span>
            <span><span class="badge bg-warning text-dark">Pending changes</span> — unsaved edits</span>
            <span>Approval: <code>none</code> | <code>manager</code> | <code>admin</code></span>
        </div>

        {{-- Matrix grid — rendered by JS from AJAX data --}}
        <div class="card-modern">
            <div class="card-body p-0">
                <div id="laneMatrixContainer" class="table-responsive">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2 text-muted">Loading matrix…</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Save Guard Modal (Plan §9.4) --}}
<div class="modal fade" id="laneGuardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Active Requisitions Warning</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="laneGuardMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="laneGuardConfirm">Save Anyway</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    /**
     * Lane Policy Matrix JS
     * Plan §9.1 Section B, §5.1, §9.4
     */
    const MATRIX_GET_URL = '{{ route("inventory.config.store-governance.lane-matrix") }}';
    const MATRIX_POST_URL = '{{ route("inventory.config.store-governance.lane-matrix.update") }}';
    const CAN_MANAGE     = @json(auth()->user()?->can('store-governance.manage') ?? false);
    const CSRF           = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const ROLE_LABELS = {
        central:              'Central',
        pharmacy_hub:         'Pharmacy Hub',
        pharmacy_satellite:   'Pharmacy Satellite',
        department:           'Department',
        ward:                 'Ward',
        other:                'Other',
    };

    const APPROVAL_LEVELS = ['none', 'manager', 'admin'];

    let matrixData = [];
    let pendingCell = null;

    // ── Fetch matrix and render ──────────────────────────────────────────
    async function loadMatrix() {
        const res  = await fetch(MATRIX_GET_URL, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();
        matrixData = json.matrix ?? [];
        renderMatrix(matrixData);
    }

    function renderMatrix(matrix) {
        const roles = Object.keys(ROLE_LABELS);
        let html = `<table class="table table-bordered table-sm mb-0" id="laneTable">
            <thead class="table-light">
                <tr>
                    <th class="bg-light">Source \\ Dest</th>
                    ${roles.map(r => `<th class="text-center small">${ROLE_LABELS[r]}</th>`).join('')}
                </tr>
            </thead>
            <tbody>`;

        roles.forEach(src => {
            html += `<tr><th class="bg-light small fw-semibold">${ROLE_LABELS[src]}</th>`;
            roles.forEach(dst => {
                if (src === dst) {
                    html += `<td class="bg-secondary bg-opacity-10 text-center text-muted">—</td>`;
                    return;
                }
                const cell = matrix.find(c => c.source_role === src && c.destination_role === dst);
                const allowed  = cell?.allowed ?? false;
                const approval = cell?.requires_approval_level ?? 'none';
                const notes    = cell?.notes ?? '';

                const badgeCls = allowed ? 'bg-success' : 'bg-danger';
                const badgeTxt = allowed ? 'Allowed' : 'Blocked';

                if (CAN_MANAGE) {
                    html += `<td class="p-1 lane-cell" data-src="${src}" data-dst="${dst}">
                        <div class="d-flex flex-column gap-1">
                            <div class="form-check form-switch mb-0 d-flex justify-content-center">
                                <input class="form-check-input lane-allowed"
                                       type="checkbox"
                                       ${allowed ? 'checked' : ''}>
                            </div>
                            <select class="form-select form-select-sm lane-approval">
                                ${APPROVAL_LEVELS.map(lvl =>
                                    `<option value="${lvl}" ${approval === lvl ? 'selected' : ''}>${lvl}</option>`
                                ).join('')}
                            </select>
                            <input type="text" class="form-control form-control-sm lane-notes"
                                   placeholder="Notes…" value="${notes}">
                            <button class="btn btn-xs btn-primary lane-save-btn mt-1">
                                <i class="fas fa-save me-1"></i>Save
                            </button>
                        </div>
                    </td>`;
                } else {
                    html += `<td class="text-center p-1">
                        <span class="badge ${badgeCls}">${badgeTxt}</span>
                        <div class="small text-muted">${approval !== 'none' ? approval : ''}</div>
                    </td>`;
                }
            });
            html += `</tr>`;
        });

        html += `</tbody></table>`;
        document.getElementById('laneMatrixContainer').innerHTML = html;
    }

    // ── Save cell ────────────────────────────────────────────────────────
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.lane-save-btn');
        if (! btn) return;

        const cell  = btn.closest('.lane-cell');
        const src   = cell.dataset.src;
        const dst   = cell.dataset.dst;
        const body  = {
            source_role:             src,
            destination_role:        dst,
            allowed:                 cell.querySelector('.lane-allowed').checked ? 1 : 0,
            requires_approval_level: cell.querySelector('.lane-approval').value,
            notes:                   cell.querySelector('.lane-notes').value,
        };

        await postLane(body, false);
    });

    async function postLane(body, forceSave) {
        if (forceSave) body.force_save = 1;

        const res  = await fetch(MATRIX_POST_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body:    JSON.stringify(body),
        });
        const json = await res.json();

        if (res.status === 409 && json.save_guard) {
            pendingCell = body;
            document.getElementById('laneGuardMessage').innerHTML = `<p>${json.message}</p>`;
            new bootstrap.Modal(document.getElementById('laneGuardModal')).show();
            return;
        }

        if (json.success) {
            toastr.success('Lane policy saved.');
            loadMatrix();
        } else {
            toastr.error(json.message ?? 'Save failed.');
        }
    }

    document.getElementById('laneGuardConfirm').addEventListener('click', function () {
        bootstrap.Modal.getInstance(document.getElementById('laneGuardModal')).hide();
        if (pendingCell) {
            postLane(pendingCell, true);
            pendingCell = null;
        }
    });

    // Init
    loadMatrix();
})();
</script>
@endsection
