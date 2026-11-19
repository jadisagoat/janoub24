<?php
/**
 * يولّد نسخ AVIF لكل صور الأخبار الموجودة (الصور الجديدة تُولَّد تلقائياً عند الرفع).
 *   php database/build_avif.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require dirname(__DIR__) . '/app/bootstrap.php';

if (!function_exists('imageavif')) {
    exit("PHP على هذا الخادم لا يدعم AVIF — الموقع سيستخدم WebP تلقائياً.\n");
}
$n = 0;
$t = microtime(true);
foreach (rows("SELECT DISTINCT image FROM articles WHERE image LIKE 'uploads/%.webp'") as $r) {
    if (make_avif($r['image'])) {
        $n++;
    }
}
cache_clear();
printf("تم: %d صورة خلال %.1f ثانية\n", $n, microtime(true) - $t);
