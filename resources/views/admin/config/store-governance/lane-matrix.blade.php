@extends('admin.layouts.app')
@section('title', 'Lane Policy Matrix')
@section('page_name', 'Configuration')
@section('subpage_name', 'Store Lane Policy Matrix')

@section('content')
{{--
    Lane Policy Matrix Admin Page
    Plan §9.1 Section B, §5.1, §9.4
    Compact mode by default. Edit Mode toggle unlocks inline controls.
    Role axis tooltips. Supply chain hierarchy legend.
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
                <a class="nav-link active fw-semibold" href="{{ route('inventory.config.store-governance.lane-matrix') }}">
                    <i class="fas fa-project-diagram me-1"></i> Lane Matrix
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('inventory.config.store-governance.context-rules') }}">
                    <i class="fas fa-filter me-1"></i> Resolution Rules
                </a>
            </li>
        </ul>

        {{-- ── Supply Chain Hierarchy Legend ──────────────────────────── --}}
        <div class="card-modern mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <span class="fw-semibold small text-muted">Supply Chain:</span>
                    <span class="badge" style="background:#6610f2">Central</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-primary">Hub</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-info text-dark">Satellite</span>
                    <span class="text-muted small ms-2">|</span>
                    <span class="badge bg-primary">Hub</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge" style="background:#fd7e14">Department</span>
                    <i class="fas fa-arrow-right text-muted"></i>
                    <span class="badge bg-success">Ward</span>
                </div>
            </div>
        </div>

        {{-- ── Legend + Edit Mode Toggle ─────────────────────────────────── --}}
        <div class="d-flex flex-wrap gap-3 mb-3 align-items-center justify-content-between">
            <div class="d-flex gap-3 flex-wrap">
                <span><span class="badge bg-success">✓ Allowed</span> — lane is open</span>
                <span><span class="badge bg-danger">✗ Blocked</span> — lane is denied (default)</span>
                <span><code>Approval</code> sub-label shown when not "none"</span>
            </div>
            @can('store-governance.manage')
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="editModeToggle">
                <label class="form-check-label fw-semibold" for="editModeToggle">
                    <i class="fas fa-edit me-1"></i> Edit Mode
                </label>
            </div>
            @endcan
        </div>

        {{-- Matrix grid — rendered by JS --}}
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
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Active Requisitions Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="laneGuardMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="laneGuardConfirm">
                    <i class="fas fa-exclamation me-1"></i> Save Anyway
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
     * Lane Policy Matrix JS
     * Plan §9.1 Section B, §5.1, §9.4
     * Compact badges by default. Edit Mode toggle shows inline controls.
     */
    const MATRIX_GET_URL  = '{{ route("inventory.config.store-governance.lane-matrix") }}';
    const MATRIX_POST_URL = '{{ route("inventory.config.store-governance.lane-matrix.update") }}';
    const CAN_MANAGE      = @json(auth()->user()?->can('store-governance.manage') ?? false);
    const CSRF            = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const ROLE_LABELS = {
        central:             'Central',
        pharmacy_hub:        'Pharmacy Hub',
        pharmacy_satellite:  'Pharmacy Satellite',
        department:          'Department',
        ward:                'Ward',
        other:               'Other',
    };

    const ROLE_TOOLTIPS = {
        central:             'Central warehouse — top of supply chain, issues to hubs',
        pharmacy_hub:        'Pharmacy hub — receives from central, issues to satellites & departments',
        pharmacy_satellite:  'Pharmacy satellite — receives from hub, serves outpatient dispensing',
        department:          'Department store — receives from hub, serves ward requests',
        ward:                'Ward store — bedside stock, receives from department or hub',
        other:               'Other store — custom role, consult administrator',
    };

    const ROLE_COLORS = {
        central:             '#6610f2',
        pharmacy_hub:        '#0d6efd',
        pharmacy_satellite:  '#0dcaf0',
        department:          '#fd7e14',
        ward:                '#198754',
        other:               '#6c757d',
    };

    const APPROVAL_LEVELS = ['none', 'manager', 'admin'];

    let matrixData  = [];
    let pendingCell = null;
    let editMode    = false;

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
                    ${roles.map(r => `
                        <th class="text-center small" title="${ROLE_TOOLTIPS[r]}">
                            <span style="color:${ROLE_COLORS[r]};font-weight:600">${ROLE_LABELS[r]}</span>
                        </th>`).join('')}
                </tr>
            </thead>
            <tbody>`;

        roles.forEach(src => {
            html += `<tr><th class="bg-light small fw-semibold" title="${ROLE_TOOLTIPS[src]}"
                         style="color:${ROLE_COLORS[src]}">${ROLE_LABELS[src]}</th>`;
            roles.forEach(dst => {
                if (src === dst) {
                    html += `<td class="text-center align-middle" style="background:#f8f9fa;color:#adb5bd">
                        <i class="fas fa-lock"></i>
                    </td>`;
                    return;
                }
                const cell     = matrix.find(c => c.source_role === src && c.destination_role === dst);
                const allowed  = cell?.allowed ?? false;
                const approval = cell?.requires_approval_level ?? 'none';
                const notes    = (cell?.notes ?? '').replace(/"/g, '&quot;');

                if (CAN_MANAGE && editMode) {
                    // ── Edit mode cell ────────────────────────────────────
                    html += `<td class="p-1 lane-cell align-middle" data-src="${src}" data-dst="${dst}" style="min-width:140px">
                        <div class="d-flex flex-column gap-1">
                            <div class="form-check form-switch mb-0 d-flex justify-content-center">
                                <input class="form-check-input lane-allowed" type="checkbox" ${allowed ? 'checked' : ''}>
                            </div>
                            <select class="form-select form-select-sm lane-approval">
                                ${APPROVAL_LEVELS.map(lvl =>
                                    `<option value="${lvl}" ${approval === lvl ? 'selected' : ''}>${lvl}</option>`
                                ).join('')}
                            </select>
                            <input type="text" class="form-control form-control-sm lane-notes"
                                   placeholder="Notes…" value="${notes}">
                            <div class="d-flex flex-column gap-1 mt-1">
                                <button class="btn btn-xs btn-primary lane-save-btn">
                                    <i class="fas fa-save d-block mx-auto mb-1"></i>
                                    <span class="d-block" style="font-size:0.7rem">Save Cell</span>
                                </button>
                                <button class="btn btn-xs btn-outline-secondary lane-reset-btn">
                                    <i class="fas fa-undo d-block mx-auto mb-1"></i>
                                    <span class="d-block" style="font-size:0.7rem">Reset</span>
                                </button>
                            </div>
                        </div>
                    </td>`;
                } else {
                    // ── Compact/read-only cell ────────────────────────────
                    const badgeCls = allowed ? 'bg-success' : 'bg-danger';
                    const badgeTxt = allowed ? '✓ Allowed' : '✗ Blocked';
                    const approvalLabel = (approval && approval !== 'none')
                        ? `<div class="text-muted" style="font-size:0.7rem">${approval}</div>` : '';
                    html += `<td class="text-center align-middle p-1">
                        <span class="badge ${badgeCls}">${badgeTxt}</span>
                        ${approvalLabel}
                    </td>`;
                }
            });
            html += `</tr>`;
        });

        html += `</tbody></table>`;
        document.getElementById('laneMatrixContainer').innerHTML = html;
    }

    // ── Edit mode toggle ─────────────────────────────────────────────────
    document.getElementById('editModeToggle')?.addEventListener('change', function () {
        editMode = this.checked;
        renderMatrix(matrixData);
    });

    // ── Save cell ────────────────────────────────────────────────────────
    document.addEventListener('click', async function (e) {
        const saveBtn = e.target.closest('.lane-save-btn');
        if (saveBtn) {
            const cell = saveBtn.closest('.lane-cell');
            const body = {
                source_role:             cell.dataset.src,
                destination_role:        cell.dataset.dst,
                allowed:                 cell.querySelector('.lane-allowed').checked ? 1 : 0,
                requires_approval_level: cell.querySelector('.lane-approval').value,
                notes:                   cell.querySelector('.lane-notes').value,
            };
            await postLane(body, false);
            return;
        }

        const resetBtn = e.target.closest('.lane-reset-btn');
        if (resetBtn) {
            const cell = resetBtn.closest('.lane-cell');
            if (!confirm('Reset this cell to the system default (deny)?')) return;
            await postLane({
                source_role:             cell.dataset.src,
                destination_role:        cell.dataset.dst,
                allowed:                 0,
                requires_approval_level: 'none',
                notes:                   '',
                reset_to_default:        1,
            }, false);
        }
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
            await loadMatrix();
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
