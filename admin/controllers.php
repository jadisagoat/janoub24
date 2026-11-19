<?php
declare(strict_types=1);

/* ================= الدخول والخروج ================= */

function a_login(): void
{
    if (current_user()) {
        redirect(admin_url());
    }
    $err = '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    if (is_post()) {
        check_csrf();
        if (login_blocked($ip)) {
            $err = 'محاولات كثيرة خاطئة. حاول بعد 15 دقيقة.';
        } else {
            $u = row('SELECT * FROM users WHERE email = ? AND is_active = 1', [mb_strtolower(post('email'))]);
            // تجزئة وهمية صالحة عند عدم وجود البريد: نفس زمن التحقق، فلا يُعرف إن كان الحساب موجوداً
            $hash = $u['password_hash'] ?? '$2y$10$lie9eczget/zT4KpfFYbCOpB4euIhIX2dnX9NXLel8O6uZuNnhq5S';
            if (password_verify((string)($_POST['password'] ?? ''), $hash) && $u) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                login_reset($ip);
                q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), $u['id']]);
                }
                redirect(admin_url());
            }
            login_failed($ip);
            usleep(600000);
            $err = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }
    }
    ob_start(); ?>
    <div class="login-wrap">
      <form class="login card" method="post">
        <div class="brand-lg"><img src="<?= e(asset('img/logo.png')) ?>" width="656" height="170" alt="<?= e(site_name()) ?>"></div>
        <p class="muted">تسجيل الدخول إلى غرفة الأخبار</p>
        <?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
        <?= csrf_field() ?>
        <label>البريد الإلكتروني<input type="email" name="email" required autofocus value="<?= e(post('email')) ?>" dir="ltr"></label>
        <label>كلمة المرور<input type="password" name="password" required dir="ltr"></label>
        <button class="btn btn-full">دخول</button>
      </form>
    </div>
    <?php admin_render('تسجيل الدخول', ob_get_clean());
}

function a_logout(): void
{
    if (!is_post()) {
        redirect(admin_url()); // يمنع تسجيل الخروج القسري عبر رابط
    }
    check_csrf();
    $_SESSION = [];
    session_destroy();
    setcookie('j24_admin', '', ['expires' => time() - 3600, 'path' => '/']);
    redirect(admin_url('login'));
}

/* ================= لوحة التحكم ================= */

function a_dashboard(): void
{
    $stats = [
        'اليوم'           => (int)val("SELECT COUNT(*) FROM articles WHERE status='published' AND DATE(published_at) = CURDATE()"),
        'إجمالي المنشور'  => (int)val("SELECT COUNT(*) FROM articles WHERE status='published'"),
        'مسودات ومراجعة'  => (int)val("SELECT COUNT(*) FROM articles WHERE status <> 'published'"),
        'المشاهدات (7 أيام)' => (int)val("SELECT COALESCE(SUM(views),0) FROM articles WHERE published_at >= NOW() - INTERVAL 7 DAY"),
    ];
    $top = rows("SELECT id, title, slug, views, published_at FROM articles WHERE status='published' AND published_at >= NOW() - INTERVAL 7 DAY ORDER BY views DESC LIMIT 8");
    $pending = rows("SELECT a.id, a.title, a.status, u.name author FROM articles a LEFT JOIN users u ON u.id=a.author_id WHERE a.status='pending' ORDER BY a.updated_at DESC LIMIT 8");
    $recent = rows("SELECT a.id, a.title, a.status, a.published_at, c.name cat FROM articles a JOIN categories c ON c.id=a.category_id ORDER BY a.id DESC LIMIT 8");
    $demo = (int)val('SELECT COUNT(*) FROM articles WHERE is_demo = 1');
    ob_start(); ?>
    <?php if ($demo && can('settings')): ?>
      <div class="alert warn">الموقع يحتوي على <?= $demo ?> خبراً تجريبياً. احذفها قبل الإطلاق من <a href="<?= e(admin_url('tools')) ?>">الأدوات</a>.</div>
    <?php endif; ?>
    <div class="stats">
      <?php foreach ($stats as $k => $v): ?><div class="stat card"><b><?= e(number_format($v)) ?></b><span><?= e($k) ?></span></div><?php endforeach; ?>
    </div>
    <div class="grid2">
      <section class="card">
        <h2>الأكثر قراءة هذا الأسبوع</h2>
        <table class="tbl"><tbody>
        <?php foreach ($top as $a): ?>
          <tr><td><a href="<?= e(admin_url('article', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td><td class="num"><?= number_format((int)$a['views']) ?></td></tr>
        <?php endforeach; if (!$top): ?><tr><td class="muted">لا بيانات بعد.</td></tr><?php endif; ?>
        </tbody></table>
      </section>
      <section class="card">
        <h2>آخر ما أُضيف</h2>
        <table class="tbl"><tbody>
        <?php foreach ($recent as $a): ?>
          <tr><td><a href="<?= e(admin_url('article', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a><div class="muted sm"><?= e($a['cat']) ?> · <?= e(time_ago($a['published_at'])) ?></div></td><td><?= status_badge($a['status'], $a['published_at']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </section>
      <?php if ($pending && can('publish')): ?>
      <section class="card">
        <h2>بانتظار المراجعة</h2>
        <table class="tbl"><tbody>
        <?php foreach ($pending as $a): ?>
          <tr><td><a href="<?= e(admin_url('article', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a></td><td class="muted"><?= e($a['author']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </section>
      <?php endif; ?>
      <section class="card">
        <h2>روابط السيو — قدّمها في Google Search Console</h2>
        <ul class="links">
          <li><a href="<?= e(url('sitemap.xml')) ?>" target="_blank" dir="ltr"><?= e(url('sitemap.xml')) ?></a></li>
          <li><a href="<?= e(url('sitemap-news.xml')) ?>" target="_blank" dir="ltr"><?= e(url('sitemap-news.xml')) ?></a> <span class="muted sm">(Google News)</span></li>
          <li><a href="<?= e(url('feed')) ?>" target="_blank" dir="ltr"><?= e(url('feed')) ?></a> <span class="muted sm">(RSS)</span></li>
          <li><a href="<?= e(url('robots.txt')) ?>" target="_blank" dir="ltr"><?= e(url('robots.txt')) ?></a></li>
        </ul>
      </section>
    </div>
    <?php admin_render('لوحة التحكم', ob_get_clean());
}

/* ================= الأخبار ================= */

function a_articles(): void
{
    $me = current_user();
    $w = ['1'];
    $prm = [];
    $f = ['q' => trim((string)($_GET['q'] ?? '')), 'status' => (string)($_GET['status'] ?? ''), 'cat' => (int)($_GET['cat'] ?? 0)];
    if ($f['q'] !== '') {
        $w[] = 'a.title LIKE ?';
        $prm[] = '%' . $f['q'] . '%';
    }
    if (in_array($f['status'], ['draft', 'pending', 'published'], true)) {
        $w[] = 'a.status = ?';
        $prm[] = $f['status'];
    }
    if ($f['cat']) {
        $w[] = 'a.category_id = ?';
        $prm[] = $f['cat'];
    }
    if (!can('all_articles')) {
        $w[] = 'a.author_id = ?';
        $prm[] = $me['id'];
    }
    $where = implode(' AND ', $w);
    $per = 30;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)val("SELECT COUNT(*) FROM articles a WHERE $where", $prm);
    $list = rows("SELECT a.id, a.title, a.slug, a.status, a.published_at, a.views, a.is_featured, a.is_breaking, a.type, a.image,
        c.name cat, u.name author FROM articles a JOIN categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.author_id
        WHERE $where ORDER BY a.published_at DESC LIMIT " . (($page - 1) * $per) . ", $per", $prm);
    $cats = ['' => 'كل الأقسام'] + array_column(categories(), 'name', 'id');
    ob_start(); ?>
    <form class="filters card" method="get">
      <input type="hidden" name="p" value="articles">
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="ابحث بالعنوان…">
      <select name="status"><?= sel(['' => 'كل الحالات', 'published' => 'منشور', 'pending' => 'بانتظار المراجعة', 'draft' => 'مسودة'], $f['status']) ?></select>
      <select name="cat"><?= sel($cats, $f['cat'] ?: '') ?></select>
      <button class="btn">تصفية</button>
      <a class="btn btn-ghost" href="<?= e(admin_url('article')) ?>">+ خبر جديد</a>
    </form>
    <div class="card">
      <p class="muted sm">العدد: <?= number_format($total) ?></p>
      <table class="tbl tbl-list">
        <thead><tr><th></th><th>العنوان</th><th>القسم</th><th>الكاتب</th><th>الحالة</th><th>التاريخ</th><th>مشاهدات</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $a): ?>
          <tr>
            <td class="thumb"><img src="<?= e(img_url($a['image'], 'sm')) ?>" alt="" width="80" height="45" loading="lazy"></td>
            <td><a class="ttl" href="<?= e(admin_url('article', ['id' => $a['id']])) ?>"><?= e($a['title']) ?></a>
              <?= $a['is_featured'] ? '<span class="tag">مميز</span>' : '' ?><?= $a['is_breaking'] ? '<span class="tag tag-red">عاجل</span>' : '' ?><?= $a['type'] !== 'news' ? '<span class="tag">' . e(['video' => 'فيديو', 'opinion' => 'رأي', 'gallery' => 'صور', 'live' => '● مباشر'][$a['type']] ?? '') . '</span>' : '' ?></td>
            <td><?= e($a['cat']) ?></td>
            <td class="muted"><?= e($a['author'] ?? '—') ?></td>
            <td><?= status_badge($a['status'], $a['published_at']) ?></td>
            <td class="muted sm nowrap"><?= e(ar_date($a['published_at'])) ?></td>
            <td class="num"><?= number_format((int)$a['views']) ?></td>
            <td class="nowrap">
              <a href="<?= e(article_url($a)) ?><?= $a['status'] !== 'published' ? '?preview=1' : '' ?>" target="_blank" title="عرض">↗</a>
              <?php if (can('publish')): ?>
              <form method="post" action="<?= e(admin_url('article_del', ['id' => $a['id']])) ?>" class="inline" onsubmit="return confirm('حذف الخبر نهائياً؟')"><?= csrf_field() ?><button class="link danger" title="حذف">✕</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?= admin_pager($total, $per, $page, array_merge(['p' => 'articles'], array_filter($f))) ?>
    </div>
    <?php admin_render('الأخبار', ob_get_clean());
}

function a_article_delete(): void
{
    require_can('publish');
    if (!is_post()) {
        redirect(admin_url('articles'));
    }
    check_csrf();
    q('DELETE FROM articles WHERE id = ?', [(int)($_GET['id'] ?? 0)]);
    cache_clear();
    flash('تم حذف الخبر.');
    redirect(admin_url('articles'));
}

function a_article_form(): void
{
    $me = current_user();
    $id = (int)($_GET['id'] ?? 0);
    $a = $id ? row('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    if ($id && !$a) {
        flash('الخبر غير موجود.', 'err');
        redirect(admin_url('articles'));
    }
    if ($a && !can('all_articles') && ((int)$a['author_id'] !== (int)$me['id'] || $a['status'] === 'published')) {
        require_can('all_articles');
    }
    $errors = [];

    if (is_post()) {
        check_csrf();
        $status = post('status', 'draft');
        if (!in_array($status, ['draft', 'pending', 'published'], true)) {
            $status = 'draft';
        }
        if ($status === 'published' && !can('publish')) {
            $status = 'pending';
        }
        $pa = post('published_at');
        $publishedAt = $pa !== '' && strtotime($pa) ? date('Y-m-d H:i:s', strtotime($pa)) : null;
        if (!$publishedAt) {
            $publishedAt = ($a && $a['status'] === 'published') ? $a['published_at'] : date('Y-m-d H:i:s');
        }
        $type = in_array(post('type'), ['news', 'video', 'opinion', 'gallery', 'live'], true) ? post('type') : 'news';
        $d = [
            'title'           => mb_substr(post('title'), 0, 255),
            'subtitle'        => mb_substr(post('subtitle'), 0, 255) ?: null,
            'slug'            => slugify(post('slug') !== '' ? post('slug') : post('title')),
            'excerpt'         => mb_substr(post('excerpt'), 0, 500) ?: null,
            'body'            => body_store(clean_html((string)($_POST['body'] ?? ''))),
            'category_id'     => (int)post('category_id'),
            'type'            => $type,
            'live_open'       => $type === 'live' ? (int)isset($_POST['live_open']) : 0,
            'video_url'       => post('video_url') ?: null,
            'image_caption'   => mb_substr(post('image_caption'), 0, 255) ?: null,
            'source'          => mb_substr(post('source'), 0, 190) ?: null,
            'seo_title'       => mb_substr(post('seo_title'), 0, 190) ?: null,
            'seo_description' => mb_substr(post('seo_description'), 0, 300) ?: null,
            'focus_keyword'   => mb_substr(post('focus_keyword'), 0, 120) ?: null,
            'status'          => $status,
            'published_at'    => $publishedAt,
            'is_featured'     => can('publish') ? (int)isset($_POST['is_featured']) : (int)($a['is_featured'] ?? 0),
            'is_breaking'     => can('publish') ? (int)isset($_POST['is_breaking']) : (int)($a['is_breaking'] ?? 0),
            'author_id'       => can('all_articles') && (int)post('author_id') ? (int)post('author_id') : (int)($a['author_id'] ?? $me['id']),
            'image'           => $a['image'] ?? null,
        ];
        if ($d['title'] === '') {
            $errors[] = 'العنوان مطلوب.';
        }
        if (trim(strip_tags($d['body'])) === '' && $type !== 'video') {
            $errors[] = 'نص الخبر مطلوب.';
        }
        if (!val('SELECT id FROM categories WHERE id = ?', [$d['category_id']])) {
            $errors[] = 'اختر قسماً.';
        }
        if ($d['video_url'] && !youtube_id($d['video_url'])) {
            $errors[] = 'رابط يوتيوب غير صالح.';
        }
        if (isset($_POST['remove_image'])) {
            $d['image'] = null;
        }
        if (!$errors && !empty($_FILES['image']['name'])) {
            try {
                $d['image'] = save_uploaded_image($_FILES['image'], $d['title']);
            } catch (Throwable $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        if (!$errors) {
            $cols = array_keys($d);
            if ($a) {
                $set = implode(', ', array_map(static fn($c) => "`$c` = ?", $cols));
                q("UPDATE articles SET $set, updated_at = NOW() WHERE id = ?", array_merge(array_values($d), [$id]));
            } else {
                q('INSERT INTO articles (`' . implode('`, `', $cols) . '`, updated_at) VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ', ?)', array_merge(array_values($d), [$d['published_at']]));
                $id = (int)db()->lastInsertId();
            }
            sync_tags($id, post('tags'));
            refresh_search_text($id);
            // القضاء: تلقائي من النص، أو اختيار المحرر
            $reg = post('region', 'auto');
            $reg = $reg === 'auto' ? detect_region($d['title'], (string)$d['excerpt'], $d['body']) : (region($reg) ? $reg : null);
            q('UPDATE articles SET region = ? WHERE id = ?', [$reg, $id]);

            $live = $status === 'published' && ts($publishedAt) <= time();
            $wasBreaking = (int)($a['is_breaking'] ?? 0);
            if ($live && $d['is_breaking'] && !$wasBreaking && !val('SELECT id FROM breaking_news WHERE article_id = ?', [$id])) {
                q('INSERT INTO breaking_news (text, article_id, expires_at) VALUES (?, ?, NOW() + INTERVAL 24 HOUR)', [$d['title'], $id]);
            }
            cache_clear();
            if ($live) {
                indexnow_ping([url('news/' . $id . '/' . $d['slug'])]);
            }
            flash($a ? 'تم حفظ التعديلات.' : 'تم إنشاء الخبر.');
            redirect(admin_url('article', ['id' => $id]));
        }
        $a = array_merge($a ?? [], $d, ['id' => $id ?: null]);
        $tagsCsv = post('tags');
    } else {
        $tagsCsv = $a ? implode('، ', array_column(article_tags($id), 'name')) : '';
    }

    $a = $a ?? ['id' => null, 'title' => '', 'subtitle' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'category_id' => 0, 'type' => 'news',
        'video_url' => '', 'image' => null, 'image_caption' => '', 'source' => '', 'seo_title' => '', 'seo_description' => '', 'focus_keyword' => '',
        'status' => can('publish') ? 'published' : 'draft', 'region' => 'auto', 'live_open' => 1, 'published_at' => null, 'is_featured' => 0, 'is_breaking' => 0, 'author_id' => $me['id']];
    $statusOpts = can('publish') ? ['published' => 'منشور', 'pending' => 'بانتظار المراجعة', 'draft' => 'مسودة'] : ['draft' => 'مسودة', 'pending' => 'إرسال للمراجعة'];
    $authors = array_column(rows('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name'), 'name', 'id');
    $cats = ['' => '— اختر القسم —'] + array_column(categories(), 'name', 'id');
    $publicUrl = $a['id'] ? url('news/' . $a['id'] . '/' . $a['slug']) : null;

    ob_start(); ?>
    <?php foreach ($errors as $er): ?><div class="alert err"><?= e($er) ?></div><?php endforeach; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
    <form method="post" enctype="multipart/form-data" class="article-form" id="article-form">
      <?= csrf_field() ?>
      <div class="af-main">
        <section class="card">
          <label class="lbl-big">العنوان <span class="cnt" data-count="title" data-max="70"></span>
            <input type="text" name="title" id="f-title" value="<?= e($a['title']) ?>" required maxlength="255" placeholder="عنوان واضح وجذاب يتضمن الكلمة المفتاحية">
          </label>
          <label>العنوان الفرعي (اختياري)
            <input type="text" name="subtitle" value="<?= e($a['subtitle']) ?>" maxlength="255">
          </label>
          <label>الرابط (Slug)
            <input type="text" name="slug" id="f-slug" value="<?= e($a['slug']) ?>" placeholder="يُنشأ تلقائياً من العنوان">
          </label>
          <?php if ($publicUrl): ?><p class="muted sm" dir="ltr"><a href="<?= e($publicUrl) ?><?= $a['status'] !== 'published' ? '?preview=1' : '' ?>" target="_blank"><?= e(rawurldecode($publicUrl)) ?></a></p><?php endif; ?>
          <label>الملخص / المقدمة <span class="cnt" data-count="excerpt" data-max="160"></span>
            <textarea name="excerpt" id="f-excerpt" rows="3" maxlength="500" placeholder="جملة أو جملتان تلخصان الخبر — تظهر في نتائج البحث ومواقع التواصل"><?= e($a['excerpt']) ?></textarea>
          </label>
          <label>نص الخبر</label>
          <div id="editor"><?= body_render((string)$a['body']) ?></div>
          <textarea name="body" id="f-body" hidden><?= e(body_render((string)$a['body'])) ?></textarea>
          <p class="muted sm" id="wc"></p>
        </section>

        <section class="card seo-box">
          <h2>تحسين محركات البحث (SEO)</h2>
          <label>الكلمة المفتاحية الرئيسية
            <input type="text" name="focus_keyword" id="f-kw" value="<?= e($a['focus_keyword']) ?>" placeholder="مثال: بلدية صور">
          </label>
          <label>عنوان السيو (اختياري) <span class="cnt" data-count="seo_title" data-max="60"></span>
            <input type="text" name="seo_title" id="f-seo-title" value="<?= e($a['seo_title']) ?>" maxlength="190" placeholder="يُستخدم العنوان الأصلي إن تُرك فارغاً">
          </label>
          <label>وصف السيو (اختياري) <span class="cnt" data-count="seo_description" data-max="160"></span>
            <textarea name="seo_description" id="f-seo-desc" rows="2" maxlength="300" placeholder="يُستخدم الملخص إن تُرك فارغاً"><?= e($a['seo_description']) ?></textarea>
          </label>
          <div class="serp">
            <div class="serp-site"><img src="<?= e(asset('img/favicon-48.png')) ?>" alt="" width="18" height="18"> <?= e(site_name()) ?> <span dir="ltr"><?= e(parse_url(base_url(), PHP_URL_HOST)) ?></span></div>
            <div class="serp-title" id="serp-title"></div>
            <div class="serp-desc" id="serp-desc"></div>
          </div>
          <ul class="checks" id="seo-checks"></ul>
        </section>
      </div>

      <aside class="af-side">
        <section class="card">
          <h2>النشر</h2>
          <label>الحالة<select name="status"><?= sel($statusOpts, $a['status']) ?></select></label>
          <label>تاريخ النشر (اتركه فارغاً = الآن، أو حدد موعداً لاحقاً للجدولة)
            <input type="datetime-local" name="published_at" value="<?= $a['published_at'] ? e(date('Y-m-d\TH:i', ts($a['published_at']))) : '' ?>">
          </label>
          <?php if (can('publish')): ?>
          <label class="chk"><input type="checkbox" name="is_featured" value="1"<?= checked($a['is_featured']) ?>> خبر مميز (يظهر في الواجهة)</label>
          <label class="chk"><input type="checkbox" name="is_breaking" value="1"<?= checked($a['is_breaking']) ?>> عاجل (يُضاف لشريط العاجل)</label>
          <?php endif; ?>
          <div class="row-btns">
            <button class="btn btn-full" type="submit">حفظ</button>
            <?php if ($publicUrl): ?><a class="btn btn-ghost" href="<?= e($publicUrl) ?>?preview=1" target="_blank">معاينة</a><?php endif; ?>
          </div>
        </section>
        <section class="card">
          <label>القسم<select name="category_id" required><?= sel($cats, $a['category_id'] ?: '') ?></select></label>
<?php $regionOpts = ['auto' => 'تحديد تلقائي من النص', '' => '— بدون قضاء —']; foreach (regions() as $rs => $rr) { $regionOpts[$rs] = $rr['full']; } ?>
          <label>قضاء الجنوب<select name="region"><?= sel($regionOpts, $a['region'] ?? '') ?></select></label>
          <label>نوع المحتوى<select name="type" id="f-type"><?= sel(['news' => 'خبر', 'live' => 'تغطية مباشرة', 'video' => 'فيديو', 'opinion' => 'مقال رأي', 'gallery' => 'ألبوم صور'], $a['type']) ?></select></label>
          <div class="live-box" data-show-for="live">
            <label class="chk"><input type="checkbox" name="live_open" value="1"<?= checked($a['live_open'] ?? 1) ?>> التغطية مفتوحة (شارة «مباشر» + تحديث تلقائي)</label>
            <?php if ($a['id']): ?><a class="btn btn-full btn-live" href="<?= e(admin_url('live', ['id' => $a['id']])) ?>">● إدارة التحديثات المباشرة</a><?php else: ?><p class="muted sm">احفظ الخبر أولاً ثم أضف التحديثات.</p><?php endif; ?>
          </div>
          <label>رابط يوتيوب<input type="url" name="video_url" value="<?= e($a['video_url']) ?>" dir="ltr" placeholder="https://www.youtube.com/watch?v=..."></label>
          <?php if (can('all_articles')): ?>
          <label>الكاتب<select name="author_id"><?= sel($authors, $a['author_id']) ?></select></label>
          <?php endif; ?>
          <label>الوسوم (افصل بفاصلة)<input type="text" name="tags" id="f-tags" value="<?= e($tagsCsv) ?>" placeholder="صور، بلديات، الجنوب"></label>
          <label>المصدر (اختياري)<input type="text" name="source" value="<?= e($a['source']) ?>"></label>
        </section>
        <section class="card">
          <h2>الصورة الرئيسية</h2>
          <div class="img-prev" id="img-prev"><?php if ($a['image']): ?><img src="<?= e(img_url($a['image'], 'md')) ?>" alt=""><?php else: ?><span class="muted">لا صورة</span><?php endif; ?></div>
          <input type="file" name="image" id="f-image" accept="image/jpeg,image/png,image/webp">
          <p class="muted sm">يُفضّل 1200×675 أو أكبر. تُضغط تلقائياً إلى WebP بثلاثة مقاسات.</p>
          <?php if ($a['image']): ?><label class="chk"><input type="checkbox" name="remove_image" value="1"> إزالة الصورة</label><?php endif; ?>
          <label>تعليق الصورة<input type="text" name="image_caption" value="<?= e($a['image_caption']) ?>"></label>
        </section>
      </aside>
    </form>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
    <?php admin_render($a['id'] ? 'تعديل خبر' : 'خبر جديد', ob_get_clean());
}

/** رفع صورة من داخل المحرر */
function a_upload(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (!is_post()) {
        http_response_code(405);
        exit('{}');
    }
    check_csrf();
    try {
        $path = save_inline_image($_FILES['image'] ?? []);
        echo json_encode(['url' => base_url() . '/' . $path]);
    } catch (Throwable $ex) {
        http_response_code(422);
        echo json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

/* ================= الأقسام ================= */

function a_categories(): void
{
    require_can('categories');
    $edit = isset($_GET['id']) ? row('SELECT * FROM categories WHERE id = ?', [(int)$_GET['id']]) : null;
    if (is_post()) {
        check_csrf();
        if (isset($_POST['delete'])) {
            $cid = (int)$_POST['delete'];
            if (val('SELECT COUNT(*) FROM articles WHERE category_id = ?', [$cid])) {
                flash('لا يمكن حذف قسم يحتوي على أخبار. انقل الأخبار أولاً.', 'err');
            } else {
                q('DELETE FROM categories WHERE id = ?', [$cid]);
                flash('تم حذف القسم.');
            }
            cache_clear();
            redirect(admin_url('categories'));
        }
        $name = post('name');
        $slug = strtolower(preg_replace('/[^a-z0-9-]+/i', '-', post('slug') ?: 'cat-' . time()));
        $slug = trim($slug, '-') ?: 'cat-' . time();
        $color = preg_match('/^#[0-9a-f]{6}$/i', post('color')) ? post('color') : '#0b6fae';
        $layout = in_array(post('home_layout'), ['lead', 'grid', 'list'], true) ? post('home_layout') : 'lead';
        $vals = [$name, $slug, post('description') ?: null, post('seo_title') ?: null, $color, (int)post('sort_order'), (int)isset($_POST['show_in_menu']), (int)isset($_POST['show_on_home']), $layout];
        if ($name === '') {
            flash('اسم القسم مطلوب.', 'err');
        } elseif (val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, (int)($edit['id'] ?? 0)])) {
            flash('الرابط مستخدم لقسم آخر.', 'err');
        } else {
            if ($edit) {
                q('UPDATE categories SET name=?, slug=?, description=?, seo_title=?, color=?, sort_order=?, show_in_menu=?, show_on_home=?, home_layout=? WHERE id=?', array_merge($vals, [$edit['id']]));
            } else {
                q('INSERT INTO categories (name, slug, description, seo_title, color, sort_order, show_in_menu, show_on_home, home_layout) VALUES (?,?,?,?,?,?,?,?,?)', $vals);
            }
            cache_clear();
            flash('تم حفظ القسم.');
        }
        redirect(admin_url('categories'));
    }
    $list = rows('SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) n FROM categories c ORDER BY sort_order, id');
    $c = $edit ?? ['name' => '', 'slug' => '', 'description' => '', 'seo_title' => '', 'color' => '#0b6fae', 'sort_order' => count($list) + 1, 'show_in_menu' => 1, 'show_on_home' => 1, 'home_layout' => 'lead'];
    ob_start(); ?>
    <div class="grid2 grid-side">
      <section class="card">
        <table class="tbl">
          <thead><tr><th>القسم</th><th>الرابط</th><th>الأخبار</th><th>القائمة</th><th>الواجهة</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($list as $r): ?>
            <tr>
              <td><span class="dot" style="background:<?= e($r['color']) ?>"></span><a href="<?= e(admin_url('categories', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a></td>
              <td dir="ltr" class="muted sm"><?= e($r['slug']) ?></td>
              <td class="num"><?= (int)$r['n'] ?></td>
              <td><?= $r['show_in_menu'] ? '✓' : '—' ?></td>
              <td><?= $r['show_on_home'] ? '✓' : '—' ?></td>
              <td><form method="post" class="inline" onsubmit="return confirm('حذف القسم؟')"><?= csrf_field() ?><button class="link danger" name="delete" value="<?= (int)$r['id'] ?>">✕</button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <form class="card" method="post" action="<?= e(admin_url('categories', $edit ? ['id' => $edit['id']] : [])) ?>">
        <h2><?= $edit ? 'تعديل: ' . e($edit['name']) : 'قسم جديد' ?></h2>
        <?= csrf_field() ?>
        <label>الاسم<input name="name" value="<?= e($c['name']) ?>" required></label>
        <label>الرابط (حروف إنجليزية)<input name="slug" value="<?= e($c['slug']) ?>" dir="ltr" placeholder="politics"></label>
        <label>عنوان السيو<input name="seo_title" value="<?= e($c['seo_title']) ?>" placeholder="أخبار سياسة لبنان اليوم"></label>
        <label>الوصف (يظهر في جوجل)<textarea name="description" rows="3"><?= e($c['description']) ?></textarea></label>
        <div class="row">
          <label>اللون<input type="color" name="color" value="<?= e($c['color']) ?>"></label>
          <label>الترتيب<input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>"></label>
        </div>
        <label>شكل العرض في الواجهة<select name="home_layout"><?= sel(['lead' => 'خبر كبير + قائمة', 'grid' => 'شبكة 4 بطاقات', 'list' => 'قائمة صفين'], $c['home_layout']) ?></select></label>
        <label class="chk"><input type="checkbox" name="show_in_menu"<?= checked($c['show_in_menu']) ?>> يظهر في القائمة</label>
        <label class="chk"><input type="checkbox" name="show_on_home"<?= checked($c['show_on_home']) ?>> يظهر في الواجهة</label>
        <button class="btn btn-full">حفظ</button>
        <?php if ($edit): ?><a class="btn btn-ghost btn-full" href="<?= e(admin_url('categories')) ?>">إلغاء</a><?php endif; ?>
      </form>
    </div>
    <?php admin_render('الأقسام', ob_get_clean());
}

/* ================= العاجل ================= */

function a_breaking(): void
{
    require_can('breaking');
    if (is_post()) {
        check_csrf();
        if (isset($_POST['delete'])) {
            q('DELETE FROM breaking_news WHERE id = ?', [(int)$_POST['delete']]);
            flash('تم الحذف.');
        } elseif (post('text') !== '') {
            $hours = max(1, min(168, (int)post('hours', '24')));
            $aid = (int)post('article_id') ?: null;
            q('INSERT INTO breaking_news (text, article_id, url, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ' . $hours . ' HOUR)',
                [mb_substr(post('text'), 0, 300), $aid, post('url') ?: null]);
            flash('تمت إضافة الخبر العاجل.');
        }
        cache_clear();
        redirect(admin_url('breaking'));
    }
    $list = rows('SELECT b.*, a.title FROM breaking_news b LEFT JOIN articles a ON a.id = b.article_id ORDER BY b.created_at DESC LIMIT 100');
    $recent = ['' => '— بدون ربط —'] + array_column(rows("SELECT id, title FROM articles WHERE status='published' ORDER BY published_at DESC LIMIT 40"), 'title', 'id');
    ob_start(); ?>
    <form class="card breaking-form" method="post">
      <?= csrf_field() ?>
      <label class="lbl-big">نص الخبر العاجل<input name="text" maxlength="300" required placeholder="اكتب العاجل بإيجاز…" autofocus></label>
      <div class="row">
        <label>ربط بخبر<select name="article_id"><?= sel($recent, '') ?></select></label>
        <label>أو رابط خارجي<input type="url" name="url" dir="ltr"></label>
        <label>يظهر في الشريط لمدة (ساعات)<input type="number" name="hours" value="24" min="1" max="168"></label>
      </div>
      <button class="btn">نشر العاجل</button>
    </form>
    <section class="card">
      <table class="tbl">
        <thead><tr><th>الوقت</th><th>النص</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $b): $active = !$b['expires_at'] || ts($b['expires_at']) > time(); ?>
          <tr>
            <td class="nowrap muted sm"><?= e(ar_date($b['created_at'])) ?></td>
            <td><?= e($b['text']) ?><?= $b['title'] ? '<div class="muted sm">↳ ' . e($b['title']) . '</div>' : '' ?></td>
            <td><?= $active ? '<span class="st st-pub">في الشريط</span>' : '<span class="st st-draft">منتهي</span>' ?></td>
            <td><form method="post" class="inline"><?= csrf_field() ?><button class="link danger" name="delete" value="<?= (int)$b['id'] ?>">✕</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php admin_render('الأخبار العاجلة', ob_get_clean());
}

/* ================= الصفحات ================= */

function a_pages(): void
{
    require_can('pages');
    if (is_post()) {
        check_csrf();
        q('DELETE FROM pages WHERE id = ?', [(int)post('delete')]);
        cache_clear();
        flash('تم حذف الصفحة.');
        redirect(admin_url('pages'));
    }
    $list = rows('SELECT * FROM pages ORDER BY id');
    ob_start(); ?>
    <p><a class="btn" href="<?= e(admin_url('page')) ?>">+ صفحة جديدة</a></p>
    <section class="card">
      <table class="tbl">
        <thead><tr><th>العنوان</th><th>الرابط</th><th>في التذييل</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $pg): ?>
          <tr>
            <td><a href="<?= e(admin_url('page', ['id' => $pg['id']])) ?>"><?= e($pg['title']) ?></a></td>
            <td dir="ltr" class="sm"><a href="<?= e(page_url($pg['slug'])) ?>" target="_blank">/page/<?= e($pg['slug']) ?></a></td>
            <td><?= $pg['show_in_footer'] ? '✓' : '—' ?></td>
            <td><form method="post" class="inline" onsubmit="return confirm('حذف الصفحة؟')"><?= csrf_field() ?><button class="link danger" name="delete" value="<?= (int)$pg['id'] ?>">✕</button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php admin_render('الصفحات الثابتة', ob_get_clean());
}

function a_page_form(): void
{
    require_can('pages');
    $id = (int)($_GET['id'] ?? 0);
    $pg = $id ? row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
    if (is_post()) {
        check_csrf();
        $slug = trim(strtolower(preg_replace('/[^a-z0-9-]+/i', '-', post('slug') ?: 'page-' . time())), '-');
        $vals = [post('title'), $slug, body_store(clean_html((string)($_POST['body'] ?? ''))), post('seo_description') ?: null, (int)isset($_POST['show_in_footer'])];
        if (val('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$slug, $id])) {
            flash('الرابط مستخدم.', 'err');
            redirect(admin_url('page', $id ? ['id' => $id] : []));
        }
        if ($pg) {
            q('UPDATE pages SET title=?, slug=?, body=?, seo_description=?, show_in_footer=? WHERE id=?', array_merge($vals, [$id]));
        } else {
            q('INSERT INTO pages (title, slug, body, seo_description, show_in_footer) VALUES (?,?,?,?,?)', $vals);
            $id = (int)db()->lastInsertId();
        }
        cache_clear();
        flash('تم حفظ الصفحة.');
        redirect(admin_url('page', ['id' => $id]));
    }
    $pg = $pg ?? ['title' => '', 'slug' => '', 'body' => '', 'seo_description' => '', 'show_in_footer' => 1];
    ob_start(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
    <form method="post" class="card" id="article-form">
      <?= csrf_field() ?>
      <label class="lbl-big">العنوان<input name="title" value="<?= e($pg['title']) ?>" required></label>
      <div class="row">
        <label>الرابط (إنجليزي)<input name="slug" value="<?= e($pg['slug']) ?>" dir="ltr" placeholder="about"></label>
        <label>وصف السيو<input name="seo_description" value="<?= e($pg['seo_description']) ?>" maxlength="300"></label>
      </div>
      <div id="editor"><?= body_render($pg['body']) ?></div>
      <textarea name="body" id="f-body" hidden><?= e(body_render($pg['body'])) ?></textarea>
      <label class="chk"><input type="checkbox" name="show_in_footer"<?= checked($pg['show_in_footer']) ?>> رابط في التذييل</label>
      <button class="btn">حفظ</button>
    </form>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
    <?php admin_render($id ? 'تعديل صفحة' : 'صفحة جديدة', ob_get_clean());
}

/* ================= المستخدمون ================= */

function a_users(): void
{
    require_can('users');
    $list = rows('SELECT u.*, (SELECT COUNT(*) FROM articles a WHERE a.author_id = u.id) n FROM users u ORDER BY u.role, u.name');
    $roles = ['admin' => 'مدير', 'editor' => 'محرر', 'writer' => 'كاتب'];
    ob_start(); ?>
    <p><a class="btn" href="<?= e(admin_url('user')) ?>">+ مستخدم جديد</a></p>
    <section class="card">
      <table class="tbl">
        <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>الأخبار</th><th>آخر دخول</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($list as $u): ?>
          <tr>
            <td><a href="<?= e(admin_url('user', ['id' => $u['id']])) ?>"><?= e($u['name']) ?></a><div class="muted sm"><?= e($u['job_title']) ?></div></td>
            <td dir="ltr" class="sm"><?= e($u['email']) ?></td>
            <td><?= e($roles[$u['role']]) ?></td>
            <td class="num"><?= (int)$u['n'] ?></td>
            <td class="muted sm"><?= $u['last_login'] ? e(time_ago($u['last_login'])) : '—' ?></td>
            <td><?= $u['is_active'] ? '<span class="st st-pub">فعّال</span>' : '<span class="st st-draft">موقوف</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted sm">المدير: كل الصلاحيات · المحرر: نشر وتعديل كل الأخبار والأقسام والعاجل · الكاتب: يكتب أخباره ويرسلها للمراجعة.</p>
    </section>
    <?php admin_render('المستخدمون', ob_get_clean());
}

function user_form_save(?array $u, bool $isAdminForm): ?string
{
    $name = post('name');
    $email = mb_strtolower(post('email'));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'الاسم والبريد الصحيح مطلوبان.';
    }
    if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int)($u['id'] ?? 0)])) {
        return 'البريد مستخدم لحساب آخر.';
    }
    $slug = slugify(post('slug') ?: $name, 60);
    if (val('SELECT id FROM users WHERE slug = ? AND id <> ?', [$slug, (int)($u['id'] ?? 0)])) {
        $slug .= '-' . random_int(10, 99);
    }
    $pass = (string)($_POST['password'] ?? '');
    if (!$u && strlen($pass) < 8) {
        return 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    }
    if ($pass !== '' && strlen($pass) < 8) {
        return 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    }
    $d = ['name' => $name, 'slug' => $slug, 'email' => $email, 'job_title' => post('job_title') ?: null, 'bio' => post('bio') ?: null,
        'twitter' => post('twitter') ?: null, 'facebook' => post('facebook') ?: null];
    if ($isAdminForm) {
        $d['role'] = in_array(post('role'), ['admin', 'editor', 'writer'], true) ? post('role') : 'writer';
        $d['is_active'] = (int)isset($_POST['is_active']);
        if ($u && (int)$u['id'] === (int)current_user()['id']) {
            $d['role'] = 'admin';
            $d['is_active'] = 1; // لا يمكنك إيقاف نفسك
        }
    }
    if ($pass !== '') {
        $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
    }
    if (!empty($_FILES['avatar']['name'])) {
        try {
            $d['avatar'] = save_inline_image($_FILES['avatar'], 300, true);
        } catch (Throwable $ex) {
            return $ex->getMessage();
        }
    }
    $cols = array_keys($d);
    if ($u) {
        q('UPDATE users SET ' . implode(', ', array_map(static fn($c) => "`$c` = ?", $cols)) . ' WHERE id = ?', array_merge(array_values($d), [$u['id']]));
    } else {
        q('INSERT INTO users (`' . implode('`,`', $cols) . '`) VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')', array_values($d));
    }
    cache_clear();
    return null;
}

function user_form_html(array $u, bool $isAdminForm): string
{
    ob_start(); ?>
    <form method="post" enctype="multipart/form-data" class="card narrow">
      <?= csrf_field() ?>
      <div class="row">
        <label>الاسم<input name="name" value="<?= e($u['name']) ?>" required></label>
        <label>البريد<input type="email" name="email" value="<?= e($u['email']) ?>" required dir="ltr"></label>
      </div>
      <div class="row">
        <label>الصفة<input name="job_title" value="<?= e($u['job_title']) ?>" placeholder="مراسل / محرر / كاتب رأي"></label>
        <label>الرابط<input name="slug" value="<?= e($u['slug']) ?>" dir="ltr"></label>
      </div>
      <label>نبذة (تظهر في صفحة الكاتب — مهمة لثقة جوجل E-E-A-T)<textarea name="bio" rows="3"><?= e($u['bio']) ?></textarea></label>
      <div class="row">
        <label>حساب إكس<input type="url" name="twitter" value="<?= e($u['twitter']) ?>" dir="ltr"></label>
        <label>فيسبوك<input type="url" name="facebook" value="<?= e($u['facebook']) ?>" dir="ltr"></label>
      </div>
      <div class="row">
        <label>الصورة الشخصية<input type="file" name="avatar" accept="image/*"></label>
        <?php if (!empty($u['avatar'])): ?><img class="avatar" src="<?= e(img_url($u['avatar'])) ?>" alt="" width="64" height="64"><?php endif; ?>
      </div>
      <label>كلمة المرور <?= !empty($u['id']) ? '(اتركها فارغة لعدم التغيير)' : '' ?><input type="password" name="password" autocomplete="new-password" dir="ltr" minlength="8"></label>
      <?php if ($isAdminForm): ?>
      <div class="row">
        <label>الدور<select name="role"><?= sel(['writer' => 'كاتب', 'editor' => 'محرر', 'admin' => 'مدير'], $u['role']) ?></select></label>
        <label class="chk"><input type="checkbox" name="is_active"<?= checked($u['is_active']) ?>> حساب فعّال</label>
      </div>
      <?php endif; ?>
      <button class="btn">حفظ</button>
    </form>
    <?php return ob_get_clean();
}

function a_user_form(): void
{
    require_can('users');
    $id = (int)($_GET['id'] ?? 0);
    $u = $id ? row('SELECT * FROM users WHERE id = ?', [$id]) : null;
    if (is_post()) {
        check_csrf();
        $err = user_form_save($u, true);
        if ($err) {
            flash($err, 'err');
        } else {
            flash('تم حفظ المستخدم.');
            redirect(admin_url('users'));
        }
        redirect(admin_url('user', $id ? ['id' => $id] : []));
    }
    $u = $u ?? ['id' => null, 'name' => '', 'email' => '', 'slug' => '', 'job_title' => '', 'bio' => '', 'twitter' => '', 'facebook' => '', 'avatar' => null, 'role' => 'writer', 'is_active' => 1];
    admin_render($id ? 'تعديل مستخدم' : 'مستخدم جديد', user_form_html($u, true));
}

function a_profile(): void
{
    $u = current_user();
    if (is_post()) {
        check_csrf();
        $err = user_form_save($u, false);
        flash($err ?? 'تم حفظ ملفك الشخصي.', $err ? 'err' : 'ok');
        redirect(admin_url('profile'));
    }
    admin_render('ملفي الشخصي', user_form_html($u, false));
}

/* ================= الإعدادات ================= */

function a_settings(): void
{
    require_can('settings');
    $groups = [
        'الهوية' => [
            'site_name' => ['اسم الموقع', 'text'],
            'site_name_en' => ['الاسم بالإنجليزية', 'text'],
            'site_tagline' => ['الشعار النصي', 'text'],
            'site_description' => ['وصف الموقع (للصفحة الرئيسية في جوجل — 150 حرفاً تقريباً)', 'textarea'],
            'footer_about' => ['نبذة التذييل', 'textarea'],
            'contact_email' => ['بريد التحرير', 'email'],
            'founding_date' => ['سنة التأسيس', 'text'],
        ],
        'التواصل الاجتماعي' => [
            'social_facebook' => ['فيسبوك', 'url'],
            'social_x' => ['إكس (تويتر)', 'url'],
            'twitter_handle' => ['معرّف إكس (مثل @janoub24website)', 'text'],
            'social_youtube' => ['يوتيوب', 'url'],
            'social_instagram' => ['إنستغرام', 'url'],
            'social_whatsapp' => ['قناة واتساب', 'url'],
            'news_whatsapp' => ['رقم واتساب لاستقبال الأخبار من القرّاء (مثال: 9613123456)', 'text'],
            'social_telegram' => ['قناة تلغرام', 'url'],
            'facebook_app_id' => ['Facebook App ID (اختياري)', 'text'],
        ],
        'محركات البحث والتحليلات' => [
            'google_site_verification' => ['رمز تحقق Google Search Console', 'text'],
            'bing_verification' => ['رمز تحقق Bing Webmaster', 'text'],
            'google_news_name' => ['اسم النشرة في Google News', 'text'],
            'google_news_url' => ['رابط متابعة الموقع على Google News', 'url'],
            'ga4_id' => ['معرّف Google Analytics 4 (G-XXXX)', 'text'],
            'indexnow_key' => ['مفتاح IndexNow (فهرسة فورية في Bing و Yandex)', 'text'],
        ],
        'أسعار اليوم (تظهر في الشريط الجانبي — اترك الحقل فارغاً لإخفائه)' => [
            'price_usd' => ['سعر صرف الدولار في السوق (ل.ل)', 'text'],
            'price_95' => ['صفيحة بنزين 95 أوكتان (ل.ل)', 'text'],
            'price_98' => ['صفيحة بنزين 98 أوكتان (ل.ل)', 'text'],
            'price_diesel' => ['صفيحة المازوت (ل.ل)', 'text'],
            'price_gas' => ['قارورة الغاز (ل.ل)', 'text'],
        ],
        'أكواد مخصصة' => [
            'head_code' => ['كود داخل <head> (إعلانات/تتبع)', 'code'],
            'footer_code' => ['كود قبل </body>', 'code'],
        ],
    ];
    if (is_post()) {
        check_csrf();
        $st = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        foreach (['price_usd', 'price_95', 'price_98', 'price_diesel', 'price_gas'] as $pk) {
            if (trim((string)($_POST[$pk] ?? '')) !== setting($pk)) {
                $st->execute(['prices_updated', date('Y-m-d H:i:s')]);
                break;
            }
        }
        foreach ($groups as $fields) {
            foreach ($fields as $k => $_) {
                $st->execute([$k, trim((string)($_POST[$k] ?? ''))]);
            }
        }
        cache_clear();
        flash('تم حفظ الإعدادات.');
        redirect(admin_url('settings'));
    }
    ob_start(); ?>
    <form method="post" class="settings">
      <?= csrf_field() ?>
      <?php foreach ($groups as $g => $fields): ?>
        <section class="card">
          <h2><?= e($g) ?></h2>
          <?php foreach ($fields as $k => [$label, $type]): $v = setting($k); ?>
            <label><?= e($label) ?>
              <?php if ($type === 'textarea' || $type === 'code'): ?>
                <textarea name="<?= e($k) ?>" rows="<?= $type === 'code' ? 4 : 2 ?>"<?= $type === 'code' ? ' dir="ltr" class="mono"' : '' ?>><?= e($v) ?></textarea>
              <?php else: ?>
                <input type="<?= e($type) ?>" name="<?= e($k) ?>" value="<?= e($v) ?>"<?= in_array($type, ['url', 'email'], true) ? ' dir="ltr"' : '' ?>>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
      <button class="btn">حفظ الإعدادات</button>
    </form>
    <?php admin_render('الإعدادات والسيو', ob_get_clean());
}

/* ================= أدوات ================= */

function a_tools(): void
{
    require_can('settings');
    if (is_post()) {
        check_csrf();
        $act = post('action');
        if ($act === 'cache') {
            flash('تم مسح ' . cache_clear() . ' صفحة من الذاكرة المؤقتة.');
        } elseif ($act === 'demo') {
            q('DELETE FROM articles WHERE is_demo = 1');
            q("DELETE FROM users WHERE email LIKE '%@demo.janoub24'");
            q('DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM article_tags)');
            q('DELETE FROM breaking_news WHERE article_id IS NULL AND url IS NULL');
            foreach (glob(ROOT . '/uploads/demo/*') ?: [] as $f) {
                @unlink($f);
            }
            cache_clear();
            flash('تم حذف المحتوى التجريبي بالكامل.');
        } elseif ($act === 'indexnow') {
            $urls = array_map('article_url', articles(['since_hours' => 48, 'limit' => 100]));
            indexnow_ping(array_merge([url()], $urls));
            flash('تم إرسال ' . (count($urls) + 1) . ' رابطاً إلى IndexNow (يعمل فقط على النطاق الحقيقي).');
        }
        redirect(admin_url('tools'));
    }
    $demo = (int)val('SELECT COUNT(*) FROM articles WHERE is_demo = 1');
    $cacheFiles = count(glob(cache_dir() . '/*.html') ?: []);
    ob_start(); ?>
    <div class="grid2">
      <form method="post" class="card"><?= csrf_field() ?>
        <h2>الذاكرة المؤقتة</h2>
        <p class="muted">صفحات مخزنة حالياً: <?= $cacheFiles ?>. تُمسح تلقائياً عند أي نشر أو تعديل.</p>
        <button class="btn" name="action" value="cache">مسح الذاكرة المؤقتة</button>
      </form>
      <form method="post" class="card" onsubmit="return confirm('سيتم حذف كل الأخبار والكتّاب التجريبيين نهائياً. متابعة؟')"><?= csrf_field() ?>
        <h2>المحتوى التجريبي</h2>
        <p class="muted">أخبار تجريبية: <?= $demo ?>. احذفها قبل إطلاق الموقع.</p>
        <button class="btn btn-danger" name="action" value="demo"<?= $demo ? '' : ' disabled' ?>>حذف المحتوى التجريبي</button>
      </form>
      <form method="post" class="card"><?= csrf_field() ?>
        <h2>فهرسة فورية IndexNow</h2>
        <p class="muted">يرسل أخبار آخر 48 ساعة إلى Bing و Yandex فوراً. (يُرسل كل خبر جديد تلقائياً عند نشره.)</p>
        <button class="btn" name="action" value="indexnow">إرسال الآن</button>
      </form>
      <section class="card">
        <h2>معلومات النظام</h2>
        <ul class="links sm">
          <li>PHP <?= e(PHP_VERSION) ?> · MySQL <?= e((string)val('SELECT VERSION()')) ?></li>
          <li>WebP: <?= function_exists('imagewebp') ? '✓' : '✗' ?> · التخزين المؤقت: <?= (int)cfg('cache_ttl') ?> ثانية</li>
          <li>الرابط: <span dir="ltr"><?= e(base_url()) ?></span></li>
        </ul>
      </section>
    </div>
    <?php admin_render('أدوات', ob_get_clean());
}


/* ================= التغطية المباشرة ================= */

function a_live(): void
{
    $me = current_user();
    $id = (int)($_GET['id'] ?? 0);
    $a = row('SELECT id, title, slug, type, live_open, status, author_id FROM articles WHERE id = ?', [$id]);
    if (!$a) {
        flash('الخبر غير موجود.', 'err');
        redirect(admin_url('articles'));
    }
    if (!can('all_articles') && (int)$a['author_id'] !== (int)$me['id']) {
        require_can('all_articles');
    }
    if (is_post()) {
        check_csrf();
        if (isset($_POST['delete'])) {
            q('DELETE FROM live_updates WHERE id = ? AND article_id = ?', [(int)$_POST['delete'], $id]);
            flash('تم حذف التحديث.');
        } elseif (isset($_POST['toggle'])) {
            q('UPDATE articles SET live_open = 1 - live_open, updated_at = NOW() WHERE id = ?', [$id]);
            flash($a['live_open'] ? 'تم إنهاء التغطية المباشرة.' : 'تم فتح التغطية المباشرة.');
        } else {
            $text = trim((string)($_POST['body'] ?? ''));
            $html = $text !== '' ? live_text_html($text) : '';
            if (!empty($_FILES['image']['name'])) {
                try {
                    $html .= '<figure><img src="{{base}}/' . save_inline_image($_FILES['image']) . '" alt="" loading="lazy"></figure>';
                } catch (Throwable $ex) {
                    flash($ex->getMessage(), 'err');
                    redirect(admin_url('live', ['id' => $id]));
                }
            }
            if ($html === '') {
                flash('اكتب نص التحديث.', 'err');
            } else {
                q('INSERT INTO live_updates (article_id, author_id, body, is_key) VALUES (?, ?, ?, ?)', [$id, $me['id'], $html, (int)isset($_POST['is_key'])]);
                q('UPDATE articles SET updated_at = NOW() WHERE id = ?', [$id]);
                flash('تم نشر التحديث — يظهر للقرّاء خلال 30 ثانية دون إعادة تحميل.');
            }
        }
        cache_clear();
        redirect(admin_url('live', ['id' => $id]));
    }
    $ups = live_updates($id);
    $publicUrl = url('news/' . $a['id'] . '/' . $a['slug']);
    ob_start(); ?>
    <?php if ($a['type'] !== 'live'): ?>
      <div class="alert warn">نوع هذا الخبر ليس «تغطية مباشرة». غيّر النوع من <a href="<?= e(admin_url('article', ['id' => $id])) ?>">محرر الخبر</a> لتظهر التحديثات للقرّاء.</div>
    <?php endif; ?>
    <div class="live-top card">
      <div>
        <span class="st <?= $a['live_open'] ? 'st-live' : 'st-draft' ?>"><?= $a['live_open'] ? '● مباشر الآن' : 'التغطية مغلقة' ?></span>
        <h2><?= e($a['title']) ?></h2>
        <a href="<?= e($publicUrl) ?>" target="_blank">عرض التغطية ↗</a> · <a href="<?= e(admin_url('article', ['id' => $id])) ?>">تعديل الخبر</a>
      </div>
      <form method="post"><?= csrf_field() ?><button class="btn <?= $a['live_open'] ? 'btn-ghost' : '' ?>" name="toggle" value="1"><?= $a['live_open'] ? 'إنهاء التغطية' : 'فتح التغطية' ?></button></form>
    </div>
    <form class="card live-form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="lbl-big">تحديث جديد
        <textarea name="body" rows="4" placeholder="اكتب التحديث بإيجاز… (سطر فارغ = فقرة جديدة، والروابط تتحول تلقائياً)" autofocus></textarea>
      </label>
      <div class="row">
        <label>صورة (اختياري)<input type="file" name="image" accept="image/*"></label>
        <label class="chk"><input type="checkbox" name="is_key" value="1"> تحديث مهم (يُميَّز باللون)</label>
      </div>
      <button class="btn">نشر التحديث</button>
    </form>
    <section class="card">
      <h2>التحديثات المنشورة (<?= count($ups) ?>)</h2>
      <ol class="live-admin-list">
        <?php foreach ($ups as $u): ?>
          <li class="<?= $u['is_key'] ? 'is-key' : '' ?>">
            <time><?= e(date('H:i', ts($u['created_at']))) ?><small><?= e(ar_date($u['created_at'], false)) ?></small></time>
            <div><?= live_html($u['body']) ?></div>
            <form method="post" onsubmit="return confirm('حذف التحديث؟')"><?= csrf_field() ?><button class="link danger" name="delete" value="<?= (int)$u['id'] ?>">✕</button></form>
          </li>
        <?php endforeach; if (!$ups): ?><li class="muted">لا تحديثات بعد.</li><?php endif; ?>
      </ol>
    </section>
    <?php admin_render('التغطية المباشرة', ob_get_clean());
}

/* ================= فحص السيو ================= */

function a_seo(): void
{
    require_can('all_articles');
    $live = "a.status = 'published'";
    $total = (int)val("SELECT COUNT(*) FROM articles a WHERE $live");
    $checks = [
        'noimg'    => ['أخبار بدون صورة رئيسية', 'جوجل Discover وأخبار جوجل لا يعرضان الخبر غالباً دون صورة كبيرة (1200 بكسل).', "a.image IS NULL"],
        'excerpt'  => ['ملخص مفقود أو قصير (أقل من 70 حرفاً)', 'الملخص يظهر في نتائج البحث ومشاركات فيسبوك؛ اجعله جملة واضحة بين 70 و160 حرفاً.', "(a.excerpt IS NULL OR CHAR_LENGTH(a.excerpt) < 70) AND (a.seo_description IS NULL OR CHAR_LENGTH(a.seo_description) < 70)"],
        'longt'    => ['عنوان أطول من 70 حرفاً', 'العنوان الطويل يُقصّ في نتائج جوجل؛ أضف «عنوان سيو» أقصر من محرر الخبر.', "CHAR_LENGTH(COALESCE(NULLIF(a.seo_title, ''), a.title)) > 70"],
        'shortt'   => ['عنوان قصير جداً (أقل من 25 حرفاً)', 'العناوين القصيرة جداً لا تحمل كلمات بحث كافية.', "CHAR_LENGTH(a.title) < 25"],
        'notags'   => ['أخبار بدون وسوم', 'الوسوم تنشئ صفحات موضوعات وروابط داخلية تقوّي الأرشفة.', "NOT EXISTS (SELECT 1 FROM article_tags t WHERE t.article_id = a.id)"],
        'thin'     => ['نص قصير جداً (أقل من 100 كلمة تقريباً)', 'المحتوى القصير جداً يُعتبر «ضعيفاً»؛ أضف تفاصيل أو خلفية للخبر.', "CHAR_LENGTH(a.body) < 600 AND a.type <> 'live'"],
        'noregion' => ['أخبار محلية بلا قضاء', 'تحديد القضاء يضيف الخبر لصفحة القضاء (مثل «أخبار النبطية») ويقوّي البحث المحلي.', "a.region IS NULL AND a.category_id = (SELECT id FROM categories WHERE slug = 'local')"],
    ];
    $res = [];
    $issues = 0;
    foreach ($checks as $k => [$label, $hint, $where]) {
        $n = (int)val("SELECT COUNT(*) FROM articles a WHERE $live AND $where");
        $list = $n ? rows("SELECT a.id, a.title, a.published_at FROM articles a WHERE $live AND $where ORDER BY a.published_at DESC LIMIT 25") : [];
        $res[$k] = compact('label', 'hint', 'n', 'list');
        $issues += $n;
    }
    $dups = rows("SELECT title, COUNT(*) n FROM articles WHERE status = 'published' GROUP BY title HAVING n > 1 ORDER BY n DESC LIMIT 20");
    $score = $total ? max(0, (int)round(100 - 100 * $issues / ($total * count($checks)))) : 100;
    $site = [
        ['الموقع يعمل بـ HTTPS', str_starts_with(base_url(), 'https://')],
        ['وضع التجربة (staging) مطفأ', !cfg('staging')],
        ['رمز تحقق Google Search Console', setting('google_site_verification') !== ''],
        ['Google Analytics 4', setting('ga4_id') !== ''],
        ['بريد التحرير (مطلوب لـ Google News)', setting('contact_email') !== ''],
        ['صفحتا «من نحن» و«سياسة التحرير»', (int)val("SELECT COUNT(*) FROM pages WHERE slug IN ('about','editorial-policy')") === 2],
        ['رابط Google News في الإعدادات', setting('google_news_url') !== ''],
        ['مفتاح IndexNow (فهرسة فورية في Bing)', setting('indexnow_key') !== ''],
        ['نبذة لكل كاتب نشط (E-E-A-T)', !val("SELECT COUNT(*) FROM users u WHERE u.is_active = 1 AND (u.bio IS NULL OR u.bio = '') AND EXISTS (SELECT 1 FROM articles a WHERE a.author_id = u.id)")],
    ];
    ob_start(); ?>
    <div class="seo-score card">
      <div class="ring" style="--p:<?= $score ?>"><b><?= $score ?>%</b><span>صحة السيو</span></div>
      <div>
        <h2>تقرير السيو لـ <?= number_format($total) ?> خبراً منشوراً</h2>
        <p class="muted">كل مشكلة تُصلح ترفع فرص ظهور الأخبار في جوجل وأخبار جوجل وDiscover. اضغط على أي خبر لتعديله.</p>
        <p class="sm"><a href="<?= e(url('sitemap.xml')) ?>" target="_blank" dir="ltr">sitemap.xml</a> · <a href="<?= e(url('sitemap-news.xml')) ?>" target="_blank" dir="ltr">sitemap-news.xml</a> · <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">اختبار النتائج المنسّقة</a> · <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener">PageSpeed</a></p>
      </div>
    </div>
    <div class="grid2">
      <section class="card">
        <h2>إعدادات الموقع</h2>
        <ul class="checks site-checks">
          <?php foreach ($site as [$l, $ok]): ?><li class="<?= $ok ? 'ok' : 'no' ?>"><?= e($l) ?></li><?php endforeach; ?>
        </ul>
        <p class="sm"><a href="<?= e(admin_url('settings')) ?>">فتح الإعدادات ←</a></p>
      </section>
      <section class="card">
        <h2>ملخص المشاكل</h2>
        <table class="tbl"><tbody>
          <?php foreach ($res as $k => $r): ?>
            <tr><td><a href="#c-<?= e($k) ?>"><?= e($r['label']) ?></a></td><td class="num"><?= $r['n'] ? '<span class="st st-pend">' . $r['n'] . '</span>' : '<span class="st st-pub">✓</span>' ?></td></tr>
          <?php endforeach; ?>
          <tr><td>عناوين مكررة</td><td class="num"><?= $dups ? '<span class="st st-pend">' . count($dups) . '</span>' : '<span class="st st-pub">✓</span>' ?></td></tr>
        </tbody></table>
      </section>
    </div>
    <?php foreach ($res as $k => $r): if (!$r['n']) continue; ?>
      <details class="card seo-issue" id="c-<?= e($k) ?>">
        <summary><b><?= e($r['label']) ?></b> <span class="st st-pend"><?= $r['n'] ?></span></summary>
        <p class="muted sm"><?= e($r['hint']) ?></p>
        <ul class="links"><?php foreach ($r['list'] as $it): ?><li><a href="<?= e(admin_url('article', ['id' => $it['id']])) ?>"><?= e($it['title']) ?></a> <span class="muted sm"><?= e(ar_date($it['published_at'], false)) ?></span></li><?php endforeach; ?></ul>
        <?php if ($r['n'] > 25): ?><p class="muted sm">… و<?= $r['n'] - 25 ?> غيرها.</p><?php endif; ?>
      </details>
    <?php endforeach; ?>
    <?php if ($dups): ?>
      <details class="card seo-issue"><summary><b>عناوين مكررة</b> <span class="st st-pend"><?= count($dups) ?></span></summary>
        <p class="muted sm">العناوين المتطابقة تتنافس مع بعضها في جوجل؛ غيّر أحدها.</p>
        <ul class="links"><?php foreach ($dups as $d): ?><li><a href="<?= e(admin_url('articles', ['q' => $d['title']])) ?>"><?= e($d['title']) ?></a> × <?= (int)$d['n'] ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
    <?php admin_render('فحص السيو', ob_get_clean());
}
