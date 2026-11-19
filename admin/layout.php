<?php
/** @var string $title @var string $html @var ?array $me @var ?array $flash @var string $p */
$nav = [
    ['dashboard', 'لوحة التحكم', true],
    ['article', '+ خبر جديد', true],
    ['articles', 'الأخبار', true],
    ['breaking', 'العاجل', can('breaking')],
    ['categories', 'الأقسام', can('categories')],
    ['pages', 'الصفحات', can('pages')],
    ['users', 'المستخدمون', can('users')],
    ['settings', 'الإعدادات والسيو', can('settings')],
    ['seo', 'فحص السيو', can('all_articles')],
    ['tools', 'أدوات', can('settings')],
];
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> — لوحة تحكم <?= e(site_name()) ?></title>
  <link rel="icon" href="<?= e(asset('img/favicon-48.png')) ?>" type="image/png">
  <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<?php if ($me): ?>
<aside class="side" id="side">
  <a class="brand" href="<?= e(admin_url()) ?>"><img src="<?= e(asset('img/logo-dark.png')) ?>" width="656" height="170" alt="<?= e(site_name()) ?>"></a>
  <nav>
    <?php foreach ($nav as [$key, $label, $show]): if (!$show) continue; ?>
      <a href="<?= e(admin_url($key)) ?>" class="<?= $p === $key ? 'on' : '' ?><?= $key === 'article' ? ' new' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="<?= e(url()) ?>" target="_blank">عرض الموقع ↗</a>
  </div>
</aside>
<div class="main">
  <header class="top">
    <button class="burger" type="button" onclick="document.getElementById('side').classList.toggle('open')" aria-label="القائمة">☰</button>
    <h1><?= e($title) ?></h1>
    <div class="me">
      <a href="<?= e(admin_url('profile')) ?>"><?= e($me['name']) ?></a>
      <form method="post" action="<?= e(admin_url('logout')) ?>"><?= csrf_field() ?><button class="link">خروج</button></form>
    </div>
  </header>
  <div class="content">
    <?php if ($flash): ?><div class="alert <?= e($flash[1]) ?>"><?= e($flash[0]) ?></div><?php endif; ?>
    <?= $html ?>
  </div>
</div>
<?php else: ?>
  <?= $html ?>
<?php endif; ?>
<script>window.J24A={site:<?= json_encode(site_name(), JSON_UNESCAPED_UNICODE) ?>,csrf:<?= json_encode(csrf()) ?>,upload:<?= json_encode(admin_url('upload')) ?>,base:<?= json_encode(base_url()) ?>};</script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
