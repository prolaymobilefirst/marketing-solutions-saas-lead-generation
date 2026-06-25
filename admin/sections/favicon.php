<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/htmlpatch.php';

const FAVICON_DIR = __DIR__ . '/../../assets/images';
const FAVICON_MAX_BYTES = 1 * 1024 * 1024;
const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const HEADER_PARTIAL_PATH = __DIR__ . '/../../partials/header.html';

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'favicon';

    try {
        if ($action === 'favicon') {
            if (empty($_FILES['favicon']) || $_FILES['favicon']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new UploadError('Choisissez un fichier à téléverser.');
            }
            $validated = validate_uploaded_file($_FILES['favicon'], UPLOAD_FAVICON_MIME_EXT, FAVICON_MAX_BYTES);

            // Fixed filename (favicon.<ext>) — overwritten each time, and any
            // stale favicon.* with a different extension from a previous
            // upload is removed so the <link> tag never points at a leftover.
            foreach (glob(FAVICON_DIR . '/favicon.*') ?: [] as $stale) {
                @unlink($stale);
            }
            $filename = 'favicon.' . $validated['ext'];
            move_validated_upload($validated, FAVICON_DIR . '/' . $filename);

            $settings = site_settings_read();
            $settings['favicon'] = 'assets/images/' . $filename;
            site_settings_save($settings);

            $flash = 'Favicon mis à jour sur toutes les pages.';
        } elseif ($action === 'logo') {
            if (empty($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new UploadError('Choisissez un fichier à téléverser.');
            }
            $validated = validate_uploaded_file($_FILES['logo'], UPLOAD_IMAGE_MIME_EXT, LOGO_MAX_BYTES);
            $validated = convert_image_to_webp($validated);

            // Same fixed-filename-overwrite pattern as the favicon, so the
            // header partial's <img src> never needs to change shape, only
            // the file behind it.
            foreach (glob(FAVICON_DIR . '/logo.*') ?: [] as $stale) {
                @unlink($stale);
            }
            $filename = 'logo.' . $validated['ext'];
            move_validated_upload($validated, FAVICON_DIR . '/' . $filename);

            $html = file_get_contents(HEADER_PARTIAL_PATH);
            if ($html === false) {
                throw new RuntimeException('Impossible de lire partials/header.html.');
            }
            $keyed = cms_scan_keyed($html);
            $patched = cms_patch_many($html, $keyed, ['header.logo' => 'assets/images/' . $filename]);
            flatfile_write_raw(HEADER_PARTIAL_PATH, $patched);

            $flash = 'Logo mis à jour sur toutes les pages (en-tête partagé).';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$settings = site_settings_read();
$currentFavicon = $settings['favicon'];

$headerHtml = file_get_contents(HEADER_PARTIAL_PATH) ?: '';
$logoField = cms_scan_keyed($headerHtml)['header.logo'] ?? null;
$currentLogo = $logoField?->value ?? '';
?>
<h2>Favicon &amp; Logo</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3>Favicon actuel</h3>
  <?php if ($currentFavicon): ?>
    <img src="../<?= htmlspecialchars($currentFavicon, ENT_QUOTES) ?>" alt="" style="width:32px;height:32px;object-fit:contain;" />
    <p class="hint"><?= htmlspecialchars($currentFavicon, ENT_QUOTES) ?></p>
  <?php else: ?>
    <p>Aucun favicon n'est actuellement configuré.</p>
  <?php endif; ?>
  <p class="hint">Synchronisé automatiquement sur toutes les pages (accueil, quiz, résultat, blog et chaque article) à chaque mise à jour.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="favicon" />
    <div class="admin-field">
      <label>Téléverser un nouveau favicon</label>
      <input type="file" name="favicon" accept=".ico,.png,.svg" required />
      <span class="hint">ICO, PNG ou SVG — 1 Mo maximum. Le type réel du fichier est vérifié côté serveur.</span>
    </div>
    <button class="admin-btn" type="submit">Téléverser et appliquer</button>
  </form>
</div>

<div class="admin-card">
  <h3>Logo du site</h3>
  <?php if ($currentLogo): ?>
    <img src="../<?= htmlspecialchars($currentLogo, ENT_QUOTES) ?>" alt="" style="max-width:160px;max-height:60px;object-fit:contain;" />
    <p class="hint"><?= htmlspecialchars($currentLogo, ENT_QUOTES) ?></p>
  <?php else: ?>
    <p>Logo introuvable dans partials/header.html.</p>
  <?php endif; ?>
  <p class="hint">Logo affiché dans l'en-tête (<code>partials/header.html</code>), partagé par toutes les pages — une seule mise à jour suffit.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="logo" />
    <div class="admin-field">
      <label>Téléverser un nouveau logo</label>
      <input type="file" name="logo" accept=".webp,.png,.jpg,.jpeg,.svg" required />
      <span class="hint">WebP, PNG, JPG ou SVG — 2 Mo maximum. Les PNG/JPG sont automatiquement convertis en WebP optimisé (taille réduite, qualité visuelle conservée) ; le type réel du fichier est vérifié côté serveur.</span>
    </div>
    <button class="admin-btn" type="submit">Téléverser et appliquer</button>
  </form>
</div>
