<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/blog_render.php';

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save') {
            $slug = trim((string) ($_POST['slug'] ?? ''));
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
                throw new RuntimeException('Le slug doit être en minuscules, chiffres et tirets (ex: mon-article).');
            }
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '') {
                throw new RuntimeException('Le titre est obligatoire.');
            }
            $existing = blog_all_posts();
            $datePublished = trim((string) ($_POST['datePublished'] ?? '')) ?: ($existing[$slug]['datePublished'] ?? date('Y-m-d'));

            $post = [
                'slug' => $slug,
                'title' => $title,
                'badge' => trim((string) ($_POST['badge'] ?? '')),
                'icon' => trim((string) ($_POST['icon'] ?? '')),
                'image' => trim((string) ($_POST['image'] ?? '')),
                'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
                'intro' => trim((string) ($_POST['intro'] ?? '')),
                'metaDescription' => trim((string) ($_POST['metaDescription'] ?? '')),
                'keywords' => trim((string) ($_POST['keywords'] ?? '')),
                'datePublished' => $datePublished,
                'dateModified' => date('Y-m-d'),
                'readTime' => trim((string) ($_POST['readTime'] ?? '')) ?: '5 min de lecture',
                'ctaText' => trim((string) ($_POST['ctaText'] ?? '')) ?: 'Démarrer mon Diagnostic →',
                'contentBlocks' => blog_parse_blocks_from_post((array) ($_POST['blocks'] ?? [])),
            ];

            $originalSlug = trim((string) ($_POST['original_slug'] ?? ''));
            if ($originalSlug !== '' && $originalSlug !== $slug) {
                blog_delete_post($originalSlug);
            }
            blog_save_post($post);
            $flash = "Article « $title » publié.";
        } elseif ($action === 'delete') {
            $slug = (string) ($_POST['slug'] ?? '');
            blog_delete_post($slug);
            $flash = 'Article supprimé.';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$posts = blog_all_posts();
uasort($posts, fn($a, $b) => strcmp($b['datePublished'] ?? '', $a['datePublished'] ?? ''));

$editSlug = $_GET['edit'] ?? null;
$editing = $editSlug !== null && isset($posts[$editSlug]) ? $posts[$editSlug] : null;
$isNew = $_GET['new'] ?? false;
?>
<h2>Blog</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<?php if ($editing || $isNew): ?>
  <div class="admin-card">
    <h3><?= $editing ? 'Modifier l\'article' : 'Nouvel article' ?></h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save" />
      <input type="hidden" name="original_slug" value="<?= htmlspecialchars((string) $editSlug, ENT_QUOTES) ?>" />

      <div class="admin-field">
        <label>Slug (URL : /blog-&lt;slug&gt;.html)</label>
        <input type="text" name="slug" value="<?= htmlspecialchars((string) $editSlug, ENT_QUOTES) ?>" pattern="[a-z0-9]+(-[a-z0-9]+)*" required />
      </div>
      <div class="admin-field">
        <label>Titre</label>
        <input type="text" name="title" value="<?= htmlspecialchars($editing['title'] ?? '', ENT_QUOTES) ?>" required />
      </div>
      <div class="admin-field">
        <label>Badge (catégorie)</label>
        <input type="text" name="badge" value="<?= htmlspecialchars($editing['badge'] ?? '', ENT_QUOTES) ?>" placeholder="Conformité" />
      </div>
      <div class="admin-field">
        <label>Icône</label>
        <div class="media-field-row">
          <input type="text" name="icon" id="blog-icon-input" value="<?= htmlspecialchars($editing['icon'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
          <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="blog-icon-input">Parcourir…</button>
        </div>
        <div class="content-block-image-preview" data-preview-for="blog-icon-input"><?= $editing && !empty($editing['icon']) ? '<img src="../' . htmlspecialchars($editing['icon'], ENT_QUOTES) . '" alt="" />' : '' ?></div>
      </div>
      <div class="admin-field">
        <label>Image de couverture (optionnelle)</label>
        <div class="media-field-row">
          <input type="text" name="image" id="blog-image-input" value="<?= htmlspecialchars($editing['image'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
          <button type="button" class="admin-btn secondary" data-role="browse-image" data-target="blog-image-input">Parcourir…</button>
        </div>
        <div class="content-block-image-preview" data-preview-for="blog-image-input"><?= $editing && !empty($editing['image']) ? '<img src="../' . htmlspecialchars($editing['image'], ENT_QUOTES) . '" alt="" />' : '' ?></div>
        <span class="hint">Si renseignée, cette image remplace la bannière dégradée + icône sur la page /blog. Laissez vide pour garder l'apparence actuelle.</span>
      </div>
      <div class="admin-field">
        <label>Extrait (carte de la page Blog)</label>
        <textarea name="excerpt"><?= htmlspecialchars($editing['excerpt'] ?? '', ENT_QUOTES) ?></textarea>
      </div>
      <div class="admin-field">
        <label>Intro (en tête de l'article)</label>
        <textarea name="intro"><?= htmlspecialchars($editing['intro'] ?? '', ENT_QUOTES) ?></textarea>
      </div>
      <div class="admin-field">
        <label>Meta description (SEO)</label>
        <textarea name="metaDescription"><?= htmlspecialchars($editing['metaDescription'] ?? '', ENT_QUOTES) ?></textarea>
      </div>
      <div class="admin-field">
        <label>Mots-clés (SEO, optionnel)</label>
        <input type="text" name="keywords" value="<?= htmlspecialchars($editing['keywords'] ?? '', ENT_QUOTES) ?>" placeholder="facturation électronique, PDP, conformité 2026" />
      </div>
      <div class="admin-field">
        <label>Date de publication</label>
        <input type="date" name="datePublished" value="<?= htmlspecialchars($editing['datePublished'] ?? date('Y-m-d'), ENT_QUOTES) ?>" />
      </div>
      <div class="admin-field">
        <label>Temps de lecture</label>
        <input type="text" name="readTime" value="<?= htmlspecialchars($editing['readTime'] ?? '5 min de lecture', ENT_QUOTES) ?>" />
      </div>
      <div class="admin-field">
        <label>Texte du bouton final</label>
        <input type="text" name="ctaText" value="<?= htmlspecialchars($editing['ctaText'] ?? 'Démarrer mon Diagnostic →', ENT_QUOTES) ?>" />
      </div>
      <div class="admin-field">
        <label>Corps de l'article</label>
        <?php
          // Legacy posts saved before the block editor only have `bodyHtml` —
          // show that as a single starting text block rather than losing it.
          $initialBlocks = $editing['contentBlocks']
              ?? (!empty($editing['bodyHtml']) ? [['type' => 'text', 'html' => $editing['bodyHtml']]] : []);
        ?>
        <div class="content-blocks" id="content-blocks" data-initial-blocks="<?= htmlspecialchars(json_encode($initialBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES) ?>"></div>
        <div class="content-blocks-add">
          <button type="button" class="admin-btn secondary" data-add-block="text">+ Bloc Texte</button>
          <button type="button" class="admin-btn secondary" data-add-block="image">+ Bloc Image</button>
          <button type="button" class="admin-btn secondary" data-add-block="video">+ Bloc Vidéo YouTube</button>
        </div>
        <span class="hint">Composez l'article en blocs — texte (h2/h3, paragraphes, listes, citations, liens), image ou vidéo YouTube — dans l'ordre exact où ils doivent apparaître sur la page. Les flèches réordonnent un bloc, la corbeille le supprime. Le texte est inséré tel quel, sans échappement.</span>
      </div>

      <button class="admin-btn" type="submit">Publier</button>
      <a class="admin-btn secondary" href="dashboard?section=blog">Annuler</a>
    </form>
  </div>
<?php else: ?>
  <p><a class="admin-btn" href="dashboard?section=blog&amp;new=1">+ Nouvel article</a></p>
<?php endif; ?>

<div class="admin-card">
  <h3>Articles publiés (<?= count($posts) ?>)</h3>
  <table class="admin-table">
    <thead><tr><th></th><th>Titre</th><th>Badge</th><th>Publié le</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $slug => $p): ?>
        <tr>
          <td><img class="thumb" src="../<?= htmlspecialchars($p['icon'] ?? '', ENT_QUOTES) ?>" alt="" /></td>
          <td><?= htmlspecialchars($p['title'] ?? '', ENT_QUOTES) ?> <a href="../blog-<?= urlencode($slug) ?>.html" target="_blank" rel="noopener">↗</a></td>
          <td><?= htmlspecialchars($p['badge'] ?? '', ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($p['datePublished'] ?? '', ENT_QUOTES) ?></td>
          <td>
            <a class="admin-btn secondary" style="font-size:.7rem;padding:.3rem .6rem;" href="dashboard?section=blog&amp;edit=<?= urlencode($slug) ?>">Modifier</a>
            <form method="post" style="display:inline" data-confirm="Supprimer cet article définitivement ?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" />
              <button class="admin-btn danger" style="font-size:.7rem;padding:.3rem .6rem;" type="submit">Suppr.</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
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

<!-- Thumbnail browser for image content blocks — the datalist above is a
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
