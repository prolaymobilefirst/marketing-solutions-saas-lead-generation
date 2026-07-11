<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/site_settings.php';

const LEAD_MAGNET_DIR = __DIR__ . '/../../server/assets';

$flash = null;
$flashType = 'success';

/** Confirms $filename is a plain basename that resolves to a real .pdf
 *  file inside server/assets — a hand-edited field value can't point
 *  outside it or reference a non-PDF file. */
function pdf_resolve_library_filename(string $filename): ?string
{
    $base = basename($filename);
    if ($base === '' || $base !== $filename || !str_ends_with(strtolower($base), '.pdf')) {
        return null;
    }
    return is_file(LEAD_MAGNET_DIR . '/' . $base) ? $base : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $filename = pdf_resolve_library_filename(trim((string) ($_POST['pdf_filename'] ?? '')));
        if ($filename === null) {
            throw new RuntimeException('Choisissez un PDF valide.');
        }

        $settings = site_settings_read();
        $settings['leadMagnetPdf'] = $filename;
        site_settings_save($settings);

        $flash = 'Le PDF du lead magnet a été mis à jour.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$settings = site_settings_read();
$activeFilename = $settings['leadMagnetPdf'];
$activePath = LEAD_MAGNET_DIR . '/' . $activeFilename;
$activeExists = $activeFilename !== '' && is_file($activePath);

$libraryFiles = [];
foreach (scandir(LEAD_MAGNET_DIR) ?: [] as $entry) {
    if (!str_ends_with(strtolower($entry), '.pdf') || !is_file(LEAD_MAGNET_DIR . '/' . $entry)) {
        continue;
    }
    $libraryFiles[] = [
        'filename' => $entry,
        'sizeKo' => (int) round(filesize(LEAD_MAGNET_DIR . '/' . $entry) / 1024),
        'mtime' => date('d/m/Y H:i', (int) filemtime(LEAD_MAGNET_DIR . '/' . $entry)),
    ];
}
usort($libraryFiles, fn($a, $b) => strcmp($a['filename'], $b['filename']));
?>
<h2>PDF — Plan d'action (lead magnet)</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3>Fichier actuel</h3>
  <?php if ($activeExists): ?>
    <p><?= htmlspecialchars($activeFilename, ENT_QUOTES) ?> — <?= round(filesize($activePath) / 1024) ?> Ko — modifié le <?= date('d/m/Y H:i', filemtime($activePath)) ?></p>
  <?php else: ?>
    <p>Aucun PDF n'est actuellement en place.</p>
  <?php endif; ?>
  <p class="hint">Ce fichier n'est jamais servi par le site — aucune page ni aucune API ne le rend téléchargeable. Il est conservé ici uniquement pour référence ; l'envoi réel du plan d'action se fait par e-mail, automatiquement par Make.com après une soumission de formulaire réussie.</p>
</div>

<div class="admin-card">
  <h3>Choisir le PDF actif</h3>
  <form method="post">
    <?= csrf_field() ?>
    <div class="admin-field">
      <label>Fichier PDF</label>
      <div class="media-field-row">
        <input type="text" name="pdf_filename" id="pdf-filename" value="<?= htmlspecialchars($activeFilename, ENT_QUOTES) ?>" readonly />
        <button type="button" class="admin-btn secondary" data-role="browse-pdf" data-target="pdf-filename">Parcourir…</button>
      </div>
      <span class="hint">Choisissez un PDF déjà téléversé ou téléversez-en un nouveau — 15 Mo maximum.</span>
    </div>
    <button class="admin-btn" type="submit">Enregistrer</button>
  </form>
</div>

<script id="pdf-files-data" type="application/json"><?= json_encode($libraryFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]' ?></script>
<div class="media-picker-overlay" id="pdf-picker-overlay" data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>" hidden>
  <div class="media-picker-modal">
    <div class="media-picker-head">
      <h3>Choisir un PDF</h3>
      <button type="button" id="pdf-picker-close" class="admin-btn secondary">Fermer</button>
    </div>
    <div class="media-picker-upload">
      <input type="file" id="pdf-picker-file" accept=".pdf" hidden />
      <button type="button" id="pdf-picker-upload-btn" class="admin-btn">+ Téléverser depuis mon ordinateur</button>
      <span class="hint">PDF uniquement — 15 Mo maximum.</span>
      <span class="hint" id="pdf-picker-upload-status"></span>
    </div>
    <div class="admin-list-files" id="pdf-picker-list">
      <?php if (!$libraryFiles): ?>
        <p class="hint">Aucun PDF téléversé pour le moment.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
