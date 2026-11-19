<?php
declare(strict_types=1);

/**
 * تخزين مؤقت للصفحات الكاملة للزوار (يتجاوزه المحررون المسجلون).
 * يُمسح تلقائياً عند نشر أو تعديل أي خبر.
 */

function cache_dir(): string
{
    $d = ROOT . '/storage/cache';
    if (!is_dir($d)) {
        mkdir($d, 0775, true);
    }
    return $d;
}

function page_cache_file(): string
{
    return cache_dir() . '/' . md5($_SERVER['REQUEST_URI'] ?? '/') . '.html';
}

function page_cache_enabled(): bool
{
    // لا نخزّن روابط بمعاملات عشوائية (?x=1، ?x=2…) — تمنع ملء القرص بملفات ذاكرة مؤقتة
    $extra = array_diff(array_keys($_GET), ['page']);
    return (int)cfg('cache_ttl', 0) > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !is_admin_session() && !$extra
        && (!isset($_GET['page']) || ctype_digit((string)$_GET['page']));
}

/** يرسل الصفحة من الذاكرة إن كانت حديثة */
function page_cache_serve(): void
{
    if (!page_cache_enabled()) {
        return;
    }
    $f = page_cache_file();
    if (is_file($f) && filemtime($f) > time() - (int)cfg('cache_ttl')) {
        $raw = file_get_contents($f);
        [$type, $body] = explode("\n", $raw, 2) + [1 => ''];
        header('Content-Type: ' . $type);
        header('X-Cache: HIT');
        header('Cache-Control: public, max-age=60');
        echo $body;
        exit;
    }
}

function page_cache_store(string $body): void
{
    if (!page_cache_enabled() || http_response_code() !== 200) {
        return;
    }
    $type = 'text/html; charset=utf-8';
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $type = trim(substr($h, 13));
        }
        if (stripos($h, 'Location:') === 0) {
            return;
        }
    }
    @file_put_contents(page_cache_file(), $type . "\n" . $body, LOCK_EX);
}

function cache_clear(): int
{
    $n = 0;
    foreach (glob(cache_dir() . '/*.html') ?: [] as $f) {
        @unlink($f) && $n++;
    }
    return $n;
}
