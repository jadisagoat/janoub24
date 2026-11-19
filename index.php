<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require ROOT . '/app/controllers.php';
require ROOT . '/app/feeds.php';
require ROOT . '/views/partials.php';

security_headers();

// وضع التجربة: منع جوجل من أرشفة نسخة الاختبار (مثل janoub24.com/newww)
if (cfg('staging')) {
    header('X-Robots-Tag: noindex, nofollow');
}

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$routes = [
    ['GET',  '#^/$#',                                   'c_home'],
    ['GET',  '#^/news/(\d+)(?:/([^/]*))?/?$#',          'c_article'],
    ['GET',  '#^/category/([a-z0-9-]+)/feed/?$#',       'c_feed'],
    ['GET',  '#^/category/([a-z0-9-]+)/?$#',            'c_category'],
    ['GET',  '#^/tag/([^/]+)/?$#',                      'c_tag'],
    ['GET',  '#^/author/([^/]+)/?$#',                   'c_author'],
    ['GET',  '#^/search/?$#',                           'c_search'],
    ['GET',  '#^/south/?$#',                            'c_south'],
    ['GET',  '#^/region/([a-z-]+)/?$#',                'c_region'],
    ['GET',  '#^/api/live/(\d+)$#',                    'c_api_live'],
    ['GET',  '#^/api/search$#',                         'c_api_search'],
    ['GET',  '#^/latest/?$#',                           'c_latest'],
    ['GET',  '#^/videos/?$#',                           'c_videos'],
    ['GET',  '#^/breaking/?$#',                         'c_breaking'],
    ['GET',  '#^/page/([^/]+)/?$#',                     'c_page'],
    ['GET',  '#^/feed/?$#',                             'c_feed'],
    ['GET',  '#^/sitemap\.xml$#',                       'c_sitemap_index'],
    ['GET',  '#^/sitemap-news\.xml$#',                  'c_sitemap_news'],
    ['GET',  '#^/sitemap-pages\.xml$#',                 'c_sitemap_pages'],
    ['GET',  '#^/sitemap-tags\.xml$#',                  'c_sitemap_tags'],
    ['GET',  '#^/sitemap-articles-(\d{4})-(\d{2})\.xml$#', 'c_sitemap_month'],
    ['GET',  '#^/robots\.txt$#',                        'c_robots'],
    ['GET',  '#^/manifest\.webmanifest$#',              'c_manifest'],
    ['POST', '#^/api/view$#',                           'c_api_view'],
    ['GET',  '#^/api/breaking$#',                       'c_api_breaking'],
    ['GET',  '#^/([A-Za-z0-9-]{8,128})\.txt$#',         'c_indexnow_key'],
];

// ضغط gzip من PHP (يعمل حتى لو كان mod_deflate معطلاً على الخادم)
if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
    ob_start('ob_gzhandler');
}

$cacheable = $method === 'GET' && !str_starts_with($path, '/api/');
if ($cacheable) {
    page_cache_serve();
    ob_start();
}

try {
    legacy_redirect($path); // روابط ووردبريس القديمة → 301
    $handled = false;
    foreach ($routes as [$m, $re, $fn]) {
        if (($m === $method || ($m === 'GET' && $method === 'HEAD')) && preg_match($re, $path, $mm)) {
            array_shift($mm);
            $fn(...$mm);
            $handled = true;
            break;
        }
    }
    if (!$handled) {
        not_found();
    }
} catch (PDOException $ex) {
    if (cfg('debug')) {
        throw $ex;
    }
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><title>صيانة</title><p style="font-family:sans-serif;text-align:center;margin-top:20vh">الموقع قيد الصيانة، يرجى المحاولة بعد قليل.</p>';
}

if ($cacheable && ob_get_level() > 0) {
    $out = ob_get_clean();
    page_cache_store($out);
    header('X-Cache: MISS');
    echo $out;
}
