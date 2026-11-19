<?php
/**
 * استيراد المحتوى من موقع ووردبريس القديم عبر REST API
 *
 *   php database/import_wordpress.php                       (كل الأخبار من janoub24.com)
 *   php database/import_wordpress.php --limit=10            (تجربة على 10 أخبار)
 *   php database/import_wordpress.php --source=https://example.com
 *
 * - يحفظ الرابط القديم (legacy_slug) والمعرف القديم (legacy_id) لتحويل 301 تلقائي.
 * - يطابق الأقسام بالاسم، وينشئ الكتّاب، وينزّل الصورة البارزة ويعالجها (WebP).
 * - آمن للتكرار: الأخبار المستوردة مسبقاً تُتخطّى.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require dirname(__DIR__) . '/app/bootstrap.php';

$opts = getopt('', ['source::', 'limit::', 'per-page::']);
$source = rtrim($opts['source'] ?? 'https://janoub24.com', '/');
$limit = (int)($opts['limit'] ?? 0);
$perPage = min(100, max(1, (int)($opts['per-page'] ?? 20)));

function http_get(string $url): ?string
{
    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header'  => "User-Agent: Janoub24-Migrator/1.0\r\nAccept: application/json\r\n",
        'ignore_errors' => true,
    ]]);
    for ($try = 1; $try <= 3; $try++) {
        $body = @file_get_contents($url, false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m) ? (int)$m[1] : 0;
        if ($body !== false && $code === 200) {
            return $body;
        }
        sleep($try * 2);
    }
    return null;
}

function api(string $source, string $path): ?array
{
    $raw = http_get($source . '/wp-json/wp/v2/' . $path);
    return $raw ? json_decode($raw, true) : null;
}

/** تنزيل صورة (يفضّل النسخة الأصلية بدل المصغّرة -300x200) */
function fetch_image(string $url)
{
    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    $candidates = array_unique([preg_replace('~-\d{2,4}x\d{2,4}(\.\w{3,4})(\?.*)?$~', '$1', $url), $url]);
    foreach ($candidates as $u) {
        $ctx = stream_context_create(['http' => ['timeout' => 40, 'ignore_errors' => true,
            'header' => "User-Agent: Mozilla/5.0 (Janoub24-Migrator)\r\nAccept: image/*\r\n"]]);
        $bin = @file_get_contents(str_replace(' ', '%20', $u), false, $ctx);
        $ok = isset($http_response_header[0]) && str_contains($http_response_header[0], ' 200');
        if ($ok && $bin && ($im = @imagecreatefromstring($bin))) {
            return $im;
        }
    }
    return null;
}

/** يستبدل روابط الصور داخل النص بنسخ محلية، ويعيد أول رابط أصلي عبر $first */
function localize_images(string $html, int $wpId, ?string &$first): string
{
    $n = 0;
    return preg_replace_callback('~<img\b([^>]*?)\bsrc="([^"]+)"([^>]*)>~i', function ($m) use ($wpId, &$first, &$n) {
        $orig = $m[2];
        if (!preg_match('~^https?://~', $orig)) {
            return $m[0];
        }
        $first ??= $orig;
        $im = fetch_image($orig);
        if (!$im) {
            return $m[0]; // تبقى على الرابط القديم إن تعذّر التنزيل
        }
        $path = store_single_image($im, 'wp-' . $wpId . '-in' . (++$n) . '-' . bin2hex(random_bytes(3)));
        imagedestroy($im);
        $attrs = preg_replace('~\s(width|height)="[^"]*"~i', '', $m[1] . $m[3]);
        return '<img' . $attrs . ' src="{{base}}/' . $path . '">';
    }, $html) ?? $html;
}

function txt(string $html): string
{
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/* 1) الأقسام */
$catMap = [];
$wpCats = api($source, 'categories?per_page=100') ?? [];
$fallback = (int)(val("SELECT id FROM categories WHERE slug = 'local'") ?: val('SELECT id FROM categories ORDER BY sort_order LIMIT 1'));
foreach ($wpCats as $wc) {
    $name = txt($wc['name']);
    $legacy = rawurldecode($wc['slug']);
    $id = null;
    foreach (categories() as $c) {
        if (ar_normalize($c['name']) === ar_normalize($name)) {
            $id = (int)$c['id'];
        }
    }
    if (!$id && in_array($name, ['الأخبار العاجلة', 'عاجل'], true)) {
        $id = $fallback;
    }
    if (!$id) {
        q('INSERT INTO categories (name, slug, description, sort_order) VALUES (?, ?, ?, 99)', [$name, 'cat-' . (int)$wc['id'], txt($wc['description'] ?? '') ?: null]);
        $id = (int)db()->lastInsertId();
        echo "  + قسم جديد: $name\n";
    }
    if (!val('SELECT legacy_slug FROM categories WHERE id = ?', [$id])) {
        q('UPDATE categories SET legacy_slug = ? WHERE id = ?', [$legacy, $id]);
    }
    $catMap[(int)$wc['id']] = $id;
}
echo '✔ الأقسام: ' . count($catMap) . "\n";

/* 2) الكتّاب */
function author_id(array $wpAuthor): int
{
    $slug = slugify(rawurldecode($wpAuthor['slug'] ?? '') ?: ($wpAuthor['name'] ?? 'author'), 60);
    $id = val('SELECT id FROM users WHERE slug = ?', [$slug]);
    if ($id) {
        return (int)$id;
    }
    $email = preg_replace('/[^a-z0-9]/', '', strtolower($slug)) ?: 'author' . random_int(100, 999);
    q('INSERT INTO users (name, slug, email, password_hash, role, bio) VALUES (?, ?, ?, ?, ?, ?)', [
        txt($wpAuthor['name'] ?? 'محرر'), $slug, $email . '-' . (int)($wpAuthor['id'] ?? 0) . '@imported.janoub24',
        password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT), 'writer', txt($wpAuthor['description'] ?? '') ?: null,
    ]);
    echo '  + كاتب: ' . ($wpAuthor['name'] ?? '') . "\n";
    return (int)db()->lastInsertId();
}

/* 3) الأخبار */
$page = 1;
$done = $skipped = $failed = 0;
while (true) {
    $posts = null;
    for ($try = 1; $try <= 4 && $posts === null; $try++) {
        $posts = api($source, "posts?per_page=$perPage&page=$page&orderby=date&order=asc&_embed=author,wp:featuredmedia,wp:term");
        if ($posts === null) {
            echo "  … إعادة محاولة الصفحة $page\n";
            sleep(5 * $try);
        }
    }
    if ($posts === null) {
        echo "✗ تعذّر جلب الصفحة $page — أعد تشغيل الأمر لاحقاً لإكمال الباقي.\n";
        break;
    }
    if (!$posts || isset($posts['code'])) {
        break; // نهاية القائمة
    }
    foreach ($posts as $p) {
        if ($limit && $done >= $limit) {
            break 2;
        }
        $wpId = (int)$p['id'];
        if (val('SELECT id FROM articles WHERE legacy_id = ?', [$wpId])) {
            $skipped++;
            continue;
        }
        try {
            $title = txt($p['title']['rendered']);
            // قالب ووردبريس يكرر الصورة البارزة داخل النص — نحذفها لأنها تظهر أعلى الخبر أصلاً
            $raw = preg_replace('~<figure class="wp-block-post-featured-image">.*?</figure>~s', '', $p['content']['rendered'] ?? '', 1) ?? '';
            $body = clean_html($raw);
            $excerpt = excerpt(txt($p['excerpt']['rendered'] ?? '') ?: $body, 300);
            $cat = $catMap[(int)($p['categories'][0] ?? 0)] ?? $fallback;
            $catSlug = (string)val('SELECT slug FROM categories WHERE id = ?', [$cat]);
            $type = in_array($catSlug, ['opinion', 'articles'], true) ? 'opinion' : 'news';
            $video = null;
            if (preg_match('~youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})~', $p['content']['rendered'] ?? '', $vm)) {
                $video = 'https://www.youtube.com/watch?v=' . $vm[1];
                if (str_contains($title, 'فيديو')) {
                    $type = 'video';
                }
            }
            $author = isset($p['_embedded']['author'][0]['name']) ? author_id($p['_embedded']['author'][0])
                : ((int)val("SELECT id FROM users WHERE slug = 'editorial'") ?: null);

            // الصور داخل النص: تُنزّل وتُحفظ محلياً (WebP) ويُعاد كتابة روابطها
            $firstInline = null;
            $body = localize_images($body, $wpId, $firstInline);

            $image = null;
            $caption = null;
            $media = $p['_embedded']['wp:featuredmedia'][0] ?? null;
            $featuredUrl = $media['source_url'] ?? null;
            if (!$featuredUrl) {
                // لا صورة بارزة: أول صورة في النص، أو صورة الفيديو من يوتيوب
                $featuredUrl = $firstInline ?? ($video ? 'https://i.ytimg.com/vi/' . youtube_id($video) . '/hqdefault.jpg' : null);
            }
            if ($featuredUrl && ($src = fetch_image($featuredUrl))) {
                $image = store_image_resource($src, 'wp-' . $wpId . '-' . bin2hex(random_bytes(3)));
                imagedestroy($src);
                $caption = $media ? (txt($media['caption']['rendered'] ?? '') ?: null) : null;
            }

            $pub = date('Y-m-d H:i:s', strtotime($p['date_gmt'] . ' UTC'));
            $mod = date('Y-m-d H:i:s', strtotime($p['modified_gmt'] . ' UTC'));
            $legacySlug = rawurldecode($p['slug']);
            q('INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, image, image_caption, video_url, type, status, views,
                published_at, created_at, updated_at, legacy_id, legacy_slug) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $cat, $author, mb_substr($title, 0, 255), slugify($legacySlug ?: $title), $excerpt, $body ?: '<p></p>', $image, $caption, $video, $type,
                ($p['status'] ?? 'publish') === 'publish' ? 'published' : 'draft', 0, $pub, $pub, $mod, $wpId, $legacySlug,
            ]);
            $id = (int)db()->lastInsertId();

            $tags = [];
            foreach ($p['_embedded']['wp:term'] ?? [] as $group) {
                foreach ($group as $term) {
                    if (($term['taxonomy'] ?? '') === 'post_tag') {
                        $tags[] = txt($term['name']);
                    }
                }
            }
            if ($tags) {
                sync_tags($id, implode(',', $tags));
            }
            refresh_search_text($id);
            q('UPDATE articles SET region = ? WHERE id = ?', [detect_region($title, $excerpt, $body), $id]);
            $done++;
            echo "  ✔ [$wpId] $title\n";
        } catch (Throwable $ex) {
            $failed++;
            echo "  ✗ [$wpId] " . $ex->getMessage() . "\n";
        }
        usleep(250000); // لطف مع الخادم القديم
    }
    if (count($posts) < $perPage) {
        break; // آخر صفحة
    }
    $page++;
}

cache_clear();
echo "\nتم: $done مستورد، $skipped موجود مسبقاً، $failed فشل.\n";
