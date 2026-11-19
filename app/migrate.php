<?php
declare(strict_types=1);

/**
 * تحديث قاعدة البيانات تلقائياً بعد رفع نسخة جديدة من الموقع.
 * يعمل مرة واحدة عند أول زيارة (يُسجَّل الإصدار داخل قاعدة البيانات: settings.schema_version)، وكل خطوة آمنة للتكرار.
 */
const SCHEMA_VERSION = 2;

function maybe_migrate(): void
{
    $v = (int)setting('schema_version', '1');
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    $lock = @fopen(ROOT . '/storage/migrate.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return; // تحديث آخر قيد التنفيذ
    }
    try {
        if ($v < 2) {
            migrate_v2();
        }
        q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', ['schema_version', (string)SCHEMA_VERSION]);
    } catch (Throwable $ex) {
        error_log('janoub24 migrate: ' . $ex->getMessage());
        if (cfg('debug')) {
            throw $ex;
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function db_has_column(string $table, string $col): bool
{
    return (bool)val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $col]);
}

/** الإصدار 2: أقضية الجنوب + التغطية المباشرة + عناوين سيو الأقسام */
function migrate_v2(): void
{
    if (!db_has_column('articles', 'region')) {
        db()->exec('ALTER TABLE articles ADD region VARCHAR(30) NULL AFTER category_id, ADD KEY idx_region (region, status, published_at)');
    }
    if (!db_has_column('articles', 'live_open')) {
        db()->exec('ALTER TABLE articles ADD live_open TINYINT(1) NOT NULL DEFAULT 0 AFTER type');
    }
    db()->exec("ALTER TABLE articles MODIFY type ENUM('news','video','opinion','gallery','live') NOT NULL DEFAULT 'news'");
    db()->exec("CREATE TABLE IF NOT EXISTS live_updates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        article_id INT UNSIGNED NOT NULL,
        author_id INT UNSIGNED NULL,
        body TEXT NOT NULL,
        is_key TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_art (article_id, id),
        CONSTRAINT fk_lu_art FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
        CONSTRAINT fk_lu_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $titles = [
        'local' => 'أخبار الجنوب اللبناني اليوم - أخبار محلية عاجلة', 'politics' => 'أخبار لبنان السياسية اليوم',
        'security' => 'أخبار أمنية وقضائية في لبنان اليوم', 'world' => 'أخبار الشرق الأوسط والعالم اليوم',
        'economy' => 'أخبار الاقتصاد اللبناني وسعر الدولار اليوم', 'opinion' => 'مقالات رأي في الشأن اللبناني والإقليمي',
        'articles' => 'مقالات وتحليلات سياسية لبنانية', 'variety' => 'منوعات وثقافة ومجتمع من لبنان',
    ];
    foreach ($titles as $slug => $t) {
        q("UPDATE categories SET seo_title = ? WHERE slug = ? AND (seo_title IS NULL OR seo_title = '')", [$t, $slug]);
    }

    // تحديد قضاء الأخبار الموجودة
    foreach (rows('SELECT id, title, excerpt, body FROM articles WHERE region IS NULL') as $a) {
        $r = detect_region($a['title'], (string)$a['excerpt'], $a['body']);
        if ($r) {
            q('UPDATE articles SET region = ? WHERE id = ?', [$r, $a['id']]);
        }
    }
    cache_clear();
}
