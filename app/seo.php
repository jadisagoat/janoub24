<?php
declare(strict_types=1);

/**
 * محرك السيو: الوسوم الوصفية، Open Graph، بطاقات X، الروابط القانونية وبيانات Schema.org المنظمة
 */
final class Seo
{
    public static string $title = '';
    public static bool $rawTitle = false;
    public static string $description = '';
    public static string $canonical = '';
    public static string $image = '';
    public static string $type = 'website';
    public static string $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    public static array $schemas = [];
    public static array $meta = [];          // [property => content] أو [[property, content], ...]
    public static ?string $prev = null;
    public static ?string $next = null;
    public static ?string $preloadImage = null;
    public static ?string $preloadSrcset = null;
    public static ?string $preloadType = null;
    public static array $breadcrumbs = [];   // [[name, url], ...]

    public static function noindex(): void
    {
        self::$robots = 'noindex, follow';
    }

    public static function fullTitle(): string
    {
        if (self::$rawTitle || self::$title === '') {
            return self::$title !== '' ? self::$title : site_name() . ' | ' . setting('site_tagline', 'أخبار لبنان والجنوب والشرق الأوسط');
        }
        return self::$title . ' | ' . site_name();
    }

    public static function organization(): array
    {
        $sameAs = array_values(array_map(static fn($s) => $s['url'], social_links()));
        $org = [
            '@type' => 'NewsMediaOrganization',
            '@id'   => url() . '#organization',
            'name'  => site_name(),
            'alternateName' => setting('site_name_en', 'Janoub24'),
            'url'   => url(),
            'logo'  => [
                '@type'  => 'ImageObject',
                'url'    => base_url() . '/assets/img/logo.png',
                'width'  => 656,
                'height' => 170,
            ],
            'sameAs' => $sameAs,
            'foundingDate' => setting('founding_date', '2019'),
            'areaServed'   => ['@type' => 'Country', 'name' => 'لبنان'],
            'publishingPrinciples'      => page_url('editorial-policy'),
            'ethicsPolicy'              => page_url('editorial-policy'),
            'correctionsPolicy'         => page_url('editorial-policy'),
            'verificationFactCheckingPolicy' => page_url('editorial-policy'),
        ];
        if ($mail = setting('contact_email')) {
            $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'newsroom', 'email' => $mail, 'availableLanguage' => ['Arabic']];
        }
        return $org;
    }

    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id'   => url() . '#website',
            'url'   => url(),
            'name'  => site_name(),
            'inLanguage' => 'ar',
            'publisher'  => ['@id' => url() . '#organization'],
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('search') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function breadcrumbSchema(): ?array
    {
        if (!self::$breadcrumbs) {
            return null;
        }
        $items = [];
        $all = array_merge([['الرئيسية', url()]], self::$breadcrumbs);
        foreach ($all as $i => [$name, $u]) {
            $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name];
            if ($u) {
                $item['item'] = $u;
            }
            $items[] = $item;
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public static function render(): string
    {
        $t = self::fullTitle();
        $d = self::$description !== '' ? self::$description : setting('site_description', 'جنوب 24 موقع إخباري لبناني يغطي أخبار الجنوب ولبنان والشرق الأوسط لحظة بلحظة.');
        $img = self::$image !== '' ? self::$image : asset('img/og-default.jpg');
        $h = [];
        $h[] = '<title>' . e($t) . '</title>';
        $h[] = '<meta name="description" content="' . e($d) . '">';
        $h[] = '<meta name="robots" content="' . e(cfg('staging') ? 'noindex, nofollow' : self::$robots) . '">';
        if (self::$canonical) {
            $h[] = '<link rel="canonical" href="' . e(self::$canonical) . '">';
        }
        if (self::$prev) {
            $h[] = '<link rel="prev" href="' . e(self::$prev) . '">';
        }
        if (self::$next) {
            $h[] = '<link rel="next" href="' . e(self::$next) . '">';
        }
        // Open Graph
        $h[] = '<meta property="og:locale" content="ar_AR">';
        $h[] = '<meta property="og:site_name" content="' . e(site_name()) . '">';
        $h[] = '<meta property="og:type" content="' . e(self::$type) . '">';
        $h[] = '<meta property="og:title" content="' . e(self::$title !== '' ? self::$title : $t) . '">';
        $h[] = '<meta property="og:description" content="' . e($d) . '">';
        if (self::$canonical) {
            $h[] = '<meta property="og:url" content="' . e(self::$canonical) . '">';
        }
        $h[] = '<meta property="og:image" content="' . e($img) . '">';
        $h[] = '<meta property="og:image:width" content="1200">';
        $h[] = '<meta property="og:image:height" content="630">';
        $h[] = '<meta property="og:image:alt" content="' . e(self::$title !== '' ? self::$title : site_name()) . '">';
        if ($fbApp = setting('facebook_app_id')) {
            $h[] = '<meta property="fb:app_id" content="' . e($fbApp) . '">';
        }
        foreach (self::$meta as $k => $v) {
            [$prop, $content] = is_int($k) ? $v : [$k, $v];
            $attr = str_starts_with($prop, 'og:') || str_starts_with($prop, 'article:') ? 'property' : 'name';
            $h[] = '<meta ' . $attr . '="' . e($prop) . '" content="' . e($content) . '">';
        }
        // X / Twitter
        $h[] = '<meta name="twitter:card" content="summary_large_image">';
        if ($tw = setting('twitter_handle')) {
            $h[] = '<meta name="twitter:site" content="' . e($tw) . '">';
        }
        $h[] = '<meta name="twitter:title" content="' . e(self::$title !== '' ? self::$title : $t) . '">';
        $h[] = '<meta name="twitter:description" content="' . e($d) . '">';
        $h[] = '<meta name="twitter:image" content="' . e($img) . '">';

        // JSON-LD
        $graph = array_merge([self::organization()], self::$schemas);
        if ($bc = self::breadcrumbSchema()) {
            $graph[] = $bc;
        }
        $h[] = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
        return implode("\n  ", $h);
    }

    /** بيانات NewsArticle الكاملة لخبر */
    public static function newsArticle(array $a, array $tags): array
    {
        $img = $a['image'] ? [img_url($a['image'], 'lg'), img_url($a['image'], 'og')] : [asset('img/og-default.jpg')];
        $type = $a['type'] === 'opinion' ? 'OpinionNewsArticle' : 'NewsArticle';
        $schema = [
            '@type' => $type,
            '@id'   => article_url($a) . '#article',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => article_url($a)],
            'headline'      => mb_substr($a['title'], 0, 110),
            'description'   => $a['excerpt'] ?: excerpt($a['body']),
            'image'         => array_values(array_unique($img)),
            'datePublished' => iso($a['published_at']),
            'dateModified'  => iso(max(ts($a['updated_at']), ts($a['published_at']))),
            'inLanguage'    => 'ar',
            'articleSection' => $a['cat_name'],
            'wordCount'     => word_count($a['body']),
            'isAccessibleForFree' => true,
            'publisher'     => ['@id' => url() . '#organization'],
            'author'        => $a['author_name']
                ? ['@type' => 'Person', 'name' => $a['author_name'], 'url' => author_url($a)]
                : ['@type' => 'Organization', 'name' => site_name(), 'url' => url()],
        ];
        if ($tags) {
            $schema['keywords'] = implode(', ', array_column($tags, 'name'));
        }
        if ($vid = youtube_id($a['video_url'])) {
            $schema['video'] = [
                '@type' => 'VideoObject',
                'name'  => $a['title'],
                'description'  => $a['excerpt'] ?: excerpt($a['body']),
                'thumbnailUrl' => ["https://i.ytimg.com/vi/$vid/hqdefault.jpg"],
                'uploadDate'   => iso($a['published_at']),
                'embedUrl'     => "https://www.youtube.com/embed/$vid",
                'contentUrl'   => "https://www.youtube.com/watch?v=$vid",
            ];
        }
        return $schema;
    }
}
