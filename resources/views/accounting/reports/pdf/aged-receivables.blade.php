@extends('accounting.reports.pdf.layout')

@section('title', 'Aged Receivables')
@section('report_title', 'Aged Receivables Report')
@section('report_subtitle', 'As of ' . $asOfDate->format('F d, Y'))

@section('styles')
<style>
    .summary-box {
        background-color: #f8f9fa;
        padding: 12px;
        margin-bottom: 15px;
        border-radius: 4px;
    }
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
    .summary-cell.primary { background: #007bff; color: white; }
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
    .category-header.danger { border-left-color: #dc3545; }
    .category-header.purple { border-left-color: #6f42c1; }
    .category-header.info { border-left-color: #17a2b8; }

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

    .aging-badge {
        font-size: 8px;
        padding: 2px 5px;
        border-radius: 2px;
    }
    .aging-current { background: #d4edda; color: #155724; }
    .aging-warning { background: #fff3cd; color: #856404; }
    .aging-danger { background: #f8d7da; color: #721c24; }
    .aging-dark { background: #343a40; color: white; }

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
@endphp

<!-- Aging Summary Grid -->
<div class="summary-grid">
    <div class="summary-cell primary">
        <div class="summary-label">Total Outstanding</div>
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

<!-- Category Breakdown -->
<div class="section-title">RECEIVABLES BY CATEGORY</div>

<!-- 1. Patient Overdrafts -->
@if(!empty($categories['patient_overdrafts']['details']))
<div class="category-header danger">
    <i>👤</i> PATIENT OVERDRAFTS
    <span style="float: right;">Total: ₦{{ number_format($categories['patient_overdrafts']['total'] ?? 0, 2) }} | {{ $categories['patient_overdrafts']['count'] ?? 0 }} patients</span>
</div>
<div class="category-summary">{{ $categories['patient_overdrafts']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 12%;">File No.</th>
            <th style="width: 25%;">Patient Name</th>
            <th style="width: 15%;">Phone</th>
            <th style="width: 15%;">HMO</th>
            <th style="width: 13%;">Last Activity</th>
            <th style="width: 10%;">Aging</th>
            <th style="width: 15%;" class="text-right">Amount Owed</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['patient_overdrafts']['details'] as $item)
        <tr>
            <td><code>{{ $item['patient_file_no'] }}</code></td>
            <td>{{ $item['patient_name'] }}</td>
            <td>{{ $item['patient_phone'] }}</td>
            <td>{{ $item['hmo_name'] }}</td>
            <td>{{ $item['last_activity'] }}</td>
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
            <td class="text-right"><strong style="color: #dc3545;">₦{{ number_format($item['amount'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- 2. HMO Claims Pending Remittance -->
@if(!empty($categories['hmo_claims']['details']))
<div class="category-header purple">
    <i>🏥</i> HMO CLAIMS PENDING REMITTANCE
    <span style="float: right;">Total: ₦{{ number_format($categories['hmo_claims']['total'] ?? 0, 2) }} | {{ $categories['hmo_claims']['count'] ?? 0 }} HMOs</span>
</div>
<div class="category-summary">{{ $categories['hmo_claims']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 30%;">HMO Name</th>
            <th style="width: 15%;" class="text-center">Claims Count</th>
            <th style="width: 15%;">Aging</th>
            <th style="width: 20%;" class="text-right">Total Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['hmo_claims']['details'] as $hmoItem)
        <tr>
            <td><strong>{{ $hmoItem['hmo_name'] }}</strong></td>
            <td class="text-center">{{ $hmoItem['claim_count'] }} claims</td>
            <td>
                @php
                    $agingClass = match($hmoItem['aging_bucket'] ?? 'current') {
                        'current' => 'aging-current',
                        '1_30', '31_60' => 'aging-warning',
                        '61_90', 'over_90' => 'aging-danger',
                        default => 'aging-current'
                    };
                    $agingLabel = match($hmoItem['aging_bucket'] ?? 'current') {
                        'current' => 'Current', '1_30' => '1-30d', '31_60' => '31-60d',
                        '61_90' => '61-90d', 'over_90' => '90+d', default => 'Current'
                    };
                @endphp
                <span class="aging-badge {{ $agingClass }}">{{ $agingLabel }}</span>
            </td>
            <td class="text-right"><strong style="color: #6f42c1;">₦{{ number_format($hmoItem['amount'], 2) }}</strong></td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<!-- 3. GL Accounts Receivable -->
@if(!empty($categories['gl_receivables']['details']))
<div class="category-header info">
    <i>📒</i> GL ACCOUNTS RECEIVABLE
    <span style="float: right;">Total: ₦{{ number_format($categories['gl_receivables']['total'] ?? 0, 2) }} | {{ $categories['gl_receivables']['count'] ?? 0 }} accounts</span>
</div>
<div class="category-summary">{{ $categories['gl_receivables']['description'] ?? '' }}</div>
<table class="detail-table">
    <thead>
        <tr>
            <th style="width: 20%;">Account Code</th>
            <th style="width: 50%;">Account Name</th>
            <th style="width: 30%;" class="text-right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @foreach($categories['gl_receivables']['details'] as $item)
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
    GRAND TOTAL RECEIVABLES: <span>₦{{ number_format($report['total'] ?? 0, 2) }}</span>
</div>

@endsection
