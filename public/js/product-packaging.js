/**
 * Product Packaging Repeater
 *
 * Dynamic repeater for packaging hierarchy levels on product create/edit forms.
 * Auto-computes base_unit_qty chain and syncs labels.
 */
(function($) {
    'use strict';

    var $container, $addBtn, $baseUnitInput, $decimalToggle;
    var levelTemplate;

    function init() {
        $container = $('#packaging-levels-container');
        $addBtn = $('#add-packaging-level');
        $baseUnitInput = $('input[name="base_unit_name"]');
        $decimalToggle = $('input[name="allow_decimal_qty"]');

        if (!$container.length) return;

        levelTemplate = buildTemplate();

        $addBtn.on('click', function(e) {
            e.preventDefault();
            addLevel();
        });

        $container.on('click', '.btn-remove-level', function(e) {
            e.preventDefault();
            $(this).closest('.packaging-row').remove();
            reindex();
            recalculate();
        });

        $container.on('input change', '.pkg-units-input, .pkg-name-input', function() {
            recalculate();
        });

        $baseUnitInput.on('input', function() {
            recalculate();
        });

        // Initial calculation
        recalculate();
    }

    function buildTemplate() {
        return '<div class="packaging-row card-modern p-3 mb-2" data-level="__LEVEL__">'
            + '<div class="d-flex justify-content-between align-items-center mb-2">'
            + '<span class="badge badge-primary level-indicator">Level __LEVEL__</span>'
            + '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-level">'
            + '<i class="mdi mdi-close"></i></button></div>'
            + '<input type="hidden" name="packagings[__IDX__][id]" value="">'
            + '<div class="row g-2">'
            + '<div class="col-md-3">'
            + '<label class="form-label-modern">Packaging Name <span class="text-danger">*</span></label>'
            + '<input type="text" name="packagings[__IDX__][name]" class="form-control form-control-modern pkg-name-input" placeholder="e.g. Strip, Box, Bottle">'
            + '</div>'
            + '<div class="col-md-2">'
            + '<label class="form-label-modern">Contains <span class="text-danger">*</span></label>'
            + '<input type="number" step="any" min="0.0001" name="packagings[__IDX__][units_in_parent]" class="form-control form-control-modern pkg-units-input" placeholder="Qty">'
            + '</div>'
            + '<div class="col-md-2">'
            + '<label class="form-label-modern">Per</label>'
            + '<div class="form-control-plaintext prev-unit-label text-muted">—</div>'
            + '</div>'
            + '<div class="col-md-2">'
            + '<label class="form-label-modern">= Base Units</label>'
            + '<div class="form-control-plaintext base-qty-display font-weight-bold">—</div>'
            + '</div>'
            + '<div class="col-md-3">'
            + '<label class="form-label-modern">Description</label>'
            + '<input type="text" name="packagings[__IDX__][description]" class="form-control form-control-modern" placeholder="Optional note">'
            + '</div>'
            + '</div>'
            + '<div class="row g-2 mt-2">'
            + '<div class="col-md-3">'
            + '<div class="custom-control custom-checkbox">'
            + '<input type="checkbox" class="custom-control-input" name="packagings[__IDX__][is_default_purchase]" id="pkg_dp___IDX__" value="1">'
            + '<label class="custom-control-label" for="pkg_dp___IDX__">Default Purchase</label>'
            + '</div></div>'
            + '<div class="col-md-3">'
            + '<div class="custom-control custom-checkbox">'
            + '<input type="checkbox" class="custom-control-input" name="packagings[__IDX__][is_default_dispense]" id="pkg_dd___IDX__" value="1">'
            + '<label class="custom-control-label" for="pkg_dd___IDX__">Default Dispense</label>'
            + '</div></div>'
            + '<div class="col-md-3">'
            + '<label class="form-label-modern">Barcode</label>'
            + '<input type="text" name="packagings[__IDX__][barcode]" class="form-control form-control-modern" placeholder="Optional">'
            + '</div>'
            + '</div></div>';
    }

    function addLevel() {
        var rows = $container.find('.packaging-row');
        var idx = rows.length;
        var level = idx + 1;

        var html = levelTemplate
            .replace(/__LEVEL__/g, level)
            .replace(/__IDX__/g, idx);

        $container.append(html);
        recalculate();
    }

    function reindex() {
        $container.find('.packaging-row').each(function(idx) {
            var level = idx + 1;
            $(this).attr('data-level', level);
            $(this).find('.level-indicator').text('Level ' + level);

            // Reindex field names
            $(this).find('[name]').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/packagings\[\d+\]/, 'packagings[' + idx + ']');
                $(this).attr('name', name);
            });

            // Reindex checkbox IDs
            $(this).find('.custom-control-input').each(function() {
                var id = $(this).attr('id');
                if (id) {
                    var newId = id.replace(/_\d+$/, '_' + idx);
                    $(this).attr('id', newId);
                    $(this).next('label').attr('for', newId);
                }
            });
        });
    }

    function recalculate() {
        var baseUnit = $baseUnitInput.val() || 'Piece';
        var rows = $container.find('.packaging-row');
        var prevName = baseUnit;
        var prevBase = 1;

        // Update base unit display
        $('.base-unit-name-display').text(baseUnit);

        rows.each(function(idx) {
            var $row = $(this);
            var units = parseFloat($row.find('.pkg-units-input').val()) || 0;
            var name = $row.find('.pkg-name-input').val() || ('Level ' + (idx + 1));

            $row.find('.prev-unit-label').text(prevName);

            if (units > 0) {
                var baseQty = units * prevBase;
                $row.find('.base-qty-display')
                    .text(formatNumber(baseQty) + ' ' + baseUnit)
                    .removeClass('text-muted').addClass('font-weight-bold');
                prevBase = baseQty;
            } else {
                $row.find('.base-qty-display').text('—').addClass('text-muted');
            }

            prevName = name;
        });
    }

    function formatNumber(n) {
        if (n === Math.floor(n)) {
            return n.toLocaleString();
        }
        return parseFloat(n.toFixed(4)).toLocaleString();
    }

    $(document).ready(init);

})(jQuery);
