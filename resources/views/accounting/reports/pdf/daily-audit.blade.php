@extends('accounting.reports.pdf.layout')

@section('title', 'Daily Audit Report')
@section('report_title', 'Daily Audit Report')
@section('report_subtitle', $date->format('l, F d, Y'))

@section('styles')
<style>
    .stats-grid {
        display: table;
        width: 100%;
        margin-bottom: 25px;
    }
    .stats-row {
        display: table-row;
    }
    .stat-box {
        display: table-cell;
        width: 16%;
        padding: 10px;
        text-align: center;
        border: 1px solid #ddd;
        background-color: #f8f9fa;
    }
    .stat-box .value {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
    .stat-box .label {
        font-size: 10px;
        color: #666;
        margin-top: 5px;
    }
    .stat-box.highlight {
        background-color: #e3f2fd;
        border-color: #2196f3;
    }
    .section-title {
        font-size: 14px;
        font-weight: bold;
        border-bottom: 2px solid #333;
        padding-bottom: 5px;
        margin: 20px 0 10px 0;
    }
    .entry-card {
        border: 1px solid #ddd;
        margin-bottom: 15px;
        page-break-inside: avoid;
    }
    .entry-header {
        background-color: #f5f5f5;
        padding: 8px;
        border-bottom: 1px solid #ddd;
    }
    .entry-header .entry-number {
        font-weight: bold;
        font-size: 12px;
    }
    .entry-header .entry-meta {
        font-size: 10px;
        color: #666;
    }
    .entry-body {
        padding: 8px;
    }
    .entry-body table {
        font-size: 10px;
    }
    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 9px;
        font-weight: bold;
    }
    .status-posted { background-color: #d4edda; color: #155724; }
    .status-draft { background-color: #fff3cd; color: #856404; }
    .status-pending { background-color: #cce5ff; color: #004085; }
    .status-approved { background-color: #d1ecf1; color: #0c5460; }
    .type-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 9px;
        background-color: #6c757d;
        color: white;
    }
</style>
@endsection

@section('content')
<!-- Summary Statistics -->
<div class="stats-grid">
    <div class="stats-row">
        <div class="stat-box highlight">
            <div class="value">{{ $stats['total_entries'] ?? 0 }}</div>
            <div class="label">Total Entries</div>
        </div>
        <div class="stat-box">
            <div class="value" style="color: #28a745;">{{ $stats['posted_entries'] ?? 0 }}</div>
            <div class="label">Posted</div>
        </div>
        <div class="stat-box">
            <div class="value" style="color: #ffc107;">{{ $stats['pending_entries'] ?? 0 }}</div>
            <div class="label">Pending/Draft</div>
        </div>
        <div class="stat-box">
            <div class="value">₦{{ number_format($stats['total_debits'] ?? 0, 0) }}</div>
            <div class="label">Total Debits</div>
        </div>
        <div class="stat-box">
            <div class="value">₦{{ number_format($stats['total_credits'] ?? 0, 0) }}</div>
            <div class="label">Total Credits</div>
        </div>
    </div>
</div>

<!-- Entries by Type -->
@if(!empty($stats['by_type']) && count($stats['by_type']) > 0)
<div style="margin-bottom: 20px;">
    <strong>Entries by Type:</strong>
    @foreach($stats['by_type'] as $type => $count)
        <span class="type-badge">{{ ucfirst($type) }}: {{ $count }}</span>
    @endforeach
</div>
@endif

<!-- Journal Entries -->
<div class="section-title">JOURNAL ENTRIES</div>

@forelse($entries as $entry)
    <div class="entry-card">
        <div class="entry-header">
            <div style="display: inline-block; width: 60%;">
                <span class="entry-number">{{ $entry->entry_number }}</span>
                <span class="status-badge status-{{ strtolower($entry->status) }}">{{ strtoupper($entry->status) }}</span>
                @if($entry->entry_type)
                    <span class="type-badge">{{ ucfirst($entry->entry_type) }}</span>
                @endif
            </div>
            <div style="display: inline-block; width: 38%; text-align: right;">
                <span class="entry-meta">
                    {{ \Carbon\Carbon::parse($entry->entry_date)->format('h:i A') }} |
                    Created by: {{ $entry->createdBy->name ?? 'System' }}
                </span>
            </div>
        </div>
        <div class="entry-body">
            <div style="margin-bottom: 8px; color: #666; font-size: 11px;">
                <strong>Description:</strong> {{ $entry->description ?? 'No description' }}
                @if($entry->reference)
                    | <strong>Ref:</strong> {{ $entry->reference }}
                @endif
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Account Code</th>
                        <th style="width: 45%;">Account Name</th>
                        <th style="width: 20%;" class="text-right">Debit</th>
                        <th style="width: 20%;" class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entry->lines as $line)
                        <tr>
                            <td>{{ $line->account->account_code ?? $line->account->code ?? '-' }}</td>
                            <td>{{ $line->account->account_name ?? $line->account->name ?? '-' }}</td>
                            <td class="text-right">{{ $line->debit > 0 ? '₦' . number_format($line->debit, 2) : '-' }}</td>
                            <td class="text-right">{{ $line->credit > 0 ? '₦' . number_format($line->credit, 2) : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2"><strong>Entry Total</strong></td>
                        <td class="text-right"><strong>₦{{ number_format($entry->lines->sum('debit'), 2) }}</strong></td>
                        <td class="text-right"><strong>₦{{ number_format($entry->lines->sum('credit'), 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@empty
    <div style="text-align: center; padding: 40px; color: #999; background-color: #f8f9fa; border: 1px dashed #ddd;">
        <strong>No journal entries recorded for this date</strong>
    </div>
@endforelse

<!-- Footer Summary -->
@if($entries->count() > 0)
<div style="margin-top: 25px; padding: 15px; background-color: #333; color: white;">
    <table style="color: white;">
        <tr>
            <td style="width: 50%;"><strong>Total Entries for {{ $date->format('F d, Y') }}:</strong></td>
            <td class="text-right"><strong>{{ $entries->count() }}</strong></td>
        </tr>
        <tr>
            <td><strong>Total Debits (Posted):</strong></td>
            <td class="text-right"><strong>₦{{ number_format($stats['total_debits'] ?? 0, 2) }}</strong></td>
        </tr>
        <tr>
            <td><strong>Total Credits (Posted):</strong></td>
            <td class="text-right"><strong>₦{{ number_format($stats['total_credits'] ?? 0, 2) }}</strong></td>
        </tr>
    </table>
</div>
@endif
@endsection
