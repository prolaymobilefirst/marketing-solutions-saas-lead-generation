<?php
declare(strict_types=1);

require_once __DIR__ . '/flatfile.php';
require_once __DIR__ . '/htmlpatch.php';

const BLOG_CONTENT_DIR = __DIR__ . '/../../content/blog';
const BLOG_TEMPLATE_PATH = __DIR__ . '/../../templates/blog-post.template.html';
const BLOG_INDEX_PATH = __DIR__ . '/../../blog.html';
const BLOG_SITE_BASE = 'https://www.facturation2026.fr/';

function blog_post_path(string $slug): string
{
    return __DIR__ . '/../../blog-' . $slug . '.html';
}

/** @return array<string,array> slug => post data, all posts in content/blog/*.json */
function blog_all_posts(): array
{
    return flatfile_list_json_dir(BLOG_CONTENT_DIR);
}

function blog_save_post(array $post): void
{
    flatfile_write_json(BLOG_CONTENT_DIR . '/' . $post['slug'] . '.json', $post);
    blog_write_post_html($post);
    blog_regenerate_index();

    // A newly generated post starts from the template as-is — it needs the
    // current GTM/site-verification/favicon settings synced in immediately,
    // same as every other page, rather than waiting for the next time
    // someone happens to resave the global settings.
    $siteSettingsPath = __DIR__ . '/site_settings.php';
    if (is_file($siteSettingsPath)) {
        require_once $siteSettingsPath;
        site_settings_sync_all_pages();
    }
}

function blog_delete_post(string $slug): void
{
    @unlink(BLOG_CONTENT_DIR . '/' . $slug . '.json');
    @unlink(blog_post_path($slug));
    blog_regenerate_index();
}

function blog_build_article_schema(array $post): string
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $post['metaDescription'],
        'datePublished' => $post['datePublished'],
        'dateModified' => $post['dateModified'],
        'author' => ['@type' => 'Organization', 'name' => 'Facturation Électronique 2026'],
        'mainEntityOfPage' => BLOG_SITE_BASE . 'blog-' . $post['slug'] . '.html',
    ];
    return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function blog_render_related_articles(array $allPosts, string $excludeSlug): string
{
    $others = array_filter($allPosts, fn($p, $slug) => $slug !== $excludeSlug, ARRAY_FILTER_USE_BOTH);
    usort($others, fn($a, $b) => strcmp($b['datePublished'] ?? '', $a['datePublished'] ?? ''));
    $others = array_slice($others, 0, 2);

    $html = '';
    foreach ($others as $p) {
        $html .= sprintf(
            "\n          <a href=\"blog-%s.html\" class=\"related-article-card\">\n            <span class=\"blog-card-badge\">%s</span>\n            <span class=\"related-article-card-title\">%s</span>\n          </a>",
            htmlspecialchars($p['slug'], ENT_QUOTES),
            cms_escape_text($p['badge']),
            cms_escape_text($p['title'])
        );
    }
    return $html . "\n          ";
}

function blog_write_post_html(array $post): void
{
    $template = file_get_contents(BLOG_TEMPLATE_PATH);
    if ($template === false) {
        throw new RuntimeException('Blog post template introuvable.');
    }

    $allPosts = blog_all_posts();
    $canonical = BLOG_SITE_BASE . 'blog-' . $post['slug'] . '.html';

    $replacements = [
        '__TITLE__' => cms_escape_text($post['title']),
        '__DESCRIPTION__' => htmlspecialchars($post['metaDescription'], ENT_COMPAT),
        '__KEYWORDS__' => htmlspecialchars($post['keywords'] ?? '', ENT_COMPAT),
        '__CANONICAL__' => htmlspecialchars($canonical, ENT_COMPAT),
        '__SCHEMA__' => "\n  " . blog_build_article_schema($post) . "\n  ",
        '__SLUG__' => htmlspecialchars($post['slug'], ENT_QUOTES),
        '__ICON__' => htmlspecialchars($post['icon'], ENT_COMPAT),
        '__BADGE__' => cms_escape_text($post['badge']),
        '__INTRO__' => cms_escape_text($post['intro']),
        '__BODY__' => $post['bodyHtml'], // trusted admin-authored HTML, inserted raw
        '__CTA_TEXT__' => cms_escape_text($post['ctaText']),
        '__RELATED_ARTICLES__' => blog_render_related_articles($allPosts, $post['slug']),
    ];

    $html = str_replace(array_keys($replacements), array_values($replacements), $template);
    flatfile_write_raw(blog_post_path($post['slug']), $html);

    // Other posts' "related articles" may now need to include/exclude this
    // one, and its title/badge may have changed — keep them all in sync.
    foreach ($allPosts as $slug => $other) {
        if ($slug === $post['slug']) {
            continue;
        }
        blog_resync_related_articles($other, $allPosts);
    }
}

function blog_resync_related_articles(array $post, array $allPosts): void
{
    $path = blog_post_path($post['slug']);
    if (!is_file($path)) {
        return;
    }
    $html = file_get_contents($path);
    $newRelated = blog_render_related_articles($allPosts, $post['slug']);
    $patched = preg_replace(
        '/<!-- RELATED_ARTICLES_START -->.*?<!-- RELATED_ARTICLES_END -->/s',
        '<!-- RELATED_ARTICLES_START -->' . $newRelated . '<!-- RELATED_ARTICLES_END -->',
        $html,
        1
    );
    if ($patched !== null && $patched !== $html) {
        flatfile_write_raw($path, $patched);
    }
}

function blog_render_grid_card(array $post): string
{
    return sprintf(
        "\n      <a href=\"blog-%s.html\" class=\"blog-grid-card\">\n".
        "        <div class=\"blog-grid-card-img\">\n".
        "          <span class=\"blog-grid-card-img-circle\">\n".
        "            <img src=\"%s\" alt=\"\" aria-hidden=\"true\" loading=\"lazy\" />\n".
        "          </span>\n".
        "        </div>\n".
        "        <div class=\"blog-grid-card-body\">\n".
        "          <span class=\"blog-card-badge\">%s</span>\n".
        "          <h2 class=\"blog-grid-card-title\">%s</h2>\n".
        "          <p class=\"blog-grid-card-excerpt\">%s</p>\n".
        "          <span class=\"blog-grid-card-meta\">📅 Mis à jour %s · %s</span>\n".
        "          <span class=\"blog-grid-card-link\">Lire l'article →</span>\n".
        "        </div>\n".
        "      </a>\n",
        htmlspecialchars($post['slug'], ENT_QUOTES),
        htmlspecialchars($post['icon'], ENT_COMPAT),
        cms_escape_text($post['badge']),
        cms_escape_text($post['title']),
        cms_escape_text($post['excerpt']),
        htmlspecialchars(substr($post['dateModified'] ?? $post['datePublished'], 0, 4), ENT_QUOTES),
        cms_escape_text($post['readTime'] ?? '')
    );
}

function blog_regenerate_index(): void
{
    $html = file_get_contents(BLOG_INDEX_PATH);
    if ($html === false) {
        throw new RuntimeException('blog.html introuvable.');
    }

    $posts = blog_all_posts();
    usort($posts, fn($a, $b) => strcmp($b['datePublished'] ?? '', $a['datePublished'] ?? ''));

    $gridHtml = '';
    foreach ($posts as $p) {
        $gridHtml .= blog_render_grid_card($p);
    }

    $html = preg_replace(
        '/<!-- BLOG_GRID_START -->.*?<!-- BLOG_GRID_END -->/s',
        '<!-- BLOG_GRID_START -->' . $gridHtml . '      <!-- BLOG_GRID_END -->',
        $html,
        1
    );

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'Actualités Facturation Électronique 2026',
        'url' => BLOG_SITE_BASE . 'blog.html',
        'blogPost' => array_map(fn($p) => [
            '@type' => 'BlogPosting',
            'headline' => $p['title'],
            'url' => BLOG_SITE_BASE . 'blog-' . $p['slug'] . '.html',
        ], $posts),
    ];
    $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $html = preg_replace(
        '/(<script type="application\/ld\+json" data-blog-schema="auto">)(.*?)(<\/script>)/s',
        '$1' . "\n  " . str_replace('$', '\$', $schemaJson) . "\n  " . '$3',
        $html,
        1
    );

    flatfile_write_raw(BLOG_INDEX_PATH, $html);
}
