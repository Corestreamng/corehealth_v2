@extends('accounting.reports.pdf.layout')

@section('title', 'Aged Payables')
@section('report_title', 'Aged Payables Report')
@section('report_subtitle', 'As of ' . $asOfDate->format('F d, Y'))

@section('styles')
<style>
    .summary-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .summary-cell {
        display: table-cell;
        text-align: center;
        padding: 8px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #dc3545; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-cell.danger { background: #dc3545; color: white; }
    .summary-cell.dark { background: #343a40; color: white; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }
    
    .section-title {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 12px;
        font-weight: bold;
    }
    
    .category-header {
        background: #e9ecef;
        padding: 6px 10px;
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: bold;
        border-left: 3px solid #007bff;
    }
    .category-header.orange { border-left-color: #fd7e14; }
    .category-header.teal { border-left-color: #20c997; }
    .category-header.warning { border-left-color: #ffc107; }
    .category-header.info { border-left-color: #17a2b8; }
    .category-header.danger { border-left-color: #dc3545; }
    
    .category-summary {
        font-size: 10px;
        color: #666;
        margin-bottom: 5px;
    }
    
    .detail-table {
        margin-bottom: 15px;
    }
    .detail-table th {
        background: #f5f5f5;
        font-size: 9px;
        padding: 6px;
    }
    .detail-table td {
        font-size: 9px;
        padding: 5px;
    }
    
    .po-table th {
        background: #fff3cd;
        font-size: 8px;
        padding: 4px;
    }
    .po-table td {
        font-size: 8px;
        padding: 3px;
    }
    
    .aging-badge {
        font-size: 8px;
        padding: 2px 5px;
        border-radius: 2px;
    }
    .aging-current { background: #d4edda; color: #155724; }
    .aging-warning { background: #fff3cd; color: #856404; }
    .aging-danger { background: #f8d7da; color: #721c24; }
    .aging-dark { background: #343a40; color: white; }
    
    .priority-box {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        padding: 8px;
        margin-bottom: 15px;
    }
    .priority-box h5 {
        color: #721c24;
        margin: 0 0 5px 0;
        font-size: 11px;
    }
    
    .grand-total-box {
        background: #212529;
        color: white;
        padding: 10px 15px;
        text-align: right;
        margin-top: 15px;
    }
    .grand-total-box span {
        font-size: 16px;
        font-weight: bold;
    }
</style>
@endsection

@section('content')
@php
    $totals = $report['totals'] ?? [];
    $summary = $report['summary'] ?? [];
    $categories = $report['categories'] ?? [];
    $priorities = $report['priorities'] ?? [];
@endphp

<!-- Aging Summary Grid -->
<div class="summary-grid">
    <div class="summary-cell primary">
        <div class="summary-label">Total Payables</div>
        <div class="summary-value">₦{{ number_format($report['total'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell success">
        <div class="summary-label">Current</div>
        <div class="summary-value">₦{{ number_format($totals['current'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell info">
        <div class="summary-label">1-30 Days</div>
        <div class="summary-value">₦{{ number_format($totals['1_30'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell warning">
        <div class="summary-label">31-60 Days</div>
        <div class="summary-value">₦{{ number_format($totals['31_60'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell danger">
        <div class="summary-label">61-90 Days</div>
        <div class="summary-value">₦{{ number_format($totals['61_90'] ?? 0, 2) }}</div>
    </div>
    <div class="summary-cell dark">
        <div class="summary-label">Over 90</div>
        <div class="summary-value">₦{{ number_format($totals['over_90'] ?? 0, 2) }}</div>
    </div>
</div>

<!-- Payment Priority Alert -->
@if(count($priorities) > 0)
<div class="priority-box">
    <h5>⚠️ PAYMENT PRIORITY ALERT</h5>
    <p style="font-size: 9px; margin: 0; color: #721c24;">
        {{ count($priorities) }} outstanding payments require immediate attention. Top 5 priorities listed below.
    </p>
    <table style="width: 100%; margin-top: 8px; font-size: 8px;">
        <tr style="background: #f5c6cb;">
            <th style="padding: 3px;">#</th>
            <th style="padding: 3px;">Vendor</th>
            <th style="padding: 3px;">Reference</th>
            <th style="padding: 3px;">Days</th>
            <th style="padding: 3px; text-align: right;">Amount</th>
        </tr>
        @foreach(array_slice($priorities, 0, 5) as $index => $priority)
        <tr style="background: {{ $index % 2 == 0 ? '#fff' : '#fce8e8' }};">
            <td style="padding: 2px;">{{ $index + 1 }}</td>
            <td style="padding: 2px;">{{ $priority['vendor_name'] }}</td>
            <td style="padding: 2px;"><code>{{ $priority['reference'] }}</code></td>
            <td style="padding: 2px;">{{ $priority['days_overdue'] }}+ days</td>
            <td style="padding: 2px; text-align: right; color: #dc3545; font-weight: bold;">₦{{ number_format($priority['amount'], 2) }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

<!-- Category Breakdown -->
<div class="section-title">PAYABLES BY CATEGORY</div>

<!-- 1. Supplier Purchase Orders -->
@if(!empty($categories['supplier_payables']['details']))
<div class="category-header orange">
    <i>🚚</i> SUPPLIER PURCHASE ORDERS
    <span style="float: right;">Total: ₦{{ number_format($categories['supplier_payables']['total'] ?? 0, 2) }} | {{ $categories['supplier_payables']['count'] ?? 0 }} suppliers</span>
</div>
<div class="category-summary">{{ $categories['supplier_payables']['description'] ?? '' }}</div>

@foreach($categories['supplier_payables']['details'] as $supplierItem)
<table class="detail-table" style="margin-bottom: 5px;">
    <tr style="background: #ffecd1;">
        <td style="padding: 6px; font-weight: bold;" colspan="2">
            {{ $supplierItem['supplier_name'] }}
            @if($supplierItem['contact_person'])
            <span style="font-weight: normal; font-size: 9px;"> - {{ $supplierItem['contact_person'] }}</span>
            @endif
        </td>
        <td style="padding: 6px; text-align: right;">
            <span style="font-size: 9px;">{{ $supplierItem['po_count'] }} POs</span> |
            <strong style="color: #fd7e14;">₦{{ number_format($supplierItem['outstanding_amount'], 2) }}</strong>
        </td>
    </tr>
</table>
<table class="po-table" style="margin-left: 10px; width: calc(100% - 10px); margin-bottom: 10px;">
    <tr>
        <th style="width: 20%;">PO Number</th>
        <th style="width: 15%;">Date</th>
        <th style="width: 15%;">Status</th>
        <th style="width: 17%;" class="text-right">Total</th>
        <th style="width: 16%;" class="text-right">Paid</th>
        <th style="width: 17%;" class="text-right">Outstanding</th>
    </tr>
    @foreach($supplierItem['purchase_orders'] ?? [] as $po)
    <tr>
        <td><code>{{ $po['po_number'] }}</code></td>
        <td>{{ $po['po_date'] }}</td>
        <td>
            <span style="font-size: 7px; padding: 1px 3px; background: {{ $po['payment_status'] == 'unpaid' ? '#f8d7da' : '#fff3cd' }}; border-radius: 2px;">
                {{ ucfirst($po['payment_status']) }}
            </span>
        </td>
        <td class="text-right">₦{{ number_format($po['total_amount'], 2) }}</td>
        <td class="text-right" style="color: #28a745;">₦{{ number_format($po['amount_paid'], 2) }}</td>
        <td class="text-right" style="color: #dc3545; font-weight: bold;">₦{{ number_format($po['outstanding'], 2) }}</td>
    </tr>
    @endforeach
</table>
@endforeach
@endif

<!-- 2. Patient Deposits -->
@if(!empty($categories['patient_deposits']['details']))
<div class="category-header teal">
    <i>💵</i> PATIENT DEPOSITS (LIABILITY)
    <span style="float: right;">Total: ₦{{ number_format($categories['patient_deposits']['total'] ?? 0, 2) }} | {{ $categories['patient_deposits']['count'] ?? 0 }} patients</span>
</div>
<div class="category-summary">{{ $categories['patient_deposits']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 12%;">File No.</th>
            <th style="width: 28%;">Patient Name</th>
            <th style="width: 15%;">Phone</th>
            <th style="width: 15%;">HMO</th>
            <th style="width: 12%;">Aging</th>
            <th style="width: 18%;" class="text-right">Deposit Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['patient_deposits']['details'] as $item)
        <tr>
            <td><code>{{ $item['patient_file_no'] }}</code></td>
            <td>{{ $item['patient_name'] }}</td>
            <td>{{ $item['patient_phone'] }}</td>
            <td>{{ $item['hmo_name'] }}</td>
            <td>
                @php
                    $agingClass = match($item['aging_bucket']) {
                        'current' => 'aging-current',
                        '1_30', '31_60' => 'aging-warning',
                        '61_90', 'over_90' => 'aging-danger',
                        default => 'aging-current'
                    };
                    $agingLabel = match($item['aging_bucket']) {
                        'current' => 'Current', '1_30' => '1-30d', '31_60' => '31-60d',
                        '61_90' => '61-90d', 'over_90' => '90+d', default => 'Current'
                    };
                @endphp
                <span class="aging-badge {{ $agingClass }}">{{ $agingLabel }}</span>
            </td>
            <td class="text-right"><strong style="color: #20c997;">₦{{ number_format($item['amount'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- 3. Supplier Credits -->
@if(!empty($categories['supplier_credits']['details']))
<div class="category-header warning">
    <i>💳</i> SUPPLIER CREDITS
    <span style="float: right;">Total: ₦{{ number_format($categories['supplier_credits']['total'] ?? 0, 2) }} | {{ $categories['supplier_credits']['count'] ?? 0 }} suppliers</span>
</div>
<div class="category-summary">{{ $categories['supplier_credits']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 30%;">Supplier Name</th>
            <th style="width: 20%;">Contact</th>
            <th style="width: 25%;">Phone / Email</th>
            <th style="width: 25%;" class="text-right">Credit Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['supplier_credits']['details'] as $item)
        <tr>
            <td><strong>{{ $item['supplier_name'] }}</strong></td>
            <td>{{ $item['contact_person'] ?? '-' }}</td>
            <td style="font-size: 8px;">{{ $item['phone'] ?? '-' }}<br>{{ $item['email'] ?? '-' }}</td>
            <td class="text-right"><strong style="color: #ffc107;">₦{{ number_format($item['credit_amount'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- 4. GL Accounts Payable -->
@if(!empty($categories['gl_payables']['details']))
<div class="category-header info">
    <i>📒</i> GL ACCOUNTS PAYABLE
    <span style="float: right;">Total: ₦{{ number_format($categories['gl_payables']['total'] ?? 0, 2) }} | {{ $categories['gl_payables']['count'] ?? 0 }} accounts</span>
</div>
<div class="category-summary">{{ $categories['gl_payables']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 25%;">Account Code</th>
            <th style="width: 50%;">Account Name</th>
            <th style="width: 25%;" class="text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['gl_payables']['details'] as $item)
        <tr>
            <td><code>{{ $item['account_code'] }}</code></td>
            <td>{{ $item['account_name'] }}</td>
            <td class="text-right"><strong style="color: #17a2b8;">₦{{ number_format($item['balance'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- Grand Total Box -->
<div class="grand-total-box">
    GRAND TOTAL PAYABLES: <span>₦{{ number_format($report['total'] ?? 0, 2) }}</span>
</div>

@endsection
