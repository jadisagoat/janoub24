<?php /** @var array $items @var array $mostRead */ ?>
<?= breadcrumbs_html() ?>
<header class="list-head" style="--c:#c8102e">
  <h1><span class="live-dot"></span> عاجل</h1>
  <p>الأخبار العاجلة لحظة بلحظة — يتم تحديث الصفحة تلقائياً.</p>
</header>
<div class="layout">
  <div class="col-main">
    <?php if (!$items): ?><p class="empty">لا أخبار عاجلة حالياً.</p><?php endif; ?>
    <ol class="urgent-list" data-auto-refresh="60">
      <?php $day = null; foreach ($items as $b): $d = ar_date($b['created_at'], false); $l = breaking_link($b); ?>
        <?php if ($d !== $day): $day = $d; ?><li class="day"><?= e($d) ?></li><?php endif; ?>
        <li>
          <time datetime="<?= e(iso($b['created_at'])) ?>"><?= e(date('H:i', ts($b['created_at']))) ?></time>
          <p><?= $l ? '<a href="' . e($l) . '">' . e($b['text']) . '</a>' : e($b['text']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <aside class="col-side"><?= most_read_box($mostRead) ?><?= follow_box() ?></aside>
</div>
