SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS live_updates, article_tags, tags, breaking_news, articles, categories, pages, settings, users;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  slug          VARCHAR(140) NOT NULL UNIQUE,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','editor','writer') NOT NULL DEFAULT 'writer',
  job_title     VARCHAR(120) NULL,
  bio           TEXT NULL,
  avatar        VARCHAR(255) NULL,
  twitter       VARCHAR(190) NULL,
  facebook      VARCHAR(190) NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  last_login    DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  slug            VARCHAR(140) NOT NULL UNIQUE,
  description     VARCHAR(300) NULL,
  seo_title       VARCHAR(190) NULL,
  color           VARCHAR(9) NOT NULL DEFAULT '#c8102e',
  sort_order      INT NOT NULL DEFAULT 0,
  show_in_menu    TINYINT(1) NOT NULL DEFAULT 1,
  show_on_home    TINYINT(1) NOT NULL DEFAULT 1,
  home_layout     ENUM('lead','grid','list') NOT NULL DEFAULT 'lead',
  legacy_slug     VARCHAR(190) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id     INT UNSIGNED NOT NULL,
  region          VARCHAR(30) NULL,
  author_id       INT UNSIGNED NULL,
  title           VARCHAR(255) NOT NULL,
  subtitle        VARCHAR(255) NULL,
  slug            VARCHAR(255) NOT NULL,
  excerpt         VARCHAR(500) NULL,
  body            MEDIUMTEXT NOT NULL,
  image           VARCHAR(255) NULL,
  image_caption   VARCHAR(255) NULL,
  video_url       VARCHAR(255) NULL,
  type            ENUM('news','video','opinion','gallery','live') NOT NULL DEFAULT 'news',
  live_open       TINYINT(1) NOT NULL DEFAULT 0,
  status          ENUM('draft','pending','published') NOT NULL DEFAULT 'draft',
  is_featured     TINYINT(1) NOT NULL DEFAULT 0,
  is_breaking     TINYINT(1) NOT NULL DEFAULT 0,
  is_demo         TINYINT(1) NOT NULL DEFAULT 0,
  views           INT UNSIGNED NOT NULL DEFAULT 0,
  seo_title       VARCHAR(190) NULL,
  seo_description VARCHAR(300) NULL,
  focus_keyword   VARCHAR(120) NULL,
  source          VARCHAR(190) NULL,
  search_text     MEDIUMTEXT NULL,
  legacy_id       INT UNSIGNED NULL,
  legacy_slug     VARCHAR(255) NULL,
  published_at    DATETIME NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_live (status, published_at),
  KEY idx_cat (category_id, status, published_at),
  KEY idx_author (author_id, status, published_at),
  KEY idx_featured (is_featured, status, published_at),
  KEY idx_type (type, status, published_at),
  KEY idx_views (views),
  KEY idx_region (region, status, published_at),
  UNIQUE KEY uq_legacy_id (legacy_id),
  KEY idx_legacy_slug (legacy_slug(191)),
  CONSTRAINT fk_art_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_art_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name  VARCHAR(120) NOT NULL,
  slug  VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tags (
  article_id INT UNSIGNED NOT NULL,
  tag_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (article_id, tag_id),
  KEY idx_tag (tag_id),
  CONSTRAINT fk_at_art FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_at_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE live_updates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  article_id  INT UNSIGNED NOT NULL,
  author_id   INT UNSIGNED NULL,
  body        TEXT NOT NULL,
  is_key      TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_art (article_id, id),
  CONSTRAINT fk_lu_art FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
  CONSTRAINT fk_lu_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE breaking_news (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  text        VARCHAR(300) NOT NULL,
  article_id  INT UNSIGNED NULL,
  url         VARCHAR(255) NULL,
  expires_at  DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp (expires_at),
  CONSTRAINT fk_br_art FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(190) NOT NULL,
  slug            VARCHAR(140) NOT NULL UNIQUE,
  body            MEDIUMTEXT NOT NULL,
  seo_description VARCHAR(300) NULL,
  show_in_footer  TINYINT(1) NOT NULL DEFAULT 1,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  `key`   VARCHAR(80) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
