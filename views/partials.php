<?php
declare(strict_types=1);

/** شارة القسم */
function badge(array $a): string
{
    $live = ($a['type'] ?? '') === 'live' && !empty($a['live_open']) ? '<span class="live-pill"><span class="live-dot"></span>مباشر</span>' : '';
    return $live . '<a class="badge" style="--c:' . e($a['cat_color'] ?? '#0b6fae') . '" href="' . e(category_url($a['cat_slug'])) . '">' . e($a['cat_name']) . '</a>';
}

function card_time(array $a): string
{
    return '<time datetime="' . e(iso($a['published_at'])) . '" class="t">' . icon('clock', 13) . ' ' . e(time_ago($a['published_at'])) . '</time>';
}

/**
 * بطاقة خبر بأنماط متعددة:
 * hero | overlay | std | row | text | video
 */
function card(array $a, string $v = 'std', array $o = []): string
{
    $u = e(article_url($a));
    $t = e($a['title']);
    $tag = $o['h'] ?? ($v === 'hero' ? 'h2' : 'h3');
    $play = $a['type'] === 'video' ? '<span class="play" aria-hidden="true">' . icon('play', 22) . '</span>' : '';
    $sizes = $o['sizes'] ?? '(max-width: 768px) 100vw, 400px';
    $eager = !empty($o['eager']);

    switch ($v) {
        case 'hero':
        case 'overlay':
            return '<article class="card card-' . $v . '"><div class="card-media">' . img_tag($a['image'], $a['title'], $sizes, $eager) . $play . '</div>'
                . '<div class="card-over">' . badge($a) . "<$tag class=\"card-title\"><a href=\"$u\" class=\"stretched\">$t</a></$tag>"
                . ($v === 'hero' && $a['excerpt'] ? '<p class="card-ex">' . e(excerpt($a['excerpt'], 170)) . '</p>' : '')
                . card_time($a) . '</div></article>';

        case 'row':
            return '<article class="card card-row"><a class="card-media" href="' . $u . '" tabindex="-1" aria-hidden="true">' . img_tag($a['image'], $a['title'], '160px') . $play . '</a>'
                . "<div class=\"card-body\"><$tag class=\"card-title\"><a href=\"$u\">$t</a></$tag>" . card_time($a) . '</div></article>';

        case 'text':
            return '<article class="card card-text">' . (!empty($o['badge']) ? badge($a) : '')
                . "<$tag class=\"card-title\"><a href=\"$u\">$t</a></$tag>" . card_time($a) . '</article>';

        case 'opinion':
            $av = $a['author_avatar'] ? '<img src="' . e(img_url($a['author_avatar'])) . '" alt="' . e($a['author_name']) . '" width="72" height="72" loading="lazy">' : '<span class="av-ph">' . e(mb_substr($a['author_name'] ?? 'ج', 0, 1)) . '</span>';
            return '<article class="card card-opinion"><a class="op-av" href="' . e(author_url($a)) . '">' . $av . '</a>'
                . '<a class="op-name" href="' . e(author_url($a)) . '">' . e($a['author_name'] ?? site_name()) . '</a>'
                . "<$tag class=\"card-title\"><a href=\"$u\">$t</a></$tag>" . card_time($a) . '</article>';

        default: // std
            return '<article class="card card-std"><a class="card-media" href="' . $u . '" tabindex="-1" aria-hidden="true">' . img_tag($a['image'], $a['title'], $sizes, $eager) . $play . '</a>'
                . '<div class="card-body">' . badge($a) . "<$tag class=\"card-title\"><a href=\"$u\">$t</a></$tag>"
                . (!empty($o['excerpt']) && $a['excerpt'] ? '<p class="card-ex">' . e(excerpt($a['excerpt'], 130)) . '</p>' : '')
                . card_time($a) . '</div></article>';
    }
}

function section_head(string $title, ?string $link = null, ?string $color = null, string $tag = 'h2'): string
{
    $style = $color ? ' style="--c:' . e($color) . '"' : '';
    $more = $link ? '<a class="more" href="' . e($link) . '">المزيد ' . icon('chevron', 14) . '</a>' : '';
    return '<div class="sec-head"' . $style . "><$tag>" . ($link ? '<a href="' . e($link) . '">' . e($title) . '</a>' : e($title)) . "</$tag>$more</div>";
}

function most_read_box(array $list): string
{
    if (!$list) {
        return '';
    }
    $h = '<section class="box most-read">' . section_head('الأكثر قراءة', null, null, 'h2') . '<ol>';
    foreach ($list as $i => $a) {
        $h .= '<li><span class="n">' . ($i + 1) . '</span><div><a href="' . e(article_url($a)) . '">' . e($a['title']) . '</a>' . card_time($a) . '</div></li>';
    }
    return $h . '</ol></section>';
}

function follow_box(): string
{
    $links = social_links();
    $h = '<section class="box follow">' . section_head('تابعونا') . '<div class="follow-grid">';
    foreach ($links as $k => $s) {
        $h .= '<a class="fb-' . e($k) . '" href="' . e($s['url']) . '" target="_blank" rel="noopener">' . icon($k, 20) . '<span>' . e($s['label']) . '</span></a>';
    }
    $h .= '</div>';
    if ($gn = setting('google_news_url')) {
        $h .= '<a class="gnews" href="' . e($gn) . '" target="_blank" rel="noopener">تابعونا على Google News</a>';
    }
    return $h . '</section>';
}

function pagination(?array $pg): string
{
    if (!$pg || $pg['pages'] < 2) {
        return '';
    }
    $p = $pg['page'];
    $h = '<nav class="pager" aria-label="ترقيم الصفحات">';
    if ($p > 1) {
        $h .= '<a href="' . e(($pg['link'])($p - 1)) . '" rel="prev">السابق</a>';
    }
    $start = max(1, $p - 2);
    $end = min($pg['pages'], $p + 2);
    for ($i = $start; $i <= $end; $i++) {
        $h .= $i === $p ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . e(($pg['link'])($i)) . '">' . $i . '</a>';
    }
    if ($p < $pg['pages']) {
        $h .= '<a href="' . e(($pg['link'])($p + 1)) . '" rel="next">التالي</a>';
    }
    return $h . '</nav>';
}

function breadcrumbs_html(): string
{
    if (!Seo::$breadcrumbs) {
        return '';
    }
    $h = '<nav class="crumbs" aria-label="مسار التنقل"><a href="' . e(url()) . '">الرئيسية</a>';
    foreach (Seo::$breadcrumbs as [$name, $u]) {
        $h .= '<span>/</span>' . ($u ? '<a href="' . e($u) . '">' . e($name) . '</a>' : '<span class="cur">' . e(mb_strimwidth($name, 0, 60, '…')) . '</span>');
    }
    return $h . '</nav>';
}

/** صندوق «أسعار اليوم» (يُحدَّث من الإعدادات) */
function prices_box(): string
{
    $items = daily_prices();
    if (!$items) {
        return '';
    }
    $ico = [
        'usd'  => '<path d="M12 3v18M16.5 7.5c0-1.9-2-3-4.5-3s-4.5 1.2-4.5 3.2c0 4.6 9 2.4 9 7.1 0 2-2 3.2-4.5 3.2s-4.5-1.1-4.5-3" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
        'fuel' => '<path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16M3 21h13M7 8h5M15 10h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V9l-3-3" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'gas'  => '<path d="M8 7h8a3 3 0 0 1 3 3v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-9a3 3 0 0 1 3-3zM10 7V4h4v3M5 13h14" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/>',
    ];
    $h = '<section class="box prices">' . section_head('أسعار اليوم في لبنان') . '<ul>';
    foreach ($items as $it) {
        $h .= '<li class="pr-' . e($it['ico']) . '"><span class="pr-ico"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">' . $ico[$it['ico']] . '</svg></span>'
            . '<span class="pr-label">' . e($it['label']) . '</span><b class="pr-val">' . e($it['value']) . ' <small>' . e($it['unit']) . '</small></b></li>';
    }
    $h .= '</ul>';
    if ($up = setting('prices_updated')) {
        $h .= '<p class="pr-upd">' . icon('clock', 12) . ' آخر تحديث: ' . e(ar_date($up)) . '</p>';
    }
    return $h . '</section>';
}

/**
 * توهّج نقاط الشعار: ثلاث نقاط فوق مواقع نقاط «جنوب» الزرقاء في logo.png (656×170)
 * ترتيب الإضاءة من اليمين لليسار: ج ← ن ← ب
 */
function logo_glow(): string
{
    $dots = [[82.21, 71.28, 0], [71.84, 25.87, 0.35], [46.31, 71.33, 0.7]];
    $h = '<span class="logo-glow" aria-hidden="true">';
    foreach ($dots as [$x, $y, $d]) {
        $h .= '<i style="--x:' . $x . '%;--y:' . $y . '%;--dl:' . $d . 's"></i>';
    }
    return $h . '</span>';
}
