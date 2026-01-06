# Billing Workbench Implementation Plan

## Overview
Transform the product-or-service-request index page into a full-fledged billing workbench modeled after the lab workbench, combining payment processing, transaction history, and patient account management into a unified interface.

## Current State Analysis

### Existing Components
1. **Product-or-Service-Request Index** (`resources/views/admin/product_or_service_request/index.blade.php`)
   - DataTable listing patients with unpaid items
   - Pay button opens modal with AJAX payment flow
   - Date filtering
   - Payment modal with item selection, discounts, HMO coverage display
   - Receipt generation (A4 and thermal)

2. **My Transactions** (`routes: my-transactions`, Controller: `paymentController::myTransactions`)
   - Lists payments made by current user
   - Date filtering
   - Payment type filtering
   - Totals and summary by payment type

3. **Patient Show - Accounts Tab** (within patient profile)
   - Patient's payment history
   - Unpaid items
   - Account balance

4. **Payment Controller** (`app/Http/Controllers/Account/paymentController.php`)
   - `ajaxUnpaid`: fetches unpaid items for patient
   - `ajaxPay`: processes payment, creates claims, returns receipts
   - `payment`: legacy payment flow
   - `myTransactions`: transaction report for current user

## Target Architecture (Lab Workbench Pattern)

### Layout Structure
```
┌─────────────────────────────────────────────────────────────┐
│                    BILLING WORKBENCH                         │
├────────────┬────────────────────────────────────────────────┤
│            │                                                 │
│  SEARCH    │                                                 │
│   PANE     │              WORK PANE                          │
│  (20%)     │              (80%)                              │
│            │                                                 │
│  ┌──────┐  │  ┌────────────────────────────────────────┐   │
│  │Search│  │  │  Patient Header (when selected)        │   │
│  └──────┘  │  └────────────────────────────────────────┘   │
│            │                                                 │
│  Filters:  │  ┌────────────────────────────────────────┐   │
│  ┌──────┐  │  │  Tabs:                                  │   │
│  │Queue │  │  │  • Billing (unpaid items + pay)        │   │
│  │Counts│  │  │  • Receipts (paid items + print)       │   │
│  └──────┘  │  │  • Transactions (payment history)      │   │
│            │  │  • Account (balance, summary)          │   │
│  ┌──────┐  │  └────────────────────────────────────────┘   │
│  │View  │  │                                                 │
│  │All   │  │  Content area changes based on active tab     │
│  └──────┘  │                                                 │
│            │                                                 │
│  ┌──────┐  │                                                 │
│  │Report│  │                                                 │
│  └──────┘  │                                                 │
│            │                                                 │
│  Patient   │                                                 │
│  Queue:    │                                                 │
│  • Item 1  │                                                 │
│  • Item 2  │                                                 │
│            │                                                 │
└────────────┴─────────────────────────────────────────────────┘
```

## Implementation Steps

### Phase 1: Backend Routes & Controller Setup

#### 1.1 Create BillingWorkbenchController
**File**: `app/Http/Controllers/BillingWorkbenchController.php`

**Methods**:
- `index()` - Main workbench view
- `searchPatients(Request $request)` - Patient search (term-based)
- `getPaymentQueue()` - List of patients with unpaid items
- `getQueueCounts()` - Counters for billing, HMO claims, credit accounts
- `getPatientBillingData($patientId)` - Unpaid items for patient
- `getPatientReceipts($patientId)` - Paid items/receipts for patient
- `getPatientTransactions($patientId, Request $request)` - Payment history with filters
- `getPatientAccountSummary($patientId)` - Balance, credit, HMO claims summary
- `processPayment(Request $request)` - Handle payment (reuse ajaxPay logic)
- `printReceipt(Request $request)` - Generate receipt for selected payment(s)
- `getMyTransactions(Request $request)` - Current user's transactions (report button)

#### 1.2 Define Routes
**File**: `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/billing-workbench', [BillingWorkbenchController::class, 'index'])
        ->name('billing.workbench');
    
    Route::get('/billing-workbench/patient-search', [BillingWorkbenchController::class, 'searchPatients'])
        ->name('billing.search-patients');
    
    Route::get('/billing-workbench/queue', [BillingWorkbenchController::class, 'getPaymentQueue'])
        ->name('billing.queue');
    
    Route::get('/billing-workbench/queue-counts', [BillingWorkbenchController::class, 'getQueueCounts'])
        ->name('billing.queue-counts');
    
    Route::get('/billing-workbench/patient/{id}/billing', [BillingWorkbenchController::class, 'getPatientBillingData'])
        ->name('billing.patient-billing');
    
    Route::get('/billing-workbench/patient/{id}/receipts', [BillingWorkbenchController::class, 'getPatientReceipts'])
        ->name('billing.patient-receipts');
    
    Route::get('/billing-workbench/patient/{id}/transactions', [BillingWorkbenchController::class, 'getPatientTransactions'])
        ->name('billing.patient-transactions');
    
    Route::get('/billing-workbench/patient/{id}/account', [BillingWorkbenchController::class, 'getPatientAccountSummary'])
        ->name('billing.patient-account');
    
    Route::post('/billing-workbench/process-payment', [BillingWorkbenchController::class, 'processPayment'])
        ->name('billing.process-payment');
    
    Route::post('/billing-workbench/print-receipt', [BillingWorkbenchController::class, 'printReceipt'])
        ->name('billing.print-receipt');
    
    Route::get('/billing-workbench/my-transactions', [BillingWorkbenchController::class, 'getMyTransactions'])
        ->name('billing.my-transactions');
});
```

### Phase 2: Frontend View Structure

#### 2.1 Main Workbench Blade
**File**: `resources/views/admin/billing/workbench.blade.php`

**Structure**:
```html
@extends('admin.layouts.app')
@section('title', 'Billing Workbench')

<div class="billing-workbench-container">
    <!-- LEFT PANEL: Search & Queue -->
    <div class="left-panel">
        <!-- Search Container -->
        <div class="search-container">
            <input id="patient-search-input" type="text" placeholder="Search patient...">
            <div class="search-results"></div>
        </div>

        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <button id="btn-queue-all" class="btn-filter">
                <i class="fa fa-list"></i> All Queue 
                <span class="badge" id="count-all">0</span>
            </button>
            <button id="btn-queue-unpaid" class="btn-filter">
                <i class="fa fa-clock"></i> Unpaid 
                <span class="badge" id="count-unpaid">0</span>
            </button>
            <button id="btn-queue-hmo" class="btn-filter">
                <i class="fa fa-hospital"></i> HMO Claims 
                <span class="badge" id="count-hmo">0</span>
            </button>
            <button id="btn-queue-credit" class="btn-filter">
                <i class="fa fa-credit-card"></i> Credit Accounts 
                <span class="badge" id="count-credit">0</span>
            </button>
        </div>

        <!-- View All & Report Buttons -->
        <div class="action-buttons">
            <button id="btn-view-all" class="btn btn-primary btn-block">
                <i class="fa fa-th-list"></i> View All Queue
            </button>
            <button id="btn-report" class="btn btn-secondary btn-block">
                <i class="fa fa-chart-bar"></i> My Transactions
            </button>
        </div>

        <!-- Queue List -->
        <div class="queue-list-container">
            <div id="queue-list"></div>
        </div>
    </div>

    <!-- RIGHT PANEL: Work Pane -->
    <div class="work-pane">
        <!-- Empty State -->
        <div id="empty-state" class="empty-state">
            <i class="fa fa-hand-pointer fa-3x"></i>
            <h4>Select a Patient</h4>
            <p>Search or click a patient from the queue to begin</p>
        </div>

        <!-- Patient Context (shown when patient selected) -->
        <div id="patient-context" style="display:none;">
            <!-- Patient Header -->
            <div class="patient-header">
                <div class="patient-photo">
                    <img id="patient-avatar" src="" alt="Patient">
                </div>
                <div class="patient-info">
                    <h3 id="patient-name">Patient Name</h3>
                    <div class="patient-meta">
                        <span><i class="fa fa-id-card"></i> <strong>File No:</strong> <span id="patient-file-no"></span></span>
                        <span><i class="fa fa-birthday-cake"></i> <strong>Age:</strong> <span id="patient-age"></span></span>
                        <span><i class="fa fa-venus-mars"></i> <strong>Gender:</strong> <span id="patient-gender"></span></span>
                        <span><i class="fa fa-hospital"></i> <strong>HMO:</strong> <span id="patient-hmo"></span></span>
                    </div>
                </div>
                <button id="btn-close-patient" class="btn-close-patient">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs workbench-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="tab-billing" data-toggle="tab" href="#pane-billing">
                        <i class="fa fa-money-bill"></i> Billing
                        <span class="badge badge-danger" id="badge-unpaid">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-receipts" data-toggle="tab" href="#pane-receipts">
                        <i class="fa fa-receipt"></i> Receipts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-transactions" data-toggle="tab" href="#pane-transactions">
                        <i class="fa fa-history"></i> Transactions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="tab-account" data-toggle="tab" href="#pane-account">
                        <i class="fa fa-wallet"></i> Account
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content workbench-content">
                <!-- Billing Tab -->
                <div class="tab-pane fade show active" id="pane-billing">
                    <!-- Unpaid Items Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Unpaid Items</h5>
                            <button id="btn-select-all-items" class="btn btn-sm btn-secondary">
                                Select All
                            </button>
                        </div>
                        <div class="card-body">
                            <table id="unpaid-items-table" class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="check-all-items"></th>
                                        <th>Type</th>
                                        <th>Name</th>
                                        <th>Coverage</th>
                                        <th>Price</th>
                                        <th>Claims</th>
                                        <th>Qty</th>
                                        <th>Disc %</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="unpaid-items-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="card mt-3">
                        <div class="card-header">Payment Details</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label>Payment Type</label>
                                    <select id="payment-type" class="form-control">
                                        <option value="CASH">Cash</option>
                                        <option value="POS">POS</option>
                                        <option value="TRANSFER">Transfer</option>
                                        <option value="TELLER">Teller</option>
                                        <option value="CHEQUE">Cheque</option>
                                        <option value="ACC_WITHDRAW">Credit Account</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Reference</label>
                                    <input id="payment-reference" type="text" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <div class="payment-summary">
                                        <div class="total-amount">
                                            Total: ₦<span id="total-amount">0.00</span>
                                        </div>
                                        <div class="discount-amount">
                                            Discount: ₦<span id="discount-amount">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button id="btn-process-payment" class="btn btn-primary btn-lg">
                                    <i class="fa fa-check"></i> Process Payment
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Area -->
                    <div id="billing-alert" class="alert d-none mt-3"></div>
                </div>

                <!-- Receipts Tab -->
                <div class="tab-pane fade" id="pane-receipts">
                    <div class="card">
                        <div class="card-header">
                            <h5>Paid Items & Receipts</h5>
                        </div>
                        <div class="card-body">
                            <table id="receipts-table" class="table table-sm"></table>
                        </div>
                    </div>
                </div>

                <!-- Transactions Tab -->
                <div class="tab-pane fade" id="pane-transactions">
                    <div class="card">
                        <div class="card-header">
                            <h5>Payment History</h5>
                            <div class="filters">
                                <input type="date" id="tx-start-date" class="form-control form-control-sm">
                                <input type="date" id="tx-end-date" class="form-control form-control-sm">
                                <select id="tx-payment-type" class="form-control form-control-sm">
                                    <option value="">All Types</option>
                                    <option value="CASH">Cash</option>
                                    <option value="POS">POS</option>
                                    <option value="TRANSFER">Transfer</option>
                                </select>
                                <button id="btn-filter-tx" class="btn btn-sm btn-primary">Filter</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="transactions-table" class="table table-sm"></table>
                        </div>
                    </div>
                </div>

                <!-- Account Tab -->
                <div class="tab-pane fade" id="pane-account">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <h6>Account Balance</h6>
                                    <h3 id="account-balance">₦0.00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <h6>Total Paid</h6>
                                    <h3 id="total-paid">₦0.00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <h6>HMO Claims</h6>
                                    <h3 id="hmo-claims">₦0.00</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <div class="card-header">Account Summary</div>
                        <div class="card-body" id="account-summary-details">
                            <!-- Summary details loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Print Modal -->
<div class="modal fade" id="receiptModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Receipt</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#receipt-a4">A4</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#receipt-thermal">Thermal</a>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div id="receipt-a4" class="tab-pane fade show active">
                        <div id="receipt-a4-content"></div>
                    </div>
                    <div id="receipt-thermal" class="tab-pane fade">
                        <div id="receipt-thermal-content"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-print-a4" class="btn btn-primary">Print A4</button>
                <button id="btn-print-thermal" class="btn btn-secondary">Print Thermal</button>
            </div>
        </div>
    </div>
</div>
```

### Phase 3: JavaScript/AJAX Logic

#### 3.1 Core State Management
```javascript
let currentPatient = null;
let unpaidItemsCache = [];
let isProcessingPayment = false;

// Initialize on page load
$(document).ready(function() {
    initSearchAutocomplete();
    loadQueueCounts();
    loadPaymentQueue();
    bindEventHandlers();
});
```

#### 3.2 Patient Search (Autocomplete)
```javascript
function initSearchAutocomplete() {
    $('#patient-search-input').on('input', debounce(function() {
        const term = $(this).val();
        if (term.length < 2) {
            $('.search-results').hide().empty();
            return;
        }
        $.get('/billing-workbench/patient-search', { term }, function(patients) {
            renderSearchResults(patients);
        });
    }, 300));
}

function renderSearchResults(patients) {
    const html = patients.map(p => `
        <div class="search-result-item" data-id="${p.id}">
            <img src="/storage/${p.photo}" alt="${p.name}">
            <div class="search-result-info">
                <div class="search-result-name">${p.name}</div>
                <div class="search-result-details">
                    ${p.file_no} | ${p.age}y, ${p.gender}
                </div>
            </div>
            ${p.pending_count > 0 ? `<span class="pending-badge">${p.pending_count}</span>` : ''}
        </div>
    `).join('');
    $('.search-results').html(html).show();
}
```

#### 3.3 Queue Management
```javascript
function loadQueueCounts() {
    $.get('/billing-workbench/queue-counts', function(counts) {
        $('#count-all').text(counts.total);
        $('#count-unpaid').text(counts.unpaid);
        $('#count-hmo').text(counts.hmo);
        $('#count-credit').text(counts.credit);
    });
}

function loadPaymentQueue(filter = 'all') {
    $.get('/billing-workbench/queue', { filter }, function(queue) {
        renderQueueList(queue);
    });
}

function renderQueueList(queue) {
    const html = queue.map(item => `
        <div class="queue-item" data-id="${item.patient_id}">
            <div class="queue-info">
                <div class="queue-name">${item.patient_name}</div>
                <div class="queue-meta">${item.file_no}</div>
            </div>
            <span class="badge badge-danger">${item.unpaid_count}</span>
        </div>
    `).join('');
    $('#queue-list').html(html);
}
```

#### 3.4 Patient Selection & Context Loading
```javascript
function selectPatient(patientId) {
    currentPatient = patientId;
    $('#empty-state').hide();
    $('#patient-context').show();
    
    // Load patient header
    loadPatientHeader(patientId);
    
    // Load billing tab (default)
    loadPatientBilling(patientId);
}

function loadPatientHeader(patientId) {
    // Fetch and populate patient metadata
}

function loadPatientBilling(patientId) {
    $.get(`/billing-workbench/patient/${patientId}/billing`, function(data) {
        unpaidItemsCache = data.items;
        renderUnpaidItems(data.items);
        $('#badge-unpaid').text(data.items.length);
    });
}
```

#### 3.5 Payment Processing
```javascript
function processPayment() {
    const selectedItems = getSelectedItems();
    if (!selectedItems.length) {
        showAlert('Please select at least one item', 'warning');
        return;
    }
    
    const payload = {
        patient_id: currentPatient,
        payment_type: $('#payment-type').val(),
        reference_no: $('#payment-reference').val(),
        items: selectedItems
    };
    
    setPaymentLoading(true);
    $.post('/billing-workbench/process-payment', payload, function(response) {
        showAlert('Payment successful', 'success');
        showReceiptModal(response.receipt_a4, response.receipt_thermal);
        loadPatientBilling(currentPatient); // Refresh
        loadQueueCounts(); // Update counters
    }).fail(function(xhr) {
        showAlert(xhr.responseJSON?.message || 'Payment failed', 'danger');
    }).always(function() {
        setPaymentLoading(false);
    });
}
```

### Phase 4: Data Flow & Backend Logic

#### 4.1 Queue Generation Logic
```php
public function getPaymentQueue(Request $request)
{
    $filter = $request->get('filter', 'all');
    
    $query = ProductOrServiceRequest::query()
        ->whereNull('payment_id')
        ->whereNull('invoice_id')
        ->select([
            'user_id',
            DB::raw('COUNT(*) as unpaid_count'),
            DB::raw('MAX(created_at) as last_created')
        ])
        ->groupBy('user_id')
        ->orderByDesc('last_created');
    
    // Apply filters
    if ($filter === 'hmo') {
        $query->where('claims_amount', '>', 0);
    }
    
    $results = $query->get();
    
    // Preload patient data
    $patients = Patient::with('user', 'hmo')
        ->whereIn('user_id', $results->pluck('user_id'))
        ->get()
        ->keyBy('user_id');
    
    $queue = $results->map(function($item) use ($patients) {
        $patient = $patients->get($item->user_id);
        return [
            'patient_id' => $patient->id,
            'patient_name' => userfullname($item->user_id),
            'file_no' => $patient->file_no,
            'unpaid_count' => $item->unpaid_count,
            'hmo' => optional($patient->hmo)->name,
        ];
    });
    
    return response()->json($queue);
}
```

#### 4.2 Transaction History
```php
public function getPatientTransactions($patientId, Request $request)
{
    $patient = Patient::findOrFail($patientId);
    
    $query = Payment::where('patient_id', $patientId);
    
    // Apply filters
    if ($request->has('start_date') && $request->has('end_date')) {
        $query->whereBetween('created_at', [
            Carbon::parse($request->start_date)->startOfDay(),
            Carbon::parse($request->end_date)->endOfDay()
        ]);
    }
    
    if ($request->has('payment_type') && $request->payment_type) {
        $query->where('payment_type', $request->payment_type);
    }
    
    $transactions = $query->orderBy('created_at', 'desc')->get();
    
    return response()->json([
        'transactions' => $transactions,
        'total_amount' => $transactions->sum('total'),
        'total_discount' => $transactions->sum('total_discount'),
    ]);
}
```

### Phase 5: Styling (Match Lab Workbench)

#### 5.1 CSS Structure
- Two-pane layout with flexbox
- Left panel: 20% width, fixed, scrollable queue
- Right panel: 80% width, tabbed interface
- Card-based DataTables with hospital color theming
- Badge counters on tabs and queue items
- Responsive breakpoints for mobile

#### 5.2 Hospital Branding
- Use `appsettings()->hos_color` for primary color
- Apply to tabs, buttons, badges, borders
- Consistent with lab workbench theming

### Phase 6: Receipt Printing

#### 6.1 Receipt Tab Logic
```javascript
function loadPatientReceipts(patientId) {
    $.get(`/billing-workbench/patient/${patientId}/receipts`, function(data) {
        renderReceiptsTable(data.paid_items);
    });
}

function printSelectedReceipts() {
    const selectedPayments = getSelectedPayments();
    $.post('/billing-workbench/print-receipt', {
        patient_id: currentPatient,
        payment_ids: selectedPayments
    }, function(response) {
        showReceiptModal(response.receipt_a4, response.receipt_thermal);
    });
}
```

#### 6.2 Backend Receipt Generation
```php
public function printReceipt(Request $request)
{
    $patient = Patient::findOrFail($request->patient_id);
    $payments = Payment::whereIn('id', $request->payment_ids)->get();
    
    // Aggregate items from selected payments
    $allItems = ProductOrServiceRequest::whereIn('payment_id', $request->payment_ids)
        ->with(['service.price', 'product.price'])
        ->get();
    
    // Build receipt data
    $receiptDetails = /* ... build from items ... */;
    
    // Render receipts (reuse existing templates)
    $a4 = View::make('admin.Accounts.receipt_a4', [
        'site' => appsettings(),
        'patientName' => userfullname($patient->user_id),
        'patientFileNo' => $patient->file_no,
        // ... rest of variables
    ])->render();
    
    $thermal = View::make('admin.Accounts.receipt_thermal', [/* ... */])->render();
    
    return response()->json([
        'receipt_a4' => $a4,
        'receipt_thermal' => $thermal
    ]);
}
```

### Phase 7: Report Modal (My Transactions)

#### 7.1 Modal Trigger
```javascript
$('#btn-report').on('click', function() {
    openTransactionReportModal();
});

function openTransactionReportModal() {
    $.get('/billing-workbench/my-transactions', function(data) {
        renderTransactionReportModal(data);
    });
}
```

#### 7.2 Backend Method
```php
public function getMyTransactions(Request $request)
{
    $userId = Auth::id();
    
    $query = Payment::where('user_id', $userId);
    
    // Date filtering
    $from = $request->input('from', now()->startOfMonth()->toDateString());
    $to = $request->input('to', now()->toDateString());
    
    $query->whereBetween('created_at', [
        Carbon::parse($from)->startOfDay(),
        Carbon::parse($to)->endOfDay()
    ]);
    
    $transactions = $query->orderBy('created_at', 'desc')->get();
    
    $summary = [
        'total_amount' => $transactions->sum('total'),
        'total_discount' => $transactions->sum('total_discount'),
        'count' => $transactions->count(),
        'by_type' => $transactions->groupBy('payment_type')->map(function($group) {
            return [
                'count' => $group->count(),
                'amount' => $group->sum('total'),
                'discount' => $group->sum('total_discount')
            ];
        })
    ];
    
    return response()->json([
        'transactions' => $transactions,
        'summary' => $summary,
        'from' => $from,
        'to' => $to
    ]);
}
```

## Migration Strategy

### Step 1: Build New Workbench (Parallel)
- Create new routes, controller, view
- Test thoroughly in isolation
- Keep old route active

### Step 2: Update Menu/Navigation
- Add "Billing Workbench" link to sidebar
- Hide old "Services and Products" link or rename it

### Step 3: User Training & Rollout
- Train billing staff on new interface
- Monitor usage and collect feedback

### Step 4: Deprecate Old View (Optional)
- After successful rollout, consider removing old view
- Or keep it as "Simple Billing" for basic use cases

## Testing Checklist

- [ ] Patient search works (file_no, name, phone)
- [ ] Queue loads and filters correctly
- [ ] Patient selection shows context and tabs
- [ ] Unpaid items load with HMO coverage badges
- [ ] Payment processing works with AJAX
- [ ] Receipts generate correctly (A4 & thermal)
- [ ] Transaction history filters work
- [ ] Account summary displays accurate data
- [ ] Receipt printing from receipts tab works
- [ ] My Transactions report modal opens and filters
- [ ] Queue counters update after payment
- [ ] HMO claims are created correctly
- [ ] Discounts and quantities calculated correctly
- [ ] Amount in words displays correctly
- [ ] Payment type shows in receipt footer
- [ ] Modal closes and page doesn't refresh unnecessarily

## File Summary

### New Files to Create
1. `app/Http/Controllers/BillingWorkbenchController.php`
2. `resources/views/admin/billing/workbench.blade.php`

### Files to Modify
1. `routes/web.php` - Add billing workbench routes
2. `resources/views/admin/partials/sidebar.blade.php` - Add menu link

### Files to Reference (No Changes)
1. `app/Http/Controllers/Account/paymentController.php` - Reuse payment logic
2. `resources/views/admin/Accounts/receipt_a4.blade.php` - Receipt template
3. `resources/views/admin/Accounts/receipt_thermal.blade.php` - Receipt template
4. `app/Http/Controllers/LabWorkbenchController.php` - Pattern reference
5. `resources/views/admin/lab/workbench.blade.php` - UI pattern reference

## Advantages of This Approach

1. **Unified Interface**: All billing functions in one place
2. **Efficient Workflow**: No page reloads, pure AJAX
3. **Context Preservation**: Patient stays selected while working
4. **Queue Management**: Clear visual queue with counters
5. **Comprehensive View**: All patient billing data accessible via tabs
6. **Consistent UX**: Matches lab workbench pattern users already know
7. **Flexible Filtering**: Multiple queue views (all, HMO, credit)
8. **Reporting Built-in**: My Transactions accessible via report button
9. **Receipt Management**: Easy reprinting of past receipts
10. **Scalable**: Easy to add new tabs or features

## Future Enhancements

1. **Credit Management Tab**: Add/deduct credit, view credit history
2. **Bulk Printing**: Print multiple receipts at once
3. **Export Transactions**: CSV/Excel export of transaction history
4. **SMS/Email Receipts**: Send receipts to patients
5. **Payment Plans**: Create and manage installment plans
6. **Real-time Notifications**: Alert when new items added to queue
7. **Dashboard Widgets**: Summary cards for today's collections
8. **Audit Trail**: Track who processed each payment
9. **Multi-currency**: Support for foreign currency payments
10. **Integration**: Link with accounting software via API

## Notes

- All existing payment logic (`ajaxPay`, receipt templates, HMO claims) will be reused
- No changes to database schema required
- Old product-or-service-request route can remain for backward compatibility
- Consider permission checks (e.g., `can('process-billing')`) throughout controller
- Use same receipt templates to maintain consistency
- Queue should auto-refresh every 30-60 seconds or via websockets
- Consider adding keyboard shortcuts for power users (e.g., Ctrl+P for payment)

---

**Document Version**: 1.0  
**Date**: January 6, 2026  
**Status**: Ready for Implementation
