<?php
declare(strict_types=1);

/* Atomic flat-file JSON read/write. Every write goes to a temp file in the
   same directory then rename()s over the target — rename() is atomic on
   the same filesystem, so a request that dies mid-write never leaves a
   half-written file for the next reader. */

function flatfile_read_json(string $path, $default = null)
{
    if (!is_file($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return $data === null && json_last_error() !== JSON_ERROR_NONE ? $default : $data;
}

function flatfile_write_json(string $path, $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
    }
    flatfile_write_raw($path, $json);
}

function flatfile_write_raw(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }
    $tmp = $dir . '/.' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($tmp, $contents) === false) {
        throw new RuntimeException("Failed to write temp file: $tmp");
    }
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Failed to rename into place: $path");
    }
    @chmod($path, 0664);
}

/* List *.json files in a directory, decoded, keyed by filename without extension. */
function flatfile_list_json_dir(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    foreach (scandir($dir) ?: [] as $entry) {
        if (substr($entry, -5) !== '.json') {
            continue;
        }
        $slug = substr($entry, 0, -5);
        $data = flatfile_read_json($dir . '/' . $entry);
        if ($data !== null) {
            $out[$slug] = $data;
        }
    }
    return $out;
}
