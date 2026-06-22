<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

admin_session_start();

if (admin_is_logged_in()) {
    header('Location: dashboard');
    exit;
}

$bootstrap = !admin_has_account();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($bootstrap) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        if ($username === '' || strlen($password) < 10) {
            $error = 'Choisissez un identifiant et un mot de passe d\'au moins 10 caractères.';
        } elseif ($password !== $confirm) {
            $error = 'Les deux mots de passe ne correspondent pas.';
        } else {
            try {
                admin_create_account($username, $password);
                admin_login($username);
                header('Location: dashboard');
                exit;
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $error = "Impossible d'écrire le fichier de compte admin (" . $e->getMessage() . "). Vérifiez que le serveur web peut écrire dans admin/data/.";
            }
        }
    } else {
        try {
            if (login_is_locked_out()) {
                $error = 'Trop de tentatives échouées. Réessayez dans ' . login_seconds_until_unlocked() . ' secondes.';
            } else {
                $username = trim((string) ($_POST['username'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if (admin_verify_credentials($username, $password)) {
                    login_record_success();
                    admin_login($username);
                    header('Location: dashboard');
                    exit;
                }
                login_record_failure();
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = "Erreur serveur lors de la connexion (" . $e->getMessage() . "). Vérifiez que le serveur web peut écrire dans admin/data/.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= $bootstrap ? 'Créer le compte admin' : 'Connexion admin' ?> — Facturation 2026</title>
<link rel="stylesheet" href="assets/admin.css" />
</head>
<body class="admin-auth-body">
  <main class="auth-card">
    <h1><?= $bootstrap ? 'Créer le compte administrateur' : 'Connexion' ?></h1>
    <?php if ($bootstrap): ?>
      <p class="auth-hint">Aucun compte admin n'existe encore — créez le premier (unique) compte.</p>
    <?php endif; ?>
    <?php if ($error): ?>
      <p class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <label>Identifiant
        <input type="text" name="username" required autofocus autocomplete="username" />
      </label>
      <label>Mot de passe
        <input type="password" name="password" required autocomplete="<?= $bootstrap ? 'new-password' : 'current-password' ?>" <?= $bootstrap ? 'minlength="10"' : '' ?> />
      </label>
      <?php if ($bootstrap): ?>
        <label>Confirmer le mot de passe
          <input type="password" name="confirm" required autocomplete="new-password" minlength="10" />
        </label>
      <?php endif; ?>
      <button type="submit"><?= $bootstrap ? 'Créer le compte' : 'Se connecter' ?></button>
    </form>
  </main>
</body>
</html>
