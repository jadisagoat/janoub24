<?php
declare(strict_types=1);

/**
 * أقضية الجنوب اللبناني (محافظتا الجنوب والنبطية)
 * - towns: بلدات تُطابَق ككلمات كاملة لتحديد قضاء الخبر تلقائياً
 * - phrases: كلمات ملتبسة (مثل «صور» = صورٌ فوتوغرافية) لا تُعتمد إلا ضمن عبارة واضحة
 * - hex: موقع الخلية في الخريطة التفاعلية (تقريبي، الغرب يساراً)
 * التحديد التلقائي اقتراح؛ يمكن للمحرر تغييره من محرر الخبر.
 */
const REGIONS = [
    'saida' => [
        'name' => 'صيدا', 'full' => 'قضاء صيدا والزهراني', 'gov' => 'محافظة الجنوب',
        'lat' => 33.5571, 'lng' => 35.3729, 'hex' => [190, 90],
        'towns' => ['صيدا', 'الزهراني', 'عين الحلوة', 'مغدوشة', 'عنقون', 'الغازية', 'الصرفند', 'عدلون', 'البيسارية', 'حارة صيدا', 'عبرا', 'الهلالية', 'درب السيم', 'المية ومية', 'انصارية', 'البابلية', 'كفرحتى', 'السكسكية'],
        'phrases' => [],
    ],
    'jezzine' => [
        'name' => 'جزين', 'full' => 'قضاء جزين', 'gov' => 'محافظة الجنوب',
        'lat' => 33.5450, 'lng' => 35.5843, 'hex' => [322, 74],
        'towns' => ['جزين', 'كفرحونة', 'بكاسين', 'عين مجدلين', 'لبعا', 'كفرجرة', 'مشموشة', 'صفاريه', 'عرمتى', 'روم جزين'],
        'phrases' => ['بلدة روم'],
    ],
    'sour' => [
        'name' => 'صور', 'full' => 'قضاء صور', 'gov' => 'محافظة الجنوب',
        'lat' => 33.2705, 'lng' => 35.2038, 'hex' => [118, 222],
        'towns' => ['الناقورة', 'قانا', 'العباسية', 'جويا', 'صديقين', 'طيردبا', 'البازورية', 'برج الشمالي', 'البرج الشمالي', 'الشعيتية', 'علما الشعب', 'الضهيرة', 'يارين', 'مروحين', 'المنصوري', 'دير قانون', 'البياضة', 'شمع', 'طير حرفا', 'زبقين', 'مجدل زون', 'القليلة', 'الحنية', 'الرشيدية', 'رأس الناقورة', 'باريش', 'معروب', 'دردغيا', 'حناويه', 'الشهابية', 'عيتيت', 'وادي جيلو', 'صريفا', 'دير قانون النهر', 'طورا', 'عين بعال', 'عيتا الجبل'],
        'phrases' => ['مدينة صور', 'قضاء صور', 'بلدية صور', 'منطقة صور', 'اتحاد بلديات صور', 'مستشفى صور', 'ميناء صور', 'شاطئ صور', 'سهل صور', 'محيط صور', 'مخيمات صور', 'جنوب صور', 'شرق صور', 'صور الجنوبية', 'بلدة معركة', 'مخيم البص'],
    ],
    'nabatieh' => [
        'name' => 'النبطية', 'full' => 'قضاء النبطية', 'gov' => 'محافظة النبطية',
        'lat' => 33.3772, 'lng' => 35.4836, 'hex' => [256, 196],
        'towns' => ['النبطية', 'كفررمان', 'حبوش', 'زوطر', 'زوطر الشرقية', 'زوطر الغربية', 'يحمر الشقيف', 'أرنون', 'ميفدون', 'الدوير', 'جبشيت', 'كفرتبنيت', 'عربصاليم', 'جرجوع', 'كفرصير', 'حاروف', 'زبدين', 'تول', 'القصيبة', 'النبطية الفوقا', 'عبا', 'إقليم التفاح', 'الشقيف', 'قلعة الشقيف', 'ميفدون', 'شوكين', 'كفرفيلا', 'جباع', 'عين قانا', 'دير الزهراني', 'حومين', 'صربا', 'كفروة', 'المروانية'],
        'phrases' => ['بلدة أنصار'],
    ],
    'hasbaya' => [
        'name' => 'حاصبيا', 'full' => 'قضاء حاصبيا', 'gov' => 'محافظة النبطية',
        'lat' => 33.3986, 'lng' => 35.6857, 'hex' => [452, 166],
        'towns' => ['حاصبيا', 'شبعا', 'كفرشوبا', 'الهبارية', 'كفرحمام', 'راشيا الفخار', 'العرقوب', 'شويا', 'عين قنيا', 'الفرديس', 'ميمس', 'كوكبا', 'حلتا', 'مزارع شبعا', 'تلال كفرشوبا', 'الماري', 'المجيدية'],
        'phrases' => [],
    ],
    'marjayoun' => [
        'name' => 'مرجعيون', 'full' => 'قضاء مرجعيون', 'gov' => 'محافظة النبطية',
        'lat' => 33.3600, 'lng' => 35.5917, 'hex' => [352, 300],
        'towns' => ['مرجعيون', 'كفركلا', 'العديسة', 'مركبا', 'حولا', 'ميس الجبل', 'بليدا', 'رب ثلاثين', 'دير ميماس', 'القليعة', 'برج الملوك', 'طلوسة', 'محيبيب', 'قبريخا', 'الصوانة', 'مجدل سلم', 'دير سريان', 'عدشيت القصير', 'إبل السقي', 'بني حيان', 'تولين', 'الوزاني', 'سهل مرجعيون', 'سهل الخيام', 'معتقل الخيام'],
        'phrases' => ['بلدة الخيام', 'مدينة الخيام', 'أطراف الخيام', 'محيط الخيام', 'أطراف بلدة الخيام', 'الخيام الجنوبية', 'بلدة الطيبة', 'الطيبة الجنوبية'],
    ],
    'bint-jbeil' => [
        'name' => 'بنت جبيل', 'full' => 'قضاء بنت جبيل', 'gov' => 'محافظة النبطية',
        'lat' => 33.1203, 'lng' => 35.4335, 'hex' => [214, 336],
        'towns' => ['بنت جبيل', 'عيترون', 'مارون الراس', 'عيتا الشعب', 'يارون', 'رميش', 'تبنين', 'حاريص', 'بيت ليف', 'كونين', 'الطيري', 'عيناثا', 'برعشيت', 'صفد البطيخ', 'شقرا', 'دبل', 'رامية', 'القوزح', 'ياطر', 'كفرا', 'صربين', 'بيت ياحون', 'عين إبل', 'السلطانية', 'خربة سلم', 'قلاوية', 'عيتا', 'حداثا', 'الجميجمة', 'بيت لاف', 'كفردونين', 'دير انطار', 'برج قلاوية'],
        'phrases' => [],
    ],
];

function regions(): array
{
    return REGIONS;
}

function region(?string $slug): ?array
{
    return $slug && isset(REGIONS[$slug]) ? REGIONS[$slug] + ['slug' => $slug] : null;
}

function region_url(string $slug): string
{
    return url('region/' . $slug);
}

/** أنماط المطابقة (مُخزّنة مؤقتاً) — كلمة كاملة مع حروف الجر/العطف الملتصقة */
function region_patterns(): array
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }
    $p = [];
    foreach (REGIONS as $slug => $r) {
        $alts = [];
        foreach (array_merge($r['towns'], $r['phrases']) as $kw) {
            $alts[] = preg_quote(ar_normalize($kw), '~');
        }
        usort($alts, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $p[$slug] = '~(?<![\p{L}\p{N}])(?:[وبلف]|ول|وب|لل)?(' . implode('|', $alts) . ')(?![\p{L}\p{N}])~u';
    }
    return $p;
}

/**
 * يحدد قضاء الخبر من العنوان والنص.
 * العنوان بوزن 3، المقدمة بوزن 2، النص بوزن 1. لا يُعتمد ذكر عابر واحد في النص.
 */
function detect_region(string $title, string $excerpt = '', string $body = ''): ?string
{
    $parts = [[ar_normalize($title), 3], [ar_normalize($excerpt), 2], [ar_normalize(mb_substr(plain_text($body), 0, 4000)), 1]];
    $scores = [];
    foreach (region_patterns() as $slug => $re) {
        $s = 0;
        foreach ($parts as [$txt, $w]) {
            if ($txt !== '') {
                $s += $w * preg_match_all($re, $txt);
            }
        }
        // «صور:» في بداية العنوان = اسم المدينة كمكان الخبر
        if ($slug === 'sour' && preg_match('~^\s*صور\s*[:،-]~u', ar_normalize($title))) {
            $s += 3;
        }
        if ($s >= 2) {
            $scores[$slug] = $s;
        }
    }
    if (!$scores) {
        return null;
    }
    arsort($scores);
    return array_key_first($scores);
}

/** عدد أخبار كل قضاء (مع عدد آخر 24 ساعة للخريطة) */
function region_stats(): array
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $s = [];
    foreach (rows("SELECT a.region, COUNT(*) n, SUM(a.published_at >= NOW() - INTERVAL 24 HOUR) fresh FROM articles a
        WHERE a.region IS NOT NULL AND " . ART_LIVE . " GROUP BY a.region") as $r) {
        $s[$r['region']] = ['n' => (int)$r['n'], 'fresh' => (int)$r['fresh']];
    }
    return $s;
}

/** خريطة الأقضية التفاعلية (SVG سداسي تقريبي) */
function region_map(?string $active = null, bool $tabs = false): string
{
    $stats = region_stats();
    $max = max(1, ...array_values(array_map(static fn($x) => $x['n'], $stats ?: [['n' => 1]])));
    $hex = static function (float $cx, float $cy, float $r): string {
        $pts = [];
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad(60 * $i - 30);
            $pts[] = round($cx + $r * cos($a), 1) . ',' . round($cy + $r * sin($a), 1);
        }
        return implode(' ', $pts);
    };
    $h = '<svg class="rmap" viewBox="0 0 560 420" role="img" aria-label="خريطة أقضية جنوب لبنان">';
    $h .= '<defs><pattern id="waves" width="28" height="14" patternUnits="userSpaceOnUse"><path d="M0 7 q7 -6 14 0 t14 0" fill="none" stroke="currentColor" stroke-width="1.2" opacity=".35"/></pattern></defs>';
    $h .= '<rect x="0" y="0" width="64" height="420" fill="url(#waves)" class="rmap-sea"/>';
    $h .= '<text x="30" y="215" class="rmap-sea-t" transform="rotate(-90 30 215)">البحر المتوسط</text>';
    $h .= '<line x1="70" y1="402" x2="520" y2="402" class="rmap-border"/><text x="300" y="417" class="rmap-border-t">الحدود الجنوبية</text>';
    foreach (REGIONS as $slug => $r) {
        [$cx, $cy] = $r['hex'];
        $st = $stats[$slug] ?? ['n' => 0, 'fresh' => 0];
        $lvl = $st['n'] ? 0.25 + 0.75 * ($st['n'] / $max) : 0.08;
        $cls = 'rmap-cell' . ($active === $slug ? ' is-active' : '') . ($st['fresh'] ? ' is-fresh' : '');
        $attr = $tabs ? ' data-tab="' . e($slug) . '" role="button" tabindex="0"' : '';
        $h .= '<a href="' . e(region_url($slug)) . '" class="' . $cls . '" style="--lvl:' . round($lvl, 2) . '"' . $attr . ' aria-label="' . e($r['full'] . ' — ' . $st['n'] . ' خبر') . '">';
        $h .= '<polygon points="' . $hex($cx, $cy, 66) . '" class="rmap-hex"/>';
        if ($st['fresh']) {
            $h .= '<circle cx="' . ($cx + 36) . '" cy="' . ($cy - 34) . '" r="6" class="rmap-ping"/><circle cx="' . ($cx + 36) . '" cy="' . ($cy - 34) . '" r="6" class="rmap-dot"/>';
        }
        $h .= '<text x="' . $cx . '" y="' . ($cy - 2) . '" class="rmap-name">' . e($r['name']) . '</text>';
        $h .= '<text x="' . $cx . '" y="' . ($cy + 22) . '" class="rmap-n">' . $st['n'] . ' خبر</text>';
        $h .= '</a>';
    }
    return $h . '</svg>';
}
