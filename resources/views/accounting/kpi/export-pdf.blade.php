@extends('accounting.reports.pdf.layout')

@section('title', 'KPI Report')
@section('report_title', 'Financial KPI Report')
@section('report_subtitle', 'As of ' . now()->format('F d, Y'))

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
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }
    .summary-cell.primary { background: #007bff; color: white; }
    .summary-cell.success { background: #28a745; color: white; }
    .summary-cell.info { background: #17a2b8; color: white; }
    .summary-cell.warning { background: #ffc107; color: #212529; }
    .summary-label { font-size: 9px; text-transform: uppercase; }
    .summary-value { font-size: 14px; font-weight: bold; }

    .category-header {
        background: #495057;
        color: white;
        padding: 8px 12px;
        margin: 15px 0 10px 0;
        font-size: 12px;
        font-weight: bold;
    }

    .status-badge {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    .status-good { background: #d4edda; color: #155724; }
    .status-warning { background: #fff3cd; color: #856404; }
    .status-critical { background: #f8d7da; color: #721c24; }
    .status-default { background: #e2e3e5; color: #383d41; }

    .trend-up { color: #28a745; }
    .trend-down { color: #dc3545; }
    .trend-flat { color: #6c757d; }
</style>
@endsection

@section('content')
    @php
        $totalKpis = isset($kpis) ? (is_array($kpis) ? count($kpis) : $kpis->count()) : 0;
        $goodKpis = isset($kpis) ? collect($kpis)->where('status', 'good')->count() : 0;
        $warningKpis = isset($kpis) ? collect($kpis)->where('status', 'warning')->count() : 0;
        $criticalKpis = isset($kpis) ? collect($kpis)->where('status', 'critical')->count() : 0;
    @endphp

    <div class="summary-grid">
        <div class="summary-cell primary">
            <div class="summary-label">Total KPIs</div>
            <div class="summary-value">{{ $totalKpis }}</div>
        </div>
        <div class="summary-cell success">
            <div class="summary-label">On Target</div>
            <div class="summary-value">{{ $goodKpis }}</div>
        </div>
        <div class="summary-cell warning">
            <div class="summary-label">Warning</div>
            <div class="summary-value">{{ $warningKpis }}</div>
        </div>
        <div class="summary-cell info">
            <div class="summary-label">Critical</div>
            <div class="summary-value">{{ $criticalKpis }}</div>
        </div>
    </div>

    @if(isset($kpis) && count($kpis) > 0)
        @php
            $groupedKpis = collect($kpis)->groupBy('category');
        @endphp

        @foreach($groupedKpis as $category => $categoryKpis)
            <div class="category-header">{{ ucfirst($category ?: 'General') }}</div>

            <table>
                <thead>
                    <tr>
                        <th>KPI Name</th>
                        <th>Description</th>
                        <th class="text-right">Current Value</th>
                        <th class="text-right">Target</th>
                        <th class="text-right">Variance</th>
                        <th class="text-center">Trend</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categoryKpis as $kpi)
                    <tr>
                        <td>{{ $kpi->name ?? $kpi['name'] ?? '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($kpi->description ?? $kpi['description'] ?? '-', 50) }}</td>
                        <td class="text-right">
                            @php
                                $value = $kpi->current_value ?? $kpi['current_value'] ?? 0;
                                $format = $kpi->format ?? $kpi['format'] ?? 'number';
                            @endphp
                            @if($format === 'currency')
                                ₦{{ number_format($value, 2) }}
                            @elseif($format === 'percentage')
                                {{ number_format($value, 2) }}%
                            @else
                                {{ number_format($value, 2) }}
                            @endif
                        </td>
                        <td class="text-right">
                            @php
                                $target = $kpi->target_value ?? $kpi['target_value'] ?? 0;
                            @endphp
                            @if($format === 'currency')
                                ₦{{ number_format($target, 2) }}
                            @elseif($format === 'percentage')
                                {{ number_format($target, 2) }}%
                            @else
                                {{ number_format($target, 2) }}
                            @endif
                        </td>
                        <td class="text-right">
                            @php
                                $variance = $value - $target;
                                $varianceClass = $variance >= 0 ? 'trend-up' : 'trend-down';
                            @endphp
                            <span class="{{ $varianceClass }}">
                                @if($format === 'currency')
                                    ₦{{ number_format(abs($variance), 2) }}
                                @elseif($format === 'percentage')
                                    {{ number_format(abs($variance), 2) }}%
                                @else
                                    {{ number_format(abs($variance), 2) }}
                                @endif
                            </span>
                        </td>
                        <td class="text-center">
                            @php
                                $trend = $kpi->trend ?? $kpi['trend'] ?? 'flat';
                                $trendIcon = match($trend) {
                                    'up' => '↑',
                                    'down' => '↓',
                                    default => '→'
                                };
                                $trendClass = match($trend) {
                                    'up' => 'trend-up',
                                    'down' => 'trend-down',
                                    default => 'trend-flat'
                                };
                            @endphp
                            <span class="{{ $trendClass }}">{{ $trendIcon }}</span>
                        </td>
                        <td class="text-center">
                            @php
                                $status = $kpi->status ?? $kpi['status'] ?? 'default';
                                $statusClass = match($status) {
                                    'good', 'on_target' => 'status-good',
                                    'warning' => 'status-warning',
                                    'critical' => 'status-critical',
                                    default => 'status-default'
                                };
                            @endphp
                            <span class="status-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @else
        <p class="text-center">No KPIs found</p>
    @endif
@endsection
