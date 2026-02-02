@extends('admin.layouts.app')

@section('title', 'Edit Journal Entry - ' . $journalEntry->entry_number)
@section('page_name', 'Accounting')
@section('subpage_name', 'Edit Journal Entry')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index'), 'icon' => 'mdi-book-open-page-variant'],
    ['label' => 'Edit Entry #' . $journalEntry->entry_number, 'url' => '#', 'icon' => 'mdi-pencil']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Edit Journal Entry</h4>
            <p class="text-muted mb-0">Modify entry {{ $journalEntry->entry_number }}</p>
        </div>
        <div>
            <a href="{{ route('accounting.journal-entries.show', $journalEntry->id) }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Entry
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('accounting.journal-entries.update', $journalEntry->id) }}" method="POST" id="journalEntryForm">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- Main Form -->
            <div class="col-lg-8">
                <!-- Header Card -->
                <div modern shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Entry Details</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Entry Number</label>
                                <input type="text" class="form-control" value="{{ $journalEntry->entry_number }}" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                                <input type="date" name="entry_date" class="form-control"
                                       value="{{ old('entry_date', $journalEntry->entry_date->format('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Entry Type <span class="text-danger">*</span></label>
                                <select name="entry_type" class="form-select" required>
                                    <option value="standard" {{ old('entry_type', $journalEntry->entry_type) == 'standard' ? 'selected' : '' }}>Standard</option>
                                    <option value="adjusting" {{ old('entry_type', $journalEntry->entry_type) == 'adjusting' ? 'selected' : '' }}>Adjusting</option>
                                    <option value="closing" {{ old('entry_type', $journalEntry->entry_type) == 'closing' ? 'selected' : '' }}>Closing</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-12">
                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="2" required>{{ old('description', $journalEntry->description) }}</textarea>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_number" class="form-control"
                                       value="{{ old('reference_number', $journalEntry->reference_number) }}"
                                       placeholder="Optional reference...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fiscal Period</label>
                                <select name="fiscal_period_id" class="form-select">
                                    <option value="">Auto-detect from date</option>
                                    @foreach($fiscalPeriods as $period)
                                        <option value="{{ $period->id }}" {{ old('fiscal_period_id', $journalEntry->fiscal_period_id) == $period->id ? 'selected' : '' }}>
                                            {{ $period->name }} ({{ $period->start_date->format('M d') }} - {{ $period->end_date->format('M d, Y') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lines Card -->
                <div modern shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Journal Lines</h6>
                        <button type="button" class="btn btn-sm btn-primary" id="addLine">
                            <i class="fas fa-plus me-1"></i> Add Line
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="linesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40%;">Account <span class="text-danger">*</span></th>
                                        <th style="width: 20%;">Debit</th>
                                        <th style="width: 20%;">Credit</th>
                                        <th style="width: 15%;">Memo</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="linesBody">
                                    @foreach(old('lines', $journalEntry->lines->toArray()) as $index => $line)
                                        <tr class="line-row">
                                            <td>
                                                <select name="lines[{{ $index }}][account_id]" class="form-select account-select" required>
                                                    <option value="">Select Account</option>
                                                    @foreach($accounts as $account)
                                                        <option value="{{ $account->id }}"
                                                                {{ (isset($line['account_id']) && $line['account_id'] == $account->id) ? 'selected' : '' }}>
                                                            {{ $account->code }} - {{ $account->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="lines[{{ $index }}][debit]"
                                                       class="form-control debit-input" step="0.01" min="0"
                                                       value="{{ $line['debit'] ?? 0 }}">
                                            </td>
                                            <td>
                                                <input type="number" name="lines[{{ $index }}][credit]"
                                                       class="form-control credit-input" step="0.01" min="0"
                                                       value="{{ $line['credit'] ?? 0 }}">
                                            </td>
                                            <td>
                                                <input type="text" name="lines[{{ $index }}][memo]"
                                                       class="form-control" value="{{ $line['memo'] ?? '' }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th class="text-end">Totals:</th>
                                        <th class="text-end" id="totalDebit">0.00</th>
                                        <th class="text-end" id="totalCredit">0.00</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div id="balanceAlert" class="alert alert-danger mt-3" style="display: none;">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Entry is not balanced!</strong> Debits must equal Credits.
                        </div>
                        <div id="balanceSuccess" class="alert alert-success mt-3" style="display: none;">
                            <i class="fas fa-check-circle me-2"></i>
                            Entry is balanced.
                        </div>
                    </div>
                </div>

                <!-- Notes Card -->
                <div modern shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Additional Notes</h6>
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Any additional notes or comments...">{{ old('notes', $journalEntry->notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Summary Card -->
                <div modern shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Entry Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small">Total Amount</label>
                            <h3 class="mb-0" id="summaryTotal">₦ 0.00</h3>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small">Number of Lines</label>
                            <h5 class="mb-0" id="summaryLines">0</h5>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small">Balance Status</label>
                            <div id="summaryBalance">
                                <span class="badge bg-secondary">Not Calculated</span>
                            </div>
                        </div>

                        <hr>

                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="save" class="btn btn-outline-secondary" id="saveDraftBtn">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            @if($journalEntry->status == 'draft')
                                <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-paper-plane me-1"></i> Save & Submit
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Current Status -->
                <div modern shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-info">Current Status</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Status:</strong>
                            @switch($journalEntry->status)
                                @case('draft')
                                    <span class="badge bg-secondary">Draft</span>
                                    @break
                                @case('submitted')
                                    <span class="badge bg-warning">Pending Approval</span>
                                    @break
                                @case('approved')
                                    <span class="badge bg-info">Approved</span>
                                    @break
                                @case('posted')
                                    <span class="badge bg-success">Posted</span>
                                    @break
                            @endswitch
                        </p>
                        <p class="mb-2"><strong>Entry #:</strong> {{ $journalEntry->entry_number }}</p>
                        <p class="mb-0"><strong>Created:</strong> {{ $journalEntry->created_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>

                <!-- Help Card -->
                <div modern shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-warning">Editing Guidelines</h6>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li class="mb-2">Entry must be balanced (Debits = Credits)</li>
                            <li class="mb-2">Minimum of 2 lines required</li>
                            <li class="mb-2">Posted entries cannot be edited</li>
                            <li>Changes will be tracked in audit log</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lineIndex = {{ count(old('lines', $journalEntry->lines)) }};
    const linesBody = document.getElementById('linesBody');
    const addLineBtn = document.getElementById('addLine');
    const accountsData = @json($accounts);

    // Add new line
    addLineBtn.addEventListener('click', function() {
        const row = document.createElement('tr');
        row.className = 'line-row';

        let accountOptions = '<option value="">Select Account</option>';
        accountsData.forEach(account => {
            accountOptions += `<option value="${account.id}">${account.code} - ${account.name}</option>`;
        });

        row.innerHTML = `
            <td>
                <select name="lines[${lineIndex}][account_id]" class="form-select account-select" required>
                    ${accountOptions}
                </select>
            </td>
            <td>
                <input type="number" name="lines[${lineIndex}][debit]" class="form-control debit-input" step="0.01" min="0" value="0">
            </td>
            <td>
                <input type="number" name="lines[${lineIndex}][credit]" class="form-control credit-input" step="0.01" min="0" value="0">
            </td>
            <td>
                <input type="text" name="lines[${lineIndex}][memo]" class="form-control">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger remove-line">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        linesBody.appendChild(row);
        lineIndex++;
        updateTotals();
        attachLineEvents(row);
    });

    // Remove line
    function attachLineEvents(row) {
        row.querySelector('.remove-line').addEventListener('click', function() {
            if (document.querySelectorAll('.line-row').length > 2) {
                row.remove();
                updateTotals();
            } else {
                alert('Minimum 2 lines required');
            }
        });

        row.querySelectorAll('.debit-input, .credit-input').forEach(input => {
            input.addEventListener('input', updateTotals);
        });
    }

    // Attach events to existing rows
    document.querySelectorAll('.line-row').forEach(row => {
        attachLineEvents(row);
    });

    // Update totals
    function updateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;

        document.querySelectorAll('.debit-input').forEach(input => {
            totalDebit += parseFloat(input.value) || 0;
        });

        document.querySelectorAll('.credit-input').forEach(input => {
            totalCredit += parseFloat(input.value) || 0;
        });

        document.getElementById('totalDebit').textContent = totalDebit.toFixed(2);
        document.getElementById('totalCredit').textContent = totalCredit.toFixed(2);
        document.getElementById('summaryTotal').textContent = '₦ ' + totalDebit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('summaryLines').textContent = document.querySelectorAll('.line-row').length;

        const isBalanced = Math.abs(totalDebit - totalCredit) < 0.01;

        document.getElementById('balanceAlert').style.display = isBalanced ? 'none' : 'block';
        document.getElementById('balanceSuccess').style.display = isBalanced ? 'block' : 'none';
        document.getElementById('summaryBalance').innerHTML = isBalanced
            ? '<span class="badge bg-success">Balanced</span>'
            : '<span class="badge bg-danger">Unbalanced</span>';

        // Disable submit if not balanced
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = !isBalanced;
        }
    }

    // Initial calculation
    updateTotals();

    // Form validation
    document.getElementById('journalEntryForm').addEventListener('submit', function(e) {
        const totalDebit = parseFloat(document.getElementById('totalDebit').textContent);
        const totalCredit = parseFloat(document.getElementById('totalCredit').textContent);

        if (Math.abs(totalDebit - totalCredit) >= 0.01) {
            e.preventDefault();
            alert('Entry must be balanced. Total Debits must equal Total Credits.');
            return false;
        }

        if (document.querySelectorAll('.line-row').length < 2) {
            e.preventDefault();
            alert('Minimum 2 lines required.');
            return false;
        }
    });
});
</script>
@endpush
@endsection
