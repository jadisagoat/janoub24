/* جنوب 24 — سكربت الواجهة (خفيف وبدون مكتبات) */
(function () {
  'use strict';
  var d = document, root = d.documentElement;

  /* الدرج الجانبي للجوال */
  var drawer = d.getElementById('drawer'), backdrop = d.querySelector('.backdrop'), menuBtn = d.querySelector('[data-open-drawer]');
  function setDrawer(open) {
    if (!drawer) return;
    drawer.classList.toggle('open', open);
    backdrop.classList.toggle('show', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    d.body.style.overflow = open ? 'hidden' : '';
  }
  d.addEventListener('click', function (e) {
    if (e.target.closest('[data-open-drawer]')) setDrawer(true);
    else if (e.target.closest('[data-close-drawer]')) setDrawer(false);
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') setDrawer(false); });

  /* الوضع الليلي */
  var tbtn = d.querySelector('[data-theme-toggle]');
  if (tbtn) tbtn.addEventListener('click', function () {
    var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('j24-theme', next); } catch (e) {}
  });

  /* شريط العاجل: تكرار المحتوى لحركة متصلة وسرعة حسب الطول */
  var track = d.querySelector('[data-ticker] ul');
  if (track && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    track.innerHTML += track.innerHTML;
    var w = track.scrollWidth / 2;
    track.style.setProperty('--dur', Math.max(25, Math.round(w / 70)) + 's');
  }

  /* تشغيل يوتيوب عند الطلب فقط (أداء أفضل) */
  d.addEventListener('click', function (e) {
    var yt = e.target.closest('.yt');
    if (!yt || yt.querySelector('iframe')) return;
    var f = d.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + yt.dataset.yt + '?autoplay=1&rel=0';
    f.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
    f.allowFullscreen = true;
    f.title = 'YouTube';
    yt.appendChild(f);
  });

  /* نسخ الرابط */
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var done = function () { b.classList.add('ok'); setTimeout(function () { b.classList.remove('ok'); }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy).then(done);
  });

  /* حجم خط المقال */
  var body = d.getElementById('story-body');
  if (body) {
    var size = parseFloat(localStorage.getItem('j24-font') || '1.18');
    root.style.setProperty('--story-size', size + 'rem');
    d.querySelectorAll('[data-font]').forEach(function (b) {
      b.addEventListener('click', function () {
        size = Math.min(1.6, Math.max(.95, size + parseInt(b.dataset.font, 10) * .08));
        root.style.setProperty('--story-size', size.toFixed(2) + 'rem');
        try { localStorage.setItem('j24-font', size.toFixed(2)); } catch (e) {}
      });
    });
  }

  /* عدّاد المشاهدات (يعمل حتى مع التخزين المؤقت للصفحات) */
  var art = d.querySelector('[data-article-id]');
  if (art) {
    var id = art.dataset.articleId, key = 'j24v' + id, last = 0;
    try { last = +localStorage.getItem(key) || 0; } catch (e) {}
    if (Date.now() - last > 30 * 60 * 1000) {
      var fd = new FormData(); fd.append('id', id);
      if (navigator.sendBeacon) navigator.sendBeacon(J24.base + '/api/view', fd);
      else fetch(J24.base + '/api/view', { method: 'POST', body: fd, keepalive: true });
      try { localStorage.setItem(key, Date.now()); } catch (e) {}
    }
  }

  /* إيقاف وميض التحميل فور ظهور الصورة (توفير المعالج والبطارية) */
  d.querySelectorAll('.card-media img, .story-media img').forEach(function (img) {
    var done = function () { (img.closest('.card-media') || img).classList.add('ld'); };
    if (img.complete) done(); else { img.addEventListener('load', done); img.addEventListener('error', done); }
  });

  /* ===== سلايدر الأخبار الرئيسية ===== */
  d.querySelectorAll('[data-slider]').forEach(function (sl) {
    var slides = [].slice.call(sl.querySelectorAll('.sl-slide')), bars = [].slice.call(sl.querySelectorAll('.sl-bar'));
    var n = slides.length;
    if (n < 2) return;
    var cnt = sl.querySelector('.sl-count b'), cur = 0, timer = null, start = 0;
    var dur = +sl.dataset.interval || 6500, left = dur, hover = false;
    var still = matchMedia('(prefers-reduced-motion: reduce)').matches;
    sl.style.setProperty('--dur', dur + 'ms');

    function warm(k) { /* تحميل صورة الشريحة التالية مسبقاً */
      slides[k].querySelectorAll('img[loading="lazy"]').forEach(function (im) { im.loading = 'eager'; });
    }
    function schedule(ms) {
      clearTimeout(timer);
      if (still || hover || d.hidden) return;
      start = Date.now(); left = ms;
      timer = setTimeout(function () { go(cur + 1); }, ms);
    }
    function go(k) {
      k = (k + n) % n;
      if (k !== cur) {
        slides[cur].classList.remove('is-active'); slides[cur].setAttribute('aria-hidden', 'true');
        slides[k].classList.add('is-active'); slides[k].removeAttribute('aria-hidden');
        cur = k;
      }
      bars.forEach(function (b, j) {
        b.classList.remove('run', 'done');
        if (j < cur) b.classList.add('done');
      });
      void sl.offsetWidth; /* إعادة تشغيل حركة الشريط */
      if (!still) bars[cur].classList.add('run'); else bars[cur].classList.add('done');
      if (cnt) cnt.textContent = (cur < 9 ? '0' : '') + (cur + 1);
      warm((cur + 1) % n);
      schedule(dur);
    }
    function pause() {
      if (hover) return;
      hover = true; sl.classList.add('paused'); clearTimeout(timer);
      left = Math.max(400, left - (Date.now() - start));
    }
    function resume() {
      if (!hover) return;
      hover = false; sl.classList.remove('paused'); schedule(left);
    }

    sl.addEventListener('mouseenter', pause);
    sl.addEventListener('mouseleave', resume);
    sl.addEventListener('focusin', pause);
    sl.addEventListener('focusout', function (e) { if (!sl.contains(e.relatedTarget)) resume(); });
    d.addEventListener('visibilitychange', function () { if (d.hidden) clearTimeout(timer); else if (!hover) schedule(left); });

    sl.querySelector('.sl-next').addEventListener('click', function () { go(cur + 1); });
    sl.querySelector('.sl-prev').addEventListener('click', function () { go(cur - 1); });
    bars.forEach(function (b) { b.addEventListener('click', function () { go(+b.dataset.go); }); });
    sl.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { go(cur + 1); e.preventDefault(); }     /* اتجاه عربي: اليسار = التالي */
      if (e.key === 'ArrowRight') { go(cur - 1); e.preventDefault(); }
    });

    /* السحب باللمس */
    var x0 = null, y0 = 0, moved = false;
    sl.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; moved = false; }, { passive: true });
    sl.addEventListener('touchmove', function (e) {
      if (x0 === null) return;
      var dx = e.touches[0].clientX - x0, dy = e.touches[0].clientY - y0;
      if (Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy)) moved = true;
    }, { passive: true });
    sl.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      if (moved && Math.abs(dx) > 45) go(dx > 0 ? cur + 1 : cur - 1);
      x0 = null;
    });
    sl.addEventListener('click', function (e) { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);

    warm(1);
    window.addEventListener('load', function () { for (var k = 0; k < n; k++) warm(k); }); /* كل الشرائح جاهزة بعد اكتمال الصفحة */
    schedule(dur);
  });

  /* ===== الحركة ===== */

  /* الظهور المتتابع عند التمرير */
  if (root.classList.contains('js-reveal')) {
    var sel = '.block .sec-head, .col-side .sec-head, .related .sec-head, .card-std, .card-row, .card-opinion, .most-read li,' +
      ' .follow-grid a, .tag-cloud, .video-main, .timeline li, .urgent-list li, .author-box, .follow-cta, .story-tags, .list-lead, .gnews';
    var targets = [].slice.call(d.querySelectorAll(sel)).filter(function (el) { return !el.closest('.hero, .drawer'); });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) {
          var el = en.target;
          el.classList.add('in');
          io.unobserve(el);
          setTimeout(function () { el.removeAttribute('data-reveal'); }, 1400);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    targets.forEach(function (el) {
      var sibs = el.parentElement ? [].slice.call(el.parentElement.children) : [];
      var i = Math.max(0, sibs.indexOf(el));
      el.style.setProperty('--d', Math.min(i, 6) * 0.07 + 's');
      el.setAttribute('data-reveal', el.matches('.sec-head') ? 'fade' : '');
      io.observe(el);
    });
    /* عناوين الأقسام داخل الشريط الجانبي والواجهة تُرسم بخط متحرك */
    d.querySelectorAll('.sec-head').forEach(function (h) { if (!h.hasAttribute('data-reveal')) { h.setAttribute('data-reveal', 'fade'); io.observe(h); } });
  }

  /* القائمة المصغّرة + زر الصعود + شريط تقدّم القراءة */
  var nav = d.querySelector('.mainnav'), toTop = d.querySelector('.to-top'), bar = d.querySelector('.progress');
  var story = d.getElementById('story-body'), ticking = false;
  function onScroll() {
    var y = window.scrollY || root.scrollTop;
    if (nav) nav.classList.toggle('scrolled', y > 160);
    if (toTop) toTop.classList.toggle('show', y > 700);
    if (bar && story) {
      var r = story.getBoundingClientRect(), total = r.height - window.innerHeight * 0.6;
      var p = Math.min(1, Math.max(0, -r.top / (total > 0 ? total : 1)));
      bar.style.transform = 'scaleX(' + p.toFixed(3) + ')';
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

  /* تحديث تلقائي لصفحة العاجل */
  var auto = d.querySelector('[data-auto-refresh]');
  if (auto) setTimeout(function () { if (!d.hidden) location.reload(); }, (+auto.dataset.autoRefresh || 60) * 1000);
})();
