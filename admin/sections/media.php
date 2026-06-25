<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/uploads.php';

const MEDIA_DIR = __DIR__ . '/../../assets/images';
const MEDIA_PUBLIC_PREFIX = 'assets/images/';
const MEDIA_MAX_BYTES = 4 * 1024 * 1024;

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'upload') {
            if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new UploadError('Choisissez un fichier à téléverser.');
            }
            $validated = validate_uploaded_file($_FILES['image'], UPLOAD_IMAGE_MIME_EXT, MEDIA_MAX_BYTES);
            $validated = convert_image_to_webp($validated);
            $hint = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
            $filename = safe_upload_filename($validated['ext'], $hint);
            move_validated_upload($validated, MEDIA_DIR . '/' . $filename);
            $flash = "Image téléversée : $filename";
        } elseif ($action === 'delete') {
            $filename = basename((string) ($_POST['filename'] ?? ''));
            $path = MEDIA_DIR . '/' . $filename;
            if ($filename === '' || !is_file($path) || dirname(realpath($path)) !== realpath(MEDIA_DIR)) {
                throw new RuntimeException('Fichier introuvable.');
            }
            unlink($path);
            $flash = "Image supprimée : $filename";
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$files = [];
foreach (scandir(MEDIA_DIR) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || $entry === '.htaccess' || str_starts_with($entry, '.')) {
        continue;
    }
    if (is_file(MEDIA_DIR . '/' . $entry)) {
        $files[] = $entry;
    }
}
sort($files);
?>
<h2>Médiathèque</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3>Téléverser une image</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload" />
    <div class="admin-field">
      <input type="file" name="image" accept=".webp,.png,.jpg,.jpeg,.svg" required />
      <span class="hint">WebP, PNG, JPG ou SVG — 4 Mo maximum. Les PNG/JPG sont automatiquement convertis en WebP optimisé (taille réduite, qualité visuelle conservée) ; le type réel du fichier est vérifié côté serveur.</span>
    </div>
    <button class="admin-btn" type="submit">Téléverser</button>
  </form>
</div>

<div class="admin-card">
  <h3>Fichiers existants (<?= count($files) ?>)</h3>
  <div class="admin-grid-media">
    <?php foreach ($files as $f): ?>
      <div class="admin-media-item">
        <img src="../<?= MEDIA_PUBLIC_PREFIX . rawurlencode($f) ?>" alt="" loading="lazy" />
        <span class="path"><?= htmlspecialchars($f, ENT_QUOTES) ?></span>
        <form method="post" data-confirm="Supprimer cette image ?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete" />
          <input type="hidden" name="filename" value="<?= htmlspecialchars($f, ENT_QUOTES) ?>" />
          <button class="admin-btn danger" type="submit" style="font-size:.7rem;padding:.3rem .6rem;">Supprimer</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
