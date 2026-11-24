@extends('accounting.reports.pdf.layout')

@section('title', 'Bank Reconciliation ' . $reconciliation->reconciliation_number)
@section('report_title', 'Bank Reconciliation')
@section('report_subtitle', $reconciliation->reconciliation_number . ' · ' . ($reconciliation->bank->name ?? '') . ' · ' . $reconciliation->statement_period_from->format('M d, Y') . ' to ' . $reconciliation->statement_period_to->format('M d, Y'))

@section('content')
@php
    $money = fn ($v) => '₦' . number_format((float) $v, 2);
    $statement = $reconciliation->items->where('source', 'statement');
    $gl = $reconciliation->items->where('source', 'gl');
@endphp
<table class="table" style="width:100%">
    <tr><td>Status</td><td>{{ ucwords(str_replace('_', ' ', $reconciliation->status)) }}</td><td>Statement date</td><td>{{ $reconciliation->statement_date->format('M d, Y') }}</td></tr>
    <tr><td>Statement opening balance</td><td>{{ $money($reconciliation->statement_opening_balance) }}</td><td>Statement closing balance</td><td>{{ $money($reconciliation->statement_closing_balance) }}</td></tr>
    <tr><td>Ledger closing balance</td><td>{{ $money($reconciliation->gl_closing_balance) }}</td><td>Variance</td><td>{{ $money($reconciliation->variance) }}</td></tr>
    <tr><td>Prepared by</td><td>{{ $reconciliation->preparedBy->name ?? '-' }}</td><td>Approved by</td><td>{{ $reconciliation->approvedBy->name ?? '-' }}</td></tr>
</table>

@foreach (['Statement lines' => $statement, 'Ledger lines' => $gl] as $title => $rows)
    <h4 style="margin-top:14px">{{ $title }} ({{ $rows->count() }})</h4>
    <table class="table" style="width:100%">
        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th style="text-align:right">Amount</th><th>Matched</th></tr></thead>
        <tbody>
        @forelse ($rows as $item)
            <tr>
                <td>{{ optional($item->transaction_date)->format('M d, Y') }}</td>
                <td>{{ $item->reference }}</td>
                <td>{{ $item->description }}</td>
                <td style="text-align:right">{{ $money($item->amount) }} {{ $item->amount_type }}</td>
                <td>{{ $item->is_matched ? 'Yes' : ($item->is_outstanding ? 'Outstanding' : 'No') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">None.</td></tr>
        @endforelse
        </tbody>
    </table>
@endforeach
@endsection
