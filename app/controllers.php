<?php
declare(strict_types=1);

/* ============ أدوات العرض ============ */

function request_path(): string
{
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . ltrim(rawurldecode($uri), '/');
    return $uri === '/index.php' ? '/' : $uri;
}

function render(string $view, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require ROOT . '/views/layout.php';
}

function not_found(): never
{
    Seo::$title = 'الصفحة غير موجودة';
    Seo::noindex();
    render('404', ['latest' => articles(['limit' => 8])], 404);
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit;
}

function current_page(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}

/** يجهز بيانات الترقيم ويضبط الرابط القانوني و prev/next */
function paginate(int $total, string $baseUrl): array
{
    $per   = (int)cfg('per_page', 20);
    $page  = current_page();
    $pages = max(1, (int)ceil($total / $per));
    if ($page > $pages) {
        not_found();
    }
    $link = static fn(int $p) => $p <= 1 ? $baseUrl : $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $p;
    Seo::$canonical = $link($page);
    Seo::$prev = $page > 1 ? $link($page - 1) : null;
    Seo::$next = $page < $pages ? $link($page + 1) : null;
    return ['page' => $page, 'pages' => $pages, 'per' => $per, 'offset' => ($page - 1) * $per, 'link' => $link, 'total' => $total];
}

function front_admin_id(): ?int
{
    if (!is_admin_session()) {
        return null;
    }
    session_name('j24_admin');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['read_and_close' => true]);
    }
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function preload_hero(?array $a): void
{
    if ($a && $a['image']) {
        $avif = avif_srcset($a['image']);
        Seo::$preloadImage = $avif ? preg_replace('~\.webp$~', '.avif', img_url($a['image'], 'md')) : img_url($a['image'], 'md');
        Seo::$preloadSrcset = $avif ?: img_url($a['image'], 'sm') . ' 400w, ' . img_url($a['image'], 'md') . ' 720w, ' . img_url($a['image'], 'lg') . ' 1200w';
        Seo::$preloadType = $avif ? 'image/avif' : null;
    }
}

/** إدراج "اقرأ أيضاً" بعد الفقرة الثانية — ربط داخلي يعزز السيو ومدة البقاء */
function inject_read_also(string $body, ?array $rel): string
{
    if (!$rel || substr_count($body, '</p>') < 3) {
        return $body;
    }
    $box = '<aside class="read-also"><span>اقرأ أيضاً</span><a href="' . e(article_url($rel)) . '">' . e($rel['title']) . '</a></aside>';
    $pos = 0;
    for ($i = 0; $i < 2; $i++) {
        $pos = strpos($body, '</p>', $pos) + 4;
    }
    return substr($body, 0, $pos) . $box . substr($body, $pos);
}

function item_list_schema(array $list): array
{
    $items = [];
    foreach (array_values($list) as $i => $a) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => article_url($a)];
    }
    return ['@type' => 'ItemList', 'itemListElement' => $items];
}

/* ============ الصفحات ============ */

function c_home(): void
{
    // 5 أخبار للسلايدر + 4 للبطاقات الجانبية: المميزة أولاً ثم الأحدث
    $featured = articles(['featured' => 1, 'limit' => 9]);
    if (count($featured) < 9) {
        $featured = array_merge($featured, articles(['exclude' => array_column($featured, 'id'), 'not_type' => 'opinion', 'limit' => 9 - count($featured)]));
    }
    $used = array_column($featured, 'id');

    $latest = articles(['limit' => 14, 'not_type' => 'opinion']);
    $mostRead = most_read(6);
    $videos = articles(['type' => 'video', 'limit' => 5]);
    $opinion = articles(['type' => 'opinion', 'limit' => 4]);

    $blocks = [];
    foreach (categories() as $c) {
        if (!(int)$c['show_on_home'] || in_array($c['slug'], ['opinion', 'articles'], true)) {
            continue;
        }
        $n = ['lead' => 5, 'grid' => 4, 'list' => 6][$c['home_layout']] ?? 5;
        $items = articles(['category' => $c['id'], 'exclude' => $used, 'not_type' => 'opinion', 'limit' => $n]);
        if (count($items) < min(3, $n)) {
            // قسم قليل الأخبار: نسمح بتكرار ما ظهر في الأعلى بدل ترك فراغ
            $items = articles(['category' => $c['id'], 'not_type' => 'opinion', 'limit' => $n]);
        }
        if ($items) {
            $blocks[] = ['cat' => $c, 'items' => $items];
            $used = array_merge($used, array_column($items, 'id'));
        }
    }

    Seo::$canonical = url();
    Seo::$schemas[] = Seo::website();
    Seo::$schemas[] = ['@type' => 'WebPage', '@id' => url() . '#webpage', 'url' => url(), 'name' => Seo::fullTitle(), 'isPartOf' => ['@id' => url() . '#website'], 'inLanguage' => 'ar'];
    preload_hero($featured[0] ?? null);

    $south = [];
    foreach (regions() as $slug => $r) {
        $items = articles(['region' => $slug, 'limit' => 4]);
        if ($items) {
            $south[$slug] = $items;
        }
    }
    $southAll = articles(['has_region' => 1, 'limit' => 4]);

    render('home', compact('featured', 'latest', 'mostRead', 'videos', 'opinion', 'blocks', 'south', 'southAll'));
}

function c_article(string $id, ?string $slug = null): void
{
    $a = article_full((int)$id);
    // المعاينة: المدير والمحرر لكل الأخبار، والكاتب لأخباره فقط
    $viewer = isset($_GET['preview']) ? front_admin_id() : null;
    $vu = $viewer ? row('SELECT id, role FROM users WHERE id = ? AND is_active = 1', [$viewer]) : null;
    $preview = $vu && $a && (in_array($vu['role'], ['admin', 'editor'], true) || (int)$a['author_id'] === (int)$vu['id']);
    if (!$a || (!is_live($a) && !$preview)) {
        not_found();
    }
    if (!$preview && ($slug ?? '') !== $a['slug']) {
        redirect(article_url($a), 301);
    }

    $tags = article_tags((int)$a['id']);
    $related = related_articles($a, 7);
    $readAlso = array_shift($related);
    $a['body'] = body_render($a['body']);
    $body = inject_read_also($a['body'], $readAlso);
    if ($readAlso && $body === $a['body']) {
        array_unshift($related, $readAlso);
    }
    $related = array_slice($related, 0, 6);
    $mostRead = most_read(5);
    $latest = articles(['limit' => 6, 'exclude' => [$a['id']]]);

    Seo::$title = $a['seo_title'] ?: $a['title'];
    Seo::$description = $a['seo_description'] ?: ($a['excerpt'] ?: excerpt($a['body']));
    Seo::$canonical = article_url($a);
    Seo::$type = 'article';
    if ($a['image']) {
        Seo::$image = img_url($a['image'], 'og');
    }
    Seo::$meta[] = ['article:published_time', iso($a['published_at'])];
    Seo::$meta[] = ['article:modified_time', iso($a['updated_at'])];
    Seo::$meta[] = ['article:section', $a['cat_name']];
    foreach ($tags as $t) {
        Seo::$meta[] = ['article:tag', $t['name']];
    }
    if ($a['author_name']) {
        Seo::$meta[] = ['author', $a['author_name']];
    }
    if ($a['focus_keyword'] || $tags) {
        Seo::$meta[] = ['news_keywords', implode(', ', array_filter(array_merge([$a['focus_keyword']], array_column($tags, 'name'))))];
    }
    $updates = $a['type'] === 'live' ? live_updates((int)$a['id']) : [];
    $schema = Seo::newsArticle($a, $tags);
    if ($a['type'] === 'live') {
        $schema['@type'] = 'LiveBlogPosting';
        $schema['coverageStartTime'] = iso($a['published_at']);
        if (!$a['live_open']) {
            $schema['coverageEndTime'] = iso($updates[0]['created_at'] ?? $a['updated_at']);
        }
        if ($updates) {
            $schema['dateModified'] = iso($updates[0]['created_at']);
        }
        $schema['liveBlogUpdate'] = array_map(static fn($u) => [
            '@type' => 'BlogPosting',
            'headline' => mb_substr(plain_text(live_html($u['body'])), 0, 110),
            'datePublished' => iso($u['created_at']),
            'articleBody' => plain_text(live_html($u['body'])),
        ], array_slice($updates, 0, 50));
    }
    Seo::$schemas[] = $schema;
    Seo::$breadcrumbs = [[$a['cat_name'], category_url($a['cat_slug'])], [$a['title'], null]];
    if ($preview) {
        Seo::noindex();
    }
    if (!youtube_id($a['video_url'])) {
        preload_hero($a);
    }

    render('article', compact('a', 'body', 'tags', 'related', 'mostRead', 'latest', 'preview', 'updates'));
}

function c_category(string $slug): void
{
    $cat = category_by_slug($slug);
    if (!$cat) {
        not_found();
    }
    $total = count_articles(['category' => $cat['id']]);
    $pg = paginate($total, category_url($cat));
    $items = articles(['category' => $cat['id'], 'limit' => $pg['per'], 'offset' => $pg['offset']]);

    Seo::$title = ($cat['seo_title'] ?: 'أخبار ' . $cat['name']) . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = $cat['description'] ?: 'تابع آخر أخبار ' . $cat['name'] . ' لحظة بلحظة على موقع ' . site_name() . '.';
    Seo::$breadcrumbs = [[$cat['name'], null]];
    Seo::$schemas[] = ['@type' => 'CollectionPage', 'name' => $cat['name'], 'url' => Seo::$canonical, 'inLanguage' => 'ar', 'isPartOf' => ['@id' => url() . '#website'], 'mainEntity' => item_list_schema($items)];
    if ($pg['page'] === 1) {
        preload_hero($items[0] ?? null);
    }
    render('listing', [
        'heading' => $cat['name'], 'intro' => $cat['description'], 'color' => $cat['color'], 'items' => $items, 'pg' => $pg,
        'lead' => $pg['page'] === 1, 'feed' => url('category/' . $cat['slug'] . '/feed'), 'mostRead' => most_read(6),
    ]);
}

function c_tag(string $slug): void
{
    $tag = row('SELECT * FROM tags WHERE slug = ?', [$slug]);
    if (!$tag) {
        not_found();
    }
    $total = count_articles(['tag' => $tag['id']]);
    if ($total === 0) {
        not_found();
    }
    $pg = paginate($total, tag_url($tag));
    $items = articles(['tag' => $tag['id'], 'limit' => $pg['per'], 'offset' => $pg['offset']]);
    Seo::$title = $tag['name'] . ' - آخر الأخبار' . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = 'كل الأخبار والتقارير حول ' . $tag['name'] . ' على موقع ' . site_name() . '.';
    if ($total < 2) {
        Seo::noindex(); // صفحات الوسوم الضعيفة لا تُفهرس
    }
    Seo::$breadcrumbs = [['الوسوم', null], [$tag['name'], null]];
    Seo::$schemas[] = ['@type' => 'CollectionPage', 'name' => $tag['name'], 'url' => Seo::$canonical, 'mainEntity' => item_list_schema($items)];
    render('listing', ['heading' => '#' . $tag['name'], 'intro' => null, 'items' => $items, 'pg' => $pg, 'lead' => false, 'mostRead' => most_read(6)]);
}

function c_author(string $slug): void
{
    $u = row('SELECT * FROM users WHERE slug = ? AND is_active = 1', [$slug]);
    if (!$u) {
        not_found();
    }
    $total = count_articles(['author' => $u['id']]);
    $pg = paginate($total, author_url($u));
    $items = articles(['author' => $u['id'], 'limit' => $pg['per'], 'offset' => $pg['offset']]);
    Seo::$title = $u['name'] . ($u['job_title'] ? ' - ' . $u['job_title'] : '') . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = $u['bio'] ? excerpt($u['bio'], 160) : 'مقالات وأخبار ' . $u['name'] . ' على موقع ' . site_name() . '.';
    Seo::$type = 'profile';
    if ($u['avatar']) {
        Seo::$image = img_url($u['avatar']);
    }
    $person = ['@type' => 'Person', '@id' => author_url($u) . '#person', 'name' => $u['name'], 'url' => author_url($u), 'worksFor' => ['@id' => url() . '#organization']];
    if ($u['job_title']) {
        $person['jobTitle'] = $u['job_title'];
    }
    if ($u['bio']) {
        $person['description'] = $u['bio'];
    }
    if ($u['avatar']) {
        $person['image'] = img_url($u['avatar']);
    }
    $same = array_values(array_filter([$u['twitter'], $u['facebook']]));
    if ($same) {
        $person['sameAs'] = $same;
    }
    Seo::$schemas[] = ['@type' => 'ProfilePage', 'url' => Seo::$canonical, 'mainEntity' => $person];
    Seo::$breadcrumbs = [['الكتّاب', null], [$u['name'], null]];
    render('listing', ['heading' => $u['name'], 'author' => $u, 'intro' => null, 'items' => $items, 'pg' => $pg, 'lead' => false, 'mostRead' => most_read(6)]);
}

function c_search(): void
{
    $qStr = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
    Seo::noindex();
    Seo::$title = $qStr !== '' ? 'نتائج البحث عن: ' . $qStr : 'البحث';
    $items = [];
    $pg = null;
    if ($qStr !== '') {
        $total = count_articles(['search' => $qStr]);
        $pg = $total ? paginate($total, url('search') . '?q=' . rawurlencode($qStr)) : null;
        $items = $pg ? articles(['search' => $qStr, 'limit' => $pg['per'], 'offset' => $pg['offset']]) : [];
    }
    Seo::$canonical = url('search');
    render('search', ['q' => $qStr, 'items' => $items, 'pg' => $pg, 'mostRead' => most_read(6)]);
}

function c_latest(): void
{
    $total = count_articles();
    $pg = paginate($total, url('latest'));
    $items = articles(['limit' => $pg['per'], 'offset' => $pg['offset']]);
    Seo::$title = 'آخر الأخبار' . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = 'آخر أخبار لبنان والجنوب والمنطقة لحظة بلحظة على ' . site_name() . '.';
    Seo::$breadcrumbs = [['آخر الأخبار', null]];
    render('listing', ['heading' => 'آخر الأخبار', 'intro' => 'تغطية متواصلة على مدار الساعة', 'items' => $items, 'pg' => $pg, 'lead' => false, 'timeline' => true, 'mostRead' => most_read(6)]);
}

function c_videos(): void
{
    $total = count_articles(['type' => 'video']);
    $pg = paginate($total, url('videos'));
    $items = articles(['type' => 'video', 'limit' => $pg['per'], 'offset' => $pg['offset']]);
    Seo::$title = 'فيديو' . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = 'أحدث الفيديوهات والتقارير المصورة من لبنان والجنوب على ' . site_name() . '.';
    Seo::$breadcrumbs = [['فيديو', null]];
    render('listing', ['heading' => 'فيديو', 'intro' => 'تقارير مصوّرة ومشاهد من الميدان', 'items' => $items, 'pg' => $pg, 'lead' => $pg['page'] === 1, 'mostRead' => most_read(6)]);
}

function c_breaking(): void
{
    $items = rows("SELECT b.*, a.slug AS article_slug FROM breaking_news b LEFT JOIN articles a ON a.id = b.article_id
        ORDER BY b.created_at DESC LIMIT 60");
    Seo::$title = 'عاجل - الأخبار العاجلة';
    Seo::$description = 'الأخبار العاجلة من لبنان والجنوب والشرق الأوسط لحظة بلحظة على ' . site_name() . '.';
    Seo::$canonical = url('breaking');
    Seo::$breadcrumbs = [['عاجل', null]];
    render('breaking', ['items' => $items, 'mostRead' => most_read(6)]);
}

function c_page(string $slug): void
{
    $p = row('SELECT * FROM pages WHERE slug = ?', [$slug]);
    if (!$p) {
        not_found();
    }
    Seo::$title = $p['title'];
    Seo::$description = $p['seo_description'] ?: excerpt($p['body']);
    Seo::$canonical = page_url($p['slug']);
    Seo::$breadcrumbs = [[$p['title'], null]];
    $type = match ($slug) { 'about' => 'AboutPage', 'contact' => 'ContactPage', default => 'WebPage' };
    Seo::$schemas[] = ['@type' => $type, 'name' => $p['title'], 'url' => Seo::$canonical, 'dateModified' => iso($p['updated_at'])];
    render('page', ['p' => $p]);
}

/* ============ الجنوب: الصفحة الجامعة وصفحات الأقضية ============ */

function place_schema(array $r): array
{
    return [
        '@type' => 'AdministrativeArea',
        'name' => $r['full'],
        'alternateName' => $r['name'],
        'containedInPlace' => ['@type' => 'AdministrativeArea', 'name' => $r['gov'] . '، لبنان'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $r['lat'], 'longitude' => $r['lng']],
    ];
}

function c_south(): void
{
    $per = [];
    foreach (regions() as $slug => $r) {
        $per[$slug] = articles(['region' => $slug, 'limit' => 4]);
    }
    $latest = articles(['has_region' => 1, 'limit' => 9]);
    Seo::$title = 'أخبار الجنوب اللبناني اليوم - صور، صيدا، النبطية، بنت جبيل ومرجعيون';
    Seo::$description = 'آخر أخبار جنوب لبنان لحظة بلحظة حسب القضاء: صور، صيدا، النبطية، بنت جبيل، مرجعيون، حاصبيا وجزين. تغطية ميدانية على مدار الساعة من ' . site_name() . '.';
    Seo::$canonical = url('south');
    Seo::$breadcrumbs = [['أخبار الجنوب', null]];
    Seo::$schemas[] = ['@type' => 'CollectionPage', 'name' => 'أخبار الجنوب اللبناني', 'url' => Seo::$canonical, 'inLanguage' => 'ar',
        'about' => array_values(array_map('place_schema', regions())), 'mainEntity' => item_list_schema($latest)];
    preload_hero($latest[0] ?? null);
    render('south', ['per' => $per, 'latest' => $latest, 'mostRead' => most_read(6)]);
}

function c_region(string $slug): void
{
    $r = region($slug);
    if (!$r) {
        not_found();
    }
    $total = count_articles(['region' => $slug]);
    $pg = paginate($total, region_url($slug));
    $items = articles(['region' => $slug, 'limit' => $pg['per'], 'offset' => $pg['offset']]);
    Seo::$title = 'أخبار ' . $r['name'] . ' اليوم - آخر أخبار ' . $r['full'] . ($pg['page'] > 1 ? ' - الصفحة ' . $pg['page'] : '');
    Seo::$description = 'تابع آخر أخبار ' . $r['full'] . ' في جنوب لبنان لحظة بلحظة: ' . implode('، ', array_slice($r['towns'], 0, 6)) . ' وغيرها، على موقع ' . site_name() . '.';
    if ($total === 0) {
        Seo::noindex();
    }
    Seo::$breadcrumbs = [['أخبار الجنوب', url('south')], [$r['name'], null]];
    Seo::$schemas[] = ['@type' => 'CollectionPage', 'name' => 'أخبار ' . $r['full'], 'url' => Seo::$canonical, 'inLanguage' => 'ar',
        'about' => place_schema($r), 'mainEntity' => item_list_schema($items)];
    if ($pg['page'] === 1) {
        preload_hero($items[0] ?? null);
    }
    render('listing', [
        'heading' => 'أخبار ' . $r['name'], 'intro' => $r['full'] . ' — ' . $r['gov'], 'color' => '#0b6fae', 'items' => $items, 'pg' => $pg,
        'lead' => $pg['page'] === 1, 'region' => $r, 'mostRead' => most_read(6),
    ]);
}

/* ============ تحويل روابط الموقع القديم (ووردبريس) ============ */

/**
 * يحافظ على ترتيب جوجل بعد الانتقال: كل رابط قديم يُحوَّل 301 إلى رابطه الجديد.
 *   /?p=123            → الخبر المستورد بالمعرف القديم
 *   /{slug-عربي}/      → الخبر المستورد بنفس الرابط القديم
 *   /category/{عربي}/  → القسم المقابل (مع /page/N/)
 *   /page/N/           → /latest?page=N
 */
function legacy_redirect(string $path): void
{
    if ($path === '/' && isset($_GET['p']) && ctype_digit((string)$_GET['p'])) {
        $a = row('SELECT id, slug FROM articles WHERE legacy_id = ?', [(int)$_GET['p']]);
        if ($a) {
            redirect(article_url($a), 301);
        }
        return;
    }
    if (preg_match('#^/page/(\d+)/?$#', $path, $m)) {
        redirect(url('latest') . ((int)$m[1] > 1 ? '?page=' . (int)$m[1] : ''), 301);
    }
    if (preg_match('#^/category/([^/]+)(?:/page/(\d+))?/?$#u', $path, $m) && !category_by_slug($m[1])) {
        $c = row('SELECT slug FROM categories WHERE legacy_slug = ?', [$m[1]]);
        if ($c) {
            redirect(category_url($c['slug']) . (!empty($m[2]) && (int)$m[2] > 1 ? '?page=' . (int)$m[2] : ''), 301);
        }
        return;
    }
    if (preg_match('#^/([^/.]+)/?$#u', $path, $m) && preg_match('/[^\x00-\x7F]/', $m[1])) {
        $a = row('SELECT id, slug FROM articles WHERE legacy_slug = ? LIMIT 1', [$m[1]]);
        if ($a) {
            redirect(article_url($a), 301);
        }
    }
}

/* ============ API ============ */

function c_api_view(): void
{
    $id = (int)($_POST['id'] ?? 0);
    // لا نقبل العدّ إلا من صفحات الموقع نفسه (يمنع التلاعب بـ«الأكثر قراءة» من مواقع أخرى)
    $from = parse_url((string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
    if ($from !== parse_url(base_url(), PHP_URL_HOST)) {
        http_response_code(204);
        return;
    }
    if ($id > 0) {
        q("UPDATE articles SET views = views + 1 WHERE id = ? AND status = 'published'", [$id]);
    }
    http_response_code(204);
}

function c_api_breaking(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=30');
    $out = [];
    foreach (breaking_items(10) as $b) {
        $out[] = ['id' => (int)$b['id'], 'text' => $b['text'], 'url' => breaking_link($b), 'time' => date('H:i', ts($b['created_at']))];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** اقتراحات البحث الفوري (قائمة منسدلة أثناء الكتابة) */
function c_api_search(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    $qStr = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 60));
    if (mb_strlen($qStr) < 2) {
        echo '{"items":[],"sections":[],"total":0}';
        return;
    }
    $n = ar_normalize($qStr);
    $sections = [];
    foreach (regions() as $slug => $r) {
        $hit = str_contains(ar_normalize($r['name']), $n);
        if (!$hit) {
            foreach ($r['towns'] as $t) {
                if (str_contains(ar_normalize($t), $n)) {
                    $hit = true;
                    break;
                }
            }
        }
        if ($hit) {
            $sections[] = ['name' => 'أخبار ' . $r['name'], 'url' => region_url($slug), 'type' => 'region'];
        }
    }
    foreach (categories() as $c) {
        if (str_contains(ar_normalize($c['name']), $n)) {
            $sections[] = ['name' => $c['name'], 'url' => category_url($c), 'type' => 'category'];
        }
    }
    foreach (rows('SELECT name, slug FROM tags WHERE name LIKE ? LIMIT 3', ['%' . addcslashes($qStr, '%_\\') . '%']) as $t) {
        $sections[] = ['name' => '#' . $t['name'], 'url' => tag_url($t), 'type' => 'tag'];
    }
    $items = [];
    foreach (articles(['search' => $qStr, 'limit' => 6]) as $a) {
        $items[] = [
            'title' => $a['title'], 'url' => article_url($a), 'img' => img_url($a['image'], 'sm'),
            'cat' => $a['cat_name'], 'color' => $a['cat_color'], 'time' => time_ago($a['published_at']),
            'live' => $a['type'] === 'live' && (int)$a['live_open'] === 1, 'video' => $a['type'] === 'video',
        ];
    }
    echo json_encode([
        'items' => $items,
        'sections' => array_slice($sections, 0, 5),
        'total' => $items ? count_articles(['search' => $qStr]) : 0,
        'all' => url('search') . '?q=' . rawurlencode($qStr),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** تحديثات التغطية المباشرة (يستدعيها المتصفح كل 30 ثانية) */
function c_api_live(string $id): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');
    $a = row("SELECT a.id, a.live_open FROM articles a WHERE a.id = ? AND a.type = 'live' AND " . ART_LIVE, [(int)$id]);
    if (!$a) {
        http_response_code(404);
        echo '{}';
        return;
    }
    $out = [];
    foreach (array_reverse(live_updates((int)$a['id'], (int)($_GET['after'] ?? 0), 30)) as $u) {
        $out[] = ['id' => (int)$u['id'], 'time' => date('H:i', ts($u['created_at'])), 'iso' => iso($u['created_at']), 'key' => (bool)$u['is_key'], 'html' => live_html($u['body'])];
    }
    echo json_encode(['open' => (bool)$a['live_open'], 'updates' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function c_indexnow_key(string $key): void
{
    if ($key === '' || $key !== setting('indexnow_key')) {
        not_found();
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $key;
}
