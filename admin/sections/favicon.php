<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/htmlpatch.php';

const FAVICON_DIR = __DIR__ . '/../../assets/images';
const HEADER_PARTIAL_PATH = __DIR__ . '/../../partials/header.html';

$flash = null;
$flashType = 'success';

/** Confirms $src ("assets/images/x.webp") resolves to a real file inside
 *  assets/images, so a hand-edited field value can't point outside it. */
function favicon_resolve_asset_path(string $src): ?string
{
    $resolved = realpath(__DIR__ . '/../../' . $src);
    $assetsRoot = realpath(FAVICON_DIR);
    if ($resolved === false || $assetsRoot === false || !str_starts_with($resolved, $assetsRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $resolved;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'favicon';

    try {
        if ($action === 'favicon') {
            $src = trim((string) ($_POST['favicon_src'] ?? ''));
            if ($src === '' || favicon_resolve_asset_path($src) === null) {
                throw new RuntimeException('Choisissez une image valide.');
            }

            $settings = site_settings_read();
            $settings['favicon'] = $src;
            site_settings_save($settings);

            $flash = 'Favicon mis à jour sur toutes les pages.';
        } elseif ($action === 'logo') {
            $src = trim((string) ($_POST['logo_src'] ?? ''));
            if ($src === '' || favicon_resolve_asset_path($src) === null) {
                throw new RuntimeException('Choisissez une image valide.');
            }

            $html = file_get_contents(HEADER_PARTIAL_PATH);
            if ($html === false) {
                throw new RuntimeException('Impossible de lire partials/header.html.');
            }
            $keyed = cms_scan_keyed($html);
            $patched = cms_patch_many($html, $keyed, ['header.logo' => $src]);
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
  <p class="hint">Synchronisé automatiquement sur toutes les pages (accueil, quiz, résultat, blog et chaque article) à chaque mise à jour.</p>
  <form method="post" action="dashboard?section=favicon">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="favicon" />
    <div class="admin-field">
      <label>Favicon</label>
      <div class="media-field-row">
        <input type="text" name="favicon_src" id="favicon-src" value="<?= htmlspecialchars($currentFavicon, ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/favicon.png" />
        <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="favicon-src">Parcourir…</button>
      </div>
      <span class="hint">PNG ou SVG recommandés (carré, fond transparent).</span>
      <div class="content-block-image-preview" data-preview-for="favicon-src"><?= $currentFavicon !== '' ? '<img src="../' . htmlspecialchars($currentFavicon, ENT_QUOTES) . '" alt="" />' : '' ?></div>
    </div>
    <button class="admin-btn" type="submit">Enregistrer</button>
  </form>
</div>

<div class="admin-card">
  <h3>Logo du site</h3>
  <p class="hint">Logo affiché dans l'en-tête (<code>partials/header.html</code>), partagé par toutes les pages — une seule mise à jour suffit.</p>
  <form method="post" action="dashboard?section=favicon">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="logo" />
    <div class="admin-field">
      <label>Logo</label>
      <div class="media-field-row">
        <input type="text" name="logo_src" id="logo-src" value="<?= htmlspecialchars($currentLogo, ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/logo.webp" />
        <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="logo-src">Parcourir…</button>
      </div>
      <div class="content-block-image-preview" data-preview-for="logo-src"><?= $currentLogo !== '' ? '<img src="../' . htmlspecialchars($currentLogo, ENT_QUOTES) . '" alt="" />' : '' ?></div>
    </div>
    <button class="admin-btn" type="submit">Enregistrer</button>
  </form>
</div>

<?php
  $mediaFileNames = [];
  foreach (scandir(__DIR__ . '/../../assets/images') ?: [] as $entry) {
      if (str_starts_with($entry, '.')) {
          continue;
      }
      if (is_file(__DIR__ . '/../../assets/images/' . $entry)) {
          $mediaFileNames[] = $entry;
      }
  }
  sort($mediaFileNames);
?>
<datalist id="media-files">
  <?php foreach ($mediaFileNames as $entry): ?>
    <option value="assets/images/<?= htmlspecialchars($entry, ENT_QUOTES) ?>"></option>
  <?php endforeach; ?>
</datalist>

<!-- Thumbnail browser for the favicon/logo fields — the datalist above is a
     plain text autocomplete, not a visual picker, so this covers that gap. -->
<script id="media-files-data" type="application/json"><?= json_encode($mediaFileNames, JSON_UNESCAPED_SLASHES) ?: '[]' ?></script>
<div class="media-picker-overlay" id="media-picker-overlay" data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" hidden>
  <div class="media-picker-modal">
    <div class="media-picker-head">
      <h3>Choisir une image</h3>
      <button type="button" id="media-picker-close" class="admin-btn secondary">Fermer</button>
    </div>
    <div class="media-picker-upload">
      <input type="file" id="media-picker-file" accept=".webp,.png,.jpg,.jpeg,.svg" hidden />
      <button type="button" id="media-picker-upload-btn" class="admin-btn">+ Téléverser depuis mon ordinateur</button>
      <span class="hint">WebP, PNG, JPG ou SVG — 4 Mo maximum. Les PNG/JPG sont automatiquement convertis en WebP optimisé.</span>
      <span class="hint" id="media-picker-upload-status"></span>
    </div>
    <div class="admin-grid-media" id="media-picker-grid"></div>
  </div>
</div>
