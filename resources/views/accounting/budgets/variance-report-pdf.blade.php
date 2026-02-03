@extends('accounting.reports.pdf.layout')

@section('title', 'Budget Variance Report')
@section('report_title', 'Budget Variance Report')
@section('report_subtitle', ($fiscalYear ? 'Fiscal Year: ' . $fiscalYear->year_name : '') . ($department ? ' | Department: ' . $department->name : ''))

@section('styles')
<style>
    /* Summary Grid */
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
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-cell.danger { background: #dc3545; color: white; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    /* Section Title */
    .section-title {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 12px;
        font-weight: bold;
    }

    /* Budget Section Header */
    .budget-header {
        background: #e9ecef;
        padding: 6px 10px;
        margin-bottom: 8px;
        font-size: 11px;
        font-weight: bold;
        border-left: 3px solid #007bff;
    }

    /* Budget Section */
    .budget-section {
        margin-bottom: 20px;
        page-break-inside: avoid;
    }

    /* Detail Table */
    .detail-table {
        margin-bottom: 15px;
    }
    .detail-table th {
        background: #f5f5f5;
        font-size: 9px;
        padding: 6px;
        border-bottom: 2px solid #333;
        text-transform: uppercase;
    }
    .detail-table td {
        font-size: 9px;
        padding: 5px 6px;
    }
    .detail-table .total-row {
        font-weight: bold;
        background-color: #f0f0f0;
        border-top: 2px solid #333;
    }

    /* Variance Colors */
    .variance-positive {
        color: #28a745;
    }
    .variance-negative {
        color: #dc3545;
    }

    /* Badges */
    .badge {
        display: inline-block;
        padding: 2px 5px;
        border-radius: 2px;
        font-size: 8px;
        font-weight: 600;
    }
    .badge-success {
        background: #d4edda;
        color: #155724;
    }
    .badge-warning {
        background: #fff3cd;
        color: #856404;
    }
    .badge-danger {
        background: #f8d7da;
        color: #721c24;
    }
</style>
@endsection

@section('content')
<!-- Summary Grid -->
<div class="summary-grid">
    <div class="summary-cell primary">
        <div class="summary-label">Total Budgeted</div>
        <div class="summary-value">₦{{ number_format($summary['total_budgeted'], 2) }}</div>
    </div>
    <div class="summary-cell {{ $summary['total_actual'] > $summary['total_budgeted'] ? 'danger' : 'success' }}">
        <div class="summary-label">Total Actual</div>
        <div class="summary-value">₦{{ number_format($summary['total_actual'], 2) }}</div>
    </div>
    <div class="summary-cell {{ $summary['total_variance'] >= 0 ? 'success' : 'danger' }}">
        <div class="summary-label">Total Variance</div>
        <div class="summary-value">₦{{ number_format($summary['total_variance'], 2) }}</div>
    </div>
    <div class="summary-cell warning">
        <div class="summary-label">Variance %</div>
        <div class="summary-value">{{ number_format($summary['variance_percent'], 1) }}%</div>
    </div>
</div>

<!-- Budget Details -->
<div class="section-title">BUDGET VARIANCE DETAILS</div>

@foreach($reportData as $budget)
    <div class="budget-section">
        <div class="budget-header">
            {{ $budget['budget_name'] }} - {{ $budget['department'] }} ({{ $budget['fiscal_year'] }})
            <span style="float: right;">Budgeted: ₦{{ number_format($budget['total_budgeted'], 2) }} | Actual: ₦{{ number_format($budget['total_actual'], 2) }}</span>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Code</th>
                    <th style="width: 28%;">Account Name</th>
                    <th class="text-right" style="width: 13%;">Budgeted</th>
                    <th class="text-right" style="width: 13%;">Actual</th>
                    <th class="text-right" style="width: 13%;">Variance</th>
                    <th class="text-right" style="width: 11%;">Variance %</th>
                    <th class="text-right" style="width: 10%;">Utilization</th>
                </tr>
            </thead>
            <tbody>
                @foreach($budget['items'] as $item)
                    <tr>
                        <td>{{ $item['account_code'] }}</td>
                        <td>{{ $item['account_name'] }}</td>
                        <td class="text-right">₦{{ number_format($item['budgeted'], 2) }}</td>
                        <td class="text-right">₦{{ number_format($item['actual'], 2) }}</td>
                        <td class="text-right {{ $item['variance'] >= 0 ? 'variance-positive' : 'variance-negative' }}">
                            {{ $item['variance'] >= 0 ? '+' : '' }}₦{{ number_format(abs($item['variance']), 2) }}
                        </td>
                        <td class="text-right">
                            @php
                                $badgeClass = abs($item['variance_percent']) > 20 ? 'badge-danger' : (abs($item['variance_percent']) > 10 ? 'badge-warning' : 'badge-success');
                            @endphp
                            <span class="badge {{ $badgeClass }}">
                                {{ $item['variance_percent'] >= 0 ? '+' : '' }}{{ number_format($item['variance_percent'], 1) }}%
                            </span>
                        </td>
                        <td class="text-right">
                            @php
                                $utilization = $item['budgeted'] > 0 ? ($item['actual'] / $item['budgeted']) * 100 : 0;
                            @endphp
                            {{ number_format($utilization, 1) }}%
                        </td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2"><strong>BUDGET TOTAL</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($budget['total_budgeted'], 2) }}</strong></td>
                    <td class="text-right"><strong>₦{{ number_format($budget['total_actual'], 2) }}</strong></td>
                    <td class="text-right {{ $budget['total_variance'] >= 0 ? 'variance-positive' : 'variance-negative' }}">
                        <strong>{{ $budget['total_variance'] >= 0 ? '+' : '' }}₦{{ number_format(abs($budget['total_variance']), 2) }}</strong>
                    </td>
                    <td class="text-right">
                        @php
                            $badgeClass = abs($budget['variance_percent']) > 20 ? 'badge-danger' : (abs($budget['variance_percent']) > 10 ? 'badge-warning' : 'badge-success');
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            <strong>{{ $budget['variance_percent'] >= 0 ? '+' : '' }}{{ number_format($budget['variance_percent'], 1) }}%</strong>
                        </span>
                    </td>
                    <td class="text-right">
                        @php
                            $utilization = $budget['total_budgeted'] > 0 ? ($budget['total_actual'] / $budget['total_budgeted']) * 100 : 0;
                        @endphp
                        <strong>{{ number_format($utilization, 1) }}%</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
@endsection
