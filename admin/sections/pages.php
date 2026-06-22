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
            if (isset($keyed[$key]) && $keyed[$key]->kind !== 'json') {
                $updates[$key] = (string) $value;
            }
        }
        $patched = cms_patch_many($html, $keyed, $updates);
        flatfile_write_raw($path, $patched);
        $flash = 'Modifications enregistrées.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$path = cms_file_path($fileKey);
$html = file_get_contents($path) ?: '';
$fields = array_values(array_filter(
    cms_scan($html),
    fn(CmsField $f) => $f->kind !== 'json' && !str_starts_with($f->key, 'seo.')
));
?>
<h2>Pages — Textes &amp; images</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-tabs">
  <?php foreach (CMS_EDITABLE_FILES as $key => $meta): ?>
    <a href="dashboard?section=pages&amp;file=<?= urlencode($key) ?>" class="<?= $key === $fileKey ? 'active' : '' ?>">
      <?= htmlspecialchars($meta['label'], ENT_QUOTES) ?>
    </a>
  <?php endforeach; ?>
</div>

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
        <?php if ($f->kind === 'attr' && $f->attr === 'src'): ?>
          <img src="../<?= htmlspecialchars($f->value, ENT_QUOTES) ?>" alt="" style="max-width:120px;max-height:80px;object-fit:contain;margin-bottom:.4rem;" />
          <input type="text" id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($f->value, ENT_QUOTES) ?>" list="media-files" />
        <?php elseif (mb_strlen($f->value) > 80): ?>
          <textarea id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]"><?= htmlspecialchars($f->value, ENT_QUOTES) ?></textarea>
        <?php else: ?>
          <input type="text" id="f-<?= htmlspecialchars($f->key, ENT_QUOTES) ?>" name="fields[<?= htmlspecialchars($f->key, ENT_QUOTES) ?>]" value="<?= htmlspecialchars($f->value, ENT_QUOTES) ?>" />
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$fields): ?>
      <p>Aucun champ éditable trouvé sur cette page.</p>
    <?php else: ?>
      <button class="admin-btn" type="submit">Enregistrer</button>
    <?php endif; ?>
  </form>
</div>

<datalist id="media-files">
  <?php foreach (scandir(__DIR__ . '/../../assets/images') ?: [] as $entry): ?>
    <?php if (is_file(__DIR__ . '/../../assets/images/' . $entry)): ?>
      <option value="assets/images/<?= htmlspecialchars($entry, ENT_QUOTES) ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>
