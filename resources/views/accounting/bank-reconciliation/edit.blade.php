{{--
    Bank Reconciliation Edit/Matching Interface
    Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 3
    Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT

    Features:
    - Visual statement viewer (PDF, Excel, CSV, Images)
    - Click-to-capture rows from Excel/CSV
    - Split-view: Statement on left, GL transactions on right
    - Drag-drop matching interface
--}}

@extends('admin.layouts.app')

@section('title', 'Reconcile - ' . $reconciliation->reconciliation_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Bank Reconciliation')

@section('content')
@include('accounting.partials.breadcrumb', [
    'items' => [
        ['label' => 'Bank Reconciliation', 'url' => route('accounting.bank-reconciliation.index'), 'icon' => 'mdi-bank-check'],
        ['label' => $reconciliation->reconciliation_number, 'url' => route('accounting.bank-reconciliation.show', $reconciliation), 'icon' => 'mdi-information'],
        ['label' => 'Match', 'url' => '#', 'icon' => 'mdi-link-variant']
    ]
])

<style>
/* Header Styling */
.reconciliation-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.balance-box {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}
.balance-box .label { font-size: 0.8rem; opacity: 0.9; }
.balance-box .value { font-size: 1.5rem; font-weight: 700; }

/* Main Layout - Split View */
.reconciliation-workspace {
    display: flex;
    gap: 20px;
    height: calc(100vh - 280px);
    min-height: 600px;
}

/* Statement Viewer Panel */
.statement-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}
.statement-panel-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.statement-viewer {
    flex: 1;
    overflow: auto;
    padding: 15px;
}
.statement-table {
    font-size: 0.85rem;
}
.statement-table th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
}
.selectable-row {
    cursor: pointer;
    transition: all 0.2s;
}
.selectable-row:hover {
    background: #e3f2fd !important;
}
.selectable-row.selected {
    background: #bbdefb !important;
    border-left: 3px solid #2196f3;
}
.selectable-row.captured {
    background: #c8e6c9 !important;
    opacity: 0.7;
}

/* Document Viewer Styles */
.pdf-viewer {
    width: 100%;
    height: 100%;
    border: none;
}
.image-viewer {
    max-width: 100%;
    height: auto;
}
.upload-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6c757d;
    text-align: center;
    padding: 40px;
}
.upload-placeholder i {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

/* Matching Panel */
.matching-panel {
    width: 400px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.item-list {
    flex: 1;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.item-list-header {
    padding: 12px 15px;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.item-list-body {
    flex: 1;
    overflow-y: auto;
}
.recon-item {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f1f1;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.85rem;
}
.recon-item:hover {
    background: #f8f9fa;
}
.recon-item.selected {
    background: #e3f2fd;
    border-left: 3px solid #2196f3;
}
.recon-item.matched {
    background: #e8f5e9;
    opacity: 0.6;
}
.recon-item .date { font-size: 0.75rem; color: #666; }
.recon-item .description { font-weight: 500; font-size: 0.8rem; }
.recon-item .amount { font-weight: 600; }
.recon-item .amount.debit { color: #dc3545; }
.recon-item .amount.credit { color: #28a745; }
.recon-item .ref { font-size: 0.7rem; color: #999; }

/* Action Bar */
.match-actions {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}

/* Captured Items Panel */
.captured-items {
    background: #fff3cd;
    border-radius: 8px;
    padding: 10px 15px;
}
.captured-item {
    display: inline-flex;
    align-items: center;
    background: #ffc107;
    color: #212529;
    padding: 4px 10px;
    border-radius: 20px;
    margin: 3px;
    font-size: 0.8rem;
}
.captured-item .remove-btn {
    margin-left: 8px;
    cursor: pointer;
    opacity: 0.7;
}
.captured-item .remove-btn:hover {
    opacity: 1;
}

/* Variance Indicator */
.variance-indicator {
    padding: 12px 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}
.variance-indicator.balanced {
    background: #d4edda;
    color: #155724;
}
.variance-indicator.unbalanced {
    background: #f8d7da;
    color: #721c24;
}

/* Statement Tabs */
.statement-tabs {
    display: flex;
    gap: 5px;
    padding: 10px 20px 0;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.statement-tab {
    padding: 8px 15px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.statement-tab.active {
    background: #fff;
    border-bottom: 2px solid #fff;
    margin-bottom: -1px;
    font-weight: 500;
}
.statement-tab .close-tab {
    opacity: 0.5;
    cursor: pointer;
    margin-left: 5px;
}
.statement-tab .close-tab:hover {
    opacity: 1;
}
</style>

<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Reconciliation Header -->
    <div class="reconciliation-header">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h5 class="mb-1">{{ $reconciliation->reconciliation_number }}</h5>
                <p class="mb-0 opacity-75">{{ $reconciliation->bank->bank_name }}</p>
                <small class="opacity-75">
                    Period: {{ $reconciliation->statement_period_from->format('M d') }} - {{ $reconciliation->statement_period_to->format('M d, Y') }}
                </small>
            </div>
            <div class="col-md-8">
                <div class="row">
                    <div class="col">
                        <div class="balance-box">
                            <div class="label">Opening Bal.</div>
                            <div class="value">₦{{ number_format($reconciliation->statement_opening_balance, 2) }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="balance-box">
                            <div class="label">Closing Bal.</div>
                            <div class="value">₦{{ number_format($reconciliation->statement_closing_balance, 2) }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="balance-box">
                            <div class="label">GL Balance</div>
                            <div class="value">₦{{ number_format($reconciliation->gl_closing_balance, 2) }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="balance-box">
                            <div class="label">Outstanding</div>
                            <div class="value">₦{{ number_format($reconciliation->outstanding_deposits + $reconciliation->outstanding_checks, 2) }}</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="balance-box {{ abs($reconciliation->variance) < 0.01 ? 'bg-success' : 'bg-danger' }}">
                            <div class="label">Variance</div>
                            <div class="value" id="varianceValue">₦{{ number_format($reconciliation->variance, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="text-right mt-2">
                    <button type="button" class="btn btn-outline-light btn-sm" id="btnEditReconciliation" title="Edit reconciliation details">
                        <i class="mdi mdi-pencil"></i> Edit Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Variance Indicator -->
    <div class="variance-indicator {{ abs($reconciliation->variance) < 0.01 ? 'balanced' : 'unbalanced' }}" id="varianceIndicator">
        @if(abs($reconciliation->variance) < 0.01)
            <i class="mdi mdi-check-circle mr-2"></i> Reconciliation is balanced! You can submit for review.
        @else
            <i class="mdi mdi-alert-circle mr-2"></i> Variance of ₦{{ number_format(abs($reconciliation->variance), 2) }} - Match items or add from statement.
        @endif
    </div>

    <!-- Action Buttons -->
    <div class="d-flex justify-content-between mb-3">
        <div>
            <a href="{{ route('accounting.bank-reconciliation.index') }}" class="btn btn-secondary btn-sm">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
            <button type="button" class="btn btn-info btn-sm" id="btnUploadStatement">
                <i class="mdi mdi-upload mr-1"></i> Upload Statement
            </button>
            <input type="file" id="statementFileInput" style="display:none" accept=".pdf,.xlsx,.xls,.csv,.docx,.doc,.jpg,.jpeg,.png,.gif,.bmp,.webp">
        </div>
        <div>
            @if(abs($reconciliation->variance) < 0.01)
                <form action="{{ route('accounting.bank-reconciliation.submit-review', $reconciliation) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="mdi mdi-check mr-1"></i> Submit for Review
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="reconciliation-workspace">
        <!-- Statement Viewer Panel -->
        <div class="statement-panel">
            <div class="statement-panel-header">
                <h6 class="mb-0"><i class="mdi mdi-file-document mr-2"></i>Bank Statement</h6>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="btnZoomIn" title="Zoom In">
                        <i class="mdi mdi-magnify-plus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnZoomOut" title="Zoom Out">
                        <i class="mdi mdi-magnify-minus"></i>
                    </button>
                </div>
            </div>

            <!-- Statement Tabs (for multiple uploaded statements) -->
            <div class="statement-tabs" id="statementTabs" style="display:none;">
                <!-- Tabs will be dynamically added here -->
            </div>

            <div class="statement-viewer" id="statementViewer">
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <i class="mdi mdi-cloud-upload-outline"></i>
                    <h5>Upload Bank Statement</h5>
                    <p class="text-muted">Supported formats: PDF, Excel, CSV, Word, Images</p>
                    <button type="button" class="btn btn-primary" id="btnUploadPlaceholder">
                        <i class="mdi mdi-upload mr-1"></i> Select File
                    </button>
                </div>
                <div id="statementContent" style="display:none;">
                    <!-- Statement content will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Right Panel: Captured Items + GL + Statement Items -->
        <div class="matching-panel">
            <!-- Captured Items from Statement (Visual Selection) -->
            <div class="captured-items" id="capturedItemsPanel" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="font-weight-bold"><i class="mdi mdi-selection mr-1"></i> Selected from Statement</small>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddCaptured" disabled>
                        <i class="mdi mdi-plus mr-1"></i> Add to Items
                    </button>
                </div>
                <div id="capturedItemsList">
                    <!-- Captured items will appear here -->
                </div>
            </div>

            <!-- GL Items -->
            <div class="item-list">
                <div class="item-list-header">
                    <span><i class="mdi mdi-book-open mr-1"></i> GL Transactions</span>
                    <span class="badge badge-primary" id="glUnmatchedCount">{{ $glItems->where('is_matched', false)->count() }}</span>
                </div>
                <div class="item-list-body" id="gl-items">
                    @forelse($glItems as $item)
                        <div class="recon-item {{ $item->is_matched ? 'matched' : '' }}"
                             data-id="{{ $item->id }}"
                             data-type="gl"
                             data-amount="{{ $item->amount }}"
                             data-amount-type="{{ $item->amount_type }}">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="date">{{ $item->transaction_date->format('M d, Y') }}</div>
                                    <div class="description">{{ Str::limit($item->description, 30) }}</div>
                                    <div class="ref">{{ $item->reference }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="amount {{ $item->amount_type }}">
                                        {{ $item->amount_type === 'debit' ? '-' : '+' }}₦{{ number_format($item->amount, 2) }}
                                    </div>
                                    @if($item->is_matched)
                                        <span class="badge badge-success" style="font-size:0.65rem">Matched</span>
                                    @elseif($item->is_outstanding)
                                        <span class="badge badge-warning" style="font-size:0.65rem">Outstanding</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-3 text-center text-muted">
                            <i class="mdi mdi-file-document-outline mdi-36px"></i>
                            <p class="mb-0 small">No GL transactions</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Statement Items -->
            <div class="item-list">
                <div class="item-list-header">
                    <span><i class="mdi mdi-bank mr-1"></i> Statement Items</span>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddStatementItem" title="Add Item Manually">
                            <i class="mdi mdi-plus"></i>
                        </button>
                        <span class="badge badge-info ml-1" id="stmtUnmatchedCount">{{ $statementItems->where('is_matched', false)->count() }}</span>
                    </div>
                </div>
                <div class="item-list-body" id="statement-items">
                    @forelse($statementItems as $item)
                        <div class="recon-item {{ $item->is_matched ? 'matched' : '' }}"
                             data-id="{{ $item->id }}"
                             data-type="statement"
                             data-amount="{{ $item->amount }}"
                             data-amount-type="{{ $item->amount_type }}">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="date">{{ $item->transaction_date->format('M d, Y') }}</div>
                                    <div class="description">{{ Str::limit($item->description, 30) }}</div>
                                    <div class="ref">{{ $item->reference }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="amount {{ $item->amount_type }}">
                                        {{ $item->amount_type === 'debit' ? '-' : '+' }}₦{{ number_format($item->amount, 2) }}
                                    </div>
                                    @if($item->is_matched)
                                        <span class="badge badge-success" style="font-size:0.65rem">Matched</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-3 text-center text-muted" id="stmtEmptyMessage">
                            <i class="mdi mdi-bank-outline mdi-36px"></i>
                            <p class="mb-0 small">Upload statement & select rows</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Match Actions -->
            <div class="match-actions">
                <button type="button" class="btn btn-success btn-block mb-2" id="btn-match" disabled>
                    <i class="mdi mdi-link-variant mr-1"></i> Match Selected Items
                </button>
                <div class="btn-group btn-group-sm btn-block">
                    <button type="button" class="btn btn-outline-warning" id="btn-outstanding" disabled>
                        <i class="mdi mdi-clock-outline mr-1"></i> Mark Outstanding
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="btn-unmatch" disabled>
                        <i class="mdi mdi-link-variant-off mr-1"></i> Unmatch
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Statement Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-plus-circle mr-2"></i>Add Statement Item</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" name="transaction_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Type <span class="text-danger">*</span></label>
                                <select name="amount_type" class="form-control" required>
                                    <option value="credit">Credit (Deposit)</option>
                                    <option value="debit">Debit (Withdrawal)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reconciliation Details Modal -->
<div class="modal fade" id="editReconciliationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="mdi mdi-pencil mr-2"></i>Edit Reconciliation Details</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="editReconciliationForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Statement Date <span class="text-danger">*</span></label>
                                <input type="date" name="statement_date" class="form-control"
                                       value="{{ $reconciliation->statement_date->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank</label>
                                <input type="text" class="form-control" value="{{ $reconciliation->bank->bank_name }}" disabled>
                                <small class="text-muted">Bank cannot be changed after creation</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Period From <span class="text-danger">*</span></label>
                                <input type="date" name="statement_period_from" class="form-control"
                                       value="{{ $reconciliation->statement_period_from->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Period To <span class="text-danger">*</span></label>
                                <input type="date" name="statement_period_to" class="form-control"
                                       value="{{ $reconciliation->statement_period_to->format('Y-m-d') }}" required>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6 class="text-muted mb-3"><i class="mdi mdi-bank mr-2"></i>Statement Balances</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Opening Balance <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" step="0.01" name="statement_opening_balance" class="form-control"
                                           value="{{ $reconciliation->statement_opening_balance }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Closing Balance <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">₦</span>
                                    </div>
                                    <input type="number" step="0.01" name="statement_closing_balance" class="form-control"
                                           value="{{ $reconciliation->statement_closing_balance }}" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="mdi mdi-information mr-2"></i>
                        <strong>Note:</strong> Changing the statement balances will recalculate the variance. GL Balance is automatically calculated from ledger entries.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- PDF.js for PDF rendering --}}
<script src="{{ asset('assets/js/pdf.min.js') }}"></script>
<script>
    // Set PDF.js worker path
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('assets/js/pdf.worker.min.js') }}';
    }
</script>

<script>
$(document).ready(function() {
    const reconciliationId = {{ $reconciliation->id }};
    const csrfToken = '{{ csrf_token() }}';
    const storageBaseUrl = '{{ asset('storage') }}';  // Dynamic storage URL

    let selectedGL = null;
    let selectedStatement = null;
    let capturedRows = [];
    let uploadedStatements = [];
    let activeStatementId = null;

    // Upload button triggers
    $('#btnUploadStatement, #btnUploadPlaceholder').click(function() {
        $('#statementFileInput').click();
    });

    // Add Statement Item button - opens modal for manual entry
    $('#btnAddStatementItem').click(function() {
        // Clear the form
        $('#addItemForm')[0].reset();
        // Set default date to today
        $('#addItemForm [name="transaction_date"]').val(new Date().toISOString().split('T')[0]);
        // Open modal
        $('#addItemModal').modal('show');
    });

    // File upload handler
    $('#statementFileInput').change(function() {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('statement_file', file);
        formData.append('_token', csrfToken);

        // Show loading in statementContent (not statementViewer to preserve structure)
        $('#uploadPlaceholder').hide();
        $('#statementContent').html('<div class="text-center p-5"><i class="mdi mdi-loading mdi-spin mdi-48px"></i><p>Uploading...</p></div>').show();

        $.ajax({
            url: '{{ route('accounting.bank-reconciliation.upload-statement', $reconciliation) }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                console.log('Upload response:', res);
                if (res.success) {
                    toastr.success(res.message);
                    uploadedStatements.push(res.import);
                    activeStatementId = res.import.id;
                    loadStatementContent(res.import);
                    updateStatementTabs();
                } else {
                    toastr.error(res.message || 'Upload failed');
                    showUploadPlaceholder();
                }
            },
            error: function(xhr) {
                console.error('Upload error:', xhr);
                toastr.error(xhr.responseJSON?.message || 'Upload failed');
                showUploadPlaceholder();
            }
        });

        // Clear input
        this.value = '';
    });

    // Load statement content
    function loadStatementContent(importData) {
        console.log('Loading statement content:', importData);

        // Show loading spinner
        $('#uploadPlaceholder').hide();
        $('#statementContent').html('<div class="text-center p-5"><i class="mdi mdi-loading mdi-spin mdi-48px"></i><p>Loading statement...</p></div>').show();

        if (importData.viewer_type === 'table') {
            // Fetch HTML table content
            const url = '{{ url('/accounting/bank-reconciliation/' . $reconciliation->id . '/statement') }}/' + importData.id;
            console.log('Fetching table from:', url);

            $.ajax({
                url: url,
                type: 'GET',
                success: function(res) {
                    console.log('Table response:', res);
                    if (res.success) {
                        $('#statementContent').html(res.content).show();
                        initTableInteractions();
                    } else {
                        $('#statementContent').html('<div class="alert alert-danger m-3">Failed to load statement content: ' + (res.message || 'Unknown error') + '</div>');
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load statement:', xhr);
                    $('#statementContent').html('<div class="alert alert-danger m-3">Failed to load statement: ' + (xhr.responseJSON?.message || xhr.statusText || 'Unknown error') + '</div>');
                }
            });
        } else if (importData.viewer_type === 'pdf') {
            console.log('Rendering PDF:', importData.viewer_url);
            // Use local PDF.js viewer
            renderPdfViewer(importData.viewer_url);
        } else if (importData.viewer_type === 'image') {
            console.log('Rendering image:', importData.viewer_url);
            $('#statementContent').html(
                '<div class="text-center p-3">' +
                '<img src="' + importData.viewer_url + '" class="image-viewer" alt="Bank Statement" style="max-width:100%; cursor:zoom-in;" onclick="window.open(this.src, \'_blank\')">' +
                '<p class="text-muted mt-2"><small>Click image to open in new tab</small></p></div>'
            ).show();
        } else {
            console.log('Rendering download link:', importData.viewer_url);
            // Download link for unsupported formats
            $('#statementContent').html(
                '<div class="text-center p-5">' +
                '<i class="mdi mdi-file-document mdi-48px text-muted"></i>' +
                '<p>This file format can only be downloaded.</p>' +
                '<a href="' + importData.viewer_url + '" class="btn btn-primary" target="_blank">' +
                '<i class="mdi mdi-download mr-1"></i> Download File</a></div>'
            ).show();
        }
    }

    // Initialize table row interactions
    function initTableInteractions() {
        $('.selectable-row').click(function() {
            $(this).toggleClass('selected');
            updateCapturedRows();
        });
    }

    // Update captured rows panel
    function updateCapturedRows() {
        capturedRows = [];
        $('.selectable-row.selected').each(function() {
            const rowData = $(this).data('values');
            const rowNum = $(this).data('row');
            capturedRows.push({
                row: rowNum,
                data: rowData
            });
        });

        if (capturedRows.length > 0) {
            $('#capturedItemsPanel').show();
            $('#btnAddCaptured').prop('disabled', false);

            let html = '';
            capturedRows.forEach((row, idx) => {
                // Show a preview of the row (first 2-3 cells)
                const preview = Array.isArray(row.data) ? row.data.slice(0, 3).join(' | ') : 'Row ' + row.row;
                html += '<span class="captured-item" data-index="' + idx + '">' +
                    '<span class="text-truncate" style="max-width:150px">' + preview + '</span>' +
                    '<span class="remove-btn" onclick="removeCapturedRow(' + idx + ')">&times;</span></span>';
            });
            $('#capturedItemsList').html(html);
        } else {
            $('#capturedItemsPanel').hide();
            $('#btnAddCaptured').prop('disabled', true);
            $('#capturedItemsList').html('');
        }
    }

    // Remove captured row
    window.removeCapturedRow = function(idx) {
        const rowNum = capturedRows[idx].row;
        $('.selectable-row[data-row="' + rowNum + '"]').removeClass('selected');
        updateCapturedRows();
    };

    // Add captured items to statement items
    $('#btnAddCaptured').click(function() {
        if (capturedRows.length === 0) return;

        // Open modal for each captured row or batch add
        // For simplicity, open modal with first row data pre-filled
        const firstRow = capturedRows[0];
        const data = firstRow.data;

        // Try to guess column mappings
        const modal = $('#addItemModal');
        modal.find('[name="description"]').val(data[1] || ''); // Often description is col 2

        // Try to find a date-like value
        for (let val of data) {
            if (val && /\d{2}[-\/]\d{2}[-\/]\d{2,4}/.test(val)) {
                // Convert to yyyy-mm-dd
                modal.find('[name="transaction_date"]').val(formatDate(val));
                break;
            }
        }

        // Try to find amount
        for (let val of data) {
            if (val && !isNaN(parseFloat(val.toString().replace(/[,\s]/g, '')))) {
                const amount = parseFloat(val.toString().replace(/[,\s]/g, ''));
                if (Math.abs(amount) > 0.01) {
                    modal.find('[name="amount"]').val(Math.abs(amount));
                    modal.find('[name="amount_type"]').val(amount < 0 ? 'debit' : 'credit');
                    break;
                }
            }
        }

        modal.modal('show');
    });

    // Format date string to yyyy-mm-dd
    function formatDate(dateStr) {
        const parts = dateStr.split(/[-\/]/);
        if (parts.length === 3) {
            let year = parts[2];
            if (year.length === 2) year = '20' + year;
            return year + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
        }
        return '';
    }

    // Add item form submission
    $('#addItemForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            _token: csrfToken,
            transaction_date: $(this).find('[name="transaction_date"]').val(),
            description: $(this).find('[name="description"]').val(),
            amount: $(this).find('[name="amount"]').val(),
            amount_type: $(this).find('[name="amount_type"]').val(),
            reference: $(this).find('[name="reference"]').val(),
            row_data: capturedRows.length > 0 ? capturedRows[0].data : null
        };

        $.ajax({
            url: '{{ route('accounting.bank-reconciliation.add-statement-item', $reconciliation) }}',
            type: 'POST',
            data: formData,
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message);
                    $('#addItemModal').modal('hide');

                    // Add item to statement items list
                    const item = res.item;
                    const html = '<div class="recon-item" data-id="' + item.id + '" data-type="statement" ' +
                        'data-amount="' + item.amount + '" data-amount-type="' + item.amount_type + '">' +
                        '<div class="d-flex justify-content-between">' +
                        '<div><div class="date">' + item.transaction_date + '</div>' +
                        '<div class="description">' + item.description.substring(0, 30) + '</div>' +
                        '<div class="ref">' + (item.reference || '') + '</div></div>' +
                        '<div class="text-right"><div class="amount ' + item.amount_type + '">' +
                        (item.amount_type === 'debit' ? '-' : '+') + '₦' + formatNumber(item.amount) +
                        '</div></div></div></div>';

                    $('#stmtEmptyMessage').remove();
                    $('#statement-items').append(html);

                    // Mark row as captured
                    if (capturedRows.length > 0) {
                        const rowNum = capturedRows[0].row;
                        $('.selectable-row[data-row="' + rowNum + '"]').removeClass('selected').addClass('captured');
                        capturedRows.shift();
                        updateCapturedRows();
                    }

                    // Update count
                    updateItemCounts();

                    // Clear form
                    $('#addItemForm')[0].reset();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to add item');
            }
        });
    });

    // Format number with commas
    function formatNumber(num) {
        return parseFloat(num).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Edit Reconciliation button click
    $('#btnEditReconciliation').click(function() {
        $('#editReconciliationModal').modal('show');
    });

    // Edit Reconciliation form submission
    $('#editReconciliationForm').submit(function(e) {
        e.preventDefault();

        const formData = {
            _token: csrfToken,
            _method: 'PUT',
            statement_date: $(this).find('[name="statement_date"]').val(),
            statement_period_from: $(this).find('[name="statement_period_from"]').val(),
            statement_period_to: $(this).find('[name="statement_period_to"]').val(),
            statement_opening_balance: $(this).find('[name="statement_opening_balance"]').val(),
            statement_closing_balance: $(this).find('[name="statement_closing_balance"]').val()
        };

        const submitBtn = $(this).find('[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="mdi mdi-loading mdi-spin"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: '{{ route('accounting.bank-reconciliation.update-details', $reconciliation) }}',
            type: 'POST',
            data: formData,
            success: function(res) {
                if (res.success) {
                    toastr.success(res.message || 'Reconciliation details updated');
                    $('#editReconciliationModal').modal('hide');

                    // Reload page to reflect changes
                    location.reload();
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to update reconciliation');
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Show upload placeholder
    function showUploadPlaceholder() {
        $('#uploadPlaceholder').show();
        $('#statementContent').hide().html('');
    }

    // Update statement tabs
    function updateStatementTabs() {
        if (uploadedStatements.length > 0) {
            $('#statementTabs').show();
            let html = '';
            uploadedStatements.forEach(stmt => {
                const isActive = stmt.id === activeStatementId;
                html += '<div class="statement-tab ' + (isActive ? 'active' : '') + '" data-id="' + stmt.id + '">' +
                    '<i class="mdi mdi-' + getFileIcon(stmt.file_format) + '"></i>' +
                    '<span class="text-truncate" style="max-width:120px">' + stmt.file_name + '</span>' +
                    '<span class="close-tab" onclick="event.stopPropagation(); removeStatement(' + stmt.id + ')">&times;</span>' +
                    '</div>';
            });
            $('#statementTabs').html(html);

            // Tab click handler
            $('.statement-tab').click(function() {
                const id = $(this).data('id');
                activeStatementId = id;
                $('.statement-tab').removeClass('active');
                $(this).addClass('active');
                const stmt = uploadedStatements.find(s => s.id === id);
                if (stmt) loadStatementContent(stmt);
            });
        } else {
            $('#statementTabs').hide();
        }
    }

    // Get icon for file type
    function getFileIcon(format) {
        const icons = {
            'pdf': 'file-pdf',
            'excel': 'file-excel',
            'csv': 'file-delimited',
            'word': 'file-word',
            'image': 'file-image'
        };
        return icons[format] || 'file-document';
    }

    // Remove statement
    window.removeStatement = function(id) {
        if (!confirm('Remove this statement?')) return;

        $.ajax({
            url: '{{ url('/accounting/bank-reconciliation/' . $reconciliation->id . '/statement') }}/' + id,
            type: 'DELETE',
            data: { _token: csrfToken },
            success: function(res) {
                uploadedStatements = uploadedStatements.filter(s => s.id !== id);
                if (activeStatementId === id) {
                    if (uploadedStatements.length > 0) {
                        activeStatementId = uploadedStatements[0].id;
                        loadStatementContent(uploadedStatements[0]);
                    } else {
                        showUploadPlaceholder();
                    }
                }
                updateStatementTabs();
                toastr.success('Statement removed');
            }
        });
    };

    // Item selection
    $(document).on('click', '.recon-item:not(.matched)', function() {
        const type = $(this).data('type');

        if (type === 'gl') {
            $('#gl-items .recon-item').removeClass('selected');
            $(this).addClass('selected');
            selectedGL = $(this).data('id');
        } else {
            $('#statement-items .recon-item').removeClass('selected');
            $(this).addClass('selected');
            selectedStatement = $(this).data('id');
        }

        updateButtons();
    });

    function updateButtons() {
        $('#btn-match').prop('disabled', !(selectedGL && selectedStatement));
        $('#btn-outstanding').prop('disabled', !selectedGL);
        $('#btn-unmatch').prop('disabled', true); // Only enable when matched item selected
    }

    function updateItemCounts() {
        const glUnmatched = $('#gl-items .recon-item:not(.matched)').length;
        const stmtUnmatched = $('#statement-items .recon-item:not(.matched)').length;
        $('#glUnmatchedCount').text(glUnmatched);
        $('#stmtUnmatchedCount').text(stmtUnmatched);
    }

    // Match items
    $('#btn-match').click(function() {
        if (!selectedGL || !selectedStatement) return;

        $.ajax({
            url: '{{ route('accounting.bank-reconciliation.match', $reconciliation) }}',
            type: 'POST',
            data: {
                _token: csrfToken,
                gl_item_id: selectedGL,
                statement_item_id: selectedStatement
            },
            success: function(res) {
                toastr.success(res.message);

                // Mark items as matched visually
                $('[data-id="' + selectedGL + '"]').addClass('matched').removeClass('selected');
                $('[data-id="' + selectedStatement + '"]').addClass('matched').removeClass('selected');

                selectedGL = null;
                selectedStatement = null;
                updateButtons();
                updateItemCounts();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Mark as outstanding
    $('#btn-outstanding').click(function() {
        if (!selectedGL) return;

        $.ajax({
            url: '{{ route('accounting.bank-reconciliation.outstanding', $reconciliation) }}',
            type: 'POST',
            data: {
                _token: csrfToken,
                item_id: selectedGL
            },
            success: function(res) {
                toastr.success(res.message);
                location.reload();
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            }
        });
    });

    // Load existing statements on page load
    $.get('{{ route('accounting.bank-reconciliation.statements', $reconciliation) }}', function(res) {
        if (res.success && res.statements.length > 0) {
            uploadedStatements = res.statements;
            activeStatementId = uploadedStatements[0].id;
            loadStatementContent(uploadedStatements[0]);
            updateStatementTabs();
        }
    });

    // PDF.js Viewer Function
    let pdfDoc = null;
    let currentPage = 1;
    let pdfScale = 1.2;

    function renderPdfViewer(pdfUrl) {
        const html = `
            <div class="pdf-container" style="height:100%;">
                <div class="pdf-toolbar d-flex justify-content-between align-items-center p-2 bg-light border-bottom">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="pdfPrevPage" title="Previous Page">
                            <i class="mdi mdi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" disabled>
                            <span id="pdfPageNum">1</span> / <span id="pdfPageCount">?</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="pdfNextPage" title="Next Page">
                            <i class="mdi mdi-chevron-right"></i>
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="pdfZoomOut" title="Zoom Out">
                            <i class="mdi mdi-magnify-minus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="pdfZoomIn" title="Zoom In">
                            <i class="mdi mdi-magnify-plus"></i>
                        </button>
                        <a href="${pdfUrl}" class="btn btn-outline-primary" target="_blank" title="Open in New Tab">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                        <a href="${pdfUrl}" class="btn btn-outline-success" download title="Download">
                            <i class="mdi mdi-download"></i>
                        </a>
                    </div>
                </div>
                <div id="pdfViewerContainer" style="overflow:auto; height:calc(100% - 50px); text-align:center; background:#525659; padding:10px;">
                    <canvas id="pdfCanvas"></canvas>
                </div>
            </div>
        `;
        $('#statementContent').html(html).show();

        // Load PDF.js
        if (typeof pdfjsLib === 'undefined') {
            // Load PDF.js library dynamically
            $.getScript('{{ asset('assets/js/pdf.min.js') }}', function() {
                pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('assets/js/pdf.worker.min.js') }}';
                loadPdf(pdfUrl);
            });
        } else {
            loadPdf(pdfUrl);
        }
    }

    function loadPdf(pdfUrl) {
        pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
            pdfDoc = pdf;
            $('#pdfPageCount').text(pdf.numPages);
            renderPdfPage(1);
        }).catch(function(error) {
            console.error('PDF load error:', error);
            $('#statementContent').html(
                '<div class="text-center p-5">' +
                '<i class="mdi mdi-alert-circle mdi-48px text-danger"></i>' +
                '<p class="mt-3">Failed to load PDF.</p>' +
                '<p class="text-muted">' + error.message + '</p>' +
                '<a href="' + pdfUrl + '" class="btn btn-primary" target="_blank">' +
                '<i class="mdi mdi-open-in-new mr-1"></i> Open PDF in New Tab</a></div>'
            );
        });
    }

    function renderPdfPage(pageNum) {
        pdfDoc.getPage(pageNum).then(function(page) {
            const canvas = document.getElementById('pdfCanvas');
            const ctx = canvas.getContext('2d');
            const viewport = page.getViewport({ scale: pdfScale });

            canvas.height = viewport.height;
            canvas.width = viewport.width;

            page.render({
                canvasContext: ctx,
                viewport: viewport
            });

            currentPage = pageNum;
            $('#pdfPageNum').text(pageNum);
        });
    }

    // PDF navigation controls
    $(document).on('click', '#pdfPrevPage', function() {
        if (pdfDoc && currentPage > 1) {
            renderPdfPage(currentPage - 1);
        }
    });

    $(document).on('click', '#pdfNextPage', function() {
        if (pdfDoc && currentPage < pdfDoc.numPages) {
            renderPdfPage(currentPage + 1);
        }
    });

    $(document).on('click', '#pdfZoomIn', function() {
        if (pdfDoc) {
            pdfScale += 0.2;
            renderPdfPage(currentPage);
        }
    });

    $(document).on('click', '#pdfZoomOut', function() {
        if (pdfDoc && pdfScale > 0.4) {
            pdfScale -= 0.2;
            renderPdfPage(currentPage);
        }
    });
});
</script>
@endpush
