<?php /** @var array $p */ ?>
<?= breadcrumbs_html() ?>
<article class="static-page">
  <h1><?= e($p['title']) ?></h1>
  <div class="story-body"><?= body_render($p['body']) ?></div>
</article>
