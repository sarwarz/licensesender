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

  // 2) Ping API (admin)
  $(document).on('click', '#ls_ping_api', function () {
    const $btn = $(this);
    const $result = $('#ls_ping_api_result');

    // If it's a <button>, use text(); if it's an <input>, fall back to val()
    const setBtnLabel = (t) => ($btn.is('input,textarea') ? $btn.val(t) : $btn.text(t));

    $btn.prop('disabled', true);
    setBtnLabel('Pinging…');
    $result.removeClass('success error').text('Please wait...');

    $.post(ls_ajax_object.ajax_url, {
      action: 'ls_ping_api',
      _ajax_nonce: ls_ajax_object.nonce
    })
      .done(function (response) {
        $btn.prop('disabled', false);
        setBtnLabel('Ping API');

        if (response && response.success && response.data && response.data.success) {
          const serverTime =
            (response.data.data && response.data.data.server_time) || '';
          $result
            .addClass('success')
            .html(
              `<strong>✅ ${response.data.message || 'Ping OK'}</strong>${
                serverTime ? `<br>Server Time: ${serverTime}` : ''
              }`
            );
        } else {
          const errorMsg =
            (response && response.data && response.data.message) || 'Ping failed.';
          $result.addClass('error').html(`<strong>❌ Ping Failed:</strong> ${errorMsg}`);
        }
      })
      .fail(function (jqXHR) {
        $btn.prop('disabled', false);
        setBtnLabel('Ping API');

        let errorMsg = '❌ Something went wrong with the request.';
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
          errorMsg = `❌ ${jqXHR.responseJSON.data.message}`;
        }
        $result.addClass('error').text(errorMsg);
      });
  });

  // 3) Send test email (delegate + consistent ajax_url)
  $(document).on('click', '#ls_send_test_email', function () {
    const email = $('#ls_test_email').val();
    const $out = $('#ls_test_email_result');

    $out.text('Sending…');

    $.post(ls_ajax_object.ajax_url, {
      action: 'ls_send_test_email',
      email: email,
      mode: $('#ls_test_email_mode').val() || 'bulk',
      _ajax_nonce: ls_ajax_object.test_email_nonce
    })
      .done(function (response) {
        if (response && response.success) {
          $out.text(response.data && response.data.message ? response.data.message : 'Sent.');
        } else {
          $out.text(
            (response && response.data && response.data.message) || 'Failed to send email.'
          );
        }
      })
      .fail(function () {
        $out.text('Request failed. Please try again.');
      });
  });
	
  /* ================================
   * SSO SETTINGS TOGGLE
   * ================================ */
  function initSsoSettings() {
    const $toggle = $('#lship_sso_enabled');
    const $token  = $('#lship_sso_token');
    const $email  = $('#lship_sso_user_email');

    if (!$toggle.length) return;

    function syncSsoState() {
      const enabled = $toggle.is(':checked');

      $token.prop('readonly', !enabled);
      $email.prop('readonly', !enabled);

      $token.toggleClass('readonly', !enabled);
      $email.toggleClass('readonly', !enabled);
    }

    syncSsoState();
    $toggle.on('change', syncSsoState);
  }

	$(initSsoSettings);

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
