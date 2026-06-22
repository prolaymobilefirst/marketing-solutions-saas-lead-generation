<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/uploads.php';

const LEAD_MAGNET_PDF_PATH = __DIR__ . '/../../server/assets/sample.pdf';
const PDF_MAX_BYTES = 15 * 1024 * 1024;

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new UploadError('Choisissez un fichier PDF à téléverser.');
        }
        $validated = validate_uploaded_file($_FILES['pdf'], UPLOAD_PDF_MIME_EXT, PDF_MAX_BYTES);
        move_validated_upload($validated, LEAD_MAGNET_PDF_PATH);
        $flash = 'Le PDF du lead magnet a été remplacé.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$exists = is_file(LEAD_MAGNET_PDF_PATH);
$size = $exists ? filesize(LEAD_MAGNET_PDF_PATH) : 0;
$mtime = $exists ? filemtime(LEAD_MAGNET_PDF_PATH) : null;
?>
<h2>PDF — Plan d'action (lead magnet)</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3>Fichier actuel</h3>
  <?php if ($exists): ?>
    <p><?= round($size / 1024) ?> Ko — modifié le <?= date('d/m/Y H:i', $mtime) ?></p>
  <?php else: ?>
    <p>Aucun PDF n'est actuellement en place — le téléchargement échouera tant qu'aucun fichier n'est téléversé.</p>
  <?php endif; ?>
  <p class="hint">Ce fichier n'est jamais accessible directement par URL : il n'est servi qu'après une soumission de formulaire réussie, via un jeton signé à courte durée de vie (voir /api/download-pdf).</p>
</div>

<div class="admin-card">
  <h3>Remplacer le PDF</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="admin-field">
      <input type="file" name="pdf" accept="application/pdf" required />
      <span class="hint">PDF uniquement — 15 Mo maximum.</span>
    </div>
    <button class="admin-btn" type="submit">Téléverser et remplacer</button>
  </form>
</div>
