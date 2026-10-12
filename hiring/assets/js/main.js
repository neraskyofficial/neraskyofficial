/* Nerasky — main.js (vanilla, offline-first) */
(function () {
  'use strict';
  var qs = function (s, c) { return (c || document).querySelector(s); };
  var qsa = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

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
      if (drop && !drop.contains(e.target)) {
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
  var msgThread = qs('#msg-thread-box');
  if (msgThread) {
    var convUrl = msgThread.getAttribute('data-conv-url');
    var stickMsg = function () {
      var nearBottom = msgThread.scrollHeight - msgThread.scrollTop - msgThread.clientHeight < 160;
      if (nearBottom) msgThread.scrollTop = msgThread.scrollHeight;
    };
    stickMsg();
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
      if (f) label.textContent = f.name;
    });
  });

  /* Dropzone click opens the file picker */
  qsa('.dropzone').forEach(function (dz) {
    dz.addEventListener('click', function () {
      var inp = dz.querySelector('input[type=file]');
      if (inp) inp.click();
    });
  });

  /* Minor consent toggle */
  var minorChk = qs('#minorChk');
  if (minorChk) {
    var consentWrap = qs('#consentWrap');
    var note = qs('.auth-minor-note');
    var sync = function () {
      var on = minorChk.checked;
      if (consentWrap) consentWrap.classList.toggle('hidden', !on);
      if (note) note.classList.toggle('show', on);
      if (consentWrap) {
        var c = qs('input[type=file]', consentWrap);
        if (c) c.required = on;
      }
    };
    minorChk.addEventListener('change', function () {
      var row = minorChk.closest('.check-row');
      if (row) row.classList.toggle('on', minorChk.checked);
      sync();
    });
    sync();
  }

  /* Auto-dismiss banner X */
  qsa('.banner .b-x').forEach(function (x) {
    x.addEventListener('click', function () { x.closest('.banner').remove(); });
  });

  /* Profile customizer — live frame & accent preview */
  var cust = qs('#custPanel');
  if (cust) {
    var prev = qs('#custPrev');
    var accents = {};
    try { accents = JSON.parse(cust.getAttribute('data-accents') || '{}'); } catch (e) { accents = {}; }
    var frame = cust.getAttribute('data-frame') || 'none';
    var accent = cust.getAttribute('data-accent') || 'indigo';

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
    syncSel();
  }
})();