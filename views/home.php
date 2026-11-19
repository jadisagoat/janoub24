<?php
/** @var array $featured @var array $latest @var array $mostRead @var array $videos @var array $opinion @var array $blocks */

$renderBlock = static function (array $b): string {
    $c = $b['cat'];
    $items = $b['items'];
    $h = '<section class="block block-' . e($c['home_layout']) . '" style="--c:' . e($c['color']) . '">' . section_head($c['name'], category_url($c), $c['color']);
    if (count($items) === 1) {
        // خبر واحد: بطاقة أفقية عريضة بدل عمود فارغ
        $h .= '<div class="lead-solo">' . card($items[0], 'std', ['excerpt' => true, 'sizes' => '(max-width: 768px) 100vw, 560px']) . '</div>';
    } elseif ($c['home_layout'] === 'grid') {
        $h .= '<div class="grid-4' . (count($items) === 3 ? ' n3' : '') . '">';
        foreach ($items as $a) {
            $h .= card($a, 'std');
        }
        $h .= '</div>';
    } elseif ($c['home_layout'] === 'list') {
        $h .= '<div class="grid-rows">';
        foreach ($items as $a) {
            $h .= card($a, 'row');
        }
        $h .= '</div>';
    } elseif (count($items) === 2) {
        $h .= '<div class="grid-2">' . card($items[0], 'std', ['excerpt' => true]) . card($items[1], 'std', ['excerpt' => true]) . '</div>';
    } else {
        $lead = array_shift($items);
        $h .= '<div class="lead-split"><div class="lead-main">' . card($lead, 'hero', ['h' => 'h3', 'sizes' => '(max-width: 768px) 100vw, 480px']) . '</div><div class="stack">';
        foreach ($items as $a) {
            $h .= card($a, 'row');
        }
        $h .= '</div></div>';
    }
    return $h . '</section>';
};

$topBlocks = array_slice($blocks, 0, 3);
$restBlocks = array_slice($blocks, 3);
?>
<h1 class="sr-only"><?= e(site_name()) ?> - <?= e(setting('site_tagline', 'أخبار لبنان والجنوب والشرق الأوسط')) ?></h1>

<?php if ($featured): ?>
<section class="hero" aria-label="أبرز الأخبار">
  <?php $slides = array_slice($featured, 0, min(5, max(1, count($featured) - 4))); $nS = count($slides); ?>
  <div class="hero-main">
    <div class="slider" data-slider data-interval="6500" role="region" aria-roledescription="carousel" aria-label="أبرز الأخبار" tabindex="0">
      <?php if ($nS > 1): ?>
      <div class="sl-bars">
        <?php foreach ($slides as $i => $a): ?><button type="button" class="sl-bar<?= $i === 0 ? ' run' : '' ?>" data-go="<?= $i ?>" aria-label="الخبر <?= $i + 1 ?>"><i></i></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php foreach ($slides as $i => $a): ?>
        <div class="sl-slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> من <?= $nS ?>"<?= $i ? ' aria-hidden="true"' : '' ?>>
          <?= card($a, 'hero', ['eager' => $i === 0, 'h' => $i === 0 ? 'h2' : 'h3', 'sizes' => '(max-width: 768px) 100vw, 800px']) ?>
        </div>
      <?php endforeach; ?>
      <?php if ($nS > 1): ?>
      <button type="button" class="sl-nav sl-prev" aria-label="الخبر السابق"><?= icon('chevron', 22) ?></button>
      <button type="button" class="sl-nav sl-next" aria-label="الخبر التالي"><?= icon('chevron', 22) ?></button>
      <div class="sl-count" aria-hidden="true"><b>01</b><span>/ <?= sprintf('%02d', $nS) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-side">
    <?php foreach (array_slice($featured, $nS, 4) as $a): ?>
      <?= card($a, 'overlay', ['sizes' => '(max-width: 768px) 50vw, 300px']) ?>
    <?php endforeach; ?>
  </div>
  <aside class="live box" aria-label="آخر الأخبار">
    <div class="sec-head"><h2><a href="<?= e(url('latest')) ?>"><span class="live-dot"></span>آخر الأخبار</a></h2></div>
    <ul class="live-list">
      <?php foreach ($latest as $a): ?>
        <li>
          <time datetime="<?= e(iso($a['published_at'])) ?>"><?= e(date('H:i', ts($a['published_at']))) ?></time>
          <a href="<?= e(article_url($a)) ?>"><?= e($a['title']) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a class="live-more" href="<?= e(url('latest')) ?>">كل الأخبار</a>
  </aside>
</section>
<?php endif; ?>

<?php if (!empty($south)): ?>
<section class="south-block" aria-label="أخبار الجنوب حسب القضاء">
  <div class="sb-head">
    <div class="sb-title">
      <span class="sb-pin" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" fill="currentColor"/><circle cx="12" cy="10" r="2.6" fill="#fff"/></svg></span>
      <div><h2><a href="<?= e(url('south')) ?>">أخبار الجنوب</a></h2><p>تابع ما يجري في كل قضاء</p></div>
    </div>
    <div class="sb-tabs" role="tablist" aria-label="الأقضية">
      <button type="button" role="tab" class="is-on" data-tab="all" aria-selected="true">الكل</button>
      <?php foreach ($south as $slug => $items): ?>
        <button type="button" role="tab" data-tab="<?= e($slug) ?>" aria-selected="false"><?= e(REGIONS[$slug]['name']) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="sb-body">
    <div class="sb-panels">
      <div class="sb-panel is-on" data-panel="all" role="tabpanel">
        <div class="sb-grid"><?php foreach ($southAll as $a) echo card($a, 'std'); ?></div>
        <a class="sb-more" href="<?= e(url('south')) ?>">كل أخبار الجنوب <?= icon('chevron', 14) ?></a>
      </div>
      <?php foreach ($south as $slug => $items): ?>
        <div class="sb-panel" data-panel="<?= e($slug) ?>" role="tabpanel" hidden>
          <div class="sb-grid"><?php foreach ($items as $a) echo card($a, 'std'); ?></div>
          <a class="sb-more" href="<?= e(region_url($slug)) ?>">كل أخبار <?= e(REGIONS[$slug]['full']) ?> <?= icon('chevron', 14) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="sb-side">
      <div class="sb-map"><?= region_map(null, true) ?></div>
      <?php $rst = region_stats(); $rmax = max(1, ...array_map(static fn($x) => $x['n'], array_values($rst) ?: [['n' => 1]])); ?>
      <ul class="sb-regions" aria-label="عدد الأخبار في كل قضاء">
        <?php foreach (regions() as $slug => $r): $st = $rst[$slug] ?? ['n' => 0, 'fresh' => 0]; ?>
          <li><a class="sb-reg<?= $st['fresh'] ? ' is-fresh' : '' ?>" href="<?= e(region_url($slug)) ?>"<?= isset($south[$slug]) ? ' data-tab="' . e($slug) . '"' : '' ?>>
            <span class="sb-reg-name"><?= e($r['name']) ?></span>
            <span class="sb-reg-bar" aria-hidden="true"><i style="--w:<?= round(100 * $st['n'] / $rmax) ?>%"></i></span>
            <b><?= $st['n'] ?></b>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="layout">
  <div class="col-main">
    <?php foreach ($topBlocks as $b) echo $renderBlock($b); ?>
  </div>
  <aside class="col-side">
    <?= prices_box() ?>
    <?= most_read_box($mostRead) ?>
    <?= follow_box() ?>
    <?php $tags = popular_tags(16); if ($tags): ?>
    <section class="box">
      <?= section_head('الأكثر تداولاً') ?>
      <div class="tag-cloud">
        <?php foreach ($tags as $t): ?><a href="<?= e(tag_url($t)) ?>">#<?= e($t['name']) ?></a><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </aside>
</div>

<?php if ($videos): ?>
<section class="video-band bleed" aria-label="فيديو">
  <div class="wrap">
    <?= section_head('فيديو', url('videos'), '#ffffff') ?>
    <?php $vList = array_slice($videos, 1, 4); $vTiles = count($vList) <= 2; /* قليل من الفيديوهات: بطاقات كبيرة تملأ الارتفاع بدل صفوف صغيرة */ ?>
    <div class="video-grid<?= $vList ? '' : ' is-solo' ?>">
      <div class="video-main"><?= card($videos[0], 'overlay', ['sizes' => '(max-width: 768px) 100vw, 700px']) ?></div>
      <?php if ($vList): ?>
      <div class="video-list<?= $vTiles ? ' is-tiles' : '' ?>">
        <?php foreach ($vList as $a): ?><?= card($a, $vTiles ? 'overlay' : 'row') ?><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($opinion): ?>
<section class="block opinion-band" aria-label="رأي">
  <?= section_head('رأي ومقالات', category_url('opinion'), '#0f1b2d') ?>
  <div class="grid-4">
    <?php foreach ($opinion as $a): ?><?= card($a, 'opinion') ?><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($restBlocks): ?>
<div class="blocks-2">
  <?php foreach ($restBlocks as $b) echo $renderBlock($b); ?>
</div>
<?php endif; ?>
