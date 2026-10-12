/* Nerasky — anim.js · motion engine (vanilla, offline, zero dependencies)
   Owns ALL scroll-linked motion for both apps: progress bar, header state,
   parallax, reveal cascade (slide/zoom/tilt variants + blur-in), stagger
   groups, counters, pointer tilt, smooth anchors and the profile photo
   live-preview. No JS → everything visible (CSS is scoped to .js);
   prefers-reduced-motion → every effect stands down. */
(function () {
  'use strict';
  var qs = function (s, c) { return (c || document).querySelector(s); };
  var qsa = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var replayHomeReveals = document.body.getAttribute('data-nav') === 'home'
    && document.body.getAttribute('data-motion') === 'auto'
    && !reduced;
  document.body.classList.add('motion-ready');
  var cursorAura = document.createElement('div');
  cursorAura.className = 'market-cursor-aura';
  cursorAura.setAttribute('aria-hidden', 'true');
  document.body.appendChild(cursorAura);

  /* A shared, frame-throttled pointer light gives the marketplace the same
     soft, responsive depth as the marketing page without affecting hit tests. */
  if (!reduced) {
    var latestPointer = null;
    var pointerFrame = 0;
    document.addEventListener('pointermove', function (event) {
      if (event.pointerType !== 'mouse') return;
      latestPointer = { x: event.clientX, y: event.clientY };
      if (pointerFrame) return;
      pointerFrame = window.requestAnimationFrame(function () {
        pointerFrame = 0;
        if (!latestPointer) return;
        cursorAura.style.setProperty('--market-cursor-x', latestPointer.x + 'px');
        cursorAura.style.setProperty('--market-cursor-y', latestPointer.y + 'px');
        cursorAura.classList.add('is-visible');
        var hero = qs('.page-nav-home .hero');
        if (hero) {
          var rect = hero.getBoundingClientRect();
          if (latestPointer.x >= rect.left && latestPointer.x <= rect.right
            && latestPointer.y >= rect.top && latestPointer.y <= rect.bottom) {
            hero.style.setProperty('--market-spot-x', ((latestPointer.x - rect.left) / rect.width * 100).toFixed(1) + '%');
            hero.style.setProperty('--market-spot-y', ((latestPointer.y - rect.top) / rect.height * 100).toFixed(1) + '%');
          } else {
            hero.style.removeProperty('--market-spot-x');
            hero.style.removeProperty('--market-spot-y');
          }
        }
        latestPointer = null;
      });
    }, { passive: true });
    document.addEventListener('pointerover', function (event) {
      if (event.pointerType !== 'mouse') return;
      var target = event.target;
      cursorAura.classList.toggle('is-interactive', !!(target instanceof Element && target.closest('a, button, [role="button"], .interactive-glow')));
    });
    document.addEventListener('pointerout', function (event) {
      if (event.pointerType === 'mouse' && !event.relatedTarget) cursorAura.classList.remove('is-visible', 'is-interactive');
    });
    document.addEventListener('mouseleave', function () {
      cursorAura.classList.remove('is-visible', 'is-interactive');
    });
  }
  /* Never leave content hidden if an observer is delayed by a busy tab. */
  window.setTimeout(function () {
    qsa('.reveal:not(.in)').forEach(function (el) {
      var r = el.getBoundingClientRect();
      if (r.top < window.innerHeight * 1.15 && r.bottom > -40) el.classList.add('in');
    });
  }, 180);

  /* ---------- low-cost pointer glow for cards and buttons ---------------- */
  qsa('.svc-card, .step-card, .cat-tile, .card-pad, .panel, .stat-card, .mock-card, .role-start-card, .browse-quick-searches a, .feature-card, .faq-card, .guide-stage, .guide-hero-card, .guide-lifecycle, .guide-toc-group a, .fee-break, .pro-about-feature, .service-card, .recent-card, .support-quick-card, .service-detail-card, .pro-about-card, .category-filter .chip, .footer-social, .footer-text-link, .site-header nav a, .btn')
    .forEach(function (el) {
      el.classList.add('interactive-glow');
      if (reduced) return;
      el.addEventListener('pointermove', function (event) {
        if (event.pointerType === 'touch') return;
        var rect = el.getBoundingClientRect();
        el.style.setProperty('--glow-x', (event.clientX - rect.left) + 'px');
        el.style.setProperty('--glow-y', (event.clientY - rect.top) + 'px');
      });
    });

  /* Native-feeling press feedback without a UI framework dependency. */
  if (!reduced) {
    qsa('.btn').forEach(function (button) {
      button.addEventListener('pointerdown', function (event) {
        var rect = button.getBoundingClientRect();
        var ripple = document.createElement('span');
        ripple.className = 'ripple';
        ripple.style.left = (event.clientX - rect.left) + 'px';
        ripple.style.top = (event.clientY - rect.top) + 'px';
        button.appendChild(ripple);
        window.setTimeout(function () { ripple.remove(); }, 600);
      });
    });
  }

  /* ---------- scroll progress + header state + parallax (one rAF loop) --- */
  var bar = document.createElement('div');
  bar.id = 'scrollProgress';
  bar.setAttribute('aria-hidden', 'true');
  document.body.appendChild(bar);

  var head = qs('.site-header') || qs('.admin-topbar');
  var px = qsa('[data-parallax]');
  var raf = false;

  function frame() {
    raf = false;
    var doc = document.documentElement;
    var y = window.scrollY || doc.scrollTop || 0;
    var max = doc.scrollHeight - doc.clientHeight;
    bar.style.width = (max > 0 ? (y / max) * 100 : 0) + '%';
    if (head) head.classList.toggle('scrolled', y > 8);
    for (var i = 0; i < px.length; i++) {
      var sp = parseFloat(px[i].getAttribute('data-parallax')) || 0.2;
      px[i].style.transform = 'translate3d(0,' + (y * sp).toFixed(1) + 'px,0)';
    }
  }
  function onScroll() { if (!raf) { raf = true; window.requestAnimationFrame(frame); } }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  frame();

  /* ---------- pointer tilt for hero / mock cards -------------------------- */
  if (!reduced) {
    qsa('[data-tilt]').forEach(function (card) {
      var r = null;
      card.addEventListener('pointermove', function (e) {
        r = r || card.getBoundingClientRect();
        var dx = (e.clientX - r.left) / r.width - 0.5;
        var dy = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = 'perspective(900px) rotateX(' + (-dy * 5).toFixed(2) + 'deg) rotateY(' + (dx * 5).toFixed(2) + 'deg) translateY(-4px)';
      });
      card.addEventListener('pointerleave', function () { card.style.transform = ''; });
    });
  }

  /* More homepage cards get a restrained, pointer-driven depth response. */
  qsa('.page-nav-home .role-start-card, .page-nav-home .home-category-grid .cat-tile, .page-nav-home .home-feature-grid .feature-card, .page-nav-home .home-step-grid .step-card, .page-nav-home .home-service-grid .service-card')
    .forEach(function (card) {
      card.classList.add('market-depth');
      if (reduced) return;
      card.addEventListener('pointermove', function (event) {
        if (event.pointerType !== 'mouse') return;
        var rect = card.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        var x = Math.max(-.5, Math.min(.5, (event.clientX - rect.left) / rect.width - .5));
        var y = Math.max(-.5, Math.min(.5, (event.clientY - rect.top) / rect.height - .5));
        card.style.setProperty('--market-tilt-x', (x * 4).toFixed(2) + 'deg');
        card.style.setProperty('--market-tilt-y', (y * -4).toFixed(2) + 'deg');
      });
      card.addEventListener('pointerleave', function () {
        card.style.removeProperty('--market-tilt-x');
        card.style.removeProperty('--market-tilt-y');
      });
    });

  /* ---------- buttery same-page anchors (guide TOC etc.) ------------------ */
  qsa('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href').slice(1);
      var t = id && /^[A-Za-z][A-Za-z0-9_-]*$/.test(id) ? document.getElementById(id) : null;
      if (!t) return;
      e.preventDefault();
      window.scrollTo({ top: t.getBoundingClientRect().top + (window.scrollY || 0) - 84, behavior: reduced ? 'auto' : 'smooth' });
      history.replaceState(null, '', '#' + id);
    });
  });

  /* ---------- live photo preview in the profile customizer ---------------- */
  var avIn = qs('#avatarInput');
  var prev = qs('#custPrev');
  if (avIn && prev) {
    avIn.addEventListener('change', function () {
      var f = avIn.files && avIn.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function () {
        var img = qs('img.avatar-photo', prev);
        if (img) { img.src = r.result; }
        else { prev.innerHTML = '<img class="avatar avatar-photo" width="76" height="76" src="' + r.result + '" alt="">'; }
      };
      r.readAsDataURL(f);
    });
  }

  /* ---------- reveal cascade --------------------------------------------- */
  /* Admin pages mark <body data-auto-anim>: cards/panels/rows opt in here. */
  if (document.body.hasAttribute('data-auto-anim')) {
    qsa('.admin-hero, .stat-card, .card-pad, .panel-card, .order-row, .admin-table, .table-wrap')
      .forEach(function (el, i) {
        if (el.classList.contains('reveal')) return;
        el.classList.add('reveal');
        if (i % 8 > 0) el.style.transitionDelay = (Math.min(i % 8, 5) * 55) + 'ms';
      });
  }

  /* Marketplace auto-motion (body[data-motion="auto"]). MUST run before the
     observer below so freshly tagged elements are actually observed. */
  if (document.body.getAttribute('data-motion') === 'auto') {
    qsa('.svc-card, .stat-card, .panel, .card-pad, .empty-state, .table-wrap, .dash-side, .banner, .order-summary > *, .feature-card, .faq-card, .step-card, .guide-stage, .service-card, .recent-card, .role-start-card, .cat-tile, .support-quick-card, .service-detail-card, .pro-about-card')
      .forEach(function (el, i) {
        if (el.classList.contains('reveal')) return;
        el.classList.add('reveal');
        el.style.transitionDelay = (Math.min(i % 6, 5) * 60) + 'ms';
      });
    qsa('.grid-2, .grid-3, .grid-4, .dash-grid').forEach(function (g) {
      if (!g.hasAttribute('data-stagger')) g.setAttribute('data-stagger', '80');
    });
    if (document.body.getAttribute('data-nav') === 'home') {
      var homeRevealIndexes = new WeakMap();
      var homeRevealDirections = ['up', 'left', 'right', 'down'];
      qsa('.hero-badge, .hero-copy h1, .hero-copy .lead, .hero-cta > *, .hero-proofline > *, .hero-stats > div, .hero-art-stamp, .mock-card > *, .role-start-grid > *, .home-step-grid > *, .home-category-grid > *, .home-feature-grid > *, .home-service-grid > *, .home-notification-grid > *, .home-mission .kv, .home-final-cta .reveal > *')
        .forEach(function (el) {
          if (!el.classList.contains('reveal')) el.classList.add('reveal');
          var parent = el.parentElement;
          var index = homeRevealIndexes.get(parent) || 0;
          homeRevealIndexes.set(parent, index + 1);
          el.setAttribute('data-reveal-direction', homeRevealDirections[index % homeRevealDirections.length]);
          el.style.setProperty('--home-reveal-delay', Math.min(index, 5) * 70 + 'ms');
        });
      qsa('.home-category-grid, .home-feature-grid, .home-service-grid, .home-step-grid, .role-start-grid, .home-notification-grid')
        .forEach(function (group) {
          if (!group.hasAttribute('data-stagger')) group.setAttribute('data-stagger', '85');
        });
    }
    if (document.body.getAttribute('data-nav') === 'guide' || document.body.getAttribute('data-nav') === 'about') {
        qsa('.guide-hero-copy > *, .guide-hero-card > *, .guide-toc-group, .guide-lifecycle, .guide-stage, .step-card, .feature-card, .faq-card, .pro-about-feature, .fee-break, .dark-band .center > *, .hero .center > *')
          .forEach(function (el) {
            if (!el.classList.contains('reveal')) el.classList.add('reveal');
          });
        qsa('.grid-2, .grid-3, .grid-4').forEach(function (group) {
          var cards = qsa(':scope > .step-card, :scope > .feature-card, :scope > .faq-card, :scope > .guide-stage, :scope > .card', group);
          if (cards.length < 2) return;
          if (!group.hasAttribute('data-stagger')) group.setAttribute('data-stagger', '75');
          cards.forEach(function (card) {
            if (!card.classList.contains('reveal')) card.classList.add('reveal');
          });
        });
    }
  }
  /* Stagger groups: children of [data-stagger] cascade in. */
  qsa('[data-stagger]').forEach(function (group) {
    var step = parseFloat(group.getAttribute('data-stagger')) || 70;
    qsa('.reveal', group).forEach(function (el, i) {
      if (!/(^|\s)reveal-delay-/.test(el.className)) el.style.transitionDelay = Math.min(i * step, 480) + 'ms';
    });
  });

  /* Table row cascade (only wraps that actually reveal). */
  qsa('.table-wrap.reveal').forEach(function (wrap) {
    qsa('tbody tr', wrap).forEach(function (tr, i) {
      tr.classList.add('row-in');
      tr.style.transitionDelay = Math.min(i * 45, 420) + 'ms';
    });
  });

  var reveals = qsa('.reveal');
  if (reveals.length) {
    var show = function (el) { el.classList.add('in'); };
    if (reduced || !('IntersectionObserver' in window)) {
      reveals.forEach(show);
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (replayHomeReveals) {
            en.target.classList.toggle('in', en.isIntersecting);
          } else if (en.isIntersecting) {
            show(en.target);
            io.unobserve(en.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });
      reveals.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.top < window.innerHeight * 0.92 && r.bottom > 0) show(el); /* already on screen */
        if (replayHomeReveals || !(r.top < window.innerHeight * 0.92 && r.bottom > 0)) io.observe(el);
      });
      /* Fail-safe: nothing may ever stay hidden. After load, force-show any
         revealed element the observer somehow missed that is on screen. */
      window.addEventListener('load', function () {
        setTimeout(function () {
          qsa('.reveal:not(.in)').forEach(function (el) {
            var r = el.getBoundingClientRect();
            if (r.top < window.innerHeight && r.bottom > 0) show(el);
          });
        }, 320);
      });
    }
  }

  /* ---------- animated stat counters -------------------------------------- */
  var counters = qsa('[data-count]');
  var runCounter = function (el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var dec = parseInt(el.getAttribute('data-decimals') || '0', 10);
    if (reduced || !('requestAnimationFrame' in window)) { el.textContent = target.toFixed(dec); return; }
    var t0 = null, dur = 1200;
    var tick = function (ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min((ts - t0) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3); /* easeOutCubic */
      el.textContent = (target * eased).toFixed(dec);
      if (p < 1) window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);
  };
  if (counters.length) {
    if (!('IntersectionObserver' in window) || reduced) {
      counters.forEach(runCounter);
    } else {
      var cio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { runCounter(en.target); cio.unobserve(en.target); }
        });
      }, { threshold: 0.4 });
      counters.forEach(function (el) { cio.observe(el); });
    }
  }

  /* ---------- light 3D tilt on service cards ------------------------------ */
  if (!reduced) {
    qsa('.svc-card').forEach(function (card) {
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        var dx = (e.clientX - r.left) / r.width - 0.5;
        var dy = (e.clientY - r.top) / r.height - 0.5;
        card.style.transform = 'translateY(-6px) rotateX(' + (-dy * 2.2).toFixed(2) + 'deg) rotateY(' + (dx * 2.2).toFixed(2) + 'deg)';
      });
      card.addEventListener('pointerleave', function () { card.style.transform = ''; });
    });
  }

  /* ---------- guide timeline scroll-draw ----------------------------------- */
  var firstTl = qs('.tl-item');
  if (firstTl && !reduced) {
    var tlBox = firstTl.parentElement;
    if (tlBox && tlBox !== document.body) {
      tlBox.classList.add('guide-timeline');
      var rail = document.createElement('div');
      rail.className = 'tl-rail';
      rail.setAttribute('aria-hidden', 'true');
      var fill = document.createElement('div');
      fill.className = 'tl-fill';
      rail.appendChild(fill);
      tlBox.appendChild(rail);
      var tlUpd = function () {
        var r = tlBox.getBoundingClientRect();
        var p = Math.min(1, Math.max(0, (window.innerHeight * 0.78 - r.top) / (r.height || 1)));
        fill.style.height = (p * 100).toFixed(1) + '%';
      };
      window.addEventListener('scroll', tlUpd, { passive: true });
      window.addEventListener('resize', tlUpd);
      tlUpd();
    }
  }
})();
