<?php
declare(strict_types=1);

$flash = null;
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = (string) ($_SESSION['admin_user'] ?? '');
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!admin_verify_credentials($username, $current)) {
        $flash = 'Mot de passe actuel incorrect.';
        $flashType = 'error';
    } elseif (strlen($new) < 10) {
        $flash = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
        $flashType = 'error';
    } elseif ($new !== $confirm) {
        $flash = 'Les deux mots de passe ne correspondent pas.';
        $flashType = 'error';
    } else {
        try {
            admin_update_password($new);
            $flash = 'Mot de passe mis à jour.';
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $flash = "Impossible d'enregistrer le nouveau mot de passe.";
            $flashType = 'error';
        }
    }
}
?>
<h2>Mon compte</h2>

<?php if ($flash): ?>
  <div class="admin-flash <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h3>Changer le mot de passe</h3>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="admin-field">
      <label>Mot de passe actuel</label>
      <input type="password" name="current_password" required autocomplete="current-password" />
    </div>
    <div class="admin-field">
      <label>Nouveau mot de passe</label>
      <input type="password" name="new_password" required minlength="10" autocomplete="new-password" />
      <span class="hint">10 caractères minimum.</span>
    </div>
    <div class="admin-field">
      <label>Confirmer le nouveau mot de passe</label>
      <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password" />
    </div>
    <button class="admin-btn" type="submit">Mettre à jour le mot de passe</button>
  </form>
</div>
