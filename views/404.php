<?php /** @var array $latest */ ?>
<section class="nf">
  <p class="nf-code">404</p>
  <h1>الصفحة غير موجودة</h1>
  <p>ربما نُقل الخبر أو تغيّر رابطه. ابحث عمّا تريد أو تصفّح آخر الأخبار.</p>
  <form class="search-big" action="<?= e(url('search')) ?>" method="get" role="search">
    <input type="search" name="q" placeholder="ابحث في الأخبار…" aria-label="بحث">
    <button type="submit" class="btn">بحث</button>
  </form>
</section>
<?php if ($latest): ?>
<section class="block">
  <?= section_head('آخر الأخبار', url('latest')) ?>
  <div class="grid-4"><?php foreach ($latest as $a): ?><?= card($a, 'std') ?><?php endforeach; ?></div>
</section>
<?php endif; ?>
