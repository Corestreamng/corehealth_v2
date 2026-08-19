<div class="col-md-2">
    <select name="payment_method" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Payment Methods</option>
        <option value="CASH">CASH</option>
        <option value="POS">POS</option>
        <option value="TRANSFER">TRANSFER</option>
        <option value="HMO">HMO</option>
        <option value="WALLET">WALLET</option>
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
