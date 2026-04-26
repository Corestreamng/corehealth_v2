<table class="table table-hover table-refined mb-0">
    <thead class="bg-light text-sm">
        <tr>
            <th class="pl-4">Item Name</th>
            <th class="text-right" style="width: 130px;">Base Price</th>
            <th class="text-right" style="width: 140px;">Patient Pays</th>
            <th class="text-right" style="width: 140px;">HMO Claims</th>
            <th class="text-center" style="width: 150px;">Mode <i class="mdi mdi-information-outline small text-muted cursor-help" data-bs-toggle="tooltip" title="Approval workflow"></i></th>
            <th class="text-right pr-4" style="width: 60px;"></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $item)
        @php
            $isProd = $type === 'product';
            $id = $item->id;
            $tariff = $isProd ? ($productTariffs->get($id)) : ($serviceTariffs->get($id));
            $basePrice = (float)$item->price;
            $payable = $tariff ? (float)$tariff->payable_amount : $basePrice;
            $claims = $tariff ? (float)$tariff->claims_amount : 0;
            $mode = $tariff ? $tariff->coverage_mode : 'primary';
        @endphp
        <tr class="hmo-row-refined transition-all" 
            data-type="{{ $type }}" 
            data-id="{{ $id }}" 
            data-name="{{ strtolower($item->name) }}"
            data-base-price="{{ $basePrice }}"
            data-orig-payable="{{ $payable }}" 
            data-orig-claims="{{ $claims }}"
            data-orig-coverage="{{ $mode }}">
            <td class="pl-4">
                <div class="font-weight-medium text-gray-800">{{ $item->name }}</div>
                <div class="divergence-indicator small mt-1"></div>
            </td>
            <td class="text-right text-muted font-weight-bold small">
                &#8358;{{ number_format($basePrice, 2) }}
            </td>
            <td class="text-right">
                <div class="input-wrapper-refined">
                    <span class="currency-symbol">&#8358;</span>
                    <input type="number" step="0.01" class="payable-input-refined" value="{{ number_format($payable, 2, '.', '') }}">
                </div>
            </td>
            <td class="text-right">
                <div class="input-wrapper-refined">
                    <span class="currency-symbol">&#8358;</span>
                    <input type="number" step="0.01" class="claims-input-refined" value="{{ number_format($claims, 2, '.', '') }}">
                </div>
            </td>
            <td class="text-center">
                <div class="coverage-toggle-container">
                    <span class="coverage-dot {{ $mode }}" title="{{ ucfirst($mode) }}"></span>
                    <select class="coverage-select-refined">
                        <option value="express" {{ $mode === 'express' ? 'selected' : '' }}>Express (Auto)</option>
                        <option value="primary" {{ $mode === 'primary' ? 'selected' : '' }}>Primary (HMO Appr.)</option>
                        <option value="secondary" {{ $mode === 'secondary' ? 'selected' : '' }}>Secondary (Auth Code)</option>
                    </select>
                </div>
            </td>
            <td class="text-right pr-4">
                <button type="button" class="btn-save-inline d-none" title="Save Row"><i class="mdi mdi-check-circle"></i></button>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
