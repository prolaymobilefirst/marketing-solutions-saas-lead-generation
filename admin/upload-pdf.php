<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/uploads.php';

/* JSON upload endpoint backing the lead-magnet PDF picker modal's
   "Téléverser…" button — same validated pipeline as upload-media.php,
   just targeting server/assets (never web-reachable, see .htaccess/
   vercel.json/netlify.toml redirects on "server/") instead of assets/images. */

admin_require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expirée — rechargez la page et réessayez.']);
    exit;
}

$pdfDir = __DIR__ . '/../server/assets';
$maxBytes = 15 * 1024 * 1024;

try {
    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new UploadError('Choisissez un fichier PDF à téléverser.');
    }
    $validated = validate_uploaded_file($_FILES['pdf'], UPLOAD_PDF_MIME_EXT, $maxBytes);
    $hint = pathinfo($_FILES['pdf']['name'], PATHINFO_FILENAME);
    $filename = safe_upload_filename($validated['ext'], $hint);
    move_validated_upload($validated, $pdfDir . '/' . $filename);
    echo json_encode([
        'ok' => true,
        'filename' => $filename,
        'sizeKo' => (int) round(filesize($pdfDir . '/' . $filename) / 1024),
        'mtime' => date('d/m/Y H:i', filemtime($pdfDir . '/' . $filename)),
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
