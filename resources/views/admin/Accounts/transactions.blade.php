@extends('admin.layouts.app')
@section('title', 'All Transactions')
@section('page_name', 'Transactions')
@section('subpage_name', 'All Payments & Discounts')
@section('content')
    <div class="container">
        <div class="card mt-4 mb-3">
            <div class="card-body">
                <form method="get" class="form-inline">
                    <label class="mr-2">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control mr-2">
                    <label class="mr-2">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control mr-2">
                    <label class="mr-2">Payment Type</label>
                    <select name="payment_type" class="form-control mr-2">
                        <option value="">All</option>
                        <option value="POS" @if ($payment_type == 'POS') selected @endif>POS</option>
                        <option value="CASH" @if ($payment_type == 'CASH') selected @endif>Cash</option>
                        <option value="TRANSFER" @if ($payment_type == 'TRANSFER') selected @endif>Transfer</option>
                        <option value="TELLER" @if ($payment_type == 'TELLER') selected @endif>Teller</option>
                        <option value="CHEQUE" @if ($payment_type == 'CHEQUE') selected @endif>Cheque</option>
                        <option value="ACC_WITHDRAW" @if ($payment_type == 'ACC_WITHDRAW') selected @endif>Credit Account
                        </option>
                        <option value="CLAIMS" @if ($payment_type == 'CLAIMS') selected @endif>Claims</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Amount</h5>
                        <p class="card-text">&#8358;{{ number_format($total_amount, 2) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Discount</h5>
                        <p class="card-text">&#8358;{{ number_format($total_discount, 2) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-secondary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Transactions</h5>
                        <p class="card-text">{{ $total_count }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">By Payment Type</h5>
                        <ul class="mb-0">
                            @foreach ($by_type as $type => $stat)
                                <li>
                                    <strong>{{ $type }}</strong>: {{ $stat['count'] }}
                                    (&#8358;{{ number_format($stat['amount'], 2) }}, Discount:
                                    &#8358;{{ number_format($stat['discount'], 2) }})
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Transactions</div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Payment Type</th>
                            <th>Amount</th>
                            <th>Discount</th>
                            <th>Reference</th>
                            <th>HMO</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transactions as $t)
                            <tr>
                                <td>{{ $t->id }}</td>
                                <td>{{ $t->created_at }}</td>
                                <td>{{ $t->patient ? userfullname($t->patient_id) : '' }}</td>
                                <td>{{ $t->payment_type }}</td>
                                <td>&#8358;{{ number_format($t->total, 2) }}</td>
                                <td>&#8358;{{ number_format($t->discount, 2) }}</td>
                                <td>{{ $t->reference_no }}</td>
                                <td>
                                    @if ($t->hmo_id)
                                        {{ optional($t->patient && $t->patient->hmo ? $t->patient->hmo : null)->name ?? 'N/A' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $t->user ? userfullname($t->user_id) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
