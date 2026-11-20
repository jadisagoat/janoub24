/* جنوب 24 — لوحة التحكم */
(function () {
  'use strict';
  var d = document;
  var $ = function (s) { return d.querySelector(s); };
  var norm = function (s) {
    return (s || '').replace(/[ً-ٰٟـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي').toLowerCase().trim();
  };

  /* ===== المحرر ===== */
  var quill = null;
  function initEditor() {
    var el = $('#editor');
    if (!el || !window.Quill) return;
    quill = new Quill(el, {
      theme: 'snow',
      placeholder: 'اكتب نص الخبر هنا…',
      modules: {
        toolbar: {
          container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote'], ['image', 'video'], ['clean']],
          handlers: { image: uploadImage }
        }
      }
    });
    quill.on('text-change', update);
    var form = $('#article-form');
    form.addEventListener('submit', function () {
      var html = quill.root.innerHTML;
      $('#f-body').value = html === '<p><br></p>' ? '' : html;
    });
  }
  function uploadImage() {
    var inp = d.createElement('input');
    inp.type = 'file';
    inp.accept = 'image/*';
    inp.onchange = function () {
      var fd = new FormData();
      fd.append('image', inp.files[0]);
      fd.append('_csrf', J24A.csrf);
      fetch(J24A.upload, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.url) {
          var range = quill.getSelection(true);
          quill.insertEmbed(range.index, 'image', j.url);
        } else alert(j.error || 'فشل الرفع');
      });
    };
    inp.click();
  }

  /* ===== عدادات الأحرف ===== */
  function counters() {
    d.querySelectorAll('[data-count]').forEach(function (s) {
      var f = d.querySelector('[name="' + s.dataset.count + '"]');
      if (!f) return;
      var max = +s.dataset.max, n = f.value.length;
      s.textContent = n + ' / ' + max;
      s.className = 'cnt ' + (n === 0 ? '' : n > max ? 'bad' : n >= max * 0.5 ? 'good' : 'mid');
    });
  }

  /* ===== معاينة جوجل وفحص السيو ===== */
  function bodyText() { return quill ? quill.getText() : ''; }
  function bodyHtml() { return quill ? quill.root.innerHTML : ''; }
  function update() {
    counters();
    var title = ($('#f-title') || {}).value || '';
    if (!$('#seo-checks')) return;
    var seoTitle = $('#f-seo-title').value || title;
    var excerpt = $('#f-excerpt').value;
    var desc = $('#f-seo-desc').value || excerpt || bodyText().slice(0, 160);
    var kw = norm($('#f-kw').value);
    var text = bodyText();
    var words = text.trim() ? text.trim().split(/\s+/).length : 0;
    var html = bodyHtml();
    var firstPara = (quill && quill.root.querySelector('p')) ? quill.root.querySelector('p').textContent : '';
    var hasImg = !!d.querySelector('#img-prev img');
    var tags = ($('#f-tags').value || '').split(/[,،]/).filter(function (t) { return t.trim(); }).length;
    var internal = (html.match(new RegExp('href="' + J24A.base.replace(/[.*+?^${}()|[\]\\\/]/g, '\\$&'), 'g')) || []).length;
    var hasH2 = /<h2/.test(html);

    $('#serp-title').textContent = (seoTitle.length > 60 ? seoTitle.slice(0, 58) + '…' : seoTitle) + ' | ' + J24A.site;
    $('#serp-desc').textContent = desc.length > 160 ? desc.slice(0, 157) + '…' : desc;
    $('#wc').textContent = 'عدد الكلمات: ' + words;

    var checks = [
      [seoTitle.length >= 30 && seoTitle.length <= 70, 'طول العنوان مناسب (30–70 حرفاً) — الحالي ' + seoTitle.length],
      [!!kw, 'تحديد كلمة مفتاحية رئيسية'],
      [kw && norm(seoTitle).indexOf(kw) > -1, 'الكلمة المفتاحية موجودة في العنوان'],
      [kw && norm(seoTitle).indexOf(kw) > -1 && norm(seoTitle).indexOf(kw) < 30, 'الكلمة المفتاحية في بداية العنوان'],
      [kw && (norm(excerpt).indexOf(kw) > -1 || norm(firstPara).indexOf(kw) > -1), 'الكلمة المفتاحية في الملخص أو الفقرة الأولى'],
      [desc.length >= 70 && desc.length <= 160, 'طول الوصف مناسب (70–160 حرفاً) — الحالي ' + desc.length],
      [words >= 150, 'النص 150 كلمة على الأقل (الأفضل 300+) — الحالي ' + words],
      [hasImg || !!$('#f-image').value, 'صورة رئيسية (ضرورية لـ Google Discover)'],
      [tags >= 2, 'وسمان على الأقل'],
      [internal >= 1, 'رابط داخلي لخبر آخر في الموقع'],
      [words < 300 || hasH2, 'عناوين فرعية (H2) في النصوص الطويلة'],
    ];
    var ok = 0;
    $('#seo-checks').innerHTML = checks.map(function (c) { if (c[0]) ok++; return '<li class="' + (c[0] ? 'ok' : 'no') + '">' + c[1] + '</li>'; }).join('') +
      '<li class="score">النتيجة: ' + Math.round(ok / checks.length * 100) + '%</li>';
  }

  /* معاينة الصورة قبل الرفع */
  var fi = $('#f-image');
  if (fi) fi.addEventListener('change', function () {
    if (!fi.files[0]) return;
    var r = new FileReader();
    r.onload = function (e) { $('#img-prev').innerHTML = '<img src="' + e.target.result + '" alt="">'; update(); };
    r.readAsDataURL(fi.files[0]);
  });

  function boot() {
    initEditor();
    d.querySelectorAll('input, textarea').forEach(function (el) { el.addEventListener('input', update); });
    update();
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})();

/* إظهار خيارات «التغطية المباشرة» حسب نوع المحتوى */
(function () {
  var t = document.getElementById('f-type'), box = document.querySelector('[data-show-for="live"]');
  if (!t || !box) return;
  var sync = function () { box.hidden = t.value !== 'live'; };
  t.addEventListener('change', sync); sync();
})();

/* بحث واختيار خبر (ربط العاجل بخبر): يبحث في كل الأخبار المنشورة بالعنوان أو الرقم */
(function () {
  document.querySelectorAll('[data-pick]').forEach(function (box) {
    var api = box.getAttribute('data-pick');
    var hid = box.querySelector('input[type=hidden]'), q = box.querySelector('.pick-q');
    var sel = box.querySelector('.pick-sel'), list = box.querySelector('.pick-list');
    var timer = null, ctl = null, items = [], active = -1, cache = {};
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

    function open(v) { list.hidden = !v; q.setAttribute('aria-expanded', v ? 'true' : 'false'); }
    function render(data) {
      items = data; active = -1;
      list.innerHTML = data.length ? data.map(function (a, i) {
        return '<li role="option" data-i="' + i + '"><img src="' + esc(a.img) + '" alt="" loading="lazy"><span><b>' + esc(a.title) + '</b><small>#' + a.id + ' · ' + esc(a.cat) + ' · ' + esc(a.time) + '</small></span></li>';
      }).join('') : '<li class="pick-empty">لا نتائج — جرّب كلمة أخرى</li>';
      open(true);
    }
    function load() {
      var v = q.value.trim();
      if (cache[v]) return render(cache[v]);
      if (ctl) ctl.abort();
      ctl = window.AbortController ? new AbortController() : null;
      list.innerHTML = '<li class="pick-empty">جارٍ البحث…</li>'; open(true);
      fetch(api + '&q=' + encodeURIComponent(v), { credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
        .then(function (r) { return r.json(); })
        .then(function (j) { cache[v] = j; if (q.value.trim() === v) render(j); })
        .catch(function (e) { if (e.name !== 'AbortError') { list.innerHTML = '<li class="pick-empty">تعذّر البحث</li>'; } });
    }
    function choose(i) {
      var a = items[i]; if (!a) return;
      hid.value = a.id;
      sel.querySelector('span').textContent = a.title;
      sel.hidden = false; q.hidden = true; open(false);
    }
    function clear() {
      hid.value = ''; sel.hidden = true; q.hidden = false; q.value = ''; q.focus(); load();
    }
    function mark(n) {
      var lis = list.querySelectorAll('li[data-i]');
      if (!lis.length) return;
      active = (n + lis.length) % lis.length;
      lis.forEach(function (li, k) { li.classList.toggle('on', k === active); });
      lis[active].scrollIntoView({ block: 'nearest' });
    }

    q.addEventListener('focus', load);
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 200); });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) load(); else mark(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
      else if (e.key === 'Enter') { if (!list.hidden && items.length) { e.preventDefault(); choose(active < 0 ? 0 : active); } }
      else if (e.key === 'Escape') { open(false); }
    });
    list.addEventListener('mousedown', function (e) {
      var li = e.target.closest('li[data-i]');
      if (li) { e.preventDefault(); choose(+li.getAttribute('data-i')); }
    });
    sel.querySelector('button').addEventListener('click', clear);
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) open(false); });
  });
})();
