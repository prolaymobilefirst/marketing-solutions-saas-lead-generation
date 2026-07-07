<?php
declare(strict_types=1);

const AFFILIATE_LINKS_PATH = __DIR__ . '/../../content/affiliate-links.json';
const QUIZ_SOFTWARE_KEYS = ['sage', 'cegid', 'quickbooks', 'pennylane', 'autre'];

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $catalog = flatfile_read_json(AFFILIATE_LINKS_PATH, []);

    try {
        if ($action === 'save') {
            $originalKey = trim((string) ($_POST['original_key'] ?? ''));
            $key = trim((string) ($_POST['key'] ?? ''));
            if (!preg_match('/^[a-z0-9_]+$/', $key)) {
                throw new RuntimeException('La clé doit contenir uniquement des lettres minuscules, chiffres et underscores.');
            }
            if ($originalKey !== '' && $originalKey === 'autre' && $key !== 'autre') {
                throw new RuntimeException("La clé « autre » ne peut pas être renommée (utilisée par la logique du quiz).");
            }
            if ($originalKey !== '' && $originalKey !== $key) {
                unset($catalog[$originalKey]);
            }
            $entry = [
                'name'  => trim((string) ($_POST['name'] ?? '')),
                'icon'  => trim((string) ($_POST['icon'] ?? '')),
                'badge' => trim((string) ($_POST['badge'] ?? '')),
                'desc'  => trim((string) ($_POST['desc'] ?? '')),
                'href'  => trim((string) ($_POST['href'] ?? '')),
            ];
            $ctaText = trim((string) ($_POST['ctaText'] ?? ''));
            if ($ctaText !== '') {
                $entry['ctaText'] = $ctaText;
            }
            if ($entry['name'] === '') {
                throw new RuntimeException('Le nom est obligatoire.');
            }
            $catalog[$key] = $entry;
            flatfile_write_json(AFFILIATE_LINKS_PATH, $catalog);
            $flash = "Logiciel « {$entry['name']} » enregistré.";
        } elseif ($action === 'delete') {
            $key = (string) ($_POST['key'] ?? '');
            if ($key === 'autre') {
                throw new RuntimeException("La clé « autre » ne peut pas être supprimée (utilisée par la logique du quiz).");
            }
            unset($catalog[$key]);
            flatfile_write_json(AFFILIATE_LINKS_PATH, $catalog);
            $flash = 'Logiciel supprimé.';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$catalog = flatfile_read_json(AFFILIATE_LINKS_PATH, []);
$editKey = $_GET['edit'] ?? null;
$editing = $editKey !== null && isset($catalog[$editKey]) ? $catalog[$editKey] : null;
?>
<h2>Liens affiliés</h2>
<p class="hint">Ce catalogue alimente les 3 cartes logiciel affichées sur la page Résultat. Les clés <?= implode(', ', QUIZ_SOFTWARE_KEYS) ?> correspondent aux options du Quiz (Étape 3) et déclenchent la mise en avant « logiciel actuel ».</p>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3><?= $editing ? 'Modifier' : 'Ajouter' ?> un logiciel</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="original_key" value="<?= htmlspecialchars((string) $editKey, ENT_QUOTES) ?>" />

    <div class="admin-field">
      <label>Clé (identifiant unique)</label>
      <input type="text" name="key" value="<?= htmlspecialchars((string) $editKey, ENT_QUOTES) ?>" pattern="[a-z0-9_]+" required <?= $editKey === 'autre' ? 'readonly' : '' ?> />
    </div>
    <div class="admin-field">
      <label>Nom</label>
      <input type="text" name="name" value="<?= htmlspecialchars($editing['name'] ?? '', ENT_QUOTES) ?>" required />
    </div>
    <div class="admin-field">
      <label>Icône (chemin image)</label>
      <input type="text" name="icon" value="<?= htmlspecialchars($editing['icon'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
    </div>
    <div class="admin-field">
      <label>Badge</label>
      <input type="text" name="badge" value="<?= htmlspecialchars($editing['badge'] ?? '', ENT_QUOTES) ?>" placeholder="Certifié PDP" />
    </div>
    <div class="admin-field">
      <label>Description</label>
      <textarea name="desc"><?= htmlspecialchars($editing['desc'] ?? '', ENT_QUOTES) ?></textarea>
    </div>
    <div class="admin-field">
      <label>Lien affilié (URL)</label>
      <input type="text" name="href" value="<?= htmlspecialchars($editing['href'] ?? '', ENT_QUOTES) ?>" placeholder="https://..." />
    </div>
    <div class="admin-field">
      <label>Texte du bouton (optionnel)</label>
      <input type="text" name="ctaText" value="<?= htmlspecialchars($editing['ctaText'] ?? '', ENT_QUOTES) ?>" placeholder="Découvrir →" />
    </div>

    <button class="admin-btn" type="submit">Enregistrer</button>
    <?php if ($editing): ?>
      <a class="admin-btn secondary" href="dashboard?section=affiliates">Annuler</a>
    <?php endif; ?>
  </form>
</div>

<div class="admin-card">
  <h3>Catalogue actuel</h3>
  <table class="admin-table">
    <thead><tr><th>Icône</th><th>Clé</th><th>Nom</th><th>Badge</th><th>Lien</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($catalog as $key => $sw): ?>
        <tr>
          <td><img class="thumb" src="../<?= htmlspecialchars($sw['icon'] ?? '', ENT_QUOTES) ?>" alt="" /></td>
          <td><?= htmlspecialchars($key, ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($sw['name'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($sw['badge'] ?? '', ENT_QUOTES) ?></td>
          <td><a href="<?= htmlspecialchars($sw['href'] ?? '#', ENT_QUOTES) ?>" target="_blank" rel="noopener">↗</a></td>
          <td>
            <a class="admin-btn secondary" style="font-size:.7rem;padding:.3rem .6rem;" href="dashboard?section=affiliates&amp;edit=<?= urlencode($key) ?>">Modifier</a>
            <?php if ($key !== 'autre'): ?>
              <form method="post" style="display:inline" data-confirm="Supprimer ce logiciel ?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="key" value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" />
                <button class="admin-btn danger" style="font-size:.7rem;padding:.3rem .6rem;" type="submit">Suppr.</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<datalist id="media-files">
  <?php foreach (scandir(__DIR__ . '/../../assets/images') ?: [] as $entry): ?>
    <?php if (!str_starts_with($entry, '.') && is_file(__DIR__ . '/../../assets/images/' . $entry)): ?>
      <option value="assets/images/<?= htmlspecialchars($entry, ENT_QUOTES) ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>
