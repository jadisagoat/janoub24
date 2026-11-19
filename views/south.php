<?php /** @var array $per @var array $latest @var array $mostRead */ $stats = region_stats(); ?>
<?= breadcrumbs_html() ?>

<header class="south-hero">
  <div class="south-hero-text">
    <span class="fs-kicker"><span class="live-dot"></span> تغطية ميدانية على مدار الساعة</span>
    <h1>أخبار الجنوب اللبناني</h1>
    <p>كل ما يحدث في قرى الجنوب ومدنه، مصنّفاً حسب القضاء. اختر قضاءً من الخريطة لتتابع أخباره.</p>
    <div class="region-chips">
      <?php foreach (regions() as $slug => $r): $n = $stats[$slug]['n'] ?? 0; ?>
        <a href="<?= e(region_url($slug)) ?>"><?= e($r['name']) ?><?= $n ? '<b>' . $n . '</b>' : '' ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="south-map"><?= region_map() ?><p class="map-note">خريطة تقريبية لأقضية الجنوب — النقطة الحمراء: أخبار خلال آخر 24 ساعة</p></div>
</header>

<?php if ($latest): ?>
<section class="block">
  <?= section_head('أحدث أخبار الجنوب', url('category/local')) ?>
  <div class="list-lead"><?= card($latest[0], 'hero', ['eager' => true, 'h' => 'h2', 'sizes' => '(max-width: 768px) 100vw, 1200px']) ?></div>
  <div class="grid-4"><?php foreach (array_slice($latest, 1, 8) as $a) echo card($a, 'std'); ?></div>
</section>
<?php endif; ?>

<div class="south-grid">
  <?php foreach ($per as $slug => $items): if (!$items) continue; $r = region($slug); ?>
    <section class="block south-district">
      <?= section_head('أخبار ' . $r['name'], region_url($slug)) ?>
      <?= card($items[0], 'std', ['excerpt' => true]) ?>
      <div class="stack"><?php foreach (array_slice($items, 1) as $a) echo card($a, 'row'); ?></div>
    </section>
  <?php endforeach; ?>
</div>
