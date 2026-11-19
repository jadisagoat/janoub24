<?php
/** @var string $content */
$menu = categories(true);
$path = request_path();
$breaking = breaking_items(10);
$social = social_links();
$liveNow = open_live();
$onLive = $liveNow && preg_match('#^/news/' . (int)$liveNow['id'] . '(/|$)#', $path);
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <?= Seo::render() ?>

  <meta name="theme-color" content="#003257">
  <meta name="format-detection" content="telephone=no">
  <link rel="icon" href="<?= e(asset('img/favicon-48.png')) ?>" sizes="48x48" type="image/png">
  <link rel="icon" href="<?= e(asset('img/logo-192.png')) ?>" sizes="192x192" type="image/png">
  <link rel="apple-touch-icon" href="<?= e(asset('img/logo-192.png')) ?>">
  <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
  <link rel="alternate" type="application/rss+xml" title="<?= e(site_name()) ?>" href="<?= e(url('feed')) ?>">
  <?php if ($v = setting('google_site_verification')): ?><meta name="google-site-verification" content="<?= e($v) ?>">
  <?php endif; if ($v = setting('bing_verification')): ?><meta name="msvalidate.01" content="<?= e($v) ?>">
  <?php endif; ?>
  <link rel="preload" href="<?= e(base_url()) ?>/assets/fonts/noto-kufi-arabic-arabic.woff2" as="font" type="font/woff2" crossorigin>
  <?php if (Seo::$preloadImage): ?><link rel="preload" as="image" href="<?= e(Seo::$preloadImage) ?>" imagesrcset="<?= e(Seo::$preloadSrcset) ?>" imagesizes="(max-width: 768px) 100vw, 800px" fetchpriority="high"<?= Seo::$preloadType ? ' type="' . e(Seo::$preloadType) . '"' : '' ?>>
  <?php endif; ?>
  <link rel="stylesheet" href="<?= e(bundle('css/app.min.css', ['css/fonts.css', 'css/style.css', 'css/features.css'])) ?>">
  <script>(function(d){try{var t=localStorage.getItem('j24-theme');if(t)d.dataset.theme=t;}catch(e){}if('IntersectionObserver'in window&&!matchMedia('(prefers-reduced-motion: reduce)').matches)d.classList.add('js-reveal');})(document.documentElement);</script>
  <?php if ($ga = setting('ga4_id')): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
  <?php endif; ?>
  <?= setting('head_code') ?>
</head>
<body>
<a class="skip" href="#main">انتقل إلى المحتوى</a>

<div class="topbar">
  <div class="wrap topbar-in">
    <span class="today"><?= e(ar_today()) ?></span>
    <span class="weather" data-weather aria-live="polite"></span>
    <nav class="top-links" aria-label="روابط سريعة">
      <a href="<?= e(page_url('about')) ?>">من نحن</a>
      <a href="<?= e(page_url('contact')) ?>">اتصل بنا</a>
      <a href="<?= e(url('latest')) ?>">آخر الأخبار</a>
    </nav>
    <div class="socials">
      <?php foreach ($social as $k => $s): ?>
        <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>"><?= icon($k, 15) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('feed')) ?>" aria-label="RSS"><?= icon('rss', 15) ?></a>
    </div>
  </div>
</div>

<header class="masthead">
  <div class="wrap mast-in">
    <button class="icon-btn menu-btn" type="button" aria-label="القائمة" aria-controls="drawer" aria-expanded="false" data-open-drawer><?= icon('menu', 24) ?></button>
    <a class="logo" href="<?= e(url()) ?>" aria-label="<?= e(site_name()) ?> - الصفحة الرئيسية">
      <img class="logo-l" src="<?= e(asset('img/logo.png')) ?>" width="656" height="170" alt="<?= e(site_name()) ?>">
      <img class="logo-d" src="<?= e(asset('img/logo-dark.png')) ?>" width="656" height="170" alt="" aria-hidden="true">
      <?= logo_glow() ?>
    </a>
    <p class="mast-tag"><?= e(setting('site_tagline', 'أخبار لبنان والجنوب والشرق الأوسط')) ?></p>
    <form class="search-form" action="<?= e(url('search')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="ابحث في الأخبار…" aria-label="بحث" autocomplete="off" value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit" aria-label="بحث"><?= icon('search', 18) ?></button>
    </form>
    <button class="icon-btn" type="button" aria-label="الوضع الليلي" data-theme-toggle><?= icon('moon', 20) ?></button>
  </div>
</header>

<nav class="mainnav" aria-label="الأقسام">
  <div class="wrap nav-in">
    <a class="nav-logo" href="<?= e(url()) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(asset('img/logo.png')) ?>" width="656" height="170" alt=""></a>
    <a href="<?= e(url()) ?>"<?= $path === '/' ? ' aria-current="page"' : '' ?>>الرئيسية</a>
    <a class="nav-south" href="<?= e(url('south')) ?>"<?= $path === '/south' || str_starts_with($path, '/region/') ? ' aria-current="page"' : '' ?>><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" fill="currentColor"/><circle cx="12" cy="10" r="2.6" fill="#fff"/></svg> الجنوب</a>
    <?php foreach ($menu as $c): ?>
      <a href="<?= e(category_url($c)) ?>"<?= $path === '/category/' . $c['slug'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('videos')) ?>"<?= $path === '/videos' ? ' aria-current="page"' : '' ?>>فيديو</a>
    <a class="nav-urgent" href="<?= e(url('breaking')) ?>"><?= icon('bolt', 14) ?> عاجل</a>
  </div>
</nav>

<?php if ($breaking): ?>
<div class="ticker" role="region" aria-label="أخبار عاجلة">
  <div class="wrap ticker-in">
    <a class="ticker-label" href="<?= e(url('breaking')) ?>"><span class="pulse"></span>عاجل</a>
    <div class="ticker-track" data-ticker>
      <ul>
        <?php foreach ($breaking as $b): $l = breaking_link($b); ?>
          <li><time><?= e(date('H:i', ts($b['created_at']))) ?></time><?= $l ? '<a href="' . e($l) . '">' . e($b['text']) . '</a>' : '<span>' . e($b['text']) . '</span>' ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($liveNow && !$onLive): ?>
<a class="live-banner" href="<?= e(article_url($liveNow)) ?>">
  <span class="wrap lb-in">
    <span class="lb-tag"><span class="live-dot"></span>مباشر</span>
    <span class="lb-text"><b><?= e($liveNow['title']) ?></b><?php if (!empty($liveNow['last'])): ?><small><?= e(date('H:i', ts($liveNow['last']['created_at']))) ?> — <?= e(excerpt(live_html($liveNow['last']['body']), 110)) ?></small><?php endif; ?></span>
    <span class="lb-go">تابع التغطية <?= icon('chevron', 14) ?></span>
  </span>
</a>
<?php endif; ?>

<aside class="drawer" id="drawer" aria-hidden="true">
  <div class="drawer-panel">
    <div class="drawer-head">
      <a class="logo logo-sm" href="<?= e(url()) ?>"><img class="logo-l" src="<?= e(asset('img/logo.png')) ?>" width="656" height="170" alt="<?= e(site_name()) ?>" loading="lazy"><img class="logo-d" src="<?= e(asset('img/logo-dark.png')) ?>" width="656" height="170" alt="" aria-hidden="true" loading="lazy"></a>
      <button class="icon-btn" type="button" aria-label="إغلاق" data-close-drawer><?= icon('close', 24) ?></button>
    </div>
    <form class="search-form" action="<?= e(url('search')) ?>" method="get" role="search">
      <input type="search" name="q" placeholder="ابحث في الأخبار…" aria-label="بحث" autocomplete="off">
      <button type="submit" aria-label="بحث"><?= icon('search', 18) ?></button>
    </form>
    <nav class="drawer-nav" aria-label="الأقسام">
      <a href="<?= e(url()) ?>">الرئيسية</a>
      <a href="<?= e(url('latest')) ?>">آخر الأخبار</a>
      <a href="<?= e(url('breaking')) ?>">عاجل</a>
      <a href="<?= e(url('south')) ?>">أخبار الجنوب</a>
      <?php foreach ($menu as $c): ?><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      <a href="<?= e(url('videos')) ?>">فيديو</a>
    </nav>
    <div class="socials drawer-socials">
      <?php foreach ($social as $k => $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>"><?= icon($k, 20) ?></a><?php endforeach; ?>
    </div>
  </div>
</aside>
<div class="backdrop" data-close-drawer></div>

<main id="main" class="wrap">
  <?= $content ?>
</main>

<?php $socialAll = $social + ['rss' => ['url' => url('feed'), 'label' => 'RSS']]; ?>
<section class="follow-strip" aria-label="تابعونا">
  <div class="wrap follow-strip-in">
    <div class="fs-text">
      <span class="fs-kicker"><span class="live-dot"></span> مباشر على مدار الساعة</span>
      <h2>تابعوا <?= e(site_name()) ?> أينما كنتم</h2>
      <p>الخبر العاجل من الجنوب ولبنان والمنطقة، لحظة بلحظة على منصّاتنا.</p>
    </div>
    <div class="fs-links">
      <?php foreach ($socialAll as $k => $s): ?>
        <a class="fs-<?= e($k) ?>" href="<?= e($s['url']) ?>"<?= $k !== 'rss' ? ' target="_blank" rel="noopener"' : '' ?>><?= icon($k, 22) ?><span><?= e($s['label']) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="foot-watermark" aria-hidden="true">24</div>
  <div class="wrap foot-grid">
    <div class="foot-about">
      <a class="logo logo-inv" href="<?= e(url()) ?>"><img src="<?= e(asset('img/logo-dark.png')) ?>" width="656" height="170" alt="<?= e(site_name()) ?>" loading="lazy"><?= logo_glow() ?></a>
      <p><?= e(setting('footer_about', 'جنوب 24 موقع إخباري لبناني مستقل، يواكب الحدث في جنوب لبنان وكل لبنان والمنطقة على مدار الساعة، بالخبر الموثوق والصورة والفيديو.')) ?></p>
      <div class="socials">
        <?php foreach ($socialAll as $k => $s): ?><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" aria-label="<?= e($s['label']) ?>"><?= icon($k, 18) ?></a><?php endforeach; ?>
      </div>
    </div>
    <nav class="foot-cats" aria-label="الأقسام">
      <h2>الأقسام</h2>
      <div class="foot-cols">
        <?php foreach ($menu as $c): ?><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
        <a href="<?= e(url('south')) ?>">أخبار الجنوب</a>
        <a href="<?= e(url('videos')) ?>">فيديو</a>
        <a href="<?= e(url('breaking')) ?>">عاجل</a>
      </div>
    </nav>
    <nav aria-label="روابط">
      <h2><?= e(site_name()) ?></h2>
      <?php foreach (footer_pages() as $p): ?><a href="<?= e(page_url($p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
      <a href="<?= e(url('latest')) ?>">أرشيف الأخبار</a>
    </nav>
    <?php
    $waNum = preg_replace('/\D+/', '', setting('news_whatsapp'));
    $waLink = $waNum !== '' ? 'https://wa.me/' . $waNum . '?text=' . rawurlencode('مرحباً ' . site_name() . '، لدي خبر/صورة من منطقتي:') : page_url('contact');
    $mail = setting('contact_email');
    ?>
    <div class="foot-send">
      <div class="fsend-card">
        <span class="fsend-ico" aria-hidden="true"><svg width="26" height="26" viewBox="0 0 24 24"><path d="M4 7h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5" stroke="currentColor" stroke-width="2" fill="none"/></svg></span>
        <h2>شاركونا الخبر</h2>
        <p>شاهدتم حدثاً في بلدتكم؟ أرسلوا لنا الخبر أو الصورة أو الفيديو، يتحقق منه فريق التحرير وينشره.</p>
        <div class="fsend-btns">
          <a class="fsend-wa" href="<?= e($waLink) ?>"<?= $waNum !== '' ? ' target="_blank" rel="noopener"' : '' ?>><?= icon('whatsapp', 18) ?> أرسل عبر واتساب</a>
          <a class="fsend-mail" href="<?= e($mail !== '' ? 'mailto:' . $mail : page_url('contact')) ?>"><?= icon('mail', 18) ?> راسلنا</a>
        </div>
      </div>
    </div>
  </div>
  <div class="foot-bottom">
    <div class="wrap foot-bottom-in">
      <span>© <?= date('Y') ?> <?= e(site_name()) ?>. جميع الحقوق محفوظة.</span>
      <nav aria-label="قانوني"><a href="<?= e(page_url('privacy')) ?>">الخصوصية</a><a href="<?= e(page_url('terms')) ?>">شروط الاستخدام</a><a href="<?= e(url('sitemap.xml')) ?>">خريطة الموقع</a><a href="<?= e(url('feed')) ?>">RSS</a></nav>
    </div>
  </div>
</footer>

<div class="progress" aria-hidden="true"></div>
<button class="to-top" type="button" aria-label="العودة للأعلى"><?= icon('chevron', 22) ?></button>
<script>window.J24={base:<?= json_encode(base_url()) ?>};</script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/features.js')) ?>" defer></script>
<script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"<?= e(parse_url(base_url(), PHP_URL_PATH) ?: '') ?>/*"},{"not":{"href_matches":"*/admin/*"}},{"not":{"href_matches":"*/api/*"}},{"not":{"href_matches":"*/feed*"}}]},"eagerness":"moderate"}]}</script>
<?= setting('footer_code') ?>
</body>
</html>
