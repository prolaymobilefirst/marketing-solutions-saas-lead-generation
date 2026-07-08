<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

admin_require_login();

const SECTIONS = [
    'pages'      => ['label' => 'Pages',        'file' => 'pages.php'],
    'seo'        => ['label' => 'SEO',           'file' => 'seo.php'],
    'media'      => ['label' => 'Médiathèque',   'file' => 'media.php'],
    'favicon'    => ['label' => 'Favicon & Logo', 'file' => 'favicon.php'],
    'pdf'        => ['label' => 'PDF (lead magnet)', 'file' => 'pdf.php'],
    'affiliates' => ['label' => 'Liens affiliés', 'file' => 'affiliates.php'],
    'blog'       => ['label' => 'Blog',          'file' => 'blog.php'],
    'help'       => ['label' => 'Aide',          'file' => 'help.php'],
    'account'    => ['label' => 'Mon compte',    'file' => 'account.php'],
];

$section = $_GET['section'] ?? 'pages';
if (!isset(SECTIONS[$section])) {
    $section = 'pages';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin — <?= htmlspecialchars(SECTIONS[$section]['label'], ENT_QUOTES) ?></title>
<link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-logo">2026 — Admin</div>
      <a href="/" target="_blank" rel="noopener noreferrer" class="admin-view-site">
        Voir le site ↗
      </a>
      <nav>
        <?php foreach (SECTIONS as $key => $meta): ?>
          <a href="dashboard?section=<?= urlencode($key) ?>" class="<?= $key === $section ? 'active' : '' ?>">
            <?= htmlspecialchars($meta['label'], ENT_QUOTES) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <a href="logout" class="admin-logout">Déconnexion</a>
    </aside>
    <main class="admin-content">
      <?php require __DIR__ . '/sections/' . SECTIONS[$section]['file']; ?>
    </main>
  </div>
  <script src="assets/admin.js"></script>
</body>
</html>
