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
      action: 'licenseshipper_view_key',
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
      action:     'licenseshipper_get_key',
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
      + ' ' + (t('downloadSoftware','Software Download')) + '</a>'
    );
  }
  if (guide) {
    if (isHttpUrl(guide)) {
      actions.push(
        '<a class="ls-action-btn ls-guide-btn" href="'+ encodeURI(guide) +'" target="_blank" rel="noopener">'
        + ' ' + t('activationGuide','Software Activation Guide') + '</a>'
      );
    } else {
      actions.push(
        '<button type="button" class="ls-action-btn ls-guide-btn ls-open-guide" data-guide="'+ escAttr(guide) +'">'
        + ' ' + t('activationGuide','Software Activation Guide') + '</button>'
      );
    }
  }

  // Keys list markup (scrollable container) with copy icon merged into code
  var listHtml = keys.map(function(k){
    return '' +
      '<li class="ls-keys-item">' +
      '  <div class="ls-key-block">' +
      '    <code class="ls-key-code">' + esc(k) + '</code>' +
      '    <button type="button" class="ls-key-copy" data-key="' + escAttr(k) + '" aria-label="' + t('copy','Copy') + '" title="' + t('copy','Copy') + '">' +
      '      <svg class="ls-key-copy__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
      '        <path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H10V7h9v14z" fill="currentColor"/>' +
      '      </svg>' +
      '    </button>' +
      '  </div>' +
      '</li>';
  }).join('');


  // Use a scroll wrapper around the UL
  var html = '<div style="text-align:left;">'
           +   (productName ? '<div style="font-weight:600; margin-bottom:6px;">'+ esc(productName) +'</div>' : '')
           +   (email ? ('<div style="margin-bottom:10px;font-size:13px;color:#6b7280;">'
           +       (email ? '<div><strong>Email:</strong> ' + esc(email) + '</div>' : '')
           +     '</div>') : '')
           +   '<div class="ls-keys-scroll"><ul class="ls-keys-list">' + listHtml + '</ul></div>'
           +   (actions.length ? ('<div class="ls-key-actions">' + actions.join('') + '</div>') : '')
           + '</div>';


  Swal.fire({
    title: (keys.length > 1 ? t('viewTitleMany', 'Your License Keys') : t('viewTitle', 'Your License Key')),
    html: html,
    icon: 'success',
    showCancelButton: true,
    confirmButtonText: t('copyAll', 'Copy All'),
    cancelButtonText: t('close', 'Close'),
    customClass: { popup: 'ls-keys-modal' } 
  }).then(function(result){
    if (result.isConfirmed) {
      navigator.clipboard.writeText(keys.join('\n')).then(function(){
        Swal.fire({ icon:'success', title:t('copied','Copied!'), text:t('keysCopied','Keys copied to clipboard.'), timer: 1400, showConfirmButton:false });
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
    html: '<div style="text-align:left; white-space:pre-wrap;">' + esc(guide) + '</div>',
    icon: 'info',
    confirmButtonText: t('close','Close')
  });
});

  // Copy a single key from the SweetAlert content
  $(document).on('click', '.ls-key-copy', function(){
    var key = $(this).data('key');
    if (!key) return;
    navigator.clipboard.writeText(String(key)).then(function(){
      Swal.fire({ icon:'success', title:t('copied','Copied!'), text:t('keyCopied','License key copied to clipboard.'), timer: 1200, showConfirmButton:false });
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
