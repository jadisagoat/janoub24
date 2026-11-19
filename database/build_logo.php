<?php
/**
 * يولّد ملفات الشعار من الشعار الأصلي (assets/img/brand/logo-source-dark.png):
 *   logo.png       — نسخة كحلية على خلفية شفافة (للترويسة البيضاء)
 *   logo-dark.png  — النسخة الأصلية على خلفية شفافة (للتذييل والوضع الليلي)
 *   logo-192/512.png, favicon-48.png — أيقونة مربع "24"
 *   og-default.jpg — صورة المشاركة الافتراضية
 * التشغيل: php database/build_logo.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$root = dirname(__DIR__);
$src = imagecreatefrompng("$root/assets/img/brand/logo-source-dark.png");
$W = imagesx($src);
$H = imagesy($src);

$D  = [2, 2, 32];       // الخلفية الداكنة
$G  = [203, 205, 208];  // الرمادي الفاتح (الكلمة والإطار والمربع)
$BL = [4, 111, 170];    // الأزرق (24 والنقاط)
$NAVY = [0, 50, 87];    // كحلي الشعار الأبيض
$WHITE = [255, 255, 255];
$SQ = [48, 34, 223, 203]; // حدود مربع "24"

// فك المزج: p = D + a(G-D) + c(BL-D)
$g = array_map(fn($i) => $G[$i] - $D[$i], [0, 1, 2]);
$b = array_map(fn($i) => $BL[$i] - $D[$i], [0, 1, 2]);
$gg = $g[0] ** 2 + $g[1] ** 2 + $g[2] ** 2;
$bb = $b[0] ** 2 + $b[1] ** 2 + $b[2] ** 2;
$gb = $g[0] * $b[0] + $g[1] * $b[1] + $g[2] * $b[2];
$det = $gg * $bb - $gb * $gb;

$mix = [];
for ($y = 0; $y < $H; $y++) {
    for ($x = 0; $x < $W; $x++) {
        $v = imagecolorat($src, $x, $y);
        $p = [(($v >> 16) & 255) - $D[0], (($v >> 8) & 255) - $D[1], ($v & 255) - $D[2]];
        $pg = $p[0] * $g[0] + $p[1] * $g[1] + $p[2] * $g[2];
        $pb = $p[0] * $b[0] + $p[1] * $b[1] + $p[2] * $b[2];
        $a = ($pg * $bb - $pb * $gb) / $det;
        $c = ($pb * $gg - $pg * $gb) / $det;
        $a = max(0.0, $a);
        $c = max(0.0, $c);
        $s = $a + $c;
        if ($s > 1) {
            $a /= $s;
            $c /= $s;
            $s = 1.0;
        }
        if ($s < 0.06) {
            $a = $c = $s = 0.0; // تنظيف ضجيج الخلفية
        }
        $mix[$y][$x] = [$a, $c, $s];
    }
}

// حدود القص (الإطار)
$minX = $W; $minY = $H; $maxX = 0; $maxY = 0;
foreach ($mix as $y => $rowv) {
    foreach ($rowv as $x => [$a, $c, $s]) {
        if ($s > 0.5) {
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }
    }
}
$pad = 2;
$minX = max(0, $minX - $pad); $minY = max(0, $minY - $pad);
$maxX = min($W - 1, $maxX + $pad); $maxY = min($H - 1, $maxY + $pad);
$cw = $maxX - $minX + 1;
$ch = $maxY - $minY + 1;

function render(array $mix, int $x0, int $y0, int $w, int $h, array $sq, callable $colors)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$a, $c, $s] = $mix[$y + $y0][$x + $x0];
            if ($s <= 0) {
                continue;
            }
            $inSq = ($x + $x0) >= $sq[0] && ($x + $x0) <= $sq[2] && ($y + $y0) >= $sq[1] && ($y + $y0) <= $sq[3];
            [$cg, $cb] = $colors($inSq);
            $r = (int)round(($a * $cg[0] + $c * $cb[0]) / $s);
            $gr = (int)round(($a * $cg[1] + $c * $cb[1]) / $s);
            $bl = (int)round(($a * $cg[2] + $c * $cb[2]) / $s);
            $alpha = 127 - (int)round(min(1, $s) * 127);
            imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, $r, $gr, $bl, $alpha));
        }
    }
    return $im;
}

$light = render($mix, $minX, $minY, $cw, $ch, $SQ, fn($in) => $in ? [$NAVY, $WHITE] : [$NAVY, $BL]);
$dark = render($mix, $minX, $minY, $cw, $ch, $SQ, fn($in) => [[226, 228, 232], [24, 132, 200]]);
imagepng($light, "$root/assets/img/logo.png", 9);
imagepng($dark, "$root/assets/img/logo-dark.png", 9);
echo "logo.png / logo-dark.png: {$cw}x{$ch}\n";

// أيقونة مربع 24
$sx = $SQ[0] - $minX + 3; $sy = $SQ[1] - $minY + 3;
$sw = $SQ[2] - $SQ[0] - 6; $sh = $SQ[3] - $SQ[1] - 6;
foreach ([512, 192, 48] as $size) {
    $ic = imagecreatetruecolor($size, $size);
    imagefill($ic, 0, 0, imagecolorallocate($ic, ...$NAVY));
    imagealphablending($ic, true);
    $inner = (int)round($size * 0.86);
    $scale = $inner / max($sw, $sh);
    $dw = (int)round($sw * $scale); $dh = (int)round($sh * $scale);
    imagecopyresampled($ic, $light, (int)(($size - $dw) / 2), (int)(($size - $dh) / 2), $sx, $sy, $dw, $dh, $sw, $sh);
    imagepng($ic, $size === 48 ? "$root/assets/img/favicon-48.png" : "$root/assets/img/logo-$size.png", 9);
}
echo "icons ok\n";

// صورة المشاركة الافتراضية
$og = imagecreatetruecolor(1200, 630);
for ($y = 0; $y < 630; $y++) {
    $t = $y / 630;
    imageline($og, 0, $y, 1200, $y, imagecolorallocate($og, (int)(0 + 2 * $t), (int)(36 - 18 * $t), (int)(66 - 30 * $t)));
}
imagealphablending($og, true);
$tw = 760;
$th = (int)round($ch * $tw / $cw);
imagecopyresampled($og, $dark, (int)((1200 - $tw) / 2), (int)((630 - $th) / 2) - 20, 0, 0, $tw, $th, $cw, $ch);
imagefilledrectangle($og, 0, 606, 1200, 630, imagecolorallocate($og, ...$BL));
$font = null;
foreach (['C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $f) {
    if (is_file($f)) { $font = $f; break; }
}
if ($font) {
    $bbx = imagettfbbox(24, 0, $font, 'JANOUB24.COM');
    imagettftext($og, 24, 0, 600 - (int)(($bbx[2] - $bbx[0]) / 2), 540, imagecolorallocate($og, 150, 175, 205), $font, 'JANOUB24.COM');
}
imagejpeg($og, "$root/assets/img/og-default.jpg", 90);
echo "og-default ok\n";
