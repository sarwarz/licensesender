(function ($) {
  'use strict';

  if (typeof window.LSSupport === 'undefined') {
    return;
  }

  const cfg = window.LSSupport;
  const perPage = cfg.perPage || 20;

  function setMessage($el, type, text) {
    $el.removeClass('is-error is-success').addClass(type ? 'is-' + type : '').text(text || '');
  }

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
  }

  function ticketUrl(number) {
    if (cfg.ticketUrlTemplate) {
      return cfg.ticketUrlTemplate.replace('__TICKET__', encodeURIComponent(number));
    }

    const base = cfg.managePageUrl || window.location.href.split('?')[0];
    const sep = base.indexOf('?') >= 0 ? '&' : '?';
    return base + sep + 'ls_ticket=' + encodeURIComponent(number);
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleString();
  }

  function parseDateValue(value) {
    if (!value) {
      return null;
    }

    const direct = new Date(value);
    if (!Number.isNaN(direct.getTime())) {
      return direct;
    }

    const normalized = new Date(String(value).replace(' ', 'T'));
    if (!Number.isNaN(normalized.getTime())) {
      return normalized;
    }

    return null;
  }

  function formatRelativeTime(value) {
    if (!value) {
      return '—';
    }
    const date = parseDateValue(value);
    if (!date) {
      return value;
    }

    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    const abs = Math.abs(seconds);
    const future = seconds < 0;
    const suffix = future ? cfg.i18n.fromNowFuture : cfg.i18n.fromNowPast;

    let amount = abs;
    let unit = cfg.i18n.seconds;

    if (abs >= 86400) {
      amount = Math.floor(abs / 86400);
      unit = amount === 1 ? cfg.i18n.day : cfg.i18n.days;
    } else if (abs >= 3600) {
      amount = Math.floor(abs / 3600);
      unit = amount === 1 ? cfg.i18n.hour : cfg.i18n.hours;
    } else if (abs >= 60) {
      amount = Math.floor(abs / 60);
      unit = amount === 1 ? cfg.i18n.minute : cfg.i18n.minutes;
    }

    return suffix.replace('%d', amount).replace('%s', unit);
  }

  function postForm(action, formData) {
    formData.append('action', action);
    formData.append('nonce', cfg.nonce);

    return $.ajax({
      url: cfg.ajaxUrl,
      method: 'POST',
      data: formData,
      processData: false,
      contentType: false,
    });
  }

  function postJson(action, data) {
    return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data || {}));
  }

  function canSelect2() {
    return typeof $.fn.select2 === 'function';
  }

  function initSupportSelect2($el) {
    if (!$el.length || !canSelect2()) {
      return;
    }

    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }

    // Append dropdown to <body> so theme overflow/transform on cards
    // cannot detach or clip the menu (common cross-theme Select2 bug).
    $el.select2({
      width: '100%',
      dropdownParent: $(document.body),
      dropdownCssClass: 'ls-support-select2-dropdown',
      containerCssClass: 'ls-support-select2-container',
      placeholder: $el.data('placeholder') || '',
      // Clear "x" only on optional pickers that have an empty placeholder option.
      allowClear: $el.is('#ls-support-order, #ls-support-license-key'),
      minimumResultsForSearch: $el.is('#ls-support-order') ? 0 : 8,
    });
  }

  function setSelectHtml($el, html, disabled) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.html(html).prop('disabled', !!disabled);
    if ($el.hasClass('ls-support-select2')) {
      initSupportSelect2($el);
    }
  }

  function initOpenForm() {
    const $form = $('#ls-support-open-form');
    if (!$form.length) {
      return;
    }

    const $message = $form.find('.ls-support-form-message');
    const $order = $('#ls-support-order');
    const $key = $('#ls-support-license-key');
    const defaultAttachLabel = cfg.i18n.noFilesSelected || 'No files selected';

    $form.find('select.ls-support-select2').each(function () {
      initSupportSelect2($(this));
    });
    initWpEditor(OPEN_EDITOR_ID, 240);

    $form.on('click', '.ls-support-attach-link', function () {
      $form.find('.ls-support-file-input').trigger('click');
    });

    $form.on('change', '.ls-support-file-input', function () {
      const names = Array.from(this.files || []).map(function (file) {
        return file.name;
      }).join(', ');
      $form.find('.ls-support-attach-names').text(names || defaultAttachLabel);
    });

    $order.on('change', function () {
      const orderId = $(this).val();
      setSelectHtml($key, '<option value="">' + cfg.i18n.loadingKeys + '</option>', true);

      if (!orderId) {
        setSelectHtml($key, '<option value="">' + cfg.i18n.selectOrderFirst + '</option>', true);
        return;
      }

      postJson('ls_support_order_keys', { order_id: orderId })
        .done(function (res) {
          if (!res.success) {
            setSelectHtml($key, '<option value="">' + cfg.i18n.noKeys + '</option>', true);
            return;
          }

          const keys = res.data.keys || [];
          if (!keys.length) {
            setSelectHtml($key, '<option value="">' + cfg.i18n.noKeys + '</option>', true);
            return;
          }

          let html = '<option value="">' + cfg.i18n.selectKey + '</option>';
          keys.forEach(function (item) {
            const label = item.sku ? item.key + ' (' + item.sku + ')' : item.key;
            html += '<option value="' + escapeHtml(item.key) + '">' + escapeHtml(label) + '</option>';
          });
          setSelectHtml($key, html, false);
        })
        .fail(function () {
          setSelectHtml($key, '<option value="">' + cfg.i18n.noKeys + '</option>', true);
        });
    });

    $form.on('submit', function (event) {
      event.preventDefault();
      syncWpEditor(OPEN_EDITOR_ID);

      const $btn = $form.find('[type="submit"]');
      const formData = new FormData(this);
      const message = String(formData.get('message') || '');

      if (isReplyMessageEmpty(message)) {
        setMessage($message, 'error', cfg.i18n.descriptionRequired || cfg.i18n.replyRequired || cfg.i18n.error);
        return;
      }

      $btn.prop('disabled', true);
      setMessage($message, '', cfg.i18n.submitting);

      postForm('ls_support_create_ticket', formData)
        .done(function (res) {
          if (!res.success) {
            setMessage($message, 'error', (res.data && res.data.message) || cfg.i18n.error);
            return;
          }

          setMessage($message, 'success', res.data.message || cfg.i18n.created);
          const redirectUrl = (res.data && res.data.redirect_url) || (res.data && res.data.ticket_number ? ticketUrl(res.data.ticket_number) : '') || cfg.managePageUrl || '';
          if (redirectUrl) {
            window.setTimeout(function () {
              window.location.href = redirectUrl;
            }, 600);
          }
        })
        .fail(function () {
          setMessage($message, 'error', cfg.i18n.error);
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  }

  function isCustomerMessage(message) {
    const author = String(message.author_type || message.sender_type || message.role || '').toLowerCase();
    return author.indexOf('customer') !== -1 || author.indexOf('user') !== -1;
  }

  function getAvatarInitials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) {
      return '?';
    }
    if (parts.length === 1) {
      return parts[0].slice(0, 2).toUpperCase();
    }
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }

  function getPlainText(html) {
    return $('<div>').html(html).text().replace(/\s+/g, ' ').trim();
  }

  const OPEN_EDITOR_ID = 'ls-support-message';
  const REPLY_EDITOR_ID = 'ls-support-reply-message';

  function destroyWpEditor(editorId) {
    if (typeof wp === 'undefined' || !wp.editor || !wp.editor.remove) {
      return;
    }

    if ($('#' + editorId).length) {
      wp.editor.remove(editorId);
    }
  }

  function initWpEditor(editorId, height) {
    const $textarea = $('#' + editorId);
    if (!$textarea.length || typeof wp === 'undefined' || !wp.editor || !wp.editor.initialize) {
      return;
    }

    destroyWpEditor(editorId);

    wp.editor.initialize(editorId, {
      tinymce: {
        wpautop: true,
        toolbar1: 'bold,italic,underline,bullist,numlist,link,unlink',
        toolbar2: '',
        height: height || 220,
      },
      quicktags: false,
      mediaButtons: false,
    });
  }

  function syncWpEditor(editorId) {
    if (typeof wp !== 'undefined' && wp.editor && wp.editor.save) {
      wp.editor.save(editorId);
    }
  }

  function destroyReplyEditor() {
    destroyWpEditor(REPLY_EDITOR_ID);
  }

  function initReplyEditor() {
    initWpEditor(REPLY_EDITOR_ID, 180);
  }

  function syncReplyEditor() {
    syncWpEditor(REPLY_EDITOR_ID);
  }

  function isReplyMessageEmpty(value) {
    return getPlainText(value || '').length === 0;
  }

  function renderActivityFeed(messages, customerName) {
    if (!messages || !messages.length) {
      return '<div class="ls-support-activity-empty">' + cfg.i18n.noMessages + '</div>';
    }

    const chronological = messages.slice().sort(function (a, b) {
      return new Date(a.created_at || a.sent_at || 0) - new Date(b.created_at || b.sent_at || 0);
    });

    let firstCustomerAt = -1;
    chronological.forEach(function (message, index) {
      if (firstCustomerAt === -1 && isCustomerMessage(message)) {
        firstCustomerAt = index;
      }
    });

    const firstCustomerCreatedAt = firstCustomerAt >= 0
      ? String(chronological[firstCustomerAt].created_at || chronological[firstCustomerAt].sent_at || '')
      : '';

    let html = '<div class="ls-support-activity-list">';
    messages.slice().reverse().forEach(function (message) {
      const isCustomer = isCustomerMessage(message);
      const bodyHtml = message.body_html || escapeHtml(message.message || message.body || message.content || '').replace(/\n/g, '<br>');
      const when = formatRelativeTime(message.created_at || message.sent_at || '');
      const authorLabel = isCustomer
        ? (message.author_name || customerName || cfg.i18n.you)
        : (message.author_name || cfg.i18n.supportName);
      const messageCreatedAt = String(message.created_at || message.sent_at || '');
      const isInitialReport = isCustomer && firstCustomerCreatedAt !== '' && messageCreatedAt === firstCustomerCreatedAt;
      const actionLabel = isInitialReport ? cfg.i18n.reported : cfg.i18n.replied;
      const plainText = getPlainText(bodyHtml);
      const shouldTruncate = plainText.length > 280;
      const previewHtml = shouldTruncate
        ? escapeHtml(plainText.slice(0, 280).trim()) + '…'
        : bodyHtml;

      html += '<article class="ls-support-activity-item ' + (isCustomer ? 'is-customer' : 'is-staff') + '">';
      html += '<div class="ls-support-activity-avatar" aria-hidden="true"><span>' + escapeHtml(getAvatarInitials(authorLabel)) + '</span></div>';
      html += '<div class="ls-support-activity-content">';
      html += '<div class="ls-support-activity-meta">';
      html += '<div class="ls-support-activity-title">';
      html += '<strong>' + escapeHtml(authorLabel) + '</strong>';
      html += ' <em class="ls-support-activity-action">' + escapeHtml(actionLabel) + '</em>';
      html += '</div>';
      if (when) {
        html += '<time class="ls-support-activity-time">' + escapeHtml(when) + '</time>';
      }
      html += '</div>';
      html += '<div class="ls-support-activity-body">' + previewHtml + '</div>';
      if (shouldTruncate) {
        html += '<div class="ls-support-activity-body-full" hidden>' + bodyHtml + '</div>';
        html += '<button type="button" class="ls-support-view-more">' + escapeHtml(cfg.i18n.viewMore) + '</button>';
      }
      html += renderAttachments(message.attachments || []);
      html += '</div>';
      html += '</article>';
    });
    html += '</div>';

    return html;
  }

  function formatFileSize(bytes) {
    const size = Number(bytes) || 0;
    if (size >= 1048576) {
      return (size / 1048576).toFixed(1) + ' MB';
    }
    if (size >= 1024) {
      return (size / 1024).toFixed(1) + ' KB';
    }
    return size + ' B';
  }

  function renderAttachments(attachments) {
    if (!attachments || !attachments.length) {
      return '';
    }

    let html = '<ul class="ls-support-attachments">';
    attachments.forEach(function (file) {
      const name = file.name || cfg.i18n.attachmentFallback || 'Attachment';
      const url = file.download_url || file.url || '';
      const isImage = !!file.is_image || String(file.mime || '').indexOf('image/') === 0;
      const sizeLabel = formatFileSize(file.size);

      html += '<li class="ls-support-attachment' + (isImage ? ' is-image' : '') + '">';
      if (isImage && url) {
        html += '<a class="ls-support-attachment-thumb" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">';
        html += '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(name) + '" loading="lazy" />';
        html += '</a>';
      } else {
        html += '<span class="ls-support-attachment-icon" aria-hidden="true">📎</span>';
      }
      html += '<div class="ls-support-attachment-meta">';
      if (url) {
        html += '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(name) + '</a>';
      } else {
        html += '<span>' + escapeHtml(name) + '</span>';
      }
      if (sizeLabel) {
        html += '<small>' + escapeHtml(sizeLabel) + '</small>';
      }
      html += '</div>';
      html += '</li>';
    });
    html += '</ul>';
    return html;
  }

  function renderTicketSidebar(ticket) {
    const data = ticket || {};
    const orderLabel = data.order_label || (data.order_id ? '#' + data.order_id : '—');

    let html = '<div class="ls-support-sidebar-stack">';
    html += '<div class="ls-support-sidebar-card">';
    html += '<h3>' + cfg.i18n.ticketStatus + '</h3>';
    html += '<dl class="ls-support-sidebar-list">';
    html += '<div><dt>' + cfg.i18n.statusLabel + '</dt><dd><span class="ls-support-badge ls-support-badge-status ls-support-badge-status-' + escapeHtml(data.status_class || 'default') + '">' + escapeHtml(data.status_label || '—') + '</span></dd></div>';
    html += '<div><dt>' + cfg.i18n.categoryLabel + '</dt><dd>' + escapeHtml(data.category_label || '—') + '</dd></div>';
    html += '<div><dt>' + cfg.i18n.priorityLabel + '</dt><dd><span class="ls-support-badge ls-support-badge-priority ls-support-badge-priority-' + escapeHtml(data.priority_class || 'low') + '">' + escapeHtml(data.priority_label || '—') + '</span></dd></div>';
    html += '</dl></div>';

    html += '<div class="ls-support-sidebar-card">';
    html += '<h3>' + cfg.i18n.customerLabel + '</h3>';
    html += '<dl class="ls-support-sidebar-list">';
    html += '<div><dt>' + cfg.i18n.nameLabel + '</dt><dd>' + escapeHtml(data.customer_name || '—') + '</dd></div>';
    html += '</dl></div>';

    html += '<div class="ls-support-sidebar-card">';
    html += '<h3>' + cfg.i18n.ticketInfo + '</h3>';
    html += '<dl class="ls-support-sidebar-list">';
    html += '<div><dt>' + cfg.i18n.orderLabel + '</dt><dd>' + escapeHtml(orderLabel) + '</dd></div>';
    html += '<div><dt>' + cfg.i18n.updatedLabel + '</dt><dd>' + escapeHtml(formatRelativeTime(data.updated_at)) + '</dd></div>';
    html += '</dl></div>';
    html += '</div>';

    return html;
  }

  function renderTicketPage(ticketNumber, subject, ticket, messages, canReply) {
    const titleSubject = subject || cfg.i18n.untitled;

    $('#ls-support-ticket-header').html(
      '<div class="ls-support-ticket-header-inner">' +
        '<p class="ls-support-ticket-id-label">#' + escapeHtml(ticketNumber) + '</p>' +
        '<h2 class="ls-support-ticket-title">' + escapeHtml(titleSubject) + '</h2>' +
      '</div>'
    );

    let html = '';

    if (canReply) {
      html += '<form class="ls-support-reply-form ls-support-reply-form-top" id="ls-support-reply-form" enctype="multipart/form-data">';
      html += '<input type="hidden" name="ticket_number" value="' + escapeHtml(ticketNumber) + '" />';
      html += '<div class="ls-support-reply-head">';
      html += '<label for="' + REPLY_EDITOR_ID + '">' + cfg.i18n.replyLabel + '</label>';
      html += '</div>';
      html += '<div class="ls-support-field ls-support-reply-editor-field">';
      html += '<div class="ls-support-editor-wrap">';
      html += '<textarea id="' + REPLY_EDITOR_ID + '" name="message" rows="5" maxlength="10000" placeholder="' + escapeHtml(cfg.i18n.replyPlaceholder) + '"></textarea>';
      html += '</div>';
      html += '</div>';
      html += '<div class="ls-support-reply-footer">';
      html += '<div class="ls-support-attach-col">';
      html += '<div class="ls-support-attach-row">';
      html += '<input type="file" class="ls-support-file-input" id="ls-support-reply-attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.zip" />';
      html += '<button type="button" class="button ls-support-btn-secondary ls-support-attach-link">' + escapeHtml(cfg.i18n.attachFile) + '</button>';
      html += '<span class="ls-support-attach-names" aria-live="polite">' + escapeHtml(cfg.i18n.noFilesSelected || 'No files selected') + '</span>';
      html += '</div>';
      html += '</div>';
      html += '<button type="submit" class="button ls-support-btn-primary ls-support-reply-send">' + cfg.i18n.sendReply + '</button>';
      html += '</div>';
      html += '<div class="ls-support-form-message" aria-live="polite"></div>';
      html += '</form>';
    } else if (ticket && (ticket.status_class === 'closed' || ticket.status_class === 'resolved')) {
      html += '<div class="ls-support-view-only-notice">' + escapeHtml(cfg.i18n.ticketClosed) + '</div>';
    } else {
      html += '<div class="ls-support-view-only-notice">' + escapeHtml(cfg.i18n.viewOnly) + '</div>';
    }

    html += '<section class="ls-support-activity-section">';
    html += '<div class="ls-support-activity-head">';
    html += '<h3>' + cfg.i18n.activityLabel + '</h3>';
    html += '<span class="ls-support-activity-count">' + (messages ? messages.length : 0) + '</span>';
    html += '</div>';
    html += renderActivityFeed(messages, (ticket && ticket.customer_name) || '');
    html += '</section>';

    $('#ls-support-ticket-content').html(html);
    $('#ls-support-ticket-sidebar').html(renderTicketSidebar(ticket));

    if (canReply) {
      window.setTimeout(initReplyEditor, 0);
    }
  }

  function loadTicketView(ticketNumber) {
    destroyReplyEditor();
    $('#ls-support-ticket-header').html('').attr('hidden', true);
    $('#ls-support-ticket-sidebar').html('').attr('hidden', true);
    $('#ls-support-ticket-content').html(
      '<div class="ls-support-loading-panel" role="status" aria-live="polite">' +
        '<div class="ls-support-empty-state is-loading">' +
          '<span class="ls-support-loading-dots" aria-hidden="true"></span>' +
          '<p>' + escapeHtml(cfg.i18n.loadingConversation) + '</p>' +
        '</div>' +
      '</div>'
    );
    $('.ls-support-ticket-page').addClass('is-loading');

    return postJson('ls_support_get_conversation', { ticket_number: ticketNumber })
      .done(function (res) {
        $('.ls-support-ticket-page').removeClass('is-loading');
        $('#ls-support-ticket-header').removeAttr('hidden');
        $('#ls-support-ticket-sidebar').removeAttr('hidden');

        if (!res.success) {
          $('#ls-support-ticket-content').html(
            '<div class="ls-support-loading-panel">' +
              '<div class="ls-support-empty-list">' + escapeHtml((res.data && res.data.message) || cfg.i18n.error) + '</div>' +
            '</div>'
          );
          return;
        }

        renderTicketPage(
          ticketNumber,
          res.data.subject || '',
          res.data.ticket || {},
          res.data.messages || [],
          !!res.data.can_reply
        );
      })
      .fail(function () {
        $('.ls-support-ticket-page').removeClass('is-loading');
        $('#ls-support-ticket-header').removeAttr('hidden');
        $('#ls-support-ticket-sidebar').removeAttr('hidden');
        $('#ls-support-ticket-content').html(
          '<div class="ls-support-loading-panel">' +
            '<div class="ls-support-empty-list">' + escapeHtml(cfg.i18n.error) + '</div>' +
          '</div>'
        );
      });
  }

  function stripAccessTokenFromUrl() {
    if (!window.history.replaceState) {
      return;
    }

    const hasToken = window.location.search.indexOf('access_token=') !== -1
      || window.location.search.indexOf('ticket_access_token=') !== -1
      || window.location.search.indexOf('portal_token=') !== -1
      || window.location.search.indexOf('ls_support_created=') !== -1;

    if (!hasToken && window.location.search.indexOf('token=') === -1) {
      return;
    }

    const url = new URL(window.location.href);
    ['access_token', 'ticket_access_token', 'token', 'portal_token', 'ls_support_created'].forEach(function (key) {
      url.searchParams.delete(key);
    });
    window.history.replaceState({}, document.title, url.toString());
  }

  function initTicketView() {
    const $wrap = $('#ls-support-ticket-view');
    if (!$wrap.length) {
      return;
    }

    stripAccessTokenFromUrl();

    const ticketNumber = String($wrap.data('ticket') || '');
    if (!ticketNumber) {
      return;
    }

    loadTicketView(ticketNumber);

    $('#ls-support-ticket-refresh').on('click', function () {
      loadTicketView(ticketNumber);
    });

    $wrap.on('click', '.ls-support-attach-link', function () {
      $(this).closest('.ls-support-attach-col').find('.ls-support-file-input').trigger('click');
    });

    $wrap.on('change', '.ls-support-file-input', function () {
      const names = Array.from(this.files || []).map(function (file) {
        return file.name;
      }).join(', ');
      $(this).closest('.ls-support-attach-col').find('.ls-support-attach-names').text(
        names || (cfg.i18n.noFilesSelected || 'No files selected')
      );
    });

    $wrap.on('click', '.ls-support-view-more', function () {
      const $item = $(this).closest('.ls-support-activity-item');
      const $full = $item.find('.ls-support-activity-body-full');
      if ($full.length) {
        $item.find('.ls-support-activity-body').html($full.html());
        $full.remove();
      }
      $(this).remove();
    });

    $wrap.on('submit', '#ls-support-reply-form', function (event) {
      event.preventDefault();
      const $form = $(this);
      const $message = $form.find('.ls-support-form-message');
      const $btn = $form.find('[type="submit"]');

      syncReplyEditor();

      const replyText = $form.find('#' + REPLY_EDITOR_ID).val();
      if (isReplyMessageEmpty(replyText)) {
        setMessage($message, 'error', cfg.i18n.replyRequired || cfg.i18n.error);
        return;
      }

      const formData = new FormData(this);

      $btn.prop('disabled', true);
      setMessage($message, '', cfg.i18n.sendingReply);

      postForm('ls_support_reply_ticket', formData)
        .done(function (res) {
          if (!res.success) {
            setMessage($message, 'error', (res.data && res.data.message) || cfg.i18n.error);
            return;
          }

          if (res.data && res.data.reload_ticket) {
            setMessage($message, 'success', res.data.message || cfg.i18n.replySent);
            loadTicketView(ticketNumber);
            return;
          }

          setMessage($message, 'success', res.data.message || cfg.i18n.replySent);
          destroyReplyEditor();
          renderTicketPage(
            ticketNumber,
            res.data.ticket && res.data.ticket.subject ? res.data.ticket.subject : '',
            res.data.ticket || {},
            res.data.messages || [],
            !!res.data.can_reply
          );
        })
        .fail(function () {
          setMessage($message, 'error', cfg.i18n.error);
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });
  }

  function renderTicketTable(tickets) {
    const $list = $('#ls-support-ticket-list');

    if (!tickets.length) {
      let empty = '<tr><td colspan="6" class="ls-support-table-empty">';
      empty += '<div class="ls-support-empty-state">';
      empty += '<div class="ls-support-empty-icon" aria-hidden="true">';
      empty += '<svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M7 8h10M7 12h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
      empty += '</div>';
      empty += '<h3>' + escapeHtml(cfg.i18n.noTicketsTitle || cfg.i18n.noTickets) + '</h3>';
      empty += '<p>' + escapeHtml(cfg.i18n.noTicketsHint || cfg.i18n.noTickets) + '</p>';
      if (cfg.openPageUrl) {
        empty += '<a class="button ls-support-btn-primary" href="' + escapeHtml(cfg.openPageUrl) + '">' + escapeHtml(cfg.i18n.openTicketCta || 'Open a ticket') + '</a>';
      }
      empty += '</div></td></tr>';
      $list.html(empty);
      return;
    }

    let html = '';
    tickets.forEach(function (ticket) {
      const orderLabel = ticket.order_label || (ticket.order_id ? '#' + ticket.order_id : '—');
      const href = ticket.ticket_url || ticketUrl(ticket.ticket_number);
      html += '<tr class="ls-support-table-row" tabindex="0" role="link" data-href="' + escapeHtml(href) + '">';
      html += '<td class="ls-support-col-ticket">';
      html += '<div class="ls-support-ticket-cell">';
      html += '<span class="ls-support-ticket-id">#' + escapeHtml(ticket.ticket_number) + '</span>';
      html += '<span class="ls-support-ticket-subject">' + escapeHtml(ticket.subject || cfg.i18n.untitled) + '</span>';
      html += '</div>';
      html += '</td>';
      html += '<td class="ls-support-col-priority"><span class="ls-support-badge ls-support-badge-priority ls-support-badge-priority-' + escapeHtml(ticket.priority_class || 'low') + '">' + escapeHtml(ticket.priority_label || '—') + '</span></td>';
      html += '<td class="ls-support-col-category">' + escapeHtml(ticket.category_label || '—') + '</td>';
      html += '<td class="ls-support-col-status"><span class="ls-support-badge ls-support-badge-status ls-support-badge-status-' + escapeHtml(ticket.status_class || 'default') + '">' + escapeHtml(ticket.status_label || ticket.status || 'open') + '</span></td>';
      html += '<td class="ls-support-col-order">' + escapeHtml(orderLabel) + '</td>';
      html += '<td class="ls-support-col-date"><time datetime="' + escapeHtml(ticket.updated_at || '') + '">' + escapeHtml(formatRelativeTime(ticket.updated_at)) + '</time></td>';
      html += '</tr>';
    });

    $list.html(html);
  }

  function renderPagination(meta) {
    const $pagination = $('#ls-support-pagination');
    const total = meta.total || 0;
    const page = meta.page || 1;
    const totalPages = meta.total_pages || 1;
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(page * perPage, total);

    let html = '<div class="ls-support-pagination-inner">';
    html += '<span class="ls-support-page-count">' + cfg.i18n.ticketRange.replace('%1', start).replace('%2', end).replace('%3', total) + '</span>';
    html += '<div class="ls-support-page-controls">';
    html += '<button type="button" class="ls-support-page-nav" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="' + escapeHtml(cfg.i18n.prevPage) + '">&lsaquo;</button>';
    html += '<span class="ls-support-page-indicator">' + page + ' / ' + Math.max(totalPages, 1) + '</span>';
    html += '<button type="button" class="ls-support-page-nav" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + ' aria-label="' + escapeHtml(cfg.i18n.nextPage) + '">&rsaquo;</button>';
    html += '</div>';
    html += '</div>';

    $pagination.html(html);
  }

  function initManage() {
    const $wrap = $('#ls-support-manage');
    if (!$wrap.length) {
      return;
    }

    const state = {
      search: '',
      status: '',
      sort_by: 'updated_at',
      sort_order: 'desc',
      page: 1,
    };

    function readFiltersFromForm() {
      state.search = String($('#ls-support-search').val() || '').trim();
      state.status = String($('#ls-support-filter-status').val() || '');
      state.sort_by = String($('#ls-support-filter-sort').val() || 'updated_at');
    }

    function applyFilters() {
      readFiltersFromForm();
      loadTickets(1);
    }

    function loadTickets(page) {
      if (typeof page === 'number') {
        state.page = page;
      }

      const $list = $('#ls-support-ticket-list');
      $list.html(
        '<tr><td colspan="6" class="ls-support-table-empty">' +
        '<div class="ls-support-empty-state is-loading"><span class="ls-support-loading-dots" aria-hidden="true"></span><p>' +
        cfg.i18n.loadingTickets +
        '</p></div></td></tr>'
      );

      return postJson('ls_support_list_tickets', {
        search: state.search,
        status: state.status,
        sort_by: state.sort_by,
        sort_order: state.sort_order,
        page: state.page,
        per_page: perPage,
      }).done(function (res) {
        if (!res.success) {
          $list.html(
            '<tr><td colspan="6" class="ls-support-table-empty">' +
            '<div class="ls-support-empty-state"><p>' +
            escapeHtml((res.data && res.data.message) || cfg.i18n.error) +
            '</p></div></td></tr>'
          );
          renderPagination({ total: 0, page: 1, total_pages: 1 });
          return;
        }

        const data = res.data || {};
        renderTicketTable(data.tickets || []);
        renderPagination(data);
        state.page = data.page || state.page;
      }).fail(function () {
        $list.html(
          '<tr><td colspan="6" class="ls-support-table-empty">' +
          '<div class="ls-support-empty-state"><p>' + cfg.i18n.error + '</p></div></td></tr>'
        );
        renderPagination({ total: 0, page: 1, total_pages: 1 });
      });
    }

    function goToTicket($row) {
      const href = String($row.data('href') || '');
      if (href) {
        window.location.href = href;
      }
    }

    loadTickets(1);

    $('#ls-support-apply-filters').on('click', applyFilters);

    $('#ls-support-search').on('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyFilters();
      }
    });

    $('#ls-support-filter-status, #ls-support-filter-sort').on('change', applyFilters);

    $('#ls-support-refresh').on('click', function () {
      loadTickets(state.page);
    });

    $wrap.on('click', '.ls-support-page-link, .ls-support-page-nav', function () {
      const $btn = $(this);
      if ($btn.prop('disabled')) {
        return;
      }
      loadTickets(parseInt($btn.data('page'), 10) || 1);
    });

    $wrap.on('click', '.ls-support-table-row', function () {
      goToTicket($(this));
    });

    $wrap.on('keydown', '.ls-support-table-row', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        goToTicket($(this));
      }
    });
  }

  $(function () {
    initOpenForm();
    initManage();
    initTicketView();
  });
})(jQuery);
