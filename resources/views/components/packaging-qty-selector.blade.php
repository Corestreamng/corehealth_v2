{{--
Packaging-aware quantity selector component.

Usage:
  <x-packaging-qty-selector
      :product-id="$productId"
      name="qty"
      packaging-name="packaging_id"
      :value="$currentQty"
      :packaging-value="$currentPackagingId"
  />

The component auto-loads packagings via AJAX from /products/{id}/packagings.
Include jQuery before this component.
--}}

@props([
    'productId' => null,
    'name' => 'qty',
    'packagingName' => 'packaging_id',
    'value' => '',
    'packagingValue' => '',
    'required' => false,
    'inputClass' => 'form-control form-control-sm',
    'allowDecimal' => false,
])

<div class="packaging-qty-selector d-flex align-items-center" data-product-id="{{ $productId }}">
    <input type="number"
           name="{{ $name }}"
           value="{{ $value }}"
           class="{{ $inputClass }} packaging-qty-input mr-1"
           style="max-width: 90px;"
           step="{{ $allowDecimal ? '0.01' : '1' }}"
           min="0"
           placeholder="Qty"
           {{ $required ? 'required' : '' }}
           data-packaging-qty-input>

    <select name="{{ $packagingName }}"
            class="form-control form-control-sm packaging-unit-select"
            style="max-width: 130px;"
            data-packaging-unit-select>
        <option value="">Base Unit</option>
        {{-- Populated via JS on init --}}
    </select>

    <span class="ml-2 text-muted small packaging-base-equiv" data-packaging-base-equiv>
        {{-- Shows "= X base units" --}}
    </span>
</div>

@once
<script>
$(function() {
    function initPackagingQtySelector($container) {
        var productId = $container.data('product-id');
        if (!productId) return;

        var $select = $container.find('[data-packaging-unit-select]');
        var $input = $container.find('[data-packaging-qty-input]');
        var $equiv = $container.find('[data-packaging-base-equiv]');
        var selectedVal = $select.data('selected') || '{{ $packagingValue }}';

        $.get('/products/' + productId + '/packagings', function(data) {
            $select.find('option:not(:first)').remove();
            $select.find('option:first').text((data.base_unit_name || 'Base Unit') + ' (base)');

            (data.packagings || []).forEach(function(pkg) {
                var opt = $('<option>', {
                    value: pkg.id,
                    text: pkg.name + ' (' + parseFloat(pkg.base_unit_qty) + ' ' + (data.base_unit_name || 'units') + ')',
                    'data-base-qty': pkg.base_unit_qty
                });
                if (String(pkg.id) === String(selectedVal)) opt.prop('selected', true);
                $select.append(opt);
            });

            updateEquiv();
        });

        function updateEquiv() {
            var baseQty = parseFloat($select.find(':selected').data('base-qty')) || 1;
            var qty = parseFloat($input.val()) || 0;
            if (qty > 0 && baseQty > 1) {
                $equiv.text('= ' + parseFloat((qty * baseQty).toFixed(4)) + ' ' + ($select.find('option:first').text().replace(' (base)', '') || 'units'));
            } else {
                $equiv.text('');
            }
        }

        $select.on('change', updateEquiv);
        $input.on('input change', updateEquiv);
    }

    $('.packaging-qty-selector').each(function() {
        initPackagingQtySelector($(this));
    });

    // Expose for dynamic initialization
    window.initPackagingQtySelector = initPackagingQtySelector;
});
</script>
@endonce
