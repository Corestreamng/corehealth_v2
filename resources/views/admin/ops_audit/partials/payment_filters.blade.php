<div class="col-md-2">
    <select name="payment_method" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Payment Methods</option>
        <option value="ACCOUNT">Account</option>
        <option value="BILL_TO_ORG">Bill to Org</option>
        <option value="BILL_TO_STAFF">Bill to Staff</option>
        <option value="CASH">Cash</option>
        <option value="HMO_FULL_COVER">HMO Full Cover</option>
        <option value="MOBILE">Mobile</option>
        <option value="POS">POS</option>
        <option value="REFUND">Refund</option>
        <option value="TRANSFER">Transfer</option>
    </select>
</div>
<div class="col-md-2">
    <select name="cashier_id" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Cashiers</option>
        @php
            $cashiers = \App\Models\User::role(['ACCOUNTS', 'BILLER', 'ADMIN', 'SUPERADMIN'])->orderBy('firstname')->get();
        @endphp
        @foreach($cashiers as $cashier)
            <option value="{{ $cashier->id }}">{{ $cashier->firstname }} {{ $cashier->surname }}</option>
        @endforeach
    </select>
</div>
