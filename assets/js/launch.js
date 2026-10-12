(function () {
  'use strict';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var motionPreview = document.body.getAttribute('data-motion-preview') === 'true';
  var disableHeavyMotion = reduced;
  var cursor = document.querySelector('.launch-cursor');
  var spotlight = document.querySelector('.launch-spotlight');
  var progress = document.querySelector('.launch-progress i');
  var parallax = Array.prototype.slice.call(document.querySelectorAll('[data-parallax]'));

  if (cursor && !disableHeavyMotion) {
    window.addEventListener('pointermove', function (event) {
      cursor.style.left = event.clientX + 'px';
      cursor.style.top = event.clientY + 'px';
      if (spotlight) {
        spotlight.style.setProperty('--mx', event.clientX + 'px');
        spotlight.style.setProperty('--my', event.clientY + 'px');
      }
    }, { passive: true });
    document.querySelectorAll('a,button').forEach(function (element) {
      element.addEventListener('mouseenter', function () {
        cursor.style.width = '42px';
        cursor.style.height = '42px';
        cursor.style.background = 'rgba(114,88,255,.08)';
      });
      element.addEventListener('mouseleave', function () {
        cursor.style.width = '22px';
        cursor.style.height = '22px';
        cursor.style.background = 'transparent';
      });
    });
  }

  var items = document.querySelectorAll('.launch-reveal');
  var childGroups = document.querySelectorAll('.launch-child-reveal');
  items.forEach(function (element, index) {
    if (element.classList.contains('launch-step')) {
      element.setAttribute('data-stagger-item', '');
      element.style.setProperty('--stagger', Math.min(index * 100, 300) + 'ms');
    }
  });
  if (!('IntersectionObserver' in window) || disableHeavyMotion) {
    items.forEach(function (element) { element.classList.add('is-visible'); });
    childGroups.forEach(function (element) { element.classList.add('children-visible'); });
  } else {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
        } else if (entry.boundingClientRect.top > 0) {
          entry.target.classList.remove('is-visible');
        }
      });
    }, { threshold: .24, rootMargin: '0px 0px -10% 0px' });
    items.forEach(function (element) { observer.observe(element); });
    var childObserver = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('children-visible');
        } else if (entry.boundingClientRect.top > 0) {
          entry.target.classList.remove('children-visible');
        }
      });
    }, { threshold: .2, rootMargin: '0px 0px -10% 0px' });
    childGroups.forEach(function (element) { childObserver.observe(element); });
  }

  var card = document.querySelector('.stage-main');
  if (card && !disableHeavyMotion) {
    card.addEventListener('pointermove', function (event) {
      var rect = card.getBoundingClientRect();
      var x = (event.clientX - rect.left) / rect.width - .5;
      var y = (event.clientY - rect.top) / rect.height - .5;
      card.style.setProperty('--rx', (-y * 7).toFixed(2) + 'deg');
      card.style.setProperty('--ry', (x * 9).toFixed(2) + 'deg');
      card.style.setProperty('--lift', '-8px');
    });
    card.addEventListener('pointerleave', function () {
      card.style.setProperty('--rx', '0deg');
      card.style.setProperty('--ry', '0deg');
      card.style.setProperty('--lift', '0');
    });
  }

  if (!disableHeavyMotion) {
    document.querySelectorAll('.launch-step').forEach(function (step) {
      step.addEventListener('pointermove', function (event) {
        var rect = step.getBoundingClientRect();
        var x = (event.clientX - rect.left) / rect.width - .5;
        var y = (event.clientY - rect.top) / rect.height - .5;
        step.style.transform = 'perspective(700px) rotateX(' + (-y * 2).toFixed(2) + 'deg) rotateY(' + (x * 2).toFixed(2) + 'deg) translateY(-8px)';
      });
      step.addEventListener('pointerleave', function () {
        step.style.transform = '';
      });
    });

    document.querySelectorAll('.launch-step, .stage-card, .launch-cta').forEach(function (element) {
      element.addEventListener('pointermove', function (event) {
        var rect = element.getBoundingClientRect();
        element.style.setProperty('--glow-x', ((event.clientX - rect.left) / rect.width * 100).toFixed(1) + '%');
        element.style.setProperty('--glow-y', ((event.clientY - rect.top) / rect.height * 100).toFixed(1) + '%');
      }, { passive: true });
      element.addEventListener('pointerleave', function () {
        element.style.setProperty('--glow-x', '50%');
        element.style.setProperty('--glow-y', '50%');
      });
    });
  }

  if (!disableHeavyMotion) {
    document.querySelectorAll('.launch-pill').forEach(function (element) {
      element.addEventListener('pointermove', function (event) {
        var rect = element.getBoundingClientRect();
        element.style.setProperty('--tx', ((event.clientX - rect.left) / rect.width - .5) * 5 + 'px');
        element.style.setProperty('--ty', ((event.clientY - rect.top) / rect.height - .5) * 4 + 'px');
      });
      element.addEventListener('pointerleave', function () {
        element.style.setProperty('--tx', '0px');
        element.style.setProperty('--ty', '0px');
      });
    });
  }

  function updateScroll() {
    var max = document.documentElement.scrollHeight - window.innerHeight;
    var percent = max > 0 ? window.scrollY / max * 100 : 0;
    if (progress) progress.style.width = percent + '%';
    parallax.forEach(function (element) {
      var speed = parseFloat(element.getAttribute('data-parallax')) || .5;
      element.style.transform = 'translate3d(0,' + (window.scrollY * speed).toFixed(1) + 'px,0)';
    });
    document.querySelectorAll('.launch-section, .launch-cta').forEach(function (section) {
      var rect = section.getBoundingClientRect();
      var shift = Math.max(-35, Math.min(35, (window.innerHeight * .5 - (rect.top + rect.height * .5)) * .035));
      section.style.setProperty('--section-shift', shift.toFixed(1) + 'px');
    });
  }
  window.addEventListener('scroll', updateScroll, { passive: true });
  updateScroll();

  var canvas = document.querySelector('.launch-particles');
  var context = canvas && canvas.getContext('2d');
  var dots = [];
  function resizeCanvas() {
    if (!canvas || disableHeavyMotion) return;
    canvas.width = window.innerWidth * devicePixelRatio;
    canvas.height = window.innerHeight * devicePixelRatio;
    canvas.style.width = window.innerWidth + 'px';
    canvas.style.height = window.innerHeight + 'px';
    context.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
    dots = [];
    for (var i = 0; i < Math.min(55, Math.floor(window.innerWidth / 22)); i++) {
      dots.push({ x: Math.random() * window.innerWidth, y: Math.random() * window.innerHeight * .8, vx: (Math.random() - .5) * .18, vy: (Math.random() - .5) * .18, r: Math.random() * 1.5 + .5 });
    }
  }
  function animateParticles() {
    if (!context || disableHeavyMotion) return;
    context.clearRect(0, 0, window.innerWidth, window.innerHeight);
    dots.forEach(function (dot, index) {
      dot.x += dot.vx;
      dot.y += dot.vy;
      if (dot.x < 0 || dot.x > window.innerWidth) dot.vx *= -1;
      if (dot.y < 0 || dot.y > window.innerHeight) dot.vy *= -1;
      context.beginPath();
      context.arc(dot.x, dot.y, dot.r, 0, Math.PI * 2);
      context.fillStyle = 'rgba(114,88,255,.28)';
      context.fill();
      for (var next = index + 1; next < dots.length; next++) {
        var other = dots[next];
        var distance = Math.hypot(dot.x - other.x, dot.y - other.y);
        if (distance < 125) {
          context.beginPath();
          context.moveTo(dot.x, dot.y);
          context.lineTo(other.x, other.y);
          context.strokeStyle = 'rgba(114,88,255,' + (1 - distance / 125) * .1 + ')';
          context.stroke();
        }
      }
    });
    requestAnimationFrame(animateParticles);
  }
  resizeCanvas();
  window.addEventListener('resize', resizeCanvas, { passive: true });
  animateParticles();
}());
