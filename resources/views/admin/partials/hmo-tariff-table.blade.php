<table class="table table-hover table-refined mb-0">
    <thead class="bg-light">
        <tr>
            <th class="pl-4">HMO Entity</th>
            <th class="text-right" style="width: 140px;">Patient Pays</th>
            <th class="text-right" style="width: 140px;">HMO Claims</th>
            <th class="text-center" style="width: 150px;">
                Mode 
                <i class="mdi mdi-information-outline small text-muted cursor-help" data-bs-toggle="tooltip" title="Approval workflow for this service"></i>
            </th>
            <th class="text-right pr-4" style="width: 60px;"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $hmo)
        <tr class="hmo-row-refined transition-all" 
            data-hmo-id="{{ $hmo['id'] }}" 
            data-name="{{ strtolower($hmo['name']) }}"
            data-scheme="{{ strtolower($schemeName ?? '') }}"
            data-orig-payable="{{ $hmo['payable_amount'] }}" 
            data-orig-claims="{{ $hmo['claims_amount'] }}"
            data-orig-coverage="{{ $hmo['coverage_mode'] }}">
            <td class="pl-4">
                <div class="font-weight-medium text-gray-800">{{ $hmo['name'] }}</div>
                <div class="divergence-indicator small mt-1"></div>
            </td>
            <td class="text-right">
                <div class="input-wrapper-refined">
                    <span class="currency-symbol">&#8358;</span>
                    <input type="number" step="0.01" class="payable-input-refined" value="{{ number_format($hmo['payable_amount'], 2, '.', '') }}">
                </div>
            </td>
            <td class="text-right">
                <div class="input-wrapper-refined">
                    <span class="currency-symbol">&#8358;</span>
                    <input type="number" step="0.01" class="claims-input-refined" value="{{ number_format($hmo['claims_amount'], 2, '.', '') }}">
                </div>
            </td>
            <td class="text-center">
                <div class="coverage-toggle-container">
                    <span class="coverage-dot {{ $hmo['coverage_mode'] }}" title="{{ ucfirst($hmo['coverage_mode']) }}"></span>
                    <select class="coverage-select-refined">
                        <option value="express" {{ $hmo['coverage_mode'] === 'express' ? 'selected' : '' }}>Express (Auto)</option>
                        <option value="primary" {{ $hmo['coverage_mode'] === 'primary' ? 'selected' : '' }}>Primary (HMO Appr.)</option>
                        <option value="secondary" {{ $hmo['coverage_mode'] === 'secondary' ? 'selected' : '' }}>Secondary (Auth Code)</option>
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
