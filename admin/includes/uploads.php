<?php
declare(strict_types=1);

/* Validated file upload helper. Uploads are the classic webshell vector on
   shared PHP hosting, so this never trusts the client-supplied filename,
   extension, or Content-Type header — it sniffs the real MIME type with
   finfo and only allows a fixed whitelist. */

const UPLOAD_IMAGE_MIME_EXT = [
    'image/webp' => 'webp',
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/svg+xml' => 'svg',
];

const UPLOAD_PDF_MIME_EXT = [
    'application/pdf' => 'pdf',
];

const UPLOAD_FAVICON_MIME_EXT = [
    'image/x-icon' => 'ico',
    'image/vnd.microsoft.icon' => 'ico',
    'image/png' => 'png',
    'image/svg+xml' => 'svg',
];

class UploadError extends RuntimeException {}

/**
 * @param array $file one entry from $_FILES
 * @param array<string,string> $allowedMimeExt mime => extension whitelist
 * @return array{tmp_path:string, ext:string, size:int} validated upload info
 */
function validate_uploaded_file(array $file, array $allowedMimeExt, int $maxBytes): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new UploadError('Échec du téléversement (code ' . ($file['error'] ?? '?') . ').');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadError('Fichier invalide.');
    }
    if ($file['size'] > $maxBytes) {
        throw new UploadError('Fichier trop volumineux (max ' . round($maxBytes / 1048576, 1) . ' Mo).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimeExt[$mime])) {
        throw new UploadError("Type de fichier non autorisé ($mime).");
    }

    return [
        'tmp_path' => $file['tmp_name'],
        'ext' => $allowedMimeExt[$mime],
        'size' => (int) $file['size'],
    ];
}

/* Safe, random destination filename — never derived from user input, so
   path traversal / double-extension tricks (".php.png") are impossible. */
function safe_upload_filename(string $ext, string $hint = ''): string
{
    $hint = preg_replace('/[^a-z0-9-]/', '', strtolower($hint));
    $hint = $hint !== '' ? substr($hint, 0, 30) . '-' : '';
    return $hint . bin2hex(random_bytes(6)) . '.' . $ext;
}

function move_validated_upload(array $validated, string $destPath): void
{
    $dir = dirname($destPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }
    if (!move_uploaded_file($validated['tmp_path'], $destPath)) {
        throw new RuntimeException('Impossible de déplacer le fichier téléversé.');
    }
    @chmod($destPath, 0664);
}
