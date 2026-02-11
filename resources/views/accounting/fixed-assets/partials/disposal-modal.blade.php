{{--
    Enhanced Disposal Modal - Shared Component
    Shows disposal details, calculations, and journal entry preview
--}}
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="mdi mdi-delete mr-2"></i>Dispose Asset</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">&times;</button>
            </div>
            <form id="dispose-form">
                <input type="hidden" id="dispose-asset-id">
                <div class="modal-body">
                    <div class="row">
                        <!-- Left Column: Input Form -->
                        <div class="col-md-5">
                            <div class="alert alert-warning">
                                <i class="mdi mdi-alert-circle mr-2"></i>
                                Disposing <strong id="dispose-asset-name"></strong>
                            </div>

                            <!-- Asset Summary Card -->
                            <div class="card-modern mb-3" style="background: #f8f9fa;">
                                <div class="card-body p-3">
                                    <h6 class="mb-2"><i class="mdi mdi-information mr-1"></i>Asset Information</h6>
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Asset Number:</small>
                                        <small><strong id="dispose-asset-number">-</strong></small>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Total Cost:</small>
                                        <small><strong id="dispose-total-cost">₦0.00</strong></small>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Accumulated Depreciation:</small>
                                        <small class="text-success"><strong id="dispose-accum-depr">₦0.00</strong></small>
                                    </div>
                                    <div class="d-flex justify-content-between" style="border-top: 1px solid #dee2e6; padding-top: 5px; margin-top: 5px;">
                                        <small class="text-muted"><strong>Current Book Value:</strong></small>
                                        <small><strong id="dispose-book-value" class="text-primary">₦0.00</strong></small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Disposal Date <span class="text-danger">*</span></label>
                                <input type="date" id="dispose-date" class="form-control"
                                       value="{{ now()->format('Y-m-d') }}" required>
                            </div>

                            <div class="form-group">
                                <label>Disposal Type <span class="text-danger">*</span></label>
                                <select id="dispose-type" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="sale">Sale</option>
                                    <option value="scrapped">Scrapped</option>
                                    <option value="donated">Donated</option>
                                    <option value="trade_in">Trade In</option>
                                    <option value="theft_loss">Theft/Loss</option>
                                    <option value="insurance_claim">Insurance Claim</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Disposal Amount (Proceeds)</label>
                                <input type="number" id="dispose-amount" class="form-control"
                                       step="0.01" min="0" placeholder="0.00">
                                <small class="text-muted">Amount received from disposal (if any)</small>
                            </div>

                            <!-- Payment Source Section -->
                            <div id="payment-source-section" style="display: none;">
                                <div class="card-modern mb-3" style="background: #e7f3ff; border-left: 3px solid #007bff;">
                                    <div class="card-body p-3">
                                        <h6 class="mb-2"><i class="mdi mdi-bank-transfer mr-1"></i>Payment Destination</h6>
                                        <div class="form-group mb-2">
                                            <label class="small">Received Via <span class="text-danger">*</span></label>
                                            <select id="dispose-payment-method" class="form-control form-control-sm">
                                                <option value="">Select Payment Source</option>
                                                <option value="cash">Cash Account</option>
                                                <option value="bank_transfer">Bank Account</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-0" id="dispose-bank-row" style="display: none;">
                                            <label class="small">Bank Account <span class="text-danger">*</span></label>
                                            <select id="dispose-bank-id" class="form-control form-control-sm">
                                                <option value="">Select Bank Account</option>
                                                @foreach($banks as $bank)
                                                    <option value="{{ $bank->id }}">{{ $bank->name }} - {{ $bank->account_number }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Reason <span class="text-danger">*</span></label>
                                <textarea id="dispose-reason" class="form-control" rows="2" required
                                    placeholder="Explain why this asset is being disposed..."></textarea>
                            </div>

                            <div class="form-group" id="buyer-info-row" style="display: none;">
                                <label>Buyer Information</label>
                                <input type="text" id="dispose-buyer" class="form-control"
                                    placeholder="Buyer name, contact details, etc.">
                            </div>
                        </div>

                        <!-- Right Column: Calculations & Journal Entry Preview -->
                        <div class="col-md-7">
                            <h6 class="mb-3"><i class="mdi mdi-calculator mr-1"></i>Disposal Calculation</h6>

                            <!-- Calculation Breakdown -->
                            <div class="card-modern mb-3" style="background: #f8f9fa;">
                                <div class="card-body p-3">
                                    <table class="table table-sm table-borderless mb-0" style="font-size: 0.9rem;">
                                        <tr>
                                            <td class="text-muted">Disposal Proceeds:</td>
                                            <td class="text-right"><strong id="calc-proceeds">₦0.00</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Less: Book Value:</td>
                                            <td class="text-right">(<span id="calc-book-value">₦0.00</span>)</td>
                                        </tr>
                                        <tr style="border-top: 2px solid #dee2e6;">
                                            <td><strong id="calc-result-label">Gain/(Loss) on Disposal:</strong></td>
                                            <td class="text-right">
                                                <strong id="calc-result" class="text-success">₦0.00</strong>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Journal Entry Preview -->
                            <div id="je-preview-section">
                                <h6 class="mb-2"><i class="mdi mdi-book-open-variant mr-1"></i>Journal Entry Preview</h6>
                                <small class="text-muted d-block mb-2">The following journal entry will be automatically created:</small>

                                <div class="card-modern">
                                    <div class="card-body p-0">
                                        <table class="table table-sm mb-0" style="font-size: 0.85rem;">
                                            <thead style="background: #495057; color: white;">
                                                <tr>
                                                    <th style="width: 50%;">Account</th>
                                                    <th class="text-right" style="width: 25%;">Debit</th>
                                                    <th class="text-right" style="width: 25%;">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody id="je-preview-body">
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">
                                                        <i class="mdi mdi-information-outline"></i>
                                                        Enter disposal details to see journal entry
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot style="background: #f8f9fa; font-weight: 600;">
                                                <tr>
                                                    <td>TOTALS</td>
                                                    <td class="text-right" id="je-total-debit">₦0.00</td>
                                                    <td class="text-right" id="je-total-credit">₦0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <div class="alert alert-info mt-3 mb-0" style="font-size: 0.85rem;">
                                    <i class="mdi mdi-information mr-1"></i>
                                    <strong>What happens:</strong> The asset will be removed from the register,
                                    accumulated depreciation reversed, and gain/loss recorded.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="mdi mdi-close mr-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="mdi mdi-delete mr-1"></i>Dispose Asset & Record Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Disposal modal calculation and preview logic
(function() {
    let currentAssetData = null;

    // Store asset data when modal opens
    window.setDisposalAssetData = function(assetData) {
        currentAssetData = assetData;
        $('#dispose-asset-id').val(assetData.id);
        $('#dispose-asset-name').text(assetData.name);
        $('#dispose-asset-number').text(assetData.asset_number);
        $('#dispose-total-cost').text('₦' + parseFloat(assetData.total_cost).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#dispose-accum-depr').text('₦' + parseFloat(assetData.accumulated_depreciation).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#dispose-book-value').text('₦' + parseFloat(assetData.book_value).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        // Reset form
        $('#dispose-form')[0].reset();
        $('#dispose-date').val('{{ now()->format('Y-m-d') }}');
        updateDisposalCalculation();
    };

    // Update calculations when amount changes
    $(document).on('input change', '#dispose-amount, #dispose-type', updateDisposalCalculation);

    function updateDisposalCalculation() {
        if (!currentAssetData) return;

        const proceeds = parseFloat($('#dispose-amount').val()) || 0;
        const bookValue = parseFloat(currentAssetData.book_value);
        const totalCost = parseFloat(currentAssetData.total_cost);
        const accumDepr = parseFloat(currentAssetData.accumulated_depreciation);
        const gainLoss = proceeds - bookValue;

        // Update calculation display
        $('#calc-proceeds').text('₦' + proceeds.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-book-value').text('₦' + bookValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#calc-result').text('₦' + Math.abs(gainLoss).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        if (gainLoss >= 0) {
            $('#calc-result').removeClass('text-danger').addClass('text-success');
            $('#calc-result-label').text('Gain on Disposal:');
        } else {
            $('#calc-result').removeClass('text-success').addClass('text-danger');
            $('#calc-result-label').text('Loss on Disposal:');
        }

        // Show/hide payment source section
        if (proceeds > 0) {
            $('#payment-source-section').slideDown();
        } else {
            $('#payment-source-section').slideUp();
        }

        // Generate JE preview
        generateJEPreview(proceeds, bookValue, totalCost, accumDepr, gainLoss);
    }

    function generateJEPreview(proceeds, bookValue, totalCost, accumDepr, gainLoss) {
        const paymentMethod = $('#dispose-payment-method').val();
        const bankId = $('#dispose-bank-id').val();
        let html = '';
        let totalDebit = 0;
        let totalCredit = 0;

        // Get account info from asset data (passed from category relationships)
        const assetAccountName = currentAssetData.asset_account_name || 'Fixed Asset';
        const assetAccountCode = currentAssetData.asset_account_code || '1460';
        const deprAccountName = currentAssetData.depreciation_account_name || 'Accumulated Depreciation';
        const deprAccountCode = currentAssetData.depreciation_account_code || '1500';

        // 1. DEBIT: Cash/Bank (if proceeds > 0)
        if (proceeds > 0) {
            let accountName = 'Cash Account (1010)';
            if (paymentMethod === 'bank_transfer' && bankId) {
                const bankName = $('#dispose-bank-id option:selected').text().split(' - ')[0];
                accountName = bankName + ' - Bank Account';
            }
            html += `<tr>
                <td><strong>${accountName}</strong></td>
                <td class="text-right text-success"><strong>₦${proceeds.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                <td class="text-right">-</td>
            </tr>`;
            totalDebit += proceeds;
        }

        // 2. DEBIT: Accumulated Depreciation (use category's depreciation account)
        html += `<tr>
            <td><strong>${deprAccountName} (${deprAccountCode})</strong></td>
            <td class="text-right text-success"><strong>₦${accumDepr.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
            <td class="text-right">-</td>
        </tr>`;
        totalDebit += accumDepr;

        // 3. DEBIT: Loss on Disposal (if loss)
        if (gainLoss < 0) {
            html += `<tr>
                <td><strong>Loss on Disposal (6900)</strong></td>
                <td class="text-right text-success"><strong>₦${Math.abs(gainLoss).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                <td class="text-right">-</td>
            </tr>`;
            totalDebit += Math.abs(gainLoss);
        }

        // 4. CREDIT: Fixed Asset at cost (use category's asset account)
        html += `<tr>
            <td><strong>${assetAccountName} (${assetAccountCode})</strong></td>
            <td class="text-right">-</td>
            <td class="text-right text-danger"><strong>₦${totalCost.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
        </tr>`;
        totalCredit += totalCost;

        // 5. CREDIT: Gain on Disposal (if gain)
        if (gainLoss > 0) {
            html += `<tr>
                <td><strong>Gain on Disposal (4200)</strong></td>
                <td class="text-right">-</td>
                <td class="text-right text-danger"><strong>₦${gainLoss.toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
            </tr>`;
            totalCredit += gainLoss;
        }

        $('#je-preview-body').html(html);
        $('#je-total-debit').text('₦' + totalDebit.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#je-total-credit').text('₦' + totalCredit.toLocaleString('en-US', {minimumFractionDigits: 2}));
    }

    // Update preview when payment method changes
    $(document).on('change', '#dispose-payment-method, #dispose-bank-id', updateDisposalCalculation);

    // Show/hide bank selection
    $(document).on('change', '#dispose-payment-method', function() {
        if ($(this).val() === 'bank_transfer') {
            $('#dispose-bank-row').slideDown();
        } else {
            $('#dispose-bank-row').slideUp();
            $('#dispose-bank-id').val('');
        }
    });

    // Show/hide buyer info based on disposal type
    $(document).on('change', '#dispose-type', function() {
        if ($(this).val() === 'sale') {
            $('#buyer-info-row').slideDown();
        } else {
            $('#buyer-info-row').slideUp();
            $('#dispose-buyer').val('');
        }
    });
})();
</script>
@endpush
