@extends('accounting.reports.pdf.layout')

@section('title', 'Balance Sheet')
@section('report_title', 'Balance Sheet')
@section('report_subtitle', 'As of ' . $asOfDate->format('F d, Y'))

@section('styles')
<style>
    .columns { width: 100%; }
    .column {
        width: 48%;
        display: inline-block;
        vertical-align: top;
    }
    .column-left {
        border-right: 1px solid #ddd;
        padding-right: 15px;
    }
    .column-right { padding-left: 15px; }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #2196f3;
        border-bottom: 2px solid #2196f3;
        padding-bottom: 5px;
        margin-bottom: 15px;
    }
    .subsection { margin-bottom: 15px; }
    .subsection-title {
        font-weight: bold;
        color: #666;
        margin-bottom: 8px;
    }
    .indent { padding-left: 15px; }
    .subtotal-row {
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 5px;
    }
    .total-box {
        margin-top: 15px;
        padding: 10px;
        background-color: #2196f3;
        color: white;
    }
    .total-box table { color: white; }
</style>
@endsection

@section('content')
@php
    // Data structure from ReportService:
    // $report['assets']['groups'] = [{name, accounts: [{id, code, name, balance}], total}]
    // $report['assets']['total'] = total assets
    // $report['liabilities'] and $report['equity'] have same structure
    // $report['total_assets'], $report['total_liabilities'], $report['total_equity']

    $assetGroups = $report['assets']['groups'] ?? [];
    $liabilityGroups = $report['liabilities']['groups'] ?? [];
    $equityGroups = $report['equity']['groups'] ?? [];

    $totalAssets = $report['total_assets'] ?? $report['assets']['total'] ?? 0;
    $totalLiabilities = $report['total_liabilities'] ?? $report['liabilities']['total'] ?? 0;
    $totalEquity = $report['total_equity'] ?? $report['equity']['total'] ?? 0;
@endphp

<table class="columns">
    <tr>
        <!-- Assets Column -->
        <td class="column column-left">
            <div class="section-title">ASSETS</div>

            @php $calculatedAssets = 0; @endphp
            @if(is_array($assetGroups) && count($assetGroups) > 0)
                @foreach($assetGroups as $group)
                    <div class="subsection">
                        <div class="subsection-title">{{ $group['name'] ?? 'Assets' }}</div>
                        <table>
                            @foreach($group['accounts'] ?? [] as $account)
                                <tr>
                                    <td class="indent">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                    <td class="text-right" style="width: 100px;">₦{{ number_format($account['balance'] ?? 0, 2) }}</td>
                                </tr>
                                @php $calculatedAssets += ($account['balance'] ?? 0); @endphp
                            @endforeach
                            <tr class="subtotal-row">
                                <td>Total {{ $group['name'] ?? '' }}</td>
                                <td class="text-right">₦{{ number_format($group['total'] ?? 0, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @else
                <div class="subsection">
                    <p style="color: #999; text-align: center;">No assets recorded</p>
                </div>
            @endif

            <div class="total-box">
                <table>
                    <tr>
                        <td><strong>TOTAL ASSETS</strong></td>
                        <td class="text-right" style="width: 100px;"><strong>₦{{ number_format($totalAssets ?: $calculatedAssets, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
        </td>

        <!-- Liabilities & Equity Column -->
        <td class="column column-right">
            <div class="section-title">LIABILITIES & EQUITY</div>

            <!-- Liabilities Section -->
            @php $calculatedLiabilities = 0; @endphp
            @if(is_array($liabilityGroups) && count($liabilityGroups) > 0)
                @foreach($liabilityGroups as $group)
                    <div class="subsection">
                        <div class="subsection-title">{{ $group['name'] ?? 'Liabilities' }}</div>
                        <table>
                            @foreach($group['accounts'] ?? [] as $account)
                                <tr>
                                    <td class="indent">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                    <td class="text-right" style="width: 100px;">₦{{ number_format($account['balance'] ?? 0, 2) }}</td>
                                </tr>
                                @php $calculatedLiabilities += ($account['balance'] ?? 0); @endphp
                            @endforeach
                            <tr class="subtotal-row">
                                <td>Total {{ $group['name'] ?? '' }}</td>
                                <td class="text-right">₦{{ number_format($group['total'] ?? 0, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @else
                <div class="subsection">
                    <p style="color: #999; text-align: center;">No liabilities recorded</p>
                </div>
            @endif

            <!-- Equity Section -->
            @php $calculatedEquity = 0; @endphp
            @if(is_array($equityGroups) && count($equityGroups) > 0)
                @foreach($equityGroups as $group)
                    <div class="subsection">
                        <div class="subsection-title">{{ $group['name'] ?? 'Equity' }}</div>
                        <table>
                            @foreach($group['accounts'] ?? [] as $account)
                                <tr>
                                    <td class="indent">{{ $account['code'] ?? '' }} - {{ $account['name'] ?? '' }}</td>
                                    <td class="text-right" style="width: 100px;">₦{{ number_format($account['balance'] ?? 0, 2) }}</td>
                                </tr>
                                @php $calculatedEquity += ($account['balance'] ?? 0); @endphp
                            @endforeach
                            <tr class="subtotal-row">
                                <td>Total {{ $group['name'] ?? '' }}</td>
                                <td class="text-right">₦{{ number_format($group['total'] ?? 0, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                @endforeach
            @else
                <div class="subsection">
                    <p style="color: #999; text-align: center;">No equity recorded</p>
                </div>
            @endif

            @php
                $finalLiabilities = $totalLiabilities ?: $calculatedLiabilities;
                $finalEquity = $totalEquity ?: $calculatedEquity;
                $totalLiabilitiesAndEquity = $finalLiabilities + $finalEquity;
            @endphp
            <div class="total-box">
                <table>
                    <tr>
                        <td><strong>TOTAL LIAB. & EQUITY</strong></td>
                        <td class="text-right" style="width: 100px;"><strong>₦{{ number_format($totalLiabilitiesAndEquity, 2) }}</strong></td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

@php
    $finalAssets = $totalAssets ?: $calculatedAssets;
    $difference = $finalAssets - $totalLiabilitiesAndEquity;
@endphp
<div class="balance-check {{ abs($difference) < 0.01 ? 'balanced' : 'unbalanced' }}">
    @if(abs($difference) < 0.01)
        <strong>✓ Balance Sheet is BALANCED</strong>
    @else
        <strong>✗ Balance Sheet is OUT OF BALANCE</strong><br>
        Difference: ₦{{ number_format(abs($difference), 2) }}
    @endif
</div>
@endsection
