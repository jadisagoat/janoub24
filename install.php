<?php
/**
 * مثبّت جنوب 24
 *   من سطر الأوامر:  php install.php            (مع محتوى تجريبي)
 *                     php install.php --no-demo
 *                     php install.php --force   (إعادة التثبيت ومسح البيانات!)
 *   من المتصفح: يعمل مرة واحدة فقط ثم يُقفل. احذف الملف بعد التثبيت على الاستضافة.
 */
declare(strict_types=1);

define('ROOT', __DIR__);
$GLOBALS['config'] = require ROOT . '/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);
mb_internal_encoding('UTF-8');
require ROOT . '/app/db.php';
require ROOT . '/app/helpers.php';
require ROOT . '/app/images.php';
require ROOT . '/app/models.php';

$cli = PHP_SAPI === 'cli';
$args = $cli ? ($argv ?? []) : [];
$force = in_array('--force', $args, true);
$withDemo = $cli ? !in_array('--no-demo', $args, true) : (($_GET['demo'] ?? '1') !== '0');
$lock = ROOT . '/storage/installed.lock';

function out(string $m): void
{
    echo PHP_SAPI === 'cli' ? $m . PHP_EOL : '<p>' . htmlspecialchars($m) . '</p>';
}

if (!$cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تثبيت جنوب 24</title><body style="font-family:Tahoma;max-width:700px;margin:40px auto;line-height:1.9">';
}
if (is_file($lock) && !$force) {
    out('الموقع مثبّت مسبقاً. لإعادة التثبيت من سطر الأوامر: php install.php --force');
    exit;
}
if (!extension_loaded('gd')) {
    out('تحذير: امتداد GD غير مفعّل — لن تُعالج الصور.');
}

/* 1) قاعدة البيانات */
$c = cfg('db');
$pdo = new PDO("mysql:host={$c['host']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// حماية: لا يُمسح موقع قائم أبداً من المتصفح. إعادة التثبيت فوق بيانات موجودة من سطر الأوامر فقط مع --force
$existing = (int)val("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('articles', 'users', 'settings')");
if ($existing > 0 && !($cli && $force)) {
    if (!$cli) {
        http_response_code(403);
    }
    out('⛔ قاعدة البيانات تحتوي على موقع مثبّت. تم إيقاف التثبيت لحماية البيانات.');
    out('احذف ملف install.php من الخادم.');
    exit;
}

$sql = file_get_contents(ROOT . '/database/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    db()->exec($stmt);
}
out('✔ تم إنشاء الجداول');

/* 2) الإعدادات */
$settings = [
    'site_name'        => 'جنوب 24',
    'site_name_en'     => 'Janoub24',
    'site_tagline'     => 'أخبار لبنان والجنوب والشرق الأوسط',
    'site_description' => 'جنوب 24 موقع إخباري لبناني يغطي أخبار الجنوب ولبنان والشرق الأوسط لحظة بلحظة: سياسة، أمن، اقتصاد، تقارير وفيديو.',
    'footer_about'     => 'جنوب 24 موقع إخباري لبناني مستقل، يواكب الحدث في جنوب لبنان وكل لبنان والمنطقة على مدار الساعة، بالخبر الموثوق والصورة والفيديو.',
    'social_facebook'  => 'https://www.facebook.com/p/--24-100069110781098/',
    'social_x'         => 'https://x.com/janoub24website',
    'social_youtube'   => 'https://www.youtube.com/@janoub24',
    'social_whatsapp'  => '',
    'social_telegram'  => '',
    'news_whatsapp'    => '',
    'social_instagram' => '',
    'twitter_handle'   => '@janoub24website',
    'contact_email'    => '',
    'google_news_url'  => '',
    'google_news_name' => 'جنوب 24',
    'ga4_id'           => '',
    'google_site_verification' => '',
    'bing_verification' => '',
    'facebook_app_id'  => '',
    'indexnow_key'     => bin2hex(random_bytes(16)),
    'founding_date'    => '2019',
    'head_code'        => '',
    'footer_code'      => '',
];
$st = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?)');
foreach ($settings as $k => $v) {
    $st->execute([$k, $v]);
}
out('✔ تم حفظ الإعدادات');

/* 3) الأقسام */
$cats = [
    ['أخبار محلية', 'local', 'أخبار الجنوب اللبناني لحظة بلحظة: صور، صيدا، النبطية، بنت جبيل، مرجعيون، حاصبيا وجزين.', '#0b6fae', 'lead', 1, 1],
    ['سياسة', 'politics', 'آخر الأخبار السياسية في لبنان: الحكومة، مجلس النواب، والمواقف والتطورات.', '#b91c1c', 'lead', 1, 1],
    ['أمن وقضاء', 'security', 'أخبار أمنية وقضائية من لبنان: توقيفات، أحكام، حوادث ومتابعات.', '#b45309', 'list', 1, 1],
    ['إقليمي ودولي', 'world', 'أخبار الشرق الأوسط والعالم العربي والعالم وتأثيرها على لبنان.', '#0f766e', 'grid', 1, 1],
    ['اقتصاد', 'economy', 'أخبار الاقتصاد اللبناني: الدولار، المصارف، الأسعار، المحروقات والأسواق.', '#15803d', 'grid', 1, 1],
    ['رأي', 'opinion', 'مقالات رأي وتحليلات لكتّاب جنوب 24.', '#0f1b2d', 'grid', 1, 0],
    ['مقالات', 'articles', 'مقالات وقراءات معمّقة في الشأن اللبناني والإقليمي.', '#6d28d9', 'grid', 1, 0],
    ['منوعات', 'variety', 'منوعات، ثقافة، فن، صحة ومجتمع.', '#db2777', 'grid', 1, 1],
];
$catIds = [];
foreach ($cats as $i => [$name, $slug, $desc, $color, $layout, $menu, $home]) {
    q('INSERT INTO categories (name, slug, description, color, home_layout, sort_order, show_in_menu, show_on_home) VALUES (?,?,?,?,?,?,?,?)',
        [$name, $slug, $desc, $color, $layout, $i + 1, $menu, $home]);
    $catIds[$slug] = (int)db()->lastInsertId();
}
foreach (['local' => 'أخبار الجنوب اللبناني اليوم - أخبار محلية عاجلة', 'politics' => 'أخبار لبنان السياسية اليوم', 'security' => 'أخبار أمنية وقضائية في لبنان اليوم', 'world' => 'أخبار الشرق الأوسط والعالم اليوم', 'economy' => 'أخبار الاقتصاد اللبناني وسعر الدولار اليوم', 'opinion' => 'مقالات رأي في الشأن اللبناني والإقليمي', 'articles' => 'مقالات وتحليلات سياسية لبنانية', 'variety' => 'منوعات وثقافة ومجتمع من لبنان'] as $cs => $ct) {
    q('UPDATE categories SET seo_title = ? WHERE slug = ?', [$ct, $cs]);
}
out('✔ تم إنشاء الأقسام');

/* 4) المستخدمون */
$adminPass = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
q('INSERT INTO users (name, slug, email, password_hash, role, job_title, bio) VALUES (?,?,?,?,?,?,?)',
    ['إدارة التحرير', 'editorial', 'admin@janoub24.com', password_hash($adminPass, PASSWORD_DEFAULT), 'admin', 'هيئة التحرير', 'فريق تحرير موقع جنوب 24.']);
q('UPDATE users SET avatar = ? WHERE slug = ?', ['assets/img/logo-192.png', 'editorial']);
$users = ['editor' => (int)db()->lastInsertId()];
if ($withDemo) {
    $demoUsers = [
        'reporter' => ['مراسل جنوب 24', 'janoub24-reporter', 'مراسل ميداني', 'فريق المراسلين الميدانيين لموقع جنوب 24 في مختلف أقضية الجنوب.'],
        'writer1'  => ['هاني منصور', 'hani-mansour', 'كاتب رأي', 'كاتب ومحلل في الشؤون الاجتماعية والتنموية.'],
        'writer2'  => ['ليلى حمدان', 'layla-hamdan', 'باحثة في الشؤون العامة', 'باحثة وكاتبة في قضايا الإصلاح والحوكمة.'],
        'writer3'  => ['نادر شمس', 'nader-shams', 'صحافي', 'صحافي مهتم بالإعلام الرقمي وتحولاته.'],
    ];
    foreach ($demoUsers as $key => [$n, $s, $job, $bio]) {
        q('INSERT INTO users (name, slug, email, password_hash, role, job_title, bio) VALUES (?,?,?,?,?,?,?)',
            [$n, $s, $s . '@demo.janoub24', password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'writer', $job, $bio]);
        $users[$key] = (int)db()->lastInsertId();
    }
}
out('✔ تم إنشاء المستخدمين');

/* 5) الصفحات الثابتة (مهمة لـ E-E-A-T و Google News) */
$pages = [
    ['من نحن', 'about', '<p><strong>جنوب 24</strong> موقع إخباري لبناني انطلق ليكون صوت الجنوب اللبناني ونافذته على لبنان والعالم. نغطي الأخبار المحلية في أقضية صور وصيدا والنبطية وبنت جبيل ومرجعيون وحاصبيا وجزين، إلى جانب الشأن السياسي والأمني والاقتصادي اللبناني والتطورات الإقليمية والدولية.</p><h2>رسالتنا</h2><p>تقديم خبر دقيق وسريع ومتوازن، يحترم عقل القارئ ويلتزم بأخلاقيات المهنة.</p><h2>فريقنا</h2><p>يضم فريق جنوب 24 محررين ومراسلين ميدانيين ومصورين يعملون على مدار الساعة.</p>', 'تعرّف على موقع جنوب 24: رسالتنا وفريقنا وتغطيتنا لأخبار الجنوب ولبنان والمنطقة.'],
    ['اتصل بنا', 'contact', '<p>يسعدنا تواصلكم معنا لإرسال الأخبار والصور والملاحظات أو للاستفسارات الإعلانية.</p><ul><li>البريد الإلكتروني: يُضاف من لوحة التحكم</li><li>واتساب الأخبار: يُضاف من لوحة التحكم</li></ul>', 'تواصل مع فريق تحرير جنوب 24 لإرسال الأخبار والملاحظات والاستفسارات الإعلانية.'],
    ['سياسة التحرير', 'editorial-policy', '<h2>الدقة والتحقق</h2><p>نلتزم بالتحقق من الأخبار من مصدرين على الأقل قبل النشر، ونميّز بوضوح بين الخبر والرأي.</p><h2>التصحيحات</h2><p>عند اكتشاف خطأ نصححه بشكل واضح ونذكر تاريخ التحديث في أسفل الخبر.</p><h2>الاستقلالية</h2><p>يعمل فريق التحرير باستقلالية تامة عن أي جهة سياسية أو تجارية، ويُشار إلى المحتوى المموّل بوضوح.</p><h2>حقوق النشر</h2><p>نحترم حقوق الملكية الفكرية ونذكر المصدر عند الاقتباس.</p>', 'سياسة التحرير في جنوب 24: الدقة، التحقق، التصحيحات والاستقلالية.'],
    ['سياسة الخصوصية', 'privacy', '<p>نحترم خصوصية زوارنا. يستخدم الموقع ملفات تعريف الارتباط لأغراض الإحصاء وتحسين التجربة فقط، ولا نشارك بياناتكم مع أي طرف ثالث لأغراض تجارية.</p>', 'سياسة الخصوصية لموقع جنوب 24.'],
    ['شروط الاستخدام', 'terms', '<p>جميع المحتويات المنشورة على جنوب 24 محمية بحقوق النشر. يُسمح بالاقتباس مع ذكر المصدر ورابط الخبر الأصلي.</p>', 'شروط استخدام موقع جنوب 24.'],
];
foreach ($pages as [$t, $s, $b, $d]) {
    q('INSERT INTO pages (title, slug, body, seo_description) VALUES (?,?,?,?)', [$t, $s, $b, $d]);
}
out('✔ تم إنشاء الصفحات الثابتة');

/* 6) صور الهوية (شعار PNG وصورة المشاركة الافتراضية) */
function brand_font(): ?string
{
    foreach (['C:/Windows/Fonts/tahomabd.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $f) {
        if (is_file($f)) {
            return $f;
        }
    }
    return null;
}
function hex2rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
// ملفات الشعار (logo.png و logo-dark.png والأيقونات و og-default.jpg) مأخوذة من الشعار الأصلي
// ولإعادة توليدها: php database/build_logo.php

/* 7) المحتوى التجريبي */
function demo_image(int $seed, string $hex)
{
    mt_srand($seed * 7919);
    $w = 1200;
    $h = 675;
    $im = imagecreatetruecolor($w, $h);
    [$r, $g, $b] = hex2rgb($hex);
    $dark = [15, 27, 45];
    for ($y = 0; $y < $h; $y++) {
        $t = $y / $h;
        $col = imagecolorallocate($im, (int)($r * (1 - $t) + $dark[0] * $t), (int)($g * (1 - $t) + $dark[1] * $t), (int)($b * (1 - $t) + $dark[2] * $t));
        imageline($im, 0, $y, $w, $y, $col);
    }
    imagealphablending($im, true);
    for ($i = 0; $i < 7; $i++) {
        $a = mt_rand(95, 118);
        $col = imagecolorallocatealpha($im, 255, 255, 255, $a);
        $rad = mt_rand(120, 520);
        imagefilledellipse($im, mt_rand(-100, $w + 100), mt_rand(-100, $h + 100), $rad, $rad, $col);
    }
    $line = imagecolorallocatealpha($im, 255, 255, 255, 112);
    for ($x = -$h; $x < $w; $x += 46) {
        imageline($im, $x, $h, $x + $h, 0, $line);
    }
    $red = imagecolorallocatealpha($im, 0, 50, 87, 20);
    imagefilledrectangle($im, 60, $h - 110, 150, $h - 60, $red);
    $font = brand_font();
    if ($font) {
        imagettftext($im, 30, 0, 74, $h - 70, imagecolorallocate($im, 255, 255, 255), $font, '24');
    }
    return $im;
}

if ($withDemo) {
    $demo = require ROOT . '/database/demo.php';
    $closing = 'ويواصل «جنوب 24» متابعة تفاصيل هذا الملف وموافاتكم بالمستجدات تباعاً.';
    $ids = [];
    foreach ($demo as $i => [$catSlug, $type, $feat, $brk, $title, $ex, $paras, $tags, $author, $mins]) {
        $cat = $catIds[$catSlug];
        $color = $cats[array_search($catSlug, array_column($cats, 1), true)][3];
        $img = null;
        if (extension_loaded('gd')) {
            $src = demo_image($i + 1, $color);
            $img = store_image_resource($src, 'demo-' . ($i + 1) . '-' . bin2hex(random_bytes(3)), 'demo');
            imagedestroy($src);
        }
        $body = '';
        foreach ($paras as $k => $p) {
            $body .= '<p>' . e($p) . '</p>';
            if ($k === 1 && $type !== 'opinion') {
                $body .= '<h2>تفاصيل إضافية</h2>';
            }
        }
        if ($type !== 'opinion') {
            $body .= '<p>' . $closing . '</p>';
        }
        $pub = date('Y-m-d H:i:s', time() - $mins * 60);
        q('INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, image, image_caption, type, status, is_featured, is_breaking, is_demo, views, published_at, created_at, updated_at)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?)',
            [$cat, $users[$author] ?? $users['editor'], $title, slugify($title), $ex, $body, $img, $img ? 'صورة تعبيرية' : null, $type, 'published', $feat, $brk, mt_rand(40, 4000), $pub, $pub, $pub]);
        $id = (int)db()->lastInsertId();
        $ids[] = ['id' => $id, 'title' => $title, 'brk' => $brk, 'pub' => $pub];
        sync_tags($id, $tags);
        refresh_search_text($id);
    }
    // عناوين عاجلة
    foreach ($ids as $a) {
        if ($a['brk']) {
            q('INSERT INTO breaking_news (text, article_id, created_at, expires_at) VALUES (?,?,?, NOW() + INTERVAL 2 DAY)', [$a['title'], $a['id'], $a['pub']]);
        }
    }
    foreach ([
        ['الدفاع المدني: لا إصابات في حادث سير على أوتوستراد صيدا – صور', 15],
        ['اتحاد بلديات قضاء النبطية يعقد اجتماعاً طارئاً لبحث أزمة النفايات', 55],
        ['الأرصاد الجوية: منخفض جوي يضرب لبنان اعتباراً من مساء الغد', 120],
    ] as [$text, $mins]) {
        q('INSERT INTO breaking_news (text, created_at, expires_at) VALUES (?, ?, NOW() + INTERVAL 2 DAY)', [$text, date('Y-m-d H:i:s', time() - $mins * 60)]);
    }
    out('✔ تم إدخال ' . count($ids) . ' خبراً تجريبياً مع صورها');
}

if (!is_dir(ROOT . '/storage')) {
    mkdir(ROOT . '/storage', 0775, true);
}
file_put_contents($lock, date('c'));
q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', ['schema_version', '2']); // قاعدة بيانات جديدة = أحدث إصدار
cache_clear_all_safe();

function cache_clear_all_safe(): void
{
    foreach (glob(ROOT . '/storage/cache/*.html') ?: [] as $f) {
        @unlink($f);
    }
}

out('');
out('========================================');
out('تم التثبيت بنجاح ✔');
out('لوحة التحكم: ' . base_url() . '/admin/');
out('البريد: admin@janoub24.com');
out('كلمة المرور: ' . $adminPass);
out('غيّر كلمة المرور بعد أول دخول.');
out('========================================');
