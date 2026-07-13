(function($){

  function T(key, fallback) {
    return t(key, fallback);
  }

  // i18n helper with fallbacks
  function t(key, fallback){
    try { return (LSMyKeys.i18n && LSMyKeys.i18n[key]) || fallback; }
    catch(e) { return fallback; }
  }

  // ===== VIEW KEYS (cached) =====
  $(document).on('click', '.ls-btn-view-key', function(e){
    e.preventDefault();
    var $btn = $(this);
    var data = {
      action: 'licensesender_view_key',
      nonce: $btn.data('nonce') || LSMyKeys.viewNonce,
      order_id: $btn.data('order-id'),
      product_id: $btn.data('product-id')
    };

    var oldText = $btn.text();
    $btn.prop('disabled', true).text(t('viewing','Loading key…'));

    $.post(LSMyKeys.ajaxUrl, data)
      .done(function(res){
        if (res && res.success && res.data) {
          showKeysModal(res.data, $btn.data('product-name'));
        } else {
          var msg  = (res && res.data && res.data.message) ? res.data.message : t('error','Unable to fetch key. Please try again.');
          var code = (res && res.data && res.data.code) ? res.data.code : '';
          if (code === 'not_found') {
            Swal.fire({
              icon: 'question',
              title: t('noSavedTitle','No Saved Keys'),
              text: t('noSaved','No saved keys found. Fetch new keys?'),
              showCancelButton: true,
              confirmButtonText: t('fetchNow','Get Keys'),
              cancelButtonText: t('close','Close')
            }).then(function(r){
              if (r.isConfirmed) {
                // Trigger Get Keys button in the same card/row if present
                var $get = $btn.closest('.ls-card, tr').find('.ls-btn-get-key');
                if ($get.length) $get.trigger('click');
              }
            });
          } else {
            Swal.fire({ icon:'error', title:'Error', text: msg });
          }
        }
      })
      .fail(function(){
        Swal.fire({ icon:'error', title:'Error', text: t('error','Unable to fetch key. Please try again.') });
      })
      .always(function(){
        $btn.prop('disabled', false).text(oldText);
      });
  });

  // ===== GET KEYS (fresh) with confirmation =====
  $(document).on('click', '.ls-btn-get-key', function (e) {
     e.preventDefault();
    var $btn  = $(this);
    var qty   = parseInt($btn.data('qty'), 10) || 1;
    var email = ($btn.data('email') || '').toString();
    var pn    = ($btn.data('product-name') || '').toString();

    var data  = {
      action:     'licensesender_get_key',
      nonce:      $btn.data('nonce') || (window.LSMyKeys && LSMyKeys.nonce),
      order_id:   $btn.data('order-id'),
      product_id: $btn.data('product-id'),
      qnty:       qty,
      email:      email
    };

    var F = function(path, fallback){
      try{
        var parts = path.split('.');
        var cur = window;
        for (var i=0;i<parts.length;i++){ cur = cur[parts[i]]; if (cur == null) return fallback; }
        return cur || fallback;
      }catch(e){ return fallback; }
    };

    var defTitle = 'Get license keys?';
    var defText  = 'We will fetch your license keys. Continue?';

    var title = F('LSMyKeys.i18n.confirmTitle', defTitle);
    var textTmpl = F('LSMyKeys.i18n.confirmText', defText);
    var text = textTmpl;

    if (/\{product\}/.test(textTmpl)) {
      text = textTmpl.replace(/\{product\}/g, pn || '');
    } else if (pn) {
      // If no placeholder but product is known, append it neatly.
      text = textTmpl.replace(/\s+$/, '') + ' ' + pn;
    }

    Swal.fire({
      icon: 'question',
      title: title,
      text: text,
      showCancelButton: true,
      confirmButtonText: t('confirmBtn', 'Yes, get keys'),
      cancelButtonText: t('cancelBtn', 'Cancel'),
      confirmButtonColor: t('confirmColor', '#4f46e5'),
      cancelButtonColor: t('cancelColor', '#6b7280'),
      reverseButtons: true
    }).then(function(result){
      if (!result.isConfirmed) return;

      // Loader modal while request is in-flight
      Swal.fire({
        title: T('loading','Fetching key…'),
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function(){ Swal.showLoading(); }
      });

      var oldText = $btn.text();
      $btn.prop('disabled', true).text(T('loading','Fetching key…'));

      $.post((window.LSMyKeys && LSMyKeys.ajaxUrl) || ajaxurl, data)
        .done(function(res){
          Swal.close();

          if (res && res.success && res.data) {
            // Collect keys as array of strings
            var keys = [];
            if (Array.isArray(res.data.keys)) {
              keys = res.data.keys.map(function(k){ return String(k.key_value || k); });
            } else if (res.data.key) {
              keys = [ String(res.data.key) ];
            }

            if (keys.length) {
              // 1) Show SweetAlert keys modal (uses full payload so you keep links/meta)
              showKeysModal(res.data, pn);

              // 2) Swap to "View Keys (N)"
              var viewText = T('viewKeys','View Keys');
              var $view = $('<button/>', {
                type: 'button',
                class: 'button ls-btn-view-key',
                text: viewText
              }).attr({
                'data-order-id':     $btn.data('order-id'),
                'data-product-id':   $btn.data('product-id'),
                'data-product-name': pn,
                'data-count':        keys.length,
                'data-nonce': (window.LSMyKeys && LSMyKeys.viewNonce) ? LSMyKeys.viewNonce : ($btn.data('nonce') || '')
              });

              $btn.replaceWith($view);
              return; // success path done
            }
          }

          // If we reach here, treat as error
          var msg = (res && res.data && res.data.message)
            ? res.data.message
            : T('error','Unable to fetch key. Please try again.');
          Swal.fire({ icon:'error', title:T('errorTitle','Error'), text: msg });
        })
        .fail(function(jqXHR){
          Swal.close();
          var msg = T('error','Unable to fetch key. Please try again.');
          if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
            msg = String(jqXHR.responseJSON.data.message);
          }
          Swal.fire({ icon:'error', title:T('errorTitle','Error'), text: msg });
        })
        .always(function(){
          $btn.prop('disabled', false).text(oldText);
        });
    });
  });




  // ===== SweetAlert modal for ONE or MANY keys, with two action buttons =====
function showKeysModal(payload, productName){
  // Normalize keys
  var keys = [];
  if (payload) {
    if (Array.isArray(payload.keys) && payload.keys.length){
      keys = payload.keys.map(function(r){ return r.key_value || r.key || String(r); });
    } else if (payload.key) {
      keys = [String(payload.key)];
    }
  }
  if (!keys.length){
    Swal.fire({ icon:'info', title:t('noKeys','No keys to display'), text:t('noSaved','No saved keys found. Fetch new keys?') });
    return;
  }

  // Meta
  var meta  = payload.meta || {};
  var dl    = meta.download_link    || payload.download_link    || '';
  var guide = meta.guide_url
          || meta.activation_guide
          || payload.activation_guide
          || '';
  var email = meta.email            || payload.email            || '';

  var actions = [];
  if (dl) {
    actions.push(
      '<a class="ls-action-btn ls-download-btn" href="'+ encodeURI(dl) +'" target="_blank" rel="noopener">'
      + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0l4-4m-4 4l-4-4M5 21h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
      + '<span>' + (t('downloadSoftware','Software Download')) + '</span></a>'
    );
  }
  if (guide) {
    if (isHttpUrl(guide)) {
      actions.push(
        '<a class="ls-action-btn ls-guide-btn" href="'+ encodeURI(guide) +'" target="_blank" rel="noopener">'
        + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
        + '<span>' + t('activationGuide','Software Activation Guide') + '</span></a>'
      );
    } else {
      actions.push(
        '<button type="button" class="ls-action-btn ls-guide-btn ls-open-guide" data-guide="'+ escAttr(guide) +'">'
        + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>'
        + '<span>' + t('activationGuide','Software Activation Guide') + '</span></button>'
      );
    }
  }

  var listHtml = keys.map(function(k, index){
    return '' +
      '<li class="ls-keys-item">' +
      '  <div class="ls-key-block">' +
      '    <span class="ls-key-index" aria-hidden="true">' + (index + 1) + '</span>' +
      '    <code class="ls-key-code">' + esc(k) + '</code>' +
      '    <button type="button" class="ls-key-copy" data-key="' + escAttr(k) + '" aria-label="' + t('copy','Copy') + '" title="' + t('copy','Copy') + '">' +
      '      <svg class="ls-key-copy__icon ls-key-copy__icon--copy" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">' +
      '        <path d="M8 8V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2M6 8h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
      '      </svg>' +
      '      <svg class="ls-key-copy__icon ls-key-copy__icon--check" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">' +
      '        <path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
      '      </svg>' +
      '      <span class="ls-key-copy__label ls-key-copy__label--idle">' + t('copy','Copy') + '</span>' +
      '      <span class="ls-key-copy__label ls-key-copy__label--done">' + t('copied','Copied') + '</span>' +
      '    </button>' +
      '  </div>' +
      '</li>';
  }).join('');

  var titleText = keys.length > 1
    ? t('viewTitleMany', 'Your License Keys')
    : t('viewTitle', 'Your License Key');

  var html = ''
    + '<div class="ls-keys-sheet">'
    +   '<div class="ls-keys-sheet__hero">'
    +     '<div class="ls-keys-sheet__mark" aria-hidden="true">'
    +       '<svg width="22" height="22" viewBox="0 0 24 24" fill="none">'
    +         '<path d="M15 7a4 4 0 1 1-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
    +         '<path d="M11 11l-7.5 7.5M6 15l2 2M8 13l2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>'
    +       '</svg>'
    +     '</div>'
    +     '<div class="ls-keys-sheet__intro">'
    +       '<p class="ls-keys-sheet__eyebrow">' + esc(titleText) + '</p>'
    +       (productName ? '<h3 class="ls-keys-sheet__product">' + esc(productName) + '</h3>' : '')
    +       '<div class="ls-keys-sheet__meta">'
    +         '<span class="ls-keys-sheet__chip">' + keys.length + ' ' + (keys.length > 1 ? t('keysLabel','keys') : t('keyLabel','key')) + '</span>'
    +         (email ? '<span class="ls-keys-sheet__chip ls-keys-sheet__chip--muted">' + esc(email) + '</span>' : '')
    +       '</div>'
    +     '</div>'
    +   '</div>'
    +   '<div class="ls-keys-scroll"><ul class="ls-keys-list">' + listHtml + '</ul></div>'
    +   (actions.length ? ('<div class="ls-key-actions">' + actions.join('') + '</div>') : '')
    + '</div>';

  Swal.fire({
    title: '',
    html: html,
    showCancelButton: true,
    confirmButtonText: t('copyAll', 'Copy All'),
    cancelButtonText: t('close', 'Close'),
    reverseButtons: true,
    focusConfirm: false,
    buttonsStyling: false,
    customClass: {
      popup: 'ls-keys-modal',
      htmlContainer: 'ls-keys-modal__body',
      actions: 'ls-keys-modal__footer',
      confirmButton: 'ls-keys-modal__btn ls-keys-modal__btn--primary',
      cancelButton: 'ls-keys-modal__btn ls-keys-modal__btn--ghost'
    }
  }).then(function(result){
    if (result.isConfirmed) {
      navigator.clipboard.writeText(keys.join('\n')).then(function(){
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: t('keysCopied','Keys copied to clipboard.'),
          showConfirmButton: false,
          timer: 1600,
          timerProgressBar: true,
          customClass: { popup: 'ls-toast' }
        });
      });
    }
  });
}

// If guide is *text*, open a secondary modal with that content
$(document).on('click', '.ls-open-guide', function(){
  var guide = $(this).data('guide');
  if (!guide) return;
  Swal.fire({
    title: t('activationGuide','Software Activation Guide'),
    html: '<div class="ls-guide-content">' + esc(guide) + '</div>',
    confirmButtonText: t('close','Close'),
    buttonsStyling: false,
    customClass: {
      popup: 'ls-keys-modal ls-guide-modal',
      confirmButton: 'ls-keys-modal__btn ls-keys-modal__btn--primary'
    }
  });
});

  // Copy a single key from the SweetAlert content
  $(document).on('click', '.ls-key-copy', function(){
    var $btn = $(this);
    var key = $btn.data('key');
    if (!key) return;
    navigator.clipboard.writeText(String(key)).then(function(){
      $btn.addClass('is-copied').attr('aria-label', t('copied','Copied'));
      window.setTimeout(function(){
        $btn.removeClass('is-copied').attr('aria-label', t('copy','Copy'));
      }, 1400);
    });
  });


  // Simple escapers
  function esc(s){ return String(s).replace(/[&<>"']/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]); }); }
  function escAttr(s){ return String(s).replace(/"/g, '&quot;'); }
  // URL checker (simple and robust for http/https)
  function isHttpUrl(s){
    return /^https?:\/\//i.test(String(s || ''));
  }

})(jQuery);
