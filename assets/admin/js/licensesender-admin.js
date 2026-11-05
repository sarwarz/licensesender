(function ($) {
  'use strict';

  // 1) Safe init for Select2
	function initLsSelects() {
		// Prefer WooCommerce's SelectWoo; fall back to Select2 if present
		if ($.fn.selectWoo) {
			$('#ls_mapped_product, .ls_mapped_variation_product').selectWoo();
		} else if ($.fn.select2) {
			$('#ls_mapped_product, .ls_mapped_variation_product').select2();
		}
	}

  // 1) Safe init for Select2/SelectWoo
  $(initLsSelects);

  // Re-init when WC (re)loads variation panels dynamically
  $(document.body).on(
    'woocommerce_variations_loaded woocommerce_variations_added wc-enhanced-select-init',
    initLsSelects
  );

  /* ================================
   * PRODUCT MAPPING TOGGLE
   * ================================ */
  function syncProductMappingVisibility() {
    var enabled = $('#ls_enabled').is(':checked');
    $('.ls-mapped-product-field').toggle(enabled);
  }

  $(document).on('change', '#ls_enabled', syncProductMappingVisibility);
  $(syncProductMappingVisibility);

  function syncVariationMappingVisibility($checkbox) {
    var enabled = $checkbox.is(':checked');
    $checkbox.closest('.ls-variation-license-fields').find('.ls-mapped-variation-field').toggle(enabled);
  }

  $(document).on('change', '.ls-enabled-variation', function () {
    syncVariationMappingVisibility($(this));
  });

  $(document.body).on('woocommerce_variations_loaded woocommerce_variations_added', function () {
    $('.ls-enabled-variation').each(function () {
      syncVariationMappingVisibility($(this));
    });
  });

})(jQuery);
