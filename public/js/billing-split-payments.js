/**
 * CoreHealth v2 - Billing Split Payments Module
 * Handles multi-tender / split payment workflows in checkout modal
 */
(function(window, $) {
    'use strict';

    window.CoreHealth = window.CoreHealth || {};

    const SplitPayments = {
        init: function() {
            this.bindEvents();
            this.loadBanks();
        },

        bindEvents: function() {
            const self = this;

            // Monitor payment modal show to ensure banks are ready
            $(document).on('show.bs.modal shown.bs.modal', '#paymentModal', function() {
                self.loadBanks();
            });

            // Monitor payment method dropdown
            $(document).on('change', '#payment-method', function() {
                const method = $(this).val();
                if (method === 'SPLIT') {
                    $('#split-payment-section').slideDown(200);
                    $('#bank-selection-section').hide();
                    $('#staff-selection-section').hide();
                    $('#org-selection-section').hide();
                    $('#account-payment-note').hide();
                    $('#payment-reference').closest('.payment-reference-section').hide();

                    self.loadBanks(function() {
                        self.ensureInitialLegs();
                    });
                } else {
                    $('#split-payment-section').slideUp(150);
                    $('#payment-reference').closest('.payment-reference-section').show();
                }
            });

            // Add split leg button
            $(document).on('click', '#btn-add-split-leg', function(e) {
                e.preventDefault();
                self.addLeg();
            });

            // Remove split leg button
            $(document).on('click', '.btn-remove-split-leg', function(e) {
                e.preventDefault();
                const totalRows = $('#split-legs-container .split-leg-row').length;
                if (totalRows <= 2) {
                    if (typeof toastr !== 'undefined') {
                        toastr.warning('A split payment must have at least two payment methods.');
                    }
                    return;
                }
                $(this).closest('.split-leg-row').remove();
                self.recalculateSummary();
            });

            // Leg method change
            $(document).on('change', '.split-leg-method', function() {
                const method = $(this).val();
                const $row = $(this).closest('.split-leg-row');
                const $bankCol = $row.find('.split-leg-bank-col');
                const $bankSelect = $row.find('.split-leg-bank');

                if (['POS', 'TRANSFER', 'MOBILE'].includes(method)) {
                    self.populateBankSelect($bankSelect);
                    $bankCol.show();
                } else {
                    $bankCol.hide();
                    $bankSelect.val('');
                }
                self.recalculateSummary();
            });

            // Leg amount or reference input
            $(document).on('input change', '.split-leg-amount', function() {
                self.recalculateSummary();
            });
        },

        getTotalPayable: function() {
            const raw = $('#summary-total').text().replace('₦', '').replace(/,/g, '').trim();
            return parseFloat(raw) || 0;
        },

        loadBanks: function(callback) {
            if (window.availableBanks && window.availableBanks.length > 0) {
                if (typeof callback === 'function') callback(window.availableBanks);
                return;
            }
            const self = this;
            $.ajax({
                url: '/banks/active',
                method: 'GET',
                success: function(res) {
                    if (res && res.banks) {
                        window.availableBanks = res.banks;
                        $('.split-leg-bank').each(function() {
                            self.populateBankSelect($(this));
                        });
                        const $pb = $('#payment-bank');
                        if ($pb.length && $pb.children('option').length <= 1) {
                            (res.banks || []).forEach(function(b) {
                                const text = b.account_number ? `${b.name} - ${b.account_number}` : b.name;
                                $pb.append(`<option value="${b.id}">${text}</option>`);
                            });
                        }
                        if (typeof callback === 'function') callback(window.availableBanks);
                    } else {
                        if (typeof callback === 'function') callback([]);
                    }
                },
                error: function() {
                    if (typeof callback === 'function') callback([]);
                }
            });
        },

        populateBankSelect: function($select) {
            if (!$select || !$select.length) return;
            const currentVal = $select.val() || $select.attr('data-selected-bank');

            const render = function(banks) {
                $select.find('option:not(:first)').remove();
                (banks || []).forEach(function(bank) {
                    const text = bank.account_number ? `${bank.name} - ${bank.account_number}` : bank.name;
                    $select.append(`<option value="${bank.id}">${text}</option>`);
                });
                if (currentVal) $select.val(currentVal);
            };

            if (!window.availableBanks || window.availableBanks.length === 0) {
                this.loadBanks(function(banks) {
                    render(banks);
                });
            } else {
                render(window.availableBanks);
            }
        },

        createLegRowHtml: function(method, amount, bankId, ref) {
            method = method || 'CASH';
            amount = amount !== undefined && amount !== null ? amount : '';
            bankId = bankId || '';
            ref = ref || '';

            const isBankMethod = ['POS', 'TRANSFER', 'MOBILE'].includes(method);

            return `
                <div class="split-leg-row p-2 mb-2 border rounded bg-white shadow-sm position-relative">
                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                        <div style="flex: 1 1 140px;">
                            <label class="small text-muted mb-0 d-block">Method</label>
                            <select class="form-control form-control-sm split-leg-method">
                                <option value="CASH" ${method === 'CASH' ? 'selected' : ''}>Cash</option>
                                <option value="POS" ${method === 'POS' ? 'selected' : ''}>POS / Card</option>
                                <option value="TRANSFER" ${method === 'TRANSFER' ? 'selected' : ''}>Bank Transfer</option>
                                <option value="MOBILE" ${method === 'MOBILE' ? 'selected' : ''}>Mobile Money</option>
                                <option value="ACCOUNT" ${method === 'ACCOUNT' ? 'selected' : ''}>Account Balance</option>
                            </select>
                        </div>
                        <div class="split-leg-bank-col" style="flex: 1 1 200px; ${isBankMethod ? '' : 'display: none;'}">
                            <label class="small text-muted mb-0 d-block">Bank</label>
                            <select class="form-control form-control-sm split-leg-bank" data-selected-bank="${bankId}">
                                <option value="">-- Select Bank --</option>
                            </select>
                        </div>
                        <div style="flex: 1 1 130px;">
                            <label class="small text-muted mb-0 d-block">Amount (₦)</label>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-sm split-leg-amount" placeholder="0.00" value="${amount}">
                        </div>
                        <div style="flex: 1 1 160px;">
                            <label class="small text-muted mb-0 d-block">Reference (Optional)</label>
                            <input type="text" class="form-control form-control-sm split-leg-ref" placeholder="Ref / Trans ID" value="${ref}">
                        </div>
                        <div style="flex: 0 0 auto; padding-top: 18px;">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-split-leg" title="Remove method" style="height: 31px; width: 31px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="mdi mdi-delete-outline" style="font-size: 16px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        ensureInitialLegs: function() {
            const self = this;
            const $container = $('#split-legs-container');
            if ($container.children('.split-leg-row').length >= 2) {
                this.recalculateSummary();
                return;
            }

            const total = this.getTotalPayable();
            $container.empty();

            // Default leg 1: Cash
            const row1 = $(this.createLegRowHtml('CASH', ''));
            $container.append(row1);

            // Default leg 2: POS with remainder
            const row2 = $(this.createLegRowHtml('POS', total > 0 ? total.toFixed(2) : ''));
            $container.append(row2);

            this.loadBanks(function() {
                self.populateBankSelect(row2.find('.split-leg-bank'));
            });
            this.recalculateSummary();
        },

        addLeg: function() {
            const self = this;
            const $container = $('#split-legs-container');
            const total = this.getTotalPayable();
            const currentAllocated = this.getCurrentSum();
            const remainder = Math.max(0, total - currentAllocated);

            const row = $(this.createLegRowHtml('TRANSFER', remainder > 0 ? remainder.toFixed(2) : ''));
            $container.append(row);

            this.loadBanks(function() {
                self.populateBankSelect(row.find('.split-leg-bank'));
            });
            this.recalculateSummary();
        },

        getCurrentSum: function() {
            let sum = 0;
            $('.split-leg-row').each(function() {
                const val = parseFloat($(this).find('.split-leg-amount').val()) || 0;
                sum += val;
            });
            return sum;
        },

        recalculateSummary: function() {
            const total = this.getTotalPayable();
            const sum = this.getCurrentSum();
            const diff = total - sum;

            $('#split-total-target').text(`₦${total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            $('#split-sum-display').text(`₦${sum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);

            const $remDisplay = $('#split-remaining-display');
            if (Math.abs(diff) < 0.01) {
                $remDisplay.removeClass('text-danger text-warning').addClass('text-success').text('Balanced (₦0.00)');
            } else if (diff > 0) {
                $remDisplay.removeClass('text-success text-danger').addClass('text-warning')
                    .text(`Remaining: ₦${diff.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            } else {
                $remDisplay.removeClass('text-success text-warning').addClass('text-danger')
                    .text(`Over-allocated: ₦${Math.abs(diff).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            }
        },

        validateAndGetSplits: function(totalPayable) {
            const rows = $('#split-legs-container .split-leg-row');
            if (rows.length < 2) {
                if (typeof toastr !== 'undefined') toastr.warning('Split payment requires at least two payment method splits.');
                return false;
            }

            const splits = [];
            let sum = 0;
            let isValid = true;

            rows.each(function(idx) {
                const method = $(this).find('.split-leg-method').val();
                const bankId = $(this).find('.split-leg-bank').val() || null;
                const amount = parseFloat($(this).find('.split-leg-amount').val()) || 0;
                const ref = $(this).find('.split-leg-ref').val() || '';

                if (amount <= 0) {
                    if (typeof toastr !== 'undefined') toastr.warning(`Split #${idx + 1} must have an amount greater than 0.`);
                    isValid = false;
                    return false;
                }

                if (['POS', 'TRANSFER', 'MOBILE'].includes(method) && !bankId) {
                    if (typeof toastr !== 'undefined') toastr.warning(`Please select a bank for split #${idx + 1} (${method}).`);
                    isValid = false;
                    return false;
                }

                sum += amount;
                splits.push({
                    payment_method: method,
                    bank_id: bankId ? parseInt(bankId, 10) : null,
                    amount: amount,
                    reference_no: ref || null
                });
            });

            if (!isValid) {
                return false;
            }

            if (Math.abs(sum - totalPayable) > 0.05) {
                const diff = totalPayable - sum;
                const msg = diff > 0
                    ? `Split allocations do not cover the bill. ₦${diff.toLocaleString(undefined, { minimumFractionDigits: 2 })} remaining.`
                    : `Split allocations exceed total bill by ₦${Math.abs(diff).toLocaleString(undefined, { minimumFractionDigits: 2 })}.`;
                if (typeof toastr !== 'undefined') toastr.error(msg);
                return false;
            }

            return splits;
        }
    };

    window.CoreHealth.SplitPayments = SplitPayments;
    window.CoreHealth.validateAndGetSplits = function(total) {
        return SplitPayments.validateAndGetSplits(total);
    };

    $(document).ready(function() {
        SplitPayments.init();
    });

})(window, jQuery);
