<script>
    (function($) {
        if (typeof $ === 'undefined') {
            console.warn('jQuery not available in catalog view, delaying init');
            return;
        }
        const HMO_IDS = @json($hmoIds);
        
        function updateDivergence($row) {
            const p = parseFloat($row.find('.payable-input-refined').val()) || 0;
            const c = parseFloat($row.find('.claims-input-refined').val()) || 0;
            const base = parseFloat($row.data('base-price'));
            const total = p + c;
            const diff = total - base;
            const $indicator = $row.find('.divergence-indicator');
            if (Math.abs(diff)> 0.01) {
                $row.addClass('diverged');
                const sign = diff> 0 ? '+' : '';
                $indicator.html(`<span class="badge badge-warning" title="Total: &#8358;${total.toFixed(2)}"><i class="mdi mdi-alert-circle-outline mr-1"></i>${sign}${diff.toFixed(2)} from base</span>`);
            } else {
                $row.removeClass('diverged'); $indicator.empty();
            }
        }

        // Accordion & Scope Tracking
        $(document).off('click', '.scheme-modern-header').on('click', '.scheme-modern-header', function() {
            $(this).closest('.scheme-modern-group').toggleClass('open');
            refreshScopeSummary();
        });

        function refreshScopeSummary() {
            const isBulkOpen = !$('#bulkBar').hasClass('d-none');
            const scope = $('#bulkScope').val();
            let $targets = collectTargets(scope);
            $('#currentScopeCount').text($targets.length);
            
            if (isBulkOpen) {
                $('.scheme-modern-group').each(function() {
                    const $g = $(this);
                    const isInScope = (scope === 'visible' && $g.hasClass('open')) || (scope !== 'visible');
                    $g.find('.bulk-active-indicator').toggleClass('d-none', !isInScope);
                });
            } else {
                $('.bulk-active-indicator').addClass('d-none');
            }
        }

        function collectTargets(scope) {
            let $targets = $('.hmo-row-refined:visible');
            if (scope === 'visible') $targets = $('.scheme-modern-group.open .hmo-row-refined:visible');
            else if (scope === 'pharmacy') $targets = $('.hmo-row-refined[data-type="product"]');
            else if (scope === 'services') $targets = $('.hmo-row-refined[data-type="service"]');
            else if (scope === 'empty') $targets = $('.hmo-row-refined').filter((i, el) => parseFloat($(el).data('orig-payable')) == 0 && parseFloat($(el).data('orig-claims')) == 0);
            return $targets;
        }

        $('#bulkScope').on('change', refreshScopeSummary);

        $('#catalogSearch').off('input').on('input', function() {
            const term = $(this).val().toLowerCase();
            let visibleCount = 0;
            $('.hmo-row-refined').each(function() {
                const match = $(this).data('name').indexOf(term) !== -1 || $(this).closest('.scheme-modern-group').data('category').toLowerCase().indexOf(term) !== -1;
                $(this).toggle(match);
                if (match) visibleCount++;
            });
            $('.scheme-modern-group').each(function() {
                const hasVisible = $(this).find('.hmo-row-refined:visible').length> 0;
                $(this).toggle(hasVisible);
                if (term && hasVisible) $(this).addClass('open');
            });
            $('#visibleCount').text(visibleCount);
            refreshScopeSummary();
        });

        $(document).off('input', '.payable-input-refined').on('input', '.payable-input-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            const val = parseFloat($(this).val()) || 0;
            const base = parseFloat($row.data('base-price'));
            if ($('#globalSyncToggle').is(':checked')) {
                const remainder = base - val;
                if (remainder>= 0) $row.find('.claims-input-refined').val(remainder.toFixed(2));
            }
            updateDivergence($row); checkDirty($row);
        });

        $(document).off('input', '.claims-input-refined').on('input', '.claims-input-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            const val = parseFloat($(this).val()) || 0;
            const base = parseFloat($row.data('base-price'));
            if ($('#globalSyncToggle').is(':checked')) {
                const remainder = base - val;
                if (remainder>= 0) $row.find('.payable-input-refined').val(remainder.toFixed(2));
            }
            updateDivergence($row); checkDirty($row);
        });

        $(document).off('change', '.coverage-select-refined').on('change', '.coverage-select-refined', function() {
            const $row = $(this).closest('.hmo-row-refined');
            $row.find('.coverage-dot').attr('class', 'coverage-dot ' + $(this).val());
            checkDirty($row);
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
                const hasDirty = $group.find('.hmo-row-refined.dirty').length> 0;
                $group.find('.scheme-stats-pill').toggleClass('d-none', !hasDirty);
            }
            updateBulkCount();
        }

        function updateBulkCount() {
            const count = $('.hmo-row-refined.dirty').length;
            $('#impactSummary').text(`${count} item${count> 1 ? 's' : ''} modified`);
            $('#saveAllContainer').toggleClass('d-none', count === 0);
        }

        // Detailed Save Flow
        $('#saveAllBtn').off('click').on('click', function() {
            const impactMap = {};
            let divCount = 0; let covCount = 0;
            $('.hmo-row-refined.dirty').each(function() {
                const $r = $(this);
                const category = $r.closest('.scheme-modern-group').find('.scheme-modern-header h6').text();
                const itemName = $r.find('.font-weight-medium').text();
                const isDivergent = $r.hasClass('diverged');
                const isCovChange = $r.data('orig-coverage') !== $r.find('.coverage-select-refined').val();
                if (isDivergent) divCount++;
                if (isCovChange) covCount++;
                if (!impactMap[category]) impactMap[category] = [];
                impactMap[category].push({ name: itemName, div: isDivergent, cov: isCovChange });
            });
            let html = '';
            for (const cat in impactMap) {
                html += `<div class="impact-scheme-card shadow-sm"><div class="impact-scheme-header"><span class="impact-scheme">${cat}</span><span class="badge badge-pill badge-primary">${impactMap[cat].length} items</span></div><div class="impact-hmo-list">`;
                impactMap[cat].forEach(item => {
                    html += `<div class="impact-hmo-item"><span>${item.name}</span><div class="d-flex gap-1">${item.div ? '<span class="badge badge-soft-warning smallest">Divergent</span>' : ''}${item.cov ? '<span class="badge badge-soft-info smallest">Coverage</span>' : ''}${(!item.div && !item.cov) ? '<span class="badge badge-soft-success smallest">Price Change</span>' : ''}</div></div>`;
                });
                html += `</div></div>`;
            }
            $('#impactDetailsList').html(html);
            $('#sumTotalHmos').text($('.hmo-row-refined.dirty').length);
            $('#sumDivergentHmos').text(divCount);
            $('#sumCoverageHmos').text(covCount);
            $('#saveSummaryModal').modal('show');
        });

        $('#confirmFinalSave').off('click').on('click', function() {
            $('#saveSummaryModal').modal('hide');
            const rows = [];
            $('.hmo-row-refined.dirty').each(function() {
                const $r = $(this);
                HMO_IDS.forEach(hmoId => {
                    rows.push({
                        hmo_id: hmoId, product_id: $r.data('type') === 'product' ? $r.data('id') : null, service_id: $r.data('type') === 'service' ? $r.data('id') : null,
                        payable_amount: $r.find('.payable-input-refined').val(), claims_amount: $r.find('.claims-input-refined').val(), coverage_mode: $r.find('.coverage-select-refined').val()
                    });
                });
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
                    $('.hmo-row-refined.dirty').each(function() {
                        const $r = $(this);
                        $r.data('orig-payable', $r.find('.payable-input-refined').val()).data('orig-claims', $r.find('.claims-input-refined').val()).data('orig-coverage', $r.find('.coverage-select-refined').val());
                        $r.removeClass('dirty');
                    });
                    $('.scheme-stats-pill').addClass('d-none');
                    updateBulkCount();
                },
                error: function() { toastr.error('Save failed'); },
                complete: function() { $btn.prop('disabled', false).html(originalHtml); }
            });
        }

        $(document).off('change', '.mode-radio').on('change', '.mode-radio', function() { $('#bulkBar').attr('data-mode', $(this).val()); });
        
        $('#bulkClaimsPct').on('input', function() {
            if ($('#globalSyncToggle').is(':checked')) {
                const val = parseFloat($(this).val()) || 0;
                $('#bulkPayablePct').val(Math.max(0, 100 - val));
            }
        });

        $('#bulkEditToggle').off('click').on('click', () => {
            $('#bulkBar').toggleClass('d-none');
            refreshScopeSummary();
        });

        $('#applyBulkBtn').off('click').on('click', function() {
            const mode = $('input[name="bulkMode"]:checked').val();
            const coverage = $('#bulkCoverage').val();
            const scope = $('#bulkScope').val();
            let $targets = collectTargets(scope);
            
            $targets.each(function() {
                const $row = $(this);
                const base = parseFloat($row.data('base-price'));
                const refVal = $('#bulkRefPrice').val();
                const refPrice = refVal !== '' ? parseFloat(refVal) : base;

                if (mode === 'percent') {
                    const hmoPct = parseFloat($('#bulkClaimsPct').val()) || 0;
                    const patPct = parseFloat($('#bulkPayablePct').val()) || 0;
                    
                    const claims = (refPrice * (hmoPct / 100)).toFixed(2);
                    const payable = (refPrice * (patPct / 100)).toFixed(2);
                    $row.find('.claims-input-refined').val(claims);
                    $row.find('.payable-input-refined').val(payable);
                } else {
                    const p = $('#bulkPayableAmount').val();
                    const c = $('#bulkClaimsAmount').val();
                    if (p !== '') $row.find('.payable-input-refined').val(p);
                    if (c !== '') $row.find('.claims-input-refined').val(c);
                }
                if (coverage) $row.find('.coverage-select-refined').val(coverage).trigger('change');
                updateDivergence($row); checkDirty($row);
            });
            toastr.info('Bulk updates applied locally.');
        });

        // Init
        $('.hmo-row-refined').each(function() { updateDivergence($(this)); });
        refreshScopeSummary();
    })(jQuery);
</script>
