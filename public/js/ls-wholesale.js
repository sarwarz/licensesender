(function ($) {
  'use strict';

  function showToast(message, isError) {
    var $toast = $('<div class="ls-wholesale-toast"></div>').text(message);
    if (isError) {
      $toast.css('background', '#991b1b');
    }
    $('body').append($toast);
    setTimeout(function () {
      $toast.fadeOut(200, function () {
        $(this).remove();
      });
    }, 3200);
  }

  function refreshCartFragments(fragments, cartHash) {
    if (cartHash) {
      try {
        sessionStorage.setItem('wc_cart_hash', cartHash);
      } catch (e) {
        // Ignore storage errors in private browsing.
      }
    }

    if (fragments) {
      $.each(fragments, function (selector, html) {
        var $target = $(selector);
        if ($target.length) {
          $target.replaceWith(html);
        }
      });
      $(document.body).trigger('added_to_cart', [fragments, cartHash, null]);
    }

    $(document.body).trigger('wc_fragment_refresh');
  }

  function updateViewCartCount(count) {
    count = parseInt(count, 10) || 0;
    var $count = $('.ls-wholesale-cart-count');
    if (!$count.length) {
      return;
    }
    if (count > 0) {
      $count.text('(' + count + ')').prop('hidden', false).removeAttr('hidden');
    } else {
      $count.text('').prop('hidden', true).attr('hidden', 'hidden');
    }
  }

  var catalogState = {
    page: 1
  };

  function getCatalogPerPage() {
    var perPage = window.lsWholesale && window.lsWholesale.catalogPerPage;
    perPage = parseInt(perPage, 10);
    return perPage > 0 ? perPage : 10;
  }

  function formatI18n(template, values) {
    var output = template || '';
    values.forEach(function (value, index) {
      output = output.replace('%' + (index + 1) + '$d', value);
    });
    return output;
  }

  function getCartWholesaleQty() {
    var qty = window.lsWholesale && window.lsWholesale.cartWholesaleQty;
    qty = parseInt(qty, 10);
    return qty > 0 ? qty : 0;
  }

  function getMinOrderQuantity() {
    var min = window.lsWholesale && window.lsWholesale.minOrderQuantity;
    min = parseInt(min, 10);
    return min > 0 ? min : 0;
  }

  function getAddToCartMinimumError(quantity) {
    var minimum = getMinOrderQuantity();
    if (!minimum) {
      return '';
    }

    var current = getCartWholesaleQty();
    quantity = parseInt(quantity, 10) || 1;

    if (current >= minimum || current + quantity >= minimum) {
      return '';
    }

    var i18n = (window.lsWholesale && window.lsWholesale.i18n) || {};
    return formatI18n(i18n.minOrderAdd || 'Wholesale orders require a minimum of %1$d units. Add at least %2$d more units to continue.', [
      minimum,
      minimum - current
    ]);
  }

  function updateCatalogQuantityMinimums() {
    var minimum = getMinOrderQuantity();
    if (!minimum) {
      return;
    }

    var current = getCartWholesaleQty();
    var qtyMin = 1;

    if (current < minimum) {
      qtyMin = Math.max(1, minimum - current);
    }

    $('.ls-wholesale-qty').each(function () {
      var $input = $(this);
      var max = parseInt($input.attr('max'), 10);
      var value = parseInt($input.val(), 10) || qtyMin;

      $input.attr('min', qtyMin);
      if (value < qtyMin) {
        $input.val(qtyMin);
      }
      if (max > 0 && value > max) {
        $input.val(max);
      }
    });
  }

  function scrollToCatalogTable() {
    var $wrap = $('.ls-wholesale-table-wrap');
    if ($wrap.length && $wrap.offset()) {
      $('html, body').animate({ scrollTop: $wrap.offset().top - 24 }, 200);
    }
  }

  function updateCatalogView(options) {
    options = options || {};
    var $wrap = $('.ls-wholesale-table-wrap');
    var $table = $wrap.find('.ls-wholesale-catalog-table');
    if (!$table.length) {
      return;
    }

    if (options.resetPage) {
      catalogState.page = 1;
    }

    var perPage = getCatalogPerPage();
    var $rows = $table.find('tbody tr.ls-wholesale-table-row');
    var $empty = $wrap.find('.ls-wholesale-catalog-no-results');
    var $pagination = $wrap.find('.ls-wholesale-catalog-pagination');
    var i18n = (window.lsWholesale && window.lsWholesale.i18n) || {};
    var q = ($('.ls-wholesale-catalog-search-input').val() || '').toLowerCase().trim();
    var matched = [];

    $rows.each(function () {
      var $row = $(this);
      var haystack = ($row.attr('data-search') || '').toLowerCase();
      var match = !q || haystack.indexOf(q) !== -1;
      $row.toggleClass('is-search-hidden', !match);
      if (match) {
        matched.push($row);
      }
    });

    var total = matched.length;
    var totalPages = Math.max(1, Math.ceil(total / perPage));

    if (catalogState.page > totalPages) {
      catalogState.page = totalPages;
    }
    if (catalogState.page < 1) {
      catalogState.page = 1;
    }

    var start = (catalogState.page - 1) * perPage;
    var end = start + perPage;

    matched.forEach(function ($row, index) {
      var onPage = index >= start && index < end;
      $row.toggleClass('is-page-hidden', !onPage);
    });

    $empty.prop('hidden', !q || total > 0);

    if (total <= perPage) {
      $pagination.prop('hidden', true);
      return;
    }

    var from = total === 0 ? 0 : start + 1;
    var to = Math.min(end, total);

    $pagination.prop('hidden', false);
    $pagination.find('.ls-wholesale-catalog-pagination-info').text(
      formatI18n(i18n.showing || 'Showing %1$d–%2$d of %3$d', [from, to, total])
    );
    $pagination.find('.ls-wholesale-catalog-page-status').text(
      formatI18n(i18n.pageOf || 'Page %1$d of %2$d', [catalogState.page, totalPages])
    );
    $pagination.find('.ls-wholesale-catalog-page-prev').prop('disabled', catalogState.page <= 1);
    $pagination.find('.ls-wholesale-catalog-page-next').prop('disabled', catalogState.page >= totalPages);
  }

  $(function () {
    if ($('.ls-wholesale-catalog-table').length) {
      updateCatalogView();
      updateCatalogQuantityMinimums();
    }
  });

  $(document).on('input', '.ls-wholesale-catalog-search-input', function () {
    updateCatalogView({ resetPage: true });
  });

  $(document).on('click', '.ls-wholesale-catalog-page-prev', function () {
    if (catalogState.page <= 1) {
      return;
    }
    catalogState.page -= 1;
    updateCatalogView();
    scrollToCatalogTable();
  });

  $(document).on('click', '.ls-wholesale-catalog-page-next', function () {
    catalogState.page += 1;
    updateCatalogView();
    scrollToCatalogTable();
  });

  $(document).on('input change', '.ls-wholesale-qty', function () {
    var $input = $(this);
    var minimum = getMinOrderQuantity();
    if (!minimum) {
      return;
    }

    var current = getCartWholesaleQty();
    var qtyMin = 1;
    if (current < minimum) {
      qtyMin = Math.max(1, minimum - current);
    }

    var value = parseInt($input.val(), 10) || qtyMin;
    if (value < qtyMin) {
      $input.val(qtyMin);
    }
  });

  $(document).on('click', '.ls-wholesale-add-to-cart', function () {
    var $btn = $(this);
    var sku = $btn.data('sku');
    var $wrap = $btn.closest('.ls-wholesale-table-row');
    var qty = parseInt($wrap.find('.ls-wholesale-qty').val(), 10) || 1;
    var minimumError = getAddToCartMinimumError(qty);

    if (!sku || !window.lsWholesale) {
      return;
    }

    if (minimumError) {
      showToast(minimumError, true);
      return;
    }

    $btn.prop('disabled', true).text(window.lsWholesale.i18n.adding || 'Adding…');

    $.post(window.lsWholesale.ajaxUrl, {
      action: 'ls_wholesale_add_to_cart',
      nonce: window.lsWholesale.nonce,
      sku: sku,
      quantity: qty
    })
      .done(function (response) {
        if (response && response.success) {
          showToast(response.data.message || 'Added to cart');
          if (typeof response.data.cart_wholesale_qty !== 'undefined') {
            window.lsWholesale.cartWholesaleQty = response.data.cart_wholesale_qty;
            updateCatalogQuantityMinimums();
          }
          if (typeof response.data.cart_count !== 'undefined') {
            updateViewCartCount(response.data.cart_count);
          }
          if (response.data.fragments) {
            refreshCartFragments(response.data.fragments, response.data.cart_hash);
          } else {
            $(document.body).trigger('wc_fragment_refresh');
          }
        } else {
          showToast((response && response.data && response.data.message) || 'Failed', true);
        }
      })
      .fail(function (xhr) {
        var message = 'Request failed';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          message = xhr.responseJSON.data.message;
        }
        showToast(message, true);
      })
      .always(function () {
        $btn.prop('disabled', false).text(window.lsWholesale.i18n.addToCart || 'Add');
      });
  });

  function showApplyThanks($card, title, message) {
    var $notice = $('<div class="ls-wholesale-card ls-wholesale-notice ls-wholesale-notice-success"></div>');
    $('<h2></h2>').text(title || '').appendTo($notice);
    $('<p class="ls-wholesale-lead"></p>').text(message || '').appendTo($notice);
    $card.empty().append($notice);
  }

  $(document).on('submit', '#ls-wholesale-apply-form', function (event) {
    var $form = $(this);
    var config = window.lsWholesaleApply;

    if (!config) {
      return;
    }

    event.preventDefault();

    var $btn = $form.find('button[type="submit"]');
    var $card = $('#ls-wholesale-apply-card');
    var submitLabel = config.i18n.submit || 'Submit application';
    var submittingLabel = config.i18n.submitting || 'Submitting…';

    $btn.prop('disabled', true).text(submittingLabel);

    $.post(config.ajaxUrl, {
      action: 'ls_wholesale_submit_application',
      nonce: config.nonce,
      company_name: $form.find('[name="company_name"]').val(),
      business_email: $form.find('[name="business_email"]').val(),
      phone: $form.find('[name="phone"]').val(),
      messenger_link: $form.find('[name="messenger_link"]').val(),
      website: $form.find('[name="website"]').val(),
      message: $form.find('[name="message"]').val(),
      apply_page_url: $form.find('[name="apply_page_url"]').val() || config.successUrl
    })
      .done(function (response) {
        if (response && response.success) {
          var title = (response.data && response.data.title) || config.i18n.thanksTitle;
          var message = (response.data && response.data.message) || config.i18n.thanksMessage;
          var redirectUrl = (response.data && response.data.redirect_url) || config.successUrl;

          showApplyThanks($card, title, message);

          try {
            var url = new URL(redirectUrl, window.location.origin);
            window.history.replaceState(null, '', url.toString());
          } catch (e) {
            window.history.replaceState(null, '', redirectUrl);
          }

          if ($card.length && $card.offset()) {
            $('html, body').animate({ scrollTop: $card.offset().top - 40 }, 250);
          }
        } else {
          showToast((response && response.data && response.data.message) || config.i18n.error, true);
        }
      })
      .fail(function (xhr) {
        var message = config.i18n.error;
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          message = xhr.responseJSON.data.message;
        }
        showToast(message, true);
      })
      .always(function () {
        $btn.prop('disabled', false).text(submitLabel);
      });
  });
})(jQuery);
