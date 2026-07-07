<?php
declare(strict_types=1);

/* Site-wide settings (GTM container, search-engine site verification, and
   the favicon) — synced into every static page via HTML comment markers,
   the same pattern used for blog.html's auto-generated grid/schema. These
   aren't per-page [data-cms] fields because they're the same value on
   every page; one save here rewrites all of them at once. */

require_once __DIR__ . '/flatfile.php';

const SITE_SETTINGS_PATH = __DIR__ . '/../../content/site-settings.json';

const SITE_SETTINGS_DEFAULTS = [
    'gtmContainerId' => '',
    'googleSiteVerification' => '',
    'bingSiteVerification' => '',
    'favicon' => '',
    'metaPixelId' => '',
];

const SITE_SETTINGS_STATIC_FILES = ['index.html', 'quiz.html', 'results.html', 'blog.html', 'mentions-legales.html', 'politique-confidentialite.html'];

function site_settings_read(): array
{
    return array_merge(SITE_SETTINGS_DEFAULTS, flatfile_read_json(SITE_SETTINGS_PATH, []));
}

function site_settings_save(array $settings): void
{
    $settings = array_merge(SITE_SETTINGS_DEFAULTS, $settings);
    flatfile_write_json(SITE_SETTINGS_PATH, $settings);
    site_settings_sync_all_pages($settings);
}

function site_settings_build_head_meta_block(array $s): string
{
    $lines = [];
    if ($s['favicon'] !== '') {
        $lines[] = '<link rel="icon" href="' . htmlspecialchars($s['favicon'], ENT_COMPAT) . '">';
    }
    if ($s['googleSiteVerification'] !== '') {
        $lines[] = '<meta name="google-site-verification" content="' . htmlspecialchars($s['googleSiteVerification'], ENT_COMPAT) . '">';
    }
    if ($s['bingSiteVerification'] !== '') {
        $lines[] = '<meta name="msvalidate.01" content="' . htmlspecialchars($s['bingSiteVerification'], ENT_COMPAT) . '">';
    }
    return $lines ? "\n  " . implode("\n  ", $lines) . "\n  " : '';
}

function site_settings_gtm_id(array $s): string
{
    // GTM IDs are GTM-XXXXXXX — strip anything else so the value can never
    // break out of the inline <script> it's embedded in below.
    return preg_replace('/[^A-Za-z0-9-]/', '', $s['gtmContainerId']);
}

function site_settings_build_gtm_head_block(array $s): string
{
    $id = site_settings_gtm_id($s);
    if ($id === '') {
        return '';
    }
    return "\n  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
         . "  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
         . "  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
         . "  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
         . "  })(window,document,'script','dataLayer','$id');</script>\n  ";
}

function site_settings_build_gtm_body_block(array $s): string
{
    $id = site_settings_gtm_id($s);
    if ($id === '') {
        return '';
    }
    return "\n  <noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=$id\"\n"
         . "  height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n  ";
}

function site_settings_pixel_id(array $s): string
{
    // Meta Pixel IDs are purely numeric — strip anything else so the value
    // can never break out of the inline <script> or the <img src=...> URL
    // it's embedded in below.
    return preg_replace('/[^0-9]/', '', $s['metaPixelId']);
}

function site_settings_build_meta_pixel_head_block(array $s): string
{
    $id = site_settings_pixel_id($s);
    if ($id === '') {
        return '';
    }
    return "\n  <script>\n"
         . "  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?\n"
         . "  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;\n"
         . "  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;\n"
         . "  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,\n"
         . "  document,'script','https://connect.facebook.net/en_US/fbevents.js');\n"
         . "  fbq('init', '$id');\n"
         . "  fbq('track', 'PageView');\n"
         . "  </script>\n"
         . "  <noscript><img height=\"1\" width=\"1\" style=\"display:none\"\n"
         . "  src=\"https://www.facebook.com/tr?id=$id&ev=PageView&noscript=1\" /></noscript>\n  ";
}

/** @return string[] project-root-relative paths of every page that carries the sync markers */
function site_settings_target_files(): array
{
    $files = SITE_SETTINGS_STATIC_FILES;
    $blogRenderPath = __DIR__ . '/blog_render.php';
    if (is_file($blogRenderPath)) {
        require_once $blogRenderPath;
        foreach (array_keys(blog_all_posts()) as $slug) {
            $files[] = 'blog-' . $slug . '.html';
        }
    }
    return $files;
}

function site_settings_sync_all_pages(?array $settings = null): void
{
    $settings ??= site_settings_read();
    $headMeta = site_settings_build_head_meta_block($settings);
    $gtmHead = site_settings_build_gtm_head_block($settings);
    $gtmBody = site_settings_build_gtm_body_block($settings);
    $pixelHead = site_settings_build_meta_pixel_head_block($settings);

    foreach (site_settings_target_files() as $rel) {
        $path = __DIR__ . '/../../' . $rel;
        if (!is_file($path)) {
            continue;
        }
        $html = file_get_contents($path);
        $html = preg_replace('/<!-- GTM_HEAD_START -->.*?<!-- GTM_HEAD_END -->/s', '<!-- GTM_HEAD_START -->' . $gtmHead . '<!-- GTM_HEAD_END -->', $html, 1);
        $html = preg_replace('/<!-- META_PIXEL_START -->.*?<!-- META_PIXEL_END -->/s', '<!-- META_PIXEL_START -->' . $pixelHead . '<!-- META_PIXEL_END -->', $html, 1);
        $html = preg_replace('/<!-- SITE_META_START -->.*?<!-- SITE_META_END -->/s', '<!-- SITE_META_START -->' . $headMeta . '<!-- SITE_META_END -->', $html, 1);
        $html = preg_replace('/<!-- GTM_BODY_START -->.*?<!-- GTM_BODY_END -->/s', '<!-- GTM_BODY_START -->' . $gtmBody . '<!-- GTM_BODY_END -->', $html, 1);
        flatfile_write_raw($path, $html);
    }
}
