(function () {
  'use strict';

  var config = window.NERASKY_CONFIG || {
    homeUrl: '../index.php',
    browseUrl: '../browse.php',
    guideUrl: '../guide.php',
    authUrl: '../register.php',
    authLabel: 'Join free',
    dashboardUrl: '../login.php',
    dashboardLabel: 'Visit Nerasky',
    termsUrl: '../terms.php',
    privacyUrl: '../privacy.php',
    logoUrl: '../assets/nerasky-logo.png'
  };

  var features = [
    ['01', 'Find your edge', 'Discover thoughtful services from students who understand the pressure, pace, and possibility of campus life.', '⌕', config.browseUrl],
    ['02', 'Share what you know', 'Turn the skills you already have into meaningful work, visible proof, and momentum before graduation.', '✦', config.authUrl],
    ['03', 'Move with confidence', 'Clear profiles, fair economics, and human support make every next step feel more considered.', '↗', config.guideUrl]
  ];
  var useCases = [
    ['BUYERS', 'Find help that fits', 'Browse student services for tutoring, design, writing, coding, and more. Compare real profiles and service details before you choose.', '⌕', 'Explore services', config.browseUrl],
    ['SELLERS', 'Turn a skill into paid work', 'Create a profile, publish the services you are ready to offer, and build experience one project at a time.', '✦', 'Offer your skills', config.authUrl],
    ['EVERY STEP', 'Keep the next move clear', 'Review the order details, delivery expectations, and platform fee before you commit to a service.', '↗', 'Read the guide', config.guideUrl]
  ];

  function link(url, text, className) {
    return '<a class="' + (className || '') + '" href="' + url + '">' + text + '</a>';
  }

  function render() {
    var featureMarkup = features.map(function (item) {
      return '<article class="feature-card glass-panel reveal"><span class="feature-number">' + item[0] +
        '</span><span class="feature-icon">' + item[3] + '</span><h3>' + item[1] +
        '</h3><p>' + item[2] + '</p>' + link(item[4], 'Explore <span>↗</span>') + '</article>';
    }).join('');
    var useCaseMarkup = useCases.map(function (item) {
      return '<article class="feature-card glass-panel reveal"><span class="feature-number">' + item[0] +
        '</span><span class="feature-icon">' + item[3] + '</span><h3>' + item[1] +
        '</h3><p>' + item[2] + '</p>' + link(item[5], item[4] + ' <span>↗</span>') + '</article>';
    }).join('');

    document.getElementById('root').innerHTML =
      '<div class="landing-shell">' +
      '<div class="scroll-progress" aria-hidden="true"><span></span></div>' +
      '<div class="cursor-aura" aria-hidden="true"></div>' +
      '<header class="site-nav"><a class="brand" href="' + config.homeUrl + '"><img class="brand-logo" src="' +
      config.logoUrl + '" alt=""><span>Nerasky</span></a><nav class="nav-links" id="nav-links">' +
      link('#features', 'Why Nerasky') + link('#stories', 'Use cases') + link(config.browseUrl, 'Marketplace') +
      (config.careersUrl ? link(config.careersUrl, 'Careers') : '') +
      '</nav><div class="nav-actions">' + link(config.dashboardUrl, config.dashboardLabel, 'nav-login') +
      link(config.authUrl, config.authLabel + '<span>↗</span>', 'button') +
      '<button class="mobile-menu" id="mobile-menu" type="button" aria-label="Toggle navigation" aria-controls="nav-links" aria-expanded="false">☰</button></div></header>' +
      '<main><section class="hero"><div class="hero-media"></div><div class="hero-orbit hero-orbit-one"></div><div class="hero-orbit hero-orbit-two"></div><div class="hero-orbit hero-orbit-three"></div><div class="hero-scrim"></div><div class="hero-grid"></div><div class="hero-particles" aria-hidden="true"></div><svg class="hero-doodle" viewBox="0 0 220 150" fill="none" aria-hidden="true"><path class="doodle-stroke" d="M12 92C39 42 86 24 124 45c31 17 35 53 11 70-24 17-62 6-64-16-2-17 21-30 38-21"/><path class="doodle-stroke doodle-spark" d="m166 27 5 14 14 5-14 5-5 14-5-14-14-5 14-5 5-14Z"/><path class="doodle-stroke doodle-tail" d="M153 111c17 2 27 9 34 21"/></svg>' +
      '<div class="hero-content reveal"><p class="eyebrow"><span class="eyebrow-dot"></span>Built by students · for students</p><h1><span class="hero-line"><span>Your talent</span></span><em class="hero-line"><span>deserves more</span></em><span class="hero-line"><span>than just</span></span><span class="hero-line"><span>a hobby.</span></span></h1><svg class="landing-title-underline" viewBox="0 0 420 18" preserveAspectRatio="none" aria-hidden="true"><path d="M3 13C74 6 111 8 174 10c62 2 126-1 241-8" /></svg><p class="hero-copy">Turn what you love doing into opportunities that pay.</p><div class="hero-actions">' +
      link(config.browseUrl, 'Find student services <span>↗</span>', 'button') + link(config.authUrl, 'Offer your skills <span>↗</span>', 'text-link') +
      '</div><div class="hero-proof"><span class="proof-mark" aria-hidden="true">✦</span><span><strong>Browse real student listings.</strong><small>Profiles, prices and service details up front.</small></span></div></div>' +
      '<div class="hero-card glass-panel reveal"><div class="card-label"><span class="preview-mark" aria-hidden="true">✦</span>ILLUSTRATIVE PREVIEW <span>EXAMPLE</span></div><div class="card-user"><span class="avatar">SC</span><span><strong>Student creator</strong><small>Brand design · Example profile</small></span></div><div class="service-preview"><small>EXAMPLE SERVICE</small><h2>Make your brand impossible to scroll past.</h2><div><strong>₱450</strong><span>Illustrative price</span></div></div><div class="card-footer"><span>Sample only · browse for current offers</span></div></div></section>' +
      '<section class="trust-row"><div><strong>95%</strong><span>stays with student sellers</span></div><div><strong>FREE</strong><span>to join and create a profile</span></div><div><strong>5%</strong><span>flat platform fee</span></div></section>' +
      '<section class="content-section" id="features"><div class="section-kicker">01 / WHY NERASKY</div><div class="section-heading"><h2>Your skills deserve a <em>stage.</em></h2><p>Everything you need to turn capability into confidence, without losing what makes it personal.</p></div><div class="feature-grid">' + featureMarkup + '</div></section>' +
      '<section class="stories-section" id="stories"><div class="section-kicker">02 / WAYS TO USE NERASKY</div><div class="section-heading"><h2>Different goals.<br><em>One place to start.</em></h2><p>Whether you need a hand or want to offer one, see what the next step looks like before you begin.</p></div><div class="feature-grid">' + useCaseMarkup + '</div></section>' +
      '<section class="final-cta"><div class="cta-orbit"></div><div class="section-kicker">03 / YOUR NEXT MOVE</div><h2>Start small.<br><em>Move boldly.</em></h2><p>Browse your next opportunity or publish your first service in minutes.</p>' + link(config.authUrl, config.authLabel === 'Join free' ? 'Create your free account <span>↗</span>' : 'Post your first service <span>↗</span>', 'button button-light') + '<small class="cta-note">No complicated setup. Just your skills and your next move.</small></section></main>' +
      '<footer class="sticky-footer"><a class="brand" href="' + config.homeUrl + '"><img class="brand-logo" src="' + config.logoUrl + '" alt=""><span>Nerasky</span></a><span>Made for student ambition · ' + new Date().getFullYear() + '</span><div>' + link(config.termsUrl, 'Terms') + link(config.privacyUrl, 'Privacy') + (config.careersUrl ? link(config.careersUrl, 'Careers') : '') + link(config.browseUrl, 'Marketplace ↗') + '</div></footer></div>';
  }

  function activate() {
    var menu = document.getElementById('mobile-menu');
    var nav = document.getElementById('nav-links');
    if (menu) menu.addEventListener('click', function () {
      nav.classList.toggle('nav-links-open');
      menu.setAttribute('aria-expanded', String(nav.classList.contains('nav-links-open')));
    });
    if (nav && menu) {
      nav.querySelectorAll('a').forEach(function (navLink) {
        navLink.addEventListener('click', function () {
          nav.classList.remove('nav-links-open');
          menu.setAttribute('aria-expanded', 'false');
        });
      });
    }

    document.querySelectorAll('.button').forEach(function (button) {
      button.classList.add('pointer-magnetic');
      button.addEventListener('pointermove', function (event) {
        if (reduceMotion || event.pointerType !== 'mouse') return;
        var bounds = button.getBoundingClientRect();
        var x = (event.clientX - bounds.left - bounds.width / 2) / bounds.width;
        var y = (event.clientY - bounds.top - bounds.height / 2) / bounds.height;
        button.style.setProperty('--mag-x', (x * 5).toFixed(2) + 'px');
        button.style.setProperty('--mag-y', (y * 4).toFixed(2) + 'px');
      });
      button.addEventListener('pointerleave', function () {
        button.style.removeProperty('--mag-x');
        button.style.removeProperty('--mag-y');
      });
    });
    if ('IntersectionObserver' in window) {
      var ambientObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          entry.target.classList.toggle('motion-paused', !entry.isIntersecting);
        });
      }, { threshold: 0 });
      document.querySelectorAll('.hero, .final-cta').forEach(function (section) {
        ambientObserver.observe(section);
      });
    }
    document.querySelectorAll([
      '.trust-row > div',
      '.section-kicker',
      '.section-heading > *',
      '.feature-card > :not(.feature-card)',
      '.bento-note > *',
      '.final-cta',
      '.final-cta > :not(.cta-orbit)',
      '.sticky-footer > *'
    ].join(',')).forEach(function (element) {
      element.classList.add('reveal');
    });

    var revealItems = document.querySelectorAll('.reveal');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      var siblingIndexes = new WeakMap();
      var directions = ['up', 'left', 'right', 'down'];
      revealItems.forEach(function (element) {
        var parent = element.parentElement;
        var index = siblingIndexes.get(parent) || 0;
        siblingIndexes.set(parent, index + 1);
        element.dataset.reveal = directions[index % directions.length];
        element.style.setProperty('--reveal-delay', Math.min(index, 4) * 90 + 'ms');
        observer.observe(element);
      });
    } else {
      revealItems.forEach(function (element) { element.classList.add('is-visible'); });
    }

    document.querySelectorAll('.hero-card, .feature-card').forEach(function (element) {
      element.classList.add('pointer-tilt');
      element.addEventListener('pointermove', function (event) {
        if (reduceMotion || event.pointerType === 'touch') return;
        var box = element.getBoundingClientRect();
        if (!box.width || !box.height) return;
        var x = Math.max(-0.5, Math.min(0.5, (event.clientX - box.left) / box.width - 0.5));
        var y = Math.max(-0.5, Math.min(0.5, (event.clientY - box.top) / box.height - 0.5));
        element.style.setProperty('--pointer-x', (x * 8).toFixed(2) + 'px');
        element.style.setProperty('--pointer-y', (y * 8).toFixed(2) + 'px');
        element.style.setProperty('--tilt-x', (x * 10).toFixed(2) + 'deg');
        element.style.setProperty('--tilt-y', (y * -10).toFixed(2) + 'deg');
        element.style.setProperty('--spot-x', ((x + 0.5) * 100).toFixed(1) + '%');
        element.style.setProperty('--spot-y', ((y + 0.5) * 100).toFixed(1) + '%');
      });
      element.addEventListener('pointerleave', function () {
        ['--pointer-x', '--pointer-y', '--tilt-x', '--tilt-y', '--spot-x', '--spot-y'].forEach(function (property) {
          element.style.removeProperty(property);
        });
      });
    });

    var progressBar = document.querySelector('.scroll-progress > span');
    var scrollFrame = 0;
    function updateScrollContext() {
      scrollFrame = 0;
      var scrollable = document.documentElement.scrollHeight - window.innerHeight;
      var progress = scrollable > 0 ? Math.max(0, Math.min(1, window.scrollY / scrollable)) : 0;
      document.documentElement.style.setProperty('--page-progress', (progress * 100).toFixed(2) + '%');
      document.documentElement.style.setProperty('--hero-scroll-shift', Math.min(window.scrollY * 0.12, 88).toFixed(1) + 'px');
      document.documentElement.style.setProperty('--doodle-scroll-shift', (Math.min(window.scrollY * 0.045, 34) * -1).toFixed(1) + 'px');
      document.documentElement.style.setProperty('--ambient-shift', Math.min(window.scrollY * 0.035, 42).toFixed(1) + 'px');
      if (progressBar) progressBar.style.transform = 'scaleX(' + progress.toFixed(4) + ')';
    }
    window.addEventListener('scroll', function () {
      if (!scrollFrame) scrollFrame = window.requestAnimationFrame(updateScrollContext);
    }, { passive: true });
    window.addEventListener('resize', updateScrollContext, { passive: true });
    updateScrollContext();

    var sectionLinks = document.querySelectorAll('.nav-links a[href^="#"]');
    if ('IntersectionObserver' in window && sectionLinks.length) {
      var sectionObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          sectionLinks.forEach(function (linkElement) {
            var current = linkElement.hash === '#' + entry.target.id;
            if (current) linkElement.setAttribute('aria-current', 'location');
            else linkElement.removeAttribute('aria-current');
          });
        });
      }, { rootMargin: '-25% 0px -60% 0px' });
      sectionLinks.forEach(function (linkElement) {
        var section = document.getElementById(linkElement.hash.slice(1));
        if (section) sectionObserver.observe(section);
      });
    }

    var particleLayer = document.querySelector('.hero-particles');
    if (particleLayer) {
      for (var i = 0; i < 18; i += 1) {
        var particle = document.createElement('span');
        particle.style.setProperty('--particle-x', Math.round(Math.random() * 100) + '%');
        particle.style.setProperty('--particle-y', Math.round(Math.random() * 100) + '%');
        particle.style.setProperty('--particle-delay', (Math.random() * 5).toFixed(2) + 's');
        particle.style.setProperty('--particle-size', (2 + Math.random() * 5).toFixed(1) + 'px');
        particleLayer.appendChild(particle);
      }
    }

    var pendingPointer = null;
    var pointerFrame = 0;
    var cursorAura = document.querySelector('.cursor-aura');
    var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    document.addEventListener('pointermove', function (event) {
      if (event.pointerType !== 'mouse' || !finePointer || reduceMotion) return;
      pendingPointer = { x: event.clientX, y: event.clientY };
      if (pointerFrame) return;
      pointerFrame = window.requestAnimationFrame(function () {
        pointerFrame = 0;
        if (cursorAura && pendingPointer) {
          cursorAura.style.setProperty('--cursor-x', pendingPointer.x + 'px');
          cursorAura.style.setProperty('--cursor-y', pendingPointer.y + 'px');
          cursorAura.classList.add('is-visible');
        }
        var hero = document.querySelector('.hero');
        if (!hero || !pendingPointer) return;
        var bounds = hero.getBoundingClientRect();
        var x = pendingPointer.x - bounds.left;
        var y = pendingPointer.y - bounds.top;
        var driftX = (pendingPointer.x / window.innerWidth - 0.5) * 3;
        var driftY = (pendingPointer.y / window.innerHeight - 0.5) * 3;
        document.documentElement.style.setProperty('--hero-pointer-x', ((pendingPointer.x / window.innerWidth - 0.5) * 18).toFixed(1) + 'px');
        document.documentElement.style.setProperty('--hero-pointer-y', ((pendingPointer.y / window.innerHeight - 0.5) * 18).toFixed(1) + 'px');
        hero.style.setProperty('--hero-content-x', (-driftX).toFixed(1) + 'px');
        hero.style.setProperty('--hero-content-y', (-driftY).toFixed(1) + 'px');
        hero.style.setProperty('--hero-spot-x', Math.max(0, Math.min(100, x / bounds.width * 100)).toFixed(1) + '%');
        hero.style.setProperty('--hero-spot-y', Math.max(0, Math.min(100, y / bounds.height * 100)).toFixed(1) + '%');
        pendingPointer = null;
      });
    }, { passive: true });
    if (cursorAura && finePointer && !reduceMotion) {
      document.addEventListener('pointerover', function (event) {
        if (event.pointerType !== 'mouse') return;
        var target = event.target;
        cursorAura.classList.toggle('is-interactive', !!(target instanceof Element && target.closest('a, button, [role="button"]')));
      });
      document.addEventListener('pointerout', function (event) {
        if (event.pointerType === 'mouse' && !event.relatedTarget) cursorAura.classList.remove('is-visible', 'is-interactive');
      });
      document.addEventListener('mouseleave', function () {
        cursorAura.classList.remove('is-visible', 'is-interactive');
      });
    }
  }

  render();
  activate();
}());
