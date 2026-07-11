<?php
declare(strict_types=1);

/* PHP twin of api/download-pdf.js — same contract: valid token streams the
   PDF as an attachment, anything else (missing/expired/invalid, including
   direct/bookmarked access) is a 302 to "/". */

require_once __DIR__ . '/../php/pdf-token.php';

$token = $_GET['token'] ?? null;

$valid = false;
try {
    $valid = pdf_verify_token(is_string($token) ? $token : null);
} catch (Throwable $e) {
    error_log($e->getMessage());
}

if (!$valid) {
    header('Location: /', true, 302);
    exit;
}

// The admin panel's "PDF (lead magnet)" section lets an admin pick which
// server/assets/*.pdf is active; content/site-settings.json is the single
// source of truth for that choice (shared with the Node twins). Falls back
// to the original fixed filename if the setting is missing or malformed.
$settings = json_decode((string) @file_get_contents(__DIR__ . '/../content/site-settings.json'), true);
$activeFilename = is_array($settings) && !empty($settings['leadMagnetPdf']) && is_string($settings['leadMagnetPdf'])
    ? basename($settings['leadMagnetPdf'])
    : 'sample.pdf';

$pdfPath = __DIR__ . '/../server/assets/' . $activeFilename;
if (!is_file($pdfPath)) {
    header('Location: /', true, 302);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="plan-action-facturation-2026.pdf"');
header('Cache-Control: no-store');
readfile($pdfPath);
