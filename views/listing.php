<?php
/** @var string $heading @var ?string $intro @var array $items @var array $pg @var bool $lead @var array $mostRead */
$color = $color ?? null;
$timeline = $timeline ?? false;
$author = $author ?? null;
$region = $region ?? null;
?>
<?= breadcrumbs_html() ?>

<header class="list-head"<?= $color ? ' style="--c:' . e($color) . '"' : '' ?>>
  <?php if ($author): ?>
    <div class="author-head">
      <?php if ($author['avatar']): ?><img src="<?= e(img_url($author['avatar'])) ?>" alt="<?= e($author['name']) ?>" width="96" height="96"><?php endif; ?>
      <div>
        <h1><?= e($author['name']) ?></h1>
        <?php if ($author['job_title']): ?><p class="job"><?= e($author['job_title']) ?></p><?php endif; ?>
        <?php if ($author['bio']): ?><p><?= e($author['bio']) ?></p><?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <h1><?= e($heading) ?></h1>
    <?php if ($intro): ?><p><?= e($intro) ?></p><?php endif; ?>
  <?php endif; ?>
  <?php if ($region): ?>
    <nav class="region-chips region-chips-sm" aria-label="أقضية الجنوب">
      <a href="<?= e(url('south')) ?>">كل الجنوب</a>
      <?php foreach (regions() as $rs => $rr): ?><a href="<?= e(region_url($rs)) ?>"<?= $rs === $region['slug'] ? ' aria-current="page"' : '' ?>><?= e($rr['name']) ?></a><?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if (!empty($feed)): ?><a class="feed-link" href="<?= e($feed) ?>"><?= icon('rss', 14) ?> RSS</a><?php endif; ?>
</header>

<div class="layout">
  <div class="col-main">
    <?php if (!$items): ?>
      <p class="empty">لا توجد أخبار في هذا القسم حالياً.</p>
    <?php elseif ($timeline): ?>
      <ol class="timeline">
        <?php foreach ($items as $a): ?>
          <li>
            <time datetime="<?= e(iso($a['published_at'])) ?>"><?= e(date('H:i', ts($a['published_at']))) ?><small><?= e(ar_date($a['published_at'], false)) ?></small></time>
            <?= card($a, 'row', ['h' => 'h2']) ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <?php if ($lead): $first = array_shift($items); ?>
        <div class="list-lead"><?= card($first, 'hero', ['eager' => true, 'h' => 'h2', 'sizes' => '(max-width: 768px) 100vw, 800px']) ?></div>
      <?php endif; ?>
      <div class="grid-3">
        <?php foreach ($items as $a): ?><?= card($a, 'std', ['h' => 'h2', 'excerpt' => true]) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= pagination($pg) ?>
  </div>
  <aside class="col-side">
    <?php if ($region): ?><section class="box"><?= section_head('أقضية الجنوب', url('south')) ?><div class="side-map"><?= region_map($region['slug']) ?></div></section><?php endif; ?>
    <?= most_read_box($mostRead) ?>
    <?= follow_box() ?>
  </aside>
</div>
