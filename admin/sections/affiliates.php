<?php
declare(strict_types=1);

const AFFILIATE_LINKS_PATH = __DIR__ . '/../../content/affiliate-links.json';

/* The 3 fixed priority buckets, keyed the same way as quiz.html's Step 2
   data-value attributes and sessionStorage's quiz_volume value. Each bucket
   always holds exactly 2 recommendation slots — there is no add/delete UI
   because the slot count is architecturally fixed, unlike the old
   software-keyed catalog this replaces. */
const PRIORITY_BUCKETS = [
    'simple_gratuit'     => 'Priorité : Outil simple et gratuit',
    'automatiser_compta' => 'Priorité : Automatiser la comptabilité',
    'gestion_crm'        => 'Priorité : Gestion tout-en-un / CRM',
];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $catalog = flatfile_read_json(AFFILIATE_LINKS_PATH, []);

    try {
        $incoming = $_POST['fields'] ?? [];
        foreach (PRIORITY_BUCKETS as $bucket => $label) {
            if (!isset($incoming[$bucket]) || !is_array($incoming[$bucket])) {
                continue;
            }
            $slots = [];
            foreach ([0, 1] as $slot) {
                $raw = $incoming[$bucket][$slot] ?? [];
                $name = trim((string) ($raw['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException("Le nom est obligatoire pour chaque logiciel ($label, emplacement " . ($slot + 1) . ").");
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
                $slots[$slot] = $entry;
            }
            $catalog[$bucket] = [$slots[0], $slots[1]];
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
<p class="hint">Ce catalogue alimente les 2 cartes logiciel affichées sur la page Résultat. Chaque priorité (Étape 2 du Quiz) a exactement 2 emplacements fixes.</p>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?>

  <?php foreach (PRIORITY_BUCKETS as $bucket => $label): ?>
    <div class="admin-card">
      <h3><?= htmlspecialchars($label, ENT_QUOTES) ?></h3>
      <?php foreach ([0, 1] as $slot): ?>
        <?php $entry = $catalog[$bucket][$slot] ?? []; ?>
        <fieldset style="border:1px solid #ddd;padding:1rem;margin-bottom:1rem;">
          <legend>Emplacement <?= $slot + 1 ?></legend>
          <div class="admin-field">
            <label>Nom</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][name]" value="<?= htmlspecialchars($entry['name'] ?? '', ENT_QUOTES) ?>" required />
          </div>
          <div class="admin-field">
            <label>Icône (chemin image)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][icon]" value="<?= htmlspecialchars($entry['icon'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
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
            <label>Lien affilié (URL)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][href]" value="<?= htmlspecialchars($entry['href'] ?? '', ENT_QUOTES) ?>" placeholder="https://..." />
          </div>
          <div class="admin-field">
            <label>Texte du bouton (optionnel)</label>
            <input type="text" name="fields[<?= $bucket ?>][<?= $slot ?>][ctaText]" value="<?= htmlspecialchars($entry['ctaText'] ?? '', ENT_QUOTES) ?>" placeholder="Découvrir →" />
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <button class="admin-btn" type="submit">Enregistrer</button>
</form>

<datalist id="media-files">
  <?php foreach (scandir(__DIR__ . '/../../assets/images') ?: [] as $entry): ?>
    <?php if (!str_starts_with($entry, '.') && is_file(__DIR__ . '/../../assets/images/' . $entry)): ?>
      <option value="assets/images/<?= htmlspecialchars($entry, ENT_QUOTES) ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>
