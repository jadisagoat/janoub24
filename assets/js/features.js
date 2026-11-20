/* جنوب 24 — الميزات التفاعلية: الجنوب، المباشر، إشعار العاجل، الطقس، الاستماع، عارض الصور */
(function () {
  'use strict';
  var d = document, base = (window.J24 && J24.base) || '';
  var esc = function (s) { var x = d.createElement('div'); x.textContent = s == null ? '' : String(s); return x.innerHTML; };
  var chev = '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  /* ===== تبويبات أقضية الجنوب + الخريطة ===== */
  d.querySelectorAll('.south-block').forEach(function (sb) {
    var tabs = [].slice.call(sb.querySelectorAll('.sb-tabs [data-tab]'));
    var panels = [].slice.call(sb.querySelectorAll('.sb-panel'));
    var cells = [].slice.call(sb.querySelectorAll('.rmap-cell[data-tab], .sb-reg[data-tab]'));
    function show(k) {
      if (!sb.querySelector('.sb-panel[data-panel="' + k + '"]')) return false;
      tabs.forEach(function (t) { var on = t.dataset.tab === k; t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
      panels.forEach(function (p) { var on = p.dataset.panel === k; p.hidden = !on; p.classList.toggle('is-on', on); });
      cells.forEach(function (c) { c.classList.toggle('is-active', c.dataset.tab === k); });
      return true;
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
    cells.forEach(function (c) {
      c.addEventListener('click', function (e) { if (show(c.dataset.tab)) e.preventDefault(); });
      c.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && show(c.dataset.tab)) e.preventDefault(); });
    });
  });

  /* ===== التغطية المباشرة: جلب التحديثات كل 30 ثانية ===== */
  var lf = d.querySelector('.live-feed[data-live]');
  if (lf && lf.dataset.open === '1') {
    var lid = lf.dataset.live, last = +lf.dataset.last || 0, list = lf.querySelector('.lf-list');
    var btn = lf.querySelector('.lf-new'), cnt = lf.querySelector('.lf-count b'), pending = 0, timer;
    var poll = function () {
      if (d.hidden) return;
      fetch(base + '/api/live/' + lid + '?after=' + last, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
        (j.updates || []).forEach(function (u) {
          if (u.id <= last) return;
          last = u.id;
          var empty = list.querySelector('.lf-empty');
          if (empty) empty.remove();
          var li = d.createElement('li');
          li.className = 'lf-item is-new' + (u.key ? ' is-key' : '');
          li.id = 'u' + u.id;
          li.innerHTML = '<time datetime="' + esc(u.iso) + '">' + esc(u.time) + '<small>الآن</small></time><div class="lf-body">' + (u.key ? '<span class="lf-key">مهم</span>' : '') + u.html + '</div>';
          list.insertBefore(li, list.firstChild);
          pending++;
          if (cnt) cnt.textContent = (+cnt.textContent || 0) + 1;
        });
        if (pending && lf.getBoundingClientRect().top < -80) {
          btn.hidden = false;
          btn.textContent = '↑ ' + pending + ' تحديث جديد — اضغط للعرض';
        } else { pending = 0; }
        if (j.open === false) {
          clearInterval(timer);
          var st = lf.querySelector('.lf-state');
          st.classList.remove('is-open');
          st.lastChild.textContent = 'انتهت التغطية المباشرة';
        }
      }).catch(function () {});
    };
    timer = setInterval(poll, 30000);
    d.addEventListener('visibilitychange', function () { if (!d.hidden) poll(); });
    btn.addEventListener('click', function () { btn.hidden = true; pending = 0; lf.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
  }

  /* ===== إشعار العاجل المنبثق ===== */
  (function () {
    var KEY = 'j24-br', seen = 0;
    try { seen = +localStorage.getItem(KEY) || 0; } catch (e) {}
    var save = function () { try { localStorage.setItem(KEY, seen); } catch (e) {} };
    function toast(b) {
      var old = d.querySelector('.toast');
      if (old) old.remove();
      var t = d.createElement('div');
      t.className = 'toast';
      t.setAttribute('role', 'alert');
      var title = b.url ? '<a href="' + esc(b.url) + '">' + esc(b.text) + '</a>' : '<b>' + esc(b.text) + '</b>';
      t.innerHTML = '<div class="toast-bar"></div><div class="toast-in"><span class="toast-tag"><span class="live-dot"></span>عاجل</span><div>' + title + '<time>' + esc(b.time) + '</time></div><button type="button" class="toast-x" aria-label="إغلاق">✕</button></div>';
      d.body.appendChild(t);
      requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add('show'); }); });
      var hide = function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 600); };
      t.querySelector('.toast-x').addEventListener('click', hide);
      setTimeout(hide, 12000);
    }
    function check() {
      fetch(base + '/api/breaking', { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (list) {
        if (!list || !list.length) return;
        var max = Math.max.apply(null, list.map(function (b) { return b.id; }));
        if (!seen) { seen = max; save(); return; } /* الزيارة الأولى: لا إزعاج */
        var fresh = list.filter(function (b) { return b.id > seen; });
        if (fresh.length) { toast(fresh[0]); seen = max; save(); }
      }).catch(function () {});
    }
    setTimeout(check, 2500);
    setInterval(function () { if (!d.hidden) check(); }, 60000);
  })();

  /* ===== الطقس في مدن الجنوب (Open-Meteo) ===== */
  var wx = d.querySelector('[data-weather]');
  if (wx) {
    var cities = [['صور', 33.27, 35.20], ['صيدا', 33.56, 35.37], ['النبطية', 33.38, 35.48], ['بنت جبيل', 33.12, 35.43], ['مرجعيون', 33.36, 35.59]];
    var ico = function (k) {
      var p;
      if (k <= 1) p = '<circle cx="12" cy="12" r="4.5" fill="#ffc83d"/><g stroke="#ffc83d" stroke-width="2" stroke-linecap="round"><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></g>';
      else if (k <= 3 || k === 45 || k === 48) p = '<circle cx="9" cy="9" r="3.5" fill="#ffc83d"/><path d="M7 19h10a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 7 19z" fill="#dbe7f3"/>';
      else if (k >= 95) p = '<path d="M7 15h10a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 7 15z" fill="#b8c7d8"/><path d="m12 15-2 4h3l-2 4" stroke="#ffc83d" stroke-width="2" fill="none" stroke-linejoin="round"/>';
      else if (k >= 71 && k <= 86 && !(k >= 80 && k <= 82)) p = '<path d="M7 14h10a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 7 14z" fill="#dbe7f3"/><g fill="#fff"><circle cx="8" cy="18" r="1.3"/><circle cx="12" cy="20" r="1.3"/><circle cx="16" cy="18" r="1.3"/></g>';
      else p = '<path d="M7 14h10a4 4 0 0 0 0-8 5 5 0 0 0-9.6 1.5A3.3 3.3 0 0 0 7 14z" fill="#dbe7f3"/><g stroke="#5fb4ff" stroke-width="2" stroke-linecap="round"><path d="M8 17l-1 3M12 17l-1 3M16 17l-1 3"/></g>';
      return '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>';
    };
    var start = function (data) {
      if (!data || !data.length) return;
      var i = 0;
      var show = function () {
        var x = data[i++ % data.length];
        wx.classList.add('fade');
        setTimeout(function () { wx.innerHTML = ico(x.k) + '<span>' + esc(x.c) + '</span><b>' + x.t + '°</b>'; wx.classList.remove('fade'); }, 350);
      };
      show();
      setInterval(show, 5000);
    };
    var cached = null;
    try { cached = JSON.parse(sessionStorage.getItem('j24-wx') || 'null'); } catch (e) {}
    if (cached && Date.now() - cached.t < 30 * 60000) start(cached.d);
    else {
      fetch('https://api.open-meteo.com/v1/forecast?latitude=' + cities.map(function (c) { return c[1]; }).join(',') +
        '&longitude=' + cities.map(function (c) { return c[2]; }).join(',') + '&current=temperature_2m,weather_code&timezone=Asia%2FBeirut')
        .then(function (r) { return r.json(); }).then(function (j) {
          var arr = Array.isArray(j) ? j : [j];
          var data = arr.map(function (x, k) { return { c: cities[k][0], t: Math.round(x.current.temperature_2m), k: x.current.weather_code }; });
          try { sessionStorage.setItem('j24-wx', JSON.stringify({ t: Date.now(), d: data })); } catch (e) {}
          start(data);
        }).catch(function () {});
    }
  }

  /* ===== استمع للخبر (قراءة صوتية بالعربية من المتصفح) ===== */
  var story = d.getElementById('story-body'), share = d.querySelector('.share');
  if (story && share && 'speechSynthesis' in window) {
    var synth = window.speechSynthesis;
    var arVoice = function () { return synth.getVoices().filter(function (v) { return /^ar/i.test(v.lang); })[0]; };
    var setup = function () {
      if (d.querySelector('.sh-listen') || !arVoice()) return;
      var b = d.createElement('button');
      b.type = 'button';
      b.className = 'sh-listen';
      b.innerHTML = '<span class="eq"><i></i><i></i><i></i></span><svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 15v-3a8 8 0 0 1 16 0v3" stroke="currentColor" stroke-width="2" fill="none"/><rect x="3" y="14" width="5" height="7" rx="2" fill="currentColor"/><rect x="16" y="14" width="5" height="7" rx="2" fill="currentColor"/></svg><span class="lbl">استمع للخبر</span>';
      share.insertBefore(b, share.children[1] || null);
      var on = false, lbl = b.querySelector('.lbl');
      var stop = function () { on = false; b.classList.remove('is-on'); lbl.textContent = 'استمع للخبر'; };
      b.addEventListener('click', function () {
        if (on) { synth.cancel(); stop(); return; }
        var c = story.cloneNode(true);
        c.querySelectorAll('.read-also, figure, script, iframe').forEach(function (n) { n.remove(); });
        var h = d.querySelector('.story-head h1');
        var text = ((h ? h.textContent + '. ' : '') + c.textContent).replace(/\s+/g, ' ').trim();
        var parts = text.match(/[^.!؟?،]{1,220}[.!؟?،]?/g) || [text];
        var v = arVoice();
        synth.cancel();
        on = true; b.classList.add('is-on'); lbl.textContent = 'إيقاف';
        parts.forEach(function (p, i) {
          var u = new SpeechSynthesisUtterance(p.trim());
          u.voice = v; u.lang = v.lang; u.rate = 1;
          if (i === parts.length - 1) u.onend = stop;
          synth.speak(u);
        });
      });
      window.addEventListener('pagehide', function () { synth.cancel(); });
    };
    setup();
    if (synth.addEventListener) synth.addEventListener('voiceschanged', setup); else synth.onvoiceschanged = setup;
  }

  /* ===== البحث الفوري: قائمة منسدلة أثناء الكتابة ===== */
  var hl = function (txt, q) {
    var out = esc(txt);
    q.trim().split(/\s+/).filter(function (t) { return t.length > 1; }).forEach(function (t) {
      var safe = esc(t).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      out = out.replace(new RegExp('(' + safe + ')', 'gi'), '<mark>$1</mark>');
    });
    return out;
  };
  var cacheQ = {};
  d.querySelectorAll('form[role="search"]').forEach(function (form, fi) {
    var input = form.querySelector('input[name="q"]');
    if (!input) return;
    var box = d.createElement('div');
    box.className = 'sugg';
    box.id = 'sugg-' + fi;
    box.setAttribute('role', 'listbox');
    box.hidden = true;
    form.appendChild(box);
    form.classList.add('has-sugg');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', box.id);
    input.setAttribute('aria-expanded', 'false');
    var timer = null, ctrl = null, active = -1, lastQ = '';

    var open = function () { box.hidden = false; input.setAttribute('aria-expanded', 'true'); form.classList.add('sugg-open'); };
    var close = function () { box.hidden = true; input.setAttribute('aria-expanded', 'false'); form.classList.remove('sugg-open'); active = -1; };
    var opts = function () { return [].slice.call(box.querySelectorAll('[data-opt]')); };
    var mark = function (k) {
      var o = opts();
      if (!o.length) return;
      active = (k + o.length) % o.length;
      o.forEach(function (el, i) { el.classList.toggle('is-active', i === active); el.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
      o[active].scrollIntoView({ block: 'nearest' });
    };
    var render = function (q, j) {
      var h = '';
      if (j.sections && j.sections.length) {
        h += '<div class="sg-sec">' + j.sections.map(function (s) {
          return '<a data-opt href="' + esc(s.url) + '" class="sg-chip sg-' + esc(s.type) + '">' + hl(s.name, q) + '</a>';
        }).join('') + '</div>';
      }
      if (j.items && j.items.length) {
        h += '<div class="sg-list">' + j.items.map(function (it) {
          return '<a data-opt role="option" class="sg-item" href="' + esc(it.url) + '"><span class="sg-img"><img src="' + esc(it.img) + '" alt="" loading="lazy" width="96" height="54">' +
            (it.video ? '<i class="sg-play"></i>' : '') + '</span><span class="sg-txt"><b>' + hl(it.title, q) + '</b><small>' +
            (it.live ? '<em class="sg-live">مباشر</em>' : '') + '<em style="color:' + esc(it.color) + '">' + esc(it.cat) + '</em> · ' + esc(it.time) + '</small></span></a>';
        }).join('') + '</div><a data-opt class="sg-all" href="' + esc(j.all) + '">عرض كل النتائج (' + j.total + ') <span aria-hidden="true">←</span></a>';
      } else if (!j.sections || !j.sections.length) {
        h += '<div class="sg-empty">لا نتائج لـ «' + esc(q) + '». جرّب كلمة أقصر أو اسم بلدة.</div>';
      }
      box.innerHTML = h;
      active = -1;
      open();
    };
    var fetchQ = function (q) {
      if (cacheQ[q]) { render(q, cacheQ[q]); return; }
      if (ctrl) ctrl.abort();
      ctrl = 'AbortController' in window ? new AbortController() : null;
      box.innerHTML = '<div class="sg-loading"><i></i><i></i><i></i></div>';
      open();
      fetch(base + '/api/search?q=' + encodeURIComponent(q), ctrl ? { signal: ctrl.signal } : {})
        .then(function (r) { return r.json(); })
        .then(function (j) { cacheQ[q] = j; if (input.value.trim() === q) render(q, j); })
        .catch(function () {});
    };
    input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 2) { close(); lastQ = ''; return; }
      if (q === lastQ) return;
      lastQ = q;
      timer = setTimeout(function () { fetchQ(q); }, 220);
    });
    input.addEventListener('focus', function () { if (input.value.trim().length >= 2 && box.innerHTML) open(); });
    input.addEventListener('keydown', function (e) {
      if (box.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Enter' && active > -1) { e.preventDefault(); location.href = opts()[active].href; }
    });
    d.addEventListener('click', function (e) { if (!form.contains(e.target)) close(); });
  });

  /* ===== عارض الصور بملء الشاشة ===== */
  var SEL = '.story-media > img, .story-media > picture > img, .story-body img, .lf-body img';
  var big = function (img) {
    var ss = img.getAttribute('srcset');
    if (ss) { var parts = ss.split(',').map(function (s) { return s.trim().split(/\s+/)[0]; }); return parts[parts.length - 1]; }
    return img.currentSrc || img.src;
  };
  d.addEventListener('click', function (e) {
    var img = e.target.closest(SEL);
    if (!img || img.closest('a')) return;
    e.preventDefault();
    var all = [].slice.call(d.querySelectorAll(SEL)), i = all.indexOf(img);
    var box = d.createElement('div');
    box.className = 'lbx';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.innerHTML = '<img alt=""><p class="lbx-cap"></p><button type="button" class="lbx-x" aria-label="إغلاق">✕</button>' +
      (all.length > 1 ? '<button type="button" class="lbx-prev" aria-label="السابقة">' + chev + '</button><button type="button" class="lbx-next" aria-label="التالية">' + chev + '</button>' : '');
    d.body.appendChild(box);
    d.body.style.overflow = 'hidden';
    var im = box.querySelector('img'), cap = box.querySelector('.lbx-cap');
    var show = function (k) { i = (k + all.length) % all.length; im.src = big(all[i]); cap.textContent = all[i].alt || ''; };
    var close = function () { box.classList.remove('show'); d.body.style.overflow = ''; d.removeEventListener('keydown', key); setTimeout(function () { box.remove(); }, 300); };
    var key = function (ev) {
      if (ev.key === 'Escape') close();
      if (ev.key === 'ArrowLeft') show(i + 1);
      if (ev.key === 'ArrowRight') show(i - 1);
    };
    box.addEventListener('click', function (ev) {
      if (ev.target === box || ev.target.closest('.lbx-x')) close();
      else if (ev.target.closest('.lbx-next')) show(i + 1);
      else if (ev.target.closest('.lbx-prev')) show(i - 1);
    });
    d.addEventListener('keydown', key);
    show(i);
    requestAnimationFrame(function () { box.classList.add('show'); box.querySelector('.lbx-x').focus(); });
  });
})();

