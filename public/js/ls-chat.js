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
    pollInFlight: false,
    pollDelay: cfg.pollInterval || 4000,
    busy: false,
    typingEl: null,
    menuOpen: false,
    handlingMode: 'ai',
    assignedAgentName: null,
    broadcastCredential: null,
    echo: null,
    channel: null,
    realtimeOk: false,
    hiddenPaused: false,
    selectedFiles: [],
    typingTimer: null,
    remoteTypingTimer: null,
    remoteTypingVisible: false,
    sessionClosed: false,
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
        if (Array.isArray(data[key])) {
          data[key].forEach(function (value) {
            body.append(key + '[]', value);
          });
        } else {
          body.append(key, data[key]);
        }
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

  function formatBytes(bytes) {
    var size = Number(bytes || 0);
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return Math.round(size / 1024) + ' KB';
    return (size / 1024 / 1024).toFixed(1) + ' MB';
  }

  function appendAttachments(stack, attachments) {
    if (!Array.isArray(attachments) || !attachments.length) return;
    var list = el('div', 'ls-chat__attachments');

    attachments.forEach(function (file) {
      var link = el('a', file.is_image ? 'ls-chat__attachment-image' : 'ls-chat__attachment-file');
      link.href = file.url || '#';
      link.rel = 'noopener';
      link.setAttribute('aria-label', file.name || 'Attachment');

      if (file.is_image) {
        link.classList.add('ls-chat__attachment-image--lightbox');
        link.setAttribute('data-ls-lightbox', '1');
        link.setAttribute('data-ls-lightbox-url', file.url || '');
        link.setAttribute('data-ls-lightbox-name', file.name || 'Image');
        var image = document.createElement('img');
        image.src = file.url;
        image.alt = file.name || 'Image attachment';
        image.loading = 'lazy';
        link.appendChild(image);
      } else {
        link.target = '_blank';
        link.appendChild(svg('M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm1 9H7V9h8zm0 4H7v-2h8zm-2-6V3.5L18.5 9z'));
        var copy = el('span', 'ls-chat__attachment-copy');
        copy.appendChild(el('strong', '', file.name || 'Attachment'));
        copy.appendChild(el('small', '', formatBytes(file.size)));
        link.appendChild(copy);
      }
      list.appendChild(link);
    });

    stack.appendChild(list);
  }

  function appendMessage(container, message, opts) {
    opts = opts || {};
    if (!message) return;
    var id = message.id;
    var clientId = message.client_message_id ? String(message.client_message_id) : '';

    if (id !== undefined && id !== null && id !== '' && String(id) !== '0') {
      if (container.querySelector('[data-id="' + String(id).replace(/"/g, '') + '"]')) {
        return;
      }
    }

    // Upgrade optimistic bubble when the real message arrives (poll / Echo / send response).
    if (clientId) {
      var pending = container.querySelector('[data-client-id="' + clientId.replace(/"/g, '\\"') + '"]');
      if (pending) {
        if (id !== undefined && id !== null && id !== '' && String(id) !== '0') {
          pending.setAttribute('data-id', String(id));
          var upgradedId = Number(id);
          if (!isNaN(upgradedId) && upgradedId > state.lastMessageId) {
            state.lastMessageId = upgradedId;
          }
        }
        if (Array.isArray(message.attachments) && message.attachments.length) {
          var pendingStack = pending.querySelector('.ls-chat__stack');
          if (pendingStack && !pendingStack.querySelector('.ls-chat__attachments')) {
            appendAttachments(pendingStack, message.attachments);
          }
        }
        // Drop the synthetic filename bubble once the real (attachment-only) message arrives.
        if (!message.body) {
          var placeholderBubble = pending.querySelector('[data-placeholder]');
          if (placeholderBubble && placeholderBubble.parentNode) {
            placeholderBubble.parentNode.removeChild(placeholderBubble);
          }
        }
        return;
      }
    }

    var role = message.role || 'assistant';
    var row = el('div', 'ls-chat__row ls-chat__row--' + role);
    if (id !== undefined && id !== null && id !== '' && String(id) !== '0') {
      row.setAttribute('data-id', String(id));
    }
    if (clientId) {
      row.setAttribute('data-client-id', clientId);
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
    if (message.body) {
      var bubble = el('div', 'ls-chat__bubble ls-chat__bubble--' + role);
      bubble.textContent = message.body;
      if (message.placeholder) {
        bubble.setAttribute('data-placeholder', '1');
      }
      stack.appendChild(bubble);
    }
    appendAttachments(stack, message.attachments);
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

  function messageLabel(m) {
    if (m.role === 'user') return cfg.i18n.you || 'You';
    if (m.role === 'agent') return m.author_name || state.assignedAgentName || cfg.i18n.agent || 'Support';
    if (m.role === 'system') return cfg.i18n.system || 'System';
    return cfg.i18n.assistant || 'AI Assistant';
  }

  function renderMessages(container, messages) {
    (messages || []).forEach(function (m) {
      appendMessage(container, m, { label: messageLabel(m) });
    });
  }

  function stopTyping() {
    // Never remove the indicator while a remote (agent) typing state is active.
    if (state.remoteTypingVisible) return;
    if (state.typingEl && state.typingEl.parentNode) {
      state.typingEl.parentNode.removeChild(state.typingEl);
    }
  }

  function applySessionState(session, chatStatus, subtitleNode) {
    if (!session) return;
    state.handlingMode = session.handling_mode || state.handlingMode || 'ai';
    if (session.status) {
      state.sessionClosed = session.status === 'closed';
      if (typeof state.setComposerEnabled === 'function') {
        state.setComposerEnabled(!state.sessionClosed);
      }
      var rootNode = document.getElementById('ls-chat-root');
      if (rootNode) {
        rootNode.classList.toggle('is-session-closed', state.sessionClosed);
      }
    }
    if (Object.prototype.hasOwnProperty.call(session, 'assigned_agent_name')) {
      state.assignedAgentName = session.assigned_agent_name || null;
    }

    var mode = state.handlingMode;
    var statusText = '';
    var kind = 'is-ok';
    var titleNode = document.querySelector('#ls-chat-root .ls-chat__title');

    if (titleNode) {
      if (mode === 'human' && state.assignedAgentName) {
        titleNode.textContent = state.assignedAgentName;
      } else if (mode === 'ai') {
        titleNode.textContent = cfg.i18n.agentTitle || cfg.i18n.assistant || 'AI Assistant';
      } else {
        titleNode.textContent = cfg.i18n.title || 'Chat with us';
      }
    }

    if (session.status === 'closed') {
      statusText = cfg.i18n.closed;
      kind = null;
    } else if (mode === 'waiting_agent') {
      statusText = cfg.i18n.waitingAgent || cfg.i18n.escalated;
    } else if (mode === 'human') {
      statusText = cfg.i18n.agentJoined || 'An agent has joined the chat.';
    } else if (mode === 'ticket_fallback') {
      statusText = cfg.i18n.ticketFallback || cfg.i18n.escalated;
    }

    if (statusText && chatStatus) {
      setStatus(chatStatus, statusText, kind);
    }

    if (subtitleNode) {
      if (mode === 'human' && state.assignedAgentName) {
        subtitleNode.textContent = cfg.i18n.humanRole || 'Human support agent · Online';
      } else if (mode === 'waiting_agent') {
        subtitleNode.textContent = cfg.i18n.waitingAgent || 'Waiting for an agent…';
      } else if (mode === 'ticket_fallback') {
        subtitleNode.textContent = cfg.i18n.ticketFallback || 'Support ticket created';
      } else {
        subtitleNode.textContent = cfg.i18n.aiRole || 'Automated support · Online';
      }
    }

    if (mode !== 'ai') {
      stopTyping();
    }
  }

  function stopPoll() {
    if (state.pollTimer) {
      clearTimeout(state.pollTimer);
      state.pollTimer = null;
    }
  }

  function schedulePoll(feed, chatStatus, subtitleNode) {
    stopPoll();
    state.pollTimer = setTimeout(function () {
      runPoll(feed, chatStatus, subtitleNode);
    }, state.pollDelay);
  }

  function runPoll(feed, chatStatus, subtitleNode) {
    if (!state.started || state.pollInFlight || state.hiddenPaused) {
      schedulePoll(feed, chatStatus, subtitleNode);
      return;
    }

    state.pollInFlight = true;
    post('ls_chat_poll', { since_id: state.lastMessageId })
      .then(function (data) {
        state.pollDelay = cfg.pollInterval || 4000;
        renderMessages(feed, data.messages || []);
        applySessionState(data.session, chatStatus, subtitleNode);
        if (typeof state.applyTypingSnapshot === 'function') {
          state.applyTypingSnapshot(data.typing);
        }
      })
      .catch(function () {
        state.pollDelay = Math.min((state.pollDelay || 4000) * 2, 30000);
        if (chatStatus && !state.realtimeOk) {
          setStatus(chatStatus, cfg.i18n.offline || 'Reconnecting…', 'is-error');
        }
      })
      .finally(function () {
        state.pollInFlight = false;
        schedulePoll(feed, chatStatus, subtitleNode);
      });
  }

  function startPoll(feed, chatStatus, subtitleNode) {
    state.pollDelay = state.realtimeOk ? 15000 : cfg.pollInterval || 4000;
    schedulePoll(feed, chatStatus, subtitleNode);
  }

  function initRealtime(feed, chatStatus, subtitleNode) {
    if (typeof window.Echo === 'undefined' && typeof Echo === 'undefined') {
      return;
    }

    // One realtime lifecycle per chat session — escalate() may call this again.
    if (state.echo) {
      return;
    }

    post('ls_chat_broadcast_bootstrap', {})
      .then(function (data) {
        if (!data || !data.credential || !data.reverb || !data.reverb.key) {
          return;
        }

        state.broadcastCredential = data.credential;
        var EchoCtor = window.Echo || Echo;
        var echo = new EchoCtor({
          broadcaster: 'reverb',
          key: data.reverb.key,
          wsHost: data.reverb.host,
          wsPort: data.reverb.port || 80,
          wssPort: data.reverb.port || 443,
          forceTLS: (data.reverb.scheme || 'https') === 'https',
          enabledTransports: ['ws', 'wss'],
          authEndpoint: data.auth_endpoint || cfg.ajaxUrl,
          authorizer: function (channel) {
            return {
              authorize: function (socketId, callback) {
                var body = new FormData();
                body.append('action', data.auth_action || 'ls_chat_broadcast_auth');
                body.append('nonce', data.nonce || cfg.nonce);
                body.append('channel_name', channel.name);
                body.append('socket_id', socketId);
                body.append('credential', state.broadcastCredential || '');
                fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                  .then(function (res) {
                    return res.json();
                  })
                  .then(function (payload) {
                    if (payload && payload.auth) {
                      callback(null, payload);
                    } else if (payload && payload.data && payload.data.auth) {
                      callback(null, { auth: payload.data.auth });
                    } else {
                      callback(new Error('Auth failed'), null);
                    }
                  })
                  .catch(function (err) {
                    callback(err, null);
                  });
              },
            };
          },
        });

        state.echo = echo;
        var channelName = (data.channel || '').replace(/^private-/, '');
        state.channel = echo.private(channelName)
          .listen('.chat.message.created', function (event) {
            if (event && event.message) {
              renderMessages(feed, [event.message]);
            }
            if (event) {
              applySessionState(
                {
                  handling_mode: event.handling_mode,
                  status: event.status,
                  assigned_agent_name: event.message && event.message.role === 'agent' ? event.message.author_name : state.assignedAgentName,
                },
                chatStatus,
                subtitleNode
              );
            }
          })
          .listen('.chat.session.claimed', function (event) {
            applySessionState(event, chatStatus, subtitleNode);
          })
          .listen('.chat.session.released', function (event) {
            applySessionState(event, chatStatus, subtitleNode);
          })
          .listen('.chat.session.ai_resumed', function (event) {
            applySessionState(event, chatStatus, subtitleNode);
            setStatus(chatStatus, cfg.i18n.aiResumed || 'The AI assistant is back to help you.', 'is-ok');
          })
          .listen('.chat.session.ticket_fallback', function (event) {
            applySessionState(event, chatStatus, subtitleNode);
          })
          .listen('.chat.session.closed', function (event) {
            applySessionState(event, chatStatus, subtitleNode);
          })
          .listen('.chat.typing.changed', function (event) {
            if (typeof state.onTypingEvent === 'function') {
              state.onTypingEvent(event);
            }
          });

        var connector = echo.connector && echo.connector.pusher;
        if (connector && connector.connection) {
          connector.connection.bind('connected', function () {
            state.realtimeOk = true;
            state.pollDelay = 15000;
          });
          connector.connection.bind('disconnected', function () {
            state.realtimeOk = false;
            state.pollDelay = cfg.pollInterval || 4000;
          });
          connector.connection.bind('error', function () {
            state.realtimeOk = false;
            state.pollDelay = cfg.pollInterval || 4000;
          });
        }
      })
      .catch(function () {
        state.realtimeOk = false;
      });
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
      x: 'M18.3 5.71a1 1 0 0 0-1.42 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12 5.7 16.89a1 1 0 1 0 1.41 1.42L12 13.41l4.89 4.9a1 1 0 0 0 1.42-1.42L13.41 12l4.9-4.89a1 1 0 0 0 0-1.4z',
      send: 'M3.4 20.6 21 12 3.4 3.4 3.3 10l11 2-11 2 .1 6.6z',
      attach: 'M16.5 6.5v10a4.5 4.5 0 0 1-9 0V5a3 3 0 0 1 6 0v10.5a1.5 1.5 0 0 1-3 0V6H12v9.5l.1.1.1-.1V5a1.5 1.5 0 0 0-3 0v11.5a3 3 0 0 0 6 0v-10z',
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
    agentCopy.appendChild(el('strong', 'ls-chat__title', cfg.i18n.title || cfg.i18n.agentTitle || 'Customer Support'));
    var subtitleNode = el(
      'span',
      'ls-chat__subtitle',
      cfg.i18n.agentRole || cfg.i18n.online || 'Online · typically replies in a few minutes'
    );
    agentCopy.appendChild(subtitleNode);
    agent.appendChild(agentAv);
    agent.appendChild(agentCopy);

    var menuWrap = el('div', 'ls-chat__menu-wrap');
    var menuBtn = el('button', 'ls-chat__hdr-btn');
    menuBtn.type = 'button';
    menuBtn.setAttribute('aria-label', cfg.i18n.menu || 'Menu');
    menuBtn.appendChild(svg(I.menu));

    var closeBtn = el('button', 'ls-chat__hdr-btn ls-chat__hdr-btn--close');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', cfg.i18n.minimize || 'Close');
    closeBtn.appendChild(svg(I.x));

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
    var typingLabel = el('span', 'ls-chat__typing-label', cfg.i18n.aiTyping || 'AI Assistant is typing');
    var typingDots = el('div', 'ls-chat__typing-dots');
    typingDots.appendChild(el('span'));
    typingDots.appendChild(el('span'));
    typingDots.appendChild(el('span'));
    typing.appendChild(typingAv);
    typing.appendChild(typingLabel);
    typing.appendChild(typingDots);
    state.typingEl = typing;

    var composer = el('div', 'ls-chat__composer');
    var attachmentPreview = el('div', 'ls-chat__attachment-preview');
    attachmentPreview.hidden = true;
    var attachmentInput = document.createElement('input');
    attachmentInput.type = 'file';
    attachmentInput.multiple = true;
    attachmentInput.accept = 'image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp';
    attachmentInput.hidden = true;
    var attachmentBtn = el('button', 'ls-chat__attach');
    attachmentBtn.type = 'button';
    attachmentBtn.setAttribute('aria-label', cfg.i18n.attach || 'Attach images');
    attachmentBtn.setAttribute('title', cfg.i18n.attach || 'Attach images');
    attachmentBtn.appendChild(svg(I.attach));
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
    composer.appendChild(attachmentBtn);
    composer.appendChild(attachmentInput);
    composer.appendChild(textarea);
    composer.appendChild(sendBtn);

    var chatStatus = el('div', 'ls-chat__footer-status');
    var footer = el('div', 'ls-chat__footer');
    footer.appendChild(attachmentPreview);
    footer.appendChild(composer);
    footer.appendChild(chatStatus);
    footer.appendChild(el('p', 'ls-chat__powered', cfg.i18n.powered || 'Powered by LicenseSender'));

    var chatBody = el('div', 'ls-chat__body');
    chatBody.hidden = true;
    chatBody.appendChild(feed);
    chatBody.appendChild(footer);

    var restoring = el('div', 'ls-chat__restoring');
    restoring.hidden = true;
    restoring.setAttribute('aria-live', 'polite');
    restoring.appendChild(el('div', 'ls-chat__restoring-spinner'));
    restoring.appendChild(
      el('p', 'ls-chat__restoring-text', cfg.i18n.restoring || 'Opening your chat…')
    );

    panel.appendChild(header);
    panel.appendChild(gate);
    panel.appendChild(restoring);
    panel.appendChild(chatBody);

    var lightbox = el('div', 'ls-chat__lightbox');
    lightbox.hidden = true;
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Image preview');
    var lightboxBackdrop = el('button', 'ls-chat__lightbox-backdrop');
    lightboxBackdrop.type = 'button';
    lightboxBackdrop.setAttribute('aria-label', 'Close');
    var lightboxDialog = el('div', 'ls-chat__lightbox-dialog');
    var lightboxHeader = el('div', 'ls-chat__lightbox-header');
    var lightboxTitle = el('span', 'ls-chat__lightbox-title', 'Image');
    var lightboxActions = el('div', 'ls-chat__lightbox-actions');
    var lightboxOpen = el('a', 'ls-chat__lightbox-open', cfg.i18n.openOriginal || 'Open');
    lightboxOpen.target = '_blank';
    lightboxOpen.rel = 'noopener';
    lightboxOpen.href = '#';
    var lightboxClose = el('button', 'ls-chat__lightbox-close', '×');
    lightboxClose.type = 'button';
    lightboxClose.setAttribute('aria-label', 'Close');
    lightboxActions.appendChild(lightboxOpen);
    lightboxActions.appendChild(lightboxClose);
    lightboxHeader.appendChild(lightboxTitle);
    lightboxHeader.appendChild(lightboxActions);
    var lightboxBody = el('div', 'ls-chat__lightbox-body');
    var lightboxImg = document.createElement('img');
    lightboxImg.alt = '';
    lightboxBody.appendChild(lightboxImg);
    lightboxDialog.appendChild(lightboxHeader);
    lightboxDialog.appendChild(lightboxBody);
    lightbox.appendChild(lightboxBackdrop);
    lightbox.appendChild(lightboxDialog);
    // Must live inside the panel — the root collapses to 0×0 when open
    // (launcher hidden + panel is position:absolute), so a root-level overlay was invisible.
    panel.appendChild(lightbox);

    root.appendChild(launcher);
    root.appendChild(panel);

    function closeLightbox() {
      lightbox.hidden = true;
      panel.classList.remove('is-lightbox-open');
      lightboxImg.removeAttribute('src');
      lightboxImg.alt = '';
      lightboxOpen.href = '#';
    }

    function openLightbox(url, name) {
      if (!url || url === '#') return;
      lightboxTitle.textContent = name || 'Image';
      lightboxImg.src = url;
      lightboxImg.alt = name || 'Image';
      lightboxOpen.href = url;
      lightbox.hidden = false;
      panel.classList.add('is-lightbox-open');
      try {
        lightboxClose.focus();
      } catch (e) {}
    }

    function applyStartedSession(data) {
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
          { label: cfg.i18n.assistant || 'AI Assistant' }
        );
      }
      applySessionState(data.session, chatStatus, subtitleNode);
      startPoll(feed, chatStatus, subtitleNode);
      initRealtime(feed, chatStatus, subtitleNode);
    }

    function showRestoring() {
      gate.hidden = true;
      chatBody.hidden = true;
      restoring.hidden = false;
    }

    function showChat() {
      restoring.hidden = true;
      gate.hidden = true;
      chatBody.hidden = false;
      try {
        textarea.focus();
      } catch (e) {}
    }

    function showGate() {
      restoring.hidden = true;
      gate.hidden = false;
      chatBody.hidden = true;
      state.sessionClosed = false;
      setComposerEnabled(true);
      root.classList.remove('is-session-closed');
      var titleNode = document.querySelector('#ls-chat-root .ls-chat__title');
      if (titleNode) {
        titleNode.textContent = cfg.i18n.title || 'Chat with us';
      }
      subtitleNode.textContent =
        cfg.i18n.agentRole || cfg.i18n.online || 'Online · typically replies in a few minutes';
    }

    var resumeAttempted = false;
    var resumeInFlight = false;

    function tryResumeSession() {
      if (state.started || resumeInFlight || resumeAttempted) {
        return;
      }

      resumeAttempted = true;
      resumeInFlight = true;
      if (state.open) {
        showRestoring();
      }

      post('ls_chat_start', {
        visitor_name: (cfg.visitorName || '').trim(),
        visitor_email: (cfg.visitorEmail || '').trim(),
        origin_url: window.location.href,
        resume_only: '1',
      })
        .then(function (data) {
          if (data && data.session_id && (data.resumed || data.session)) {
            applyStartedSession(data);
          } else if (state.open) {
            showGate();
          }
        })
        .catch(function () {
          if (state.open && !state.started) {
            showGate();
          }
        })
        .finally(function () {
          resumeInFlight = false;
        });
    }

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
      if (!open) {
        closeLightbox();
        return;
      }

      if (state.started) {
        showChat();
        return;
      }

      // Resume still loading — show spinner, never flash the Start form.
      if (resumeInFlight || !resumeAttempted) {
        showRestoring();
        tryResumeSession();
        return;
      }

      showGate();
      try {
        (cfg.requireEmail ? emailInput : nameInput).focus();
      } catch (e) {}
    }

    function setBusy(busy, showTyping) {
      state.busy = busy;
      sendBtn.disabled = busy || state.sessionClosed;
      attachmentBtn.disabled = busy || state.sessionClosed;
      startBtn.disabled = busy;
      var allowTyping = showTyping && state.handlingMode === 'ai';
      if (busy && allowTyping) {
        typingLabel.textContent = cfg.i18n.aiTyping || 'AI Assistant is typing';
        typing.classList.add('is-visible');
        typing.setAttribute('aria-hidden', 'false');
        if (!typing.parentNode) feed.appendChild(typing);
        feed.scrollTop = feed.scrollHeight;
      } else if (typing.parentNode && !state.remoteTypingVisible) {
        typing.classList.remove('is-visible');
        typing.parentNode.removeChild(typing);
        typing.setAttribute('aria-hidden', 'true');
      }
    }

    function hideRemoteTyping() {
      clearTimeout(state.remoteTypingTimer);
      state.remoteTypingVisible = false;
      if (!state.busy && typing.parentNode) {
        typing.classList.remove('is-visible');
        typing.parentNode.removeChild(typing);
        typing.setAttribute('aria-hidden', 'true');
      }
    }

    function showRemoteTyping(name) {
      if (state.handlingMode === 'ai' && state.busy) return;
      clearTimeout(state.remoteTypingTimer);
      state.remoteTypingVisible = true;
      typingLabel.textContent =
        (name || state.assignedAgentName || cfg.i18n.agent || 'Support') +
        ' ' +
        (cfg.i18n.isTyping || 'is typing');
      typing.classList.add('is-visible');
      typing.setAttribute('aria-hidden', 'false');
      if (!typing.parentNode) feed.appendChild(typing);
      feed.scrollTop = feed.scrollHeight;
      state.remoteTypingTimer = setTimeout(hideRemoteTyping, 5000);
    }

    function applyTypingSnapshot(typingState) {
      var agent = typingState && typingState.agent;
      if (agent && agent.is_typing) {
        showRemoteTyping(agent.actor_name || state.assignedAgentName || 'Support');
      } else if (!state.busy) {
        hideRemoteTyping();
      }
    }

    function onTypingEvent(event) {
      if (!event || event.actor !== 'agent') return;
      if (event.is_typing) {
        showRemoteTyping(event.actor_name || state.assignedAgentName || 'Support');
      } else {
        hideRemoteTyping();
      }
    }

    state.applyTypingSnapshot = applyTypingSnapshot;
    state.onTypingEvent = onTypingEvent;

    function sendTyping(isTyping) {
      if (!state.started || state.handlingMode !== 'human') return;
      post('ls_chat_typing', {
        is_typing: isTyping ? 1 : 0,
        visitor_name: nameInput.value.trim(),
      }).catch(function () {
        // typing is best-effort
      });
    }

    function renderSelectedFiles() {
      // Release thumbnail object URLs from the previous render.
      Array.prototype.forEach.call(
        attachmentPreview.querySelectorAll('img[data-object-url]'),
        function (img) {
          try {
            URL.revokeObjectURL(img.src);
          } catch (e) {}
        }
      );
      attachmentPreview.innerHTML = '';
      attachmentPreview.hidden = state.selectedFiles.length === 0;
      state.selectedFiles.forEach(function (file, index) {
        var chip = el('div', 'ls-chat__attachment-chip');
        var thumbAdded = false;
        try {
          var thumb = document.createElement('img');
          thumb.className = 'ls-chat__attachment-thumb';
          thumb.src = URL.createObjectURL(file);
          thumb.alt = file.name;
          thumb.setAttribute('data-object-url', '1');
          chip.appendChild(thumb);
          thumbAdded = true;
        } catch (e) {}
        if (!thumbAdded) {
          chip.appendChild(svg(I.attach));
        }
        var copy = el('span', 'ls-chat__attachment-copy');
        copy.appendChild(el('strong', '', file.name));
        copy.appendChild(el('small', '', formatBytes(file.size)));
        chip.appendChild(copy);
        var remove = el('button', 'ls-chat__attachment-remove', '×');
        remove.type = 'button';
        remove.setAttribute('aria-label', (cfg.i18n.remove || 'Remove') + ' ' + file.name);
        remove.addEventListener('click', function () {
          state.selectedFiles.splice(index, 1);
          renderSelectedFiles();
        });
        chip.appendChild(remove);
        attachmentPreview.appendChild(chip);
      });
    }

    function setComposerEnabled(enabled) {
      textarea.disabled = !enabled;
      sendBtn.disabled = !enabled || state.busy;
      attachmentBtn.disabled = !enabled || state.busy;
      composer.classList.toggle('is-disabled', !enabled);
      if (!enabled) {
        state.selectedFiles = [];
        renderSelectedFiles();
        stopTyping();
      }
    }

    state.setComposerEnabled = setComposerEnabled;

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
          applyStartedSession(data);
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
      if ((!text && !state.selectedFiles.length) || !state.started || state.busy || state.sessionClosed) return;

      var clientMessageId = 'c-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
      var files = state.selectedFiles.slice();
      appendMessage(
        feed,
        {
          role: 'user',
          body: text || (files.length === 1 ? files[0].name : files.length + ' attachments'),
          placeholder: !text,
          client_message_id: clientMessageId,
        },
        { label: cfg.i18n.you || 'You' }
      );
      textarea.value = '';
      textarea.style.height = '';
      state.selectedFiles = [];
      renderSelectedFiles();
      clearTimeout(state.typingTimer);
      sendTyping(false);

      setBusy(true, state.handlingMode === 'ai');
      setStatus(chatStatus, '', null);
      post('ls_chat_send', {
        message: text,
        client_message_id: clientMessageId,
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
        attachments: files,
      })
        .then(function (data) {
          if (data.user_message) {
            appendMessage(feed, data.user_message, {
              label: messageLabel(data.user_message),
            });
          }
          if (data.assistant_message) {
            appendMessage(feed, data.assistant_message, {
              label: messageLabel(data.assistant_message),
            });
          }
          applySessionState(data.session, chatStatus, subtitleNode);
        })
        .catch(function (err) {
          var pending = feed.querySelector(
            '[data-client-id="' + clientMessageId.replace(/"/g, '\\"') + '"]'
          );
          if (pending) pending.classList.add('ls-chat__row--failed');
          if (text && !textarea.value) {
            textarea.value = text;
          }
          state.selectedFiles = files;
          renderSelectedFiles();
          setStatus(chatStatus, err.message || cfg.i18n.error, 'is-error');
        })
        .finally(function () {
          setBusy(false, false);
        });
    }

    sendBtn.addEventListener('click', send);
    attachmentBtn.addEventListener('click', function () {
      attachmentInput.click();
    });
    panel.addEventListener('click', function (event) {
      var link = event.target.closest('[data-ls-lightbox="1"]');
      if (!link || !panel.contains(link)) return;
      event.preventDefault();
      event.stopPropagation();
      openLightbox(
        link.getAttribute('data-ls-lightbox-url') || link.getAttribute('href') || '',
        link.getAttribute('data-ls-lightbox-name') || 'Image'
      );
    });
    lightboxBackdrop.addEventListener('click', closeLightbox);
    lightboxClose.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !lightbox.hidden) {
        closeLightbox();
      }
    });
    attachmentInput.addEventListener('change', function () {
      var incoming = Array.prototype.slice.call(attachmentInput.files || []);
      var allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      var allowedExt = /\.(jpe?g|png|gif|webp)$/i;
      var invalid = incoming.find(function (file) {
        // Some browsers leave File.type empty — fall back to the extension.
        if (!file.type) return !allowedExt.test(file.name || '');
        return allowed.indexOf(file.type) === -1;
      });
      if (invalid) {
        setStatus(
          chatStatus,
          cfg.i18n.onlyImages || 'Only image attachments are allowed (JPG, PNG, GIF, WebP).',
          'is-error'
        );
        attachmentInput.value = '';
        return;
      }
      var oversized = incoming.find(function (file) {
        return file.size > 5 * 1024 * 1024;
      });
      if (oversized) {
        var tooLarge = cfg.i18n.imageTooLarge || '%s is larger than 5 MB.';
        setStatus(chatStatus, tooLarge.replace('%s', oversized.name), 'is-error');
        attachmentInput.value = '';
        return;
      }
      var combined = state.selectedFiles.concat(incoming);
      if (combined.length > 5) {
        setStatus(chatStatus, cfg.i18n.maxImages || 'You can attach up to 5 images.', 'is-error');
      } else {
        setStatus(chatStatus, '', null);
      }
      state.selectedFiles = combined.slice(0, 5);
      attachmentInput.value = '';
      renderSelectedFiles();
    });
    textarea.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
      }
    });
    textarea.addEventListener('input', function () {
      textarea.style.height = 'auto';
      textarea.style.height = Math.min(textarea.scrollHeight, 88) + 'px';
      if (state.handlingMode === 'human') {
        sendTyping(true);
        clearTimeout(state.typingTimer);
        state.typingTimer = setTimeout(function () {
          sendTyping(false);
        }, 1500);
      }
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
      stopTyping();
      post('ls_chat_handoff', {
        visitor_name: nameInput.value.trim(),
        visitor_email: emailInput.value.trim(),
      })
        .then(function (data) {
          applySessionState(data.session, chatStatus, subtitleNode);
          if (!data.session) {
            setStatus(chatStatus, cfg.i18n.escalated, 'is-ok');
          }
          initRealtime(feed, chatStatus, subtitleNode);
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
          state.sessionId = null;
          state.handlingMode = 'ai';
          state.assignedAgentName = null;
          state.lastMessageId = 0;
          state.selectedFiles = [];
          renderSelectedFiles();
          clearTimeout(state.typingTimer);
          hideRemoteTyping();
          try {
            if (state.echo && state.echo.disconnect) state.echo.disconnect();
          } catch (e) {}
          state.echo = null;
          state.channel = null;
          state.broadcastCredential = null;
          state.realtimeOk = false;
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

    document.addEventListener('visibilitychange', function () {
      state.hiddenPaused = document.visibilityState === 'hidden';
      if (!state.hiddenPaused && state.started) {
        state.pollDelay = state.realtimeOk ? 15000 : cfg.pollInterval || 4000;
        runPoll(feed, chatStatus, subtitleNode);
      }
    });

    escalateItem.addEventListener('click', escalate);
    endItem.addEventListener('click', endChat);

    // Warm resume in the background so opening the widget feels instant.
    tryResumeSession();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();
