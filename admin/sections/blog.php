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
                'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
                'intro' => trim((string) ($_POST['intro'] ?? '')),
                'metaDescription' => trim((string) ($_POST['metaDescription'] ?? '')),
                'datePublished' => $datePublished,
                'dateModified' => date('Y-m-d'),
                'readTime' => trim((string) ($_POST['readTime'] ?? '')) ?: '5 min de lecture',
                'ctaText' => trim((string) ($_POST['ctaText'] ?? '')) ?: 'Démarrer mon Diagnostic →',
                'bodyHtml' => (string) ($_POST['bodyHtml'] ?? ''),
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
        <input type="text" name="icon" value="<?= htmlspecialchars($editing['icon'] ?? '', ENT_QUOTES) ?>" list="media-files" placeholder="assets/images/exemple.webp" />
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
        <label>Corps de l'article (HTML)</label>
        <textarea name="bodyHtml" style="min-height:320px;font-family:monospace;"><?= htmlspecialchars($editing['bodyHtml'] ?? '', ENT_QUOTES) ?></textarea>
        <span class="hint">HTML brut (h3, p, ul/li, strong…) — ce contenu est inséré tel quel, sans échappement.</span>
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

<datalist id="media-files">
  <?php foreach (scandir(__DIR__ . '/../../assets/images') ?: [] as $entry): ?>
    <?php if (is_file(__DIR__ . '/../../assets/images/' . $entry)): ?>
      <option value="assets/images/<?= htmlspecialchars($entry, ENT_QUOTES) ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>
