<?php /** @var string $q @var array $items @var ?array $pg @var array $mostRead */ ?>
<header class="list-head">
  <h1><?= $q !== '' ? 'نتائج البحث عن: «' . e($q) . '»' : 'البحث في الأخبار' ?></h1>
  <?php if ($pg): ?><p>عدد النتائج: <?= (int)$pg['total'] ?></p><?php endif; ?>
  <form class="search-big" action="<?= e(url('search')) ?>" method="get" role="search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="اكتب كلمة البحث…" aria-label="بحث" autofocus>
    <button type="submit" class="btn">بحث</button>
  </form>
</header>
<div class="layout">
  <div class="col-main">
    <?php if ($q !== '' && !$items): ?>
      <p class="empty">لم نعثر على نتائج مطابقة. جرّب كلمات أخرى أو أقل تحديداً.</p>
    <?php endif; ?>
    <div class="stack stack-lg">
      <?php foreach ($items as $a): ?><?= card($a, 'row', ['h' => 'h2']) ?><?php endforeach; ?>
    </div>
    <?= pagination($pg) ?>
  </div>
  <aside class="col-side"><?= most_read_box($mostRead) ?></aside>
</div>
