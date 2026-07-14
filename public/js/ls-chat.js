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
  };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text === 'string') node.textContent = text;
    return node;
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

  function appendMessage(container, message) {
    if (!message || !message.id) return;
    if (container.querySelector('[data-id="' + message.id + '"]')) return;

    var role = message.role || 'assistant';
    var bubble = el('div', 'ls-chat__bubble ls-chat__bubble--' + role);
    bubble.setAttribute('data-id', String(message.id));
    bubble.textContent = message.body || '';
    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;

    if (message.id > state.lastMessageId) {
      state.lastMessageId = message.id;
    }
  }

  function renderMessages(container, messages) {
    (messages || []).forEach(function (m) {
      appendMessage(container, m);
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

    var launcher = el('button', 'ls-chat__launcher', cfg.i18n.title);
    launcher.type = 'button';
    launcher.setAttribute('aria-expanded', 'false');

    var panel = el('div', 'ls-chat__panel');
    panel.hidden = true;

    var header = el('div', 'ls-chat__header');
    header.appendChild(el('strong', 'ls-chat__title', cfg.i18n.title));
    var closeBtn = el('button', 'ls-chat__icon-btn', '×');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Close');
    header.appendChild(closeBtn);

    var gate = el('div', 'ls-chat__gate');
    var nameInput = el('input', 'ls-chat__input');
    nameInput.type = 'text';
    nameInput.placeholder = cfg.i18n.nameLabel;
    nameInput.value = cfg.visitorName || '';
    var emailInput = el('input', 'ls-chat__input');
    emailInput.type = 'email';
    emailInput.placeholder = cfg.i18n.emailLabel;
    emailInput.value = cfg.visitorEmail || '';
    var startBtn = el('button', 'ls-chat__btn ls-chat__btn--primary', cfg.i18n.start);
    startBtn.type = 'button';
    gate.appendChild(nameInput);
    gate.appendChild(emailInput);
    gate.appendChild(startBtn);

    var feed = el('div', 'ls-chat__feed');
    var status = el('div', 'ls-chat__status');

    var composer = el('div', 'ls-chat__composer');
    var textarea = el('textarea', 'ls-chat__textarea');
    textarea.rows = 2;
    textarea.placeholder = cfg.i18n.placeholder;
    var sendBtn = el('button', 'ls-chat__btn ls-chat__btn--primary', cfg.i18n.send);
    sendBtn.type = 'button';
    composer.appendChild(textarea);
    composer.appendChild(sendBtn);

    var actions = el('div', 'ls-chat__actions');
    var escalateBtn = el('button', 'ls-chat__btn ls-chat__btn--ghost', cfg.i18n.escalate);
    escalateBtn.type = 'button';
    var endBtn = el('button', 'ls-chat__btn ls-chat__btn--ghost', cfg.i18n.close);
    endBtn.type = 'button';
    actions.appendChild(escalateBtn);
    actions.appendChild(endBtn);

    var chatBody = el('div', 'ls-chat__body');
    chatBody.hidden = true;
    chatBody.appendChild(feed);
    chatBody.appendChild(status);
    chatBody.appendChild(composer);
    chatBody.appendChild(actions);

    panel.appendChild(header);
    panel.appendChild(gate);
    panel.appendChild(chatBody);
    root.appendChild(launcher);
    root.appendChild(panel);

    function setOpen(open) {
      state.open = open;
      panel.hidden = !open;
      launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function setBusy(busy) {
      state.busy = busy;
      sendBtn.disabled = busy;
      startBtn.disabled = busy;
      escalateBtn.disabled = busy;
      endBtn.disabled = busy;
    }

    function showChat() {
      gate.hidden = true;
      chatBody.hidden = false;
    }

    launcher.addEventListener('click', function () {
      setOpen(!state.open);
    });
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });

    startBtn.addEventListener('click', function () {
      if (cfg.requireEmail && !emailInput.value.trim()) {
        status.textContent = cfg.i18n.emailRequired;
        return;
      }
      setBusy(true);
      status.textContent = '';
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
          showChat();
          renderMessages(feed, data.messages || []);
          if ((!data.messages || !data.messages.length) && cfg.welcome) {
            appendMessage(feed, { id: 0, role: 'assistant', body: cfg.welcome });
          }
          startPoll(feed);
        })
        .catch(function (err) {
          status.textContent = err.message || cfg.i18n.error;
        })
        .finally(function () {
          setBusy(false);
        });
    });

    function send() {
      var text = textarea.value.trim();
      if (!text || !state.started) return;
      setBusy(true);
      status.textContent = '';
      post('ls_chat_send', {
        message: text,
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
      })
        .then(function (data) {
          textarea.value = '';
          if (data.user_message) appendMessage(feed, data.user_message);
          if (data.assistant_message) appendMessage(feed, data.assistant_message);
        })
        .catch(function (err) {
          status.textContent = err.message || cfg.i18n.error;
        })
        .finally(function () {
          setBusy(false);
        });
    }

    sendBtn.addEventListener('click', send);
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });

    escalateBtn.addEventListener('click', function () {
      if (!state.started) return;
      if (!emailInput.value.trim()) {
        status.textContent = cfg.i18n.emailRequired;
        emailInput.focus();
        return;
      }
      setBusy(true);
      post('ls_chat_escalate', {
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
      })
        .then(function () {
          status.textContent = cfg.i18n.escalated;
        })
        .catch(function (err) {
          status.textContent = err.message || cfg.i18n.error;
        })
        .finally(function () {
          setBusy(false);
        });
    });

    endBtn.addEventListener('click', function () {
      if (!state.started) {
        setOpen(false);
        return;
      }
      setBusy(true);
      post('ls_chat_close', {})
        .then(function () {
          stopPoll();
          state.started = false;
          status.textContent = cfg.i18n.closed;
        })
        .catch(function (err) {
          status.textContent = err.message || cfg.i18n.error;
        })
        .finally(function () {
          setBusy(false);
        });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();
