<?php
declare(strict_types=1);

/* ============ الجلسة والصلاحيات ============ */

function admin_session(): void
{
    session_name('j24_admin');
    ini_set('session.use_strict_mode', '1');   // يرفض معرّفات جلسة مزوّرة
    ini_set('session.use_only_cookies', '1');
    $secure = str_starts_with(base_url(), 'https://');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure]);
    session_start();
    // انتهاء الجلسة بعد 8 ساعات خمول
    if (!empty($_SESSION['uid']) && isset($_SESSION['seen']) && time() - (int)$_SESSION['seen'] > 8 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

function admin_url(string $p = 'dashboard', array $q = []): string
{
    return base_url() . '/admin/?' . http_build_query(array_merge(['p' => $p], $q));
}

function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = !empty($_SESSION['uid']) ? row('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['uid']]) : null;
    }
    return $u;
}

/** الصلاحيات: admin كل شيء — editor التحرير والنشر — writer أخباره فقط دون نشر */
function can(string $perm): bool
{
    $role = current_user()['role'] ?? '';
    $map = [
        'publish'    => ['admin', 'editor'],
        'all_articles' => ['admin', 'editor'],
        'categories' => ['admin', 'editor'],
        'breaking'   => ['admin', 'editor'],
        'pages'      => ['admin', 'editor'],
        'users'      => ['admin'],
        'settings'   => ['admin'],
    ];
    return in_array($role, $map[$perm] ?? [], true);
}

function require_can(string $perm): void
{
    if (!can($perm)) {
        http_response_code(403);
        admin_render('ليس لديك صلاحية', '<div class="alert err">ليس لديك صلاحية للوصول إلى هذه الصفحة.</div>');
        exit;
    }
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf(), (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''))) {
        http_response_code(403);
        exit('انتهت صلاحية الجلسة، أعد تحميل الصفحة.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function post(string $k, string $d = ''): string
{
    return trim((string)($_POST[$k] ?? $d));
}

/* ============ محاولات الدخول ============ */

function login_attempts_file(): string
{
    return ROOT . '/storage/login_attempts.json';
}

function login_blocked(string $ip): bool
{
    $data = json_decode((string)@file_get_contents(login_attempts_file()), true) ?: [];
    $rec = $data[$ip] ?? null;
    return $rec && $rec['n'] >= 5 && $rec['t'] > time() - 900;
}

function login_failed(string $ip): void
{
    $f = login_attempts_file();
    $data = json_decode((string)@file_get_contents($f), true) ?: [];
    $rec = $data[$ip] ?? ['n' => 0, 't' => time()];
    if ($rec['t'] < time() - 900) {
        $rec = ['n' => 0, 't' => time()];
    }
    $rec['n']++;
    $rec['t'] = time();
    $data[$ip] = $rec;
    @file_put_contents($f, json_encode($data), LOCK_EX);
}

function login_reset(string $ip): void
{
    $f = login_attempts_file();
    $data = json_decode((string)@file_get_contents($f), true) ?: [];
    unset($data[$ip]);
    @file_put_contents($f, json_encode($data), LOCK_EX);
}

/* ============ العرض ============ */

function admin_render(string $title, string $html): void
{
    $me = current_user();
    $flash = flash();
    $p = $_GET['p'] ?? 'dashboard';
    require __DIR__ . '/layout.php';
}

function status_badge(string $s, ?string $pub = null): string
{
    if ($s === 'published' && $pub && ts($pub) > time()) {
        return '<span class="st st-sched">مجدول</span>';
    }
    return match ($s) {
        'published' => '<span class="st st-pub">منشور</span>',
        'pending'   => '<span class="st st-pend">بانتظار المراجعة</span>',
        default     => '<span class="st st-draft">مسودة</span>',
    };
}

function sel(array $opts, $current): string
{
    $h = '';
    foreach ($opts as $v => $label) {
        $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h;
}

function checked($v): string
{
    return $v ? ' checked' : '';
}

function admin_pager(int $total, int $per, int $page, array $q): string
{
    $pages = (int)ceil($total / $per);
    if ($pages < 2) {
        return '';
    }
    $route = $q['p'];
    unset($q['p']);
    $h = '<nav class="pager">';
    for ($i = max(1, $page - 4); $i <= min($pages, $page + 4); $i++) {
        $h .= $i === $page ? '<span>' . $i . '</span>' : '<a href="' . e(admin_url($route, array_merge($q, ['page' => $i]))) . '">' . $i . '</a>';
    }
    return $h . '</nav>';
}
