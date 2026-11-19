<?php
declare(strict_types=1);

const ART_COLS = "a.id, a.title, a.subtitle, a.slug, a.excerpt, a.image, a.type, a.video_url, a.published_at, a.updated_at,
    a.views, a.is_breaking, a.is_featured, a.category_id, a.region, a.live_open, c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color,
    u.name AS author_name, u.slug AS author_slug, u.avatar AS author_avatar, u.job_title AS author_job";
const ART_FROM = "FROM articles a JOIN categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.author_id";
const ART_LIVE = "a.status = 'published' AND a.published_at <= NOW()";

/** يبني شروط WHERE للاستعلام عن الأخبار */
function article_where(array $o): array
{
    $w = [ART_LIVE];
    $p = [];
    if (!empty($o['category'])) {
        $w[] = 'a.category_id = ?';
        $p[] = (int)$o['category'];
    }
    if (!empty($o['categories'])) {
        $w[] = 'a.category_id IN (' . ints($o['categories']) . ')';
    }
    if (!empty($o['featured'])) {
        $w[] = 'a.is_featured = 1';
    }
    if (!empty($o['type'])) {
        $w[] = 'a.type = ?';
        $p[] = $o['type'];
    }
    if (!empty($o['not_type'])) {
        $w[] = 'a.type <> ?';
        $p[] = $o['not_type'];
    }
    if (!empty($o['region'])) {
        $w[] = 'a.region = ?';
        $p[] = $o['region'];
    }
    if (!empty($o['has_region'])) {
        $w[] = 'a.region IS NOT NULL';
    }
    if (!empty($o['author'])) {
        $w[] = 'a.author_id = ?';
        $p[] = (int)$o['author'];
    }
    if (!empty($o['tag'])) {
        $w[] = 'a.id IN (SELECT article_id FROM article_tags WHERE tag_id = ?)';
        $p[] = (int)$o['tag'];
    }
    if (!empty($o['exclude'])) {
        $w[] = 'a.id NOT IN (' . ints($o['exclude']) . ')';
    }
    if (!empty($o['since_hours'])) {
        $w[] = 'a.published_at >= NOW() - INTERVAL ' . (int)$o['since_hours'] . ' HOUR';
    }
    if (!empty($o['search'])) {
        $terms = array_slice(array_filter(preg_split('/\s+/u', ar_normalize(trim($o['search']))) ?: [], static fn($t) => mb_strlen($t) >= 2), 0, 6);
        foreach ($terms as $t) {
            $w[] = 'a.search_text LIKE ?';
            $p[] = '%' . addcslashes($t, '%_\\') . '%';
        }
        if (!$terms) {
            $w[] = '0';
        }
    }
    return [implode(' AND ', $w), $p];
}

function articles(array $o = []): array
{
    [$where, $p] = article_where($o);
    $order  = $o['order'] ?? 'a.published_at DESC';
    $limit  = (int)($o['limit'] ?? 10);
    $offset = (int)($o['offset'] ?? 0);
    return rows('SELECT ' . ART_COLS . ' ' . ART_FROM . " WHERE $where ORDER BY $order LIMIT $offset, $limit", $p);
}

function count_articles(array $o = []): int
{
    [$where, $p] = article_where($o);
    return (int)val('SELECT COUNT(*) ' . ART_FROM . " WHERE $where", $p);
}

function article_full(int $id): ?array
{
    return row('SELECT a.*, c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color,
        u.name AS author_name, u.slug AS author_slug, u.avatar AS author_avatar, u.job_title AS author_job, u.bio AS author_bio,
        u.twitter AS author_twitter, u.facebook AS author_facebook ' . ART_FROM . ' WHERE a.id = ?', [$id]);
}

function is_live(array $a): bool
{
    return $a['status'] === 'published' && ts($a['published_at']) <= time();
}

function article_tags(int $id): array
{
    return rows('SELECT t.* FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name', [$id]);
}

function related_articles(array $a, int $limit = 6): array
{
    $tagIds = array_column(article_tags((int)$a['id']), 'id');
    $out = [];
    if ($tagIds) {
        $out = rows('SELECT ' . ART_COLS . ', COUNT(at.tag_id) AS score ' . ART_FROM . '
            JOIN article_tags at ON at.article_id = a.id AND at.tag_id IN (' . ints($tagIds) . ')
            WHERE ' . ART_LIVE . ' AND a.id <> ? GROUP BY a.id ORDER BY score DESC, a.published_at DESC LIMIT ' . $limit, [$a['id']]);
    }
    if (count($out) < $limit) {
        $more = articles(['category' => $a['category_id'], 'exclude' => array_merge([$a['id']], array_column($out, 'id')), 'limit' => $limit - count($out)]);
        $out = array_merge($out, $more);
    }
    return $out;
}

function most_read(int $limit = 6, int $hours = 72): array
{
    $list = articles(['since_hours' => $hours, 'order' => 'a.views DESC, a.published_at DESC', 'limit' => $limit]);
    if (count($list) < $limit) {
        $list = array_merge($list, articles(['exclude' => array_column($list, 'id'), 'order' => 'a.views DESC', 'limit' => $limit - count($list)]));
    }
    return $list;
}

function categories(bool $menuOnly = false): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = rows('SELECT * FROM categories ORDER BY sort_order, id');
    }
    return $menuOnly ? array_values(array_filter($cache, static fn($c) => (int)$c['show_in_menu'] === 1)) : $cache;
}

function category_by_slug(string $slug): ?array
{
    foreach (categories() as $c) {
        if ($c['slug'] === $slug) {
            return $c;
        }
    }
    return null;
}

function breaking_items(int $limit = 10): array
{
    return rows("SELECT b.*, a.slug AS article_slug FROM breaking_news b LEFT JOIN articles a ON a.id = b.article_id
        WHERE b.expires_at IS NULL OR b.expires_at > NOW() ORDER BY b.created_at DESC LIMIT " . $limit);
}

function breaking_link(array $b): ?string
{
    if ($b['article_id'] && $b['article_slug']) {
        return article_url(['id' => $b['article_id'], 'slug' => $b['article_slug']]);
    }
    return $b['url'] ?: null;
}

function popular_tags(int $limit = 20): array
{
    return rows('SELECT t.*, COUNT(*) AS n FROM tags t JOIN article_tags at ON at.tag_id = t.id
        JOIN articles a ON a.id = at.article_id WHERE ' . ART_LIVE . ' AND a.published_at >= NOW() - INTERVAL 30 DAY
        GROUP BY t.id ORDER BY n DESC LIMIT ' . $limit);
}

/** التغطية المباشرة المفتوحة حالياً (الأحدث) */
function open_live(): ?array
{
    static $l = false;
    if ($l === false) {
        $l = row('SELECT ' . ART_COLS . ' ' . ART_FROM . " WHERE " . ART_LIVE . " AND a.type = 'live' AND a.live_open = 1 ORDER BY a.published_at DESC LIMIT 1");
        if ($l) {
            $l['last'] = row('SELECT body, created_at FROM live_updates WHERE article_id = ? ORDER BY id DESC LIMIT 1', [$l['id']]);
        }
    }
    return $l;
}

function live_updates(int $articleId, int $afterId = 0, int $limit = 200): array
{
    return rows('SELECT id, body, is_key, created_at FROM live_updates WHERE article_id = ? AND id > ? ORDER BY id DESC LIMIT ' . $limit, [$articleId, $afterId]);
}

/** نص عادي ← فقرات HTML آمنة (يُهرَّب كل شيء) مع تحويل الروابط */
function live_text_html(string $text): string
{
    $out = '';
    foreach (preg_split('/
{2,}/u', trim($text)) ?: [] as $para) {
        $t = nl2br(e(trim($para)));
        $t = preg_replace('~(https?://[^\s<&]+)~u', '<a href="$1" target="_blank" rel="noopener nofollow">$1</a>', $t) ?? $t;
        $out .= '<p>' . $t . '</p>';
    }
    return $out;
}

/** عرض تحديث مباشر محفوظ */
function live_html(string $body): string
{
    if (preg_match('~^\s*<(p|figure)\b~', $body)) {
        return body_render($body); // محفوظ كـ HTML آمن من لوحة التحكم
    }
    $out = '';
    foreach (preg_split('/\n{2,}/u', trim($body)) ?: [] as $para) {
        $t = nl2br(e(trim($para)));
        $t = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" target="_blank" rel="noopener">$1</a>', $t) ?? $t;
        $out .= '<p>' . $t . '</p>';
    }
    return $out;
}

/** أسعار اليوم من الإعدادات */
function daily_prices(): array
{
    $items = [
        'price_usd'    => ['الدولار (السوق)', 'ل.ل', 'usd'],
        'price_95'     => ['بنزين 95 أوكتان', 'ل.ل', 'fuel'],
        'price_98'     => ['بنزين 98 أوكتان', 'ل.ل', 'fuel'],
        'price_diesel' => ['مازوت', 'ل.ل', 'fuel'],
        'price_gas'    => ['قارورة الغاز', 'ل.ل', 'gas'],
    ];
    $out = [];
    foreach ($items as $k => [$label, $unit, $ico]) {
        $v = trim(setting($k));
        if ($v !== '') {
            $num = preg_replace('/[^\d.]/', '', $v);
            $out[] = ['label' => $label, 'value' => $num !== '' && is_numeric($num) ? number_format((float)$num) : $v, 'unit' => $unit, 'ico' => $ico, 'key' => $k];
        }
    }
    return $out;
}

function footer_pages(): array
{
    return rows('SELECT title, slug FROM pages WHERE show_in_footer = 1 ORDER BY id');
}

/** حفظ الوسوم لخبر من نص مفصول بفواصل */
function sync_tags(int $articleId, string $csv): array
{
    q('DELETE FROM article_tags WHERE article_id = ?', [$articleId]);
    $names = array_unique(array_filter(array_map('trim', preg_split('/[,،]+/u', $csv) ?: [])));
    $saved = [];
    foreach (array_slice($names, 0, 15) as $name) {
        $slug = slugify($name, 60);
        $id = val('SELECT id FROM tags WHERE slug = ?', [$slug]);
        if (!$id) {
            q('INSERT INTO tags (name, slug) VALUES (?, ?)', [mb_substr($name, 0, 120), $slug]);
            $id = db()->lastInsertId();
        }
        q('INSERT IGNORE INTO article_tags (article_id, tag_id) VALUES (?, ?)', [$articleId, $id]);
        $saved[] = $name;
    }
    return $saved;
}

function refresh_search_text(int $id): void
{
    $a = row('SELECT title, subtitle, excerpt, body FROM articles WHERE id = ?', [$id]);
    if (!$a) {
        return;
    }
    $tags = implode(' ', array_column(article_tags($id), 'name'));
    $txt = ar_normalize($a['title'] . ' ' . $a['subtitle'] . ' ' . $a['excerpt'] . ' ' . $tags . ' ' . plain_text($a['body']));
    q('UPDATE articles SET search_text = ? WHERE id = ?', [preg_replace('/\s+/u', ' ', $txt), $id]);
}

function unique_article_slug(string $slug): string
{
    // الرابط يحتوي على رقم الخبر لذا التكرار مسموح — نكتفي بالتنظيف
    return slugify($slug);
}

/** إبلاغ محركات البحث فوراً عبر IndexNow (Bing و Yandex وغيرها) */
function indexnow_ping(array $urls): void
{
    $key = setting('indexnow_key');
    $host = parse_url(base_url(), PHP_URL_HOST);
    if ($key === '' || !$urls || cfg('staging') || in_array($host, ['localhost', '127.0.0.1'], true)) {
        return;
    }
    $payload = json_encode(['host' => $host, 'key' => $key, 'keyLocation' => url($key . '.txt'), 'urlList' => array_values($urls)]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json; charset=utf-8\r\n",
        'content' => $payload,
        'timeout' => 4,
        'ignore_errors' => true,
    ]]);
    @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
}
