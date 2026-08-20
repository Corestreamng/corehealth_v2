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
<div class="col-md-2">
    <select name="bank_id" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Banks</option>
        @php
            $activeBanks = \App\Models\Bank::active()->orderBy('name')->get();
        @endphp
        @foreach($activeBanks as $b)
            <option value="{{ $b->id }}">{{ $b->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <select name="entity" class="form-select form-select-sm ajax-entity-search ops-tab-filter" data-tab="{{ $tab }}" data-placeholder="Search Entity/Patient...">
        <option value="">All Entities</option>
    </select>
</div>
<div class="col-md-2">
    <select name="product_category_id" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Product Categories</option>
        @php
            $productCategories = \App\Models\ProductCategory::orderBy('category_name')->get();
        @endphp
        @foreach($productCategories as $pc)
            <option value="{{ $pc->id }}">{{ $pc->category_name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <select name="product_id" class="form-select form-select-sm ajax-product-search ops-tab-filter" data-tab="{{ $tab }}" data-placeholder="Search Product...">
        <option value="">All Products</option>
    </select>
</div>
<div class="col-md-2">
    <select name="service_category_id" class="form-select form-select-sm ops-tab-filter" data-tab="{{ $tab }}">
        <option value="">All Service Categories</option>
        @php
            $serviceCategories = \App\Models\ServiceCategory::orderBy('category_name')->get();
        @endphp
        @foreach($serviceCategories as $sc)
            <option value="{{ $sc->id }}">{{ $sc->category_name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-2">
    <select name="service_id" class="form-select form-select-sm ajax-service-search ops-tab-filter" data-tab="{{ $tab }}" data-placeholder="Search Service...">
        <option value="">All Services</option>
    </select>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.ajax-product-search').select2({
                placeholder: 'Search Product...',
                allowClear: true,
                ajax: {
                    url: '{{ route("live-search-products") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { term: params.term };
                    },
                    processResults: function(data) {
                        return {
                            results: $.map(data, function(item) {
                                return { text: item.product_name || item.name, id: item.id }
                            })
                        };
                    },
                    cache: true
                }
            });

            $('.ajax-service-search').select2({
                placeholder: 'Search Service...',
                allowClear: true,
                ajax: {
                    url: '{{ route("live-search-services") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { term: params.term };
                    },
                    processResults: function(data) {
                        return {
                            results: $.map(data, function(item) {
                                return { text: item.service_name || item.name, id: item.id }
                            })
                        };
                    },
                    cache: true
                }
            });
        }
    });
</script>
@endpush
