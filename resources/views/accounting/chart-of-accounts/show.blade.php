@extends('admin.layouts.app')

@section('title', 'Account Details')
@section('page_name', 'Accounting')
@section('subpage_name', 'Account Details')

@section('content')
@include('accounting.partials.breadcrumb', ['items' => [
    ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts.index'), 'icon' => 'mdi-file-tree'],
    ['label' => 'Account Details', 'url' => '#', 'icon' => 'mdi-information']
]])

<div class="container-fluid">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">{{ $account->full_code }} - {{ $account->name }}</h4>
            <p class="text-muted mb-0">{{ $account->accountGroup->name ?? '' }} | {{ $account->accountGroup->accountClass->name ?? '' }}</p>
        </div>
        <div>
            <a href="{{ route('accounting.chart-of-accounts.edit', $account->id) }}" class="btn btn-primary mr-2">
                <i class="mdi mdi-pencil mr-1"></i> Edit
            </a>
            <a href="{{ route('accounting.chart-of-accounts.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Account Information --}}
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>Account Code:</th>
                            <td>{{ $account->full_code }}</td>
                        </tr>
                        <tr>
                            <th>Account Name:</th>
                            <td>{{ $account->name }}</td>
                        </tr>
                        <tr>
                            <th>Account Class:</th>
                            <td>{{ $account->accountGroup->accountClass->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Account Group:</th>
                            <td>{{ $account->accountGroup->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Normal Balance:</th>
                            <td>
                                <span class="badge badge-{{ $account->normal_balance == 'debit' ? 'success' : 'info' }}">
                                    {{ ucfirst($account->normal_balance) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge badge-{{ $account->is_active ? 'success' : 'secondary' }}">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                        @if($account->description)
                        <tr>
                            <th>Description:</th>
                            <td>{{ $account->description }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            {{-- Balance Summary --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Balance Summary</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Current Balance</label>
                        <h3 class="mb-0 text-{{ $balance >= 0 ? 'success' : 'danger' }}">
                            ₦ {{ number_format(abs($balance), 2) }}
                        </h3>
                        <small class="text-muted">As of {{ now()->format('M d, Y') }}</small>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="text-muted small">This Month</label>
                        <h4 class="mb-0 text-{{ $periodBalance >= 0 ? 'success' : 'danger' }}">
                            ₦ {{ number_format(abs($periodBalance), 2) }}
                        </h4>
                        <small class="text-muted">{{ now()->format('F Y') }}</small>
                    </div>
                </div>
            </div>

            {{-- Sub Accounts --}}
            @if($account->subAccounts->count() > 0)
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sub Accounts</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        @foreach($account->subAccounts as $subAccount)
                        <li class="list-group-item px-0">
                            <a href="{{ route('accounting.chart-of-accounts.show', $subAccount->id) }}">
                                {{ $subAccount->full_code }} - {{ $subAccount->name }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>

        {{-- Recent Transactions --}}
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                    <div>
                        <a href="{{ route('accounting.reports.general-ledger', ['account_id' => $account->id]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-book-multiple mr-1"></i> View Full Ledger
                        </a>
                        @if(in_array($account->accountGroup->code ?? '', ['1010', '1020', '1030']))
                        <a href="{{ route('accounting.reports.bank-statement', ['account_id' => $account->id]) }}" class="btn btn-sm btn-outline-info ml-2">
                            <i class="mdi mdi-bank mr-1"></i> Bank Statement
                        </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($account->journalLines->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Entry #</th>
                                    <th>Description</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $runningBalance = 0; @endphp
                                @foreach($account->journalLines as $line)
                                    @if($line->journalEntry && $line->journalEntry->status == 'posted')
                                    @php
                                        if ($account->isDebitBalance()) {
                                            $runningBalance += $line->debit - $line->credit;
                                        } else {
                                            $runningBalance += $line->credit - $line->debit;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $line->journalEntry->entry_date->format('M d, Y') }}</td>
                                        <td>
                                            <a href="{{ route('accounting.journal-entries.show', $line->journalEntry->id) }}">
                                                {{ $line->journalEntry->entry_number }}
                                            </a>
                                        </td>
                                        <td>{{ Str::limit($line->description ?: $line->journalEntry->description, 50) }}</td>
                                        <td class="text-end">
                                            {{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}
                                        </td>
                                        <td class="text-end">
                                            {{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2">
                        <i class="mdi mdi-information-outline mr-1"></i>
                        Showing last 50 posted transactions. <a href="{{ route('accounting.reports.general-ledger', ['account_id' => $account->id]) }}">View all</a>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="mdi mdi-file-document-outline mdi-48px text-muted mb-3"></i>
                        <p class="text-muted">No transactions found for this account.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
