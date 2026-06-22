<?php
declare(strict_types=1);

require_once __DIR__ . '/flatfile.php';

define('ADMIN_DATA_DIR', __DIR__ . '/../data');
define('ADMIN_USERS_FILE', ADMIN_DATA_DIR . '/admin-users.json');
define('LOGIN_ATTEMPTS_FILE', ADMIN_DATA_DIR . '/login-attempts.json');

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300; // 5 minutes

function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            // Path '/' (not '/admin') and SameSite=Lax (not Strict): some
            // embedded browser previews / forwarded-port proxies (VS Code
            // Simple Browser, devcontainer/codespace tunnels, etc.) don't
            // reliably round-trip a Strict, narrowly-scoped cookie between
            // the GET that renders a form and the POST that submits it,
            // which makes the CSRF check fail on every single submission.
            // Lax still blocks cross-site POST (the actual CSRF vector),
            // so this doesn't weaken the protection.
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function admin_has_account(): bool
{
    return is_file(ADMIN_USERS_FILE);
}

function admin_create_account(string $username, string $password): void
{
    flatfile_write_json(ADMIN_USERS_FILE, [
        'username' => $username,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'createdAt' => date('c'),
    ]);
}

function admin_verify_credentials(string $username, string $password): bool
{
    $user = flatfile_read_json(ADMIN_USERS_FILE);
    if (!$user || !hash_equals($user['username'], $username)) {
        return false;
    }
    return password_verify($password, $user['passwordHash']);
}

/* ── Login rate limiting (file-based, no DB) ───────────────────────────── */

function login_attempts_state(): array
{
    return flatfile_read_json(LOGIN_ATTEMPTS_FILE, ['count' => 0, 'lockedUntil' => 0]);
}

function login_is_locked_out(): bool
{
    $state = login_attempts_state();
    return ($state['lockedUntil'] ?? 0) > time();
}

function login_seconds_until_unlocked(): int
{
    $state = login_attempts_state();
    return max(0, (int) ($state['lockedUntil'] ?? 0) - time());
}

function login_record_failure(): void
{
    $state = login_attempts_state();
    $state['count'] = ($state['count'] ?? 0) + 1;
    if ($state['count'] >= LOGIN_MAX_ATTEMPTS) {
        $state['lockedUntil'] = time() + LOGIN_LOCKOUT_SECONDS;
        $state['count'] = 0;
    }
    flatfile_write_json(LOGIN_ATTEMPTS_FILE, $state);
}

function login_record_success(): void
{
    flatfile_write_json(LOGIN_ATTEMPTS_FILE, ['count' => 0, 'lockedUntil' => 0]);
}

/* ── Session guard ──────────────────────────────────────────────────────── */

function admin_is_logged_in(): bool
{
    admin_session_start();
    return !empty($_SESSION['admin_user']);
}

function admin_require_login(): void
{
    if (!admin_is_logged_in()) {
        header('Location: index');
        exit;
    }
}

function admin_login(string $username): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = $username;
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    session_destroy();
}

/* ── CSRF ───────────────────────────────────────────────────────────────── */

function csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void
{
    admin_session_start();
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Go back and retry.');
    }
}
