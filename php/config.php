<?php
declare(strict_types=1);

/* Central config for the PHP side (admin CMS + lead funnel).
   Env vars win when the host supports them (Hostinger Apache: `SetEnv` in
   .htaccess, or a VPS/Node-style env panel). Otherwise falls back to a
   gitignored config.local.php — same role as .env.local on the Node side. */

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    require $localConfigPath;
}

function lmoh_config(string $key, ?string $default = null): ?string
{
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    if (defined($key) && constant($key) !== '') {
        return (string) constant($key);
    }
    return $default;
}

define('LMOH_ROOT', dirname(__DIR__));
