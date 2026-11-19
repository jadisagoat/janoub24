<?php
declare(strict_types=1);

/* ============ إعدادات وروابط ============ */

function cfg(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        try {
            $all = [];
            foreach (rows('SELECT `key`, `value` FROM settings') as $r) {
                $all[$r['key']] = (string)$r['value'];
            }
        } catch (Throwable $e) {
            $all = [];
        }
    }
    return ($all[$key] ?? '') !== '' ? $all[$key] : $default;
}

function site_name(): string
{
    return setting('site_name', 'جنوب 24');
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    return rtrim((string)cfg('site_url'), '/');
}

/** يبني رابطاً مطلقاً مع ترميز المقاطع العربية */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if ($path === '') {
        return base_url() . '/';
    }
    $parts = explode('?', $path, 2);
    $segs  = array_map(static fn($s) => rawurlencode(rawurldecode($s)), explode('/', $parts[0]));
    return base_url() . '/' . implode('/', $segs) . (isset($parts[1]) ? '?' . $parts[1] : '');
}

function asset(string $p): string
{
    $f = ROOT . '/assets/' . $p;
    return base_url() . '/assets/' . $p . (is_file($f) ? '?v=' . filemtime($f) : '');
}

/**
 * حزمة ملفات مضغوطة (CSS) تُبنى تلقائياً عند تعديل أي ملف مصدر:
 * طلب واحد بدل عدة طلبات، وحجم أصغر بنحو الربع.
 */
function bundle(string $out, array $files): string
{
    $target = ROOT . '/assets/' . $out;
    $mt = 0;
    foreach ($files as $f) {
        $mt = max($mt, (int)@filemtime(ROOT . '/assets/' . $f));
    }
    if (!is_file($target) || filemtime($target) < $mt) {
        $code = '';
        foreach ($files as $f) {
            $code .= file_get_contents(ROOT . '/assets/' . $f) . "\n";
        }
        if (str_ends_with($out, '.css')) {
            $code = preg_replace('~/\*.*?\*/~s', '', $code) ?? $code;
            $code = preg_replace('~\s+~', ' ', $code) ?? $code;
            $code = preg_replace('~\s*([{};,>])\s*~', '$1', $code) ?? $code;
            $code = preg_replace('~([{;]\s*[-a-z]+):\s+~', '$1:', $code) ?? $code;
            $code = str_replace(';}', '}', $code);
        }
        $tmp = $target . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, trim($code)) !== false) {
            @rename($tmp, $target);
        }
    }
    return base_url() . '/assets/' . $out . '?v=' . (int)@filemtime($target);
}

function article_url(array $a): string
{
    return url('news/' . $a['id'] . '/' . $a['slug']);
}

function category_url($c): string
{
    return url('category/' . (is_array($c) ? $c['slug'] : $c));
}

function tag_url($t): string
{
    return url('tag/' . (is_array($t) ? $t['slug'] : $t));
}

function author_url($u): string
{
    return url('author/' . (is_array($u) ? ($u['author_slug'] ?? $u['slug']) : $u));
}

function page_url(string $slug): string
{
    return url('page/' . $slug);
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

/* ============ نصوص عربية ============ */

/** إزالة التشكيل والتطويل وتوحيد الحروف للبحث */
function ar_normalize(string $s): string
{
    $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s) ?? $s;
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
    return mb_strtolower($s);
}

function slugify(string $s, int $max = 90): string
{
    $s = mb_strtolower(trim($s));
    $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s) ?? $s;
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? $s;
    $s = trim($s, '-');
    if (mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max);
        $cut = preg_replace('/-[^-]*$/u', '', $s);
        $s = $cut ?: $s;
    }
    return $s !== '' ? $s : 'news';
}

/** إزالة وسوم HTML مع الحفاظ على الفواصل بين الفقرات */
function plain_text(string $html): string
{
    $t = preg_replace('/<[^>]+>/', ' ', $html) ?? $html;
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES, 'UTF-8')) ?? '');
}

function excerpt(string $html, int $len = 160): string
{
    $t = plain_text($html);
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $t = mb_substr($t, 0, $len);
    $t = preg_replace('/\s+\S*$/u', '', $t) ?? $t;
    return $t . '…';
}

function word_count(string $html): int
{
    $t = plain_text($html);
    return $t === '' ? 0 : count(preg_split('/\s+/u', $t) ?: []);
}

function reading_time(string $html): string
{
    $m = max(1, (int)ceil(word_count($html) / 200));
    return ar_plural($m, 'دقيقة', 'دقيقتان', 'دقائق', 'دقيقة') . ' قراءة';
}

/** صيغ الجمع العربية: 1، 2، 3-10، 11+ */
function ar_plural(int $n, string $one, string $two, string $few, string $many): string
{
    if ($n === 1) {
        return $one;
    }
    if ($n === 2) {
        return $two;
    }
    if ($n >= 3 && $n <= 10) {
        return $n . ' ' . $few;
    }
    return $n . ' ' . $many;
}

const AR_MONTHS = [1 => 'كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'];
const AR_DAYS   = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

function ts($dt): int
{
    return is_numeric($dt) ? (int)$dt : (int)strtotime((string)$dt);
}

function ar_date($dt, bool $time = true): string
{
    $t = ts($dt);
    $s = date('j', $t) . ' ' . AR_MONTHS[(int)date('n', $t)] . ' ' . date('Y', $t);
    return $time ? $s . ' | ' . date('H:i', $t) : $s;
}

function ar_today(): string
{
    $t = time();
    return AR_DAYS[(int)date('w', $t)] . ' ' . ar_date($t, false);
}

function time_ago($dt): string
{
    $diff = time() - ts($dt);
    if ($diff < 60) {
        return 'الآن';
    }
    if ($diff < 3600) {
        return 'منذ ' . ar_plural(intdiv($diff, 60), 'دقيقة', 'دقيقتين', 'دقائق', 'دقيقة');
    }
    if ($diff < 86400) {
        return 'منذ ' . ar_plural(intdiv($diff, 3600), 'ساعة', 'ساعتين', 'ساعات', 'ساعة');
    }
    if ($diff < 7 * 86400) {
        return 'منذ ' . ar_plural(intdiv($diff, 86400), 'يوم', 'يومين', 'أيام', 'يوماً');
    }
    return ar_date($dt, false);
}

function iso($dt): string
{
    return date('c', ts($dt));
}

function short_number(int $n): string
{
    if ($n >= 1000000) {
        return round($n / 1000000, 1) . 'M';
    }
    if ($n >= 1000) {
        return round($n / 1000, 1) . 'K';
    }
    return (string)$n;
}

/* ============ يوتيوب ============ */

function youtube_id(?string $url): ?string
{
    if (!$url) {
        return null;
    }
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return preg_match('~^[A-Za-z0-9_-]{11}$~', $url) ? $url : null;
}

/* ============ تنقية HTML لمحتوى المقالات ============ */

/** يقبل فقط: روابط http/https/mailto/tel أو نسبية أو {{base}} — يرفض javascript: وأشباهها حتى مع التمويه */
function safe_url(string $v): bool
{
    $v = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';
    if ($v === '' || $v[0] === '/' || $v[0] === '#' || $v[0] === '?' || str_starts_with($v, '{{base}}')) {
        return true;
    }
    if (!preg_match('~^([a-z][a-z0-9+.-]*):~i', $v, $m)) {
        return true; // رابط نسبي
    }
    return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true);
}

function clean_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'img', 'figure', 'figcaption', 'iframe', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'span', 'hr', 'pre', 'code'];
    $attrs = ['href', 'src', 'alt', 'title', 'width', 'height', 'class', 'target', 'rel', 'allowfullscreen', 'frameborder', 'colspan', 'rowspan', 'loading'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) {
        return strip_tags($html, '<p><br><strong><em><h2><h3><ul><ol><li><blockquote>');
    }

    $walk = function (DOMNode $node) use (&$walk, $allowed, $attrs) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'meta', 'link', 'svg', 'math', 'template'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (!in_array($tag, $allowed, true)) {
                    $walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $v = trim($attr->value);
                    if (!in_array($name, $attrs, true) || (in_array($name, ['href', 'src'], true) && !safe_url($v))) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'iframe') {
                    $src = $child->getAttribute('src');
                    if (!preg_match('~^https://(www\.)?(youtube\.com|youtube-nocookie\.com|player\.vimeo\.com|www\.facebook\.com)/~', $src)) {
                        $node->removeChild($child);
                        continue;
                    }
                    $child->setAttribute('loading', 'lazy');
                }
                if ($tag === 'a') {
                    $href = $child->getAttribute('href');
                    if ($href !== '' && !str_starts_with($href, base_url()) && preg_match('~^https?://~', $href)) {
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener');
                    }
                }
                if ($tag === 'img') {
                    $child->setAttribute('loading', 'lazy');
                }
                $walk($child);
            }
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    // saveHTML يرمّز الأقواس داخل الروابط؛ نعيد العنصر النائب لعنوان الموقع كما هو
    return str_replace('%7B%7Bbase%7D%7D', '{{base}}', $out);
}

/* ============ روابط محمولة داخل نص المقال ============ */
// تُحفظ روابط الصور المحلية بصيغة {{base}}/uploads/... حتى لا تنكسر عند تغيير النطاق

function body_store(string $html): string
{
    return str_replace([base_url() . '/uploads/', 'src="/uploads/'], ['{{base}}/uploads/', 'src="{{base}}/uploads/'], $html);
}

function body_render(string $html): string
{
    return str_replace('{{base}}', base_url(), $html);
}

/* ============ أيقونات SVG ============ */

function icon(string $name, int $size = 18): string
{
    $p = [
        'facebook'  => '<path d="M14 8.5V6.6c0-.9.6-1.1 1-1.1h2.6V1.6L14 1.5c-4 0-4.9 3-4.9 4.9v2.1H6.7v4h2.4V22.5H14V12.5h3.3l.4-4z" fill="currentColor"/>',
        'x'         => '<path d="M17.8 2h3.3l-7.2 8.3 8.5 11.2h-6.7l-5.2-6.8-6 6.8H1.2l7.7-8.8L.8 2h6.8l4.7 6.2L17.8 2zm-1.1 17.6h1.8L6.9 3.9H4.9l11.8 15.7z" fill="currentColor"/>',
        'youtube'   => '<path d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12c0 1.6.2 3.2.5 4.8a3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1c.3-1.6.5-3.2.5-4.8s-.2-3.2-.5-4.8zM9.7 15V9l5.8 3-5.8 3z" fill="currentColor"/>',
        'whatsapp'  => '<path d="M12 1.5A10.4 10.4 0 0 0 3 17.2L1.5 22.5l5.5-1.4A10.4 10.4 0 1 0 12 1.5zm0 19a8.6 8.6 0 0 1-4.4-1.2l-.3-.2-3.3.9.9-3.2-.2-.3A8.6 8.6 0 1 1 12 20.5zm4.7-6.4c-.3-.1-1.5-.8-1.8-.8-.2-.1-.4-.1-.6.1l-.8 1c-.2.2-.3.2-.6.1a7 7 0 0 1-3.5-3c-.3-.5.3-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.8-1.2.2-.6.2-1.1.1-1.2l-.6-.4z" fill="currentColor"/>',
        'telegram'  => '<path d="M21.9 3.6 18.7 19c-.2 1.1-.9 1.4-1.8.9l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.3-5 9.2-8.3c.4-.4-.1-.6-.6-.2L6.2 12.8l-4.9-1.5c-1-.3-1.1-1 .2-1.5L20.6 2.4c.9-.3 1.6.2 1.3 1.2z" fill="currentColor"/>',
        'instagram' => '<path d="M12 2.2c3.2 0 3.6 0 4.8.1 3.2.1 4.8 1.7 4.9 4.9.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 3.9 3.9 2.4 7.2 2.3c1.2-.1 1.6-.1 4.8-.1zM12 6.9a5.1 5.1 0 1 0 0 10.2 5.1 5.1 0 0 0 0-10.2zm0 8.4a3.3 3.3 0 1 1 0-6.6 3.3 3.3 0 0 1 0 6.6zm5.3-9.8a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z" fill="currentColor"/>',
        'rss'       => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round"/><circle cx="5" cy="19" r="1.8" fill="currentColor"/>',
        'search'    => '<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2.2" fill="none"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/>',
        'clock'     => '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
        'eye'       => '<path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12z" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" fill="none"/>',
        'play'      => '<path d="M8 5.5v13l11-6.5z" fill="currentColor"/>',
        'link'      => '<path d="M10 14a4 4 0 0 0 5.7 0l3.5-3.5a4 4 0 0 0-5.7-5.7L12 6.3M14 10a4 4 0 0 0-5.7 0l-3.5 3.5a4 4 0 0 0 5.7 5.7l1.5-1.5" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
        'bolt'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7z" fill="currentColor"/>',
        'chevron'   => '<path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2" fill="none"/><path d="m3 7 9 6 9-6" stroke="currentColor" stroke-width="2" fill="none"/>',
        'print'     => '<path d="M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z" stroke="currentColor" stroke-width="2" fill="none" stroke-linejoin="round"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($p[$name] ?? '') . '</svg>';
}

/** روابط حسابات التواصل المعرّفة في الإعدادات */
function social_links(): array
{
    $out = [];
    foreach (['facebook' => 'فيسبوك', 'x' => 'إكس', 'youtube' => 'يوتيوب', 'instagram' => 'إنستغرام', 'telegram' => 'تلغرام', 'whatsapp' => 'واتساب'] as $k => $label) {
        $u = setting('social_' . $k);
        if ($u !== '') {
            $out[$k] = ['url' => $u, 'label' => $label];
        }
    }
    return $out;
}

/** ترويسات الأمان (تعمل حتى لو كان mod_headers معطلاً على الاستضافة) */
function security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: ' . ($admin ? 'DENY' : 'SAMEORIGIN'));
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (str_starts_with(base_url(), 'https://')) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function is_admin_session(): bool
{
    return isset($_COOKIE['j24_admin']);
}
