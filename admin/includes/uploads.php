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

    if (!empty($validated['is_temp_file'])) {
        // Came out of convert_image_to_webp(), not straight from $_FILES —
        // it's a plain tempnam() file, not a PHP upload, so move_uploaded_file()
        // would reject it.
        if (!rename($validated['tmp_path'], $destPath)) {
            throw new RuntimeException('Impossible de déplacer le fichier converti.');
        }
    } elseif (!move_uploaded_file($validated['tmp_path'], $destPath)) {
        throw new RuntimeException('Impossible de déplacer le fichier téléversé.');
    }
    @chmod($destPath, 0664);
}

const WEBP_QUALITY = 82; // visually lossless for photos/screenshots at a fraction of the size

/**
 * Re-encodes a validated PNG/JPEG upload as WebP for a smaller file at the
 * same visual quality. SVG stays vector (rasterizing it would be a quality
 * downgrade, not an upgrade) and an already-WebP upload is left alone (re-
 * encoding a lossy format a second time only loses more quality without a
 * guaranteed size win). Falls back to the original file untouched if the
 * GD WebP encoder isn't available on this host.
 *
 * @param array{tmp_path:string, ext:string, size:int} $validated
 * @return array{tmp_path:string, ext:string, size:int, is_temp_file?:bool}
 */
function convert_image_to_webp(array $validated): array
{
    if ($validated['ext'] !== 'png' && $validated['ext'] !== 'jpg') {
        return $validated;
    }
    if (!function_exists('imagewebp') || !(imagetypes() & IMG_WEBP)) {
        return $validated;
    }

    $image = $validated['ext'] === 'png'
        ? @imagecreatefrompng($validated['tmp_path'])
        : @imagecreatefromjpeg($validated['tmp_path']);
    if ($image === false) {
        return $validated;
    }

    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $webpPath = $validated['tmp_path'] . '-converted.webp';
    $ok = imagewebp($image, $webpPath, WEBP_QUALITY);
    imagedestroy($image);

    if (!$ok || !is_file($webpPath)) {
        return $validated;
    }

    return [
        'tmp_path' => $webpPath,
        'ext' => 'webp',
        'size' => (int) filesize($webpPath),
        'is_temp_file' => true,
    ];
}
