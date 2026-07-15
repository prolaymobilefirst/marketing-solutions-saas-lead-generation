<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/htmlpatch.php';

const FOOTER_PARTIAL_PATH = __DIR__ . '/../../partials/footer.html';
const BANNER_PARTIAL_PATH = __DIR__ . '/../../partials/partner-banner.html';

const FOOTER_PARTNER_SLUGS = ['abby', 'tiime', 'indy', 'karlia', 'axonaut', 'shine'];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    try {
        $incoming = $_POST['fields'] ?? [];

        foreach ([FOOTER_PARTIAL_PATH, BANNER_PARTIAL_PATH] as $path) {
            $html = file_get_contents($path);
            if ($html === false) {
                throw new RuntimeException('Impossible de lire ' . basename($path) . '.');
            }
            $keyed = cms_scan_keyed($html);
            $updates = [];
            foreach ($incoming as $key => $value) {
                if (isset($keyed[$key])) {
                    $updates[$key] = (string) $value;
                }
            }
            $patched = cms_patch_many($html, $keyed, $updates);
            flatfile_write_raw($path, $patched);
        }

        $flash = 'Pied de page enregistré.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$footerFields = cms_scan_keyed(file_get_contents(FOOTER_PARTIAL_PATH) ?: '');
$bannerFields = cms_scan_keyed(file_get_contents(BANNER_PARTIAL_PATH) ?: '');

/** Small local helper so every field row below stays one line. */
function footer_field_value(array $fields, string $key): string
{
    return $fields[$key]->value ?? '';
}
?>
<h2>Pied de page</h2>
<p class="hint">Gère le bandeau de logos partenaires, le bas de page et la bannière d'aide ("Besoin d'aide pour comprendre la réforme 2026 ?") affichés sur toutes les pages.</p>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <div class="admin-card">
    <h3>Bandeau logiciels partenaires</h3>
    <div class="admin-field">
      <label>Texte du bandeau</label>
      <input type="text" name="fields[footer.trustLabel]" value="<?= htmlspecialchars(footer_field_value($footerFields, 'footer.trustLabel'), ENT_QUOTES) ?>" />
    </div>
    <p class="hint">Le badge officiel "Plateforme agréée" n'est pas modifiable ici (élément réglementaire fixe).</p>

    <?php foreach (FOOTER_PARTNER_SLUGS as $slug): ?>
      <?php
        $srcKey = "footer.partner.$slug.src";
        $altKey = "footer.partner.$slug.alt";
        $iconInputId = "footer-logo-$slug";
      ?>
      <fieldset style="border:1px solid #ddd;padding:1rem;margin-bottom:1rem;">
        <legend><?= htmlspecialchars(ucfirst($slug), ENT_QUOTES) ?></legend>
        <div class="admin-field">
          <label>Image</label>
          <div class="media-field-row">
            <input type="text" name="fields[<?= $srcKey ?>]" id="<?= $iconInputId ?>" value="<?= htmlspecialchars(footer_field_value($footerFields, $srcKey), ENT_QUOTES) ?>" list="media-files" />
            <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="<?= $iconInputId ?>">Parcourir…</button>
          </div>
          <div class="content-block-image-preview" data-preview-for="<?= $iconInputId ?>"><?php $srcVal = footer_field_value($footerFields, $srcKey); ?><?= $srcVal !== '' ? '<img src="../' . htmlspecialchars($srcVal, ENT_QUOTES) . '" alt="" />' : '' ?></div>
        </div>
        <div class="admin-field">
          <label>Texte alternatif (alt)</label>
          <input type="text" name="fields[<?= $altKey ?>]" value="<?= htmlspecialchars(footer_field_value($footerFields, $altKey), ENT_QUOTES) ?>" />
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div>

  <div class="admin-card">
    <h3>Bas de page</h3>
    <div class="admin-field">
      <label>Copyright</label>
      <input type="text" name="fields[footer.copyright]" value="<?= htmlspecialchars(footer_field_value($footerFields, 'footer.copyright'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Lien "Blog" (texte)</label>
      <input type="text" name="fields[footer.link.blog]" value="<?= htmlspecialchars(footer_field_value($footerFields, 'footer.link.blog'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Lien "Mentions Légales" (texte)</label>
      <input type="text" name="fields[footer.link.legal]" value="<?= htmlspecialchars(footer_field_value($footerFields, 'footer.link.legal'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Lien "Politique de Confidentialité" (texte)</label>
      <input type="text" name="fields[footer.link.privacy]" value="<?= htmlspecialchars(footer_field_value($footerFields, 'footer.link.privacy'), ENT_QUOTES) ?>" />
    </div>
  </div>

  <div class="admin-card">
    <h3>Bannière d'aide (réforme 2026)</h3>
    <div class="admin-field">
      <label>Titre (desktop)</label>
      <input type="text" name="fields[banner.labelFull]" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.labelFull'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Titre (mobile)</label>
      <input type="text" name="fields[banner.labelShort]" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.labelShort'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Icône</label>
      <div class="media-field-row">
        <input type="text" name="fields[banner.logo.src]" id="banner-logo-src" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.logo.src'), ENT_QUOTES) ?>" list="media-files" />
        <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="banner-logo-src">Parcourir…</button>
      </div>
      <div class="content-block-image-preview" data-preview-for="banner-logo-src"><?php $bannerSrcVal = footer_field_value($bannerFields, 'banner.logo.src'); ?><?= $bannerSrcVal !== '' ? '<img src="../' . htmlspecialchars($bannerSrcVal, ENT_QUOTES) . '" alt="" />' : '' ?></div>
    </div>
    <div class="admin-field">
      <label>Icône — texte alternatif (alt)</label>
      <input type="text" name="fields[banner.logo.alt]" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.logo.alt'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Texte descriptif</label>
      <textarea name="fields[banner.pillText]"><?= htmlspecialchars(footer_field_value($bannerFields, 'banner.pillText'), ENT_QUOTES) ?></textarea>
    </div>
    <div class="admin-field">
      <label>Texte du bouton</label>
      <input type="text" name="fields[banner.ctaText]" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.ctaText'), ENT_QUOTES) ?>" />
    </div>
    <div class="admin-field">
      <label>Lien du bouton (URL)</label>
      <input type="text" name="fields[banner.ctaHref]" value="<?= htmlspecialchars(footer_field_value($bannerFields, 'banner.ctaHref'), ENT_QUOTES) ?>" placeholder="blog.html" />
    </div>
  </div>

  <button class="admin-btn" type="submit">Enregistrer</button>
</form>

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

<!-- Thumbnail browser for icon fields — the datalist above is a plain text
     autocomplete, not a visual picker, so this covers that gap. -->
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
