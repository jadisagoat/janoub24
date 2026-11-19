<?php
declare(strict_types=1);

/**
 * معالجة الصور: كل صورة تُقص بنسبة 16:9 وتُحفظ بثلاثة مقاسات (WebP عند توفره)
 *   name.webp (1200) — name-md.webp (720) — name-sm.webp (400)
 * إضافة إلى name-og.jpg (1200x630) لمشاركات فيسبوك وإكس وواتساب.
 */

const IMG_SIZES = ['lg' => [1200, 675], 'md' => [720, 405], 'sm' => [400, 225]];

function img_ext(): string
{
    return function_exists('imagewebp') ? 'webp' : 'jpg';
}

function img_url(?string $path, string $size = 'lg'): string
{
    if (!$path) {
        return base_url() . '/assets/img/placeholder' . ($size === 'lg' || $size === 'og' ? '' : '-' . $size) . '.webp';
    }
    if (preg_match('~^https?://~', $path)) {
        return $path;
    }
    if ($size !== 'lg') {
        $variant = preg_replace('~\.(\w+)$~', '-' . $size . '.$1', $path);
        if ($size === 'og') {
            $variant = preg_replace('~\.\w+$~', '-og.jpg', $path);
        }
        if ($variant && is_file(ROOT . '/' . $variant)) {
            return base_url() . '/' . $variant;
        }
    }
    return base_url() . '/' . $path;
}

/** نسخة AVIF (أصغر بنحو 30–50% من WebP) إن وُجدت */
function avif_srcset(?string $path): ?string
{
    if (!$path || preg_match('~^https?://~', $path)) {
        return null;
    }
    $base = preg_replace('~\.\w+$~', '', $path);
    if (!is_file(ROOT . "/$base-sm.avif")) {
        return null;
    }
    $u = base_url() . '/' . $base;
    return "$u-sm.avif 400w, $u-md.avif 720w, $u.avif 1200w";
}

/** وسم صورة متجاوب (AVIF ثم WebP) — $eager للصورة الرئيسية (LCP) */
function img_tag(?string $path, string $alt, string $sizes = '(max-width: 768px) 100vw, 640px', bool $eager = false, string $class = ''): string
{
    $cls = $class ? ' class="' . e($class) . '"' : '';
    if (!$path) {
        $ph = base_url() . '/assets/img/placeholder';
        return '<img src="' . e("$ph-md.webp") . '" srcset="' . e("$ph-sm.webp 400w, $ph-md.webp 720w, $ph.webp 1200w") . '" sizes="' . e($sizes) . '" width="1200" height="675" alt="' . e($alt) . '"' . $cls . ' loading="lazy" decoding="async">';
    }
    $srcset = img_url($path, 'sm') . ' 400w, ' . img_url($path, 'md') . ' 720w, ' . img_url($path, 'lg') . ' 1200w';
    $load = $eager ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"';
    $img = '<img src="' . e(img_url($path, 'md')) . '" srcset="' . e($srcset) . '" sizes="' . e($sizes) . '" width="1200" height="675" alt="' . e($alt) . '"' . $cls . ' ' . $load . '>';
    $avif = avif_srcset($path);
    return $avif ? '<picture><source type="image/avif" srcset="' . e($avif) . '" sizes="' . e($sizes) . '">' . $img . '</picture>' : $img;
}

/** يولّد نسخ AVIF لصورة محفوظة (من ملفات WebP الموجودة) */
function make_avif(string $path): bool
{
    if (!function_exists('imageavif') || !preg_match('~\.webp$~', $path)) {
        return false;
    }
    $base = preg_replace('~\.webp$~', '', $path);
    foreach (['' => '', '-md' => '-md', '-sm' => '-sm'] as $suf) {
        $srcFile = ROOT . "/$base$suf.webp";
        $dst = ROOT . "/$base$suf.avif";
        if (!is_file($srcFile) || is_file($dst)) {
            continue;
        }
        $im = @imagecreatefromwebp($srcFile);
        if (!$im) {
            return false;
        }
        imageavif($im, $dst, 52, 6);
        imagedestroy($im);
    }
    return true;
}

function gd_load(string $file, string $mime)
{
    return match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file),
        'image/png'  => imagecreatefrompng($file),
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file) : false,
        'image/gif'  => imagecreatefromgif($file),
        default      => false,
    };
}

function gd_cover($src, int $w, int $h)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);
    $cw = (int)round($w / $scale);
    $ch = (int)round($h / $scale);
    $sx = (int)(($sw - $cw) / 2);
    $sy = (int)max(0, ($sh - $ch) / 3); // ميل للأعلى للحفاظ على الوجوه
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $w, $h, $cw, $ch);
    return $dst;
}

function gd_save($im, string $file): void
{
    if (str_ends_with($file, '.webp')) {
        imagewebp($im, $file, 80);
    } else {
        imagejpeg($im, $file, 82);
    }
}

/** يحفظ مورد GD بكل المقاسات ويرجع المسار النسبي للنسخة الكبيرة */
function store_image_resource($src, string $baseName, ?string $subdir = null): string
{
    $subdir = $subdir ?? date('Y/m');
    $dir = ROOT . '/uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $ext = img_ext();
    foreach (IMG_SIZES as $key => [$w, $h]) {
        $im = gd_cover($src, $w, $h);
        $suffix = $key === 'lg' ? '' : '-' . $key;
        gd_save($im, "$dir/$baseName$suffix.$ext");
        imagedestroy($im);
    }
    $og = gd_cover($src, 1200, 630);
    imagejpeg($og, "$dir/$baseName-og.jpg", 84);
    imagedestroy($og);
    $path = "uploads/$subdir/$baseName.$ext";
    make_avif($path);
    return $path;
}

/** رفع صورة من نموذج — يرجع المسار أو يرمي استثناء برسالة عربية */
/**
 * يرفض الصور ذات الأبعاد الضخمة قبل فك ضغطها (صورة صغيرة الحجم بأبعاد 30000×30000
 * تستهلك ذاكرة الخادم كلها وتوقفه).
 */
function assert_image_dimensions(string $tmp): void
{
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) {
        throw new RuntimeException('ملف الصورة غير صالح.');
    }
    if ($info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 40000000) {
        throw new RuntimeException('أبعاد الصورة كبيرة جداً (الحد 8000×8000 بكسل).');
    }
}

function save_uploaded_image(array $file, string $hint = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع الصورة.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('حجم الصورة يتجاوز 10 ميغابايت.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new RuntimeException('الصيغة غير مدعومة (JPG, PNG, WebP, GIF فقط).');
    }
    assert_image_dimensions($file['tmp_name']);
    $src = gd_load($file['tmp_name'], $mime);
    if (!$src) {
        throw new RuntimeException('تعذّرت قراءة الصورة.');
    }
    // أسماء ملفات لاتينية فقط لضمان عمل الروابط على كل الاستضافات
    $slug = $hint !== '' ? slugify($hint, 40) : '';
    $slug = preg_match('/^[a-z0-9-]+$/', $slug) ? $slug : 'j24-' . date('dHi');
    $name = $slug . '-' . bin2hex(random_bytes(4));
    $path = store_image_resource($src, $name);
    imagedestroy($src);
    return $path;
}

/** حفظ مورد GD بمقاس واحد مع الحفاظ على النسبة — للصور داخل نص المقال */
function store_single_image($src, string $name, int $maxW = 1200): string
{
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min($maxW, $w);
    $nh = max(1, (int)round($h * $nw / $w));
    $im = imagecreatetruecolor($nw, $nh);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    imagecopyresampled($im, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $sub = date('Y/m');
    $dir = ROOT . '/uploads/' . $sub;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $name . '.' . img_ext();
    gd_save($im, "$dir/$file");
    imagedestroy($im);
    return "uploads/$sub/$file";
}

/** صورة مستقلة بمقاس واحد (للصور داخل نص المقال والصور الشخصية) */
function save_inline_image(array $file, int $maxW = 1200, bool $square = false): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع الصورة.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new RuntimeException('الصيغة غير مدعومة.');
    }
    assert_image_dimensions($file['tmp_name']);
    $src = gd_load($file['tmp_name'], $mime);
    if (!$src) {
        throw new RuntimeException('تعذّرت قراءة الصورة.');
    }
    if ($square) {
        $im = gd_cover($src, $maxW, $maxW);
    } else {
        $w = imagesx($src);
        $h = imagesy($src);
        $nw = min($maxW, $w);
        $nh = (int)round($h * $nw / $w);
        $im = imagecreatetruecolor($nw, $nh);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagecopyresampled($im, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    }
    $sub = date('Y/m');
    $dir = ROOT . '/uploads/' . $sub;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'inline-' . bin2hex(random_bytes(5)) . '.' . img_ext();
    gd_save($im, "$dir/$name");
    imagedestroy($im);
    imagedestroy($src);
    return "uploads/$sub/$name";
}
