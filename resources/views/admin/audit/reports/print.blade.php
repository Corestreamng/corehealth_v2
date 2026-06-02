@extends('accounting.reports.pdf.layout')

@section('title', $categoryLabel . ' - ' . $reportLabel)
@section('report_title', $categoryLabel . ' Audit')
@section('report_subtitle', $reportLabel . ' (' . $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y') . ')')

@section('styles')
<style>
    /* Custom styles for Audit Print layout */
    .kpi-container {
        display: table;
        width: 100%;
        margin-bottom: 20px;
        border-collapse: separate;
        border-spacing: 10px;
    }
    .kpi-card {
        display: table-cell;
        width: 25%;
        border: 1px solid #ddd;
        background-color: #f9f9f9;
        padding: 15px;
        text-align: center;
        vertical-align: middle;
        border-radius: 4px;
    }
    .kpi-label {
        font-size: 10px;
        color: #666;
        text-transform: uppercase;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .kpi-value {
        font-size: 18px;
        font-weight: bold;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin-top: 30px;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 2px solid #ccc;
    }
    .audit-table {
        font-size: 9px;
        width: 100%;
    }
    .audit-table th {
        background-color: #f5f5f5;
        font-size: 9px;
    }
    .audit-table td {
        word-wrap: break-word;
        max-width: 200px;
    }
    /* Prevent rows from breaking across pages if possible */
    tr {
        page-break-inside: avoid;
    }
    /* Force page break before new sections if needed, but we'll leave it flowing normally for now */
    .page-break-before {
        page-break-before: always;
    }
</style>
@endsection

@section('content')

    <!-- KPIs -->
    @if(isset($kpis) && count($kpis) > 0)
        <div class="kpi-container">
            @foreach(array_chunk($kpis, 4) as $kpiRow)
                <div style="display: table-row;">
                    @foreach($kpiRow as $kpi)
                        <div class="kpi-card">
                            <div class="kpi-label">{{ $kpi['label'] }}</div>
                            @php
                                $valClass = '';
                                if(strpos($kpi['class'], 'text-success') !== false) $valClass = 'color: #28a745;';
                                if(strpos($kpi['class'], 'text-danger') !== false) $valClass = 'color: #dc3545;';
                                if(strpos($kpi['class'], 'text-info') !== false) $valClass = 'color: #17a2b8;';
                                if(strpos($kpi['class'], 'text-primary') !== false) $valClass = 'color: #007bff;';
                            @endphp
                            <div class="kpi-value" style="{{ $valClass }}">{!! $kpi['value'] !!}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <!-- Data Tables -->
    @if(isset($tabbedData) && count($tabbedData) > 0)
        @foreach($tabbedData as $tabId => $tabInfo)
            @if(in_array($tabId, $selectedTabs))
                <div class="section-title">
                    {{ $tabInfo['label'] ?? ucfirst(str_replace('_', ' ', $tabId)) }}
                </div>
                
                @if(isset($tabInfo['rows']) && count($tabInfo['rows']) > 0)
                    <table class="audit-table">
                        <thead>
                            <tr>
                                @foreach($tabInfo['headers'] as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tabInfo['rows'] as $row)
                                <tr>
                                    @if(is_array($row))
                                        @foreach($row as $cell)
                                            {{-- Strip HTML tags for clean printing, except for basic formatting if needed, but mostly we want text --}}
                                            <td>{!! strip_tags($cell, '<span><br><strong><b><i>') !!}</td>
                                        @endforeach
                                    @else
                                        {{-- Fallback if row isn't an array --}}
                                        <td>{!! $row !!}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div style="padding: 15px; text-align: center; color: #777; font-style: italic; border: 1px dashed #ccc;">
                        No data available for this section in the selected period.
                    </div>
                @endif
                
                @if(!$loop->last)
                    <div class="page-break-before"></div>
                @endif
            @endif
        @endforeach
    @else
        {{-- Fallback for Single Table --}}
        @if(isset($rows) && count($rows) > 0)
            <div class="section-title">Report Data</div>
            <table class="audit-table">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @if(is_array($row))
                                @foreach($row as $cell)
                                    <td>{!! strip_tags($cell, '<span><br><strong><b><i>') !!}</td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="padding: 20px; text-align: center; color: #777; font-style: italic; border: 1px dashed #ccc;">
                No data available for the selected period.
            </div>
        @endif
    @endif

@endsection
