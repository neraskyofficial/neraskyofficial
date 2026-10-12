/* Nerasky — main.js (vanilla, offline-first) */
(function () {
  'use strict';
  var qs = function (s, c) { return (c || document).querySelector(s); };
  var qsa = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Lightweight password feedback before the form is submitted. */
  var passwordStrength = qs('[data-password-strength]');
  var strengthLabel = qs('#passwordStrength');
  if (passwordStrength && strengthLabel) {
    passwordStrength.addEventListener('input', function () {
      var value = passwordStrength.value;
      var score = (value.length >= 8 ? 1 : 0) + (/[A-Z]/.test(value) ? 1 : 0) + (/[0-9]/.test(value) ? 1 : 0) + (/[^A-Za-z0-9]/.test(value) ? 1 : 0);
      strengthLabel.textContent = value === '' ? '' : (score < 2 ? 'Weak password' : (score < 4 ? 'Good password' : 'Strong password'));
      strengthLabel.className = 'password-strength ' + (score < 2 ? 'weak' : (score < 4 ? 'good' : 'strong'));
    });
  }

  /* Mobile nav */
  var navToggle = qs('#navToggle');
  var mainNav = qs('#mainNav');
  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var open = mainNav.classList.toggle('open');
      navToggle.classList.toggle('open', open);
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* Service work showcase gallery */
  qsa('[data-service-gallery]').forEach(function (gallery) {
    var mainImage = qs('[data-gallery-main]', gallery);
    var mainLink = mainImage ? mainImage.closest('a') : null;
    qsa('[data-gallery-thumb]', gallery).forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        if (!mainImage || !mainLink) return;
        var imageUrl = thumb.getAttribute('data-image-url') || '';
        var imageAlt = thumb.getAttribute('data-image-alt') || '';
        if (!imageUrl) return;
        mainImage.src = imageUrl;
        mainImage.alt = imageAlt;
        mainLink.href = imageUrl;
        qsa('[data-gallery-thumb]', gallery).forEach(function (item) {
          var active = item === thumb;
          item.classList.toggle('active', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
      });
    });
  });

  /* Browse category filter */
  var categoryToggle = qs('[data-category-toggle]');
  var extraCategories = qs('#extraCategories');
  if (categoryToggle && extraCategories) {
    categoryToggle.addEventListener('click', function () {
      var expanded = categoryToggle.getAttribute('aria-expanded') === 'true';
      categoryToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      categoryToggle.textContent = expanded ? 'See all categories' : 'Show fewer categories';
      extraCategories.hidden = expanded;
    });
  }

  /* User dropdown */
  var btn = qs('#userMenuBtn');
  var drop = qs('#userMenuDrop');
  if (btn && drop) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = drop.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (drop && !drop.contains(e.target) && !btn.contains(e.target)) {
        drop.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        drop.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* Toasts */
  qsa('.toast').forEach(function (t) {
    var kill = function () {
      t.classList.add('leaving');
      setTimeout(function () { t.remove(); }, 340);
    };
    var x = qs('.toast-x', t);
    if (x) x.addEventListener('click', kill);
    setTimeout(kill, 5200);
  });

  /* Confirm modal */
  var backdrop = qs('#confirmBackdrop');
  if (backdrop) {
    var confirmText = qs('#confirmText');
    var confirmOk = qs('#confirmOk');
    var current = null;
    qsa('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        var msg = el.getAttribute('data-confirm') || 'Are you sure?';
        current = el;
        confirmText.textContent = msg;
        backdrop.classList.remove('hidden');
        e.preventDefault();
      });
    });
    var close = function () { backdrop.classList.add('hidden'); current = null; };
    qs('#confirmCancel').addEventListener('click', close);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
    confirmOk.addEventListener('click', function () {
      if (!current) return;
      var form = current.closest('form');
      if (form) { form.submit(); } else { window.location.href = current.getAttribute('href') || location.href; }
    });
  }

  /* Notification bell + dropdown */
  var notifBtn = qs('#notifBtn');
  var notifDrop = qs('#notifDrop');
  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = notifDrop.classList.toggle('open');
      notifBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (notifDrop && !notifDrop.contains(e.target) && !notifBtn.contains(e.target)) {
        notifDrop.classList.remove('open');
        notifBtn.setAttribute('aria-expanded', 'false');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        notifDrop.classList.remove('open');
        notifBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* Mark all notifications read */
  var markAllBtn = qs('#notifMarkAll');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      var csrf = document.body.getAttribute('data-csrf') || '';
      fetch('notif_readall.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'Nerasky' },
        credentials: 'same-origin',
        body: 'csrf=' + encodeURIComponent(csrf)
      }).then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            qsa('.notif-item.unread').forEach(function (it) { it.classList.remove('unread'); });
            var dot = qs('.notif-dot');
            if (dot) dot.remove();
            var ma = qs('#notifMarkAll');
            if (ma) ma.remove();
          }
        }).catch(function () {});
    });
  }

  /* Live badge polling (messages + notifications) */
  var badgeMsg = qs('a[href*="messages.php"] .nav-badge');
  var notifDot = qs('.notif-dot');
  if (badgeMsg || notifDot) {
    setInterval(function () {
      fetch('notif_tick.php', { headers: { 'X-Requested-With': 'Nerasky' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) return;
          if (badgeMsg) {
            badgeMsg.textContent = (d.msgs > 9) ? '9+' : String(d.msgs);
            badgeMsg.style.display = d.msgs > 0 ? 'inline-flex' : 'none';
          }
          if (notifDot) {
            notifDot.textContent = (d.notifs > 9) ? '9+' : String(d.notifs);
            notifDot.style.display = d.notifs > 0 ? 'inline-flex' : 'none';
          }
        }).catch(function () {});
    }, 12000);
  }

  /* Direct-message thread polling */
  var messagesLayout = qs('#messagesLayout');
  if (messagesLayout) {
    var chatShell = qs('.msg-thread', messagesLayout);
    var scrollChatIntoView = function () {
      if (!chatShell) return;
      if (window.matchMedia('(max-width: 820px)').matches) {
        window.scrollTo({ top: Math.max(0, chatShell.getBoundingClientRect().top + window.scrollY - 76), behavior: 'smooth' });
      } else {
        chatShell.scrollIntoView({ block: 'start' });
      }
    };
    var messageSearch = qs('[data-message-search]', messagesLayout);
    if (messageSearch) {
      messageSearch.addEventListener('input', function () {
        var term = messageSearch.value.trim().toLowerCase();
        qsa('[data-message-search-item]', messagesLayout).forEach(function (item) {
          item.hidden = term !== '' && (item.getAttribute('data-message-search-item') || '').indexOf(term) === -1;
        });
      });
    }
    /* Mobile pull-to-refresh for live inbox/order pages. */
    if (/\/(messages|orders)\.php$/.test(window.location.pathname)) {
      var refreshStartY = 0;
      document.addEventListener('touchstart', function (event) {
        if (window.scrollY === 0 && event.touches[0]) refreshStartY = event.touches[0].clientY;
      }, { passive: true });
      document.addEventListener('touchend', function (event) {
        var endY = event.changedTouches[0] ? event.changedTouches[0].clientY : refreshStartY;
        if (refreshStartY > 0 && endY - refreshStartY > 100 && window.scrollY === 0) window.location.reload();
        refreshStartY = 0;
      }, { passive: true });
    }
    var noteBackdrop = qs('#noteDetailBackdrop');
    var noteBody = qs('#noteDetailBody');
    var noteReplies = qs('#noteDetailReplies');
    var noteTitle = qs('#noteDetailTitle');
    var noteReply = qs('#noteDetailReply');
    var closeNote = function () { if (noteBackdrop) noteBackdrop.classList.add('hidden'); };
    qsa('[data-note]', messagesLayout).forEach(function (note) {
      note.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (noteBody) noteBody.textContent = note.getAttribute('data-note') || '';
        if (noteTitle) noteTitle.textContent = note.getAttribute('data-note-name') + "'s note";
        if (noteReplies) {
          var replyCount = parseInt(note.getAttribute('data-note-replies') || '0', 10);
          noteReplies.textContent = replyCount + (replyCount === 1 ? ' reply' : ' replies');
        }
        if (noteReply) noteReply.setAttribute('href', note.getAttribute('data-note-reply-url') || '#');
        if (noteBackdrop) noteBackdrop.classList.remove('hidden');
      });
    });
    qsa('[data-conversation-link]', messagesLayout).forEach(function (link) {
      link.addEventListener('click', function (event) {
        if (event.defaultPrevented) return;
        var href = link.getAttribute('href');
        if (!href) return;
        event.preventDefault();
        window.location.assign(href);
      });
    });
    if (qs('#noteDetailClose')) qs('#noteDetailClose').addEventListener('click', closeNote);
    if (noteBackdrop) noteBackdrop.addEventListener('click', function (event) { if (event.target === noteBackdrop) closeNote(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeNote(); });
    qsa('[data-open-presence]', messagesLayout).forEach(function (button) {
      button.addEventListener('click', function () {
        var toggle = qs('#presenceToggle');
        if (toggle && toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
        if (toggle) toggle.scrollIntoView({ block: 'center', behavior: 'smooth' });
      });
    });
    qsa('[data-chat-action]', messagesLayout).forEach(function (button) {
      button.addEventListener('click', function () {
        messagesLayout.classList.toggle('mobile-chat-open', button.getAttribute('data-chat-action') === 'show');
        messagesLayout.classList.toggle('mobile-chat-hidden', button.getAttribute('data-chat-action') === 'hide');
        if (button.getAttribute('data-chat-action') === 'show') {
          var thread = qs('#msg-thread-box');
          if (thread) {
            scrollChatIntoView();
            thread.scrollTop = thread.scrollHeight;
          }
        } else {
          var side = qs('.msg-side', messagesLayout);
          if (side) side.scrollIntoView({ block: 'start' });
        }
      });
    });
  }
  var msgThread = qs('#msg-thread-box');
  if (msgThread) {
    var convUrl = msgThread.getAttribute('data-conv-url');
    var messageForm = qs('#messageSendForm');
    var messageInput = messageForm ? qs('textarea[name="body"]', messageForm) : null;
    var jumpButton = qs('[data-chat-jump]');
    qsa('[data-message-template]').forEach(function (template) {
      template.addEventListener('click', function () {
        if (!messageInput) return;
        messageInput.value = template.getAttribute('data-message-template') || '';
        messageInput.focus();
      });
    });
    if (jumpButton) {
      jumpButton.addEventListener('click', function () { scrollLatest(); });
      msgThread.addEventListener('scroll', function () {
        var away = msgThread.scrollHeight - msgThread.scrollTop - msgThread.clientHeight > 180;
        jumpButton.classList.toggle('hidden', !away);
      });
    }
    var scrollLatest = function () {
      msgThread.scrollTop = msgThread.scrollHeight;
      window.requestAnimationFrame(function () {
        msgThread.scrollTop = msgThread.scrollHeight;
      });
      window.setTimeout(function () {
        msgThread.scrollTop = msgThread.scrollHeight;
      }, 120);
    };
    var stickMsg = function (force) {
      var nearBottom = msgThread.scrollHeight - msgThread.scrollTop - msgThread.clientHeight < 160;
      if (force || nearBottom) scrollLatest();
    };
    scrollLatest();
    if (window.location.hash === '#msg-thread-box' || new URLSearchParams(window.location.search).get('sent') === '1') {
      scrollChatIntoView();
      if (messageInput) {
        window.setTimeout(function () { messageInput.focus({ preventScroll: true }); }, 50);
      }
    } else {
      scrollChatIntoView();
      scrollLatest();
    }
    if (messageInput && messageForm) {
      var typingIndicator = qs('#typingIndicator');
      var normalTypingText = typingIndicator ? typingIndicator.textContent : '';
      messageInput.addEventListener('input', function () {
        if (!typingIndicator) return;
        typingIndicator.textContent = messageInput.value.trim() ? 'You’re typing…' : normalTypingText;
      });
      messageInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
          event.preventDefault();
          if (messageInput.value.trim() !== '') messageForm.requestSubmit();
        }
      });
    }
    setInterval(function () {
      fetch(convUrl + '&fragment=thread', { headers: { 'X-Requested-With': 'Nerasky' }, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (html && html.trim()) { msgThread.innerHTML = html; stickMsg(); }
        })
        .catch(function () {});
    }, 4000);
  }

  /* Compose drawer */
  var composeBtn = qs('#composeBtn');
  var composeBackdrop = qs('#composeBackdrop');
  if (composeBtn && composeBackdrop) {
    composeBtn.addEventListener('click', function () { composeBackdrop.classList.remove('hidden'); });
    var composeClose = qs('#composeClose');
    if (composeClose) composeClose.addEventListener('click', function () { composeBackdrop.classList.add('hidden'); });
    composeBackdrop.addEventListener('click', function (e) { if (e.target === composeBackdrop) composeBackdrop.classList.add('hidden'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') composeBackdrop.classList.add('hidden'); });
  }

  /* Star rating widget */
  qsa('[data-rating]').forEach(function (wrap) {
    var input = qs('input', wrap);
    var stars = qsa('.rate-star', wrap);
    var val = parseInt(input.value, 10) || 0;
    var paint = function (v) {
      stars.forEach(function (s, i) { s.classList.toggle('on', i < v); });
    };
    stars.forEach(function (s, i) {
      s.addEventListener('click', function () { input.value = i + 1; val = i + 1; paint(val); });
      s.addEventListener('mouseenter', function () { paint(i + 1); });
    });
    wrap.addEventListener('mouseleave', function () { paint(val); });
    paint(val);
  });

  /* Chat polling */
  var chatBox = qs('#chat-box');
  if (chatBox) {
    var orderId = chatBox.getAttribute('data-order');
    var page = window.location.pathname.split('/').pop();
    var pollUrl = page + '?order=' + orderId + '&fragment=messages';
    var stick = function () {
      var nearBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 160;
      if (nearBottom) chatBox.scrollTop = chatBox.scrollHeight;
    };
    stick();
    setInterval(function () {
      fetch(pollUrl, { headers: { 'X-Requested-With': 'Nerasky' }, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (html && html.trim()) { chatBox.innerHTML = html; stick(); }
        })
        .catch(function () {});
    }, 4000);
  }

  /* Copy reference numbers */
  qsa('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var t = el.getAttribute('data-copy');
      var p = navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject(new Error('no clip'));
      p.then(function () {
        var old = el.textContent;
        el.textContent = 'Copied ✓';
        setTimeout(function () { el.textContent = old; }, 1500);
      }).catch(function () {});
    });
  });

  /* File input -> label */
  qsa('[data-file-label]').forEach(function (inp) {
    var label = qs(inp.getAttribute('data-file-label'));
    if (!label) return;
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (f) {
        label.textContent = f.name;
        var wrapper = inp.closest('.msg-attach-label');
        if (wrapper) wrapper.classList.add('has-file');
      } else {
        var emptyWrapper = inp.closest('.msg-attach-label');
        if (emptyWrapper) emptyWrapper.classList.remove('has-file');
      }
    });
  });

  /* Dropzone click opens the file picker */
  qsa('.dropzone').forEach(function (dz) {
    dz.addEventListener('click', function () {
      var inp = dz.querySelector('input[type=file]');
      if (inp) inp.click();
    });
  });

  /* Age gate — DOB drives the 15+ rule and the consent requirement */
  var dobInput = qs('#dobInput');
  if (dobInput) {
    var consentWrap = qs('#consentWrap');
    var ageNote = qs('#ageNote');
    var ageNoteWrap = qs('#ageNoteWrap');
    var regSubmit = qs('#regSubmit');
    var idType = qs('#idType');
    var sync = function () {
      var v = dobInput.value;
      if (!v) {
        if (ageNoteWrap) ageNoteWrap.classList.add('hidden');
        if (regSubmit) regSubmit.disabled = false;
        return;
      }
      var d = new Date(v + 'T00:00:00');
      var now = new Date();
      var age = now.getFullYear() - d.getFullYear();
      var m = now.getMonth() - d.getMonth();
      if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
      var minor = age >= 15 && age < 18;
      var blocked = age < 15 || age > 100;
      if (ageNoteWrap) ageNoteWrap.classList.remove('hidden');
      if (ageNote) {
        ageNote.className = 'banner ' + (blocked ? 'banner-bad' : (minor ? 'banner-warn' : 'banner-info'));
        ageNote.innerHTML = blocked
          ? '<span class="b-ic">✕</span><span><b>Nerasky is open to students 15 and up.</b> You cannot sign up yet.</span>'
          : (minor
            ? '<span class="b-ic">!</span><span>Age <b>' + age + '</b> — a signed parental consent form is required.</span>'
            : '<span class="b-ic">✓</span><span>Age <b>' + age + '</b> — school or government ID accepted.</span>');
      }
      if (consentWrap) consentWrap.classList.toggle('hidden', !minor);
      if (consentWrap) {
        var c = qs('input[type=file]', consentWrap);
        if (c) c.required = minor;
      }
      if (idType) {
        if (minor) idType.value = 'school';
        idType.disabled = minor;
      }
      if (regSubmit) regSubmit.disabled = blocked;
    };
    dobInput.addEventListener('change', sync);
    dobInput.addEventListener('input', sync);
    sync();
  }

  /* Auto-dismiss banner X */
  qsa('.banner .b-x').forEach(function (x) {
    x.addEventListener('click', function () { x.closest('.banner').remove(); });
  });

  /* Profile customizer — live frame & accent preview */
  var cust = qs('#custPanel');
  if (cust) {
    var custToggle = qs('#custToggle');
    var custHide = qs('#custHide');
    var setCustomizerOpen = function (open, scroll) {
      cust.hidden = !open;
      if (custToggle) {
        custToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        custToggle.innerHTML = open ? 'Hide customize profile' : 'Show customize profile';
      }
      if (open && scroll) cust.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    if (custToggle) custToggle.addEventListener('click', function () {
      setCustomizerOpen(cust.hidden, true);
    });
    if (custHide) custHide.addEventListener('click', function () {
      setCustomizerOpen(false, false);
      if (custToggle) custToggle.focus();
    });
    var tabs = qsa('.customizer-tab', cust);
    var panels = qsa('.customizer-tab-panel', cust);
    var activateCustomizerTab = function (name) {
      tabs.forEach(function (tab) {
        var active = tab.getAttribute('data-customizer-tab') === name;
        tab.classList.toggle('active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('active', panel.getAttribute('data-customizer-panel') === name);
      });
    };
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activateCustomizerTab(tab.getAttribute('data-customizer-tab') || 'appearance');
      });
    });
    qsa('.profile-settings-link').forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        setCustomizerOpen(true, false);
        activateCustomizerTab('stats');
        cust.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    activateCustomizerTab('appearance');
    var prev = qs('#custPrev');
    var profileHero = qs('.profile-hero');
    var accents = {};
    try { accents = JSON.parse(cust.getAttribute('data-accents') || '{}'); } catch (e) { accents = {}; }
    var frame = cust.getAttribute('data-frame') || 'none';
    var accent = cust.getAttribute('data-accent') || 'indigo';
    var layout = cust.getAttribute('data-layout') || 'classic';
    var progressStyle = (qs('.progress-style-option input:checked', cust) || {}).value || 'gradient';

    var syncSel = function () {
      qsa('.frame-swatch', cust).forEach(function (s) {
        var on = s.getAttribute('data-frame') === frame;
        s.classList.toggle('sel', on);
        var r = qs('input', s);
        if (r && !r.disabled) r.checked = on;
      });
      qsa('.accent-dot', cust).forEach(function (d) {
        var on = d.getAttribute('data-accent') === accent;
        d.classList.toggle('sel', on);
        var r2 = qs('input', d);
        if (r2) r2.checked = on;
      });
    };
    var applyFrame = function (f) {
      frame = f;
      if (prev) prev.className = 'avwrap avf-' + f;
      syncSel();
    };
    var applyAccent = function (a) {
      accent = a;
      var hex = accents[a] || '#6366F1';
      if (prev) prev.style.setProperty('--avc', hex);
      qsa('.frame-swatch .avwrap', cust).forEach(function (w) { w.style.setProperty('--avc', hex); });
      syncSel();
    };
    var applyLayout = function (l) {
      layout = l;
      if (profileHero) {
        profileHero.classList.remove('profile-layout-classic', 'profile-layout-spotlight', 'profile-layout-centered');
        profileHero.classList.add('profile-layout-' + l);
      }
      qsa('.layout-option', cust).forEach(function (option) {
        var input = qs('input', option);
        if (input && !input.disabled) input.checked = input.value === layout;
        option.classList.toggle('sel', input && input.value === layout);
      });
    };
    var applyProgressStyle = function (style) {
      progressStyle = style;
      qsa('.pro-performance .seller-progress, .profile-progress-card .seller-progress').forEach(function (bar) {
        bar.classList.remove('progress-fill-gradient', 'progress-fill-solid', 'progress-fill-sunset');
        bar.classList.add('progress-fill-' + progressStyle);
      });
      qsa('.profile-stat-grid').forEach(function (grid) {
        grid.classList.remove('progress-fill-gradient', 'progress-fill-solid', 'progress-fill-sunset');
        grid.classList.add('progress-fill-' + progressStyle);
      });
      qsa('.progress-style-option', cust).forEach(function (option) {
        var input = qs('input', option);
        if (input && !input.disabled) input.checked = input.value === progressStyle;
        option.classList.toggle('sel', !!input && input.value === progressStyle);
      });
    };

    qsa('.frame-swatch', cust).forEach(function (s) {
      s.addEventListener('click', function (ev) {
        ev.preventDefault();
        if (s.classList.contains('locked')) {
          s.classList.remove('shake');
          void s.offsetWidth;
          s.classList.add('shake');
          return;
        }
        applyFrame(s.getAttribute('data-frame'));
      });
    });
    qsa('.accent-dot', cust).forEach(function (d) {
      d.addEventListener('click', function (ev) {
        ev.preventDefault();
        applyAccent(d.getAttribute('data-accent'));
      });
    });
    qsa('.layout-option', cust).forEach(function (option) {
      option.addEventListener('click', function (ev) {
        var input = qs('input', option);
        if (!input || input.disabled) return;
        if (ev.target !== input) ev.preventDefault();
        applyLayout(input.value);
      });
      var input = qs('input', option);
      if (input) {
        input.addEventListener('change', function () {
          if (!input.disabled && input.checked) applyLayout(input.value);
        });
      }
    });
    qsa('.stat-visibility-option', cust).forEach(function (option) {
      var input = qs('input', option);
      var syncStat = function () {
        option.classList.toggle('sel', !!input && input.checked);
      };
      if (input) {
        input.addEventListener('change', syncStat);
        option.addEventListener('click', function (event) {
          if (event.target !== input && !input.disabled) {
            event.preventDefault();
            input.checked = !input.checked;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
        });
      }
      syncStat();
    });
    qsa('.progress-style-option', cust).forEach(function (option) {
      var input = qs('input', option);
      if (input) {
        option.addEventListener('click', function (event) {
          if (input.disabled) return;
          if (event.target !== input) event.preventDefault();
          input.checked = true;
          applyProgressStyle(input.value);
        });
        input.addEventListener('change', function () {
          if (!input.disabled && input.checked) applyProgressStyle(input.value);
        });
      }
    });
    applyLayout(layout);
    applyProgressStyle(progressStyle);
    syncSel();
  }
})();