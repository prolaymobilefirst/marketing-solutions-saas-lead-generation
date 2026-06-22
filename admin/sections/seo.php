<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/htmlpatch.php';
require_once __DIR__ . '/../includes/cms_pages.php';

$fileKey = $_GET['file'] ?? 'index';
if (!isset(CMS_EDITABLE_FILES[$fileKey])) {
    $fileKey = 'index';
}

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fileKey = $_POST['file'] ?? $fileKey;
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

$path = cms_file_path($fileKey);
$html = file_get_contents($path) ?: '';
$fields = array_values(array_filter(
    cms_scan($html),
    fn(CmsField $f) => str_starts_with($f->key, 'seo.')
));
?>
<h2>SEO &amp; Schema</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-tabs">
  <?php foreach (CMS_EDITABLE_FILES as $key => $meta): ?>
    <a href="dashboard?section=seo&amp;file=<?= urlencode($key) ?>" class="<?= $key === $fileKey ? 'active' : '' ?>">
      <?= htmlspecialchars($meta['label'], ENT_QUOTES) ?>
    </a>
  <?php endforeach; ?>
</div>

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
