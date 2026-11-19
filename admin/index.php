<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib.php';
require __DIR__ . '/controllers.php';

security_headers(true);
admin_session();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$p = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard')) ?: 'dashboard';

if ($p === 'login') {
    a_login();
    exit;
}
if ($p === 'logout') {
    a_logout();
}

$me = current_user();
if (!$me) {
    redirect(admin_url('login'));
}

$routes = [
    'dashboard'    => 'a_dashboard',
    'articles'     => 'a_articles',
    'article'      => 'a_article_form',
    'article_del'  => 'a_article_delete',
    'upload'       => 'a_upload',
    'categories'   => 'a_categories',
    'breaking'     => 'a_breaking',
    'pages'        => 'a_pages',
    'page'         => 'a_page_form',
    'users'        => 'a_users',
    'user'         => 'a_user_form',
    'profile'      => 'a_profile',
    'settings'     => 'a_settings',
    'tools'        => 'a_tools',
    'live'         => 'a_live',
    'seo'          => 'a_seo',
];
if (!isset($routes[$p])) {
    http_response_code(404);
    $p = 'dashboard';
}
$routes[$p]();
