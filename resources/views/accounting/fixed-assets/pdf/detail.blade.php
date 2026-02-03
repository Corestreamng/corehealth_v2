@extends('accounting.reports.pdf.layout')

@section('title', 'Asset Details - ' . $fixedAsset->asset_number)
@section('report_title', 'Fixed Asset Details')
@section('report_subtitle', $fixedAsset->asset_number . ' - ' . $fixedAsset->name)

@section('styles')
<style>
    .asset-info-grid {
        display: table;
        width: 100%;
        margin-bottom: 15px;
    }
    .info-section {
        display: table-cell;
        width: 50%;
        padding: 10px;
        vertical-align: top;
    }
    .info-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        margin-bottom: 10px;
    }
    .info-box h4 {
        font-size: 11px;
        font-weight: 700;
        color: #495057;
        margin-bottom: 8px;
        padding-bottom: 5px;
        border-bottom: 2px solid #dee2e6;
    }
    .info-row {
        display: table;
        width: 100%;
        margin-bottom: 5px;
        font-size: 10px;
    }
    .info-label {
        display: table-cell;
        color: #666;
        width: 50%;
    }
    .info-value {
        display: table-cell;
        font-weight: 600;
        text-align: right;
        width: 50%;
    }

    .book-value-highlight {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 15px;
    }
    .book-value-highlight .label {
        font-size: 10px;
        opacity: 0.9;
    }
    .book-value-highlight .amount {
        font-size: 24px;
        font-weight: 700;
        margin: 5px 0;
    }

    .depreciation-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        font-size: 9px;
    }
    .depreciation-table th {
        background: #495057;
        color: white;
        padding: 6px;
        font-size: 9px;
        font-weight: 600;
        border: 1px solid #dee2e6;
    }
    .depreciation-table td {
        padding: 5px;
        border: 1px solid #dee2e6;
    }
    .depreciation-table tr:nth-child(even) {
        background: #f8f9fa;
    }

    .status-badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 600;
        display: inline-block;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-disposed { background: #d6d8db; color: #383d41; }
    .status-voided { background: #f8d7da; color: #721c24; }

    .text-right { text-align: right; }
    .text-center { text-align: center; }

    .section-title {
        font-size: 12px;
        font-weight: 700;
        color: #495057;
        margin: 20px 0 10px 0;
        padding-bottom: 5px;
        border-bottom: 2px solid #dee2e6;
    }
</style>
@endsection

@section('content')
<!-- Asset Status Badge -->
<div style="text-align: right; margin-bottom: 10px;">
    @php
        $statusClass = match($fixedAsset->status) {
            'active' => 'status-active',
            'disposed' => 'status-disposed',
            'voided' => 'status-voided',
            default => 'status-active'
        };
    @endphp
    <span class="status-badge {{ $statusClass }}">
        {{ ucfirst(str_replace('_', ' ', $fixedAsset->status)) }}
    </span>
</div>

<!-- Book Value Highlight -->
<div class="book-value-highlight">
    <div class="label">Net Book Value</div>
    <div class="amount">₦{{ number_format($fixedAsset->book_value, 2) }}</div>
    @if($fixedAsset->status === 'active')
        <div class="label">Monthly Depreciation: ₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}</div>
    @endif
</div>

<!-- Asset Information Grid -->
<div class="asset-info-grid">
    <div class="info-section">
        <!-- Cost Information -->
        <div class="info-box">
            <h4>Cost Information</h4>
            <div class="info-row">
                <span class="info-label">Acquisition Cost:</span>
                <span class="info-value">₦{{ number_format($fixedAsset->acquisition_cost, 2) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Additional Costs:</span>
                <span class="info-value">₦{{ number_format($fixedAsset->additional_costs, 2) }}</span>
            </div>
            <div class="info-row" style="border-top: 1px solid #dee2e6; padding-top: 5px; margin-top: 5px;">
                <span class="info-label"><strong>Total Cost:</strong></span>
                <span class="info-value"><strong>₦{{ number_format($fixedAsset->total_cost, 2) }}</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Salvage Value:</span>
                <span class="info-value">₦{{ number_format($fixedAsset->salvage_value, 2) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Depreciable Amount:</span>
                <span class="info-value">₦{{ number_format($fixedAsset->depreciable_amount, 2) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Accumulated Depreciation:</span>
                <span class="info-value text-danger">₦{{ number_format($fixedAsset->accumulated_depreciation, 2) }}</span>
            </div>
        </div>

        <!-- Depreciation Settings -->
        <div class="info-box">
            <h4>Depreciation Settings</h4>
            <div class="info-row">
                <span class="info-label">Method:</span>
                <span class="info-value">{{ ucfirst(str_replace('_', ' ', $fixedAsset->depreciation_method)) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Useful Life:</span>
                <span class="info-value">{{ $fixedAsset->useful_life_years }} years ({{ $fixedAsset->useful_life_months }} months)</span>
            </div>
            <div class="info-row">
                <span class="info-label">Monthly Depreciation:</span>
                <span class="info-value">₦{{ number_format($fixedAsset->monthly_depreciation, 2) }}</span>
            </div>
            @if($fixedAsset->last_depreciation_date)
            <div class="info-row">
                <span class="info-label">Last Depreciation:</span>
                <span class="info-value">{{ $fixedAsset->last_depreciation_date->format('M d, Y') }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="info-section">
        <!-- Asset Details -->
        <div class="info-box">
            <h4>Asset Details</h4>
            <div class="info-row">
                <span class="info-label">Category:</span>
                <span class="info-value">{{ $fixedAsset->category?->name ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Department:</span>
                <span class="info-value">{{ $fixedAsset->department?->name ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Custodian:</span>
                <span class="info-value">{{ $fixedAsset->custodian?->name ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Location:</span>
                <span class="info-value">{{ $fixedAsset->location ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Serial Number:</span>
                <span class="info-value">{{ $fixedAsset->serial_number ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Model:</span>
                <span class="info-value">{{ $fixedAsset->model ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Manufacturer:</span>
                <span class="info-value">{{ $fixedAsset->manufacturer ?? '-' }}</span>
            </div>
        </div>

        <!-- Important Dates -->
        <div class="info-box">
            <h4>Important Dates</h4>
            <div class="info-row">
                <span class="info-label">Acquisition Date:</span>
                <span class="info-value">{{ $fixedAsset->acquisition_date?->format('M d, Y') ?? '-' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">In-Service Date:</span>
                <span class="info-value">{{ $fixedAsset->in_service_date?->format('M d, Y') ?? '-' }}</span>
            </div>
            @if($fixedAsset->warranty_expiry_date)
            <div class="info-row">
                <span class="info-label">Warranty Expiry:</span>
                <span class="info-value">{{ $fixedAsset->warranty_expiry_date->format('M d, Y') }}</span>
            </div>
            @endif
            @if($fixedAsset->insurance_expiry_date)
            <div class="info-row">
                <span class="info-label">Insurance Expiry:</span>
                <span class="info-value">{{ $fixedAsset->insurance_expiry_date->format('M d, Y') }}</span>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Depreciation History -->
@if($depreciationHistory->count() > 0)
<h4 class="section-title">Depreciation History (Last 24 Months)</h4>
<table class="depreciation-table">
    <thead>
        <tr>
            <th>Date</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Book Value After</th>
            <th>Journal Entry</th>
        </tr>
    </thead>
    <tbody>
        @foreach($depreciationHistory as $dep)
        <tr>
            <td>{{ $dep->depreciation_date->format('M d, Y') }}</td>
            <td class="text-right">₦{{ number_format($dep->amount, 2) }}</td>
            <td class="text-right">₦{{ number_format($dep->book_value_after, 2) }}</td>
            <td>{{ $dep->journalEntry?->entry_number ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
@endsection
