<script>
    (function($) {
        if (typeof $ === 'undefined') {
            console.warn('jQuery not available yet, delaying script init');
            return;
        }
        const SALE_PRICE = {{ $salePrice }};
        const ITEM_TYPE = '{{ $itemType }}';
        const ITEM_ID = {{ $itemId }};
        
        function updateDivergence($row) {
            const p = parseFloat($row.find('.payable-input-refined').val()) || 0;
            const c = parseFloat($row.find('.claims-input-refined').val()) || 0;
            const total = p + c;
            const diff = total - SALE_PRICE;
            const $indicator = $row.find('.divergence-indicator');
            if (Math.abs(diff) > 0.01) {
                $row.addClass('diverged');
                const sign = diff > 0 ? '+' : '';
                $indicator.html(`<span class="badge badge-warning" title="Total: &#8358;${total.toFixed(2)}"><i class="mdi mdi-alert-circle-outline mr-1"></i>${sign}${diff.toFixed(2)}</span>`);
            } else {
                $row.removeClass('diverged'); $indicator.empty();
            }
        }

        // Accordion & Scope Tracking
        $(document).off('click', '.scheme-modern-header').on('click', '.scheme-modern-header', function() {
            const $group = $(this).closest('.scheme-modern-group');
            $group.toggleClass('open');
            refreshScopeSummary();
        });

        function refreshScopeSummary() {
            const isBulkOpen = !$('#bulkBar').hasClass('d-none');
            const scope = $('#bulkScope').val();
            let $targets = collectTargets(scope);
            
            const count = $targets.length;
            $('#currentScopeCount').text(count);
            
            // Visual cues on schemes
            if (isBulkOpen) {
                $('.scheme-modern-group').each(function() {
                    const $g = $(this);
                    const isInScope = (scope === 'all') || (scope === 'visible' && $g.hasClass('open')) || (scope === 'empty' && $g.find('.hmo-row-refined').filter((i, el) => parseFloat($(el).data('orig-payable')) == 0 && parseFloat($(el).data('orig-claims')) == 0).length > 0);
                    $g.find('.bulk-active-indicator').toggleClass('d-none', !isInScope);
                });
            } else {
                $('.bulk-active-indicator').addClass('d-none');
            }
        }

        function collectTargets(scope) {
            let $targets;
            if (scope === 'visible') {
                $targets = $('.tab-pane.active .scheme-modern-group.open .hmo-row-refined:visible');
                if ($targets.length === 0 && $('.tab-pane.active#flat-view').length) {
                    $targets = $('.tab-pane.active .hmo-row-refined:visible');
                }
            } else if (scope === 'empty') {
                $targets = $('.hmo-row-refined').filter((i, el) => parseFloat($(el).data('orig-payable')) == 0 && parseFloat($(el).data('orig-claims')) == 0);
            } else {
                $targets = $('.hmo-row-refined');
            }
            return $targets;
        }

        $('#bulkScope').on('change', refreshScopeSummary);

        $('#tariffSearch').off('input').on('input', function() {
            const term = $(this).val().toLowerCase();
            if (!term) {
                $('.scheme-modern-group').removeClass('open').show().first().addClass('open');
                $('.hmo-row-refined').show();
                refreshScopeSummary();
                return;
            }
            $('.hmo-row-refined').each(function() {
                const name = $(this).data('name');
                const scheme = $(this).data('scheme');
                const match = name.indexOf(term) !== -1 || (scheme && scheme.indexOf(term) !== -1);
                $(this).toggle(match);
            });
            $('.scheme-modern-group').each(function() {
                const hasVisible = $(this).find('.hmo-row-refined:visible').length > 0;
                $(this).toggle(hasVisible);
                if (hasVisible) $(this).addClass('open');
            });
            refreshScopeSummary();
        });

        $(document).off('input', '.payable-input-refined').on('input', '.payable-input-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            const val = parseFloat($(this).val()) || 0;
            if ($('#globalSyncToggle').is(':checked')) {
                const remainder = SALE_PRICE - val;
                if (remainder >= 0) $row.find('.claims-input-refined').val(remainder.toFixed(2));
            }
            updateDivergence($row); checkDirty($row);
            syncCrossTab($row, 'payable', $(this).val());
        });

        $(document).off('input', '.claims-input-refined').on('input', '.claims-input-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            const val = parseFloat($(this).val()) || 0;
            if ($('#globalSyncToggle').is(':checked')) {
                const remainder = SALE_PRICE - val;
                if (remainder >= 0) $row.find('.payable-input-refined').val(remainder.toFixed(2));
            }
            updateDivergence($row); checkDirty($row);
            syncCrossTab($row, 'claims', $(this).val());
        });

        function syncCrossTab($row, field, val) {
            const id = $row.data('hmo-id');
            const $otherRows = $(`.hmo-row-refined[data-hmo-id="${id}"]`).not($row);
            $otherRows.each(function() {
                const $r = $(this);
                $r.find('.' + field + '-input-refined').val(val);
                if ($('#globalSyncToggle').is(':checked')) {
                    const remainder = SALE_PRICE - (parseFloat(val) || 0);
                    if (remainder >= 0) {
                        const otherField = field === 'payable' ? 'claims' : 'payable';
                        $r.find('.' + otherField + '-input-refined').val(remainder.toFixed(2));
                    }
                }
                updateDivergence($r); checkDirty($r);
            });
        }

        $(document).off('change', '.coverage-select-refined').on('change', '.coverage-select-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            const val = $(this).val();
            $row.find('.coverage-dot').attr('class', 'coverage-dot ' + val);
            checkDirty($row);
            const id = $row.data('hmo-id');
            $(`.hmo-row-refined[data-hmo-id="${id}"]`).not($row).each(function() {
                $(this).find('.coverage-select-refined').val(val);
                $(this).find('.coverage-dot').attr('class', 'coverage-dot ' + val);
                checkDirty($(this));
            });
        });

        function checkDirty($row) {
            const op = parseFloat($row.data('orig-payable')).toFixed(2);
            const oc = parseFloat($row.data('orig-claims')).toFixed(2);
            const ocv = $row.data('orig-coverage');
            const cp = parseFloat($row.find('.payable-input-refined').val()).toFixed(2);
            const cc = parseFloat($row.find('.claims-input-refined').val()).toFixed(2);
            const ccv = $row.find('.coverage-select-refined').val();
            const dirty = (op !== cp) || (oc !== cc) || (ocv !== ccv);
            $row.toggleClass('dirty', dirty);
            const $group = $row.closest('.scheme-modern-group');
            if ($group.length) {
                const hasDirty = $group.find('.hmo-row-refined.dirty').length > 0;
                $group.find('.scheme-stats-pill').toggleClass('d-none', !hasDirty);
            }
            updateBulkCount();
        }

        function updateBulkCount() {
            const uniqueHmoIds = new Set();
            $('.hmo-row-refined.dirty').each(function() { uniqueHmoIds.add($(this).data('hmo-id')); });
            const count = uniqueHmoIds.size;
            $('#changeCount').text(count);
            $('#impactSummary').text(`${count} HMO Entity${count > 1 ? 'ies' : ''} modified`);
            $('#saveAllContainer').toggleClass('d-none', count === 0);
        }

        $('#saveAllBtn').off('click').on('click', function() {
            const impactMap = {};
            let divCount = 0; let covCount = 0; let processed = new Set();
            $('.hmo-row-refined.dirty').each(function() {
                const id = $(this).data('hmo-id');
                if (processed.has(id)) return;
                processed.add(id);
                const $r = $(this);
                const scheme = $r.closest('.scheme-modern-group').find('.scheme-modern-header h6').text() || 'Flat List Items';
                const hmoName = $r.find('.font-weight-medium').text();
                const isDivergent = $r.hasClass('diverged');
                const isCovChange = $r.data('orig-coverage') !== $r.find('.coverage-select-refined').val();
                if (isDivergent) divCount++;
                if (isCovChange) covCount++;
                if (!impactMap[scheme]) impactMap[scheme] = [];
                impactMap[scheme].push({ name: hmoName, div: isDivergent, cov: isCovChange });
            });
            let html = '';
            for (const scheme in impactMap) {
                html += `<div class="impact-scheme-card shadow-sm"><div class="impact-scheme-header"><span class="impact-scheme">${scheme}</span><span class="badge badge-pill badge-primary">${impactMap[scheme].length} HMOs</span></div><div class="impact-hmo-list">`;
                impactMap[scheme].forEach(hmo => {
                    html += `<div class="impact-hmo-item"><span>${hmo.name}</span><div class="d-flex gap-1">${hmo.div ? '<span class="badge badge-soft-warning smallest">Divergent</span>' : ''}${hmo.cov ? '<span class="badge badge-soft-info smallest">Coverage</span>' : ''}${(!hmo.div && !hmo.cov) ? '<span class="badge badge-soft-success smallest">Price Change</span>' : ''}</div></div>`;
                });
                html += `</div></div>`;
            }
            $('#impactDetailsList').html(html);
            $('#sumTotalHmos').text(processed.size);
            $('#sumDivergentHmos').text(divCount);
            $('#sumCoverageHmos').text(covCount);
            $('#saveSummaryModal').modal('show');
        });

        $('#confirmFinalSave').off('click').on('click', function() {
            $('#saveSummaryModal').modal('hide');
            const rows = []; const processedHmos = new Set();
            $('.hmo-row-refined.dirty').each(function() {
                const id = $(this).data('hmo-id');
                if (!processedHmos.has(id)) {
                    const $r = $(this);
                    rows.push({
                        hmo_id: id, product_id: ITEM_TYPE === 'product' ? ITEM_ID : null, service_id: ITEM_TYPE === 'service' ? ITEM_ID : null,
                        payable_amount: $r.find('.payable-input-refined').val(), claims_amount: $r.find('.claims-input-refined').val(), coverage_mode: $r.find('.coverage-select-refined').val()
                    });
                    processedHmos.add(id);
                }
            });
            performSave(rows);
        });

        function performSave(rows) {
            const $btn = $('#saveAllBtn'); const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...');
            $.ajax({
                url: '{{ route("hmo-tariffs.bulk-update") }}', method: 'POST', data: { _token: '{{ csrf_token() }}', rows: rows },
                success: function(res) {
                    toastr.success(res.message);
                    rows.forEach(r => {
                        const $rs = $('.hmo-row-refined[data-hmo-id="'+r.hmo_id+'"]');
                        $rs.data('orig-payable', r.payable_amount).data('orig-claims', r.claims_amount).data('orig-coverage', r.coverage_mode);
                        $rs.removeClass('dirty');
                    });
                    $('.scheme-stats-pill').addClass('d-none');
                    updateBulkCount();
                },
                error: function() { toastr.error('Save failed'); },
                complete: function() { $btn.prop('disabled', false).html(originalHtml); }
            });
        }

        // Bulk Tools UI & Preview Logic
        function updateBulkPreview() {
            const hmoPct = parseFloat($('#bulkClaimsPct').val()) || 0;
            const patPct = parseFloat($('#bulkPayablePct').val()) || 0;
            const refPrice = parseFloat($('#bulkRefPrice').val()) || SALE_PRICE;
            
            const claims = (refPrice * (hmoPct / 100));
            const payable = (refPrice * (patPct / 100)); 
            $('#previewBulkClaims').text('₦' + claims.toFixed(2));
            $('#previewBulkPayable').text('₦' + payable.toFixed(2));
        }

        $('#bulkClaimsPct').on('input', function() {
            if ($('#globalSyncToggle').is(':checked')) {
                const val = parseFloat($(this).val()) || 0;
                $('#bulkPayablePct').val(Math.max(0, 100 - val));
            }
            updateBulkPreview();
        });
        $('#bulkPayablePct, #bulkRefPrice').on('input', updateBulkPreview);

        $(document).off('change', '.mode-radio').on('change', '.mode-radio', function() { 
            $('#bulkBar').attr('data-mode', $(this).val()); 
            updateBulkPreview();
        });
        $('#bulkEditToggle').off('click').on('click', () => {
            const $bar = $('#bulkBar');
            $bar.toggleClass('d-none');
            refreshScopeSummary();
            updateBulkPreview();
            if (!$bar.hasClass('d-none')) $('html, body').animate({ scrollTop: $bar.offset().top - 100 }, 500);
        });

        $('#applyBulkBtn').off('click').on('click', function() {
            const mode = $('input[name="bulkMode"]:checked').val();
            const coverage = $('#bulkCoverage').val();
            const scope = $('#bulkScope').val();
            let $targets = collectTargets(scope);
            
            $targets.each(function() {
                const $row = $(this);
                if (mode === 'percent') {
                    const hmoPct = parseFloat($('#bulkClaimsPct').val()) || 0;
                    const patPct = parseFloat($('#bulkPayablePct').val()) || 0;
                    const refPrice = parseFloat($('#bulkRefPrice').val()) || SALE_PRICE;
                    
                    const claims = (refPrice * (hmoPct / 100)).toFixed(2);
                    const payable = (refPrice * (patPct / 100)).toFixed(2);
                    
                    $row.find('.claims-input-refined').val(claims);
                    $row.find('.payable-input-refined').val(payable);
                    
                    // Manually trigger updates
                    updateDivergence($row); checkDirty($row);
                    syncCrossTab($row, 'claims', claims);
                    syncCrossTab($row, 'payable', payable);
                } else {
                    const p = $('#bulkPayableAmount').val();
                    const c = $('#bulkClaimsAmount').val();
                    if (p !== '') $row.find('.payable-input-refined').val(p).trigger('input');
                    if (c !== '') $row.find('.claims-input-refined').val(c).trigger('input');
                }
                if (coverage) $row.find('.coverage-select-refined').val(coverage).trigger('change');
            });
            toastr.info('Bulk updates applied locally.');
        });

        // Initial state
        $('.hmo-row-refined').each(function() { updateDivergence($(this)); });
        refreshScopeSummary();
    })(jQuery);
</script>
