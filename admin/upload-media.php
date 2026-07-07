<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/uploads.php';

/* JSON upload endpoint backing the media picker modal's "Téléverser…"
   button (blog.php's image content blocks) — same validated pipeline as
   the Médiathèque page, just returning JSON for a fetch() caller instead
   of re-rendering a full admin page. */

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

$mediaDir = __DIR__ . '/../assets/images';
$maxBytes = 4 * 1024 * 1024;

try {
    if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new UploadError('Choisissez un fichier à téléverser.');
    }
    $validated = validate_uploaded_file($_FILES['image'], UPLOAD_IMAGE_MIME_EXT, $maxBytes);
    $validated = convert_image_to_webp($validated);
    $hint = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
    $filename = safe_upload_filename($validated['ext'], $hint);
    move_validated_upload($validated, $mediaDir . '/' . $filename);
    echo json_encode(['ok' => true, 'filename' => $filename, 'path' => 'assets/images/' . $filename]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
