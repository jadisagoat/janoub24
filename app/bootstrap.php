<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

$GLOBALS['config'] = require ROOT . '/config.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Asia/Beirut');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

require ROOT . '/app/db.php';
require ROOT . '/app/helpers.php';
require ROOT . '/app/images.php';
require ROOT . '/app/models.php';
require ROOT . '/app/regions.php';
require ROOT . '/app/seo.php';
require ROOT . '/app/cache.php';
require ROOT . '/app/migrate.php';

maybe_migrate();
