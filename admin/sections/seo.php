<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/htmlpatch.php';
require_once __DIR__ . '/../includes/cms_pages.php';
require_once __DIR__ . '/../includes/site_settings.php';

$fileKey = $_GET['file'] ?? 'index';
if ($fileKey !== 'global' && !isset(CMS_EDITABLE_FILES[$fileKey])) {
    $fileKey = 'index';
}

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fileKey = $_POST['file'] ?? $fileKey;

    if ($fileKey === 'global') {
        try {
            site_settings_save([
                'gtmContainerId' => trim((string) ($_POST['gtmContainerId'] ?? '')),
                'googleSiteVerification' => trim((string) ($_POST['googleSiteVerification'] ?? '')),
                'bingSiteVerification' => trim((string) ($_POST['bingSiteVerification'] ?? '')),
                'favicon' => site_settings_read()['favicon'],
            ]);
            $flash = 'Réglages globaux enregistrés sur toutes les pages.';
        } catch (Throwable $e) {
            $flash = $e->getMessage();
            $flashType = 'error';
        }
    } else {
        try {
            $path = cms_file_path($fileKey);
            $html = file_get_contents($path);
            if ($html === false) {
                throw new RuntimeException('Impossible de lire le fichier.');
            }
            $keyed = cms_scan_keyed($html);
            $updates = [];
            foreach ($_POST['fields'] ?? [] as $key => $value) {
                if (!isset($keyed[$key]) || !str_starts_with($key, 'seo.')) {
                    continue;
                }
                $value = (string) $value;
                if ($keyed[$key]->kind === 'json') {
                    $decoded = json_decode($value);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        throw new RuntimeException("JSON invalide pour « $key » : " . json_last_error_msg());
                    }
                    $value = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                $updates[$key] = $value;
            }
            $patched = cms_patch_many($html, $keyed, $updates);
            flatfile_write_raw($path, $patched);
            $flash = 'Modifications SEO enregistrées.';
        } catch (Throwable $e) {
            $flash = $e->getMessage();
            $flashType = 'error';
        }
    }
}
?>
<h2>SEO &amp; Schema</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-tabs">
  <a href="dashboard?section=seo&amp;file=global" class="<?= $fileKey === 'global' ? 'active' : '' ?>">Global</a>
  <?php foreach (CMS_EDITABLE_FILES as $key => $meta): ?>
    <a href="dashboard?section=seo&amp;file=<?= urlencode($key) ?>" class="<?= $key === $fileKey ? 'active' : '' ?>">
      <?= htmlspecialchars($meta['label'], ENT_QUOTES) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($fileKey === 'global'): ?>

  <?php $settings = site_settings_read(); ?>
  <div class="admin-card">
    <p class="hint">Ces réglages sont injectés automatiquement sur toutes les pages (accueil, quiz, résultat, blog et chaque article) — aucune action supplémentaire requise après l'enregistrement.</p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="file" value="global" />

      <div class="admin-field">
        <label for="f-gtm">ID de conteneur Google Tag Manager</label>
        <input type="text" id="f-gtm" name="gtmContainerId" value="<?= htmlspecialchars($settings['gtmContainerId'], ENT_QUOTES) ?>" placeholder="GTM-XXXXXXX" />
        <span class="hint">Insère le script GTM (head) et le &lt;noscript&gt; (body) sur toutes les pages. Laissez vide pour désactiver.</span>
      </div>
      <div class="admin-field">
        <label for="f-google-verif">Code de vérification Google Search Console</label>
        <input type="text" id="f-google-verif" name="googleSiteVerification" value="<?= htmlspecialchars($settings['googleSiteVerification'], ENT_QUOTES) ?>" placeholder="Contenu de la balise meta, sans les guillemets" />
        <span class="hint">Méthode « balise HTML » de Search Console — collez uniquement la valeur de l'attribut content.</span>
      </div>
      <div class="admin-field">
        <label for="f-bing-verif">Code de vérification Bing Webmaster Tools</label>
        <input type="text" id="f-bing-verif" name="bingSiteVerification" value="<?= htmlspecialchars($settings['bingSiteVerification'], ENT_QUOTES) ?>" placeholder="Contenu de la balise meta, sans les guillemets" />
      </div>

      <button class="admin-btn" type="submit">Enregistrer</button>
    </form>
  </div>

<?php else: ?>

  <?php
  $path = cms_file_path($fileKey);
  $html = file_get_contents($path) ?: '';
  $fields = array_values(array_filter(
      cms_scan($html),
      fn(CmsField $f) => str_starts_with($f->key, 'seo.')
  ));
  ?>

  <?php if ($fileKey === 'blog'): ?>
    <p class="hint">Le schéma JSON-LD de la page Blog (liste des articles) est régénéré automatiquement par le gestionnaire de Blog — il n'apparaît pas ici.</p>
  <?php endif; ?>

  <div class="admin-card">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="file" value="<?= htmlspecialchars($fileKey, ENT_QUOTES) ?>" />

      <?php foreach ($fields as $f): ?>
        <div class="admin-field">
          <label for="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>">
            <?= htmlspecialchars($f->label ?? $f->key, ENT_QUOTES) ?>
            <span class="hint">(<?= htmlspecialchars($f->key, ENT_QUOTES) ?>)</span>
          </label>
          <?php if ($f->kind === 'json'): ?>
            <textarea id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]" style="min-height:200px;font-family:monospace;"><?= htmlspecialchars($f->value, ENT_QUOTES) ?></textarea>
            <span class="hint">JSON-LD brut — doit être un JSON valide.</span>
          <?php elseif (mb_strlen($f->value) > 80): ?>
            <textarea id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]"><?= htmlspecialchars($f->value, ENT_QUOTES) ?></textarea>
          <?php else: ?>
            <input type="text" id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($f->value, ENT_QUOTES) ?>" />
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!$fields): ?>
        <p>Aucun champ SEO trouvé sur cette page.</p>
      <?php else: ?>
        <button class="admin-btn" type="submit">Enregistrer</button>
      <?php endif; ?>
    </form>
  </div>

<?php endif; ?>
