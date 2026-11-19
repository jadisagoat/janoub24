<?php
/** @var array $a @var string $body @var array $tags @var array $related @var array $mostRead @var array $latest @var bool $preview */
$u = article_url($a);
$vid = youtube_id($a['video_url']);
$shareText = rawurlencode($a['title']);
$shareUrl = rawurlencode($u);
?>
<?php if ($preview && !is_live($a)): ?>
  <div class="notice">معاينة: هذا الخبر غير منشور بعد (<?= e($a['status']) ?>).</div>
<?php endif; ?>

<?= breadcrumbs_html() ?>

<div class="layout">
  <article class="col-main story" data-article-id="<?= (int)$a['id'] ?>">
    <header class="story-head">
      <?= badge($a) ?>
      <?php if ($a['type'] !== 'live' && $a['is_breaking']): ?><span class="badge badge-urgent">عاجل</span><?php endif; ?>
      <?php if ($rg = region($a['region'] ?? null)): ?><a class="badge badge-region" href="<?= e(region_url($rg['slug'])) ?>"><svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" fill="currentColor"/></svg><?= e($rg['full']) ?></a><?php endif; ?>
      <h1><?= e($a['title']) ?></h1>
      <?php if ($a['subtitle']): ?><p class="story-sub"><?= e($a['subtitle']) ?></p><?php endif; ?>
      <div class="story-meta">
        <?php if ($a['author_name']): ?>
          <a class="by" href="<?= e(author_url($a)) ?>" rel="author">
            <?php if ($a['author_avatar']): ?><img src="<?= e(img_url($a['author_avatar'])) ?>" alt="" width="36" height="36"><?php endif; ?>
            <span><?= e($a['author_name']) ?></span>
          </a>
        <?php else: ?>
          <span class="by"><span><?= e(site_name()) ?></span></span>
        <?php endif; ?>
        <span class="sep"></span>
        <time datetime="<?= e(iso($a['published_at'])) ?>"><?= icon('clock', 14) ?> <?= e(ar_date($a['published_at'])) ?></time>
        <?php if (ts($a['updated_at']) - ts($a['published_at']) > 600): ?>
          <span class="upd">آخر تحديث: <time datetime="<?= e(iso($a['updated_at'])) ?>"><?= e(ar_date($a['updated_at'])) ?></time></span>
        <?php endif; ?>
        <span class="rt"><?= e(reading_time($a['body'])) ?></span>
      </div>
    </header>

    <?php if ($vid || $a['image']): ?>
    <figure class="story-media">
      <?php if ($vid): ?>
        <div class="yt" data-yt="<?= e($vid) ?>">
          <img src="https://i.ytimg.com/vi/<?= e($vid) ?>/hqdefault.jpg" alt="<?= e($a['title']) ?>" width="480" height="360" fetchpriority="high">
          <button type="button" aria-label="تشغيل الفيديو"><?= icon('play', 34) ?></button>
        </div>
      <?php else: ?>
        <?= img_tag($a['image'], $a['title'], '(max-width: 768px) 100vw, 800px', true) ?>
      <?php endif; ?>
      <?php if ($a['image_caption']): ?><figcaption><?= e($a['image_caption']) ?></figcaption><?php endif; ?>
    </figure>
    <?php endif; ?>

    <div class="share" aria-label="مشاركة">
      <span>شارك:</span>
      <a class="sh-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="فيسبوك"><?= icon('facebook') ?></a>
      <a class="sh-x" href="https://x.com/intent/post?text=<?= $shareText ?>&url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="إكس"><?= icon('x') ?></a>
      <a class="sh-wa" href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="واتساب"><?= icon('whatsapp') ?></a>
      <a class="sh-tg" href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareText ?>" target="_blank" rel="noopener" aria-label="تلغرام"><?= icon('telegram') ?></a>
      <button type="button" class="sh-copy" data-copy="<?= e($u) ?>" aria-label="نسخ الرابط"><?= icon('link') ?></button>
      <button type="button" class="sh-print" onclick="window.print()" aria-label="طباعة"><?= icon('print') ?></button>
      <div class="font-ctl" aria-label="حجم الخط"><button type="button" data-font="-1">أ-</button><button type="button" data-font="1">أ+</button></div>
    </div>

    <?php if ($a['type'] === 'live'): $open = (bool)$a['live_open']; ?>
    <section class="live-feed" data-live="<?= (int)$a['id'] ?>" data-open="<?= $open ? 1 : 0 ?>" data-last="<?= (int)($updates[0]['id'] ?? 0) ?>" aria-label="التغطية المباشرة">
      <div class="lf-head">
        <span class="lf-state<?= $open ? ' is-open' : '' ?>"><span class="live-dot"></span><?= $open ? 'تغطية مباشرة — تُحدَّث تلقائياً' : 'انتهت التغطية المباشرة' ?></span>
        <span class="lf-count"><b><?= count($updates) ?></b> تحديث</span>
      </div>
      <button type="button" class="lf-new" hidden></button>
      <ol class="lf-list" aria-live="polite">
        <?php foreach ($updates as $u): ?>
          <li class="lf-item<?= $u['is_key'] ? ' is-key' : '' ?>" id="u<?= (int)$u['id'] ?>">
            <time datetime="<?= e(iso($u['created_at'])) ?>"><?= e(date('H:i', ts($u['created_at']))) ?><small><?= e(ar_date($u['created_at'], false)) ?></small></time>
            <div class="lf-body"><?php if ($u['is_key']): ?><span class="lf-key">مهم</span><?php endif; ?><?= live_html($u['body']) ?></div>
          </li>
        <?php endforeach; ?>
        <?php if (!$updates): ?><li class="lf-empty">ستظهر التحديثات هنا فور نشرها.</li><?php endif; ?>
      </ol>
    </section>
    <?php endif; ?>

    <div class="story-body" id="story-body">
      <?php
      // لا نكرر المقدمة إن كانت مقتطفاً من الفقرة الأولى نفسها
      $ledeKey = mb_substr(ar_normalize(trim(preg_replace('/[.…\s]+$/u', '', (string)$a['excerpt']))), 0, 60);
      $showLede = $a['excerpt'] && !str_contains(ar_normalize(mb_substr(plain_text($a['body']), 0, 600)), $ledeKey);
      ?>
      <?php if ($showLede): ?><p class="lede"><strong><?= e($a['excerpt']) ?></strong></p><?php endif; ?>
      <?= $body ?>
    </div>

    <?php if ($a['source']): ?><p class="source">المصدر: <?= e($a['source']) ?></p><?php endif; ?>

    <?php if ($tags): ?>
      <div class="story-tags">
        <?php foreach ($tags as $t): ?><a href="<?= e(tag_url($t)) ?>" rel="tag">#<?= e($t['name']) ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php $wa = social_links()['whatsapp'] ?? null; $gn = setting('google_news_url'); if ($wa || $gn): ?>
      <div class="follow-cta">
        <?php if ($wa): ?><a class="btn btn-wa" href="<?= e($wa['url']) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 18) ?> تابعوا قناتنا على واتساب</a><?php endif; ?>
        <?php if ($gn): ?><a class="btn btn-gn" href="<?= e($gn) ?>" target="_blank" rel="noopener">تابعونا على Google News</a><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($a['author_name'] && ($a['author_bio'] || $a['type'] === 'opinion')): ?>
      <aside class="author-box">
        <?php if ($a['author_avatar']): ?><img src="<?= e(img_url($a['author_avatar'])) ?>" alt="<?= e($a['author_name']) ?>" width="80" height="80" loading="lazy"><?php endif; ?>
        <div>
          <a class="name" href="<?= e(author_url($a)) ?>"><?= e($a['author_name']) ?></a>
          <?php if ($a['author_job']): ?><span class="job"><?= e($a['author_job']) ?></span><?php endif; ?>
          <?php if ($a['author_bio']): ?><p><?= e($a['author_bio']) ?></p><?php endif; ?>
        </div>
      </aside>
    <?php endif; ?>

    <?php if ($related): ?>
      <section class="related" aria-label="أخبار ذات صلة">
        <?= section_head('أخبار ذات صلة') ?>
        <div class="grid-3">
          <?php foreach ($related as $r): ?><?= card($r, 'std') ?><?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </article>

  <aside class="col-side">
    <?= most_read_box($mostRead) ?>
    <?= prices_box() ?>
    <section class="box">
      <?= section_head('آخر الأخبار', url('latest')) ?>
      <div class="stack">
        <?php foreach ($latest as $l): ?><?= card($l, 'row') ?><?php endforeach; ?>
      </div>
    </section>
    <?= follow_box() ?>
  </aside>
</div>
