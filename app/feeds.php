<?php
declare(strict_types=1);

/* ============ RSS + خرائط الموقع + robots ============ */

function xml_header(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    header('X-Robots-Tag: noindex, follow');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
}

function x($s): string
{
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function c_feed(?string $catSlug = null): void
{
    $o = ['limit' => 40];
    $title = site_name();
    $link = url();
    if ($catSlug) {
        $cat = category_by_slug($catSlug);
        if (!$cat) {
            not_found();
        }
        $o['category'] = $cat['id'];
        $title .= ' - ' . $cat['name'];
        $link = category_url($cat);
    }
    $items = articles($o);
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel>';
    echo '<title>' . x($title) . '</title><link>' . x($link) . '</link>';
    echo '<atom:link href="' . x($catSlug ? url('category/' . $catSlug . '/feed') : url('feed')) . '" rel="self" type="application/rss+xml"/>';
    echo '<description>' . x(setting('site_description', 'أخبار لبنان والجنوب والشرق الأوسط')) . '</description>';
    echo '<language>ar</language><ttl>10</ttl>';
    echo '<lastBuildDate>' . date(DATE_RSS, $items ? ts($items[0]['published_at']) : time()) . '</lastBuildDate>';
    echo '<image><url>' . x(asset('img/logo-512.png')) . '</url><title>' . x($title) . '</title><link>' . x($link) . '</link></image>';
    foreach ($items as $a) {
        $u = article_url($a);
        echo '<item>';
        echo '<title>' . x($a['title']) . '</title><link>' . x($u) . '</link><guid isPermaLink="false">' . x(site_name() . '-' . $a['id']) . '</guid>';
        echo '<pubDate>' . date(DATE_RSS, ts($a['published_at'])) . '</pubDate>';
        echo '<category>' . x($a['cat_name']) . '</category>';
        if ($a['author_name']) {
            echo '<dc:creator>' . x($a['author_name']) . '</dc:creator>';
        }
        echo '<description>' . x($a['excerpt']) . '</description>';
        if ($a['image']) {
            echo '<media:content url="' . x(img_url($a['image'], 'lg')) . '" medium="image" width="1200" height="675"/>';
            echo '<enclosure url="' . x(img_url($a['image'], 'og')) . '" type="image/jpeg" length="0"/>';
        }
        echo '</item>';
    }
    echo '</channel></rss>';
}

function c_sitemap_index(): void
{
    xml_header();
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $last = val("SELECT MAX(published_at) FROM articles a WHERE " . ART_LIVE) ?: date('Y-m-d H:i:s');
    foreach (['sitemap-news.xml', 'sitemap-pages.xml', 'sitemap-tags.xml'] as $s) {
        echo '<sitemap><loc>' . x(url($s)) . '</loc><lastmod>' . iso($last) . '</lastmod></sitemap>';
    }
    $months = rows("SELECT DATE_FORMAT(published_at, '%Y') y, DATE_FORMAT(published_at, '%m') m, MAX(updated_at) lm
        FROM articles a WHERE " . ART_LIVE . " GROUP BY y, m ORDER BY y DESC, m DESC");
    foreach ($months as $m) {
        echo '<sitemap><loc>' . x(url("sitemap-articles-{$m['y']}-{$m['m']}.xml")) . '</loc><lastmod>' . iso($m['lm']) . '</lastmod></sitemap>';
    }
    echo '</sitemapindex>';
}

/** خريطة أخبار Google News — أخبار آخر 48 ساعة فقط */
function c_sitemap_news(): void
{
    $items = articles(['since_hours' => 48, 'limit' => 1000]);
    xml_header();
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    $pub = setting('google_news_name', site_name());
    foreach ($items as $a) {
        echo '<url><loc>' . x(article_url($a)) . '</loc>';
        echo '<news:news><news:publication><news:name>' . x($pub) . '</news:name><news:language>ar</news:language></news:publication>';
        echo '<news:publication_date>' . iso($a['published_at']) . '</news:publication_date><news:title>' . x($a['title']) . '</news:title></news:news>';
        if ($a['image']) {
            echo '<image:image><image:loc>' . x(img_url($a['image'])) . '</image:loc></image:image>';
        }
        echo '</url>';
    }
    echo '</urlset>';
}

function c_sitemap_month(string $y, string $m): void
{
    $from = "$y-$m-01 00:00:00";
    $to = date('Y-m-d H:i:s', strtotime("$from +1 month"));
    $items = rows('SELECT a.id, a.slug, a.title, a.image, a.updated_at, a.published_at FROM articles a WHERE ' . ART_LIVE . '
        AND a.published_at >= ? AND a.published_at < ? ORDER BY a.published_at DESC', [$from, $to]);
    if (!$items) {
        not_found();
    }
    xml_header();
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
    foreach ($items as $a) {
        echo '<url><loc>' . x(article_url($a)) . '</loc><lastmod>' . iso(max(ts($a['updated_at']), ts($a['published_at']))) . '</lastmod>';
        if ($a['image']) {
            echo '<image:image><image:loc>' . x(img_url($a['image'])) . '</image:loc></image:image>';
        }
        echo '</url>';
    }
    echo '</urlset>';
}

function c_sitemap_pages(): void
{
    xml_header();
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    $now = date('c');
    $urls = [[url(), $now, 'always', '1.0'], [url('latest'), $now, 'always', '0.9'], [url('breaking'), $now, 'always', '0.9'], [url('videos'), $now, 'hourly', '0.7']];
    foreach (categories() as $c) {
        $urls[] = [category_url($c), $now, 'hourly', '0.8'];
    }
    $urls[] = [url('south'), $now, 'hourly', '0.9'];
    foreach (region_stats() as $slug => $st) {
        if ($st['n'] > 0 && region($slug)) {
            $urls[] = [region_url($slug), $now, 'hourly', '0.8'];
        }
    }
    foreach (rows('SELECT slug, updated_at FROM pages') as $p) {
        $urls[] = [page_url($p['slug']), iso($p['updated_at']), 'monthly', '0.3'];
    }
    foreach (rows("SELECT DISTINCT u.slug FROM users u JOIN articles a ON a.author_id = u.id WHERE u.is_active = 1 AND " . ART_LIVE) as $u) {
        $urls[] = [author_url($u['slug']), $now, 'daily', '0.5'];
    }
    foreach ($urls as [$loc, $lm, $cf, $pr]) {
        echo "<url><loc>" . x($loc) . "</loc><lastmod>$lm</lastmod><changefreq>$cf</changefreq><priority>$pr</priority></url>";
    }
    echo '</urlset>';
}

function c_sitemap_tags(): void
{
    $tags = rows('SELECT t.slug, MAX(a.published_at) lm FROM tags t JOIN article_tags at ON at.tag_id = t.id
        JOIN articles a ON a.id = at.article_id WHERE ' . ART_LIVE . ' GROUP BY t.id HAVING COUNT(*) >= 2 ORDER BY lm DESC LIMIT 5000');
    xml_header();
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($tags as $t) {
        echo '<url><loc>' . x(tag_url($t['slug'])) . '</loc><lastmod>' . iso($t['lm']) . '</lastmod></url>';
    }
    echo '</urlset>';
}

function c_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $base = parse_url(base_url(), PHP_URL_PATH) ?: '';
    if (cfg('staging')) {
        echo "User-agent: *\nDisallow: /\n";
        return;
    }
    echo "User-agent: *\n";
    echo "Allow: $base/\n";
    echo "Disallow: $base/admin/\n";
    echo "Disallow: $base/api/\n";
    echo "Disallow: $base/search\n";
    echo "Disallow: $base/*?preview=\n\n";
    echo "User-agent: Googlebot-News\nAllow: $base/\n\n";
    echo 'Sitemap: ' . url('sitemap.xml') . "\n";
    echo 'Sitemap: ' . url('sitemap-news.xml') . "\n";
}

function c_manifest(): void
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => site_name() . ' - ' . setting('site_tagline', 'أخبار لبنان والجنوب'),
        'short_name' => site_name(),
        'lang' => 'ar',
        'dir' => 'rtl',
        'start_url' => url() . '?utm_source=pwa',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#003257',
        'icons' => [
            ['src' => asset('img/logo-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => asset('img/logo-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
