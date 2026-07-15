<?php
declare(strict_types=1);

const AFFILIATE_LINKS_PATH = __DIR__ . '/../../content/affiliate-links.json';

/* The 2 fixed recommendation buckets, keyed the same way as js/webhook.js's
   CONNEXION_BUCKET. "crm" is shown whenever the visitor's Step 2 priority
   answer was "gestion_crm" (tout-en-un / CRM), overriding Step 3 entirely,
   and otherwise whenever Step 3's current software is Pennylane, Sage,
   Cegid or EBP — all four route to "crm" as the closest analog (Pennylane
   already covers core accounting, so CRM tools are the complementary
   upsell; Cegid was never given its own recommendation set). Slot count
   varies per bucket — "autre" shows 4 recommendations, "crm" only 2 —
   there is no add/delete UI because the counts are architecturally fixed
   per bucket. */
const CONNEXION_BUCKETS = [
    'autre' => ['label' => 'Logiciel actuel : Autre logiciel, Excel, Word...', 'slots' => 4],
    'crm'   => ['label' => 'CRM / tout-en-un : priorité "gestion commerciale", ou logiciel actuel Pennylane, Sage, Cegid ou EBP', 'slots' => 2],
];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $catalog = flatfile_read_json(AFFILIATE_LINKS_PATH, []);

    try {
        $incoming = $_POST['fields'] ?? [];
        foreach (CONNEXION_BUCKETS as $bucket => $config) {
            if (!isset($incoming[$bucket]) || !is_array($incoming[$bucket])) {
                continue;
            }
            $slots = [];
            for ($slot = 0; $slot < $config['slots']; $slot++) {
                $raw = $incoming[$bucket][$slot] ?? [];
                $name = trim((string) ($raw['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException("Le nom est obligatoire pour chaque logiciel ({$config['label']}, emplacement " . ($slot + 1) . ").");
                }
                $entry = [
                    'name'  => $name,
                    'icon'  => trim((string) ($raw['icon'] ?? '')),
                    'badge' => trim((string) ($raw['badge'] ?? '')),
                    'desc'  => trim((string) ($raw['desc'] ?? '')),
                    'href'  => trim((string) ($raw['href'] ?? '')),
                ];
                $ctaText = trim((string) ($raw['ctaText'] ?? ''));
                if ($ctaText !== '') {
                    $entry['ctaText'] = $ctaText;
                }
                $promo = trim((string) ($raw['promo'] ?? ''));
                if ($promo !== '') {
                    $entry['promo'] = $promo;
                }
                if (!empty($raw['topPick'])) {
                    $entry['topPick'] = true;
                }
                $slots[] = $entry;
            }
            $catalog[$bucket] = $slots;
        }
        flatfile_write_json(AFFILIATE_LINKS_PATH, $catalog);
        $flash = 'Catalogue de recommandations enregistré.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$catalog = flatfile_read_json(AFFILIATE_LINKS_PATH, []);
?>
<h2>Liens affiliés</h2>
<p class="hint">Ce catalogue alimente les cartes logiciel affichées sur la page Résultat, choisies selon la réponse à l'Étape 3 du Quiz (logiciel comptable actuel).</p>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <?php foreach (CONNEXION_BUCKETS as $bucket => $config): ?>
    <div class="admin-card">
      <h3><?= htmlspecialchars($config['label'], ENT_QUOTES) ?></h3>
      <?php for ($slot = 0; $slot < $config['slots']; $slot++): ?>
        <?php $entry = $catalog[$bucket][$slot] ?? []; ?>
        <fieldset style="border:1px solid #ddd;padding:1rem;margin-bottom:1rem;">
          <legend>Emplacement <?= $slot + 1 ?></legend>
          <div class="admin-field">
            <label>Nom</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][name]" value="<?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES) ?>" required />
          </div>
          <?php $iconInputId = "affiliate-icon-{$bucket}-{$slot}"; ?>
          <div class="admin-field">
            <label>Icône (chemin image)</label>
            <div class="media-field-row">
              <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][icon]" id="<?= $iconInputId ?>" value="<?= htmlspecialchars($entry['icon'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
              <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="<?= $iconInputId ?>">Parcourir…</button>
            </div>
            <div class="content-block-image-preview" data-preview-for="<?= $iconInputId ?>"><?= !empty($entry['icon']) ? '<img src="../' . htmlspecialchars($entry['icon'], ENT_QUOTES) . '" alt="" />' : '' ?></div>
          </div>
          <div class="admin-field">
            <label>Badge</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][badge]" value="<?= htmlspecialchars($entry['badge'] ?? '', ENT_QUOTES) ?>" placeholder="Partenaire" />
          </div>
          <div class="admin-field">
            <label>Description</label>
            <textarea name="fields[<?= $bucket ?>][<?= $slot ?>][desc]"><?= htmlspecialchars($entry['desc'] ?? '', ENT_QUOTES) ?></textarea>
          </div>
          <div class="admin-field">
            <label>Offre promo (optionnel — étiquette verte affichée sur la carte)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][promo]" value="<?= htmlspecialchars($entry['promo'] ?? '', ENT_QUOTES) ?>" placeholder="1 MOIS OFFERT" />
          </div>
          <div class="admin-field">
            <label>Lien affilié (URL)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][href]" value="<?= htmlspecialchars($entry['href'] ?? '', ENT_QUOTES) ?>" placeholder="https://..." />
          </div>
          <div class="admin-field">
            <label>Texte du bouton (optionnel)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][ctaText]" value="<?= htmlspecialchars($entry['ctaText'] ?? '', ENT_QUOTES) ?>" placeholder="Découvrir →" />
          </div>
          <div class="admin-field">
            <label>
              <input type="checkbox" name="fields[<?= $bucket ?>][<?= $slot ?>][topPick]" value="1" <?= !empty($entry['topPick']) ? 'checked' : '' ?> />
              Meilleur choix (badge "★ Meilleur choix" + bordure mise en avant sur la carte)
            </label>
          </div>
        </fieldset>
      <?php endfor; ?>
    </div>
  <?php endforeach; ?>

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
