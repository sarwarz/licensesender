(function () {
  'use strict';

  if (typeof window.LSChat === 'undefined') {
    return;
  }

  var cfg = window.LSChat;
  var state = {
    open: false,
    started: false,
    sessionId: null,
    lastMessageId: 0,
    pollTimer: null,
    busy: false,
    typingEl: null,
    menuOpen: false,
  };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === 'string') node.textContent = text;
    return node;
  }

  function svg(pathD, viewBox) {
    var ns = 'http://www.w3.org/2000/svg';
    var s = document.createElementNS(ns, 'svg');
    s.setAttribute('viewBox', viewBox || '0 0 24 24');
    s.setAttribute('width', '18');
    s.setAttribute('height', '18');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    var p = document.createElementNS(ns, 'path');
    p.setAttribute('d', pathD);
    p.setAttribute('fill', 'currentColor');
    s.appendChild(p);
    return s;
  }

  function iconChar(char, className) {
    var span = el('span', className || 'ls-chat__icon-char', char);
    span.setAttribute('aria-hidden', 'true');
    return span;
  }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce);
    Object.keys(data || {}).forEach(function (key) {
      if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
        body.append(key, data[key]);
      }
    });

    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!json || !json.success) {
          var msg =
            (json && json.data && json.data.message) ||
            (json && json.message) ||
            cfg.i18n.error;
          throw new Error(msg);
        }
        return json.data || {};
      });
    });
  }

  function setStatus(node, message, kind) {
    node.textContent = message || '';
    node.classList.remove('is-error', 'is-ok');
    if (kind) node.classList.add(kind);
  }

  function agentAvatarMark() {
    return svg(
      'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v1.2c0 .7.5 1.2 1.2 1.2h16.8c.7 0 1.2-.5 1.2-1.2v-1.2c0-3.2-6.4-4.8-9.6-4.8z'
    );
  }

  function appendMessage(container, message, opts) {
    opts = opts || {};
    if (!message) return;
    var id = message.id;
    if (id !== undefined && id !== null && id !== '' && String(id) !== '0') {
      if (container.querySelector('[data-id="' + id + '"]')) return;
    }

    var role = message.role || 'assistant';
    var row = el('div', 'ls-chat__row ls-chat__row--' + role);
    if (id !== undefined && id !== null && id !== '') {
      row.setAttribute('data-id', String(id));
    }

    if (role !== 'user') {
      var av = el('div', 'ls-chat__msg-avatar');
      av.appendChild(agentAvatarMark());
      row.appendChild(av);
    }

    var stack = el('div', 'ls-chat__stack');
    if (opts.label) {
      stack.appendChild(el('div', 'ls-chat__meta', opts.label));
    }
    var bubble = el('div', 'ls-chat__bubble ls-chat__bubble--' + role);
    bubble.textContent = message.body || '';
    stack.appendChild(bubble);
    row.appendChild(stack);

    container.appendChild(row);
    if (state.typingEl && state.typingEl.parentNode === container) {
      container.appendChild(state.typingEl);
    }
    container.scrollTop = container.scrollHeight;

    var numericId = Number(id);
    if (!isNaN(numericId) && numericId > state.lastMessageId) {
      state.lastMessageId = numericId;
    }
  }

  function renderMessages(container, messages) {
    (messages || []).forEach(function (m) {
      var label =
        m.role === 'user'
          ? cfg.i18n.you || 'You'
          : m.role === 'agent'
            ? cfg.i18n.agent || 'Support'
            : cfg.i18n.assistant || 'Assistant';
      appendMessage(container, m, { label: label });
    });
  }

  function stopPoll() {
    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }
  }

  function startPoll(feed) {
    stopPoll();
    state.pollTimer = setInterval(function () {
      if (!state.started || state.busy) return;
      post('ls_chat_poll', { since_id: state.lastMessageId })
        .then(function (data) {
          renderMessages(feed, data.messages || []);
        })
        .catch(function () {});
    }, cfg.pollInterval || 4000);
  }

  function build() {
    var root = document.getElementById('ls-chat-root');
    if (!root) return;

    if (cfg.brandColor) {
      root.style.setProperty('--ls-chat-brand', cfg.brandColor);
    }

    var I = {
      chat: 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.2L4 17.2V4h16v12z',
      back: 'M15.4 4.6a1 1 0 0 1 0 1.4L10.4 11l5 5a1 1 0 1 1-1.4 1.4l-5.7-5.7a1 1 0 0 1 0-1.4L14 4.6a1 1 0 0 1 1.4 0z',
      menu: 'M4 7h16v2H4V7zm0 5h16v2H4v-2zm0 5h16v2H4v-2z',
      send: 'M3.4 20.6 21 12 3.4 3.4 3.3 10l11 2-11 2 .1 6.6z',
      human: 'M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 4v2h16v-2c0-2-4-4-8-4z',
      end: 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm3.3 12.1-1.2 1.2L12 13.4l-2.1 2-1.2-1.3 2.1-2-2-2.1 1.2-1.2 2.1 2.1 2-2.1 1.2 1.2-2 2.1z',
    };

    var launcherStyle = cfg.launcherStyle === 'label' ? 'label' : 'icon';
    var launcher = el(
      'button',
      'ls-chat__launcher' + (launcherStyle === 'label' ? ' ls-chat__launcher--label' : ' ls-chat__launcher--icon')
    );
    launcher.type = 'button';
    launcher.setAttribute('aria-label', cfg.i18n.title);
    launcher.setAttribute('aria-expanded', 'false');
    launcher.appendChild(svg(I.chat));
    if (launcherStyle === 'label') {
      launcher.appendChild(
        el('span', 'ls-chat__launcher-text', cfg.i18n.launcherLabel || cfg.i18n.title || 'Chat with us')
      );
    }

    var panel = el('div', 'ls-chat__panel');
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', cfg.i18n.title);

    // Header — title text + menu + close (no back button)
    var header = el('div', 'ls-chat__header');

    var agent = el('div', 'ls-chat__agent');
    var agentAv = el('div', 'ls-chat__agent-avatar');
    agentAv.appendChild(svg(I.chat));
    agentAv.appendChild(el('span', 'ls-chat__online'));
    var agentCopy = el('div', 'ls-chat__agent-copy');
    agentCopy.appendChild(el('strong', 'ls-chat__title', cfg.i18n.title || 'Chat with us'));
    agentCopy.appendChild(
      el('span', 'ls-chat__subtitle', cfg.i18n.online || 'Online · usually replies in minutes')
    );
    agent.appendChild(agentAv);
    agent.appendChild(agentCopy);

    var menuWrap = el('div', 'ls-chat__menu-wrap');
    var menuBtn = el('button', 'ls-chat__hdr-btn');
    menuBtn.type = 'button';
    menuBtn.setAttribute('aria-label', cfg.i18n.menu || 'Menu');
    menuBtn.appendChild(iconChar('☰', 'ls-chat__icon-char'));

    var closeBtn = el('button', 'ls-chat__hdr-btn ls-chat__hdr-btn--close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', cfg.i18n.minimize || 'Close');
    closeBtn.appendChild(iconChar('×', 'ls-chat__icon-char ls-chat__icon-char--lg'));

    var menu = el('div', 'ls-chat__menu');
    menu.hidden = true;

    var escalateItem = el('button', '');
    escalateItem.type = 'button';
    escalateItem.appendChild(iconChar('👤'));
    escalateItem.appendChild(document.createTextNode(' ' + cfg.i18n.escalate));
    var endItem = el('button', '');
    endItem.type = 'button';
    endItem.appendChild(iconChar('⏻'));
    endItem.appendChild(document.createTextNode(' ' + cfg.i18n.close));
    menu.appendChild(escalateItem);
    menu.appendChild(endItem);
    menuWrap.appendChild(menuBtn);
    menuWrap.appendChild(menu);

    header.appendChild(agent);
    header.appendChild(menuWrap);
    header.appendChild(closeBtn);

    // Gate
    var gate = el('div', 'ls-chat__gate');
    var intro = el('div', 'ls-chat__intro');
    intro.appendChild(el('p', 'ls-chat__intro-kicker', cfg.i18n.support || 'Customer support'));
    intro.appendChild(
      el('p', 'ls-chat__intro-text', cfg.welcome || cfg.i18n.defaultWelcome)
    );
    intro.appendChild(el('p', 'ls-chat__intro-help', cfg.i18n.gateHelp));

    var nameField = el('div', 'ls-chat__field');
    nameField.appendChild(el('label', 'ls-chat__label', cfg.i18n.nameLabel));
    var nameInput = el('input', 'ls-chat__input');
    nameInput.type = 'text';
    nameInput.autocomplete = 'name';
    nameInput.value = cfg.visitorName || '';
    nameInput.placeholder = cfg.i18n.namePlaceholder || '';
    nameField.appendChild(nameInput);

    var emailField = el('div', 'ls-chat__field');
    emailField.appendChild(el('label', 'ls-chat__label', cfg.i18n.emailLabel));
    var emailInput = el('input', 'ls-chat__input');
    emailInput.type = 'email';
    emailInput.autocomplete = 'email';
    emailInput.value = cfg.visitorEmail || '';
    emailInput.placeholder = cfg.i18n.emailPlaceholder || '';
    emailField.appendChild(emailInput);

    var startBtn = el('button', 'ls-chat__btn ls-chat__btn--primary', cfg.i18n.start);
    startBtn.type = 'button';
    var gateStatus = el('div', 'ls-chat__status');

    gate.appendChild(intro);
    gate.appendChild(nameField);
    gate.appendChild(emailField);
    gate.appendChild(startBtn);
    gate.appendChild(gateStatus);

    // Chat body
    var feed = el('div', 'ls-chat__feed');
    feed.appendChild(el('div', 'ls-chat__day', cfg.i18n.today || 'Today'));

    var typing = el('div', 'ls-chat__typing');
    typing.setAttribute('aria-hidden', 'true');
    var typingAv = el('div', 'ls-chat__msg-avatar');
    typingAv.appendChild(agentAvatarMark());
    var typingDots = el('div', 'ls-chat__typing-dots');
    typingDots.appendChild(el('span'));
    typingDots.appendChild(el('span'));
    typingDots.appendChild(el('span'));
    typing.appendChild(typingAv);
    typing.appendChild(typingDots);
    state.typingEl = typing;

    var composer = el('div', 'ls-chat__composer');
    var textarea = el('textarea', 'ls-chat__textarea');
    textarea.rows = 1;
    textarea.placeholder = cfg.i18n.placeholder;
    textarea.setAttribute('spellcheck', 'false');
    textarea.setAttribute('data-gramm', 'false');
    textarea.setAttribute('data-enable-grammarly', 'false');
    var sendBtn = el('button', 'ls-chat__send-icon');
    sendBtn.type = 'button';
    sendBtn.setAttribute('aria-label', cfg.i18n.send);
    sendBtn.appendChild(svg(I.send));
    composer.appendChild(textarea);
    composer.appendChild(sendBtn);

    var chatStatus = el('div', 'ls-chat__footer-status');
    var footer = el('div', 'ls-chat__footer');
    footer.appendChild(composer);
    footer.appendChild(chatStatus);
    footer.appendChild(el('p', 'ls-chat__powered', cfg.i18n.powered || 'Powered by LicenseSender'));

    var chatBody = el('div', 'ls-chat__body');
    chatBody.hidden = true;
    chatBody.appendChild(feed);
    chatBody.appendChild(footer);

    panel.appendChild(header);
    panel.appendChild(gate);
    panel.appendChild(chatBody);
    root.appendChild(launcher);
    root.appendChild(panel);

    function setMenu(open) {
      state.menuOpen = open;
      menu.hidden = !open;
    }

    function setOpen(open) {
      state.open = open;
      panel.hidden = !open;
      root.classList.toggle('is-open', open);
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
      setMenu(false);
      if (open && !state.started) {
        (cfg.requireEmail ? emailInput : nameInput).focus();
      }
    }

    function setBusy(busy, showTyping) {
      state.busy = busy;
      sendBtn.disabled = busy;
      startBtn.disabled = busy;
      if (busy && showTyping) {
        typing.classList.add('is-visible');
        typing.setAttribute('aria-hidden', 'false');
        if (!typing.parentNode) feed.appendChild(typing);
        feed.scrollTop = feed.scrollHeight;
      } else if (typing.parentNode) {
        typing.classList.remove('is-visible');
        typing.parentNode.removeChild(typing);
        typing.setAttribute('aria-hidden', 'true');
      }
    }

    function showChat() {
      gate.hidden = true;
      chatBody.hidden = false;
      textarea.focus();
    }

    function showGate() {
      gate.hidden = false;
      chatBody.hidden = true;
    }

    launcher.addEventListener('click', function () {
      setOpen(true);
    });
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });
    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      setMenu(!state.menuOpen);
    });
    document.addEventListener('click', function () {
      if (state.menuOpen) setMenu(false);
    });
    menu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    startBtn.addEventListener('click', function () {
      if (cfg.requireEmail && !emailInput.value.trim()) {
        setStatus(gateStatus, cfg.i18n.emailRequired, 'is-error');
        emailInput.focus();
        return;
      }
      setBusy(true, false);
      setStatus(gateStatus, '', null);
      post('ls_chat_start', {
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
        origin_url: window.location.href,
      })
        .then(function (data) {
          state.started = true;
          state.sessionId = data.session_id;
          state.lastMessageId = 0;
          feed.innerHTML = '';
          feed.appendChild(el('div', 'ls-chat__day', cfg.i18n.today || 'Today'));
          showChat();
          renderMessages(feed, data.messages || []);
          if ((!data.messages || !data.messages.length) && cfg.welcome) {
            appendMessage(
              feed,
              { id: 'welcome-local', role: 'assistant', body: cfg.welcome },
              { label: cfg.i18n.assistant || 'Assistant' }
            );
          }
          startPoll(feed);
        })
        .catch(function (err) {
          setStatus(gateStatus, err.message || cfg.i18n.error, 'is-error');
        })
        .finally(function () {
          setBusy(false, false);
        });
    });

    function send() {
      var text = textarea.value.trim();
      if (!text || !state.started || state.busy) return;

      var pendingId = 'local-' + Date.now();
      appendMessage(
        feed,
        { id: pendingId, role: 'user', body: text },
        { label: cfg.i18n.you || 'You' }
      );
      textarea.value = '';
      textarea.style.height = '';

      setBusy(true, true);
      setStatus(chatStatus, '', null);
      post('ls_chat_send', {
        message: text,
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
      })
        .then(function (data) {
          if (data.user_message && data.user_message.id) {
            var pending = feed.querySelector('[data-id="' + pendingId + '"]');
            if (pending) {
              pending.setAttribute('data-id', String(data.user_message.id));
              var nid = Number(data.user_message.id);
              if (!isNaN(nid) && nid > state.lastMessageId) state.lastMessageId = nid;
            }
          }
          if (data.assistant_message) {
            appendMessage(feed, data.assistant_message, {
              label: cfg.i18n.assistant || 'Assistant',
            });
          }
        })
        .catch(function (err) {
          var pending = feed.querySelector('[data-id="' + pendingId + '"]');
          if (pending) pending.classList.add('ls-chat__row--failed');
          setStatus(chatStatus, err.message || cfg.i18n.error, 'is-error');
        })
        .finally(function () {
          setBusy(false, false);
        });
    }

    sendBtn.addEventListener('click', send);
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });
    textarea.addEventListener('input', function () {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 88) + 'px';
    });

    function escalate() {
      setMenu(false);
      if (!state.started) return;
      if (!emailInput.value.trim()) {
        setStatus(chatStatus, cfg.i18n.emailRequired, 'is-error');
        showGate();
        emailInput.focus();
        return;
      }
      setBusy(true, false);
      post('ls_chat_escalate', {
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
      })
        .then(function () {
          setStatus(chatStatus, cfg.i18n.escalated, 'is-ok');
        })
        .catch(function (err) {
          setStatus(chatStatus, err.message || cfg.i18n.error, 'is-error');
        })
        .finally(function () {
          setBusy(false, false);
        });
    }

    function endChat() {
      setMenu(false);
      if (!state.started) {
        setOpen(false);
        return;
      }
      setBusy(true, false);
      post('ls_chat_close', {})
        .then(function () {
          stopPoll();
          state.started = false;
          setStatus(gateStatus, cfg.i18n.closed, 'is-ok');
          showGate();
        })
        .catch(function (err) {
          setStatus(chatStatus, err.message || cfg.i18n.error, 'is-error');
        })
        .finally(function () {
          setBusy(false, false);
        });
    }

    escalateItem.addEventListener('click', escalate);
    endItem.addEventListener('click', endChat);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();
